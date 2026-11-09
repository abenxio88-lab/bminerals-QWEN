<?php
/**
 * Balochistan Minerals - Blog Discussion & Article Q&A Endpoint
 * Connects website readers directly to the Trade Desk and dynamically displays approved technical discussions.
 */

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/../admin/db.php';

$pdo = getDB();
if (!$pdo) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database connection unavailable.']);
    exit;
}

// ------------------------------------------
// 1. GET: Fetch Approved Q&A for an Article
// ------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $slug = cleanInput($_GET['slug'] ?? '');
    if (empty($slug)) {
        echo json_encode(['success' => false, 'error' => 'Article slug required.']);
        exit;
    }

    $stmt = $pdo->prepare("SELECT id, author_name, author_company, author_country, comment_text, reply_text, replied_by, replied_at, created_at 
        FROM blog_discussions 
        WHERE article_slug = ? AND status = 'Approved' 
        ORDER BY created_at ASC");
    $stmt->execute([$slug]);
    $discussions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'slug' => $slug,
        'count' => count($discussions),
        'discussions' => $discussions
    ]);
    exit;
}

// ------------------------------------------
// 2. POST: Submit a Question / Comment under an Article
// ------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Honeypot anti-spam
    if (!empty($_POST['botcheck'])) {
        echo json_encode(['success' => true, 'message' => 'Question received.']);
        exit;
    }

    $slug    = cleanInput($_POST['article_slug'] ?? '');
    $name    = cleanInput($_POST['author_name'] ?? '');
    $company = cleanInput($_POST['author_company'] ?? '');
    $country = cleanInput($_POST['author_country'] ?? 'International');
    $email   = filter_var(trim($_POST['author_email'] ?? ''), FILTER_SANITIZE_EMAIL);
    $text    = cleanInput($_POST['comment_text'] ?? '');

    if (empty($slug) || empty($name) || empty($email) || empty($text)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Please provide your name, valid email, and your question.']);
        exit;
    }

    try {
        // A. Insert into blog_discussions
        $stmt = $pdo->prepare("INSERT INTO blog_discussions 
            (article_slug, author_name, author_company, author_country, author_email, comment_text, status) 
            VALUES (?, ?, ?, ?, ?, ?, 'Pending')");
        $stmt->execute([$slug, $name, $company, $country, $email, $text]);
        $discussionId = (int)$pdo->lastInsertId();

        // B. Connect to Inquiries table so it instantly lands in the Trade Desk Admin Portal!
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        $inqStmt = $pdo->prepare("INSERT INTO inquiries 
            (buyer_name, company_name, country, email, inquiry_type, source, mineral_type, message, status, ip_address) 
            VALUES (?, ?, ?, ?, 'Blog Q&A / Inquiry', ?, 'General Mineral', ?, 'New', ?)");
        $inqSource = 'Blog: ' . $slug;
        $inqStmt->execute([$name, $company, $country, $email, $inqSource, $text, $ip]);
        $leadId = (int)$pdo->lastInsertId();

        // C. Send alert email to export desk
        $to = defined('COMPANY_EMAIL') ? COMPANY_EMAIL : 'info@balochistanminerals.com';
        $subject = "💬 New Blog Question on [" . $slug . "] from " . $name;
        $emailBody = "A reader posted a question under blog article: $slug\n\n"
                   . "Name: $name\n"
                   . "Company: " . ($company ?: 'N/A') . "\n"
                   . "Email: $email\n"
                   . "Country: $country\n\n"
                   . "Question:\n$text\n\n"
                   . "---\n"
                   . "Reply in Trade Desk Portal: https://balochistanminerals.com/admin/dashboard.php?q=" . urlencode($email);

        $headers = "From: Balochistan Minerals Web <" . $to . ">\r\n"
                 . "Reply-To: $email\r\n"
                 . "X-Mailer: PHP/" . phpversion();

        @mail($to, $subject, $emailBody, $headers);

        echo json_encode([
            'success' => true,
            'message' => 'Thank you! Your question has been transmitted to our technical desk and will appear after moderation.',
            'discussion_id' => $discussionId,
            'lead_id' => $leadId
        ]);
        exit;
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
        exit;
    }
}
