<?php
/**
 * Balochistan Minerals - Blog Discussions & Technical Q&A Moderation Desk
 * Dedicated Content Moderation Terminal (Completely Isolated from Commercial Deal Pipeline)
 */

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/flag-helper.php';
requireAuth();

$user = getLoggedInUser();
$pdo  = getDB();

$flashMessage = $_SESSION['bm_flash_msg'] ?? '';
$flashType    = $_SESSION['bm_flash_type'] ?? 'success';
unset($_SESSION['bm_flash_msg'], $_SESSION['bm_flash_type']);

// Handle Moderation Actions
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && $pdo !== null) {
    $action = $_POST['action'] ?? '';
    $csrf   = $_POST['csrf_token'] ?? '';
    $discId = (int)($_POST['discussion_id'] ?? 0);

    if (verifyCsrfToken($csrf)) {
        if ($action === 'set_status') {
            $newStatus = cleanInput($_POST['status'] ?? 'Pending');
            $stmt = $pdo->prepare('UPDATE blog_discussions SET status = ? WHERE id = ?');
            $stmt->execute([$newStatus, $discId]);
            $_SESSION['bm_flash_msg'] = "Discussion #$discId status updated to $newStatus.";
            $_SESSION['bm_flash_type'] = 'success';
        } elseif ($action === 'submit_reply') {
            $replyText = cleanInput($_POST['reply_text'] ?? '');
            $repliedBy = cleanInput($_POST['replied_by'] ?? 'Balochistan Minerals Technical Desk');
            $approveAfterReply = !empty($_POST['auto_approve']);

            $newStatus = $approveAfterReply ? 'Approved' : 'Pending';
            $stmt = $pdo->prepare('UPDATE blog_discussions SET reply_text = ?, replied_by = ?, replied_at = NOW(), status = ? WHERE id = ?');
            $stmt->execute([$replyText, $repliedBy, $newStatus, $discId]);
            $_SESSION['bm_flash_msg'] = "Official response published to Discussion #$discId.";
            $_SESSION['bm_flash_type'] = 'success';
        } elseif ($action === 'delete_discussion') {
            $stmt = $pdo->prepare('DELETE FROM blog_discussions WHERE id = ?');
            $stmt->execute([$discId]);
            $_SESSION['bm_flash_msg'] = "Discussion #$discId permanently deleted.";
            $_SESSION['bm_flash_type'] = 'warning';
        }
    } else {
        $_SESSION['bm_flash_msg'] = 'Session expired. Please try again.';
        $_SESSION['bm_flash_type'] = 'danger';
    }

    // Post/Redirect/Get: Redirect back to avoid browser form resubmission prompts on refresh
    header('Location: ' . $_SERVER['REQUEST_URI']);
    exit;
}

// Fetch Metrics & Discussions
$discussions = [];
$filterStatus = cleanInput($_GET['status'] ?? '');
$searchQuery  = cleanInput($_GET['q'] ?? '');

$totalDiscussions   = 0;
$pendingDiscussions = 0;
$approvedDiscussions= 0;
$newLeads           = 0;
$pendingReviews     = 0;

