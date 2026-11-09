<?php
/**
 * Balochistan Minerals - Customer Reviews Moderation
 * Executive Left Sidebar Theme
 */

require_once __DIR__ . '/auth.php';
requireAuth();

$user = getLoggedInUser();
$pdo  = getDB();

$flashMessage = $_SESSION['bm_flash_msg'] ?? '';
$flashType    = $_SESSION['bm_flash_type'] ?? 'success';
unset($_SESSION['bm_flash_msg'], $_SESSION['bm_flash_type']);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $pdo !== null) {
    $action = $_POST['action'] ?? '';
    $csrf   = $_POST['csrf_token'] ?? '';
    $revId  = (int)($_POST['review_id'] ?? 0);

    if (verifyCsrfToken($csrf)) {
        if ($action === 'set_status') {
            $newStatus = cleanInput($_POST['status'] ?? 'Pending');
            $stmt = $pdo->prepare('UPDATE reviews SET status = ? WHERE id = ?');
            $stmt->execute([$newStatus, $revId]);
            $_SESSION['bm_flash_msg'] = "Review #$revId status updated to $newStatus.";
            $_SESSION['bm_flash_type'] = 'success';
        } elseif ($action === 'delete_review') {
            $stmt = $pdo->prepare('DELETE FROM reviews WHERE id = ?');
            $stmt->execute([$revId]);
            $_SESSION['bm_flash_msg'] = "Review #$revId permanently deleted.";
            $_SESSION['bm_flash_type'] = 'warning';
        }
    } else {
        $_SESSION['bm_flash_msg'] = 'Session expired. Please try again.';
        $_SESSION['bm_flash_type'] = 'danger';
    }

    // Post/Redirect/Get: Prevent browser form resubmission dialog on page refresh
    header('Location: ' . $_SERVER['REQUEST_URI']);
    exit;
}

$reviews = [];
$filterStatus = cleanInput($_GET['status'] ?? '');
$newLeads = 0;
$pendingReviews = 0;

if ($pdo !== null) {
    $newLeads = (int)$pdo->query("SELECT COUNT(*) FROM inquiries WHERE status = 'New'")->fetchColumn();
    $pendingReviews = (int)$pdo->query("SELECT COUNT(*) FROM reviews WHERE status = 'Pending'")->fetchColumn();
    $pendingDiscussions = (int)$pdo->query("SELECT COUNT(*) FROM blog_discussions WHERE status = 'Pending'")->fetchColumn();
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
        <button type="button" id="sidebarCloseBtn" class="btn-sidebar-panel-toggle" aria-label="Toggle Sidebar" title="Collapse Sidebar (Ctrl + B)">
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
          <span class="badge font-monospace" style="background: rgba(230,92,26,0.15); color: #E65C1A; font-size: 0.7rem;"><?= $totalProspects ?></span>
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
        <a href="reviews.php" class="sidebar-link active">
          <div><i class="bi bi-star-fill"></i> Customer Reviews</div>
          <?php if ($pendingReviews > 0): ?>
            <span class="badge bg-secondary font-monospace" style="font-size: 0.7rem;"><?= $pendingReviews ?></span>
          <?php endif; ?>
        </a>
        <a href="blog-discussions.php" class="sidebar-link">
          <div><i class="bi bi-chat-square-text-fill"></i> Blog Discussions</div>
          <?php if (!empty($pendingDiscussions) && $pendingDiscussions > 0): ?>
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
        <div class="topbar-left">
          <button type="button" id="sidebarToggle" class="btn-sidebar-toggle" title="Toggle Sidebar (Ctrl + B)">
            <i class="bi bi-layout-sidebar-inset"></i>
          </button>
          <div class="topbar-title-wrap">
            <h1 class="topbar-title">Customer Reviews Moderation</h1>
            <div class="topbar-subtitle">Buyer Testimonial Verification & Verification Moderation</div>
          </div>
        </div>
        <div class="topbar-right">
          <div class="btn-group btn-group-sm" role="group">
            <a href="reviews.php" class="btn <?= empty($filterStatus) ? 'btn-dark text-white fw-bold' : 'btn-outline-secondary text-dark' ?>" style="font-size: 0.78rem;">All</a>
            <a href="reviews.php?status=Pending" class="btn <?= $filterStatus === 'Pending' ? 'btn-warning fw-bold' : 'btn-outline-secondary text-dark' ?>" style="font-size: 0.78rem;">Pending (<?= $pendingReviews ?>)</a>
            <a href="reviews.php?status=Approved" class="btn <?= $filterStatus === 'Approved' ? 'btn-success text-white fw-bold' : 'btn-outline-secondary text-dark' ?>" style="font-size: 0.78rem;">Approved</a>
          </div>
        </div>
      </header>

      <main class="content-canvas">
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
                          <span class="badge-mod-approved">Approved</span>
                        <?php elseif ($rev['status'] === 'Pending'): ?>
                          <span class="badge-mod-pending">Pending Review</span>
                        <?php else: ?>
                          <span class="badge-mod-rejected">Rejected</span>
                        <?php endif; ?>
                      </td>
                      <td class="small text-muted"><?= date('M d, Y', strtotime($rev['created_at'])) ?></td>
                      <td class="text-end">
                        <div class="d-inline-flex gap-1.5 align-items-center justify-content-end">
                          <?php if ($rev['status'] !== 'Approved'): ?>
                            <form method="POST" action="reviews.php" class="d-inline m-0">
                              <input type="hidden" name="action" value="set_status">
                              <input type="hidden" name="status" value="Approved">
                              <input type="hidden" name="review_id" value="<?= $rev['id'] ?>">
                              <input type="hidden" name="csrf_token" value="<?= getCsrfToken() ?>">
                              <button type="submit" class="btn-mod-approve" title="Approve and make public">
                                <i class="bi bi-check2"></i> Approve
                              </button>
                            </form>
                          <?php endif; ?>

                          <?php if ($rev['status'] !== 'Rejected'): ?>
                            <form method="POST" action="reviews.php" class="d-inline m-0">
                              <input type="hidden" name="action" value="set_status">
                              <input type="hidden" name="status" value="Rejected">
                              <input type="hidden" name="review_id" value="<?= $rev['id'] ?>">
                              <input type="hidden" name="csrf_token" value="<?= getCsrfToken() ?>">
                              <button type="submit" class="btn-mod-icon" title="Reject / Hide from site">
                                <i class="bi bi-eye-slash"></i>
                              </button>
                            </form>
                          <?php endif; ?>

                          <form method="POST" action="reviews.php" class="d-inline m-0" onsubmit="return confirm('Delete this review permanently?');">
                            <input type="hidden" name="action" value="delete_review">
                            <input type="hidden" name="review_id" value="<?= $rev['id'] ?>">
                            <input type="hidden" name="csrf_token" value="<?= getCsrfToken() ?>">
                            <button type="submit" class="btn-mod-icon btn-mod-delete" title="Delete">
                              <i class="bi bi-trash3"></i>
                            </button>
                          </form>
                        </div>
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
