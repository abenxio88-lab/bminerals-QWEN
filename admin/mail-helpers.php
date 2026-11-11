<?php
/**
 * Balochistan Minerals - Shared Email Content Sanitizer, MIME Extractor & Attachment Handler
 * Used consistently across IMAP sync, SMTP dispatch, 360° Buyer Dossier ("Desk Turn"), and Email Desk.
 */

if (!defined('BM_MAIL_MAX_FILE_BYTES')) {
    define('BM_MAIL_MAX_FILE_BYTES', 10 * 1024 * 1024); // 10 MB per file
}
if (!defined('BM_MAIL_MAX_TOTAL_BYTES')) {
    define('BM_MAIL_MAX_TOTAL_BYTES', 20 * 1024 * 1024); // 20 MB per email
}
if (!defined('BM_MAIL_MAX_FILES')) {
    define('BM_MAIL_MAX_FILES', 10);
}

/**
 * Returns the allowlist of safe commercial document/image extensions and MIME types.
 */
function getAllowedMailAttachmentMap() {
    return [
        'pdf'  => 'application/pdf',
        'doc'  => 'application/msword',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'xls'  => 'application/vnd.ms-excel',
        'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'csv'  => 'text/csv',
        'txt'  => 'text/plain',
        'jpg'  => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png'  => 'image/png',
        'gif'  => 'image/gif',
        'webp' => 'image/webp',
        'zip'  => 'application/zip',
    ];
}

/**
 * Ensures admin/uploads/attachments exists and cannot execute PHP/CGI scripts via HTTP.
 */
function getMailAttachmentsBaseDir() {
    $uploadsRoot = __DIR__ . '/uploads';
    $attDir      = $uploadsRoot . '/attachments';
    if (!is_dir($attDir)) {
        @mkdir($attDir, 0755, true);
    }

    $htaccessPath = $uploadsRoot . '/.htaccess';
    if (!file_exists($htaccessPath)) {
        $rules = "Options -Indexes -ExecCGI\n"
            . "<FilesMatch \"\\.(php|phtml|php3|php4|php5|php7|php8|phar|cgi|pl|py|sh|exe|bat|cmd|js|html|htm|svg)$\">\n"
            . "  <IfModule mod_authz_core.c>\n"
            . "    Require all denied\n"
            . "  </IfModule>\n"
            . "  <IfModule !mod_authz_core.c>\n"
            . "    Order deny,allow\n"
            . "    Deny from all\n"
            . "  </IfModule>\n"
            . "</FilesMatch>\n";
        @file_put_contents($htaccessPath, $rules);
    }

    $indexFile = $uploadsRoot . '/index.php';
    if (!file_exists($indexFile)) {
        @file_put_contents($indexFile, "<?php http_response_code(403); exit;\n");
    }

    return $attDir;
}

/**
 * Formats byte count into readable label (e.g. 420 KB, 2.3 MB).
 */
function formatBytesHuman($bytes) {
    $b = max(0, (int)$bytes);
    if ($b < 1024) return $b . ' B';
    if ($b < 1048576) return round($b / 1024) . ' KB';
    return round($b / 1048576, 1) . ' MB';
}

/**
 * Sanitizes an original attachment filename while keeping readability and extension intact.
 */
function sanitizeMailFilename($filename, $fallbackExt = '') {
    $base = basename(trim(str_replace(["\r", "\n", "\0", "\\", "/"], '', (string)$filename)));
    $base = preg_replace('/[^\w\.\-\(\) ]+/u', '_', $base);
    $base = trim((string)preg_replace('/\s+/', ' ', $base), ". ");
    if ($base === '') {
        $base = 'document' . ($fallbackExt !== '' ? '.' . ltrim($fallbackExt, '.') : '.bin');
    }
    if (strlen($base) > 140) {
        $ext  = pathinfo($base, PATHINFO_EXTENSION);
        $stem = substr(pathinfo($base, PATHINFO_FILENAME), 0, 120);
        $base = $stem . ($ext !== '' ? '.' . $ext : '');
    }
    return $base;
}

/**
 * Detects real MIME type from file bytes and verifies it matches safe types cleanly.
 */
function resolveSafeMimeForAttachment($absOrTempPath, $ext, $declaredMime = '') {
    $map = getAllowedMailAttachmentMap();
    $extLower = strtolower(trim((string)$ext));
    if (!isset($map[$extLower])) {
        return null;
    }
    $fallbackMime = $map[$extLower];

    if (function_exists('finfo_open') && is_file($absOrTempPath)) {
        $finfo = @finfo_open(FILEINFO_MIME_TYPE);
        if ($finfo) {
            $detected = strtolower(trim((string)@finfo_file($finfo, $absOrTempPath)));
            @finfo_close($finfo);
            // Block executable binaries masquerading with document extensions
            $blockedMimes = [
                'application/x-executable',
                'application/x-dosexec',
                'application/x-msdownload',
                'application/x-httpd-php',
                'text/x-php',
                'application/javascript',
                'text/html',
            ];
            if (in_array($detected, $blockedMimes, true)) {
                return null;
            }
            if ($detected !== '' && $detected !== 'application/octet-stream') {
                return $detected;
            }
        }
    }

    $decl = strtolower(trim((string)$declaredMime));
    return ($decl !== '' && $decl !== 'application/octet-stream') ? $decl : $fallbackMime;
}

