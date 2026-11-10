<?php
/**
 * Balochistan Minerals - Persistent Admin Notification Center API
 * Stores and manages real-time system, mail sync, and inbound RFQ alerts in MySQL.
 */

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/db.php';

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Authentication required']);
    exit;
}

$pdo = getDB();
if (!$pdo) {
    echo json_encode(['success' => false, 'error' => 'Database connection unavailable']);
    exit;
}

ensureNotificationsTable($pdo);

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
    try {
        $unread = (int)$pdo->query("SELECT COUNT(*) FROM admin_notifications WHERE is_read = 0")->fetchColumn();
        $rows = $pdo->query("SELECT id, type, category, title, message, inquiry_id, link_url, is_read, created_at
            FROM admin_notifications
            ORDER BY is_read ASC, created_at DESC, id DESC
            LIMIT 40")->fetchAll(PDO::FETCH_ASSOC);

        foreach ($rows as &$r) {
            $r['id'] = (int)$r['id'];
            $r['inquiry_id'] = $r['inquiry_id'] ? (int)$r['inquiry_id'] : null;
            $r['is_read'] = (int)$r['is_read'] === 1;
            $r['created_human'] = date('M d, h:i A', strtotime($r['created_at']));
        }
        unset($r);

        echo json_encode([
            'success' => true,
            'unread_count' => $unread,
            'notifications' => $rows,
            'csrf_token' => getCsrfToken(),
        ]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = $_POST['csrf_token'] ?? '';
    if (!verifyCsrfToken($csrf)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Invalid security token']);
        exit;
    }

    $action = cleanInput($_POST['action'] ?? '');
    try {
        if ($action === 'mark_read') {
            $id = (int)($_POST['id'] ?? 0);
            if ($id > 0) {
                $stmt = $pdo->prepare("UPDATE admin_notifications SET is_read = 1 WHERE id = ?");
                $stmt->execute([$id]);
            }
        } elseif ($action === 'mark_all_read') {
            $pdo->exec("UPDATE admin_notifications SET is_read = 1 WHERE is_read = 0");
        } elseif ($action === 'clear_all') {
            $pdo->exec("DELETE FROM admin_notifications");
        } elseif ($action === 'log') {
            $type = cleanInput($_POST['type'] ?? 'info');
            $category = cleanInput($_POST['category'] ?? 'system');
            $title = cleanInput($_POST['title'] ?? 'System Alert');
            $message = cleanInput($_POST['message'] ?? '');
            $inquiryId = !empty($_POST['inquiry_id']) ? (int)$_POST['inquiry_id'] : null;
            $linkUrl = cleanInput($_POST['link_url'] ?? '');
            if ($message !== '') {
                logAdminNotification($pdo, $type, $category, $title, $message, $inquiryId, $linkUrl ?: null, 2);
            }
        } else {
            echo json_encode(['success' => false, 'error' => 'Unknown action']);
            exit;
        }

        $unread = (int)$pdo->query("SELECT COUNT(*) FROM admin_notifications WHERE is_read = 0")->fetchColumn();
        echo json_encode(['success' => true, 'unread_count' => $unread]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