if ($pdo !== null) {
    $totalDiscussions    = (int)$pdo->query("SELECT COUNT(*) FROM blog_discussions")->fetchColumn();
    $pendingDiscussions  = (int)$pdo->query("SELECT COUNT(*) FROM blog_discussions WHERE status = 'Pending'")->fetchColumn();
    $approvedDiscussions = (int)$pdo->query("SELECT COUNT(*) FROM blog_discussions WHERE status = 'Approved'")->fetchColumn();
    $newLeads            = (int)$pdo->query("SELECT COUNT(*) FROM inquiries WHERE status = 'New'")->fetchColumn();
    $pendingReviews      = (int)$pdo->query("SELECT COUNT(*) FROM reviews WHERE status = 'Pending'")->fetchColumn();
    $totalProspects      = (int)$pdo->query("SELECT COUNT(*) FROM buyer_prospects")->fetchColumn();

    $sql = "SELECT * FROM blog_discussions WHERE 1=1";
    $params = [];

    if (!empty($filterStatus)) {
        $sql .= " AND status = ?";
        $params[] = $filterStatus;
    }
    if (!empty($searchQuery)) {
        $sql .= " AND (author_name LIKE ? OR author_company LIKE ? OR author_email LIKE ? OR comment_text LIKE ? OR article_slug LIKE ?)";
        $searchTerm = "%" . $searchQuery . "%";
        $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm]);
    }

    $sql .= " ORDER BY created_at DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $discussions = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Blog Discussions Moderation | Balochistan Minerals</title>
  <link rel="icon" type="image/png" sizes="32x32" href="../images/favicon/favicon-32x32.png">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@500;600;700;800&family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <link rel="stylesheet" href="admin-layout.css?v=<?= filemtime(__DIR__ . '/admin-layout.css') ?>">
  <style>
    :root {
      --brand-navy: #0F172A;
      --brand-border: #E2E8F0;
      --brand-canvas: #F8FAFC;
      --brand-surface: #FFFFFF;
    }
    .article-slug-badge {
      font-family: 'SFMono-Regular', Consolas, monospace;
      font-size: 0.72rem;
      background: #F1F5F9;
      color: #334155;
      padding: 3px 8px;
      border-radius: 4px;
      text-decoration: none;
      display: inline-flex;
      align-items: center;
      gap: 4px;
      transition: all 0.15s ease;
    }
    .article-slug-badge:hover {
      background: #E2E8F0;
      color: #0F172A;
    }
    .comment-bubble {
      background: #FFFFFF;
      border: 1px solid #E2E8F0;
      border-radius: 8px;
      padding: 10px 14px;
      font-size: 0.82rem;
      line-height: 1.45;
      color: #1E293B;
      position: relative;
    }
    .reply-bubble {
      background: #F0FDF4;
      border: 1px solid #BBF7D0;
      border-radius: 8px;
      padding: 9px 13px;
      font-size: 0.80rem;
      line-height: 1.4;
      color: #166534;
      margin-top: 8px;
    }
  </style>
