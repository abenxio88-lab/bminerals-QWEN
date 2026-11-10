<?php
/**
 * Balochistan Minerals - Customer Review Submission Endpoint
 * Moderated into MySQL for owner approval
 */

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

require_once __DIR__ . '/../admin/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method Not Allowed']);
    exit;
}

if (!empty($_POST['botcheck'])) {
    echo json_encode(['success' => true, 'message' => 'Review received.']);
    exit;
}

$name     = cleanInput($_POST['name'] ?? '');
$company  = cleanInput($_POST['company'] ?? '');
$rating   = max(1, min(5, (int)($_POST['review_rating'] ?? 5)));
$product  = cleanInput($_POST['review_product'] ?? 'General Products');
$text     = cleanInput($_POST['message'] ?? '');
$consent  = isset($_POST['review_publish_consent']) ? 1 : 0;
$country  = cleanInput($_POST['country'] ?? 'International');

if (empty($name) || empty($text)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Please provide your name and review comments.']);
    exit;
}

$pdo = getDB();
$reviewId = 0;

if ($pdo !== null) {
    try {
        $stmt = $pdo->prepare('INSERT INTO reviews (reviewer_name, company_name, country, rating, product_reviewed, review_text, publish_consent, status) VALUES (?, ?, ?, ?, ?, ?, ?, "Pending")');
        $stmt->execute([$name, $company, $country, $rating, $product, $text, $consent]);
        $reviewId = (int)$pdo->lastInsertId();
        logAdminNotification(
            $pdo,
            'info',
            'review',
            "New Client Review ({$rating}/5)",
            "{$name}" . ($company ? " ({$company})" : '') . " reviewed {$product}",
            null,
            'reviews.php',
            0
        );
    } catch (Exception $e) {
        error_log("DB Review Error: " . $e->getMessage());
    }
}

// Alert owner
$to = COMPANY_EMAIL;
$subject = "⭐ New Customer Review Awaiting Approval - Balochistan Minerals";
$body = "A new client review was submitted on BalochistanMinerals.com:\n\n"
      . "Reviewer: $name ($company)\n"
      . "Rating: $rating / 5 Stars\n"
      . "Product: $product\n"
      . "Feedback:\n$text\n\n"
      . "Moderate in Portal: https://balochistanminerals.com/admin/reviews.php";

$headers = "From: Balochistan Minerals Web <" . COMPANY_EMAIL . ">\r\n"
         . "X-Mailer: PHP/" . phpversion();

@mail($to, $subject, $body, $headers);

echo json_encode([
    'success'   => true,
    'message'   => 'Thank you! Your feedback has been received and will appear after moderation.',
    'review_id' => $reviewId
]);
