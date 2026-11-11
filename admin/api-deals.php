<?php
/**
 * Balochistan Minerals - Stage-Measured Export Deal & Evidence Vault API
 * Manages Deal lifecycle, Stage measurements, Document versioning/locking, Stockpile links, and ZIP proof packs.
 */

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/vault-helpers.php';
requirePermission('view_deals', true);

$pdo = getDB();
if (!$pdo) {
    header('Content-Type: application/json; charset=utf-8');
    http_response_code(503);
    echo json_encode(['success' => false, 'error' => 'Database connection unavailable.']);
    exit;
}

ensureDealVaultSchema($pdo);
ensureTeamSecuritySchema($pdo);
$user             = getLoggedInUser();
$currentUid       = (int)($user['id'] ?? 0);
$currentTier      = $user['tier'] ?? 'Owner';
$canFinancials    = hasPermission('view_deal_financials');
$canVerifyLock    = hasPermission('verify_lock_docs');
$canStandardClose = hasPermission('standard_close_deal');
$canForceClose    = hasPermission('force_close_deal');
$canReopen        = hasPermission('reopen_deal');
$canAssign        = hasPermission('assign_records');
$canCreateDeal    = hasPermission('create_deal');
$canBackup        = hasPermission('download_backup');
$canSeal          = $canForceClose;
$action = $_REQUEST['action'] ?? 'list';

function buildRoleFlagsPayload($canFinancials, $canVerifyLock, $canStandardClose, $canForceClose, $canReopen, $canAssign, $canCreateDeal, $canBackup) {
    return [
        'role'               => getAdminRole(),
        'can_financials'     => $canFinancials,
        'can_verify_lock'    => $canVerifyLock,
        'can_standard_close' => $canStandardClose,
        'can_force_close'    => $canForceClose,
        'can_reopen'         => $canReopen,
        'can_assign'         => $canAssign,
        'can_create'         => $canCreateDeal,
        'can_backup'         => $canBackup,
        'can_seal'           => $canForceClose,
    ];
}

function assertDealNotSealed($pdo, $dealId) {
    $st = $pdo->prepare("SELECT deal_state, deal_code FROM `deals` WHERE id = ? LIMIT 1");
    $st->execute([$dealId]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        echo json_encode(['success' => false, 'error' => 'Deal not found.']);
        exit;
    }
    if (in_array($row['deal_state'], ['Closed Settled', 'Archived'], true)) {
        http_response_code(423);
        echo json_encode(['success' => false, 'error' => "Deal {$row['deal_code']} is sealed ({$row['deal_state']}). Reopen it cleanly as Owner before modifying."]);
        exit;
    }
    return $row;
}

function getRestrictedDocCategoriesForField() {
    return ['spa', 'proforma_invoice', 'lc_swift', 'form_e', 'final_invoice', 'bank_prc', 'royalty_pass'];
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    releaseSessionLock();
}

// ---------------------------------------------------------
// STREAM ZIP PROOF PACK (GET)
// ---------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'GET' && $action === 'zip_pack') {
    $dealId = (int)($_GET['deal_id'] ?? 0);
    $scope  = ($_GET['scope'] ?? 'buyer') === 'all' ? 'all' : 'buyer';
    if ($scope === 'all' && !$canFinancials) {
        $scope = 'buyer';
    }
    if ($dealId <= 0) {
        http_response_code(400);
        echo 'Invalid deal ID.';
        exit;
    }

    $tmpZip = tempnam(sys_get_temp_dir(), 'bm_pack_') . '.zip';
    $res = buildDealZipProofPack($pdo, $dealId, $scope, $tmpZip);
    if (empty($res['success']) || !is_file($tmpZip)) {
        @unlink($tmpZip);
        http_response_code(500);
        echo $res['error'] ?? 'Failed generating ZIP archive.';
        exit;
    }

    $dealCode = preg_replace('/[^A-Za-z0-9\-_]/', '_', $res['deal']['deal_code']);
    $outName  = $dealCode . ($scope === 'all' ? '_FULL_INTERNAL_VAULT.zip' : '_BUYER_PROOF_PACK.zip');

    header('Content-Type: application/zip');
    header('X-Content-Type-Options: nosniff');
    header('Content-Disposition: attachment; filename="' . $outName . '"');
    header('Content-Length: ' . filesize($tmpZip));
    if (ob_get_level()) ob_end_clean();
    readfile($tmpZip);
    @unlink($tmpZip);
    exit;
}

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

