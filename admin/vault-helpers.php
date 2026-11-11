<?php
/**
 * Balochistan Minerals - Protected Deal Evidence Vault & Compression Storage Helper
 * Handles SHA-256 deduplicated storage, secure path resolution, optional GD thumbnail fallback,
 * protected HTTP streaming, and multi-stage ZIP proof pack compilation.
 */

require_once __DIR__ . '/mail-helpers.php';
require_once __DIR__ . '/deal-helpers.php';

if (!defined('BM_VAULT_MAX_FILE_BYTES')) {
    define('BM_VAULT_MAX_FILE_BYTES', 15 * 1024 * 1024); // 15 MB hard ceiling per file
}

if (!defined('BM_VAULT_DIR')) {
    define('BM_VAULT_DIR', __DIR__ . '/uploads/deals');
}

/**
 * Ensures the vault root and optional monthly subdirectory exist and are HTTP-protected.
 */
function ensureVaultDirectory($relSubDir = '') {
    $base = rtrim(BM_VAULT_DIR, '/\\');
    $normalizedBase = str_replace('\\', '/', $base);
    $normalizedAdminUploads = str_replace('\\', '/', __DIR__ . '/uploads');

    if (strpos($normalizedBase, $normalizedAdminUploads) === 0) {
        ensureProtectedUploadDir($base, true);
    } elseif (!is_dir($base)) {
        @mkdir($base, 0755, true);
    }

    if ($relSubDir !== '') {
        $sub = $base . '/' . trim($relSubDir, '/\\');
        if (!is_dir($sub)) {
            @mkdir($sub, 0755, true);
        }
        return $sub;
    }

    return $base;
}

/**
 * Safely resolves a relative storage_key inside BM_VAULT_DIR (blocks traversal).
 */
function resolveVaultBlobAbsPath($storageKey) {
    $rel = ltrim(str_replace('\\', '/', trim((string)$storageKey)), '/');
    if ($rel === '' || strpos($rel, '..') !== false) {
        return null;
    }

    $baseDir = ensureVaultDirectory();
    $baseReal = realpath($baseDir);
    if (!$baseReal) {
        return null;
    }

    $candidate = $baseReal . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $rel);
    $realFile  = realpath($candidate);
    if (!$realFile || !is_file($realFile)) {
        return null;
    }

    $basePrefix = rtrim($baseReal, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
    if (stripos($realFile, $basePrefix) !== 0) {
        return null;
    }

    return $realFile;
}

/**
 * Generates a lightweight WebP thumbnail via GD ONLY when GD is available and client didn't send one.
 */
function tryCreateServerThumbnail($sourcePath, $sourceMime, $thumbTargetPath) {
    if (!function_exists('imagewebp') || !function_exists('imagecreatetruecolor')) {
        return false;
    }

    $img = null;
    if ($sourceMime === 'image/jpeg' && function_exists('imagecreatefromjpeg')) {
        $img = @imagecreatefromjpeg($sourcePath);
    } elseif ($sourceMime === 'image/png' && function_exists('imagecreatefrompng')) {
        $img = @imagecreatefrompng($sourcePath);
    } elseif ($sourceMime === 'image/webp' && function_exists('imagecreatefromwebp')) {
        $img = @imagecreatefromwebp($sourcePath);
    }

    if (!$img) {
        return false;
    }

    $w = imagesx($img);
    $h = imagesy($img);
    if ($w <= 0 || $h <= 0) {
        imagedestroy($img);
        return false;
    }

    $maxDim = 400;
    if ($w > $maxDim || $h > $maxDim) {
        if ($w >= $h) {
            $newW = $maxDim;
            $newH = max(1, (int)round($h * ($maxDim / $w)));
        } else {
            $newH = $maxDim;
            $newW = max(1, (int)round($w * ($maxDim / $h)));
        }
        $dst = imagecreatetruecolor($newW, $newH);
        imagealphablending($dst, false);
        imagesavealpha($dst, true);
        imagecopyresampled($dst, $img, 0, 0, 0, 0, $newW, $newH, $w, $h);
        $ok = @imagewebp($dst, $thumbTargetPath, 72);
        imagedestroy($dst);
        imagedestroy($img);
        return (bool)$ok;
    }

    $ok = @imagewebp($img, $thumbTargetPath, 75);
    imagedestroy($img);
    return (bool)$ok;
}

/**
 * Validates and persists an uploaded file (+ optional client-generated WebP thumbnail),
 * deduplicating identical binaries via SHA-256 hash.
 */
