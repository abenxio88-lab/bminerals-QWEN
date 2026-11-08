<?php
/**
 * Balochistan Minerals - Customer Reviews Moderation
 * Executive Left Sidebar Theme
 */

require_once __DIR__ . '/auth.php';
requireAuth();

$user = getLoggedInUser();
$pdo  = getDB();

$flashMessage = '';
$flashType    = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $pdo !== null) {
    $action = $_POST['action'] ?? '';
    $csrf   = $_POST['csrf_token'] ?? '';
    $revId  = (int)($_POST['review_id'] ?? 0);

    if (verifyCsrfToken($csrf)) {
        if ($action === 'set_status') {
            $newStatus = cleanInput($_POST['status'] ?? 'Pending');
            $stmt = $pdo->prepare('UPDATE reviews SET status = ? WHERE id = ?');
            $stmt->execute([$newStatus, $revId]);
            $flashMessage = "Review #$revId status updated to $newStatus.";
        } elseif ($action === 'delete_review') {
            $stmt = $pdo->prepare('DELETE FROM reviews WHERE id = ?');
            $stmt->execute([$revId]);
            $flashMessage = "Review #$revId permanently deleted.";
            $flashType = 'warning';
        }
    } else {
        $flashMessage = 'Session expired. Please try again.';
        $flashType = 'danger';
    }
}

$reviews = [];
$filterStatus = cleanInput($_GET['status'] ?? '');
$newLeads = 0;
$pendingReviews = 0;

