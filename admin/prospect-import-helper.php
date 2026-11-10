<?php
/**
 * Balochistan Minerals - Pure PHP Excel (.xlsx) & CSV Buyer Prospect Importer
 * Zero external dependencies: uses PHP ZipArchive + SimpleXML for .xlsx and fgetcsv() for .csv.
 */

if (!defined('BM_PROSPECT_IMPORT_MAX_BYTES')) {
    define('BM_PROSPECT_IMPORT_MAX_BYTES', 5 * 1024 * 1024); // 5 MB
}
if (!defined('BM_PROSPECT_IMPORT_MAX_ROWS')) {
    define('BM_PROSPECT_IMPORT_MAX_ROWS', 1000);
}

/**
 * Converts Excel column letters (e.g. A, B, Z, AA, AB) in a cell ref ("AB14") to 0-based index.
 */
function prospectColLettersToIndex($cellRef) {
    if (!preg_match('/^([A-Z]+)/i', trim((string)$cellRef), $m)) {
        return -1;
    }
    $letters = strtoupper($m[1]);
    $index   = 0;
    $len     = strlen($letters);
    for ($i = 0; $i < $len; $i++) {
        $index = ($index * 26) + (ord($letters[$i]) - ord('A') + 1);
    }
    return $index - 1;
}

/**
 * Parses an Excel .xlsx file using ZipArchive + SimpleXML, preserving omitted blank cells.
 */
function parseProspectXlsxRows($filePath, &$error = '') {
    if (!class_exists('ZipArchive')) {
        $error = 'Server PHP ZipArchive extension is unavailable for reading .xlsx files. Please upload .csv instead.';
        return null;
    }
    if (!function_exists('simplexml_load_string')) {
        $error = 'Server PHP SimpleXML extension is unavailable. Please upload .csv instead.';
        return null;
    }

    $zip = new ZipArchive();
    if ($zip->open($filePath) !== true) {
        $error = 'Could not open .xlsx archive. Ensure it is a valid Excel Workbook (.xlsx) or save as .csv.';
        return null;
    }

    // 1. Read shared strings table if present
    $sharedStrings = [];
    $ssXmlStr = $zip->getFromName('xl/sharedStrings.xml');
    if ($ssXmlStr !== false) {
        $prevErr = libxml_use_internal_errors(true);
        $ssXml = @simplexml_load_string($ssXmlStr, 'SimpleXMLElement', LIBXML_NONET | LIBXML_NOCDATA);
        libxml_clear_errors();
        libxml_use_internal_errors($prevErr);

        if ($ssXml) {
            foreach ($ssXml->si as $si) {
                if (isset($si->t)) {
                    $sharedStrings[] = (string)$si->t;
                } elseif (isset($si->r)) {
                    $buf = '';
                    foreach ($si->r as $run) {
                        $buf .= (string)$run->t;
                    }
                    $sharedStrings[] = $buf;
                } else {
                    $sharedStrings[] = '';
                }
            }
        }
    }

    // 2. Resolve first worksheet XML path cleanly
    $sheetPath = 'xl/worksheets/sheet1.xml';
    $wbXmlStr  = $zip->getFromName('xl/workbook.xml');
    $relXmlStr = $zip->getFromName('xl/_rels/workbook.xml.rels');
    if ($wbXmlStr !== false && $relXmlStr !== false) {
        $prevErr = libxml_use_internal_errors(true);
        $wbXml  = @simplexml_load_string($wbXmlStr, 'SimpleXMLElement', LIBXML_NONET);
        $relXml = @simplexml_load_string($relXmlStr, 'SimpleXMLElement', LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($prevErr);

        if ($wbXml && $relXml && isset($wbXml->sheets->sheet[0])) {
            $firstSheet = $wbXml->sheets->sheet[0];
            $rNs = $firstSheet->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships');
            $rid = (string)($rNs['id'] ?? '');
            if ($rid !== '') {
                foreach ($relXml->Relationship as $rel) {
                    if ((string)$rel['Id'] === $rid) {
                        $target = ltrim((string)$rel['Target'], '/');
                        if (strpos($target, 'xl/') !== 0) {
                            $target = 'xl/' . $target;
                        }
                        if ($zip->locateName($target) !== false) {
                            $sheetPath = $target;
                        }
                        break;
                    }
                }
            }
        }
    }

    $sheetXmlStr = $zip->getFromName($sheetPath);
    if ($sheetXmlStr === false) {
        // Fallback: pick first worksheet found in zip
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            if (preg_match('#^xl/worksheets/sheet\d+\.xml$#i', (string)$name)) {
                $sheetXmlStr = $zip->getFromName($name);
                if ($sheetXmlStr !== false) break;
            }
        }
    }
    $zip->close();

    if ($sheetXmlStr === false) {
        $error = 'No readable worksheet found inside the .xlsx file.';
        return null;
    }

    $prevErr = libxml_use_internal_errors(true);
    $sheetXml = @simplexml_load_string($sheetXmlStr, 'SimpleXMLElement', LIBXML_NONET | LIBXML_NOCDATA);
    libxml_clear_errors();
    libxml_use_internal_errors($prevErr);

    if (!$sheetXml || !isset($sheetXml->sheetData)) {
        $error = 'Malformed XML inside Excel worksheet.';
        return null;
    }

    $rows = [];
    foreach ($sheetXml->sheetData->row as $rowNode) {
        $rowArr = [];
        $seqCol = 0;
        foreach ($rowNode->c as $cell) {
            $ref = (string)($cell['r'] ?? '');
            $colIdx = $ref !== '' ? prospectColLettersToIndex($ref) : $seqCol;
            if ($colIdx < 0) $colIdx = $seqCol;
            $seqCol = $colIdx + 1;

            while (count($rowArr) < $colIdx) {
                $rowArr[] = '';
            }

            $type = (string)($cell['t'] ?? '');
            $val  = '';
            if ($type === 's') {
                $ssIdx = (int)($cell->v ?? -1);
                $val = $sharedStrings[$ssIdx] ?? '';
            } elseif ($type === 'inlineStr') {
                if (isset($cell->is->t)) {
                    $val = (string)$cell->is->t;
                } elseif (isset($cell->is->r)) {
                    foreach ($cell->is->r as $run) {
                        $val .= (string)$run->t;
                    }
                }
            } elseif ($type === 'b') {
                $val = ((string)($cell->v ?? '0') === '1') ? '1' : '0';
            } else {
                $val = isset($cell->v) ? (string)$cell->v : '';
            }
            $rowArr[$colIdx] = trim($val);
        }

        // Skip completely empty rows
        if (count(array_filter($rowArr, fn($x) => $x !== '')) > 0) {
            $rows[] = $rowArr;
        }
        if (count($rows) > BM_PROSPECT_IMPORT_MAX_ROWS + 5) {
            break;
        }
    }

    return $rows;
}