function storeUploadedVaultFile($pdo, $fileEntry, $thumbEntry = null, $origClientBytes = 0) {
    if (!is_array($fileEntry) || ($fileEntry['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        return ['success' => false, 'error' => 'File upload failed or missing.'];
    }

    $tmpPath = $fileEntry['tmp_name'] ?? '';
    $size    = (int)($fileEntry['size'] ?? 0);
    if ($tmpPath === '' || !is_uploaded_file($tmpPath) && !is_file($tmpPath)) {
        return ['success' => false, 'error' => 'Invalid temporary upload path.'];
    }
    if ($size <= 0 || $size > BM_VAULT_MAX_FILE_BYTES) {
        return ['success' => false, 'error' => 'File size must be between 1 byte and 15 MB.'];
    }

    $safeName = sanitizeMailFilename($fileEntry['name'] ?? 'document.bin');
    $ext      = strtolower(pathinfo($safeName, PATHINFO_EXTENSION));
    $safeMime = resolveSafeMimeForAttachment($tmpPath, $ext, $fileEntry['type'] ?? '');
    if ($safeMime === null) {
        return ['success' => false, 'error' => 'Blocked or unsupported file type (' . htmlspecialchars($ext) . ').'];
    }

    $sha256 = hash_file('sha256', $tmpPath);
    if (!$sha256) {
        return ['success' => false, 'error' => 'Unable to compute file checksum.'];
    }

    // SHA-256 Deduplication check
    $existing = $pdo->prepare("SELECT * FROM `file_blobs` WHERE sha256 = ? LIMIT 1");
    $existing->execute([$sha256]);
    $blobRow = $existing->fetch(PDO::FETCH_ASSOC);

    if ($blobRow && resolveVaultBlobAbsPath($blobRow['storage_key'])) {
        $pdo->prepare("UPDATE `file_blobs` SET ref_count = ref_count + 1 WHERE id = ?")->execute([$blobRow['id']]);
        return [
            'success'      => true,
            'blob_id'      => (int)$blobRow['id'],
            'deduplicated' => true,
            'byte_size'    => (int)$blobRow['byte_size'],
            'mime_type'    => $blobRow['mime_type'],
            'safe_name'    => $safeName,
            'thumb_key'    => $blobRow['thumb_key'],
        ];
    }

    // Store fresh physical file
    $month = date('Y-m');
    $monthDir = ensureVaultDirectory($month);
    $fileKeyRel = $month . '/' . bin2hex(random_bytes(6)) . '_' . substr($sha256, 0, 16) . '.' . $ext;
    $absDest    = $monthDir . '/' . basename($fileKeyRel);

    $moved = is_uploaded_file($tmpPath) ? @move_uploaded_file($tmpPath, $absDest) : @copy($tmpPath, $absDest);
    if (!$moved || !is_file($absDest)) {
        return ['success' => false, 'error' => 'Failed writing file to protected vault storage.'];
    }

    $thumbKeyRel = null;
    if (is_array($thumbEntry) && ($thumbEntry['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
        $thumbTmp = $thumbEntry['tmp_name'] ?? '';
        $thumbSize = (int)($thumbEntry['size'] ?? 0);
        if ($thumbTmp !== '' && $thumbSize > 0 && $thumbSize <= (512 * 1024)) {
            $thumbMime = resolveSafeMimeForAttachment($thumbTmp, 'webp', $thumbEntry['type'] ?? 'image/webp');
            if ($thumbMime === 'image/webp' || $thumbMime === 'image/jpeg') {
                $tExt = ($thumbMime === 'image/jpeg') ? 'jpg' : 'webp';
                $thumbDir = ensureVaultDirectory('thumbs/' . $month);
                $thumbName = basename($fileKeyRel, '.' . $ext) . '_thumb.' . $tExt;
                $thumbAbs = $thumbDir . '/' . $thumbName;
                $tMoved = is_uploaded_file($thumbTmp) ? @move_uploaded_file($thumbTmp, $thumbAbs) : @copy($thumbTmp, $thumbAbs);
                if ($tMoved && is_file($thumbAbs)) {
                    $thumbKeyRel = 'thumbs/' . $month . '/' . $thumbName;
                }
            }
        }
    } elseif (strpos($safeMime, 'image/') === 0 && $safeMime !== 'image/avif' && $safeMime !== 'image/gif') {
        $thumbDir = ensureVaultDirectory('thumbs/' . $month);
        $thumbName = basename($fileKeyRel, '.' . $ext) . '_thumb.webp';
        $thumbAbs = $thumbDir . '/' . $thumbName;
        if (tryCreateServerThumbnail($absDest, $safeMime, $thumbAbs)) {
            $thumbKeyRel = 'thumbs/' . $month . '/' . $thumbName;
        }
    }

    $storedOrigBytes = max($size, (int)$origClientBytes);

    if ($blobRow) {
        // Stale missing blob repair
        $upd = $pdo->prepare("UPDATE `file_blobs` SET storage_key = ?, thumb_key = ?, mime_type = ?, original_ext = ?, byte_size = ?, orig_client_bytes = ?, ref_count = ref_count + 1 WHERE id = ?");
        $upd->execute([$fileKeyRel, $thumbKeyRel, $safeMime, $ext, $size, $storedOrigBytes, $blobRow['id']]);
        $blobId = (int)$blobRow['id'];
    } else {
        $ins = $pdo->prepare("INSERT INTO `file_blobs` (`sha256`, `storage_driver`, `storage_key`, `thumb_key`, `mime_type`, `original_ext`, `byte_size`, `orig_client_bytes`, `ref_count`) VALUES (?, 'local', ?, ?, ?, ?, ?, ?, 1)");
        $ins->execute([$sha256, $fileKeyRel, $thumbKeyRel, $safeMime, $ext, $size, $storedOrigBytes]);
        $blobId = (int)$pdo->lastInsertId();
    }

    return [
        'success'      => true,
        'blob_id'      => $blobId,
        'deduplicated' => false,
        'byte_size'    => $size,
        'mime_type'    => $safeMime,
        'safe_name'    => $safeName,
        'thumb_key'    => $thumbKeyRel,
    ];
}

/**
 * Decrements blob reference count and removes physical files only when unreferenced.
 */
function decrementOrDeleteVaultBlob($pdo, $blobId) {
    if ($blobId <= 0) return;
    $stmt = $pdo->prepare("SELECT * FROM `file_blobs` WHERE id = ? LIMIT 1");
    $stmt->execute([$blobId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) return;

    $remaining = (int)$row['ref_count'] - 1;
    if ($remaining > 0) {
        $pdo->prepare("UPDATE `file_blobs` SET ref_count = ? WHERE id = ?")->execute([$remaining, $blobId]);
        return;
    }

    $mainAbs = resolveVaultBlobAbsPath($row['storage_key']);
    if ($mainAbs && is_file($mainAbs)) {
        @unlink($mainAbs);
    }
    if (!empty($row['thumb_key'])) {
        $thumbAbs = resolveVaultBlobAbsPath($row['thumb_key']);
        if ($thumbAbs && is_file($thumbAbs)) {
            @unlink($thumbAbs);
        }
    }
    $pdo->prepare("DELETE FROM `file_blobs` WHERE id = ?")->execute([$blobId]);
}

/**
 * Streams a vault file safely to HTTP response.
 */
function streamVaultBlobToHttp($absPath, $mimeType, $downloadFilename, $preferInline = true) {
    if (!is_file($absPath) || !is_readable($absPath)) {
        http_response_code(404);
        echo 'File not found.';
        exit;
    }

    $ext = strtolower(pathinfo($downloadFilename, PATHINFO_EXTENSION));
    $inlineSafe = ['pdf', 'jpg', 'jpeg', 'png', 'gif', 'webp', 'avif'];
    $disp = ($preferInline && in_array($ext, $inlineSafe, true)) ? 'inline' : 'attachment';
    $safeOutName = str_replace(['"', "\r", "\n"], '_', basename($downloadFilename));

    header('Content-Type: ' . ($mimeType ?: 'application/octet-stream'));
    header('X-Content-Type-Options: nosniff');
    header('Content-Length: ' . filesize($absPath));
    header('Cache-Control: private, max-age=3600');
    header('Content-Disposition: ' . $disp . '; filename="' . $safeOutName . '"');

    if (ob_get_level()) {
        ob_end_clean();
    }
    readfile($absPath);
    exit;
}

/**
 * Builds an organized ZIP proof pack for L/C presentation or buyer sharing.
 */
function buildDealZipProofPack($pdo, $dealId, $scope = 'buyer', $destZipPath = '') {
    if (!class_exists('ZipArchive')) {
        return ['success' => false, 'error' => 'ZipArchive extension not available on PHP.'];
    }

    $stmtDeal = $pdo->prepare("SELECT * FROM `deals` WHERE id = ? LIMIT 1");
    $stmtDeal->execute([$dealId]);
    $deal = $stmtDeal->fetch(PDO::FETCH_ASSOC);
    if (!$deal) {
        return ['success' => false, 'error' => 'Deal not found.'];
    }

    $zip = new ZipArchive();
    if ($zip->open($destZipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        return ['success' => false, 'error' => 'Unable to initialize ZIP archive.'];
    }

    // Build clean manifest summary text
    $manifest = [];
    $manifest[] = "BALOCHISTAN MINERALS (PVT) LTD — EXPORT DEAL COMPLIANCE PACK";
    $manifest[] = "================================================================";
    $manifest[] = "Deal Code:         " . $deal['deal_code'];
    $manifest[] = "Tracking Code:     " . $deal['tracking_code'];
    $manifest[] = "Consignee / Buyer: " . $deal['company_name'] . " (" . $deal['buyer_name'] . ")";
    $manifest[] = "Commodity:         " . $deal['commodity'] . " | " . $deal['contracted_mt'] . " MT";
    $manifest[] = "Incoterm / Terms:  " . $deal['incoterm'] . " | " . $deal['payment_terms'];
    $manifest[] = "Routing:           " . $deal['port_of_loading'] . " -> " . ($deal['port_of_discharge'] ?: 'TBD');
    $manifest[] = "Vessel / B/L:      " . ($deal['vessel_name'] ?: 'Pending') . " | B/L: " . ($deal['bill_of_lading'] ?: 'Draft');
    $manifest[] = "Current Stage:     " . $deal['current_stage_label'];
    $manifest[] = "Generated At:      " . date('Y-m-d H:i:s') . " PKT";
    $manifest[] = "";
    $manifest[] = "STAGE-BY-STAGE VERIFIED MEASUREMENTS";
    $manifest[] = "----------------------------------------------------------------";

    $milestones = $pdo->prepare("SELECT * FROM `deal_milestones` WHERE deal_id = ? ORDER BY stage_order ASC");
    $milestones->execute([$dealId]);
    $msRows = $milestones->fetchAll(PDO::FETCH_ASSOC);

    $metricsStmt = $pdo->prepare("SELECT * FROM `deal_measurements` WHERE milestone_id = ? ORDER BY id ASC");
    $stageDirMap = [];
    foreach ($msRows as $ms) {
        $dirName = sprintf('%02d_%s', (int)$ms['stage_order'], preg_replace('/[^A-Za-z0-9]+/', '_', $ms['stage_title']));
        $stageDirMap[(int)$ms['id']] = trim($dirName, '_');

        $manifest[] = "";
        $manifest[] = "[Stage {$ms['stage_order']}] {$ms['stage_title']} — STATUS: {$ms['status']}";
        $metricsStmt->execute([$ms['id']]);
        foreach ($metricsStmt->fetchAll(PDO::FETCH_ASSOC) as $met) {
            if (trim((string)$met['metric_value']) === '') continue;
            if ($scope === 'buyer' && (int)$met['is_buyer_visible'] !== 1) continue;
            $u = $met['unit'] !== '' ? ' ' . $met['unit'] : '';
            $manifest[] = "  - {$met['metric_label']}: {$met['metric_value']}{$u}";
        }
    }

    $zip->addFromString('00_Deal_Compliance_Manifest.txt', implode("\r\n", $manifest) . "\r\n");

    $sqlDocs = "SELECT dd.*, fb.storage_key FROM `deal_documents` dd
                JOIN `file_blobs` fb ON fb.id = dd.blob_id
                WHERE dd.deal_id = ? AND dd.doc_status != 'Superseded'";
    if ($scope === 'buyer') {
        $sqlDocs .= " AND dd.visibility IN ('buyer_portal', 'public_sample')";
    }
    $sqlDocs .= " ORDER BY dd.milestone_id ASC, dd.id ASC";

    $docsStmt = $pdo->prepare($sqlDocs);
    $docsStmt->execute([$dealId]);
    $usedNames = [];

    foreach ($docsStmt->fetchAll(PDO::FETCH_ASSOC) as $doc) {
        $abs = resolveVaultBlobAbsPath($doc['storage_key']);
        if (!$abs) continue;
        $folder = $stageDirMap[(int)$doc['milestone_id']] ?? 'General_Documents';
        $safeBase = sanitizeMailFilename($doc['original_filename']);
        $entryPath = $folder . '/' . $doc['doc_category'] . '_v' . $doc['doc_version'] . '_' . $safeBase;
        if (isset($usedNames[$entryPath])) {
            $usedNames[$entryPath]++;
            $entryPath = $folder . '/' . $doc['doc_category'] . '_v' . $doc['doc_version'] . '_' . $usedNames[$entryPath] . '_' . $safeBase;
        } else {
            $usedNames[$entryPath] = 1;
        }
        $zip->addFile($abs, $entryPath);
    }

    $zip->close();
    return ['success' => true, 'deal' => $deal];
}
