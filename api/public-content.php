<?php
/**
 * Balochistan Minerals - Public Dynamic Content API
 * Serves approved reviews, active mineral spot prices, dynamic FAQs, and shipment tracking.
 */

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/../admin/db.php';

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
        $code = cleanInput($_GET['code'] ?? '');
        if (empty($code)) {
            echo json_encode(['success' => false, 'error' => 'Please provide a valid tracking number (e.g. BM-EXP-2026-081).']);
            exit;
        }

        $stmt = $pdo->prepare("SELECT tracking_code, company_name, commodity, tonnage, vessel_name, bill_of_lading, port_of_loading, port_of_discharge, status_stage, notes, updated_at FROM shipments WHERE tracking_code = ? LIMIT 1");
        $stmt->execute([$code]);
        $shipment = $stmt->fetch();

        if ($shipment) {
            $response['shipment'] = $shipment;
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