/**
 * Persists raw attachment bytes into protected storage and returns structured metadata (or null if skipped/unsafe).
 */
function saveMailAttachmentBytes($bytes, $origFilename, $declaredMime = '', $isInline = false, $cid = null) {
    if (!is_string($bytes) || $bytes === '') {
        return null;
    }
    $size = strlen($bytes);
    if ($size > BM_MAIL_MAX_FILE_BYTES) {
        return null;
    }

    $map      = getAllowedMailAttachmentMap();
    $safeName = sanitizeMailFilename($origFilename);
    $ext      = strtolower(pathinfo($safeName, PATHINFO_EXTENSION));

    // If inline image lacks extension (e.g. CID without filename), infer from declared MIME
    if ($ext === '' && $declaredMime !== '') {
        $mimeToExt = [
            'image/png'       => 'png',
            'image/jpeg'      => 'jpg',
            'image/jpg'       => 'jpg',
            'image/gif'       => 'gif',
            'image/webp'      => 'webp',
            'application/pdf' => 'pdf',
            'text/csv'        => 'csv',
            'text/plain'      => 'txt',
        ];
        $declLower = strtolower(trim(explode(';', (string)$declaredMime)[0]));
        if (isset($mimeToExt[$declLower])) {
            $ext = $mimeToExt[$declLower];
            $safeName = sanitizeMailFilename(pathinfo($safeName, PATHINFO_FILENAME) . '.' . $ext);
        }
    }

    if ($ext === '' || !isset($map[$ext])) {
        return null;
    }

    $baseDir   = getMailAttachmentsBaseDir();
    $subFolder = date('Y-m');
    $targetDir = $baseDir . '/' . $subFolder;
    if (!is_dir($targetDir)) {
        @mkdir($targetDir, 0755, true);
    }

    $token   = bin2hex(random_bytes(8));
    $diskName = $token . '_' . preg_replace('/[^A-Za-z0-9\.\-_]/', '_', $safeName);
    $relPath = $subFolder . '/' . $diskName;
    $absPath = $targetDir . '/' . $diskName;

    if (@file_put_contents($absPath, $bytes) === false) {
        return null;
    }

    $safeMime = resolveSafeMimeForAttachment($absPath, $ext, $declaredMime);
    if ($safeMime === null) {
        @unlink($absPath);
        return null;
    }

    $cleanCid = $cid !== null ? trim((string)$cid, " <>\t\r\n") : null;

    return [
        'name'     => $safeName,
        'path'     => $relPath,
        'abs_path' => $absPath,
        'mime'     => $safeMime,
        'size'     => $size,
        'inline'   => (bool)$isInline,
        'cid'      => ($cleanCid !== '' ? $cleanCid : null),
    ];
}

/**
 * Handles uploaded multi-file or single-file attachments from PHP $_FILES safely.
 * Returns ['files' => [...], 'errors' => [...]].
 */