if ($pdo !== null) {
    $newLeads = (int)$pdo->query("SELECT COUNT(*) FROM inquiries WHERE status = 'New'")->fetchColumn();
    $pendingReviews = (int)$pdo->query("SELECT COUNT(*) FROM reviews WHERE status = 'Pending'")->fetchColumn();
    $totalProspects = (int)$pdo->query("SELECT COUNT(*) FROM buyer_prospects")->fetchColumn();

    $sql = "SELECT * FROM reviews WHERE 1=1";
    $params = [];
    if (!empty($filterStatus)) {
        $sql .= " AND status = ?";
        $params[] = $filterStatus;
    }
    $sql .= " ORDER BY created_at DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $reviews = $stmt->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Customer Reviews Moderation | Balochistan Minerals</title>
  <link rel="icon" type="image/png" sizes="32x32" href="../images/favicon/favicon-32x32.png">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@500;600;700;800&family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <link rel="stylesheet" href="admin-layout.css">
  <style>
    :root {
      --navy-900: #0F172A;
      --brand-navy: #0F172A;
      --brand-slate: #1E293B;
      --brand-gold: #B48C36;
      --brand-gold-subtle: #F8F5EE;
      --brand-border: #E2E8F0;
      --brand-canvas: #F8FAFC;
      --brand-surface: #FFFFFF;
      --brand-orange: #E65C1A;
      --text-dark: #0F172A;
      --text-muted: #64748B;
      --sidebar-width: 255px;
    }
    body { font-family: 'Poppins', sans-serif; font-size: 0.88rem; color: var(--text-dark); background-color: var(--brand-canvas); margin: 0; }
    h1, h2, h3, h4, h5, h6, .brand-font { font-family: 'Montserrat', sans-serif; letter-spacing: -0.015em; }
    .app-wrapper { display: flex; min-height: 100vh; }
    .app-sidebar {
      width: var(--sidebar-width); background: var(--brand-surface); border-right: 1px solid var(--brand-border);
      display: flex; flex-direction: column; position: fixed; top: 0; bottom: 0; left: 0; z-index: 1040;
    }
    .sidebar-header {
      padding: 22px 16px 18px;
      border-bottom: 1px solid var(--brand-border);
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      text-align: center;
    }

    .sidebar-logo {
      max-height: 48px;
      width: auto;
      margin: 0 auto 10px;
      display: block;
    }

    .sidebar-badge {
      font-family: 'Montserrat', sans-serif;
      font-size: 0.65rem;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 0.08em;
      padding: 4px 12px;
      border-radius: 20px;
      background: #E65C1A;
      color: #FFFFFF !important;
      border: 1px solid #D45A15;
      box-shadow: 0 2px 6px rgba(230, 92, 26, 0.3);
      display: inline-block;
      white-space: nowrap;
    }
    .sidebar-nav { padding: 16px 12px; flex: 1; }
    .sidebar-section-title { font-size: 0.65rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.08em; color: #94A3B8; padding: 12px 10px 6px; }
    .sidebar-link {
      display: flex; align-items: center; justify-content: space-between;
      padding: 9px 12px; border-radius: 6px; color: #475569; font-family: 'Montserrat', sans-serif; font-size: 0.82rem; font-weight: 600; text-decoration: none; margin-bottom: 2px; transition: all 0.15s ease;
    }
    .sidebar-link i { font-size: 1.05rem; margin-right: 10px; color: #64748B; }
    .sidebar-link:hover { background: #F1F5F9; color: var(--brand-navy); }
    .sidebar-link.active { background: #F8FAFC; color: var(--brand-navy); border: 1px solid var(--brand-border); position: relative; }
    .sidebar-link.active::before { content: ''; position: absolute; left: -12px; top: 6px; bottom: 6px; width: 3.5px; background: var(--brand-gold); border-radius: 0 3px 3px 0; }
    .sidebar-link.active i { color: var(--brand-gold); }
    .sidebar-footer { padding: 14px 18px; border-top: 1px solid var(--brand-border); }
    .app-main { margin-left: var(--sidebar-width); flex: 1; min-width: 0; }
    .top-bar { background: var(--brand-surface); border-bottom: 1px solid var(--brand-border); padding: 14px 28px; display: flex; align-items: center; justify-content: space-between; position: sticky; top: 0; z-index: 1030; }
    .content-canvas { padding: 24px 28px; }
    .star-rating { color: #F59E0B; }
  </style>
</head>
<body>

  <div class="app-wrapper">

    <!-- LEFT SIDEBAR (Slideable & Collapsible) -->
    <aside class="app-sidebar" id="appSidebar">
      <div class="sidebar-header">
        <a href="../" target="_blank" class="sidebar-logo-wrap" title="Balochistan Minerals">
          <img src="../images/new-logo.png" alt="Balochistan Minerals" class="sidebar-logo" onerror="this.style.display='none'">
          <span class="sidebar-badge">TRADE DESK PORTAL</span>
        </a>
        <button type="button" id="sidebarCloseBtn" class="btn btn-sm btn-light border d-lg-none" aria-label="Close Sidebar" title="Close Sidebar">
          <i class="bi bi-x-lg"></i>
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
          <div><i class="bi bi-envelope-paper-fill"></i> Email Reply Desk</div>
          <span class="badge bg-primary font-monospace" style="font-size: 0.65rem;">HOSTINGER</span>
        </a>
        <a href="prospects.php" class="sidebar-link">
          <div><i class="bi bi-bullseye"></i> Buyer Directory</div>
          <span class="badge font-monospace" style="background: rgba(230,92,26,0.15); color: #E65C1A; font-size: 0.7rem;"><?= $totalProspects ?></span>
        </a>
        <a href="radar.php" class="sidebar-link">
          <div><i class="bi bi-broadcast-pin"></i> Live Visitor Radar</div>
          <span class="badge bg-success font-monospace" style="font-size: 0.65rem;">LIVE</span>
        </a>

        <div class="sidebar-section-title">Logistics & Trade Operations</div>
        <a href="stockpiles.php" class="sidebar-link">
          <div><i class="bi bi-boxes"></i> Stockpiles & Shipments</div>
        </a>
        <a href="prices.php" class="sidebar-link">
          <div><i class="bi bi-cash-stack"></i> Mineral Spot Prices</div>
        </a>

        <div class="sidebar-section-title">Buyer Engagement</div>
        <a href="reviews.php" class="sidebar-link active">
          <div><i class="bi bi-star-fill"></i> Customer Reviews</div>
          <?php if ($pendingReviews > 0): ?>
            <span class="badge bg-secondary font-monospace" style="font-size: 0.7rem;"><?= $pendingReviews ?></span>
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
          <div class="fw-semibold small" style="color: var(--navy-900);"><?= htmlspecialchars($user['full_name']) ?></div>
          <a href="logout.php" class="btn btn-sm btn-outline-danger p-1 px-2" title="Sign Out"><i class="bi bi-power"></i></a>
        </div>
      </div>
    </aside>

    <!-- Mobile / Tablet Backdrop Overlay -->
    <div class="sidebar-backdrop" id="sidebarBackdrop"></div>

    <!-- MAIN AREA -->
    <div class="app-main">
      <header class="top-bar">
        <div class="d-flex align-items-center gap-2.5">
          <button type="button" id="sidebarToggle" class="btn-sidebar-toggle" title="Toggle Sidebar (Ctrl + B)">
            <i class="bi bi-layout-sidebar-inset"></i>
          </button>
          <div>
            <h1 class="h5 mb-0 fw-bold brand-font" style="color: var(--navy-900);">Customer Reviews Moderation</h1>
            <div class="small text-muted">Approve or reject client testimonials before they display on the public website.</div>
          </div>
        </div>
        <div class="d-flex gap-2">
          <a href="reviews.php" class="btn btn-sm <?= empty($filterStatus) ? 'btn-dark' : 'btn-outline-secondary' ?>">All</a>
          <a href="reviews.php?status=Pending" class="btn btn-sm <?= $filterStatus === 'Pending' ? 'btn-warning' : 'btn-outline-secondary' ?>">Pending (<?= $pendingReviews ?>)</a>
          <a href="reviews.php?status=Approved" class="btn btn-sm <?= $filterStatus === 'Approved' ? 'btn-success' : 'btn-outline-secondary' ?>">Approved</a>
        </div>
      </header>

      <main class="content-canvas">
        <?php if (!empty($flashMessage)): ?>
          <div class="alert alert-<?= $flashType ?> alert-dismissible fade show" role="alert">
            <?= htmlspecialchars($flashMessage) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
          </div>
        <?php endif; ?>

        <div class="card shadow-sm border-0 rounded-3">
          <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
              <thead class="table-light">
                <tr>
                  <th>Reviewer & Company</th>
                  <th>Rating</th>
                  <th>Product / Commodity</th>
                  <th>Client Feedback</th>
                  <th>Status</th>
                  <th>Date</th>
                  <th class="text-end">Moderation</th>
                </tr>
              </thead>
              <tbody>
                <?php if (empty($reviews)): ?>
                  <tr><td colspan="7" class="text-center py-5 text-muted">No reviews found.</td></tr>
                <?php else: ?>
                  <?php foreach ($reviews as $rev): ?>
                    <tr>
                      <td>
                        <div class="fw-bold"><?= htmlspecialchars($rev['reviewer_name']) ?></div>
                        <div class="small text-muted"><?= htmlspecialchars($rev['company_name'] ?: 'Independent') ?> (<?= htmlspecialchars($rev['country'] ?: 'Global') ?>)</div>
                      </td>
                      <td>
                        <div class="star-rating">
                          <?php for ($i = 1; $i <= 5; $i++): ?>
                            <i class="bi bi-star<?= $i <= $rev['rating'] ? '-fill' : '' ?>"></i>
                          <?php endfor; ?>
                        </div>
                      </td>
                      <td><span class="badge bg-light text-dark border"><?= htmlspecialchars($rev['product_reviewed']) ?></span></td>
                      <td style="max-width: 320px;"><div class="small"><?= nl2br(htmlspecialchars($rev['review_text'])) ?></div></td>
                      <td>
                        <?php if ($rev['status'] === 'Approved'): ?>
                          <span class="status-badge-active"><i class="bi bi-check-circle me-1"></i> Approved (Live)</span>
                        <?php elseif ($rev['status'] === 'Pending'): ?>
                          <span style="background: #FEF3C7; color: #92400E; border: 1px solid #FDE68A; font-weight: 700; font-size: 0.72rem; padding: 3px 8px; border-radius: 4px; display: inline-block;"><i class="bi bi-clock me-1"></i> Pending Review</span>
                        <?php else: ?>
                          <span class="status-badge-hidden">Rejected</span>
                        <?php endif; ?>
                      </td>
                      <td class="small text-muted"><?= date('M d, Y', strtotime($rev['created_at'])) ?></td>
                      <td class="text-end">
                        <div class="d-inline-flex gap-1">
                          <?php if ($rev['status'] !== 'Approved'): ?>
                            <form method="POST" action="reviews.php">
                              <input type="hidden" name="action" value="set_status">
                              <input type="hidden" name="status" value="Approved">
                              <input type="hidden" name="review_id" value="<?= $rev['id'] ?>">
                              <input type="hidden" name="csrf_token" value="<?= getCsrfToken() ?>">
                              <button type="submit" class="btn btn-sm btn-success" title="Approve and make public">
                                <i class="bi bi-check-lg"></i> Approve
                              </button>
                            </form>
                          <?php endif; ?>

                          <?php if ($rev['status'] !== 'Rejected'): ?>
                            <form method="POST" action="reviews.php">
                              <input type="hidden" name="action" value="set_status">
                              <input type="hidden" name="status" value="Rejected">
                              <input type="hidden" name="review_id" value="<?= $rev['id'] ?>">
                              <input type="hidden" name="csrf_token" value="<?= getCsrfToken() ?>">
                              <button type="submit" class="btn btn-sm btn-outline-secondary" title="Reject / Hide from site">
                                <i class="bi bi-x-lg"></i>
                              </button>
                            </form>
                          <?php endif; ?>

                          <form method="POST" action="reviews.php" onsubmit="return confirm('Delete this review permanently?');">
                            <input type="hidden" name="action" value="delete_review">
                            <input type="hidden" name="review_id" value="<?= $rev['id'] ?>">
                            <input type="hidden" name="csrf_token" value="<?= getCsrfToken() ?>">
                            <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete">
                              <i class="bi bi-trash"></i>
                            </button>
                          </form>
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

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
  <script src="admin-layout.js"></script>
</body>
</html>
