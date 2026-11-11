<?php
/**
 * Balochistan Minerals - Owner-Only Pure-PHP Disaster Recovery Backup Exporter
 * Downloads a complete SQL dump OR full ZIP bundle (SQL + admin/uploads/*)
 * without requiring exec()/mysqldump on shared hosting.
 */

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/vault-helpers.php';
requireAuth();

if (!canSealOrBackupDeals()) {
    http_response_code(403);
    echo 'Owner privileges required for database & vault backups.';
    exit;
}

$pdo = getDB();
if (!$pdo) {
    http_response_code(503);
    echo 'Database connection unavailable.';
    exit;
}

ensureDealVaultSchema($pdo);
$mode = ($_GET['mode'] ?? 'full_zip') === 'sql' ? 'sql' : 'full_zip';
$stamp = date('Y-m-d_His');

function buildPurePhpSqlDump($pdo) {
    $out = [];
    $out[] = "-- Balochistan Minerals Full MySQL Backup";
    $out[] = "-- Generated at: " . date('Y-m-d H:i:s') . " PKT";
    $out[] = "SET FOREIGN_KEY_CHECKS=0;";
    $out[] = "SET SQL_MODE='NO_AUTO_VALUE_ON_ZERO';\n";

    $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    foreach ($tables as $table) {
        $safeTable = str_replace('`', '', $table);
        $createRow = $pdo->query("SHOW CREATE TABLE `{$safeTable}`")->fetch(PDO::FETCH_NUM);
        if (!$createRow || empty($createRow[1])) continue;

        $out[] = "DROP TABLE IF EXISTS `{$safeTable}`;";
        $out[] = $createRow[1] . ";\n";

        $rowsStmt = $pdo->query("SELECT * FROM `{$safeTable}`");
        while ($row = $rowsStmt->fetch(PDO::FETCH_ASSOC)) {
            $cols = array_map(fn($c) => '`' . str_replace('`', '', $c) . '`', array_keys($row));
            $vals = [];
            foreach ($row as $v) {
                $vals[] = ($v === null) ? 'NULL' : $pdo->quote((string)$v);
            }
            $out[] = "INSERT INTO `{$safeTable}` (" . implode(',', $cols) . ") VALUES (" . implode(',', $vals) . ");";
        }
        $out[] = "";
    }

    $out[] = "SET FOREIGN_KEY_CHECKS=1;\n";
    return implode("\n", $out);
}

$sqlContent = buildPurePhpSqlDump($pdo);

if ($mode === 'sql') {
    header('Content-Type: application/sql; charset=utf-8');
    header('X-Content-Type-Options: nosniff');
    header('Content-Disposition: attachment; filename="BM_DB_BACKUP_' . $stamp . '.sql"');
    header('Content-Length: ' . strlen($sqlContent));
    if (ob_get_level()) ob_end_clean();
    echo $sqlContent;
    exit;
}

if (!class_exists('ZipArchive')) {
    http_response_code(500);
    echo 'ZipArchive extension unavailable; use ?mode=sql for SQL backup.';
    exit;
}

$tmpZip = tempnam(sys_get_temp_dir(), 'bm_bak_') . '.zip';
$zip = new ZipArchive();
if ($zip->open($tmpZip, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
    @unlink($tmpZip);
    http_response_code(500);
    echo 'Unable to create backup archive.';
    exit;
}

$zip->addFromString("database/BM_DB_BACKUP_{$stamp}.sql", $sqlContent);

// Include all files inside admin/uploads (attachments + deals vault + thumbs)
$uploadsDir = realpath(__DIR__ . '/uploads');
if ($uploadsDir && is_dir($uploadsDir)) {
    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($uploadsDir, FilesystemIterator::SKIP_DOTS)
    );
    $prefixLen = strlen(rtrim($uploadsDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR);
    foreach ($it as $fileInfo) {
        if ($fileInfo->isFile()) {
            $abs = $fileInfo->getRealPath();
            $rel = str_replace('\\', '/', substr($abs, $prefixLen));
            $zip->addFile($abs, 'uploads/' . $rel);
        }
    }
}

$zip->close();

header('Content-Type: application/zip');
header('X-Content-Type-Options: nosniff');
header('Content-Disposition: attachment; filename="BM_FULL_RECOVERY_BACKUP_' . $stamp . '.zip"');
header('Content-Length: ' . filesize($tmpZip));
if (ob_get_level()) ob_end_clean();
readfile($tmpZip);
@unlink($tmpZip);
exit;
