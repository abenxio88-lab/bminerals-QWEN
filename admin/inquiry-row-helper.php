<?php
/**
 * Balochistan Minerals - Executive RFQ Table Row & Modal Component Generator
 * Shared between dashboard.php and api-live-poll.php for zero markup drift.
 */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/flag-helper.php';
require_once __DIR__ . '/mail-helpers.php';

if (!function_exists('getBallInCourtStatus')) {
    /**
     * Compute Ball-in-Court Velocity Status for deal negotiations
     */
    function getBallInCourtStatus($row) {
        if (($row['status'] ?? '') === 'Contract Signed') {
            return [
                'code' => 'closed',
                'label' => 'Contract Executed',
                'badgeClass' => 'badge-vel-closed',
                'icon' => 'bi-check-circle-fill'
            ];
        }
        $lastDir = $row['last_email_dir'] ?? null;
        $lastTime = !empty($row['last_email_time']) ? strtotime($row['last_email_time']) : (!empty($row['created_at']) ? strtotime($row['created_at']) : time());
        $daysDiff = (time() - $lastTime) / 86400;

        if (empty($lastDir) || $lastDir === 'inbound') {
            return [
                'code' => 'desk_turn',
                'label' => 'Desk Turn',
                'badgeClass' => 'badge-vel-desk',
                'icon' => 'bi-arrow-return-right'
            ];
        } else {
            if ($daysDiff >= 5) {
                return [
                    'code' => 'stalled',
                    'label' => 'Stalled (' . floor($daysDiff) . 'd)',
                    'badgeClass' => 'badge-vel-stalled',
                    'icon' => 'bi-hourglass-split'
                ];
            } else {
                return [
                    'code' => 'buyer_turn',
                    'label' => 'Awaiting Buyer',
                    'badgeClass' => 'badge-vel-buyer',
                    'icon' => 'bi-clock-history'
                ];
            }
        }
    }
}

