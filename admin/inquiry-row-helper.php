<?php
/**
 * Balochistan Minerals - Executive RFQ Table Row & Modal Component Generator
 * Shared between dashboard.php and api-live-poll.php for zero markup drift.
 */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/flag-helper.php';

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

            <!-- Email Reply Modal (Executive Stretchable & Resizable Split View) -->
            <div class="modal fade text-start" id="replyModal<?= (int)$row['id'] ?>" tabindex="-1" aria-hidden="true">
              <div class="modal-dialog modal-dialog-centered modal-dialog-resizable" style="max-width: 1040px;">
                <div class="modal-content">
                  <form method="POST" action="dashboard.php" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="send_reply">
                    <input type="hidden" name="inquiry_id" value="<?= (int)$row['id'] ?>">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">

                    <div class="modal-header py-2.5 px-3">
                      <div class="d-flex align-items-center gap-2">
                        <span class="badge-rfq-mild">
                          <i class="bi bi-reply-fill"></i> RFQ #<?= (int)$row['id'] ?>
                        </span>
                        <h6 class="modal-title fw-bold brand-font mb-0" style="color: var(--brand-navy); font-size: 0.98rem;">
                          Reply to <?= htmlspecialchars($row['buyer_name'] ?? '') ?> <span class="text-muted fw-normal fs-6">(<?= htmlspecialchars($row['company_name'] ?: 'Buyer') ?>)</span>
                        </h6>
                      </div>
                      <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>

                    <div class="modal-body p-3 p-md-3.5">
                      <div class="row g-3">
                        <!-- Left Column: Inbound Inquiry & Thread Context -->
                        <div class="col-md-5 border-end pe-md-3">
                          <div class="d-flex align-items-center text-uppercase fw-bold text-muted mb-2 pb-1 border-bottom" style="font-size: 0.70rem; letter-spacing: 0.05em;">
                            <i class="bi bi-inbox-fill text-primary me-1.5"></i> Buyer Inquiry Context
                          </div>

                          <div class="p-2.5 rounded bg-light border mb-2.5">
                            <div class="d-flex justify-content-between align-items-start">
                              <div>
                                <div class="fw-bold text-dark" style="font-size: 0.88rem;"><?= htmlspecialchars($row['buyer_name'] ?? '') ?></div>
                                <div class="small text-muted"><?= htmlspecialchars($row['company_name'] ?: 'Independent Buyer') ?> • <?= htmlspecialchars($resolvedCountry) ?></div>
                              </div>
                              <span class="badge bg-white text-dark border font-monospace" style="font-size: 0.68rem;"><?= date('M d, Y', strtotime($row['created_at'])) ?></span>
                            </div>

                            <div class="mt-2 pt-2 border-top">
                              <div class="text-xs fw-bold text-muted text-uppercase" style="font-size: 0.65rem;">Landed In Mailbox:</div>
                              <div class="font-monospace fw-bold small text-dark mt-0.5">
                                <i class="bi bi-mailbox2 text-primary me-1"></i><?= !empty($row['source']) && strpos($row['source'], '@') !== false ? htmlspecialchars($row['source']) : 'sales@balochistanminerals.com' ?>
                              </div>
                            </div>

                            <div class="mt-2 pt-2 border-top">
                              <div class="d-flex justify-content-between small">
                                <span class="text-muted">Commodity:</span>
                                <span class="fw-bold text-dark"><?= htmlspecialchars($row['mineral_type'] ?? '') ?></span>
                              </div>
                              <div class="d-flex justify-content-between small mt-1">
                                <span class="text-muted">Port / Terms:</span>
                                <span class="fw-bold text-dark"><?= htmlspecialchars($row['destination_port'] ?: 'FOB Karachi') ?></span>
                              </div>
                              <?php if (!empty($row['spot_price_quoted'])): ?>
                                <div class="d-flex justify-content-between small mt-1">
                                  <span class="text-muted">Spot Quoted:</span>
                                  <span class="fw-bold text-success font-monospace">$<?= number_format((float)$row['spot_price_quoted'], 2) ?> USD</span>
                                </div>
                              <?php endif; ?>
                            </div>
                          </div>

                          <div>
                            <label class="form-label mb-1">Original Buyer Message</label>
                            <div class="p-2.5 rounded bg-white border small thread-rich-body" style="max-height: 220px; overflow-y: auto; font-size: 0.78rem; line-height: 1.5; color: #334155;">