/**
 * Parses a CSV/TSV file with UTF-8 BOM stripping, encoding cleanup, and delimiter detection.
 */
function parseProspectCsvRows($filePath, &$error = '') {
    $raw = @file_get_contents($filePath);
    if ($raw === false || $raw === '') {
        $error = 'Uploaded CSV file is empty or unreadable.';
        return null;
    }

    // Handle UTF-16 (Excel "Unicode Text") or UTF-8 BOM
    if (substr($raw, 0, 2) === "\xFF\xFE" && function_exists('mb_convert_encoding')) {
        $raw = mb_convert_encoding(substr($raw, 2), 'UTF-8', 'UTF-16LE');
    } elseif (substr($raw, 0, 2) === "\xFE\xFF" && function_exists('mb_convert_encoding')) {
        $raw = mb_convert_encoding(substr($raw, 2), 'UTF-8', 'UTF-16BE');
    } elseif (substr($raw, 0, 3) === "\xEF\xBB\xBF") {
        $raw = substr($raw, 3);
    } elseif (function_exists('mb_check_encoding') && !mb_check_encoding($raw, 'UTF-8') && function_exists('mb_convert_encoding')) {
        $raw = mb_convert_encoding($raw, 'UTF-8', 'Windows-1252');
    }

    $raw = str_replace(["\r\n", "\r"], "\n", $raw);
    $lines = explode("\n", $raw);
    $firstNonEmpty = '';
    foreach ($lines as $ln) {
        if (trim($ln) !== '') {
            $firstNonEmpty = $ln;
            break;
        }
    }
    if ($firstNonEmpty === '') {
        $error = 'CSV file contains no rows.';
        return null;
    }

    // Auto-detect delimiter (, vs ; vs tab)
    $delims = [",", ";", "\t"];
    $bestDelim = ",";
    $bestCount = -1;
    foreach ($delims as $d) {
        $cnt = substr_count($firstNonEmpty, $d);
        if ($cnt > $bestCount) {
            $bestCount = $cnt;
            $bestDelim = $d;
        }
    }

    $fp = fopen('php://temp', 'r+');
    fwrite($fp, $raw);
    rewind($fp);

    $rows = [];
    while (($row = fgetcsv($fp, 0, $bestDelim)) !== false) {
        if (!is_array($row)) continue;
        $cleaned = array_map(fn($c) => trim((string)$c), $row);
        if (count(array_filter($cleaned, fn($x) => $x !== '')) > 0) {
            $rows[] = $cleaned;
        }
        if (count($rows) > BM_PROSPECT_IMPORT_MAX_ROWS + 5) {
            break;
        }
    }
    fclose($fp);

    return $rows;
}