</head>
<body>

  <div class="app-wrapper">

    <!-- LEFT SIDEBAR -->
    <aside class="app-sidebar" id="appSidebar">
      <div class="sidebar-header">
        <a href="../" target="_blank" class="sidebar-logo-wrap" title="Balochistan Minerals">
          <img src="../images/new-logo.png" alt="Balochistan Minerals" class="sidebar-logo" onerror="this.style.display='none'">
          <span class="sidebar-badge">TRADE DESK PORTAL</span>
        </a>
        <button type="button" id="sidebarCloseBtn" class="btn-sidebar-panel-toggle" title="Collapse Sidebar (Ctrl + B)">
          <i class="bi bi-layout-sidebar-inset"></i>
        </button>
      </div>

      <nav class="sidebar-nav">
        <div class="sidebar-section-title">Lead & Deal Engine</div>
        <a href="dashboard.php" class="sidebar-link">
          <div><i class="bi bi-inbox-fill"></i> Inbound RFQs</div>
          <?php if ($newLeads > 0): ?>
            <span class="badge bg-dark font-monospace" style="font-size: 0.7rem;"><?= $newLeads ?></span>
          <?php endif; ?>
        </a>
        <a href="email-desk.php" class="sidebar-link">
          <div><i class="bi bi-envelope"></i> Email Reply Desk</div>
          <span class="badge-sidebar-hostinger">HOSTINGER</span>
        </a>
        <a href="prospects.php" class="sidebar-link">
          <div><i class="bi bi-bullseye"></i> Buyer Directory</div>
          <span class="badge bg-light text-secondary border font-monospace" style="font-size: 0.7rem;"><?= $totalProspects ?></span>
        </a>
        <a href="radar.php" class="sidebar-link">
          <div><i class="bi bi-broadcast-pin"></i> Live Visitor Radar</div>
          <span class="badge bg-success font-monospace" style="font-size: 0.65rem;">LIVE</span>
        </a>

        <div class="sidebar-section-title">Trade Analytics & Intelligence</div>
        <a href="analytics.php" class="sidebar-link">
          <div><i class="bi bi-graph-up"></i> Intelligence Hub</div>
          <span class="badge-sidebar-intel">PROJ</span>
        </a>

        <div class="sidebar-section-title">Logistics & Trade Operations</div>
        <a href="stockpiles.php" class="sidebar-link">
          <div><i class="bi bi-boxes"></i> Stockpiles & Shipments</div>
        </a>
        <a href="prices.php" class="sidebar-link">
          <div><i class="bi bi-cash-stack"></i> Mineral Spot Prices</div>
        </a>

        <div class="sidebar-section-title">Buyer Engagement & Content</div>
        <a href="reviews.php" class="sidebar-link">
          <div><i class="bi bi-star-fill"></i> Customer Reviews</div>
          <?php if ($pendingReviews > 0): ?>
            <span class="badge bg-secondary font-monospace" style="font-size: 0.7rem;"><?= $pendingReviews ?></span>
          <?php endif; ?>
        </a>
        <a href="blog-discussions.php" class="sidebar-link active">
          <div><i class="bi bi-chat-square-text-fill"></i> Blog Discussions</div>
          <?php if ($pendingDiscussions > 0): ?>
            <span class="badge bg-warning text-dark font-monospace" style="font-size: 0.7rem;"><?= $pendingDiscussions ?></span>
          <?php endif; ?>
        </a>
        <a href="faqs.php" class="sidebar-link">
          <div><i class="bi bi-question-circle-fill"></i> Dynamic FAQs</div>
        </a>

        <div class="sidebar-section-title">System & Links</div>
        <a href="../" target="_blank" class="sidebar-link">
          <div><i class="bi bi-globe2"></i> Live Website</div>
          <i class="bi bi-box-arrow-up-right small text-muted"></i>
        </a>
      </nav>

      <div class="sidebar-footer">
        <div class="d-flex align-items-center justify-content-between">
          <div>
            <div class="fw-bold small" style="color: var(--brand-navy);"><?= htmlspecialchars($user['full_name']) ?></div>
            <div class="text-muted" style="font-size: 0.7rem;">Content Desk</div>
          </div>
          <a href="logout.php" class="btn btn-sm btn-outline-secondary p-1 px-2" title="Sign Out">
            <i class="bi bi-power"></i>
          </a>
        </div>
      </div>
    </aside>

    <div class="sidebar-backdrop" id="sidebarBackdrop"></div>

    <!-- MAIN AREA -->
    <div class="app-main">
      <header class="top-bar">
        <div class="topbar-left">
          <button type="button" id="sidebarToggle" class="btn-sidebar-toggle" title="Toggle Sidebar (Ctrl + B)">
            <i class="bi bi-layout-sidebar-inset"></i>
          </button>
          <div class="topbar-title-wrap">
            <h1 class="topbar-title">Blog Discussions & Reader Q&A</h1>
            <div class="topbar-subtitle">Moderate public comments & technical inquiries across all articles</div>
          </div>
        </div>

        <div class="topbar-right">
          <div class="pill-status" title="Isolated Content Moderation Desk">
            <span class="status-dot dot-green"></span>
            <span>Content Desk: Isolated from Sales Inbox</span>
          </div>
        </div>
      </header>

      <main class="content-canvas">

        <!-- Metric Strip -->
        <div class="row g-2.5 g-md-3 mb-3">
          <div class="col-6 col-lg-3">
            <div class="metric-card metric-leads">
              <div class="metric-card-top">
                <span class="metric-card-label">Total Submissions</span>
                <div class="metric-card-icon"><i class="bi bi-chat-quote-fill"></i></div>
              </div>
              <div class="metric-card-bottom">
                <h3 class="metric-card-value"><?= $totalDiscussions ?></h3>
                <span class="metric-card-subtext d-none d-sm-inline">All Blog Articles</span>
              </div>
            </div>
          </div>

          <div class="col-6 col-lg-3">
            <div class="metric-card metric-action">
              <div class="metric-card-top">
                <span class="metric-card-label">Pending Approval</span>
                <div class="metric-card-icon"><i class="bi bi-hourglass-split"></i></div>
              </div>
              <div class="metric-card-bottom">
                <h3 class="metric-card-value text-warning"><?= $pendingDiscussions ?></h3>
                <span class="metric-card-subtext d-none d-sm-inline">Needs Moderation</span>
              </div>
            </div>
          </div>

          <div class="col-6 col-lg-3">
            <div class="metric-card metric-contracts">
              <div class="metric-card-top">
                <span class="metric-card-label">Live on Website</span>
                <div class="metric-card-icon"><i class="bi bi-check-circle-fill"></i></div>
              </div>
              <div class="metric-card-bottom">
                <h3 class="metric-card-value text-success"><?= $approvedDiscussions ?></h3>
                <span class="metric-card-subtext d-none d-sm-inline">Publicly Visible</span>
              </div>
            </div>
          </div>

          <div class="col-6 col-lg-3">
            <div class="metric-card metric-quotes">
              <div class="metric-card-top">
                <span class="metric-card-label">Active Articles</span>
                <div class="metric-card-icon"><i class="bi bi-journal-text"></i></div>
              </div>
              <div class="metric-card-bottom">
                <h3 class="metric-card-value">12</h3>
                <span class="metric-card-subtext d-none d-sm-inline">Technical Guides</span>
              </div>
            </div>
          </div>
        </div>

        <!-- Filter & Search Toolbar -->
        <div class="filter-toolbar mb-3">
          <form method="GET" action="blog-discussions.php" class="row g-2 align-items-center">
            <div class="col-12 col-md-5">
              <div class="input-group input-group-sm search-input-group">
                <span class="input-group-text py-1.5"><i class="bi bi-search"></i></span>
                <input type="text" name="q" class="form-control py-1.5" placeholder="Search author, company, comment, article..." value="<?= htmlspecialchars($searchQuery) ?>">
                <?php if (!empty($searchQuery)): ?>
                  <a href="blog-discussions.php" class="input-group-text text-decoration-none py-1.5"><i class="bi bi-x-circle text-muted"></i></a>
                <?php endif; ?>
              </div>
            </div>

            <div class="col-6 col-md-3">
              <select name="status" class="form-select form-select-sm py-1.5" onchange="this.form.submit()">
                <option value="">All Statuses</option>
                <option value="Pending" <?= $filterStatus === 'Pending' ? 'selected' : '' ?>>Pending (<?= $pendingDiscussions ?>)</option>
                <option value="Approved" <?= $filterStatus === 'Approved' ? 'selected' : '' ?>>Approved (<?= $approvedDiscussions ?>)</option>
                <option value="Rejected" <?= $filterStatus === 'Rejected' ? 'selected' : '' ?>>Rejected</option>
              </select>
            </div>

            <div class="col-6 col-md-2">
              <button type="submit" class="btn-filter-action">
                <i class="bi bi-funnel"></i> <span>Filter</span>
              </button>
            </div>
          </form>

          <div class="quick-view-wrapper mt-2">
            <span class="quick-view-label"><i class="bi bi-tag-fill"></i> Stage:</span>
            <a href="blog-discussions.php" class="quick-filter-pill <?= empty($filterStatus) ? 'active' : '' ?>">All (<?= $totalDiscussions ?>)</a>
            <a href="blog-discussions.php?status=Pending" class="quick-filter-pill <?= $filterStatus === 'Pending' ? 'active' : '' ?>">Pending (<?= $pendingDiscussions ?>)</a>
            <a href="blog-discussions.php?status=Approved" class="quick-filter-pill <?= $filterStatus === 'Approved' ? 'active' : '' ?>">Approved (<?= $approvedDiscussions ?>)</a>
            <a href="blog-discussions.php?status=Rejected" class="quick-filter-pill <?= $filterStatus === 'Rejected' ? 'active' : '' ?>">Rejected</a>
          </div>
        </div>

        <!-- Discussions Register Table -->
        <div class="table-panel">
          <div class="table-panel-header">
            <div class="d-flex align-items-center gap-2">
              <h2 class="table-panel-title">
                <i class="bi bi-chat-left-text text-secondary"></i> Reader Comments & Questions Register
              </h2>
              <span class="badge rounded-pill bg-dark font-monospace px-2.5 py-1"><?= count($discussions) ?></span>
            </div>
          </div>

          <div class="table-responsive table-scroll-container">
            <table class="table-clean">
              <thead>
                <tr>
                  <th style="min-width: 220px;">Author & Origin</th>
                  <th style="min-width: 190px;">Article</th>
                  <th style="min-width: 320px;">Question / Discussion Text</th>
                  <th style="min-width: 110px;">Submitted</th>
                  <th style="min-width: 110px;">Status</th>
                  <th style="min-width: 180px;" class="text-end">Moderation Actions</th>
                </tr>
              </thead>
              <tbody>
                <?php if (empty($discussions)): ?>
                  <tr>
                    <td colspan="6" class="text-center py-5 text-muted">
                      No blog discussions found matching your filter criteria.
                    </td>
                  </tr>
                <?php else: ?>
                  <?php foreach ($discussions as $disc): ?>
                    <?php
                      $resolvedCountry = detectCountryName($disc['author_country'] ?? '', $disc['author_email'] ?? '', '', $disc['author_company'] ?? '', $disc['comment_text'] ?? '');
                      $nameWords = preg_split('/\s+/', trim($disc['author_name'] ?? 'Reader'));
                      $initials = '';
                      foreach ($nameWords as $w) {
                        if (!empty($w)) $initials .= mb_substr($w, 0, 1);
                        if (strlen($initials) >= 2) break;
                      }
                      $initials = strtoupper($initials ?: 'RD');
                    ?>
                    <tr>
                      <!-- Author -->
                      <td>
                        <div class="d-flex align-items-start gap-2">
                          <div class="buyer-avatar"><?= htmlspecialchars($initials) ?></div>
                          <div>
                            <div class="fw-semibold text-dark" style="font-size: 0.85rem;"><?= htmlspecialchars($disc['author_name']) ?></div>
                            <div class="text-muted small" style="font-size: 0.76rem;">
                              <i class="bi bi-building me-1"></i><?= htmlspecialchars($disc['author_company'] ?: 'Industry Reader') ?>
                            </div>
                            <div class="d-flex align-items-center gap-1 mt-1 small text-muted">
                              <span class="buyer-flag-chip" style="border:none !important; padding:0;"><?= getCountryFlagSvg($resolvedCountry) ?></span>
                              <span><?= htmlspecialchars($resolvedCountry) ?></span>
                            </div>
                            <?php if (!empty($disc['author_email'])): ?>
                              <div class="small text-secondary font-monospace mt-1" style="font-size: 0.72rem;">
                                <i class="bi bi-envelope me-1"></i><?= htmlspecialchars($disc['author_email']) ?>
                              </div>
                            <?php endif; ?>
                          </div>
                        </div>
                      </td>

                      <!-- Article Target -->
                      <td>
                        <a href="../blog/<?= htmlspecialchars($disc['article_slug']) ?>.html" target="_blank" class="article-slug-badge" title="Open live blog article">
                          <i class="bi bi-link-45deg"></i> <?= htmlspecialchars($disc['article_slug']) ?>
                        </a>
                      </td>

                      <!-- Comment & Official Reply -->
                      <td>
                        <div class="comment-bubble">
                          <i class="bi bi-chat-quote-fill me-1 text-secondary opacity-50"></i>
                          <?= nl2br(htmlspecialchars($disc['comment_text'])) ?>
                        </div>

                        <?php if (!empty($disc['reply_text'])): ?>
                          <div class="reply-bubble">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                              <strong><i class="bi bi-shield-check me-1"></i> <?= htmlspecialchars($disc['replied_by']) ?>:</strong>
                              <span class="font-monospace text-xs text-muted" style="font-size: 0.68rem;"><?= date('M d, Y', strtotime($disc['replied_at'])) ?></span>
                            </div>
                            <?= nl2br(htmlspecialchars($disc['reply_text'])) ?>
                          </div>
                        <?php endif; ?>
                      </td>

                      <!-- Date -->
                      <td>
                        <div class="small fw-semibold"><?= date('M d, Y', strtotime($disc['created_at'])) ?></div>
                        <div class="text-muted" style="font-size: 0.72rem;"><?= date('h:i A', strtotime($disc['created_at'])) ?></div>
                      </td>

                      <!-- Status Badge -->
                      <td>
                        <?php if ($disc['status'] === 'Approved'): ?>
                          <span class="badge-mod-approved">
                            Approved
                          </span>
                        <?php elseif ($disc['status'] === 'Pending'): ?>
                          <span class="badge-mod-pending">
                            Pending Review
                          </span>
                        <?php else: ?>
                          <span class="badge-mod-rejected">
                            Archived
                          </span>
                        <?php endif; ?>
                      </td>

                      <!-- Actions -->
                      <td class="text-end">
                        <div class="d-inline-flex gap-1.5 align-items-center justify-content-end">
                          <?php if ($disc['status'] !== 'Approved'): ?>
                            <form method="POST" action="blog-discussions.php" class="d-inline m-0">
                              <input type="hidden" name="action" value="set_status">
                              <input type="hidden" name="discussion_id" value="<?= $disc['id'] ?>">
                              <input type="hidden" name="status" value="Approved">
                              <input type="hidden" name="csrf_token" value="<?= getCsrfToken() ?>">
                              <button type="submit" class="btn-mod-approve" title="Approve & Publish to Article">
                                <i class="bi bi-check2"></i> Approve
                              </button>
                            </form>
                          <?php endif; ?>

                          <button type="button" class="btn-mod-reply" data-bs-toggle="modal" data-bs-target="#replyDiscModal<?= $disc['id'] ?>" title="Write Official Desk Answer">
                            <i class="bi bi-reply-fill"></i> Reply
                          </button>

                          <?php if ($disc['status'] !== 'Rejected'): ?>
                            <form method="POST" action="blog-discussions.php" class="d-inline m-0">
                              <input type="hidden" name="action" value="set_status">
                              <input type="hidden" name="discussion_id" value="<?= $disc['id'] ?>">
                              <input type="hidden" name="status" value="Rejected">
                              <input type="hidden" name="csrf_token" value="<?= getCsrfToken() ?>">
                              <button type="submit" class="btn-mod-icon" title="Reject / Archive">
                                <i class="bi bi-eye-slash"></i>
                              </button>
                            </form>
                          <?php endif; ?>

                          <form method="POST" action="blog-discussions.php" class="d-inline m-0" onsubmit="return confirm('Permanently delete this discussion?');">
                            <input type="hidden" name="action" value="delete_discussion">
                            <input type="hidden" name="discussion_id" value="<?= $disc['id'] ?>">
                            <input type="hidden" name="csrf_token" value="<?= getCsrfToken() ?>">
                            <button type="submit" class="btn-mod-icon btn-mod-delete" title="Delete permanently">
                              <i class="bi bi-trash3"></i>
                            </button>
                          </form>
                        </div>

                        <!-- Modal: Technical Answer Composer -->
                        <div class="modal fade text-start" id="replyDiscModal<?= $disc['id'] ?>" tabindex="-1" aria-hidden="true">
                          <div class="modal-dialog modal-dialog-centered">
                            <div class="modal-content">
                              <form method="POST" action="blog-discussions.php">
                                <input type="hidden" name="action" value="submit_reply">
                                <input type="hidden" name="discussion_id" value="<?= $disc['id'] ?>">
                                <input type="hidden" name="csrf_token" value="<?= getCsrfToken() ?>">

                                <div class="modal-header py-2.5 px-3">
                                  <h6 class="modal-title fw-bold brand-font" style="color: var(--brand-navy); font-size: 0.95rem;">
                                    Answer <?= htmlspecialchars($disc['author_name']) ?>'s Question
                                  </h6>
                                  <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                </div>

                                <div class="modal-body p-3">
                                  <div class="p-2.5 bg-light rounded border mb-3 small">
                                    <div class="text-xs text-muted fw-bold text-uppercase">Question under <?= htmlspecialchars($disc['article_slug']) ?>:</div>
                                    <div class="mt-1 text-dark fst-italic">&ldquo;<?= htmlspecialchars($disc['comment_text']) ?>&rdquo;</div>
                                  </div>

                                  <div class="mb-2">
                                    <label class="form-label small fw-bold mb-1">Answered By</label>
                                    <input type="text" name="replied_by" class="form-control form-control-sm" value="<?= htmlspecialchars($disc['replied_by'] ?: 'Balochistan Minerals Technical Desk') ?>" required>
                                  </div>

                                  <div class="mb-2">
                                    <label class="form-label small fw-bold mb-1">Official Technical Reply</label>
                                    <textarea name="reply_text" class="form-control form-control-sm font-monospace" rows="5" placeholder="Enter clear, technical, professional response..." required><?= htmlspecialchars($disc['reply_text'] ?? '') ?></textarea>
                                  </div>

                                  <div class="form-check p-2 bg-light rounded border">
                                    <input class="form-check-input ms-0 me-2" type="checkbox" name="auto_approve" value="1" id="autoApproveCheck<?= $disc['id'] ?>" checked>
                                    <label class="form-check-label small fw-semibold text-dark" for="autoApproveCheck<?= $disc['id'] ?>">
                                      Publish to live blog article immediately upon saving
                                    </label>
                                  </div>
                                </div>

                                <div class="modal-footer py-2 px-3">
                                  <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                                  <button type="submit" class="btn btn-sm btn-dark" style="background: #0F172A;">
                                    <i class="bi bi-check2-circle me-1"></i> Save & Publish Reply
                                  </button>
                                </div>
                              </form>
                            </div>
                          </div>
                        </div>

                      </td>
                    </tr>
                  <?php endforeach; ?>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>

      </main>
    </div>
  </div>

  <?php if (!empty($flashMessage)): ?>
  <div class="live-toast-container" id="flashToastContainer">
    <div class="live-toast" id="flashToast">
      <div class="live-toast-icon" style="background: <?= $flashType === 'danger' ? '#EF4444' : ($flashType === 'warning' ? '#F59E0B' : '#10B981') ?>;">
        <i class="bi <?= $flashType === 'danger' ? 'bi-x-circle-fill' : ($flashType === 'warning' ? 'bi-trash-fill' : 'bi-check2-circle') ?>"></i>
      </div>
      <div class="live-toast-body">
        <div class="live-toast-title"><?= $flashType === 'danger' ? 'Notice' : ($flashType === 'warning' ? 'Record Deleted' : 'Action Completed') ?></div>
        <div class="live-toast-text"><?= htmlspecialchars($flashMessage) ?></div>
      </div>
      <button type="button" class="live-toast-close" onclick="this.closest('.live-toast').remove()">&times;</button>
    </div>
  </div>
  <script>
    setTimeout(function() {
      const ft = document.getElementById('flashToast');
      if (ft) {
        ft.classList.add('hiding');
        setTimeout(() => ft.remove(), 350);
      }
    }, 3200);
  </script>
  <?php endif; ?>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
  <script src="admin-layout.js"></script>
</body>
</html>
