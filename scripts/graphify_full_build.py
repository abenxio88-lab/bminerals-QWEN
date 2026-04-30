import json
import re
from dataclasses import dataclass
from pathlib import Path
from typing import Dict, Iterable, List, Optional, Set, Tuple


EXCLUDE_DIR_PARTS = {
    "node_modules",
    "vendor",
    "backups",
    "_backups",
    "graphify-out",
    ".git",
}


def _is_excluded(path: Path) -> bool:
    parts = {p.lower() for p in path.parts}
    return any(p in parts for p in EXCLUDE_DIR_PARTS)


def _norm_rel(root: Path, path: Path) -> str:
    try:
        return str(path.resolve().relative_to(root.resolve())).replace("\\", "/")
    except Exception:
        return str(path).replace("\\", "/")


def _safe_id(prefix: str, rel_path: str) -> str:
    # graphify node ids are arbitrary strings; keep them stable and filesystem-derived.
    s = f"{prefix}:{rel_path}"
    return re.sub(r"[^a-zA-Z0-9:_/.\-]+", "_", s)


def _read_text(path: Path) -> str:
    return path.read_text(encoding="utf-8", errors="ignore")


HREF_RE = re.compile(r"""href\s*=\s*["']([^"']+)["']""", re.IGNORECASE)
SRC_RE = re.compile(r"""src\s*=\s*["']([^"']+)["']""", re.IGNORECASE)
CSS_URL_RE = re.compile(r"""url\(\s*['"]?([^'")]+)['"]?\s*\)""", re.IGNORECASE)
CSS_IMPORT_RE = re.compile(r"""@import\s+(?:url\()?['"]([^'"]+)['"]\)?""", re.IGNORECASE)


def _strip_query_fragment(s: str) -> str:
    return s.split("#", 1)[0].split("?", 1)[0]


def _looks_external(ref: str) -> bool:
    r = ref.strip().lower()
    return (
        r.startswith("http://")
        or r.startswith("https://")
        or r.startswith("//")
        or r.startswith("data:")
        or r.startswith("mailto:")
        or r.startswith("tel:")
    )


def _resolve_ref(from_file: Path, ref: str, root: Path) -> Optional[Path]:
    ref = _strip_query_fragment(ref.strip())
    if not ref or _looks_external(ref):
        return None
    if ref.startswith("/"):
        # Site-root absolute: treat as repo-root relative
        target = root / ref.lstrip("/")
    else:
        target = (from_file.parent / ref)
    try:
        target = target.resolve()
    except Exception:
        target = target
    if not target.exists():
        return None
    if _is_excluded(target):
        return None
    return target


@dataclass(frozen=True)
class Node:
    node_id: str
    label: str
    file_type: str
    source_file: str


def _node_for_file(root: Path, path: Path) -> Node:
    rel = _norm_rel(root, path)
    ext = path.suffix.lower()
    if ext in {".js", ".mjs", ".cjs"}:
        ft = "code"
        prefix = "code"
    elif ext in {".css"}:
        ft = "document"
        prefix = "css"
    elif ext in {".html", ".htm", ".md", ".txt"}:
        ft = "document"
        prefix = "doc"
    elif ext in {".png", ".jpg", ".jpeg", ".webp", ".avif", ".svg", ".ico"}:
        ft = "image"
        prefix = "img"
    else:
        ft = "document"
        prefix = "file"
    return Node(
        node_id=_safe_id(prefix, rel),
        label=rel,
        file_type=ft,
        source_file=rel,
    )


def _edge(source: Node, target: Node, relation: str) -> Dict:
    # Graphify's code-only rebuild hook preserves:
    # - all non-code nodes, and
    # - semantic edges that are INFERRED/AMBIGUOUS, plus non-code↔non-code edges.
    # To keep doc/image -> code reference edges across auto-updates, mark those as INFERRED.
    confidence = "EXTRACTED"
    confidence_score = 1.0
    if source.file_type != "code" or target.file_type != "code":
        if source.file_type == "code" or target.file_type == "code":
            confidence = "INFERRED"
            confidence_score = 0.95
    return {
        "source": source.node_id,
        "target": target.node_id,
        "relation": relation,
        "confidence": confidence,
        "confidence_score": confidence_score,
        "source_file": source.source_file,
        "source_location": None,
        "weight": 1.0,
    }


def _collect_site_files(root: Path) -> Tuple[List[Path], List[Path], List[Path]]:
    html: List[Path] = []
    css: List[Path] = []
    js: List[Path] = []
    for p in root.rglob("*"):
        if not p.is_file():
            continue
        if _is_excluded(p):
            continue
        ext = p.suffix.lower()
        if ext in {".html", ".htm"}:
            html.append(p)
        elif ext == ".css":
            css.append(p)
        elif ext in {".js", ".mjs", ".cjs"}:
            js.append(p)
    return html, css, js