/**
 * Maps spreadsheet header row to canonical buyer_prospects columns flexibly.
 */
function mapProspectHeaderColumns($headerRow) {
    $aliases = [
        'company_name'   => ['companyname', 'company', 'buyercompany', 'organization', 'organisation', 'firm', 'importer', 'client', 'targetcompany', 'name'],
        'contact_person' => ['contactperson', 'contactname', 'contact', 'person', 'representative', 'officer', 'director', 'buyername', 'poc'],
        'country'        => ['country', 'nation', 'corridor', 'destination', 'region'],
        'city'           => ['city', 'port', 'town', 'cityport', 'state', 'location'],
        'email'          => ['email', 'emailaddress', 'mail', 'workemail', 'businessemail', 'directemail'],
        'phone'          => ['phone', 'phonenumber', 'tel', 'telephone', 'landline', 'officephone'],
        'whatsapp'       => ['whatsapp', 'wa', 'mobile', 'cell', 'cellphone', 'whatsappnumber'],
        'target_mineral' => ['targetmineral', 'mineral', 'mineraltype', 'commodity', 'product', 'ore', 'material'],
        'buyer_type'     => ['buyertype', 'type', 'category', 'sector', 'industry', 'profile', 'buyerprofile'],
        'annual_demand'  => ['annualdemand', 'demand', 'volume', 'tonnage', 'quantity', 'qty', 'estdemand'],
        'target_grade'   => ['targetgrade', 'grade', 'spec', 'specs', 'specification', 'specifications', 'purity', 'quality'],
        'status'         => ['status', 'stage', 'pipelinestage', 'pipelinestatus'],
        'notes'          => ['notes', 'note', 'comments', 'comment', 'remarks', 'description'],
    ];

    $colMap = [];
    foreach ($headerRow as $idx => $rawHeader) {
        $norm = preg_replace('/[^a-z0-9]/', '', strtolower(trim((string)$rawHeader)));
        if ($norm === '' || $norm === 'id' || $norm === 'createdat') continue;

        foreach ($aliases as $canon => $keys) {
            if (in_array($norm, $keys, true) && !isset($colMap[$canon])) {
                $colMap[$canon] = (int)$idx;
                break;
            }
        }
    }

    return $colMap;
}

/**
 * Normalizes a pipeline status value to one of the 5 directory stages.
 */
function normalizeProspectPipelineStatus($rawStatus, $fallback = 'Identified') {
    $valid = ['Identified', 'Outreach Sent', 'Assay Shared', 'Negotiating', 'Contract Executed'];
    $s = strtolower(trim((string)$rawStatus));
    if ($s === '') return $fallback;

    foreach ($valid as $v) {
        if ($s === strtolower($v)) return $v;
    }
    if (strpos($s, 'contract') !== false || strpos($s, 'execut') !== false || strpos($s, 'sign') !== false || strpos($s, 'won') !== false) {
        return 'Contract Executed';
    }
    if (strpos($s, 'negot') !== false || strpos($s, 'quot') !== false) {
        return 'Negotiating';
    }
    if (strpos($s, 'assay') !== false || strpos($s, 'sample') !== false) {
        return 'Assay Shared';
    }
    if (strpos($s, 'outreach') !== false || strpos($s, 'sent') !== false || strpos($s, 'contact') !== false) {
        return 'Outreach Sent';
    }
    return $fallback;
}