<?php
  if (function_exists('formatPlainTextAsCleanHtml')) {
      echo formatPlainTextAsCleanHtml($row['message'] ?: 'No message body recorded with this inquiry.');
  } else {
      echo nl2br(htmlspecialchars($row['message'] ?: 'No message body recorded with this inquiry.'));
  }
?>
                            </div>
                          </div>

                          <?php if (!empty($row['internal_notes'])): ?>
                            <div class="mt-2">
                              <label class="form-label mb-1">Previous Interaction Timeline</label>
                              <div class="p-2 rounded bg-light border font-monospace text-muted" style="max-height: 90px; overflow-y: auto; font-size: 0.68rem; line-height: 1.35; white-space: pre-wrap;">
<?= htmlspecialchars($row['internal_notes']) ?>
                              </div>
                            </div>
                          <?php endif; ?>
                        </div>

                        <!-- Right Column: Compose & Dispatch Engine -->
                        <div class="col-md-7 ps-md-3 d-flex flex-column justify-content-between">
                          <div>
                            <div class="d-flex align-items-center justify-content-between text-uppercase fw-bold text-muted mb-2 pb-1 border-bottom" style="font-size: 0.70rem; letter-spacing: 0.05em;">
                              <span><i class="bi bi-envelope-paper-fill text-warning me-1.5"></i> Compose Official Reply</span>
                              <span class="badge bg-light text-dark border font-monospace" style="font-size: 0.67rem;">Hostinger SMTP Relay</span>
                            </div>

                            <div class="row g-2 mb-2">
                              <div class="col-6">
                                <label class="form-label mb-1">From Mailbox</label>
                                <select name="from_email" class="form-select form-select-sm font-monospace fw-bold">
                                  <?php
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
                                    foreach ($mbList as $mbMeta):
                                  ?>
                                    <option value="<?= htmlspecialchars($mbMeta['user']) ?>" <?= $rowMailbox === $mbMeta['user'] ? 'selected' : '' ?>><?= htmlspecialchars($mbMeta['user']) ?></option>
                                  <?php endforeach; ?>
                                </select>
                              </div>
                              <div class="col-6">
                                <label class="form-label mb-1">To Recipient</label>
                                <input type="email" name="to_email" class="form-control form-control-sm font-monospace fw-bold" value="<?= htmlspecialchars($row['email'] ?? '') ?>" required>
                              </div>
                            </div>

                            <div class="mb-2">
                              <label class="form-label mb-1">Subject Line</label>
                              <input type="text" name="subject" class="form-control form-control-sm fw-semibold" value="Re: Inquiry for <?= htmlspecialchars($row['mineral_type'] ?? '') ?> - Balochistan Minerals (Pvt) Ltd" required>
                            </div>

                            <div class="mb-2">
                              <div class="d-flex justify-content-between align-items-center mb-1">
                                <label class="form-label mb-0">Select B2B Commodity Template</label>
                                <span class="text-muted" style="font-size: 0.68rem;">Auto-fills body below</span>
                              </div>
                              <select class="form-select form-select-sm" onchange="loadReplyTemplate(this, <?= (int)$row['id'] ?>)">
                                <option value="quote">Template 1: Formal Price Quotation & FOB Karachi Terms</option>
                                <option value="assay">Template 2: SGS Chemical Assay & Purity Verification</option>
                                <option value="lc">Template 3: Payment Terms & SBP Letter of Credit (L/C)</option>
                                <option value="custom">Template 4: Custom Executive Message</option>
                              </select>
                            </div>

                            <div class="mb-1">
                              <label class="form-label mb-1">Message Body</label>
                              <textarea id="replyBody<?= (int)$row['id'] ?>" name="reply_body" class="form-control form-control-sm font-monospace" rows="8" required style="font-size: 0.80rem; line-height: 1.45;">Dear <?= htmlspecialchars($row['buyer_name'] ?? 'Buyer') ?>,

