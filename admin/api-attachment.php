<?php
/**
 * Balochistan Minerals - Authenticated Email Attachment & Inline CID Image Endpoint
 * Serves email attachments and inline CID signature images only to authenticated admin users,
 * strictly verified against stored DB metadata inside admin/uploads/attachments.
 */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/mail-helpers.php';

if (!isLoggedIn()) {
    http_response_code(401);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Authentication required.';
    exit;
}

$pdo = getDB();
if (!$pdo) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Database unavailable.';
    exit;
}

$msgId = (int)($_GET['msg_id'] ?? 0);
if ($msgId <= 0) {
    http_response_code(400);
    echo 'Invalid message ID.';
    exit;
}

$stmt = $pdo->prepare("SELECT id, attachments FROM email_messages WHERE id = ? LIMIT 1");
$stmt->execute([$msgId]);
$msgRow = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$msgRow) {
    http_response_code(404);
    echo 'Message not found.';
    exit;
}

$attachments = decodeAttachmentsMeta($msgRow['attachments'] ?? '');
$targetItem  = null;
$isCidMode   = false;

if (isset($_GET['cid']) && trim((string)$_GET['cid']) !== '') {
    $wantCid   = strtolower(trim((string)$_GET['cid'], " <>"));
    $isCidMode = true;
    foreach ($attachments as $att) {
        if (!empty($att['cid']) && strtolower(trim((string)$att['cid'], " <>")) === $wantCid) {
            $targetItem = $att;
            break;
        }
    }
} elseif (isset($_GET['idx'])) {
    $idx = (int)$_GET['idx'];
    if (isset($attachments[$idx])) {
        $targetItem = $attachments[$idx];
    }
}

if (!$targetItem || empty($targetItem['path'])) {
    http_response_code(404);
    echo 'Attachment not found.';
    exit;
}

$absPath = resolveAttachmentAbsPath($targetItem['path']);
if (!$absPath || !is_file($absPath)) {
    http_response_code(404);
    echo 'Attachment file missing from storage.';
    exit;
}

$mime = (string)($targetItem['mime'] ?? 'application/octet-stream');
$name = sanitizeMailFilename($targetItem['name'] ?? basename($absPath));
$ext  = strtolower(pathinfo($name, PATHINFO_EXTENSION));

if ($isCidMode && stripos($mime, 'image/') !== 0) {
    http_response_code(403);
    echo 'Invalid inline media type.';
    exit;
}

$forceDownload = isset($_GET['download']) && $_GET['download'] === '1';
$allowInline   = !$forceDownload && ($isCidMode || in_array($ext, ['pdf', 'jpg', 'jpeg', 'png', 'gif', 'webp', 'txt'], true));
$disposition   = $allowInline ? 'inline' : 'attachment';

// Prevent active HTML/SVG rendering even if mislabeled
if (in_array($ext, ['html', 'htm', 'svg', 'js', 'php'], true)) {
    $disposition = 'attachment';
    $mime = 'application/octet-stream';
}

header('X-Content-Type-Options: nosniff');
header("Content-Security-Policy: default-src 'none'; img-src 'self'; sandbox");
header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize($absPath));
header('Content-Disposition: ' . $disposition . '; filename="' . str_replace('"', '', $name) . '"; filename*=UTF-8\'\'' . rawurlencode($name));
header('Cache-Control: private, max-age=' . ($isCidMode ? '86400' : '3600'));

readfile($absPath);
exit;

