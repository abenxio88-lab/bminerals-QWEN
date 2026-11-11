<?php
/**
 * Balochistan Minerals - Protected Vault File & Thumbnail Streamer
 * Supports three strictly isolated access modes:
 *   1. Authenticated Admin Session (isLoggedIn) -> full deal vault access
 *   2. Valid Buyer Access Token (?code=...&k=...&doc_id=...) -> buyer_portal & public_sample only
 *   3. Public Sample Document (?public_doc_id=...) -> visibility = 'public_sample' only
 */

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/vault-helpers.php';

$pdo = getDB();
if (!$pdo) {
    http_response_code(503);
    echo 'Database unavailable.';
    exit;
}

ensureDealVaultSchema($pdo);

$wantThumb   = !empty($_GET['thumb']);
$preferInline = empty($_GET['download']);
$publicDocId = (int)($_GET['public_doc_id'] ?? 0);
$docId       = (int)($_GET['doc_id'] ?? 0);
$code        = cleanInput($_GET['code'] ?? '');
$token       = trim((string)($_GET['k'] ?? ''));

$row = null;

if ($publicDocId > 0) {
    $stmt = $pdo->prepare("SELECT dd.*, fb.storage_key, fb.thumb_key, fb.mime_type
        FROM `deal_documents` dd
        JOIN `file_blobs` fb ON fb.id = dd.blob_id
        WHERE dd.id = ? AND dd.visibility = 'public_sample' AND dd.doc_status != 'Superseded'
        LIMIT 1");
    $stmt->execute([$publicDocId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
} elseif ($code !== '' && $docId > 0) {
    $stmtDeal = $pdo->prepare("SELECT id, is_public_demo FROM `deals` WHERE tracking_code = ? LIMIT 1");
    $stmtDeal->execute([$code]);
    $deal = $stmtDeal->fetch(PDO::FETCH_ASSOC);

    if ($deal) {
        $authorized = false;
        if ((int)$deal['is_public_demo'] === 1) {
            $authorized = true;
        } elseif ($token !== '') {
            $tokStmt = $pdo->prepare("SELECT id FROM `deal_access_tokens` WHERE deal_id = ? AND token = ? AND is_active = 1 LIMIT 1");
            $tokStmt->execute([$deal['id'], $token]);
            if ($tokStmt->fetchColumn()) {
                $authorized = true;
            }
        }

        if ($authorized) {
            $stmt = $pdo->prepare("SELECT dd.*, fb.storage_key, fb.thumb_key, fb.mime_type
                FROM `deal_documents` dd
                JOIN `file_blobs` fb ON fb.id = dd.blob_id
                WHERE dd.id = ? AND dd.deal_id = ?
                  AND dd.visibility IN ('buyer_portal', 'public_sample')
                  AND dd.doc_status != 'Superseded'
                LIMIT 1");
            $stmt->execute([$docId, $deal['id']]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
        }
    }
} elseif (isLoggedIn() && $docId > 0) {
    requireAuth();
    $canFinVault = canViewDealFinancials();
    releaseSessionLock();
    $stmt = $pdo->prepare("SELECT dd.*, fb.storage_key, fb.thumb_key, fb.mime_type
        FROM `deal_documents` dd
        JOIN `file_blobs` fb ON fb.id = dd.blob_id
        WHERE dd.id = ? LIMIT 1");
    $stmt->execute([$docId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row && !$canFinVault) {
        $restrictedCats = ['spa', 'proforma_invoice', 'lc_swift', 'form_e', 'final_invoice', 'bank_prc', 'royalty_pass'];
        if ($row['visibility'] === 'internal_only' && in_array($row['doc_category'], $restrictedCats, true)) {
            $row = null;
        }
    }
} else {
    http_response_code(401);
    echo 'Authentication or valid access token required.';
    exit;
}

if (!$row) {
    http_response_code(404);
    echo 'Document not found or permission denied.';
    exit;
}

$useKey = ($wantThumb && !empty($row['thumb_key'])) ? $row['thumb_key'] : $row['storage_key'];
$useMime = ($wantThumb && !empty($row['thumb_key']))
    ? (strtolower(pathinfo($row['thumb_key'], PATHINFO_EXTENSION)) === 'jpg' ? 'image/jpeg' : 'image/webp')
    : $row['mime_type'];

$absPath = resolveVaultBlobAbsPath($useKey);
if (!$absPath) {
    http_response_code(404);
    echo 'Stored asset unavailable.';
    exit;
}

$outFilename = ($wantThumb && !empty($row['thumb_key']))
    ? pathinfo($row['original_filename'], PATHINFO_FILENAME) . '_thumb.webp'
    : $row['original_filename'];

streamVaultBlobToHttp($absPath, $useMime, $outFilename, $preferInline);
