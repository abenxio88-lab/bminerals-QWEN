<?php
/**
 * Balochistan Minerals - Public Dynamic Content API
 * Serves approved reviews, active mineral spot prices, dynamic FAQs, and shipment tracking.
 */

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/../admin/db.php';
require_once __DIR__ . '/../admin/deal-helpers.php';

$pdo = getDB();
$type = cleanInput($_GET['type'] ?? 'all');

if ($pdo === null) {
    echo json_encode(['success' => false, 'error' => 'Database temporarily unavailable.']);
    exit;
}

$response = ['success' => true];

try {
    if ($type === 'reviews' || $type === 'all') {
        $stmt = $pdo->query("SELECT reviewer_name, company_name, country, rating, product_reviewed, review_text, created_at FROM reviews WHERE status = 'Approved' ORDER BY created_at DESC LIMIT 10");
        $response['reviews'] = $stmt->fetchAll();
    }

    if ($type === 'prices' || $type === 'all') {
        $stmt = $pdo->query("SELECT mineral_name, grade_name, purity_spec, price_low_usd, price_high_usd, unit, delivery_terms, notes, updated_at FROM mineral_prices WHERE is_active = 1 ORDER BY sort_order ASC");
        $response['prices'] = $stmt->fetchAll();
    }

    if ($type === 'faqs' || $type === 'all') {
        $stmt = $pdo->query("SELECT category, question, answer FROM faqs WHERE is_active = 1 ORDER BY sort_order ASC");
        $response['faqs'] = $stmt->fetchAll();
    }

    if ($type === 'stockpiles' || $type === 'all') {
        $stmt = $pdo->query("SELECT mineral_name, location, tonnage_available, grade_spec, status FROM stockpiles WHERE status = 'Ready for Loading' ORDER BY tonnage_available DESC");
        $response['stockpiles'] = $stmt->fetchAll();
    }

    if ($type === 'track') {
        ensureDealVaultSchema($pdo);
        $code  = cleanInput($_GET['code'] ?? '');
        $token = trim((string)($_GET['k'] ?? ''));

        if (empty($code)) {
            echo json_encode(['success' => false, 'error' => 'Please provide a valid tracking number (e.g. BM-EXP-2026-081).']);
            exit;
        }

        $dStmt = $pdo->prepare("SELECT id, deal_code, tracking_code, company_name, commodity, contracted_mt, incoterm, vessel_name, bill_of_lading, port_of_loading, port_of_discharge, current_stage_label, buyer_public_notes, is_public_demo, updated_at FROM `deals` WHERE tracking_code = ? LIMIT 1");
        $dStmt->execute([$code]);
        $deal = $dStmt->fetch(PDO::FETCH_ASSOC);

        if ($deal) {
            if ((int)$deal['is_public_demo'] !== 1) {
                if ($token === '') {
                    http_response_code(403);
                    echo json_encode(['success' => false, 'requires_token' => true, 'error' => 'Please enter your private buyer access key to view this consignment manifest.']);
                    exit;
                }
                $tokStmt = $pdo->prepare("SELECT id FROM `deal_access_tokens` WHERE deal_id = ? AND token = ? AND is_active = 1 LIMIT 1");
                $tokStmt->execute([$deal['id'], $token]);
                $tokId = $tokStmt->fetchColumn();
                if (!$tokId) {
                    http_response_code(403);
                    echo json_encode(['success' => false, 'requires_token' => true, 'error' => 'Invalid or expired buyer access token for this tracking reference.']);
                    exit;
                }
                $pdo->prepare("UPDATE `deal_access_tokens` SET last_viewed_at = NOW() WHERE id = ?")->execute([$tokId]);
            }

            $msStmt = $pdo->prepare("SELECT id, stage_order, stage_title, status, buyer_summary, completed_at FROM `deal_milestones` WHERE deal_id = ? ORDER BY stage_order ASC");
            $msStmt->execute([$deal['id']]);
            $milestones = $msStmt->fetchAll(PDO::FETCH_ASSOC);

            $metStmt = $pdo->prepare("SELECT dm.milestone_id, dm.metric_key, dm.metric_label, dm.metric_value, dm.unit, ms.stage_order
                FROM `deal_measurements` dm
                JOIN `deal_milestones` ms ON ms.id = dm.milestone_id
                WHERE dm.deal_id = ? AND dm.is_buyer_visible = 1 AND ms.status != 'Pending' AND TRIM(dm.metric_value) != ''
                ORDER BY ms.stage_order ASC, dm.id ASC");
            $metStmt->execute([$deal['id']]);
            $measurements = $metStmt->fetchAll(PDO::FETCH_ASSOC);

            $docStmt = $pdo->prepare("SELECT dd.id, dd.milestone_id, dd.doc_category, dd.title, dd.doc_status, dd.doc_version, fb.mime_type, fb.thumb_key, fb.byte_size
                FROM `deal_documents` dd
                JOIN `file_blobs` fb ON fb.id = dd.blob_id
                WHERE dd.deal_id = ? AND dd.visibility IN ('buyer_portal','public_sample') AND dd.doc_status != 'Superseded'
                ORDER BY dd.milestone_id ASC, dd.id DESC");
            $docStmt->execute([$deal['id']]);
            $docs = $docStmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($docs as &$doc) {
                $qTok = $token !== '' ? '&k=' . urlencode($token) : '';
                $doc['file_url']  = 'admin/api-vault-file.php?code=' . urlencode($deal['tracking_code']) . $qTok . '&doc_id=' . (int)$doc['id'];
                $doc['thumb_url'] = !empty($doc['thumb_key']) ? ($doc['file_url'] . '&thumb=1') : null;
                unset($doc['thumb_key']);
            }
            unset($doc);

            $sgsStatus = 'Pending';
            foreach ($milestones as $msRow) {
                if ((int)$msRow['stage_order'] === 4) {
                    $sgsStatus = $msRow['status'];
                    break;
                }
            }

            $response['shipment'] = [
                'tracking_code'     => $deal['tracking_code'],
                'deal_code'         => $deal['deal_code'],
                'company_name'      => (int)$deal['is_public_demo'] === 1 ? 'Sample Export Consignment (Demo)' : $deal['company_name'],
                'commodity'         => $deal['commodity'],
                'tonnage'           => number_format((float)$deal['contracted_mt'], 0) . ' MT',
                'incoterm'          => $deal['incoterm'],
                'vessel_name'       => $deal['vessel_name'],
                'bill_of_lading'    => $deal['bill_of_lading'],
                'port_of_loading'   => $deal['port_of_loading'],
                'port_of_discharge' => $deal['port_of_discharge'],
                'status_stage'      => $deal['current_stage_label'],
                'inspection_status' => $sgsStatus,
                'notes'             => $deal['buyer_public_notes'],
                'is_public_demo'    => (int)$deal['is_public_demo'],
                'updated_at'        => $deal['updated_at'],
            ];
            $response['milestones']   = $milestones;
            $response['measurements'] = $measurements;
            $response['documents']    = $docs;
        } else {
            $response['success'] = false;
            $response['error'] = 'No export consignment found for tracking code: ' . htmlspecialchars($code);
        }
    }

    echo json_encode($response);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Query execution error.']);
}