// ---------------------------------------------------------
// GET ROUTES
// ---------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if ($action === 'list') {
        $whereSql = ($currentTier === 'Junior' && $currentUid > 0)
            ? " WHERE (d.assigned_user_id IS NULL OR d.assigned_user_id = ?)"
            : "";
        $stDeals = $pdo->prepare("SELECT d.*, u.full_name AS assigned_staff_name
            FROM `deals` d
            LEFT JOIN `admin_users` u ON u.id = d.assigned_user_id
            {$whereSql}
            ORDER BY d.is_public_demo ASC, FIELD(d.deal_state,'Active','Disputed','Closed Settled','Archived'), d.updated_at DESC, d.id DESC");
        $stDeals->execute(($whereSql !== '') ? [$currentUid] : []);
        $deals = $stDeals->fetchAll(PDO::FETCH_ASSOC);
        $globalAlerts = [];
        foreach ($deals as &$d) {
            $d['readiness']    = computeDealReadiness($pdo, (int)$d['id']);
            $d['intelligence'] = computeDealIntelligence($pdo, $d, $d['readiness']);
            $allocStmt = $pdo->prepare("SELECT COALESCE(SUM(allocated_mt), 0) FROM `deal_stockpile_allocations` WHERE deal_id = ?");
            $allocStmt->execute([$d['id']]);
            $d['allocated_mt'] = (float)$allocStmt->fetchColumn();

            foreach ($d['intelligence']['alerts'] as $al) {
                if (!$canFinancials && $al['type'] === 'balance') continue;
                $globalAlerts[] = [
                    'deal_id'   => (int)$d['id'],
                    'deal_code' => $d['deal_code'],
                    'company'   => $d['company_name'] ?: $d['buyer_name'],
                    'type'      => $al['type'],
                    'level'     => $al['level'],
                    'text'      => $al['text'],
                ];
            }

            if (!$canFinancials) {
                $d['unit_price_usd'] = 0;
                $d['intelligence']['contract_usd']    = 0;
                $d['intelligence']['received_usd']    = 0;
                $d['intelligence']['balance_due_usd'] = 0;
                $d['internal_notes'] = '';
            }
        }
        unset($d);

        $blobStats = $pdo->query("SELECT COUNT(*) AS unique_blobs, COALESCE(SUM(byte_size), 0) AS stored_bytes, COALESCE(SUM(orig_client_bytes), 0) AS orig_bytes, COALESCE(SUM(ref_count), 0) AS total_refs FROM `file_blobs`")->fetch(PDO::FETCH_ASSOC);
        $stockpiles = $pdo->query("SELECT s.id, s.mineral_name, s.location, s.tonnage_available, s.grade_spec, s.status,
                COALESCE((SELECT SUM(a.allocated_mt) FROM `deal_stockpile_allocations` a JOIN `deals` dl ON dl.id = a.deal_id WHERE a.stockpile_id = s.id AND dl.stockpile_deducted = 0 AND dl.deal_state = 'Active'), 0) AS committed_mt
            FROM `stockpiles` s ORDER BY s.mineral_name ASC")->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            'success'    => true,
            'deals'      => $deals,
            'vault_stats'=> $blobStats,
            'stockpiles' => $stockpiles,
            'exceptions' => $globalAlerts,
            'staff_list' => $canAssign ? getActiveStaffList($pdo) : [],
            'role_flags' => buildRoleFlagsPayload($canFinancials, $canVerifyLock, $canStandardClose, $canForceClose, $canReopen, $canAssign, $canCreateDeal, $canBackup),
        ]);
        exit;
    }

    if ($action === 'from_inquiry') {
        $inqId = (int)($_GET['inquiry_id'] ?? 0);
        $stmt = $pdo->prepare("SELECT * FROM `inquiries` WHERE id = ? LIMIT 1");
        $stmt->execute([$inqId]);
        $inq = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$inq) {
            echo json_encode(['success' => false, 'error' => 'Inquiry not found.']);
            exit;
        }
        echo json_encode([
            'success' => true,
            'inquiry' => $inq,
            'num_mt'  => (float)preg_replace('/[^0-9.]/', '', (string)($inq['quantity'] ?? '0')),
        ]);
        exit;
    }

    if ($action === 'detail') {
        $dealId = (int)($_GET['id'] ?? 0);
        $stmt = $pdo->prepare("SELECT * FROM `deals` WHERE id = ? LIMIT 1");
        $stmt->execute([$dealId]);
        $deal = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$deal) {
            echo json_encode(['success' => false, 'error' => 'Deal not found.']);
            exit;
        }
        if ($currentTier === 'Junior' && !empty($deal['assigned_user_id']) && (int)$deal['assigned_user_id'] !== $currentUid) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'This shipment deal is assigned to another colleague.']);
            exit;
        }

        $readiness    = computeDealReadiness($pdo, $dealId);
        $intelligence = computeDealIntelligence($pdo, $deal, $readiness);

        if (!$canFinancials) {
            $deal['unit_price_usd'] = 0;
            $deal['internal_notes'] = '';
            $intelligence['contract_usd']    = 0;
            $intelligence['received_usd']    = 0;
            $intelligence['balance_due_usd'] = 0;
        }

        $stageMetaMap = [];
        foreach (getDealStageDefinitions() as $def) {
            $stageMetaMap[$def['key']] = $def;
        }

        $msStmt = $pdo->prepare("SELECT * FROM `deal_milestones` WHERE deal_id = ? ORDER BY stage_order ASC");
        $msStmt->execute([$dealId]);
        $milestones = $msStmt->fetchAll(PDO::FETCH_ASSOC);

        $metStmt = $pdo->prepare("SELECT * FROM `deal_measurements` WHERE milestone_id = ? ORDER BY id ASC");
        $docStmt = $pdo->prepare("SELECT dd.*, fb.mime_type, fb.byte_size, fb.orig_client_bytes, fb.thumb_key, fb.sha256, fb.ref_count
            FROM `deal_documents` dd
            JOIN `file_blobs` fb ON fb.id = dd.blob_id
            WHERE dd.milestone_id = ?
            ORDER BY dd.doc_category ASC, dd.doc_version DESC, dd.id DESC");

        $restrictedMetrics = ['unit_price_usd', 'settled_usd', 'prc_ref'];
        $restrictedDocCats = getRestrictedDocCategoriesForField();

        foreach ($milestones as &$ms) {
            $metStmt->execute([$ms['id']]);
            $mets = $metStmt->fetchAll(PDO::FETCH_ASSOC);
            if (!$canFinancials) {
                $mets = array_values(array_filter($mets, function ($mRow) use ($restrictedMetrics) {
                    return !in_array($mRow['metric_key'], $restrictedMetrics, true);
                }));
                $ms['internal_remarks'] = '';
            }
            $ms['measurements'] = $mets;

            $docStmt->execute([$ms['id']]);
            $docs = $docStmt->fetchAll(PDO::FETCH_ASSOC);
            $visibleDocs = [];
            foreach ($docs as $doc) {
                if (!$canFinancials && $doc['visibility'] === 'internal_only' && in_array($doc['doc_category'], $restrictedDocCats, true)) {
                    continue;
                }
                $doc['file_url']  = 'api-vault-file.php?doc_id=' . (int)$doc['id'];
                $doc['thumb_url'] = !empty($doc['thumb_key']) ? ('api-vault-file.php?doc_id=' . (int)$doc['id'] . '&thumb=1') : null;
                $doc['size_label'] = formatBytesHuman($doc['byte_size']);
                $visibleDocs[] = $doc;
            }
            $ms['documents'] = $visibleDocs;

            $meta = $stageMetaMap[$ms['stage_key']] ?? [];
            $ms['short_title']    = $meta['short'] ?? $ms['stage_title'];
            $ms['description']    = $meta['description'] ?? '';
            $ms['doc_categories'] = $meta['doc_categories'] ?? [];
            $ms['mandatory_docs'] = $meta['mandatory_docs'] ?? [];
        }
        unset($ms);

        $allocStmt = $pdo->prepare("SELECT a.*, s.mineral_name, s.location, s.tonnage_available, s.grade_spec, s.status AS stockpile_status
            FROM `deal_stockpile_allocations` a
            JOIN `stockpiles` s ON s.id = a.stockpile_id
            WHERE a.deal_id = ?");
        $allocStmt->execute([$dealId]);
        $allocations = $allocStmt->fetchAll(PDO::FETCH_ASSOC);

        $lotsStmt = $pdo->prepare("SELECT * FROM `deal_lots` WHERE deal_id = ? ORDER BY id ASC");
        $lotsStmt->execute([$dealId]);
        $lots = $lotsStmt->fetchAll(PDO::FETCH_ASSOC);

        $payments = [];
        if ($canFinancials) {
            $payStmt = $pdo->prepare("SELECT * FROM `deal_payments` WHERE deal_id = ? ORDER BY id ASC");
            $payStmt->execute([$dealId]);
            $payments = $payStmt->fetchAll(PDO::FETCH_ASSOC);
        }

        $auditStmt = $pdo->prepare("SELECT * FROM `deal_audit_log` WHERE deal_id = ? ORDER BY id DESC LIMIT 40");
        $auditStmt->execute([$dealId]);
        $auditLogs = $auditStmt->fetchAll(PDO::FETCH_ASSOC);

        $flStmt = $pdo->prepare("SELECT * FROM `deal_field_links` WHERE deal_id = ? AND is_active = 1 ORDER BY id DESC");
        $flStmt->execute([$dealId]);
        $fieldLinks = $flStmt->fetchAll(PDO::FETCH_ASSOC);

        $tokStmt = $pdo->prepare("SELECT token, created_at, last_viewed_at FROM `deal_access_tokens` WHERE deal_id = ? AND is_active = 1 ORDER BY id DESC LIMIT 1");
        $tokStmt->execute([$dealId]);
        $activeTok = $tokStmt->fetch(PDO::FETCH_ASSOC);

        if (!$activeTok) {
            $newTok = bin2hex(random_bytes(16));
            $pdo->prepare("INSERT INTO `deal_access_tokens` (`deal_id`, `token`) VALUES (?, ?)")->execute([$dealId, $newTok]);
            $activeTok = ['token' => $newTok, 'created_at' => date('Y-m-d H:i:s'), 'last_viewed_at' => null];
        }

        echo json_encode([
            'success'      => true,
            'deal'         => $deal,
            'milestones'   => $milestones,
            'allocations'  => $allocations,
            'lots'         => $lots,
            'payments'     => $payments,
            'audit_logs'   => $auditLogs,
            'field_links'  => $fieldLinks,
            'readiness'    => $readiness,
            'intelligence' => $intelligence,
            'buyer_token'  => $activeTok,
            'presets'      => getMineralAssayPresets($deal['commodity']),
            'staff_list'   => $canAssign ? getActiveStaffList($pdo) : [],
            'role_flags'   => buildRoleFlagsPayload($canFinancials, $canVerifyLock, $canStandardClose, $canForceClose, $canReopen, $canAssign, $canCreateDeal, $canBackup),
            'stockpiles'   => $pdo->query("SELECT id, mineral_name, location, tonnage_available, grade_spec FROM `stockpiles` ORDER BY mineral_name ASC")->fetchAll(PDO::FETCH_ASSOC),
        ]);
        exit;
    }

    echo json_encode(['success' => false, 'error' => 'Unknown action.']);
    exit;
}

