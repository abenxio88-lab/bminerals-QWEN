<?php
/**
 * Balochistan Minerals - Commercial Invoice & Detailed Packing List Print Generator
 * Populated directly from verified Stage-Measured Deal metrics for L/C & PSW Customs filing.
 */

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/deal-helpers.php';
$docType = ($_GET['doc'] ?? 'invoice') === 'packing' ? 'packing' : 'invoice';
if ($docType === 'invoice') {
    requirePermission('view_deal_financials');
} else {
    requirePermission('view_deals');
}

$pdo = getDB();
if (!$pdo) {
    die('Database connection unavailable.');
}
ensureDealVaultSchema($pdo);

$dealId = (int)($_GET['id'] ?? 0);

$stmt = $pdo->prepare("SELECT * FROM `deals` WHERE id = ? LIMIT 1");
$stmt->execute([$dealId]);
$deal = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$deal) {
    die('Export deal not found.');
}

$mStmt = $pdo->prepare("SELECT metric_key, metric_label, metric_value, unit FROM `deal_measurements` WHERE deal_id = ?");
$mStmt->execute([$dealId]);
$metrics = [];
$assayLines = [];
foreach ($mStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $metrics[$row['metric_key']] = $row;
    if (strpos($row['metric_key'], 'assay_') === 0 && trim((string)$row['metric_value']) !== '') {
        $assayLines[] = $row['metric_label'] . ': ' . $row['metric_value'] . ($row['unit'] ? ' ' . $row['unit'] : '');
    }
}

$val = function ($key, $fallback = 'TBD') use ($metrics) {
    $v = trim((string)($metrics[$key]['metric_value'] ?? ''));
    return $v !== '' ? $v : $fallback;
};