Thank you for contacting Balochistan Minerals (Pvt) Ltd regarding your inquiry for <?= htmlspecialchars($row['mineral_type'] ?? 'minerals') ?>.

We are pleased to provide you with our current commercial indicative terms for <?= htmlspecialchars($row['quantity'] ?: 'your required tonnage') ?>:

• Commodity: <?= htmlspecialchars($row['mineral_type'] ?? '') ?><?= !empty($row['grade_spec']) ? ' (' . htmlspecialchars($row['grade_spec']) . ')' : '' ?>

• Delivery Terms: <?= htmlspecialchars($row['destination_port'] ?: 'FOB Karachi Port (KPT / QICT)') ?>

• Current Spot Indicative Rate: $<?= !empty($row['spot_price_quoted']) ? number_format((float)$row['spot_price_quoted'], 2) : '185.00' ?> USD / 1 Ton
• Packing: Bulk in vessel hold or 1.5 MT heavy-duty jumbo bags in 20ft containers
• Quality Control: SGS / Alfred H. Knight sampling & assay at Karachi port prior to loading

Please advise your target delivery timeline so we may issue our official Corporate Proforma Offer.

Sincerely,
Export Commercial Desk
Balochistan Minerals (Pvt) Ltd
Karachi / Quetta, Pakistan
Web: https://balochistanminerals.com</textarea>
                            </div>

                            <div class="mb-2">
                              <label class="form-label mb-1">Attach Documents (PDF, Word, Excel, Images, ZIP)</label>
                              <input type="file" name="attachments[]" multiple accept=".pdf,.doc,.docx,.xls,.xlsx,.csv,.txt,.jpg,.jpeg,.png,.gif,.webp,.zip" class="form-control form-control-sm">
                              <div class="form-text" style="font-size: 0.67rem;">Optional • Up to 10 files (Max 10 MB/file, 20 MB total)</div>
                            </div>

                            <?php
                              $cleanMailto = "mailto:" . rawurlencode($row['email'] ?? '') . "?subject=" . rawurlencode("Re: Inquiry for " . ($row['mineral_type'] ?? '') . " - Balochistan Minerals") . "&body=" . rawurlencode("Dear " . ($row['buyer_name'] ?? 'Buyer') . ",\n\nRegarding your inquiry for " . ($row['mineral_type'] ?? '') . "...\n\nBalochistan Minerals Export Desk");
                            ?>
                            <div class="text-muted text-end mt-1" style="font-size: 0.68rem;">
                              Prefer external mail client? <a href="<?= $cleanMailto ?>" class="text-decoration-underline text-secondary">Open in Outlook / Apple Mail</a>
                            </div>
                          </div>

                          <div class="form-check p-2 bg-light rounded border d-flex align-items-center mb-0 mt-2">
                            <input type="checkbox" class="form-check-input ms-0 me-2 my-0" checked disabled style="width: 1rem; height: 1rem;">
                            <label class="form-check-label small fw-semibold text-dark mb-0" style="font-size: 0.72rem;">
                              Auto-log email transcript into Buyer Dossier & advance stage to "Contacted"
                            </label>
                          </div>
                        </div>
                      </div>
                    </div>

                    <div class="modal-footer d-flex justify-content-between py-2 px-3">
                      <div class="small text-muted font-monospace" style="font-size: 0.72rem;">
                        <i class="bi bi-shield-check text-success me-1"></i>Hostinger SMTP Relay Active
                      </div>
                      <div class="d-flex gap-2">
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
