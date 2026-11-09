<?php
/**
 * Balochistan Minerals - Dynamic Buyer FAQ & Policy Manager
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

    if (verifyCsrfToken($csrf)) {
        if ($action === 'save_faq') {
            $id       = (int)($_POST['faq_id'] ?? 0);
            $cat      = cleanInput($_POST['category'] ?? 'Export & Shipping');
            $question = cleanInput($_POST['question'] ?? '');
            $answer   = cleanInput($_POST['answer'] ?? '');
            $isActive = isset($_POST['is_active']) ? 1 : 0;

            if ($id > 0) {
                $stmt = $pdo->prepare('UPDATE faqs SET category = ?, question = ?, answer = ?, is_active = ? WHERE id = ?');
                $stmt->execute([$cat, $question, $answer, $isActive, $id]);
                $flashMessage = 'FAQ updated successfully.';
            } else {
                $stmt = $pdo->prepare('INSERT INTO faqs (category, question, answer, is_active) VALUES (?, ?, ?, ?)');
                $stmt->execute([$cat, $question, $answer, $isActive]);
                $flashMessage = 'New FAQ created successfully.';
            }
        } elseif ($action === 'delete_faq') {
            $id = (int)($_POST['faq_id'] ?? 0);
            $stmt = $pdo->prepare('DELETE FROM faqs WHERE id = ?');
            $stmt->execute([$id]);
            $flashMessage = 'FAQ permanently deleted.';
            $flashType = 'warning';
        }
    } else {
        $flashMessage = 'Session expired. Please try again.';
        $flashType = 'danger';
    }
}

$faqs = [];
$newLeads = 0;
$pendingReviews = 0;

if ($pdo !== null) {
    $newLeads = (int)$pdo->query("SELECT COUNT(*) FROM inquiries WHERE status = 'New'")->fetchColumn();
    $pendingReviews = (int)$pdo->query("SELECT COUNT(*) FROM reviews WHERE status = 'Pending'")->fetchColumn();
    $totalProspects = (int)$pdo->query("SELECT COUNT(*) FROM buyer_prospects")->fetchColumn();
    $faqs = $pdo->query('SELECT * FROM faqs ORDER BY sort_order ASC, id ASC')->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Dynamic FAQs | Balochistan Minerals</title>
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

    /* Crisp Luxury Modal & Button Styles */
    .modal-content {
      border: 1px solid rgba(15, 23, 42, 0.1) !important;
      border-radius: 14px !important;
      box-shadow: 0 25px 50px -12px rgba(15, 23, 42, 0.25) !important;
      overflow: hidden;
    }
    .modal-header {
      background: #F8FAFC;
      border-bottom: 1px solid #E2E8F0;
      padding: 18px 24px;
    }
    .modal-body {
      padding: 24px;
      background: #FFFFFF;
    }
    .modal-footer {
      background: #F8FAFC;
      border-top: 1px solid #E2E8F0;
      padding: 16px 24px;
    }
    .form-control, .form-select {
      border: 1.5px solid #CBD5E1;
      border-radius: 8px;
      padding: 8px 12px;
      font-size: 0.88rem;
      color: #0F172A;
      font-weight: 500;
    }
    .form-control:focus, .form-select:focus {
      border-color: #E65C1A;
      box-shadow: 0 0 0 3px rgba(230, 92, 26, 0.15);
      outline: none;
    }
    .btn-save-action {
      background: #0F172A !important;
      color: #FFFFFF !important;
      border: 1px solid #0F172A !important;
      padding: 8px 22px !important;
      font-weight: 700 !important;
      font-size: 0.84rem !important;
      border-radius: 6px !important;
      display: inline-flex !important;
      align-items: center !important;
      gap: 6px !important;
      box-shadow: 0 2px 6px rgba(15,23,42,0.2) !important;
      cursor: pointer !important;
    }
    .btn-save-action:hover {
      background: #1E293B !important;
      border-color: #1E293B !important;
    }
    .btn-cancel-action {
      background: #FFFFFF !important;
      color: #475569 !important;
      border: 1.5px solid #CBD5E1 !important;
      padding: 8px 18px !important;
      font-weight: 600 !important;
      font-size: 0.84rem !important;
      border-radius: 6px !important;
      cursor: pointer !important;
    }
    .btn-cancel-action:hover {
      background: #F1F5F9 !important;
      color: #0F172A !important;
      border-color: #94A3B8 !important;
    }
    .btn-delete-action {
      background: #FEF2F2 !important;
      color: #DC2626 !important;
      border: 1.5px solid #FECACA !important;
      padding: 8px 16px !important;
      font-weight: 600 !important;
      font-size: 0.84rem !important;
      border-radius: 6px !important;
      display: inline-flex !important;
      align-items: center !important;
      gap: 6px !important;
      cursor: pointer !important;
    }
    .btn-delete-action:hover {
      background: #FEE2E2 !important;
      color: #991B1B !important;
      border-color: #F87171 !important;
    }
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

        <div class="sidebar-section-title">Logistics & Trade Operations</div>
        <a href="stockpiles.php" class="sidebar-link">
          <div><i class="bi bi-boxes"></i> Stockpiles & Shipments</div>
        </a>
        <a href="prices.php" class="sidebar-link">
          <div><i class="bi bi-cash-stack"></i> Mineral Spot Prices</div>
        </a>

        <div class="sidebar-section-title">Buyer Engagement</div>
        <a href="reviews.php" class="sidebar-link">
          <div><i class="bi bi-star-fill"></i> Customer Reviews</div>
          <?php if ($pendingReviews > 0): ?>
            <span class="badge bg-secondary font-monospace" style="font-size: 0.7rem;"><?= $pendingReviews ?></span>
          <?php endif; ?>
        </a>
        <a href="faqs.php" class="sidebar-link active">
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
            <h1 class="topbar-title">Dynamic Buyer FAQs & Policies</h1>
            <div class="topbar-subtitle">Shipping Terms, Inspection Protocols & Payment FAQs</div>
          </div>
        </div>
        <div class="topbar-right">
          <button type="button" class="btn-topbar-primary" data-bs-toggle="modal" data-bs-target="#faqModal0">
            <i class="bi bi-plus-lg"></i> <span>Add New FAQ</span>
          </button>
        </div>
      </header>

      <main class="content-canvas">
        <?php if (!empty($flashMessage)): ?>
          <div class="alert alert-<?= $flashType ?> alert-dismissible fade show" role="alert">
            <?= htmlspecialchars($flashMessage) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
          </div>
        <?php endif; ?>

        <div class="row g-3">
          <?php if (empty($faqs)): ?>
            <div class="col-12"><div class="card p-5 text-center text-muted">No FAQs defined yet.</div></div>
          <?php else: ?>
            <?php foreach ($faqs as $f): ?>
              <div class="col-12 col-lg-6">
                <div class="card h-100 shadow-sm border-0 p-3 rounded-3">
                  <div class="d-flex align-items-start justify-content-between mb-2">
                    <span class="badge" style="background: #F1F5F9; color: #0F172A; border: 1px solid #CBD5E1; font-weight: 700; font-size: 0.72rem;"><?= htmlspecialchars($f['category']) ?></span>
                    <div class="d-flex align-items-center gap-1">
                      <?php if ($f['is_active']): ?>
                        <span class="status-badge-active"><i class="bi bi-dot"></i> Live</span>
                      <?php else: ?>
                        <span class="status-badge-hidden">Draft</span>
                      <?php endif; ?>
                      <button type="button" class="btn btn-sm btn-link text-dark p-0 ms-2" data-bs-toggle="modal" data-bs-target="#faqModal<?= $f['id'] ?>">
                        <i class="bi bi-pencil-square"></i>
                      </button>
                    </div>
                  </div>
                  <h6 class="fw-bold mb-2 brand-font" style="color: var(--navy-900);"><?= htmlspecialchars($f['question']) ?></h6>
                  <p class="small text-muted mb-0"><?= nl2br(htmlspecialchars($f['answer'])) ?></p>
                </div>
              </div>

              <!-- Edit FAQ Modal -->
              <div class="modal fade" id="faqModal<?= $f['id'] ?>" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered">
                  <div class="modal-content">
                    <form method="POST" action="faqs.php">
                      <input type="hidden" name="action" value="save_faq">
                      <input type="hidden" name="faq_id" value="<?= $f['id'] ?>">
                      <input type="hidden" name="csrf_token" value="<?= getCsrfToken() ?>">

                      <div class="modal-header">
                        <h6 class="modal-title fw-bold">Edit FAQ</h6>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                      </div>

                      <div class="modal-body">
                        <div class="mb-3">
                          <label class="form-label small fw-semibold">Category</label>
                          <select name="category" class="form-select form-select-sm">
                            <option value="Export & Shipping" <?= $f['category'] === 'Export & Shipping' ? 'selected' : '' ?>>Export & Shipping</option>
                            <option value="Quality & Assays" <?= $f['category'] === 'Quality & Assays' ? 'selected' : '' ?>>Quality & Assays</option>
                            <option value="Payment Terms" <?= $f['category'] === 'Payment Terms' ? 'selected' : '' ?>>Payment Terms</option>
                            <option value="Logistics" <?= $f['category'] === 'Logistics' ? 'selected' : '' ?>>Logistics</option>
                          </select>
                        </div>
                        <div class="mb-3">
                          <label class="form-label small fw-semibold">Question</label>
                          <input type="text" name="question" class="form-control form-control-sm" value="<?= htmlspecialchars($f['question']) ?>" required>
                        </div>
                        <div class="mb-3">
                          <label class="form-label small fw-semibold">Answer</label>
                          <textarea name="answer" class="form-control form-control-sm" rows="4" required><?= htmlspecialchars($f['answer']) ?></textarea>
                        </div>
                        <div class="form-check">
                          <input type="checkbox" name="is_active" class="form-check-input" id="actFaq<?= $f['id'] ?>" <?= $f['is_active'] ? 'checked' : '' ?>>
                          <label class="form-check-label small" for="actFaq<?= $f['id'] ?>">Show on website</label>
                        </div>
                      </div>

                      <div class="modal-footer d-flex justify-content-between">
                        <button type="button" class="btn-delete-action" onclick="if(confirm('Delete FAQ permanently?')) { document.getElementById('delFaq<?= $f['id'] ?>').submit(); }">
                          <i class="bi bi-trash3 me-1"></i> Delete
                        </button>
                        <div class="d-flex gap-2">
                          <button type="button" class="btn-cancel-action" data-bs-dismiss="modal">Cancel</button>
                          <button type="submit" class="btn-save-action">
                            <i class="bi bi-check2-circle me-1"></i> Save FAQ
                          </button>
                        </div>
                      </div>
                    </form>

                    <form id="delFaq<?= $f['id'] ?>" method="POST" action="faqs.php" style="display:none;">
                      <input type="hidden" name="action" value="delete_faq">
                      <input type="hidden" name="faq_id" value="<?= $f['id'] ?>">
                      <input type="hidden" name="csrf_token" value="<?= getCsrfToken() ?>">
                    </form>
                  </div>
                </div>
              </div>

            <?php endforeach; ?>
          <?php endif; ?>
        </div>

        <!-- Add Modal -->
        <div class="modal fade" id="faqModal0" tabindex="-1" aria-hidden="true">
          <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
              <form method="POST" action="faqs.php">
                <input type="hidden" name="action" value="save_faq">
                <input type="hidden" name="faq_id" value="0">
                <input type="hidden" name="csrf_token" value="<?= getCsrfToken() ?>">

                <div class="modal-header">
                  <h6 class="modal-title fw-bold">Add New Buyer FAQ</h6>
                  <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body">
                  <div class="mb-3">
                    <label class="form-label small fw-semibold">Category</label>
                    <select name="category" class="form-select form-select-sm">
                      <option value="Export & Shipping">Export & Shipping</option>
                      <option value="Quality & Assays">Quality & Assays</option>
                      <option value="Payment Terms">Payment Terms</option>
                      <option value="Logistics">Logistics</option>
                    </select>
                  </div>
                  <div class="mb-3">
                    <label class="form-label small fw-semibold">Question</label>
                    <input type="text" name="question" class="form-control form-control-sm" placeholder="e.g. Can we arrange SGS inspection?" required>
                  </div>
                  <div class="mb-3">
                    <label class="form-label small fw-semibold">Answer</label>
                    <textarea name="answer" class="form-control form-control-sm" rows="4" placeholder="Detailed explanation..." required></textarea>
                  </div>
                  <div class="form-check">
                    <input type="checkbox" name="is_active" class="form-check-input" id="actNewFaq" checked>
                    <label class="form-check-label small" for="actNewFaq">Show on website</label>
                  </div>
                </div>

                <div class="modal-footer d-flex justify-content-end gap-2">
                  <button type="button" class="btn-cancel-action" data-bs-dismiss="modal">Cancel</button>
                  <button type="submit" class="btn-save-action" style="background: #E65C1A !important; border-color: #D45A15 !important;">
                    <i class="bi bi-plus-lg me-1"></i> Create FAQ
                  </button>
                </div>
              </form>
            </div>
          </div>
        </div>

      </main>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
  <script src="admin-layout.js"></script>
</body>
</html>