/**
 * Imports buyer prospects from uploaded .xlsx or .csv into buyer_prospects cleanly.
 */
function importBuyerProspectsFromSpreadsheet(PDO $pdo, array $fileInfo, array $options = []) {
    if (empty($fileInfo['name']) || (int)($fileInfo['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        return ['success' => false, 'error' => 'Please select a valid .xlsx or .csv file to upload.'];
    }
    $size = (int)($fileInfo['size'] ?? 0);
    if ($size <= 0 || $size > BM_PROSPECT_IMPORT_MAX_BYTES) {
        return ['success' => false, 'error' => 'File must be non-empty and under 5 MB.'];
    }

    $ext = strtolower(pathinfo((string)$fileInfo['name'], PATHINFO_EXTENSION));
    if ($ext === 'xls') {
        return ['success' => false, 'error' => 'Legacy binary .xls files are not supported. Please open in Excel and Save As .xlsx or .csv.'];
    }
    if (!in_array($ext, ['xlsx', 'csv', 'txt'], true)) {
        return ['success' => false, 'error' => 'Unsupported file format ".' . htmlspecialchars($ext) . '". Allowed: .xlsx and .csv.'];
    }

    $parseErr = '';
    $rows = ($ext === 'xlsx')
        ? parseProspectXlsxRows($fileInfo['tmp_name'], $parseErr)
        : parseProspectCsvRows($fileInfo['tmp_name'], $parseErr);

    if ($rows === null) {
        return ['success' => false, 'error' => $parseErr ?: 'Unable to parse spreadsheet rows.'];
    }
    if (count($rows) < 2) {
        return ['success' => false, 'error' => 'Spreadsheet needs a header row plus at least 1 buyer data row.'];
    }

    $headerRow = array_shift($rows);
    $colMap    = mapProspectHeaderColumns($headerRow);

    if (!isset($colMap['company_name']) && !isset($colMap['email'])) {
        return [
            'success' => false,
            'error' => 'Could not recognize required column headers (need at least "Company Name" or "Email"). Download the Sample CSV Template to see expected columns.'
        ];
    }

    $duplicateMode = ($options['duplicate_mode'] ?? 'skip') === 'update' ? 'update' : 'skip';
    $defaultStage  = normalizeProspectPipelineStatus($options['default_status'] ?? 'Identified', 'Identified');

    $findByEmail   = $pdo->prepare("SELECT id FROM buyer_prospects WHERE LOWER(email) = ? LIMIT 1");
    $findByCompany = $pdo->prepare("SELECT id FROM buyer_prospects WHERE LOWER(company_name) = ? LIMIT 1");

    $insertStmt = $pdo->prepare("INSERT INTO buyer_prospects
        (company_name, contact_person, country, city, email, phone, whatsapp, target_mineral, buyer_type, annual_demand, target_grade, status, notes)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

    $updateStmt = $pdo->prepare("UPDATE buyer_prospects SET
        company_name = COALESCE(NULLIF(?, ''), company_name),
        contact_person = COALESCE(NULLIF(?, ''), contact_person),
        country = COALESCE(NULLIF(?, ''), country),
        city = COALESCE(NULLIF(?, ''), city),
        email = COALESCE(NULLIF(?, ''), email),
        phone = COALESCE(NULLIF(?, ''), phone),
        whatsapp = COALESCE(NULLIF(?, ''), whatsapp),
        target_mineral = COALESCE(NULLIF(?, ''), target_mineral),
        buyer_type = COALESCE(NULLIF(?, ''), buyer_type),
        annual_demand = COALESCE(NULLIF(?, ''), annual_demand),
        target_grade = COALESCE(NULLIF(?, ''), target_grade),
        status = COALESCE(NULLIF(?, ''), status),
        notes = COALESCE(NULLIF(?, ''), notes)
        WHERE id = ?");

    $inserted = 0;
    $updated  = 0;
    $skipped  = 0;

    try {
        $pdo->beginTransaction();
        $processed = 0;
        foreach ($rows as $row) {
            if ($processed >= BM_PROSPECT_IMPORT_MAX_ROWS) {
                $skipped += count($rows) - $processed;
                break;
            }
            $processed++;

            $getCell = function ($field) use ($colMap, $row) {
                if (!isset($colMap[$field])) return '';
                return trim((string)($row[$colMap[$field]] ?? ''));
            };

            $rawEmail   = filter_var($getCell('email'), FILTER_SANITIZE_EMAIL);
            $email      = filter_var($rawEmail, FILTER_VALIDATE_EMAIL) ? strtolower($rawEmail) : '';
            $company    = substr(cleanInput($getCell('company_name')), 0, 148);
            if ($company === '' && $email !== '') {
                $host = explode('@', $email)[1] ?? 'Corporate Buyer';
                $company = ucfirst(explode('.', $host)[0]) . ' Group';
            }
            if ($company === '') {
                $skipped++;
                continue;
            }

            $contact  = substr(cleanInput($getCell('contact_person')), 0, 98);
            $country  = substr(cleanInput($getCell('country')), 0, 78);
            $city     = substr(cleanInput($getCell('city')), 0, 78);
            $phone    = substr(cleanInput($getCell('phone')), 0, 48);
            $whatsapp = substr(cleanInput($getCell('whatsapp')), 0, 48);
            if ($whatsapp === '' && $phone !== '') $whatsapp = $phone;
            $mineral  = substr(cleanInput($getCell('target_mineral')), 0, 98);
            $bType    = substr(cleanInput($getCell('buyer_type')), 0, 78);
            $demand   = substr(cleanInput($getCell('annual_demand')), 0, 98);
            $grade    = substr(cleanInput($getCell('target_grade')), 0, 98);
            $statusRaw = $getCell('status');
            $notes    = cleanInput($getCell('notes'));

            // Duplicate check by email first, then exact company name
            $existingId = 0;
            if ($email !== '') {
                $findByEmail->execute([$email]);
                $existingId = (int)$findByEmail->fetchColumn();
            }
            if ($existingId <= 0) {
                $findByCompany->execute([strtolower($company)]);
                $existingId = (int)$findByCompany->fetchColumn();
            }

            if ($existingId > 0) {
                if ($duplicateMode === 'update') {
                    $updStatus = $statusRaw !== '' ? normalizeProspectPipelineStatus($statusRaw, $defaultStage) : '';
                    $updateStmt->execute([
                        $company, $contact, $country, $city, $email,
                        $phone, $whatsapp, $mineral, $bType,
                        $demand, $grade, $updStatus, $notes, $existingId
                    ]);
                    $updated++;
                } else {
                    $skipped++;
                }
                continue;
            }

            $insertStmt->execute([
                $company,
                $contact !== '' ? $contact : null,
                $country !== '' ? $country : 'International',
                $city !== '' ? $city : null,
                $email !== '' ? $email : null,
                $phone !== '' ? $phone : null,
                $whatsapp !== '' ? $whatsapp : null,
                $mineral !== '' ? $mineral : 'General Mineral',
                $bType !== '' ? $bType : 'Smelter / End User',
                $demand !== '' ? $demand : null,
                $grade !== '' ? $grade : null,
                normalizeProspectPipelineStatus($statusRaw, $defaultStage),
                $notes !== '' ? $notes : null,
            ]);
            $inserted++;
        }
        $pdo->commit();
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        return ['success' => false, 'error' => 'Import failed: ' . $e->getMessage()];
    }

    $summary = "Import complete: {$inserted} added" . ($updated > 0 ? ", {$updated} updated" : '') . ($skipped > 0 ? ", {$skipped} skipped" : '') . '.';
    if (function_exists('logAdminNotification') && ($inserted > 0 || $updated > 0)) {
        logAdminNotification($pdo, 'success', 'system', 'Buyer Directory Import', $summary, null, 'prospects.php');
    }

    return [
        'success'  => true,
        'inserted' => $inserted,
        'updated'  => $updated,
        'skipped'  => $skipped,
        'message'  => $summary,
    ];
}
