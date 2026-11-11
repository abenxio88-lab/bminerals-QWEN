<?php
/**
 * Balochistan Minerals - Scoped Mobile Field Upload Drop-Page
 * Lets yard supervisors & C&F clearing agents upload WebP-compressed photos/scans
 * into a single authorized deal stage without exposing pricing, buyer contacts, or financials.
 */

require_once __DIR__ . '/vault-helpers.php';

$pdo = getDB();
$token = trim((string)($_GET['t'] ?? $_POST['t'] ?? ''));

$linkRow = null;
$dealRow = null;
$msRow   = null;
$stageMeta = null;
$errMsg  = '';

if ($pdo && $token !== '') {
    ensureDealVaultSchema($pdo);
    $st = $pdo->prepare("SELECT * FROM `deal_field_links` WHERE token = ? AND is_active = 1 AND (expires_at IS NULL OR expires_at > NOW()) LIMIT 1");
    $st->execute([$token]);
    $linkRow = $st->fetch(PDO::FETCH_ASSOC);

    if ($linkRow) {
        $dSt = $pdo->prepare("SELECT id, deal_code, tracking_code, commodity, contracted_mt, port_of_loading, port_of_discharge, deal_state FROM `deals` WHERE id = ? LIMIT 1");
        $dSt->execute([$linkRow['deal_id']]);
        $dealRow = $dSt->fetch(PDO::FETCH_ASSOC);

        if ($dealRow && in_array($dealRow['deal_state'], ['Closed Settled', 'Archived'], true)) {
            $errMsg = 'This export deal has been sealed & closed. Uploads are locked.';
            $linkRow = null;
        } elseif ($dealRow) {
            $mSt = $pdo->prepare("SELECT * FROM `deal_milestones` WHERE deal_id = ? AND stage_key = ? LIMIT 1");
            $mSt->execute([$dealRow['id'], $linkRow['stage_key']]);
            $msRow = $mSt->fetch(PDO::FETCH_ASSOC);
            foreach (getDealStageDefinitions() as $def) {
                if ($def['key'] === $linkRow['stage_key']) {
                    $stageMeta = $def;
                    break;
                }
            }
        }
    }
}

