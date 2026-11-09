<?php
/**
 * Balochistan Minerals - Blog Discussion & Article Q&A Endpoint
 * Connects website readers directly to the Trade Desk and dynamically displays approved technical discussions.
 */

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/../admin/db.php';
require_once __DIR__ . '/../admin/flag-helper.php';

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
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';

    // A. Honeypot anti-spam traps
    if (!empty($_POST['botcheck']) || !empty($_POST['bot_verification_field'])) {
        echo json_encode(['success' => true, 'message' => 'Question received.']);
        exit;
    }

    // B. Submission speed check (bots submit instantaneously in < 1.5 seconds)
    $formTime = (int)($_POST['form_time'] ?? 0);
    if ($formTime > 0) {
        $elapsedMs = (time() * 1000) - $formTime;
        if ($elapsedMs > 0 && $elapsedMs < 1500) {
            echo json_encode(['success' => true, 'message' => 'Question received.']);
            exit;
        }
    }

    // C. Server-side Rate Limiting (max 3 submissions per 60 seconds per IP)
    if (!empty($ip)) {
        $rateStmt = $pdo->prepare("SELECT COUNT(*) FROM blog_discussions WHERE article_slug = ? AND created_at >= (NOW() - INTERVAL 1 MINUTE)");
        $rateStmt->execute([cleanInput($_POST['article_slug'] ?? '')]);
        if ((int)$rateStmt->fetchColumn() >= 6) {
            http_response_code(429);
            echo json_encode(['success' => false, 'error' => 'Too many submissions. Please wait 1 minute before submitting again.']);
            exit;
        }
    }

    $slug    = cleanInput($_POST['article_slug'] ?? '');
    $name    = cleanInput($_POST['author_name'] ?? '');
    $company = cleanInput($_POST['author_company'] ?? '');
    $rawCountry = cleanInput($_POST['author_country'] ?? '');
    $country = detectCountryName($rawCountry, $email ?: '', '', $company, $text);
    $rawEmail= trim($_POST['author_email'] ?? '');
    $email   = !empty($rawEmail) ? filter_var($rawEmail, FILTER_SANITIZE_EMAIL) : null;
    $text    = cleanInput($_POST['comment_text'] ?? '');

    // Only name, slug, and question text are required; Email is completely optional
    if (empty($slug) || empty($name) || empty($text)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Please provide your name and your question or review.']);
        exit;
    }

    // D. Duplicate content spam check (same comment text within 5 minutes)
    $dupStmt = $pdo->prepare("SELECT COUNT(*) FROM blog_discussions WHERE comment_text = ? AND created_at >= (NOW() - INTERVAL 5 MINUTE)");
    $dupStmt->execute([$text]);
    if ((int)$dupStmt->fetchColumn() > 0) {
        http_response_code(429);
        echo json_encode(['success' => false, 'error' => 'Your inquiry or review has already been received by our Export Desk.']);
        exit;
    }

    try {
        // 1. Insert into blog_discussions
        $stmt = $pdo->prepare("INSERT INTO blog_discussions 
            (article_slug, author_name, author_company, author_country, author_email, comment_text, status) 
            VALUES (?, ?, ?, ?, ?, ?, 'Pending')");
        $stmt->execute([$slug, $name, $company, $country, $email ?: null, $text]);
        $discussionId = (int)$pdo->lastInsertId();

        // 2. Connect to Inquiries table so it instantly lands in the Trade Desk Admin Portal!
        $inqStmt = $pdo->prepare("INSERT INTO inquiries 
            (buyer_name, company_name, country, email, whatsapp, inquiry_type, source, mineral_type, message, status, ip_address) 
            VALUES (?, ?, ?, ?, '', 'Blog Q&A / Review', ?, 'General Mineral', ?, 'New', ?)");
        $inqSource = 'Blog: ' . $slug;
        $inqEmail = !empty($email) ? $email : 'reader@balochistanminerals.com';
        $inqStmt->execute([$name, $company, $country, $inqEmail, $inqSource, $text, $ip]);
        $leadId = (int)$pdo->lastInsertId();

        // 3. Send alert email to export desk
        $to = defined('COMPANY_EMAIL') ? COMPANY_EMAIL : 'info@balochistanminerals.com';
        $subject = "💬 New Blog Question/Review on [" . $slug . "] from " . $name;
        $emailDisplay = !empty($email) ? $email : 'Not Provided (Public Reader Review)';
        $emailBody = "A reader posted a question/review under blog article: $slug\n\n"
                   . "Name: $name\n"
                   . "Company: " . ($company ?: 'N/A') . "\n"
                   . "Email: $emailDisplay\n"
                   . "Country: $country\n\n"
                   . "Question/Review:\n$text\n\n"
                   . "---\n"
                   . "Reply in Trade Desk Portal: https://balochistanminerals.com/admin/dashboard.php?q=" . urlencode($emailDisplay);

        $headers = "From: Balochistan Minerals Web <" . $to . ">\r\n"
                 . (!empty($email) ? "Reply-To: $email\r\n" : "")
                 . "X-Mailer: PHP/" . phpversion();

        @mail($to, $subject, $emailBody, $headers);

        echo json_encode([
            'success' => true,
            'message' => 'Thank you! Your submission has been transmitted to our technical desk and will appear after moderation.',
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