$dryMt   = (float)($metrics['dry_mt']['metric_value'] ?? 0);
$wetMt   = (float)($metrics['wet_mt']['metric_value'] ?? ($metrics['yard_weighbridge_mt']['metric_value'] ?? $deal['contracted_mt']));
$moist   = $val('moisture_pct', '0.00');
$calcMt  = $dryMt > 0 ? $dryMt : ($wetMt > 0 ? $wetMt : (float)$deal['contracted_mt']);
$unitPx  = (float)$deal['unit_price_usd'];
$totalUsd = $calcMt * $unitPx;
$docTitle = $docType === 'packing' ? 'DETAILED PACKING LIST' : 'COMMERCIAL INVOICE';
$docRef   = ($docType === 'packing' ? 'BM-PL-' : 'BM-CI-') . date('Y') . '-' . str_pad((string)$deal['id'], 3, '0', STR_PAD_LEFT);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($docTitle . ' — ' . $deal['deal_code']) ?></title>
  <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@600;700;800&family=Poppins:wght@400;500;600&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <style>
    :root { --brand-navy: #0F172A; --brand-gold: #B48C36; }
    body { font-family: 'Poppins', sans-serif; background: #E2E8F0; color: #1E293B; padding: 24px 12px; }
    .action-bar { max-width: 860px; margin: 0 auto 14px; display: flex; justify-content: space-between; align-items: center; gap: 10px; flex-wrap: wrap; }
    .doc-sheet { max-width: 860px; margin: 0 auto; background: #FFF; padding: 44px 48px; border-radius: 6px; border: 1px solid #CBD5E1; box-shadow: 0 10px 30px rgba(15, 23, 42, 0.08); }
    .brand-font { font-family: 'Montserrat', sans-serif; }
    .section-box { background: #F8FAFC; border: 1px solid #E2E8F0; border-radius: 6px; padding: 14px 16px; height: 100%; }
    .section-label { font-size: 0.68rem; font-weight: 700; letter-spacing: 0.08em; text-transform: uppercase; color: #64748B; margin-bottom: 6px; }
    table.trade-table th { background: #0F172A; color: #FFF; font-size: 0.74rem; text-transform: uppercase; letter-spacing: 0.04em; padding: 10px 12px; border: 1px solid #0F172A; }
    table.trade-table td { font-size: 0.84rem; padding: 12px; border: 1px solid #CBD5E1; vertical-align: top; }
    @media print {
      body { background: #FFF; padding: 0; }
      .no-print { display: none !important; }
      .doc-sheet { border: none; box-shadow: none; padding: 18px 20px; max-width: 100%; }
    }
  </style>
</head>
<body>

  <div class="action-bar no-print">
    <div class="d-flex gap-2">
      <a href="deals.php?id=<?= (int)$deal['id'] ?>" class="btn btn-sm btn-light border text-dark">
        <i class="bi bi-arrow-left me-1"></i> Back to Deal Vault
      </a>
      <a href="?id=<?= (int)$deal['id'] ?>&doc=invoice" class="btn btn-sm <?= $docType === 'invoice' ? 'btn-dark' : 'btn-light border text-dark' ?>">Commercial Invoice</a>
      <a href="?id=<?= (int)$deal['id'] ?>&doc=packing" class="btn btn-sm <?= $docType === 'packing' ? 'btn-dark' : 'btn-light border text-dark' ?>">Packing List</a>
    </div>
    <button onclick="window.print()" class="btn btn-sm btn-dark fw-semibold px-3">
      <i class="bi bi-printer me-1"></i> Print / Save as PDF
    </button>
  </div>

  <div class="doc-sheet">
    <div class="d-flex justify-content-between align-items-start pb-3 mb-4" style="border-bottom: 3px solid #0F172A;">
      <div>
        <img src="../images/new-logo.png" alt="Balochistan Minerals" style="max-height: 46px;" class="mb-2" onerror="this.style.display='none'">
        <div class="brand-font fw-bold fs-5 text-dark">BALOCHISTAN MINERALS (PVT) LTD</div>
        <div class="small text-muted">Export Commercial & Port Operations • Quetta & Karachi Port, Pakistan</div>
        <div class="small text-muted">Email: sales@balochistanminerals.com • Tel / WhatsApp: +92 334 8888104</div>
      </div>
      <div class="text-end">
        <div class="brand-font fw-bold fs-4" style="color: #0F172A;"><?= htmlspecialchars($docTitle) ?></div>
        <div class="font-monospace small fw-bold text-primary"><?= htmlspecialchars($docRef) ?></div>
        <div class="small text-muted mt-1">Date: <?= date('d M Y') ?></div>
        <div class="small text-muted">Deal Ref: <?= htmlspecialchars($deal['deal_code']) ?></div>
      </div>
    </div>

    <div class="row g-3 mb-4">
      <div class="col-md-6">
        <div class="section-box">
          <div class="section-label">Buyer / Consignee</div>
          <div class="fw-bold text-dark"><?= htmlspecialchars($deal['company_name'] ?: $deal['buyer_name']) ?></div>
          <div class="small text-muted">Attn: <?= htmlspecialchars($deal['buyer_name']) ?></div>
          <?php if ($deal['country']): ?><div class="small text-muted">Destination Country: <?= htmlspecialchars($deal['country']) ?></div><?php endif; ?>
          <div class="small mt-2"><strong>Port of Discharge:</strong> <?= htmlspecialchars($deal['port_of_discharge'] ?: $val('port_of_discharge')) ?></div>
        </div>
      </div>
      <div class="col-md-6">
        <div class="section-box">
          <div class="section-label">Export Compliance & Carrier References</div>
          <div class="row g-1 small">
            <div class="col-6"><span class="text-muted">Incoterm:</span> <strong><?= htmlspecialchars($deal['incoterm']) ?></strong></div>
            <div class="col-6"><span class="text-muted">Payment:</span> <strong><?= htmlspecialchars($deal['payment_terms']) ?></strong></div>
            <div class="col-6"><span class="text-muted">PSW Form-E:</span> <strong class="font-monospace"><?= htmlspecialchars($deal['form_e_ref'] ?: $val('form_e_ref')) ?></strong></div>
            <div class="col-6"><span class="text-muted">WeBOC GD #:</span> <strong class="font-monospace"><?= htmlspecialchars($deal['gd_number'] ?: $val('gd_number')) ?></strong></div>
            <div class="col-6"><span class="text-muted">Vessel:</span> <strong><?= htmlspecialchars($deal['vessel_name'] ?: $val('vessel_name')) ?></strong></div>
            <div class="col-6"><span class="text-muted">B/L No.:</span> <strong class="font-monospace"><?= htmlspecialchars($deal['bill_of_lading'] ?: $val('bl_number')) ?></strong></div>
          </div>
        </div>
      </div>
    </div>

    <table class="table trade-table mb-4">
      <thead>
        <tr>
          <th style="width: 14%;">HS Code</th>
          <th style="width: 38%;">Commodity Description & Verified Spec</th>
          <?php if ($docType === 'invoice'): ?>
            <th class="text-end" style="width: 16%;">Net Dry Weight</th>
            <th class="text-end" style="width: 16%;">Unit Price</th>
            <th class="text-end" style="width: 16%;">Total (USD)</th>
          <?php else: ?>
            <th style="width: 16%;">Packing / Bags</th>
            <th class="text-end" style="width: 16%;">Wet Wt (WMT)</th>
            <th class="text-end" style="width: 16%;">Dry Wt (DMT)</th>
          <?php endif; ?>
        </tr>
      </thead>
      <tbody>
        <tr>
          <td class="font-monospace"><?= htmlspecialchars($val('hs_code', '2610.0000')) ?></td>
          <td>
            <div class="fw-bold text-dark"><?= htmlspecialchars($deal['commodity']) ?></div>
            <div class="small text-muted mt-1">Origin: Balochistan, Pakistan (District: <?= htmlspecialchars($val('source_district', 'Balochistan')) ?>)</div>
            <?php if (!empty($assayLines)): ?>
              <div class="small mt-1"><strong>Verified Assay (<?= htmlspecialchars($val('surveyor_agency', 'Independent Lab')) ?>):</strong> <?= htmlspecialchars(implode(' | ', $assayLines)) ?></div>
            <?php endif; ?>
            <div class="small text-muted mt-1">COA Ref: <?= htmlspecialchars($val('coa_cert_no', 'As Attached')) ?> | Port of Loading: <?= htmlspecialchars($deal['port_of_loading']) ?></div>
            <?php if ($docType === 'packing'): ?>
              <div class="small mt-2"><strong>Containers / Hold:</strong> <?= htmlspecialchars($val('container_count', 'TBD')) ?></div>
              <div class="small"><strong>ISO Bolt Seals:</strong> <?= htmlspecialchars($val('seal_numbers', 'TBD')) ?></div>
            <?php endif; ?>
          </td>
          <?php if ($docType === 'invoice'): ?>
            <td class="text-end font-monospace">
              <strong><?= number_format($calcMt, 2) ?> DMT</strong>
              <div class="small text-muted">Wet: <?= number_format($wetMt, 2) ?> MT</div>
              <div class="small text-muted">Moist: <?= htmlspecialchars($moist) ?>%</div>
            </td>
            <td class="text-end font-monospace">$<?= number_format($unitPx, 2) ?></td>
            <td class="text-end font-monospace fw-bold">$<?= number_format($totalUsd, 2) ?></td>
          <?php else: ?>
            <td>
              <?= htmlspecialchars($val('packing_type', 'Jumbo Bags / Bulk')) ?>
              <div class="small text-muted">Count: <?= htmlspecialchars($val('bag_count', 'TBD')) ?></div>
            </td>
            <td class="text-end font-monospace">
              <strong><?= number_format($wetMt, 2) ?> MT</strong>
              <div class="small text-muted">Moisture: <?= htmlspecialchars($moist) ?>%</div>
            </td>
            <td class="text-end font-monospace fw-bold"><?= number_format($calcMt, 2) ?> DMT</td>
          <?php endif; ?>
        </tr>
      </tbody>
    </table>

    <div class="row g-3 align-items-end pt-3" style="border-top: 1px solid #CBD5E1;">
      <div class="col-md-7 small text-muted">
        <strong>Certification Statement:</strong> We certify that this consignment is of Pakistan origin, weights and chemical assay reflect verified port inspection records, and goods declaration complies with Pakistan Single Window (PSW) regulations.
      </div>
      <div class="col-md-5 text-end">
        <div style="height: 44px;"></div>
        <div class="fw-bold small border-top pt-2" style="display: inline-block; min-width: 220px;">
          Authorized Signatory — Export Desk<br>Balochistan Minerals (Pvt) Ltd
        </div>
      </div>
    </div>
  </div>

</body>
</html>