// POST JSON upload handler
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json; charset=utf-8');
    header('X-Content-Type-Options: nosniff');

    if (!$linkRow || !$dealRow || !$msRow) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => $errMsg ?: 'Invalid or expired field upload link.']);
        exit;
    }

    $cat       = preg_replace('/[^a-zA-Z0-9_]/', '_', trim((string)($_POST['doc_category'] ?? 'general_evidence')));
    $title     = trim((string)($_POST['title'] ?? ''));
    $origBytes = (int)($_POST['orig_client_bytes'] ?? 0);
    $note      = trim((string)($_POST['field_note'] ?? ''));

    $stored = storeUploadedVaultFile($pdo, $_FILES['file'] ?? null, $_FILES['thumb'] ?? null, $origBytes);
    if (empty($stored['success'])) {
        echo json_encode(['success' => false, 'error' => $stored['error'] ?? 'Upload rejected.']);
        exit;
    }

    $maxVerStmt = $pdo->prepare("SELECT COALESCE(MAX(doc_version), 0) FROM `deal_documents` WHERE deal_id = ? AND milestone_id = ? AND doc_category = ?");
    $maxVerStmt->execute([$dealRow['id'], $msRow['id'], $cat]);
    $nextVer = ((int)$maxVerStmt->fetchColumn()) + 1;

    if ($title === '') $title = $stored['safe_name'];
    $actor = 'Field: ' . ($linkRow['assignee_label'] ?: 'Supervisor');

    $ins = $pdo->prepare("INSERT INTO `deal_documents`
        (`deal_id`, `milestone_id`, `blob_id`, `doc_category`, `title`, `original_filename`, `doc_version`, `doc_status`, `visibility`, `uploaded_by`)
        VALUES (?, ?, ?, ?, ?, ?, ?, 'Under Review', 'internal_only', ?)");
    $ins->execute([$dealRow['id'], $msRow['id'], $stored['blob_id'], $cat, $title, $stored['safe_name'], $nextVer, $actor]);

    if ($note !== '') {
        $prevRem = trim((string)($msRow['internal_remarks'] ?? ''));
        $appended = ($prevRem !== '' ? $prevRem . "\n" : '') . '[' . date('Y-m-d H:i') . ' ' . $actor . '] ' . $note;
        $pdo->prepare("UPDATE `deal_milestones` SET internal_remarks = ?, status = IF(status='Pending','In Progress',status) WHERE id = ?")
            ->execute([$appended, $msRow['id']]);
    }

    $pdo->prepare("UPDATE `deal_field_links` SET uploads_count = uploads_count + 1, last_used_at = NOW() WHERE id = ?")->execute([$linkRow['id']]);
    logDealAudit($pdo, (int)$dealRow['id'], 'field_upload', "Field upload '{$title}' into {$msRow['stage_title']}", $actor, 'Field');
    logAdminNotification($pdo, 'info', 'deals', 'Field Evidence Uploaded', "{$dealRow['deal_code']}: {$actor} uploaded {$title}", null, "deals.php?id={$dealRow['id']}");

    echo json_encode([
        'success'      => true,
        'stored_bytes' => $stored['byte_size'],
        'orig_bytes'   => max($origBytes, $stored['byte_size']),
    ]);
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Field Evidence Upload — Balochistan Minerals</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <style>
    body { font-family: system-ui, -apple-system, sans-serif; background: #F1F5F9; padding: 18px 12px; color: #0F172A; }
    .field-card { max-width: 560px; margin: 0 auto; background: #FFF; border-radius: 12px; border: 1px solid #CBD5E1; padding: 24px; box-shadow: 0 8px 24px rgba(15,23,42,0.06); }
    .drop-area { border: 2px dashed #94A3B8; border-radius: 10px; padding: 22px 14px; text-align: center; background: #F8FAFC; cursor: pointer; }
  </style>
</head>
<body>
  <div class="field-card">
    <div class="d-flex justify-content-between align-items-center pb-3 mb-3 border-bottom">
      <div>
        <div class="fw-bold" style="font-size:1.05rem;">Balochistan Minerals</div>
        <div class="small text-muted">Operations Field Evidence Portal</div>
      </div>
      <span class="badge bg-dark">Mobile Optimized</span>
    </div>

    <?php if (!$linkRow || !$dealRow || !$msRow): ?>
      <div class="alert alert-danger mb-0">
        <i class="bi bi-shield-exclamation me-1"></i> <?= htmlspecialchars($errMsg ?: 'This field upload link is invalid, expired, or revoked. Contact the Export Desk for a new link.') ?>
      </div>
    <?php else: ?>
      <div class="p-3 rounded bg-light border mb-3">
        <div class="small text-muted">Assigned Officer: <strong><?= htmlspecialchars($linkRow['assignee_label']) ?></strong></div>
        <div class="fw-bold mt-1"><?= htmlspecialchars($dealRow['deal_code']) ?> — <?= htmlspecialchars($dealRow['commodity']) ?> (<?= number_format((float)$dealRow['contracted_mt'], 0) ?> MT)</div>
        <div class="small text-primary fw-semibold mt-1">Stage <?= (int)$msRow['stage_order'] ?>: <?= htmlspecialchars($msRow['stage_title']) ?></div>
      </div>

      <div class="mb-3">
        <label class="form-label small fw-bold">Document / Photo Type</label>
        <select id="fCategory" class="form-select form-select-sm">
          <?php
            $blockedCats = ['spa', 'proforma_invoice', 'lc_swift', 'form_e', 'final_invoice', 'bank_prc'];
            foreach (($stageMeta['doc_categories'] ?? []) as $k => $lbl):
              if (in_array($k, $blockedCats, true)) continue;
          ?>
            <option value="<?= htmlspecialchars($k) ?>"><?= htmlspecialchars($lbl) ?></option>
          <?php endforeach; ?>
          <option value="general_evidence">General Operational Photo / Slip</option>
        </select>
      </div>

      <div class="mb-3">
        <label class="form-label small fw-bold">Batch / Truck / Seal Note (Optional)</label>
        <textarea id="fNote" class="form-control form-control-sm" rows="2" placeholder="e.g. Truck #KTA-4412, Weighbridge slip 25.4 MT, Seal #8821..."></textarea>
      </div>

      <div class="drop-area mb-3" onclick="document.getElementById('fPicker').click()">
        <input type="file" id="fPicker" multiple accept="image/*,.pdf" capture="environment" style="display:none;" onchange="prepareFieldQueue(this.files)">
        <i class="bi bi-camera fs-2 text-dark"></i>
        <div class="fw-bold small mt-1">Tap to Take Photos or Select PDF Scans</div>
        <div class="text-muted" style="font-size:0.75rem;">Auto-compressed in browser to save mobile data on slow networks.</div>
      </div>

      <div id="fPreview" class="mb-3"></div>
      <div id="fStatus" class="mb-2"></div>
      <button type="button" id="fSendBtn" class="btn btn-dark w-100 fw-bold py-2" style="display:none;" onclick="sendFieldQueue()">
        <i class="bi bi-cloud-upload me-1"></i> Send Evidence to Head Office Vault
      </button>
    <?php endif; ?>
  </div>

  <script src="js/vault-compressor.js?v=<?= filemtime(__DIR__ . '/js/vault-compressor.js') ?>"></script>
  <script>
    const FIELD_TOKEN = <?= json_encode($token) ?>;
    let queue = [];

    async function prepareFieldQueue(files) {
      if (!files || !files.length) return;
      const prev = document.getElementById('fPreview');
      prev.innerHTML = '<div class="small text-primary">Compressing photos for fast mobile upload...</div>';
      for (const f of files) {
        queue.push(await BMVaultCompressor.compressFile(f));
      }
      prev.innerHTML = queue.map(q => `
        <div class="d-flex justify-content-between align-items-center p-2 border rounded mb-1 bg-light">
          <span class="small fw-semibold text-truncate" style="max-width:65%;">${q.filename}</span>
          <span class="badge bg-success-subtle text-success">${BMVaultCompressor.formatBytes(q.origBytes)} → ${BMVaultCompressor.formatBytes(q.finalBytes)}</span>
        </div>`).join('');
      document.getElementById('fSendBtn').style.display = queue.length ? 'block' : 'none';
    }

    async function sendFieldQueue() {
      const btn = document.getElementById('fSendBtn');
      const st  = document.getElementById('fStatus');
      btn.disabled = true;
      btn.textContent = 'Uploading...';

      let ok = 0;
      const cat = document.getElementById('fCategory').value;
      const note = document.getElementById('fNote').value;
      for (let i = 0; i < queue.length; i++) {
        const item = queue[i];
        const fd = new FormData();
        fd.append('t', FIELD_TOKEN);
        fd.append('doc_category', cat);
        fd.append('title', item.filename.replace(/\.[^.]+$/, ''));
        fd.append('orig_client_bytes', item.origBytes);
        if (i === 0 && note) fd.append('field_note', note);
        fd.append('file', item.file, item.filename);
        if (item.thumb) fd.append('thumb', item.thumb, 'thumb.webp');

        const res = await fetch('field-upload.php', { method: 'POST', body: fd });
        const data = await res.json();
        if (data.success) ok++;
      }

      queue = [];
      document.getElementById('fPreview').innerHTML = '';
      document.getElementById('fNote').value = '';
      btn.style.display = 'none';
      btn.disabled = false;
      btn.innerHTML = '<i class="bi bi-cloud-upload me-1"></i> Send Evidence to Head Office Vault';
      st.innerHTML = `<div class="alert alert-success py-2 small mb-0"><i class="bi bi-check-circle me-1"></i> Sent ${ok} file(s) safely to the Export Desk Vault (Pending Review).</div>`;
    }
  </script>
</body>
</html>