def _extract_static_links(root: Path, html_files: Iterable[Path], css_files: Iterable[Path]) -> Tuple[Dict[str, Node], List[Dict]]:
    nodes: Dict[str, Node] = {}
    edges: List[Dict] = []

    def ensure(path: Path) -> Node:
        n = _node_for_file(root, path)
        nodes.setdefault(n.node_id, n)
        return nodes[n.node_id]

    # HTML: href/src references
    for f in html_files:
        src_node = ensure(f)
        text = _read_text(f)
        for ref in HREF_RE.findall(text):
            tgt = _resolve_ref(f, ref, root)
            if tgt:
                tgt_node = ensure(tgt)
                edges.append(_edge(src_node, tgt_node, "references"))
        for ref in SRC_RE.findall(text):
            tgt = _resolve_ref(f, ref, root)
            if tgt:
                tgt_node = ensure(tgt)
                edges.append(_edge(src_node, tgt_node, "references"))

    # CSS: @import and url(...) assets
    for f in css_files:
        src_node = ensure(f)
        text = _read_text(f)
        for ref in CSS_IMPORT_RE.findall(text):
            tgt = _resolve_ref(f, ref, root)
            if tgt:
                tgt_node = ensure(tgt)
                edges.append(_edge(src_node, tgt_node, "imports"))
        for ref in CSS_URL_RE.findall(text):
            tgt = _resolve_ref(f, ref, root)
            if tgt:
                tgt_node = ensure(tgt)
                edges.append(_edge(src_node, tgt_node, "references"))

    return nodes, edges


def main() -> int:
    root = Path(".").resolve()

    from graphify.extract import extract
    from graphify.detect import detect
    from graphify.build import build_from_json
    from graphify.cluster import cluster, score_all
    from graphify.analyze import god_nodes, surprising_connections, suggest_questions
    from graphify.report import generate
    from graphify.export import to_json, to_html

    detected = detect(root)
    html_files, css_files, js_files = _collect_site_files(root)

    # AST extraction: only for our JS (skip vendor/ and other excluded dirs).
    code_files = [p for p in js_files if not _is_excluded(p)]
    ast = extract(code_files, cache_root=root) if code_files else {"nodes": [], "edges": [], "hyperedges": [], "input_tokens": 0, "output_tokens": 0}

    # Deterministic semantic-ish extraction for HTML/CSS/asset connections.
    sem_nodes, sem_edges = _extract_static_links(root, html_files, css_files)

    # Merge: keep AST ids as-is, add our document/image nodes and edges.
    nodes = list(ast.get("nodes", []))
    edges = list(ast.get("edges", []))

    existing_ids = {n.get("id") for n in nodes if isinstance(n, dict)}
    for n in sem_nodes.values():
        if n.node_id in existing_ids:
            continue
        nodes.append(
            {
                "id": n.node_id,
                "label": n.label,
                "file_type": n.file_type,
                "source_file": n.source_file,
                "source_location": None,
                "source_url": None,
                "captured_at": None,
                "author": None,
                "contributor": None,
            }
        )
    edges.extend(sem_edges)

    result = {
        "nodes": nodes,
        "edges": edges,
        "hyperedges": ast.get("hyperedges", []),
        "input_tokens": 0,
        "output_tokens": 0,
    }

    detection = {
        "files": {
            "code": [_norm_rel(root, p) for p in code_files],
            "document": [_norm_rel(root, p) for p in list(html_files) + list(css_files)],
            "paper": [],
            "image": [f for f in detected.get("files", {}).get("image", []) if not _is_excluded(Path(f))],
        },
        "total_files": len(code_files) + len(html_files) + len(css_files),
        "total_words": detected.get("total_words", 0),
        "warning": detected.get("warning"),
    }

    G = build_from_json(result)
    communities = cluster(G)
    cohesion = score_all(G, communities)
    gods = god_nodes(G)
    surprises = surprising_connections(G, communities)
    labels = {cid: "Community " + str(cid) for cid in communities}
    questions = suggest_questions(G, communities, labels)

    out = root / "graphify-out"
    out.mkdir(exist_ok=True)

    report = generate(
        G,
        communities,
        cohesion,
        labels,
        gods,
        surprises,
        detection,
        {"input": 0, "output": 0},
        str(root),
        suggested_questions=questions,
    )
    (out / "GRAPH_REPORT.md").write_text(report, encoding="utf-8")
    to_json(G, communities, str(out / "graph.json"))
    to_html(G, communities, str(out / "graph.html"), community_labels=labels or None)

    print(f"[graphify full] Built: {G.number_of_nodes()} nodes, {G.number_of_edges()} edges, {len(communities)} communities")
    print(f"[graphify full] Outputs: {out / 'graph.json'}, {out / 'graph.html'}, {out / 'GRAPH_REPORT.md'}")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