function handleUploadedAttachments($fieldName = 'attachments') {
    $result = ['files' => [], 'errors' => []];
    if (empty($_FILES[$fieldName]) || !isset($_FILES[$fieldName]['name'])) {
        return $result;
    }

    $f = $_FILES[$fieldName];
    $names   = is_array($f['name']) ? $f['name'] : [$f['name']];
    $tmps    = is_array($f['tmp_name']) ? $f['tmp_name'] : [$f['tmp_name']];
    $errors  = is_array($f['error']) ? $f['error'] : [$f['error']];
    $sizes   = is_array($f['size']) ? $f['size'] : [$f['size']];
    $types   = is_array($f['type']) ? $f['type'] : [$f['type']];

    $allowedMap  = getAllowedMailAttachmentMap();
    $totalBytes  = 0;
    $pendingSave = [];

    foreach ($names as $i => $rawName) {
        $err = (int)($errors[$i] ?? UPLOAD_ERR_NO_FILE);
        if ($err === UPLOAD_ERR_NO_FILE || trim((string)$rawName) === '') {
            continue;
        }
        if ($err !== UPLOAD_ERR_OK) {
            $result['errors'][] = "Upload error on '" . sanitizeMailFilename($rawName) . "' (code {$err}).";
            continue;
        }
        if (count($pendingSave) >= BM_MAIL_MAX_FILES) {
            $result['errors'][] = 'Maximum ' . BM_MAIL_MAX_FILES . ' attachments allowed per email.';
            break;
        }

        $tmpPath  = (string)($tmps[$i] ?? '');
        $safeName = sanitizeMailFilename($rawName);
        $ext      = strtolower(pathinfo($safeName, PATHINFO_EXTENSION));
        $fileSize = (int)($sizes[$i] ?? 0);

        if ($ext === '' || !isset($allowedMap[$ext])) {
            $result['errors'][] = "File type '.{$ext}' is not allowed for '{$safeName}'. Allowed: PDF, Word, Excel, CSV, TXT, Images, ZIP.";
            continue;
        }
        if ($fileSize <= 0 || !is_file($tmpPath)) {
            $result['errors'][] = "Uploaded file '{$safeName}' is empty or unavailable.";
            continue;
        }
        if ($fileSize > BM_MAIL_MAX_FILE_BYTES) {
            $result['errors'][] = "'{$safeName}' exceeds the 10 MB per-file limit.";
            continue;
        }
        $totalBytes += $fileSize;
        if ($totalBytes > BM_MAIL_MAX_TOTAL_BYTES) {
            $result['errors'][] = 'Total attachments exceed the 20 MB email limit.';
            break;
        }

        $safeMime = resolveSafeMimeForAttachment($tmpPath, $ext, (string)($types[$i] ?? ''));
        if ($safeMime === null) {
            $result['errors'][] = "Blocked unsafe or unrecognized content inside '{$safeName}'.";
            continue;
        }

        $pendingSave[] = [
            'tmp'  => $tmpPath,
            'name' => $safeName,
            'mime' => $safeMime,
            'size' => $fileSize,
        ];
    }

    if (!empty($result['errors'])) {
        return $result;
    }

    $baseDir   = getMailAttachmentsBaseDir();
    $subFolder = date('Y-m');
    $targetDir = $baseDir . '/' . $subFolder;
    if (!is_dir($targetDir)) {
        @mkdir($targetDir, 0755, true);
    }

    foreach ($pendingSave as $item) {
        $token    = bin2hex(random_bytes(8));
        $diskName = $token . '_' . preg_replace('/[^A-Za-z0-9\.\-_]/', '_', $item['name']);
        $relPath  = $subFolder . '/' . $diskName;
        $absPath  = $targetDir . '/' . $diskName;

        $moved = @move_uploaded_file($item['tmp'], $absPath);
        if (!$moved && is_uploaded_file($item['tmp']) === false && is_file($item['tmp'])) {
            $moved = @copy($item['tmp'], $absPath);
        }
        if (!$moved) {
            $result['errors'][] = "Failed to save '{$item['name']}' on server storage.";
            continue;
        }

        $result['files'][] = [
            'name'     => $item['name'],
            'path'     => $relPath,
            'abs_path' => $absPath,
            'mime'     => $item['mime'],
            'size'     => (int)filesize($absPath),
            'inline'   => false,
            'cid'      => null,
        ];
    }

    return $result;
}

/**
 * Resolves a stored relative attachment path safely inside admin/uploads/attachments.
 */
function resolveAttachmentAbsPath($relPath) {
    $cleanRel = ltrim(str_replace('\\', '/', trim((string)$relPath)), '/');
    if ($cleanRel === '' || strpos($cleanRel, '..') !== false) {
        return null;
    }
    $baseReal = realpath(getMailAttachmentsBaseDir());
    if (!$baseReal) return null;
    $candidate = realpath($baseReal . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $cleanRel));
    if (!$candidate || !is_file($candidate)) {
        return null;
    }
    if (strpos($candidate, $baseReal . DIRECTORY_SEPARATOR) !== 0) {
        return null;
    }
    return $candidate;
}

/**
 * Serializes attachment metadata cleanly for email_messages.attachments column.
 */