if (!function_exists('renderInquiryRow')) {
    /**
     * Render full <tr> for an inquiry, including embedded Reply and Edit Modals
     */
    function renderInquiryRow(array $row, string $csrfToken, bool $isNewHighlight = false): string {
        $statusClean = str_replace(' ', '_', $row['status'] ?? 'New');
        $waMsg = "Hello " . ($row['buyer_name'] ?? 'Buyer') . ", this is Balochistan Minerals (Pvt) Ltd regarding your export inquiry for " . ($row['mineral_type'] ?? 'minerals');
        if (!empty($row['quantity'])) {
            $waMsg .= " (" . $row['quantity'] . ")";
        }
        $waMsg .= ". We have verified stockpiles available. How may we assist with your specifications and assay requirements?";
        $waUrl = generateWhatsAppLink($row['whatsapp'] ?? '', $waMsg);

        $nameWords = preg_split('/\s+/', trim($row['buyer_name'] ?? 'Buyer'));
        $initials = '';
        foreach ($nameWords as $w) {
            if (!empty($w)) $initials .= mb_substr($w, 0, 1);
            if (strlen($initials) >= 2) break;
        }
        $initials = strtoupper($initials ?: 'BM');
        $resolvedCountry = detectCountryName($row['country'] ?? '', $row['email'] ?? '', $row['whatsapp'] ?? '', $row['company_name'] ?? '', $row['message'] ?? '');
        $bic = getBallInCourtStatus($row);

        $rowClass = $isNewHighlight ? 'row-new-highlight' : '';

        ob_start();
        ?>
        <tr id="inquiryRow_<?= (int)$row['id'] ?>" data-inquiry-id="<?= (int)$row['id'] ?>" class="<?= $rowClass ?>">
          <!-- Buyer -->
          <td>
            <div class="d-flex align-items-start" style="gap: 14px;">
              <div class="buyer-id-col">
                <div class="buyer-avatar cursor-pointer" onclick="openBuyerDossier(<?= (int)$row['id'] ?>)" title="Click to open <?= htmlspecialchars($row['buyer_name'] ?? '') ?> Dossier & Thread" style="cursor: pointer;"><?= htmlspecialchars($initials) ?></div>
                <div class="buyer-flag-chip" title="<?= htmlspecialchars($resolvedCountry) ?>" data-bs-toggle="tooltip">
                  <?= getCountryFlagSvg($resolvedCountry, $row['email'] ?? '', $row['whatsapp'] ?? '', $row['company_name'] ?? '') ?>
                </div>
              </div>
              <div style="min-width: 0; flex: 1;">
                <div class="fw-semibold" style="line-height: 1.3;">
                  <a href="javascript:void(0)" onclick="openBuyerDossier(<?= (int)$row['id'] ?>)" class="buyer-name-link" title="Open 360° Buyer Dossier & Negotiation Thread">
                    <?= htmlspecialchars($row['buyer_name'] ?? '') ?> <i class="bi bi-chevron-right" style="font-size: 0.72rem;"></i>
                  </a>
                </div>
                <div class="text-muted" style="font-size: 0.78rem; margin-top: 1px;">
                  <i class="bi bi-building me-1 text-secondary" style="font-size: 0.72rem;"></i><?= htmlspecialchars($row['company_name'] ?: 'Private Trader') ?>
                </div>

                <!-- Buyer Direct Email (Full Width, No Awkward Truncation) -->
                <?php if (!empty($row['email'])): ?>
                  <div class="d-flex align-items-center gap-1.5 mt-1">
                    <a href="mailto:<?= htmlspecialchars($row['email']) ?>" class="buyer-email-link" title="Send direct email to <?= htmlspecialchars($row['email']) ?>">
                      <i class="bi bi-envelope me-1 text-secondary"></i><?= htmlspecialchars($row['email']) ?>
                    </a>
                    <button type="button" class="btn-copy-clip" onclick="copyToClipboard('<?= htmlspecialchars($row['email']) ?>', this)" title="Copy email address">
                      <i class="bi bi-clipboard"></i>
                    </button>
                  </div>
                <?php endif; ?>

                <!-- Source & Mailbox Channel & Velocity Tracker -->
                <div class="mt-1.5 d-flex gap-1.5 flex-wrap align-items-center">
                  <span class="badge bg-light text-secondary fw-medium" style="font-size: 0.68rem; padding: 3px 8px; max-width: 100%; overflow: hidden; text-overflow: ellipsis; border: none !important; box-shadow: none !important;">
                    <i class="bi bi-inbox-fill text-primary me-1"></i><?= !empty($row['source']) && strpos($row['source'], '@') !== false ? htmlspecialchars($row['source']) : 'sales@balochistanminerals.com' ?>
                  </span>

                  <?php if (!empty($row['source']) && $row['source'] === 'Direct Email Modal'): ?>
                    <span class="badge bg-light text-dark fw-medium" style="font-size: 0.68rem; padding: 3px 8px; border: none !important; box-shadow: none !important;">
                      <i class="bi bi-chat-left-dots text-warning me-1"></i>Direct Message
                    </span>
                  <?php endif; ?>

                  <?php if (!empty($row['email_count']) && (int)$row['email_count'] > 0): ?>
                    <span class="badge bg-light text-primary fw-semibold cursor-pointer" onclick="openBuyerDossier(<?= (int)$row['id'] ?>)" style="font-size: 0.68rem; padding: 3px 8px; cursor: pointer; border: none !important; box-shadow: none !important;" title="Inspect <?= $row['email_count'] ?> Email Thread">
                      <i class="bi bi-chat-dots me-1"></i><?= (int)$row['email_count'] ?> Email<?= (int)$row['email_count'] > 1 ? 's' : '' ?>
                    </span>
                  <?php endif; ?>

                  <span class="<?= $bic['badgeClass'] ?> cursor-pointer" onclick="openBuyerDossier(<?= (int)$row['id'] ?>)" title="Deal Velocity: <?= $bic['label'] ?> - Click for 360° Dossier">
                    <?= $bic['label'] ?>
                  </span>
                </div>
              </div>
            </div>
          </td>

          <!-- Mineral & Message Snippet -->
          <td>
            <div class="mineral-spec-tag mb-1"><?= htmlspecialchars($row['mineral_type'] ?? '') ?></div>
            <?php if (!empty($row['grade_spec'])): ?>
              <div class="small text-muted font-monospace"><?= htmlspecialchars($row['grade_spec']) ?></div>
            <?php endif; ?>

            <!-- Buyer Inquiry Message / Email Content Preview -->
            <?php if (!empty($row['message'])):
              $cleanSnippet = function_exists('cleanEmailPlainText') ? cleanEmailPlainText($row['message']) : $row['message'];
            ?>
              <div class="inquiry-message-snippet mt-1.5 pt-1 border-top" style="border-color: #F1F5F9 !important; max-width: 100%; overflow: hidden;">
                <a href="javascript:void(0)" class="text-decoration-none d-block" data-bs-toggle="modal" data-bs-target="#replyModal<?= (int)$row['id'] ?>" title="Click to read full message & reply">
                  <span class="text-muted small fst-italic" style="font-size: 0.72rem; line-height: 1.35; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; word-break: break-word; overflow-wrap: anywhere; color: #475569 !important;">
                    <i class="bi bi-chat-left-quote-fill me-1 text-secondary" style="font-size: 0.68rem;"></i>&ldquo;<?= htmlspecialchars($cleanSnippet) ?>&rdquo;
                  </span>
                </a>
              </div>
            <?php endif; ?>
          </td>

          <!-- Port -->
          <td>
            <div class="fw-semibold" style="color: #0F172A; font-size: 0.80rem;"><?= htmlspecialchars($row['destination_port'] ?: 'FOB Karachi') ?></div>
          </td>

          <!-- Date -->
          <td>
            <div class="small fw-semibold"><?= date('M d, Y', strtotime($row['created_at'])) ?></div>
            <div class="text-muted" style="font-size: 0.72rem;"><?= date('h:i A', strtotime($row['created_at'])) ?></div>
          </td>

          <!-- Status Badge -->
          <td>
            <span class="status-badge status-<?= $statusClean ?>">
              <?= htmlspecialchars($row['status'] ?? 'New') ?>
            </span>
          </td>

          <!-- Spot Quote -->
          <td>
            <?php if ($row['spot_price_quoted'] !== null && $row['spot_price_quoted'] !== ''): ?>
              <span class="fw-bold font-monospace" style="color: #15803D;">
                $<?= number_format((float)$row['spot_price_quoted'], 2) ?>
              </span>
              <span class="text-muted" style="font-size: 0.7rem;">/ MT</span>
            <?php else: ?>
              <span class="text-muted small fst-italic">Pending quote</span>
            <?php endif; ?>
          </td>

          <!-- Actions -->
          <td class="text-end">
            <div class="d-inline-flex flex-nowrap align-items-center" style="gap: 6px;">
              <button type="button" class="btn-action-email" data-bs-toggle="modal" data-bs-target="#replyModal<?= (int)$row['id'] ?>" title="Send Official Email Reply">
                <i class="bi bi-reply-fill"></i> Reply
              </button>

              <a href="<?= $waUrl ?>" target="_blank" class="btn-action-wa" title="Launch chat on WhatsApp">
                <i class="bi bi-whatsapp"></i> Chat
              </a>

              <a href="generate-quote.php?id=<?= (int)$row['id'] ?>" target="_blank" class="btn-action-quote" title="Generate Official Corporate PDF Quote">
                <i class="bi bi-file-earmark-pdf"></i> Quote
              </a>

              <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#editModal<?= (int)$row['id'] ?>" title="Edit Lead">
                <i class="bi bi-pencil"></i>
              </button>
            </div>

<?php
  $benchmark = function_exists('getMineralBenchmarkForType') ? getMineralBenchmarkForType($row['mineral_type'] ?? '') : null;
  $effectiveSpotPrice = (!empty($row['spot_price_quoted']) && (float)$row['spot_price_quoted'] > 0)
      ? (float)$row['spot_price_quoted']
      : (float)($benchmark['price_high_usd'] ?? 185.00);
  $resolvedGradeSpec = !empty($row['grade_spec']) ? $row['grade_spec'] : ($benchmark['purity_spec'] ?? '');
  $resolvedPortTerms = !empty($row['destination_port']) ? $row['destination_port'] : ($benchmark['delivery_terms'] ?? 'FOB Karachi');
  $resolvedQtyLabel  = !empty($row['quantity']) ? $row['quantity'] : 'Open / Trial FCL Order';
  $configuredBoxes   = function_exists('getConfiguredMailboxes') ? getConfiguredMailboxes() : [];
  $rowMailbox = function_exists('matchCorporateMailboxFromString')
      ? matchCorporateMailboxFromString($row['source'] ?? '', 'sales@balochistanminerals.com')
      : 'sales@balochistanminerals.com';
  $mbList = function_exists('getCorporateMailboxOptions')
      ? getCorporateMailboxOptions()
      : [
          'sales@balochistanminerals.com' => ['user' => 'sales@balochistanminerals.com'],
          'info@balochistanminerals.com' => ['user' => 'info@balochistanminerals.com'],
          'support@balochistanminerals.com' => ['user' => 'support@balochistanminerals.com'],
        ];
  $cleanMailto = "mailto:" . rawurlencode($row['email'] ?? '') . "?subject=" . rawurlencode("Re: Inquiry for " . ($row['mineral_type'] ?? '') . " - Balochistan Minerals") . "&body=" . rawurlencode("Dear " . ($row['buyer_name'] ?? 'Buyer') . ",\n\nRegarding your inquiry for " . ($row['mineral_type'] ?? '') . "...\n\nBalochistan Minerals Export Desk");
  $defaultPlainReply = "Dear " . ($row['buyer_name'] ?? 'Buyer') . ",\n\n"
      . "Thank you for contacting Balochistan Minerals (Pvt) Ltd regarding your inquiry for " . ($row['mineral_type'] ?? 'minerals') . ".\n\n"
      . "We are pleased to provide you with our current commercial indicative terms for " . (!empty($row['quantity']) ? $row['quantity'] : 'your required tonnage') . ":\n\n"
      . "• Commodity: " . ($row['mineral_type'] ?? '') . ($resolvedGradeSpec !== '' ? " (" . $resolvedGradeSpec . ")" : "") . "\n"
      . "• Delivery Terms: " . $resolvedPortTerms . "\n"
      . "• Current Spot Indicative Rate: $" . number_format($effectiveSpotPrice, 2) . " USD / 1 Ton\n"
      . "• Packing: Bulk in vessel hold or 1.5 MT heavy-duty jumbo bags in 20ft containers\n"
      . "• Quality Control: SGS / Alfred H. Knight sampling & assay at Karachi port prior to loading\n\n"
      . "Please advise your target delivery timeline so we may issue our official Corporate Proforma Offer.\n\n"
      . "Sincerely,\nExport Commercial Desk\nBalochistan Minerals (Pvt) Ltd\nKarachi / Quetta, Pakistan\nWeb: https://balochistanminerals.com";
  $defaultHtmlReply = '<p>Dear ' . htmlspecialchars($row['buyer_name'] ?? 'Buyer') . ',</p>'
      . '<p>Thank you for contacting <strong>Balochistan Minerals (Pvt) Ltd</strong> regarding your inquiry for <strong>' . htmlspecialchars($row['mineral_type'] ?? 'minerals') . '</strong>.</p>'
      . '<p>We are pleased to provide our current commercial indicative terms for ' . htmlspecialchars(!empty($row['quantity']) ? $row['quantity'] : 'your required tonnage') . ':</p>'
      . '<ul>'
      . '<li><strong>Commodity:</strong> ' . htmlspecialchars($row['mineral_type'] ?? '') . ($resolvedGradeSpec !== '' ? ' (' . htmlspecialchars($resolvedGradeSpec) . ')' : '') . '</li>'
      . '<li><strong>Delivery Terms:</strong> ' . htmlspecialchars($resolvedPortTerms) . '</li>'
      . '<li><strong>Indicative Spot Rate:</strong> $' . number_format($effectiveSpotPrice, 2) . ' USD / MT</li>'
      . '<li><strong>Packing:</strong> Bulk in vessel hold or 1.5 MT heavy-duty jumbo bags in 20ft containers</li>'
      . '<li><strong>Quality Control:</strong> SGS / Alfred H. Knight sampling &amp; assay at load port prior to loading</li>'
      . '</ul>'
      . '<p>Please advise your target delivery schedule and discharge port so we may issue our formal Corporate Proforma Invoice.</p>'
      . '<p>Sincerely,<br><strong>Export Commercial Desk</strong><br>Balochistan Minerals (Pvt) Ltd &bull; Karachi / Quetta, Pakistan</p>';
?>
            <!-- Email Reply Modal (Executive Stretchable & Resizable Split View) -->
            <div class="modal fade text-start" id="replyModal<?= (int)$row['id'] ?>" tabindex="-1" aria-hidden="true"
                 data-reply-modal
                 data-inquiry-id="<?= (int)$row['id'] ?>"
                 data-buyer-name="<?= htmlspecialchars($row['buyer_name'] ?? '') ?>"
                 data-buyer-email="<?= htmlspecialchars($row['email'] ?? '') ?>"
                 data-mineral="<?= htmlspecialchars($row['mineral_type'] ?? '') ?>"
                 data-grade="<?= htmlspecialchars($resolvedGradeSpec) ?>"
                 data-quantity="<?= htmlspecialchars($resolvedQtyLabel) ?>"
                 data-port="<?= htmlspecialchars($resolvedPortTerms) ?>"
                 data-price="<?= number_format($effectiveSpotPrice, 2, '.', '') ?>">
              <div class="modal-dialog modal-dialog-centered modal-dialog-resizable modal-dialog-reply-wide">
                <div class="modal-content">
                  <form method="POST" action="dashboard.php" enctype="multipart/form-data" class="reply-modal-form" data-reply-form>
                    <input type="hidden" name="action" value="send_reply">
                    <input type="hidden" name="inquiry_id" value="<?= (int)$row['id'] ?>">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                    <input type="hidden" name="auto_log_submitted" value="1">
                    <input type="hidden" name="reply_html_body" value="" data-reply-html-input>
                    <textarea id="replyBody<?= (int)$row['id'] ?>" name="reply_body" class="d-none" data-reply-text-input><?= htmlspecialchars($defaultPlainReply) ?></textarea>

                    <div class="modal-header py-2 px-3">
                      <div class="d-flex align-items-center gap-2 min-w-0">
                        <span class="badge-rfq-mild">
                          <i class="bi bi-reply-fill"></i> RFQ #<?= (int)$row['id'] ?>
                        </span>
                        <h6 class="modal-title fw-bold brand-font mb-0 text-truncate" style="color: var(--brand-navy); font-size: 0.96rem;">
                          Reply to <?= htmlspecialchars($row['buyer_name'] ?? '') ?> <span class="text-muted fw-normal fs-6">(<?= htmlspecialchars($row['company_name'] ?: 'Buyer') ?>)</span>
                        </h6>
                      </div>
                      <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>

                    <div class="modal-body p-3">
                      <div class="row g-3 reply-split-row">
                        <!-- Left Column: Inbound Inquiry & Rich Email Viewer (Mirrors Dossier) -->
                        <div class="col-md-5 border-end pe-md-3 reply-left-col">
                          <div class="d-flex align-items-center justify-content-between text-uppercase fw-bold text-muted mb-2 pb-1 border-bottom" style="font-size: 0.70rem; letter-spacing: 0.05em;">
                            <span><i class="bi bi-inbox-fill text-primary me-1.5"></i> Buyer Inquiry Context</span>
                            <button type="button" class="btn btn-link btn-sm p-0 text-decoration-none fw-semibold" style="font-size: 0.70rem; text-transform: none; letter-spacing: 0;" onclick="var m = bootstrap.Modal.getInstance(this.closest('.modal')); if(m) m.hide(); if(typeof openBuyerDossier === 'function') openBuyerDossier(<?= (int)$row['id'] ?>);">
                              <i class="bi bi-layout-sidebar-inset-reverse me-1"></i>Open 360° Dossier
                            </button>
                          </div>

                          <div class="p-2.5 rounded bg-light border mb-2 flex-shrink-0">
                            <div class="d-flex justify-content-between align-items-start gap-2">
                              <div class="min-w-0">
                                <div class="fw-bold text-dark text-truncate" style="font-size: 0.88rem;"><?= htmlspecialchars($row['buyer_name'] ?? '') ?></div>
                                <div class="small text-muted text-truncate"><?= htmlspecialchars($row['company_name'] ?: 'Independent Buyer') ?> &bull; <?= htmlspecialchars($resolvedCountry) ?></div>
                              </div>
                              <span class="badge bg-white text-dark border font-monospace flex-shrink-0" style="font-size: 0.67rem;"><?= date('M d, Y', strtotime($row['created_at'])) ?></span>
                            </div>

                            <div class="mt-2 pt-1.5 border-top d-flex align-items-center justify-content-between gap-2 flex-wrap">
                              <span class="text-muted text-uppercase fw-bold" style="font-size: 0.63rem;">Landed Box</span>
                              <span class="font-monospace fw-bold text-dark" style="font-size: 0.74rem;">
                                <i class="bi bi-mailbox2 text-primary me-1"></i><?= !empty($row['source']) && strpos($row['source'], '@') !== false ? htmlspecialchars($row['source']) : 'sales@balochistanminerals.com' ?>
                              </span>
                            </div>

                            <div class="mt-1.5 pt-1.5 border-top reply-ctx-specs">
                              <div class="d-flex justify-content-between small">
                                <span class="text-muted">Commodity:</span>
                                <span class="fw-bold text-dark"><?= htmlspecialchars($row['mineral_type'] ?? '') ?></span>
                              </div>
                              <div class="d-flex justify-content-between small mt-0.5">
                                <span class="text-muted">Port / Benchmark:</span>
                                <span class="fw-bold text-dark"><?= htmlspecialchars($resolvedPortTerms) ?> &bull; <span class="text-success font-monospace">$<?= number_format($effectiveSpotPrice, 2) ?>/MT</span></span>
                              </div>
                            </div>
                          </div>

                          <div class="reply-msg-wrap">
                            <div class="d-flex align-items-center justify-content-between gap-2 mb-1.5 flex-shrink-0">
                              <label class="form-label mb-0">Original Buyer Message</label>
                              <div class="reply-view-pills" role="tablist" aria-label="Message view mode">
                                <button type="button" class="reply-view-pill active" data-view-mode="rich">Rich Email</button>
                                <button type="button" class="reply-view-pill" data-view-mode="text">Plain Text</button>
                                <button type="button" class="reply-view-pill" data-view-mode="thread">Thread (<span data-thread-count><?= max(1, (int)($row['email_count'] ?? 1)) ?></span>)</button>
                              </div>
                            </div>
                            <div class="p-2.5 rounded bg-white border small thread-rich-body reply-original-msg-box" data-reply-left-box>
                              <div data-pane="rich">
                                <?php
                                  if (function_exists('formatPlainTextAsCleanHtml')) {
                                      echo formatPlainTextAsCleanHtml($row['message'] ?: 'No message body recorded with this inquiry.');
                                  } else {
                                      echo nl2br(htmlspecialchars($row['message'] ?: 'No message body recorded with this inquiry.'));
                                  }
                                ?>
                              </div>
                              <div data-pane="text" class="d-none">
                                <?php
                                  if (function_exists('formatPlainTextAsCleanHtml')) {
                                      echo formatPlainTextAsCleanHtml($row['message'] ?: 'No message body recorded with this inquiry.');
                                  } else {
                                      echo nl2br(htmlspecialchars($row['message'] ?: 'No message body recorded with this inquiry.'));
                                  }
                                ?>
                              </div>
                              <div data-pane="thread" class="d-none text-muted small py-2">Loading full conversation thread...</div>
                            </div>
                          </div>

                          <?php if (!empty($row['internal_notes'])): ?>
                            <div class="mt-2 flex-shrink-0">
                              <label class="form-label mb-1">Previous Interaction Timeline</label>
                              <div class="p-2 rounded bg-light border font-monospace text-muted" style="max-height: 76px; overflow-y: auto; font-size: 0.67rem; line-height: 1.35; white-space: pre-wrap;">
<?= htmlspecialchars($row['internal_notes']) ?>
                              </div>
                            </div>
                          <?php endif; ?>
                        </div>

                        <!-- Right Column: Upgraded Executive Rich Email Composer -->
                        <div class="col-md-7 ps-md-3 reply-right-col">
                          <div class="reply-compose-main">
                            <div class="d-flex align-items-center justify-content-between text-uppercase fw-bold text-muted mb-1.5 pb-1 border-bottom flex-shrink-0" style="font-size: 0.70rem; letter-spacing: 0.05em;">
                              <span><i class="bi bi-envelope-paper-fill text-warning me-1.5"></i> Compose Official Reply</span>
                              <div class="d-flex align-items-center gap-2" style="text-transform: none; letter-spacing: 0;">
                                <span class="reply-draft-pill d-none" data-draft-badge>Draft restored &bull; <button type="button" class="btn btn-link btn-sm p-0 text-decoration-underline" data-reset-draft style="font-size: 0.68rem;">Reset</button></span>
                                <span class="reply-smtp-pill" data-smtp-badge>Checking SMTP...</span>
                              </div>
                            </div>

                            <div class="row g-2 mb-1.5 flex-shrink-0">
                              <div class="col-md-5">
                                <label class="form-label mb-1">From Mailbox</label>
                                <select name="from_email" class="form-select form-select-sm font-monospace fw-bold" data-from-select>
                                  <?php foreach ($mbList as $mbMeta):
                                    $mbKey = strtolower(trim($mbMeta['user']));
                                    $hasPass = !empty($configuredBoxes[$mbKey]['pass']);
                                  ?>
                                    <option value="<?= htmlspecialchars($mbMeta['user']) ?>" data-smtp-ready="<?= $hasPass ? '1' : '0' ?>" <?= $rowMailbox === $mbMeta['user'] ? 'selected' : '' ?>><?= htmlspecialchars($mbMeta['user']) ?></option>
                                  <?php endforeach; ?>
                                </select>
                              </div>
                              <div class="col-md-7">
                                <div class="d-flex align-items-center justify-content-between mb-1">
                                  <label class="form-label mb-0">To Recipient</label>
                                  <div class="d-flex align-items-center gap-1.5">
                                    <button type="button" class="reply-cc-toggle" data-toggle-cc>+ CC</button>
                                    <button type="button" class="reply-cc-toggle" data-toggle-bcc>+ BCC</button>
                                  </div>
                                </div>
                                <input type="email" name="to_email" class="form-control form-control-sm font-monospace fw-bold" value="<?= htmlspecialchars($row['email'] ?? '') ?>" required>
                              </div>
                            </div>

                            <!-- Optional CC / BCC Row -->
                            <div class="row g-2 mb-1.5 d-none flex-shrink-0" data-cc-bcc-row>
                              <div class="col d-none" data-cc-col>
                                <input type="text" name="cc_email" class="form-control form-control-sm font-monospace" placeholder="CC emails (comma-separated)..." data-cc-input>
                              </div>
                              <div class="col d-none" data-bcc-col>
                                <input type="text" name="bcc_email" class="form-control form-control-sm font-monospace" placeholder="BCC emails (comma-separated)..." data-bcc-input>
                              </div>
                            </div>

                            <div class="row g-2 mb-1.5 flex-shrink-0">
                              <div class="col-md-7">
                                <label class="form-label mb-1">Subject Line</label>
                                <input type="text" name="subject" class="form-control form-control-sm fw-semibold" value="Re: Inquiry for <?= htmlspecialchars($row['mineral_type'] ?? '') ?> - Balochistan Minerals (Pvt) Ltd" data-subject-input required>
                              </div>
                              <div class="col-md-5">
                                <label class="form-label mb-1">B2B Template</label>
                                <select class="form-select form-select-sm" data-template-select>
                                  <option value="quote">1. Formal Spot Quotation</option>
                                  <option value="assay">2. SGS Assay &amp; COA Specs</option>
                                  <option value="lc">3. L/C &amp; SBP Banking Terms</option>
                                  <option value="sample">4. Trial Sample Dispatch</option>
                                  <option value="custom">5. Custom Executive Note</option>
                                </select>
                              </div>
                            </div>

                            <!-- Visual Rich Editor & Live Preview Container -->
                            <div class="reply-body-field-wrap">
                              <div class="reply-editor-toolbar" role="toolbar" aria-label="Formatting and Email Preview">
                                <div class="d-flex align-items-center gap-1 flex-wrap" data-format-buttons>
                                  <button type="button" class="reply-tool-btn" data-cmd="bold" title="Bold"><i class="bi bi-type-bold"></i></button>
                                  <button type="button" class="reply-tool-btn" data-cmd="italic" title="Italic"><i class="bi bi-type-italic"></i></button>
                                  <button type="button" class="reply-tool-btn" data-cmd="insertUnorderedList" title="Bullet List"><i class="bi bi-list-ul"></i></button>
                                  <button type="button" class="reply-tool-btn reply-tool-label" data-insert-spec-table title="Insert Structured Commodity Spec & Rate Table"><i class="bi bi-table"></i> Spec Table</button>
                                  <button type="button" class="reply-tool-btn reply-tool-label" data-insert-quote title="Insert Quoted Buyer Message"><i class="bi bi-chat-quote"></i> Quote Msg</button>
                                  <button type="button" class="reply-tool-btn" data-cmd="removeFormat" title="Clear formatting"><i class="bi bi-eraser"></i></button>
                                </div>
                                <div class="reply-view-pills">
                                  <button type="button" class="reply-view-pill active" data-compose-mode="write">Write</button>
                                  <button type="button" class="reply-view-pill" data-compose-mode="preview"><i class="bi bi-eye me-1"></i>Preview Email</button>
                                </div>
                              </div>

                              <div class="reply-rich-editor" contenteditable="true" data-rich-editor data-default-html="<?= htmlspecialchars($defaultHtmlReply, ENT_QUOTES, 'UTF-8') ?>"><?= $defaultHtmlReply ?></div>
                              <div class="reply-email-preview-frame d-none" data-email-preview></div>
                            </div>

                            <!-- Modern Drag-and-Drop Multi-File Attachment Bar -->
                            <div class="reply-attach-dropzone mt-1.5 flex-shrink-0" data-reply-dropzone>
                              <input type="file" name="attachments[]" multiple accept=".pdf,.doc,.docx,.xls,.xlsx,.csv,.txt,.jpg,.jpeg,.png,.gif,.webp,.zip" class="d-none" data-reply-file-input>
                              <div class="d-flex align-items-center justify-content-between gap-2 flex-wrap">
                                <div class="d-flex align-items-center gap-2">
                                  <button type="button" class="btn btn-sm btn-outline-secondary py-0.5 px-2 d-inline-flex align-items-center gap-1" style="font-size: 0.72rem; background: #FFFFFF; border-color: #CBD5E1; color: #0F172A;" data-pick-files>
                                    <i class="bi bi-paperclip text-primary"></i> Attach Documents
                                  </button>
                                  <a href="generate-quote.php?id=<?= (int)$row['id'] ?>" target="_blank" class="btn btn-sm btn-outline-secondary py-0.5 px-2 d-inline-flex align-items-center gap-1" style="font-size: 0.72rem; background: #FFFFFF; border-color: #CBD5E1; color: #0F172A;" title="Open Proforma Quote to save/print PDF">
                                    <i class="bi bi-file-earmark-pdf text-danger"></i> Proforma PDF
                                  </a>
                                </div>
                                <span class="text-muted font-monospace" style="font-size: 0.67rem;" data-att-meta>Drop files here &bull; Max 10 files (20 MB)</span>
                              </div>
                              <div class="reply-att-chips" data-reply-att-list></div>
                            </div>
                          </div>
                        </div>
                      </div>
                    </div>

                    <div class="modal-footer d-flex justify-content-between align-items-center py-2 px-3 gap-2 flex-wrap">
                      <div class="d-flex align-items-center gap-3 flex-wrap">
                        <label class="form-check-label d-inline-flex align-items-center gap-1.5 small fw-semibold text-dark mb-0" style="font-size: 0.73rem; cursor: pointer;">
                          <input type="checkbox" name="auto_log" value="1" class="form-check-input my-0" checked style="width: 0.95rem; height: 0.95rem;">
                          <span>Auto-log to Dossier &amp; mark Contacted</span>
                        </label>
                        <a href="<?= $cleanMailto ?>" class="text-muted text-decoration-underline small" style="font-size: 0.71rem;" title="Open reply in local desktop email client">
                          <i class="bi bi-box-arrow-up-right me-0.5"></i>External Mail App
                        </a>
                      </div>
                      <div class="d-flex align-items-center gap-2 ms-auto">
                        <button type="button" class="btn btn-sm btn-outline-secondary px-3 py-1.5" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-sm btn-dark px-3.5 py-1.5 fw-bold text-white shadow-sm" style="background: #0F172A; border-color: #0F172A; border-radius: 6px;">
                          <i class="bi bi-send-fill me-1"></i> Send Official Reply
                        </button>
                      </div>
                    </div>
                  </form>
                </div>
              </div>
            </div>

            <!-- Edit Lead Modal -->
            <div class="modal fade text-start" id="editModal<?= (int)$row['id'] ?>" tabindex="-1" aria-hidden="true">
              <div class="modal-dialog modal-dialog-centered modal-lg">
                <div class="modal-content">
                  <form method="POST" action="dashboard.php" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="update_inquiry">
                    <input type="hidden" name="inquiry_id" value="<?= (int)$row['id'] ?>">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">

                    <div class="modal-header">
                      <h6 class="modal-title fw-bold brand-font" style="color: var(--brand-navy);">
                        Buyer Dossier #<?= (int)$row['id'] ?>: <?= htmlspecialchars($row['buyer_name'] ?? '') ?>
                      </h6>
                      <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>

                    <div class="modal-body">
                      <div class="row g-3 p-3 bg-light rounded-2 mb-3">
                        <div class="col-md-6">
                          <div class="text-muted small">Company / Country</div>
                          <div class="fw-bold"><?= htmlspecialchars($row['company_name'] ?: 'Private Trader') ?> (<?= htmlspecialchars($resolvedCountry) ?>)</div>
                          <div class="small text-muted"><i class="bi bi-envelope"></i> <?= htmlspecialchars($row['email'] ?? '') ?></div>
                          <div class="small text-muted"><i class="bi bi-whatsapp text-success"></i> <?= htmlspecialchars($row['whatsapp'] ?? '') ?></div>
                          <div class="small text-muted mt-2 p-2 bg-white rounded border">
                            <div class="text-uppercase fw-bold text-muted" style="font-size: 0.68rem; letter-spacing: 0.05em;"><i class="bi bi-mailbox2 me-1 text-primary"></i> Intake Route & Landing Box:</div>
                            <div class="font-monospace fw-bold mt-1" style="color: #0F172A; font-size: 0.8rem;">
                              <?php if (!empty($row['source']) && strpos($row['source'], '@') !== false): ?>
                                <i class="bi bi-envelope-check-fill text-success me-1"></i>Delivered to Hostinger Mailbox: <span class="badge bg-dark"><?= htmlspecialchars($row['source']) ?></span>
                              <?php elseif (!empty($row['source']) && $row['source'] === 'Direct Email Modal'): ?>
                                <i class="bi bi-chat-left-dots-fill text-warning me-1"></i>Website Direct Desk ➔ <span class="badge bg-dark">sales@balochistanminerals.com</span>
                              <?php else: ?>
                                <i class="bi bi-globe text-secondary me-1"></i><?= htmlspecialchars($row['source'] ?? '') ?>
                              <?php endif; ?>
                            </div>
                          </div>
                        </div>
                        <div class="col-md-6">
                          <div class="text-muted small">Commodity Requested</div>
                          <div class="fw-bold text-dark"><?= htmlspecialchars($row['mineral_type'] ?? '') ?></div>
                          <div class="small">Quantity: <strong><?= htmlspecialchars($row['quantity'] ?: 'Open') ?></strong></div>
                          <div class="small">Port: <strong><?= htmlspecialchars($row['destination_port'] ?: 'FOB Karachi') ?></strong></div>
                        </div>
                        <div class="col-12 mt-2">
                          <div class="text-muted small mb-1">Inbound Message:</div>
                          <div class="p-2 bg-white rounded border small" style="white-space: pre-wrap;"><?= htmlspecialchars($row['message'] ?: 'No message provided.') ?></div>
                        </div>
                      </div>

                      <div class="row g-3">
                        <div class="col-md-6">
                          <label class="form-label fw-bold small">Commercial Deal Stage</label>
                          <select name="status" class="form-select form-select-sm">
                            <option value="New" <?= ($row['status'] ?? '') === 'New' ? 'selected' : '' ?>>New</option>
                            <option value="Contacted" <?= ($row['status'] ?? '') === 'Contacted' ? 'selected' : '' ?>>Contacted (Discussion on WA)</option>
                            <option value="Assay Sent" <?= ($row['status'] ?? '') === 'Assay Sent' ? 'selected' : '' ?>>Assay / Lab Certificate Sent</option>
                            <option value="Quoted" <?= ($row['status'] ?? '') === 'Quoted' ? 'selected' : '' ?>>Spot Quoted</option>
                            <option value="Sample Sent" <?= ($row['status'] ?? '') === 'Sample Sent' ? 'selected' : '' ?>>Sample Dispatched</option>
                            <option value="Contract Signed" <?= ($row['status'] ?? '') === 'Contract Signed' ? 'selected' : '' ?>>Contract Signed / L/C Opened</option>
                            <option value="Closed" <?= ($row['status'] ?? '') === 'Closed' ? 'selected' : '' ?>>Closed (Completed)</option>
                            <option value="Archived" <?= ($row['status'] ?? '') === 'Archived' ? 'selected' : '' ?>>Archived</option>
                          </select>
                        </div>
                        <div class="col-md-6">
                          <label class="form-label fw-bold small">Spot Price ($ USD / Metric Ton)</label>
                          <div class="input-group input-group-sm">
                            <span class="input-group-text">$</span>
                            <input type="number" step="0.01" name="spot_price" class="form-control" placeholder="185.00" value="<?= htmlspecialchars((string)($row['spot_price_quoted'] ?? '')) ?>">
                            <span class="input-group-text">USD / MT</span>
                          </div>
                        </div>
                        <div class="col-12">
                          <label class="form-label fw-bold small">Internal Trade Desk Notes (Private)</label>
                          <textarea name="internal_notes" class="form-control form-control-sm" rows="3" placeholder="Private notes on trucking, weighbridge results, L/C bank requirements, etc."><?= htmlspecialchars($row['internal_notes'] ?? '') ?></textarea>
                        </div>
                      </div>
                    </div>

                    <div class="modal-footer d-flex justify-content-between">
                      <button type="button" class="btn btn-sm btn-outline-danger" onclick="if(confirm('Delete inquiry permanently?')) { document.getElementById('delForm<?= (int)$row['id'] ?>').submit(); }">
                        <i class="bi bi-trash"></i> Delete
                      </button>
                      <div class="d-flex gap-2">
                        <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-sm btn-dark" style="background: var(--brand-navy);">Save Changes</button>
                      </div>
                    </div>
                  </form>

                  <form id="delForm<?= (int)$row['id'] ?>" method="POST" action="dashboard.php" style="display:none;">
                    <input type="hidden" name="action" value="delete_inquiry">
                    <input type="hidden" name="inquiry_id" value="<?= (int)$row['id'] ?>">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                  </form>
                </div>
              </div>
            </div>

          </td>
        </tr>
        <?php
        return ob_get_clean();
    }
}