// ---------------------------------------------------------
// POST MUTATIONS (CSRF Required)
// ---------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!verifyCsrfToken($csrf)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Security CSRF check failed. Please refresh the page.']);
        exit;
    }

    // 1. CREATE DEAL
    if ($action === 'create_deal') {
        if (!$canCreateDeal) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Manager or Owner authorization required to open a new export deal.']);
            exit;
        }
        $inquiryId   = (int)($_POST['inquiry_id'] ?? 0);
        $stockpileId = (int)($_POST['stockpile_id'] ?? 0);
        $buyerName   = trim((string)($_POST['buyer_name'] ?? ''));
        $companyName = trim((string)($_POST['company_name'] ?? ''));
        $country     = trim((string)($_POST['country'] ?? ''));
        $commodity   = trim((string)($_POST['commodity'] ?? ''));
        $gradeSpec   = trim((string)($_POST['grade_spec'] ?? ''));
        $contractMt  = (float)($_POST['contracted_mt'] ?? 0);
        $incoterm    = trim((string)($_POST['incoterm'] ?? 'FOB Karachi')) ?: 'FOB Karachi';
        $payTerms    = trim((string)($_POST['payment_terms'] ?? 'Irrevocable L/C at Sight')) ?: 'Irrevocable L/C at Sight';
        $unitPrice   = (float)($_POST['unit_price_usd'] ?? 0);
        $pol         = trim((string)($_POST['port_of_loading'] ?? 'Karachi Port / Port Qasim')) ?: 'Karachi Port / Port Qasim';
        $pod         = trim((string)($_POST['port_of_discharge'] ?? ''));
        $etd         = trim((string)($_POST['estimated_departure'] ?? ''));
        $isDemo      = !empty($_POST['is_public_demo']) ? 1 : 0;

        if ($buyerName === '' || $commodity === '') {
            echo json_encode(['success' => false, 'error' => 'Buyer name and commodity are required.']);
            exit;
        }

        $ids = generateDealTrackingIdentifiers();
        try {
            $pdo->beginTransaction();

            $ins = $pdo->prepare("INSERT INTO `deals`
                (`deal_code`, `tracking_code`, `inquiry_id`, `buyer_name`, `company_name`, `country`, `commodity`, `grade_spec`,
                 `contracted_mt`, `incoterm`, `payment_terms`, `unit_price_usd`, `port_of_loading`, `port_of_discharge`,
                 `estimated_departure`, `is_public_demo`, `buyer_public_notes`)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

            $ins->execute([
                $ids['deal_code'],
                $ids['tracking_code'],
                $inquiryId > 0 ? $inquiryId : null,
                $buyerName,
                $companyName,
                $country,
                $commodity,
                $gradeSpec,
                $contractMt,
                $incoterm,
                $payTerms,
                $unitPrice,
                $pol,
                $pod,
                $etd !== '' ? $etd : null,
                $isDemo,
                'Deal initiated. Preparing commercial & compliance verification stages.',
            ]);
            $dealId = (int)$pdo->lastInsertId();

            // Create buyer access token
            $pdo->prepare("INSERT INTO `deal_access_tokens` (`deal_id`, `token`, `label`) VALUES (?, ?, 'Primary Buyer Link')")
                ->execute([$dealId, $ids['access_token']]);

            // Initialize 7 stages + mineral metrics
            initializeDealMilestonesAndPresets($pdo, $dealId, $commodity, [
                'mark_contract_active' => true,
                'metrics' => [
                    'contracted_mt'     => $contractMt > 0 ? (string)$contractMt : '',
                    'incoterm'          => $incoterm,
                    'payment_terms'     => $payTerms,
                    'unit_price_usd'    => $unitPrice > 0 ? (string)$unitPrice : '',
                    'port_of_loading'   => $pol,
                    'port_of_discharge' => $pod,
                ],
            ]);

            // Optional stockpile allocation
            if ($stockpileId > 0) {
                $allocMt = $contractMt > 0 ? $contractMt : 0;
                $pdo->prepare("INSERT IGNORE INTO `deal_stockpile_allocations` (`deal_id`, `stockpile_id`, `allocated_mt`, `lot_code`) VALUES (?, ?, ?, ?)")
                    ->execute([$dealId, $stockpileId, $allocMt, 'LOT-' . date('Ymd')]);
            }

            // Mirror lightweight summary row in shipments for backward compatibility
            $shIns = $pdo->prepare("INSERT INTO `shipments`
                (`tracking_code`, `buyer_name`, `company_name`, `commodity`, `tonnage`, `port_of_loading`, `port_of_discharge`, `status_stage`, `estimated_departure`, `notes`)
                VALUES (?, ?, ?, ?, ?, ?, ?, 'Contract & L/C Verified', ?, ?)");
            $shIns->execute([
                $ids['tracking_code'],
                $buyerName,
                $companyName ?: $buyerName,
                $commodity,
                ($contractMt > 0 ? $contractMt . ' MT' : 'TBD'),
                $pol,
                $pod ?: 'TBD',
                $etd !== '' ? $etd : null,
                'Deal managed via Stage-Measured Vault (' . $ids['deal_code'] . ').',
            ]);
            $legacyId = (int)$pdo->lastInsertId();
            $pdo->prepare("UPDATE `deals` SET legacy_shipment_id = ? WHERE id = ?")->execute([$legacyId, $dealId]);

            if ($inquiryId > 0) {
                $pdo->prepare("UPDATE `inquiries` SET status = 'Contract Signed' WHERE id = ?")->execute([$inquiryId]);
            }

            $pdo->commit();

            logDealAudit($pdo, $dealId, 'create_deal', "Created deal {$ids['deal_code']} for {$buyerName} ({$commodity}, {$contractMt} MT)");
            logAdminNotification($pdo, 'success', 'deals', 'New Export Deal Created', "{$ids['deal_code']} for {$buyerName} ({$commodity})", $inquiryId ?: null, "deals.php?id={$dealId}");

            echo json_encode([
                'success' => true,
                'deal_id' => $dealId,
                'deal_code' => $ids['deal_code'],
                'tracking_code' => $ids['tracking_code'],
            ]);
        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            echo json_encode(['success' => false, 'error' => 'Failed creating deal: ' . $e->getMessage()]);
        }
        exit;
    }

    // 2. SAVE MILESTONE & MEASUREMENTS
    if ($action === 'save_milestone') {
        $dealId       = (int)($_POST['deal_id'] ?? 0);
        assertDealNotSealed($pdo, $dealId);
        $milestoneId  = (int)($_POST['milestone_id'] ?? 0);
        $status       = $_POST['status'] ?? 'In Progress';
        $allowedStats = ['Pending', 'In Progress', 'Awaiting Verification', 'Verified Complete'];
        if (!in_array($status, $allowedStats, true)) $status = 'In Progress';

        $buyerSummary   = trim((string)($_POST['buyer_summary'] ?? ''));
        $internalRemarks= trim((string)($_POST['internal_remarks'] ?? ''));
        $metricsJson    = $_POST['metrics_json'] ?? '[]';
        $metricsList    = json_decode($metricsJson, true);
        if (!is_array($metricsList)) $metricsList = [];

        $msStmt = $pdo->prepare("SELECT * FROM `deal_milestones` WHERE id = ? AND deal_id = ? LIMIT 1");
        $msStmt->execute([$milestoneId, $dealId]);
        $ms = $msStmt->fetch(PDO::FETCH_ASSOC);
        if (!$ms) {
            echo json_encode(['success' => false, 'error' => 'Milestone not found.']);
            exit;
        }

        $completedAt = ($status === 'Verified Complete') ? ($ms['completed_at'] ?: date('Y-m-d H:i:s')) : null;
        if (!$canVerifyLock && $status === 'Verified Complete') {
            $status = 'Awaiting Verification';
            $completedAt = null;
        }
        $verifiedBy  = ($status === 'Verified Complete') ? ($user['full_name'] ?? 'Admin') : null;
        $startedAt   = ($status !== 'Pending' && empty($ms['started_at'])) ? date('Y-m-d H:i:s') : $ms['started_at'];
        $remarksToSave = $canFinancials ? $internalRemarks : (string)($ms['internal_remarks'] ?? '');

        $pdo->prepare("UPDATE `deal_milestones` SET status = ?, buyer_summary = ?, internal_remarks = ?, started_at = ?, completed_at = ?, verified_by = ? WHERE id = ?")
            ->execute([$status, $buyerSummary, $remarksToSave, $startedAt, $completedAt, $verifiedBy, $milestoneId]);

        // Upsert measurements & collect top-level deal mirrors
        $upsertM = $pdo->prepare("INSERT INTO `deal_measurements`
            (`deal_id`, `milestone_id`, `metric_key`, `metric_label`, `metric_value`, `unit`, `source_ref`, `is_buyer_visible`)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
              metric_label = VALUES(metric_label),
              metric_value = VALUES(metric_value),
              unit = VALUES(unit),
              source_ref = VALUES(source_ref),
              is_buyer_visible = VALUES(is_buyer_visible)");

        $dealPatch = [];
        foreach ($metricsList as $item) {
            $mKey   = preg_replace('/[^a-zA-Z0-9_]/', '_', strtolower(trim((string)($item['key'] ?? ''))));
            $mLabel = trim((string)($item['label'] ?? ''));
            $mVal   = trim((string)($item['value'] ?? ''));
            $mUnit  = trim((string)($item['unit'] ?? ''));
            $mSrc   = trim((string)($item['source_ref'] ?? ''));
            $mVis   = isset($item['is_buyer_visible']) ? ((int)$item['is_buyer_visible'] ? 1 : 0) : 1;

            if ($mKey === '' && $mLabel !== '') {
                $mKey = 'custom_' . substr(preg_replace('/[^a-z0-9]+/', '_', strtolower($mLabel)), 0, 32) . '_' . substr(md5($mLabel), 0, 4);
            }
            if ($mKey === '' || $mLabel === '') continue;
            if (!$canFinancials && in_array($mKey, ['unit_price_usd', 'settled_usd', 'prc_ref'], true)) {
                continue;
            }

            $upsertM->execute([$dealId, $milestoneId, $mKey, $mLabel, $mVal, $mUnit, $mSrc, $mVis]);

            if ($mVal !== '') {
                if ($mKey === 'vessel_name') $dealPatch['vessel_name'] = $mVal;
                if ($mKey === 'bl_number') $dealPatch['bill_of_lading'] = $mVal;
                if ($mKey === 'form_e_ref') $dealPatch['form_e_ref'] = $mVal;
                if ($mKey === 'gd_number') $dealPatch['gd_number'] = $mVal;
                if ($mKey === 'port_of_loading') $dealPatch['port_of_loading'] = $mVal;
                if ($mKey === 'port_of_discharge') $dealPatch['port_of_discharge'] = $mVal;
                if ($mKey === 'contracted_mt') $dealPatch['contracted_mt'] = (float)$mVal;
                if ($mKey === 'incoterm') $dealPatch['incoterm'] = $mVal;
                if ($mKey === 'payment_terms') $dealPatch['payment_terms'] = $mVal;
                if ($mKey === 'unit_price_usd') $dealPatch['unit_price_usd'] = (float)$mVal;
            }
        }

        // Determine highest active/completed stage on deal
        $allMs = $pdo->prepare("SELECT * FROM `deal_milestones` WHERE deal_id = ? ORDER BY stage_order ASC");
        $allMs->execute([$dealId]);
        $msRows = $allMs->fetchAll(PDO::FETCH_ASSOC);

        $headlineStageKey = $ms['stage_key'];
        $headlineStageLabel = mapStageKeyToLegacyStatus($headlineStageKey);
        foreach ($msRows as $rowM) {
            if ($rowM['status'] === 'In Progress' || $rowM['status'] === 'Awaiting Verification') {
                $headlineStageKey = $rowM['stage_key'];
                $headlineStageLabel = mapStageKeyToLegacyStatus($headlineStageKey);
            } elseif ($rowM['status'] === 'Verified Complete') {
                $headlineStageKey = $rowM['stage_key'];
                $headlineStageLabel = mapStageKeyToLegacyStatus($headlineStageKey);
            }
        }

        $dealPatch['current_stage_key']   = $headlineStageKey;
        $dealPatch['current_stage_label'] = $headlineStageLabel;
        if ($buyerSummary !== '') {
            $dealPatch['buyer_public_notes'] = $buyerSummary;
        }

        $sets = [];
        $vals = [];
        foreach ($dealPatch as $col => $v) {
            $sets[] = "`{$col}` = ?";
            $vals[] = $v;
        }
        $vals[] = $dealId;
        $pdo->prepare("UPDATE `deals` SET " . implode(', ', $sets) . " WHERE id = ?")->execute($vals);

        // Sync backward-compat shipments row
        $dealRow = $pdo->prepare("SELECT * FROM `deals` WHERE id = ? LIMIT 1");
        $dealRow->execute([$dealId]);
        $dr = $dealRow->fetch(PDO::FETCH_ASSOC);
        if ($dr && !empty($dr['legacy_shipment_id'])) {
            $pdo->prepare("UPDATE `shipments` SET status_stage = ?, vessel_name = ?, bill_of_lading = ?, port_of_loading = ?, port_of_discharge = ?, notes = ? WHERE id = ?")
                ->execute([
                    $dr['current_stage_label'],
                    $dr['vessel_name'],
                    $dr['bill_of_lading'],
                    $dr['port_of_loading'],
                    $dr['port_of_discharge'],
                    $dr['buyer_public_notes'] ?? '',
                    $dr['legacy_shipment_id'],
                ]);
        }

        logDealAudit($pdo, $dealId, 'milestone_update', "Updated Stage {$ms['stage_order']} ({$ms['stage_title']}) -> {$status}");

        echo json_encode([
            'success'      => true,
            'readiness'    => computeDealReadiness($pdo, $dealId),
            'headline'     => $headlineStageLabel,
        ]);
        exit;
    }

    // 3. UPLOAD STAGE DOCUMENT / COMPRESSED PHOTO
    if ($action === 'upload_doc') {
        $dealId       = (int)($_POST['deal_id'] ?? 0);
        assertDealNotSealed($pdo, $dealId);
        $milestoneId  = (int)($_POST['milestone_id'] ?? 0);
        $docCategory  = preg_replace('/[^a-zA-Z0-9_]/', '_', trim((string)($_POST['doc_category'] ?? 'general_evidence')));
        $title        = trim((string)($_POST['title'] ?? ''));
        $docStatus    = $_POST['doc_status'] ?? 'Under Review';
        $visibility   = $_POST['visibility'] ?? 'buyer_portal';
        $origBytes    = (int)($_POST['orig_client_bytes'] ?? 0);
        $keepExisting = !empty($_POST['keep_existing']);

        if (!in_array($docStatus, ['Draft', 'Under Review', 'Verified Final', 'Superseded'], true)) $docStatus = 'Under Review';
        if (!$canVerifyLock && $docStatus === 'Verified Final') {
            $docStatus = 'Under Review';
        }
        if (!in_array($visibility, ['internal_only', 'buyer_portal', 'public_sample'], true)) $visibility = 'buyer_portal';

        $chk = $pdo->prepare("SELECT id FROM `deal_milestones` WHERE id = ? AND deal_id = ? LIMIT 1");
        $chk->execute([$milestoneId, $dealId]);
        if (!$chk->fetchColumn()) {
            echo json_encode(['success' => false, 'error' => 'Invalid deal or milestone reference.']);
            exit;
        }

        $fileEntry  = $_FILES['file'] ?? null;
        $thumbEntry = $_FILES['thumb'] ?? null;
        $stored = storeUploadedVaultFile($pdo, $fileEntry, $thumbEntry, $origBytes);
        if (empty($stored['success'])) {
            echo json_encode(['success' => false, 'error' => $stored['error'] ?? 'Upload rejected.']);
            exit;
        }

        $maxVerStmt = $pdo->prepare("SELECT COALESCE(MAX(doc_version), 0) FROM `deal_documents` WHERE deal_id = ? AND milestone_id = ? AND doc_category = ?");
        $maxVerStmt->execute([$dealId, $milestoneId, $docCategory]);
        $nextVersion = ((int)$maxVerStmt->fetchColumn()) + 1;

        // Auto-supersede prior drafts on non-gallery singleton docs when marked Verified Final
        $isPhotoCat = (strpos($docCategory, 'photo') !== false);
        if (!$isPhotoCat && !$keepExisting && $docStatus === 'Verified Final') {
            $pdo->prepare("UPDATE `deal_documents` SET doc_status = 'Superseded'
                WHERE deal_id = ? AND milestone_id = ? AND doc_category = ? AND doc_status != 'Superseded'")
                ->execute([$dealId, $milestoneId, $docCategory]);
        }

        if ($title === '') {
            $title = $stored['safe_name'];
        }

        $verBy = ($docStatus === 'Verified Final') ? ($user['full_name'] ?? 'Admin') : null;
        $verAt = ($docStatus === 'Verified Final') ? date('Y-m-d H:i:s') : null;

        $ins = $pdo->prepare("INSERT INTO `deal_documents`
            (`deal_id`, `milestone_id`, `blob_id`, `doc_category`, `title`, `original_filename`, `doc_version`, `doc_status`, `visibility`, `uploaded_by`, `verified_by`, `verified_at`)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $ins->execute([
            $dealId,
            $milestoneId,
            $stored['blob_id'],
            $docCategory,
            $title,
            $stored['safe_name'],
            $nextVersion,
            $docStatus,
            $visibility,
            $user['full_name'] ?? 'Admin',
            $verBy,
            $verAt,
        ]);

        logDealAudit($pdo, $dealId, 'doc_upload', "Uploaded {$docCategory} v{$nextVersion} ({$title}) [{$docStatus}]");

        echo json_encode([
            'success'      => true,
            'doc_id'       => (int)$pdo->lastInsertId(),
            'deduplicated' => $stored['deduplicated'],
            'stored_bytes' => $stored['byte_size'],
            'orig_bytes'   => max($origBytes, $stored['byte_size']),
            'version'      => $nextVersion,
            'readiness'    => computeDealReadiness($pdo, $dealId),
        ]);
        exit;
    }

    // 4. UPDATE DOCUMENT STATUS / VISIBILITY / LOCK / DELETE
    if ($action === 'update_doc') {
        $docId = (int)($_POST['doc_id'] ?? 0);
        $op    = $_POST['op'] ?? '';

        $stmt = $pdo->prepare("SELECT * FROM `deal_documents` WHERE id = ? LIMIT 1");
        $stmt->execute([$docId]);
        $doc = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$doc) {
            echo json_encode(['success' => false, 'error' => 'Document not found.']);
            exit;
        }
        assertDealNotSealed($pdo, (int)$doc['deal_id']);

        if ($op === 'verify_lock') {
            if (!$canVerifyLock) {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'Only Manager or Owner can lock & verify documents.']);
                exit;
            }
            $pdo->prepare("UPDATE `deal_documents` SET doc_status = 'Verified Final', is_locked = 1, verified_by = ?, verified_at = NOW() WHERE id = ?")
                ->execute([$user['full_name'] ?? 'Admin', $docId]);
            logDealAudit($pdo, (int)$doc['deal_id'], 'doc_lock', "Verified & locked document #{$docId} ({$doc['title']})");
        } elseif ($op === 'set_visibility') {
            $vis = $_POST['visibility'] ?? 'internal_only';
            if (!in_array($vis, ['internal_only', 'buyer_portal', 'public_sample'], true)) $vis = 'internal_only';
            $pdo->prepare("UPDATE `deal_documents` SET visibility = ? WHERE id = ?")->execute([$vis, $docId]);
            logDealAudit($pdo, (int)$doc['deal_id'], 'doc_visibility', "Set visibility of '{$doc['title']}' -> {$vis}");
        } elseif ($op === 'set_status') {
            if ((int)$doc['is_locked'] === 1 && ($_POST['doc_status'] ?? '') !== 'Superseded') {
                echo json_encode(['success' => false, 'error' => 'Locked verified document can only be superseded by a newer version.']);
                exit;
            }
            $st = $_POST['doc_status'] ?? 'Under Review';
            if (!in_array($st, ['Draft', 'Under Review', 'Verified Final', 'Superseded'], true)) $st = 'Under Review';
            if (!$canVerifyLock && $st === 'Verified Final') {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'Only Manager or Owner can mark a document Verified Final.']);
                exit;
            }
            $pdo->prepare("UPDATE `deal_documents` SET doc_status = ? WHERE id = ?")->execute([$st, $docId]);
            logDealAudit($pdo, (int)$doc['deal_id'], 'doc_status', "Updated status of '{$doc['title']}' -> {$st}");
        } elseif ($op === 'delete') {
            if (!$canVerifyLock) {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'Only Manager or Owner can remove vault uploads.']);
                exit;
            }
            if ((int)$doc['is_locked'] === 1) {
                echo json_encode(['success' => false, 'error' => 'Verified & locked compliance records cannot be deleted (upload a newer version or supersede).']);
                exit;
            }
            $pdo->prepare("DELETE FROM `deal_documents` WHERE id = ?")->execute([$docId]);
            decrementOrDeleteVaultBlob($pdo, (int)$doc['blob_id']);
            logDealAudit($pdo, (int)$doc['deal_id'], 'doc_delete', "Deleted unlocked upload '{$doc['title']}'");
        }

        echo json_encode([
            'success'   => true,
            'readiness' => computeDealReadiness($pdo, (int)$doc['deal_id']),
        ]);
        exit;
    }

    // 5. ALLOCATE STOCKPILE
    if ($action === 'save_allocation') {
        $dealId      = (int)($_POST['deal_id'] ?? 0);
        assertDealNotSealed($pdo, $dealId);
        $stockpileId = (int)($_POST['stockpile_id'] ?? 0);
        $allocMt     = (float)($_POST['allocated_mt'] ?? 0);
        $lotCode     = trim((string)($_POST['lot_code'] ?? ''));

        if ($dealId <= 0 || $stockpileId <= 0 || $allocMt <= 0) {
            echo json_encode(['success' => false, 'error' => 'Select a valid stockpile and positive tonnage.']);
            exit;
        }

        $pdo->prepare("INSERT INTO `deal_stockpile_allocations` (`deal_id`, `stockpile_id`, `allocated_mt`, `lot_code`)
            VALUES (?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE allocated_mt = VALUES(allocated_mt), lot_code = VALUES(lot_code)")
            ->execute([$dealId, $stockpileId, $allocMt, $lotCode]);
        logDealAudit($pdo, $dealId, 'stockpile_alloc', "Linked stockpile #{$stockpileId} ({$allocMt} MT, lot {$lotCode})");

        echo json_encode(['success' => true]);
        exit;
    }

    if ($action === 'remove_allocation') {
        $allocId = (int)($_POST['allocation_id'] ?? 0);
        $alRow = $pdo->prepare("SELECT deal_id, stockpile_id FROM `deal_stockpile_allocations` WHERE id = ? LIMIT 1");
        $alRow->execute([$allocId]);
        $al = $alRow->fetch(PDO::FETCH_ASSOC);
        if ($al) {
            assertDealNotSealed($pdo, (int)$al['deal_id']);
            $pdo->prepare("DELETE FROM `deal_stockpile_allocations` WHERE id = ?")->execute([$allocId]);
            logDealAudit($pdo, (int)$al['deal_id'], 'stockpile_unlink', "Removed stockpile link #{$al['stockpile_id']}");
        }
        echo json_encode(['success' => true]);
        exit;
    }

    // 6. ROTATE BUYER ACCESS TOKEN
    if ($action === 'rotate_token') {
        $dealId = (int)($_POST['deal_id'] ?? 0);
        $pdo->prepare("UPDATE `deal_access_tokens` SET is_active = 0 WHERE deal_id = ?")->execute([$dealId]);
        $newTok = bin2hex(random_bytes(16));
        $pdo->prepare("INSERT INTO `deal_access_tokens` (`deal_id`, `token`, `label`) VALUES (?, ?, 'Rotated Buyer Link')")->execute([$dealId, $newTok]);
        logDealAudit($pdo, $dealId, 'token_rotate', 'Rotated buyer tracking access token');
        echo json_encode(['success' => true, 'token' => $newTok]);
        exit;
    }

    // 7. PARTIAL SHIPMENT LOTS
    if ($action === 'save_lot') {
        $dealId     = (int)($_POST['deal_id'] ?? 0);
        assertDealNotSealed($pdo, $dealId);
        $lotCode    = trim((string)($_POST['lot_code'] ?? ''));
        $tonnageMt  = (float)($_POST['tonnage_mt'] ?? 0);
        $vessel     = trim((string)($_POST['vessel_name'] ?? ''));
        $bl         = trim((string)($_POST['bill_of_lading'] ?? ''));
        $etd        = trim((string)($_POST['etd'] ?? ''));
        $arrDate    = trim((string)($_POST['arrival_date'] ?? ''));
        $outturnDmt = (float)($_POST['outturn_dmt'] ?? 0);
        $lotStatus  = $_POST['lot_status'] ?? 'Staged';
        if (!in_array($lotStatus, ['Staged', 'Loaded', 'In Transit', 'Discharged'], true)) $lotStatus = 'Staged';

        if ($lotCode === '' || $tonnageMt <= 0) {
            echo json_encode(['success' => false, 'error' => 'Lot code and positive MT are required.']);
            exit;
        }

        $pdo->prepare("INSERT INTO `deal_lots` (`deal_id`, `lot_code`, `tonnage_mt`, `vessel_name`, `bill_of_lading`, `etd`, `arrival_date`, `outturn_dmt`, `lot_status`)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)")
            ->execute([$dealId, $lotCode, $tonnageMt, $vessel, $bl, $etd ?: null, $arrDate ?: null, $outturnDmt, $lotStatus]);
        logDealAudit($pdo, $dealId, 'lot_added', "Added shipment lot {$lotCode} ({$tonnageMt} MT — {$vessel}) [{$lotStatus}]");
        echo json_encode(['success' => true]);
        exit;
    }

    if ($action === 'delete_lot') {
        $lotId = (int)($_POST['lot_id'] ?? 0);
        $lr = $pdo->prepare("SELECT deal_id, lot_code FROM `deal_lots` WHERE id = ? LIMIT 1");
        $lr->execute([$lotId]);
        $row = $lr->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            assertDealNotSealed($pdo, (int)$row['deal_id']);
            $pdo->prepare("DELETE FROM `deal_lots` WHERE id = ?")->execute([$lotId]);
            logDealAudit($pdo, (int)$row['deal_id'], 'lot_removed', "Removed shipment lot {$row['lot_code']}");
        }
        echo json_encode(['success' => true]);
        exit;
    }

    // 8. TRANCHE PAYMENT LEDGER (Financial Roles Only)
    if ($action === 'save_payment') {
        if (!$canFinancials) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Financial permissions required to record payment tranches.']);
            exit;
        }
        $dealId     = (int)($_POST['deal_id'] ?? 0);
        assertDealNotSealed($pdo, $dealId);
        $label      = trim((string)($_POST['tranche_label'] ?? ''));
        $tType      = $_POST['tranche_type'] ?? 'Provisional';
        if (!in_array($tType, ['Advance', 'Provisional', 'Final Settlement', 'Credit/Debit Note'], true)) $tType = 'Provisional';
        $expUsd     = (float)($_POST['expected_usd'] ?? 0);
        $recUsd     = (float)($_POST['received_usd'] ?? 0);
        $payRef     = trim((string)($_POST['payment_ref'] ?? ''));
        $status     = $_POST['status'] ?? ($recUsd > 0 ? 'Received' : 'Pending');
        if (!in_array($status, ['Pending', 'Received', 'Settled'], true)) $status = 'Pending';
        $paidDate   = trim((string)($_POST['paid_date'] ?? ''));

        if ($label === '') {
            echo json_encode(['success' => false, 'error' => 'Tranche description required.']);
            exit;
        }
        $pdo->prepare("INSERT INTO `deal_payments` (`deal_id`, `tranche_label`, `tranche_type`, `expected_usd`, `received_usd`, `payment_ref`, `status`, `paid_date`)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)")
            ->execute([$dealId, $label, $tType, $expUsd, $recUsd, $payRef, $status, $paidDate ?: null]);
        logDealAudit($pdo, $dealId, 'payment_logged', "Logged payment tranche '{$label}': \${$recUsd} received (Ref: {$payRef})");
        echo json_encode(['success' => true]);
        exit;
    }

    if ($action === 'delete_payment') {
        if (!$canFinancials) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Financial permissions required.']);
            exit;
        }
        $payId = (int)($_POST['payment_id'] ?? 0);
        $pr = $pdo->prepare("SELECT deal_id, tranche_label FROM `deal_payments` WHERE id = ? LIMIT 1");
        $pr->execute([$payId]);
        $row = $pr->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            assertDealNotSealed($pdo, (int)$row['deal_id']);
            $pdo->prepare("DELETE FROM `deal_payments` WHERE id = ?")->execute([$payId]);
            logDealAudit($pdo, (int)$row['deal_id'], 'payment_deleted', "Deleted payment entry '{$row['tranche_label']}'");
        }
        echo json_encode(['success' => true]);
        exit;
    }

    // 9. SCOPED FIELD SUPERVISOR / CLEARING AGENT UPLOAD LINKS
    if ($action === 'create_field_link') {
        $dealId   = (int)($_POST['deal_id'] ?? 0);
        assertDealNotSealed($pdo, $dealId);
        $stageKey = preg_replace('/[^a-zA-Z0-9_]/', '', (string)($_POST['stage_key'] ?? 'yard_staging'));
        $label    = trim((string)($_POST['assignee_label'] ?? 'Yard / C&F Supervisor')) ?: 'Yard / C&F Supervisor';
        $tok      = bin2hex(random_bytes(16));
        $exp      = date('Y-m-d H:i:s', strtotime('+14 days'));

        $pdo->prepare("INSERT INTO `deal_field_links` (`deal_id`, `stage_key`, `token`, `assignee_label`, `expires_at`) VALUES (?, ?, ?, ?, ?)")
            ->execute([$dealId, $stageKey, $tok, $label, $exp]);
        logDealAudit($pdo, $dealId, 'field_link_created', "Generated scoped mobile upload link for {$label} (Stage: {$stageKey})");
        echo json_encode(['success' => true, 'token' => $tok]);
        exit;
    }

    if ($action === 'revoke_field_link') {
        $linkId = (int)($_POST['link_id'] ?? 0);
        $lr = $pdo->prepare("SELECT deal_id FROM `deal_field_links` WHERE id = ? LIMIT 1");
        $lr->execute([$linkId]);
        $did = (int)$lr->fetchColumn();
        if ($did > 0) {
            $pdo->prepare("UPDATE `deal_field_links` SET is_active = 0 WHERE id = ?")->execute([$linkId]);
            logDealAudit($pdo, $did, 'field_link_revoked', "Revoked field upload link #{$linkId}");
        }
        echo json_encode(['success' => true]);
        exit;
    }

    // 10. 1-BUTTON FINALIZE & CLOSE DEAL (+ AUTO-DEDUCT STOCKPILE & SEAL VAULT)
    if ($action === 'close_deal') {
        if (!$canStandardClose && !$canForceClose) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Manager or Owner role required to finalize deals.']);
            exit;
        }
        $dealId      = (int)($_POST['deal_id'] ?? 0);
        $forceClose  = !empty($_POST['force_close']);
        if ($forceClose && !$canForceClose) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Only Owner can Force-Close a deal with incomplete compliance documents.']);
            exit;
        }
        $deductStock = !empty($_POST['deduct_stockpile']);
        $retireToken = !empty($_POST['retire_token']);
        $closeNote   = trim((string)($_POST['close_note'] ?? 'Deal verified & settled cleanly.'));

        $dealStmt = $pdo->prepare("SELECT * FROM `deals` WHERE id = ? LIMIT 1");
        $dealStmt->execute([$dealId]);
        $deal = $dealStmt->fetch(PDO::FETCH_ASSOC);
        if (!$deal) {
            echo json_encode(['success' => false, 'error' => 'Deal not found.']);
            exit;
        }
        if ($currentTier === 'Junior' && !empty($deal['assigned_user_id']) && (int)$deal['assigned_user_id'] !== $currentUid) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'This shipment deal is assigned to another colleague.']);
            exit;
        }

        $readiness = computeDealReadiness($pdo, $dealId);
        if (!$forceClose && (!empty($readiness['missing_docs']) || !empty($readiness['unverified_docs']))) {
            echo json_encode([
                'success'            => false,
                'needs_confirmation' => true,
                'missing_docs'       => $readiness['missing_docs'],
                'unverified_docs'    => $readiness['unverified_docs'],
                'error'              => 'Some mandatory documents are missing or still unverified.',
            ]);
            exit;
        }

        try {
            $pdo->beginTransaction();

            // Mark Stage 7 verified & seal all unlocked non-superseded docs
            $pdo->prepare("UPDATE `deal_milestones` SET status = 'Verified Complete', completed_at = COALESCE(completed_at, NOW()), verified_by = ?
                WHERE deal_id = ? AND stage_key = 'discharge_closeout'")
                ->execute([$user['full_name'] ?? 'Owner', $dealId]);

            $pdo->prepare("UPDATE `deal_documents` SET doc_status = 'Verified Final', is_locked = 1, verified_by = COALESCE(verified_by, ?), verified_at = COALESCE(verified_at, NOW())
                WHERE deal_id = ? AND doc_status != 'Superseded'")
                ->execute([$user['full_name'] ?? 'Owner', $dealId]);

            // Auto-deduct linked stockpile tonnage cleanly ONCE
            $deductedSummary = '';
            $stockFlag = (int)$deal['stockpile_deducted'];
            if ($deductStock && $stockFlag === 0) {
                $allocs = $pdo->prepare("SELECT stockpile_id, allocated_mt FROM `deal_stockpile_allocations` WHERE deal_id = ?");
                $allocs->execute([$dealId]);
                $dedStmt = $pdo->prepare("UPDATE `stockpiles` SET tonnage_available = GREATEST(0, tonnage_available - ?) WHERE id = ?");
                $totDed = 0;
                foreach ($allocs->fetchAll(PDO::FETCH_ASSOC) as $al) {
                    $mt = (float)$al['allocated_mt'];
                    if ($mt > 0) {
                        $dedStmt->execute([$mt, (int)$al['stockpile_id']]);
                        $totDed += $mt;
                    }
                }
                if ($totDed > 0) {
                    $stockFlag = 1;
                    $deductedSummary = " | Deducted {$totDed} MT from yard stockpile";
                }
            }

            if ($retireToken) {
                $pdo->prepare("UPDATE `deal_access_tokens` SET is_active = 0 WHERE deal_id = ?")->execute([$dealId]);
                $pdo->prepare("UPDATE `deal_field_links` SET is_active = 0 WHERE deal_id = ?")->execute([$dealId]);
            }

            $pdo->prepare("UPDATE `deals` SET deal_state = 'Closed Settled', current_stage_key = 'discharge_closeout',
                    current_stage_label = 'Discharged & Completed', sealed_at = NOW(), sealed_by = ?, stockpile_deducted = ?
                WHERE id = ?")
                ->execute([$user['full_name'] ?? 'Owner', $stockFlag, $dealId]);

            if (!empty($deal['legacy_shipment_id'])) {
                $pdo->prepare("UPDATE `shipments` SET status_stage = 'Discharged & Completed' WHERE id = ?")
                    ->execute([$deal['legacy_shipment_id']]);
            }

            if (!empty($deal['inquiry_id'])) {
                $pdo->prepare("UPDATE `inquiries` SET status = 'Closed' WHERE id = ?")->execute([(int)$deal['inquiry_id']]);
            }

            $pdo->commit();
        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            echo json_encode(['success' => false, 'error' => 'Close deal error: ' . $e->getMessage()]);
            exit;
        }

        logDealAudit($pdo, $dealId, 'deal_closed', "Finalized & sealed deal {$deal['deal_code']}{$deductedSummary}. Note: {$closeNote}");
        logSystemAudit($pdo, 'deal', "Closed deal {$deal['deal_code']}" . ($forceClose ? ' [FORCE CLOSED]' : '') . $deductedSummary, $dealId);
        logAdminNotification($pdo, 'success', 'deals', 'Deal Finalized & Sealed', "{$deal['deal_code']} closed{$deductedSummary}", $deal['inquiry_id'] ?: null, "deals.php?id={$dealId}");

        echo json_encode(['success' => true, 'summary' => "Deal {$deal['deal_code']} sealed as Closed Settled{$deductedSummary}."]);
        exit;
    }

    if ($action === 'reopen_deal') {
        if (!$canReopen) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Only Owner can reopen sealed deals.']);
            exit;
        }
        $dealId = (int)($_POST['deal_id'] ?? 0);
        $reason = trim((string)($_POST['reason'] ?? 'Owner correction'));
        $pdo->prepare("UPDATE `deals` SET deal_state = 'Active', sealed_at = NULL, sealed_by = NULL WHERE id = ?")->execute([$dealId]);
        logDealAudit($pdo, $dealId, 'deal_reopened', "Reopened sealed deal: {$reason}");
        logSystemAudit($pdo, 'deal', "Reopened sealed deal #{$dealId}: {$reason}", $dealId);
        echo json_encode(['success' => true]);
        exit;
    }

    if ($action === 'assign_deal') {
        if (!$canAssign) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Only Owner or Manager can assign export deals.']);
            exit;
        }
        $dealId = (int)($_POST['deal_id'] ?? 0);
        $assigneeId = !empty($_POST['assigned_user_id']) ? (int)$_POST['assigned_user_id'] : null;
        $pdo->prepare("UPDATE `deals` SET assigned_user_id = ? WHERE id = ?")->execute([$assigneeId, $dealId]);
        $aName = 'Unassigned';
        if ($assigneeId) {
            $uSt = $pdo->prepare("SELECT full_name FROM `admin_users` WHERE id = ? LIMIT 1");
            $uSt->execute([$assigneeId]);
            $aName = $uSt->fetchColumn() ?: ('Staff #' . $assigneeId);
        }
        logDealAudit($pdo, $dealId, 'deal_assigned', "Assigned deal to {$aName}");
        logSystemAudit($pdo, 'deal', "Assigned deal #{$dealId} to {$aName}", $dealId);
        echo json_encode(['success' => true, 'assigned_name' => $aName]);
        exit;
    }

    echo json_encode(['success' => false, 'error' => 'Unsupported POST action.']);
    exit;
}