function encodeAttachmentsMeta($attachments) {
    if (empty($attachments) || !is_array($attachments)) {
        return null;
    }
    $clean = [];
    foreach ($attachments as $att) {
        if (!is_array($att) || empty($att['name'])) continue;
        $clean[] = [
            'name'   => (string)$att['name'],
            'path'   => (string)($att['path'] ?? ''),
            'mime'   => (string)($att['mime'] ?? 'application/octet-stream'),
            'size'   => (int)($att['size'] ?? 0),
            'inline' => !empty($att['inline']),
            'cid'    => !empty($att['cid']) ? (string)$att['cid'] : null,
        ];
    }
    return !empty($clean) ? json_encode($clean, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : null;
}

/**
 * Decodes email_messages.attachments back into structured attachment items (supports JSON & legacy strings).
 */
function decodeAttachmentsMeta($raw) {
    $str = trim((string)$raw);
    if ($str === '') return [];

    if ($str[0] === '[') {
        $decoded = json_decode($str, true);
        if (is_array($decoded)) {
            $out = [];
            foreach ($decoded as $item) {
                if (!is_array($item) || empty($item['name'])) continue;
                $out[] = [
                    'name'   => (string)$item['name'],
                    'path'   => (string)($item['path'] ?? ''),
                    'mime'   => (string)($item['mime'] ?? 'application/octet-stream'),
                    'size'   => (int)($item['size'] ?? 0),
                    'inline' => !empty($item['inline']),
                    'cid'    => !empty($item['cid']) ? (string)$item['cid'] : null,
                ];
            }
            return $out;
        }
    }

    // Legacy comma-separated plain filename list
    $parts = array_filter(array_map('trim', explode(',', $str)));
    $out = [];
    foreach ($parts as $p) {
        $out[] = [
            'name'   => $p,
            'path'   => '',
            'mime'   => 'application/octet-stream',
            'size'   => 0,
            'inline' => false,
            'cid'    => null,
        ];
    }
    return $out;
}

/**
 * Returns public non-inline attachment objects ready for Desk Turn & Email Desk UI cards.
 */
function formatPublicAttachmentsForMessage($msgId, $rawAttachmentsMeta) {
    $items = decodeAttachmentsMeta($rawAttachmentsMeta);
    $out = [];
    foreach ($items as $idx => $item) {
        if (!empty($item['inline'])) continue;
        $hasFile = !empty($item['path']) && resolveAttachmentAbsPath($item['path']) !== null;
        $ext     = strtolower(pathinfo($item['name'], PATHINFO_EXTENSION));
        $canPreview = $hasFile && in_array($ext, ['pdf', 'jpg', 'jpeg', 'png', 'gif', 'webp', 'txt'], true);

        $out[] = [
            'idx'         => (int)$idx,
            'name'        => $item['name'],
            'mime'        => $item['mime'],
            'size'        => (int)$item['size'],
            'size_label'  => (int)$item['size'] > 0 ? formatBytesHuman($item['size']) : '',
            'ext'         => $ext,
            'available'   => $hasFile,
            'can_preview' => $canPreview,
            'url'         => ($msgId > 0 && $hasFile) ? ("api-attachment.php?msg_id=" . (int)$msgId . "&idx=" . (int)$idx . "&download=1") : '',
            'preview_url' => ($msgId > 0 && $canPreview) ? ("api-attachment.php?msg_id=" . (int)$msgId . "&idx=" . (int)$idx . "&view=1") : '',
        ];
    }
    return $out;
}

/**
 * Friendly label for signature/social links when an inline icon was stripped or unavailable.
 */
function getFriendlyUrlLabel($url) {
    $host = strtolower((string)(parse_url($url, PHP_URL_HOST) ?? ''));
    $host = preg_replace('/^www\./', '', $host);
    $brands = [
        'linkedin.com' => 'LinkedIn',
        'ch.linkedin.com' => 'LinkedIn',
        'uk.linkedin.com' => 'LinkedIn',
        'twitter.com'  => 'X / Twitter',
        'x.com'        => 'X / Twitter',
        'youtube.com'  => 'YouTube',
        'youtu.be'     => 'YouTube',
        'instagram.com'=> 'Instagram',
        'facebook.com' => 'Facebook',
        'wa.me'        => 'WhatsApp',
    ];
    if (isset($brands[$host])) return $brands[$host];
    return $host !== '' ? $host : $url;
}

/**
 * Cleans Outlook/MIME plain-text email noise such as [cid:...] markers and label<https://...> artifacts.
 */
function cleanEmailPlainText($text) {
    $s = str_replace(["\r\n", "\r"], "\n", (string)$text);
    // "[cid:abc]<https://linkedin.com/...>" -> "https://linkedin.com/..."
    $s = preg_replace('/\[cid:[^\]]+\]\s*<\s*(https?:\/\/[^>\s]+)\s*>/i', '$1', $s);
    // Remove leftover standalone "[cid:...]" markers
    $s = preg_replace('/\[cid:[^\]]+\]/i', '', $s);
    // "starcb.com<https://www.starcb.com/>" -> "starcb.com (https://www.starcb.com/)"
    $s = preg_replace('/([^\s<>\n]+)<\s*(https?:\/\/[^>\s]+)\s*>/i', '$1 ($2)', $s);
    // "<mailto:user@example.com>" or "<https://example.com>" -> clean address
    $s = preg_replace('/<\s*((?:https?:\/\/|mailto:)[^>\s]+)\s*>/i', '$1', $s);
    // Trim trailing horizontal whitespace on each line
    $s = preg_replace('/[ \t]+$/m', '', $s);
    // Collapse excess blank lines
    $s = preg_replace("/\n{3,}/", "\n\n", $s);
    return trim($s);
}

/**
 * Formats plain-text email body into safe, crisp HTML with clickable links and bullet lists.
 */
function formatPlainTextAsCleanHtml($text) {
    $s = str_replace(["\r\n", "\r"], "\n", (string)$text);
    // Convert Outlook tokens into temporary placeholders BEFORE HTML escaping
    $tokens = [];
    $makeToken = function ($htmlFragment) use (&$tokens) {
        $key = '[[BM_TOK_' . count($tokens) . ']]';
        $tokens[$key] = $htmlFragment;
        return $key;
    };

    // 1) "[cid:...] <https://...>" -> neat link pill
    $s = preg_replace_callback('/\[cid:[^\]]+\]\s*<\s*(https?:\/\/[^>\s]+)\s*>/i', function ($m) use ($makeToken) {
        $url = htmlspecialchars($m[1], ENT_QUOTES, 'UTF-8');
        $lbl = htmlspecialchars(getFriendlyUrlLabel($m[1]), ENT_QUOTES, 'UTF-8');
        return $makeToken('<a href="' . $url . '" target="_blank" rel="noopener noreferrer" class="bm-mail-link-pill">' . $lbl . '</a>');
    }, $s);

    // 2) Bare "[cid:...]" removed
    $s = preg_replace('/\[cid:[^\]]+\]/i', '', $s);

    // 3) "label<https://...>" -> clickable link showing label
    $s = preg_replace_callback('/([^\s<>\n\[\]]+)<\s*(https?:\/\/[^>\s]+)\s*>/i', function ($m) use ($makeToken) {
        $lbl = htmlspecialchars($m[1], ENT_QUOTES, 'UTF-8');
        $url = htmlspecialchars($m[2], ENT_QUOTES, 'UTF-8');
        return $makeToken('<a href="' . $url . '" target="_blank" rel="noopener noreferrer">' . $lbl . '</a>');
    }, $s);

    // 4) Remaining bare URLs or <https://...>
    $s = preg_replace_callback('/<?(https?:\/\/[^\s<>"\'\)]+)>?/i', function ($m) use ($makeToken) {
        $rawUrl = rtrim($m[1], '.,;');
        $trail  = substr($m[1], strlen($rawUrl));
        $url    = htmlspecialchars($rawUrl, ENT_QUOTES, 'UTF-8');
        $isLong = strlen($rawUrl) > 55;
        $lbl    = htmlspecialchars($isLong ? getFriendlyUrlLabel($rawUrl) : $rawUrl, ENT_QUOTES, 'UTF-8');
        if ($isLong) {
            $pill = '<a href="' . $url . '" target="_blank" rel="noopener noreferrer" class="bm-mail-link-pill" title="' . $url . '">' . $lbl . ' &#8599;</a>';
        } else {
            $pill = '<a href="' . $url . '" target="_blank" rel="noopener noreferrer">' . $lbl . '</a>';
        }
        return $makeToken($pill) . htmlspecialchars($trail, ENT_QUOTES, 'UTF-8');
    }, $s);

    $s = preg_replace("/\n{3,}/", "\n\n", trim($s));
    if ($s === '') return '<p></p>';

    $lines   = explode("\n", $s);
    $htmlOut = '';
    $inList  = false;
    $paraBuf = [];

    $flushPara = function () use (&$paraBuf, &$htmlOut, $tokens) {
        if (empty($paraBuf)) return;
        $joined = implode("\n", $paraBuf);
        $escaped = nl2br(htmlspecialchars($joined, ENT_QUOTES, 'UTF-8'));
        $escaped = strtr($escaped, $tokens);
        $htmlOut .= '<p>' . $escaped . '</p>';
        $paraBuf = [];
    };

    $closeList = function () use (&$inList, &$htmlOut) {
        if ($inList) {
            $htmlOut .= '</ul>';
            $inList = false;
        }
    };

    foreach ($lines as $line) {
        $trimmed = trim($line);
        if ($trimmed === '') {
            $closeList();
            $flushPara();
            continue;
        }
        if (preg_match('/^(?:\*|-|•)\s+(.*)$/u', $trimmed, $bm)) {
            $flushPara();
            if (!$inList) {
                $htmlOut .= '<ul class="bm-mail-bullets">';
                $inList = true;
            }
            $itemHtml = strtr(htmlspecialchars(trim($bm[1]), ENT_QUOTES, 'UTF-8'), $tokens);
            $htmlOut .= '<li>' . $itemHtml . '</li>';
        } else {
            $closeList();
            $paraBuf[] = $line;
        }
    }
    $closeList();
    $flushPara();

    return $htmlOut;
}

/**
 * Sanitizes a single inline CSS style attribute using a strict property allowlist.
 */
function sanitizeEmailInlineCss($styleRaw) {
    $allowedProps = [
        'color', 'background-color', 'background', 'font-weight', 'font-style',
        'text-decoration', 'text-align', 'line-height', 'margin', 'margin-top',
        'margin-bottom', 'margin-left', 'margin-right', 'padding', 'padding-top',
        'padding-bottom', 'padding-left', 'padding-right', 'border', 'border-top',
        'border-bottom', 'border-left', 'border-right', 'border-collapse',
        'border-radius', 'font-size', 'vertical-align', 'display',
    ];
    $parts = explode(';', (string)$styleRaw);
    $kept  = [];
    foreach ($parts as $decl) {
        if (strpos($decl, ':') === false) continue;
        list($prop, $val) = explode(':', $decl, 2);
        $prop = strtolower(trim($prop));
        $val  = trim($val);
        if (!in_array($prop, $allowedProps, true)) continue;
        // Reject CSS functions or injection vectors
        if (preg_match('/(expression|javascript:|vbscript:|url\s*\(|-moz-binding|@import)/i', $val)) {
            continue;
        }
        // Only keep background if it's a flat color rather than complex gradient/image
        if ($prop === 'background' && !preg_match('/^(#[0-9a-f]{3,8}|rgb\([^)]+\)|rgba\([^)]+\)|transparent|none|[a-z]{3,20})$/i', $val)) {
            continue;
        }
        if ($prop === 'display' && !in_array(strtolower($val), ['inline', 'inline-block', 'block'], true)) {
            continue;
        }
        $kept[] = $prop . ': ' . $val;
    }
    return implode('; ', $kept);
}

/**
 * Strictly sanitizes HTML email content via DOMDocument tag/attribute allowlist
 * and resolves/cleans CID inline image references cleanly.
 */
function sanitizeAndResolveEmailHtml($rawHtml, $msgId = 0, $attachmentsMeta = [], $preserveValidCid = false) {
    $rawHtml = trim((string)$rawHtml);
    if ($rawHtml === '' || !class_exists('DOMDocument')) {
        return '';
    }

    // Build lookup map of saved inline images by CID
    $cidMap = [];
    foreach ($attachmentsMeta as $att) {
        if (!empty($att['cid']) && !empty($att['path'])) {
            $key = strtolower(trim((string)$att['cid'], " <>"));
            if ($key !== '') {
                $cidMap[$key] = $att;
            }
        }
    }

    $wrapped = '<!DOCTYPE html><html><head><meta http-equiv="Content-Type" content="text/html; charset=utf-8"></head><body>' . $rawHtml . '</body></html>';
    $dom = new DOMDocument();
    $prev = libxml_use_internal_errors(true);
    @$dom->loadHTML($wrapped, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NONET);
    libxml_clear_errors();
    libxml_use_internal_errors($prev);

    $dropWithChildren = [
        'script', 'style', 'iframe', 'frame', 'frameset', 'object', 'embed',
        'applet', 'form', 'input', 'button', 'select', 'textarea', 'svg',
        'math', 'link', 'meta', 'base', 'title', 'head', 'noscript', 'template'
    ];
    $allowedTags = [
        'body', 'p', 'br', 'div', 'span', 'strong', 'b', 'em', 'i', 'u', 's', 'sub', 'sup',
        'a', 'ul', 'ol', 'li', 'table', 'thead', 'tbody', 'tfoot', 'tr', 'th', 'td',
        'blockquote', 'hr', 'pre', 'code', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'img', 'font', 'center'
    ];

    $xpath = new DOMXPath($dom);

    // 1. Remove dangerous tags completely
    foreach ($dropWithChildren as $badTag) {
        $nodes = $xpath->query('//' . $badTag);
        if ($nodes) {
            foreach (iterator_to_array($nodes) as $n) {
                if ($n->parentNode) $n->parentNode->removeChild($n);
            }
        }
    }

    // 2. Unwrap unknown/custom tags (like Microsoft o:p / v:shape) while preserving inner text
    $allNodes = iterator_to_array($dom->getElementsByTagName('*'));
    foreach (array_reverse($allNodes) as $el) {
        /** @var DOMElement $el */
        $tag = strtolower($el->tagName);
        if ($tag === 'html') continue;
        if (!in_array($tag, $allowedTags, true)) {
            $parent = $el->parentNode;
            if ($parent) {
                while ($el->firstChild) {
                    $parent->insertBefore($el->firstChild, $el);
                }
                $parent->removeChild($el);
            }
            continue;
        }

        // Handle <img> tags: resolve cid: or validate URL and drop tracking pixels
        if ($tag === 'img') {
            $src = trim((string)$el->getAttribute('src'));
            $w   = trim((string)$el->getAttribute('width'));
            $h   = trim((string)$el->getAttribute('height'));
            if (($w !== '' && (int)$w <= 2) && ($h !== '' && (int)$h <= 2)) {
                if ($el->parentNode) $el->parentNode->removeChild($el);
                continue;
            }

            if (stripos($src, 'cid:') === 0) {
                $cidKey = strtolower(trim(substr($src, 4), " <>"));
                if (isset($cidMap[$cidKey])) {
                    if ($preserveValidCid) {
                        $el->setAttribute('src', 'cid:' . $cidKey);
                    } elseif ((int)$msgId > 0) {
                        $src = 'api-attachment.php?msg_id=' . (int)$msgId . '&cid=' . rawurlencode($cidKey);
                        $el->setAttribute('src', $src);
                    }
                } else {
                    // Missing inline signature icon: drop broken <img> cleanly
                    $parentAnchor = $el->parentNode;
                    if ($parentAnchor) {
                        $parentAnchor->removeChild($el);
                    }
                    if ($parentAnchor instanceof DOMElement && strtolower($parentAnchor->nodeName) === 'a' && trim($parentAnchor->textContent) === '') {
                        $href = trim((string)$parentAnchor->getAttribute('href'));
                        if ($href !== '' && preg_match('/^https?:\/\//i', $href)) {
                            $parentAnchor->textContent = getFriendlyUrlLabel($href);
                            $parentAnchor->setAttribute('class', 'bm-mail-link-pill');
                        }
                    }
                    continue;
                }
            } elseif (!preg_match('/^(https?:\/\/|data:image\/(png|jpeg|jpg|gif|webp);base64,|api-attachment\.php\?)/i', $src)) {
                if ($el->parentNode) $el->parentNode->removeChild($el);
                continue;
            }
        }

        // Strict attribute allowlist per element
        $allowedAttrs = ['title', 'style'];
        if ($tag === 'a') {
            $allowedAttrs[] = 'href';
            $allowedAttrs[] = 'target';
            $allowedAttrs[] = 'rel';
            $allowedAttrs[] = 'class'; // only kept when set to bm-mail-link-pill above
        } elseif ($tag === 'img') {
            $allowedAttrs = ['src', 'alt', 'style'];
        } elseif (in_array($tag, ['table', 'tr', 'td', 'th'], true)) {
            $allowedAttrs = ['colspan', 'rowspan', 'align', 'valign', 'border', 'style'];
        }

        $toRemove = [];
        foreach ($el->attributes as $attr) {
            $attrName = strtolower($attr->nodeName);
            if (!in_array($attrName, $allowedAttrs, true)) {
                $toRemove[] = $attr->nodeName;
            }
        }
        foreach ($toRemove as $rName) {
            $el->removeAttribute($rName);
        }

        if ($tag === 'a') {
            $href = trim((string)$el->getAttribute('href'));
            if (!preg_match('/^(https?:\/\/|mailto:)/i', $href)) {
                $el->removeAttribute('href');
            } else {
                $el->setAttribute('target', '_blank');
                $el->setAttribute('rel', 'noopener noreferrer');
            }
            if ($el->hasAttribute('class') && $el->getAttribute('class') !== 'bm-mail-link-pill') {
                $el->removeAttribute('class');
            }
        }

        if ($el->hasAttribute('style')) {
            $safeCss = sanitizeEmailInlineCss($el->getAttribute('style'));
            if ($safeCss !== '') {
                $el->setAttribute('style', $safeCss);
            } else {
                $el->removeAttribute('style');
            }
        }
    }

    $body = $dom->getElementsByTagName('body')->item(0);
    if (!$body) return '';

    $innerHtml = '';
    foreach ($body->childNodes as $child) {
        $innerHtml .= $dom->saveHTML($child);
    }

    $innerHtml = trim($innerHtml);
    // Strip meaningless empty spans/paragraphs left by Outlook signatures
    return $innerHtml;
}

/**
 * Returns clean, safe HTML ready for rendering inside Desk Turn and Email Desk.
 */
function buildRenderableEmailHtml($rawHtml, $rawText, $msgId = 0, $attachmentsMeta = []) {
    $sanitized = sanitizeAndResolveEmailHtml($rawHtml, $msgId, $attachmentsMeta);
    if ($sanitized !== '' && trim(strip_tags($sanitized, '<img>')) !== '') {
        return $sanitized;
    }
    return formatPlainTextAsCleanHtml($rawText);
}

/**
 * Decodes transfer encoding (base64 / quoted-printable) without corrupting binary bytes.
 */
function decodeImapTransferEncoding($raw, $encoding) {
    $data = (string)$raw;
    if ((int)$encoding === 3) {
        $decoded = base64_decode($data, true);
        if ($decoded !== false) return $decoded;
    } elseif ((int)$encoding === 4) {
        return quoted_printable_decode($data);
    }
    return $data;
}

/**
 * Extracts a parameter attribute (filename, name, charset) safely from IMAP part parameters.
 */
function getImapPartParam($part, $names) {
    $all = [];
    if (!empty($part->dparameters) && is_array($part->dparameters)) {
        $all = array_merge($all, $part->dparameters);
    }
    if (!empty($part->parameters) && is_array($part->parameters)) {
        $all = array_merge($all, $part->parameters);
    }
    $targets = array_map('strtolower', (array)$names);
    foreach ($all as $p) {
        if (!empty($p->attribute) && in_array(strtolower($p->attribute), $targets, true) && !empty($p->value)) {
            $val = (string)$p->value;
            if (function_exists('decodeMimeHeaderText')) {
                $val = decodeMimeHeaderText($val);
            }
            return trim($val);
        }
    }
    return '';
}

/**
 * Extracts plain text, sanitized HTML, and attachments (inline CIDs + files) from an IMAP email.
 */
function extractImapMessageParts($stream, $msgNo, $saveAttachments = true) {
    $structure = @imap_fetchstructure($stream, $msgNo);
    if (!$structure) {
        $fallback = cleanEmailPlainText(strip_tags((string)@imap_body($stream, $msgNo, FT_PEEK)));
        return [
            'body_text'   => $fallback,
            'body_html'   => null,
            'attachments' => [],
        ];
    }

    $plainParts  = [];
    $htmlParts   = [];
    $attachments = [];

    $walk = function ($part, $partNum) use (&$walk, $stream, $msgNo, &$plainParts, &$htmlParts, &$attachments, $saveAttachments) {
        $subParts = isset($part->parts) && is_array($part->parts) ? $part->parts : [];
        if (!empty($subParts)) {
            foreach ($subParts as $idx => $sub) {
                $prefix = $partNum === '' ? (string)($idx + 1) : ($partNum . '.' . ($idx + 1));
                $walk($sub, $prefix);
            }
            return;
        }

        $section     = $partNum === '' ? '1' : $partNum;
        $raw         = @imap_fetchbody($stream, $msgNo, $section, FT_PEEK);
        if ($raw === false || $raw === '') return;

        $type        = (int)($part->type ?? 0);
        $subtype     = strtoupper((string)($part->subtype ?? 'PLAIN'));
        $disposition = isset($part->disposition) ? strtolower((string)$part->disposition) : '';
        $cid         = (!empty($part->ifid) && !empty($part->id)) ? trim((string)$part->id, " <>\t\r\n") : '';
        $filename    = getImapPartParam($part, ['filename', 'name']);

        // Inline image or downloadable file attachment?
        $isExplicitAttachment = ($disposition === 'attachment');
        $isNonTextBinary      = ($type !== 0); // image/* (5), application/* (3), audio/video (4,6), etc.
        $isTextAttachment     = ($type === 0 && $isExplicitAttachment && $filename !== '');

        if ($isExplicitAttachment || $isNonTextBinary || $isTextAttachment) {
            if ($saveAttachments) {
                $bytes = decodeImapTransferEncoding($raw, $part->encoding ?? 0);
                $mimeMap = [
                    0 => 'text/' . strtolower($subtype),
                    3 => 'application/' . strtolower($subtype),
                    4 => 'audio/' . strtolower($subtype),
                    5 => 'image/' . strtolower($subtype),
                    6 => 'video/' . strtolower($subtype),
                    7 => 'application/octet-stream',
                ];
                $declaredMime = $mimeMap[$type] ?? 'application/octet-stream';
                if ($filename === '' && $cid !== '') {
                    $filename = 'inline_' . preg_replace('/[^A-Za-z0-9]/', '', substr($cid, 0, 16));
                }
                $isInline = ($cid !== '' && $type === 5 && !$isExplicitAttachment);
                $saved = saveMailAttachmentBytes($bytes, $filename ?: 'attachment', $declaredMime, $isInline, $cid !== '' ? $cid : null);
                if ($saved !== null) {
                    $attachments[] = $saved;
                }
            }
            return;
        }

        // Plain / HTML message body part
        if ($type === 0) {
            $params = [];
            if (!empty($part->parameters))  $params = array_merge($params, $part->parameters);
            if (!empty($part->dparameters)) $params = array_merge($params, $part->dparameters);
            $decoded = decodeImapPartPayload($raw, $part->encoding ?? 0, $params);

            if ($subtype === 'PLAIN') {
                $plainParts[] = $decoded;
            } elseif ($subtype === 'HTML') {
                $htmlParts[] = $decoded;
            }
        }
    };

    $walk($structure, '');

    $rawHtml = !empty($htmlParts) ? implode("\n", $htmlParts) : '';
    $sanitizedHtml = $rawHtml !== '' ? sanitizeAndResolveEmailHtml($rawHtml, 0, $attachments, true) : '';
    $rawPlain = !empty($plainParts) ? implode("\n\n", $plainParts) : '';
    if ($rawPlain === '' && $rawHtml !== '') {
        $htmlTmp = preg_replace('/<(br|p|div|li|tr)\b[^>]*>/i', "\n", $rawHtml);
        $rawPlain = html_entity_decode(strip_tags($htmlTmp), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    return [
        'body_text'   => cleanEmailPlainText($rawPlain),
        'raw_html'    => $rawHtml,
        'body_html'   => $sanitizedHtml !== '' ? $sanitizedHtml : null,
        'attachments' => $attachments,
    ];
}
