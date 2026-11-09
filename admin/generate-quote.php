<?php
/**
 * Balochistan Minerals - Official Commercial Quotation & Proforma Generator
 * Print-Ready Executive Document on Corporate Letterhead
 */

require_once __DIR__ . '/auth.php';
requireAuth();

$pdo = getDB();
$id  = (int)($_GET['id'] ?? 0);

if ($pdo === null || $id <= 0) {
    die("Invalid inquiry or database connection unavailable.");
}

$stmt = $pdo->prepare("SELECT * FROM inquiries WHERE id = ?");
$stmt->execute([$id]);
$inq = $stmt->fetch();

if (!$inq) {
    die("Inquiry not found.");
}

// Pricing calculation
$unitPrice = $inq['spot_price_quoted'] ?? 185.00;
// Parse metric tons from string e.g. "5,000 MT" -> 5000
$tonnageNum = (float)filter_var($inq['quantity'] ?? '1000', FILTER_SANITIZE_NUMBER_INT);
if ($tonnageNum <= 0) $tonnageNum = 1000;
$totalValue = $unitPrice * $tonnageNum;

$quoteRef = "BM-QT-" . date('Y') . "-" . str_pad($inq['id'], 4, '0', STR_PAD_LEFT);
$quoteDate = date('F d, Y');
$validDate = date('F d, Y', strtotime('+7 days'));

// Pre-filled WhatsApp message
$waShareMsg = "Hello " . $inq['buyer_name'] . ", please review your official commercial quotation (" . $quoteRef . ") from Balochistan Minerals (Pvt) Ltd for " . $inq['mineral_type'] . " at $" . number_format($unitPrice, 2) . "/MT " . ($inq['destination_port'] ?: 'FOB Karachi') . ".";
$waShareUrl = generateWhatsAppLink($inq['whatsapp'], $waShareMsg);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Quotation <?= $quoteRef ?> | Balochistan Minerals</title>
  <link rel="icon" type="image/png" sizes="32x32" href="../images/favicon/favicon-32x32.png">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@500;600;700;800&family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <style>
    :root {
      --navy-900: #0F1F3A;
      --navy-800: #162A4A;
      --gold-500: #D4AF37;
      --orange-500: #E65C1A;
      --text-charcoal: #2F3A40;
    }
    body {
      font-family: 'Poppins', sans-serif;
      background: #E2E8F0;
      color: var(--text-charcoal);
      margin: 0;
      padding: 20px 0;
    }
    .action-bar {
      max-width: 840px;
      margin: 0 auto 16px;
      display: flex;
      align-items: center;
      justify-content: space-between;
    }
    .quote-sheet {
      max-width: 840px;
      margin: 0 auto;
      background: #FFFFFF;
      padding: 48px;
      border-radius: 8px;
      box-shadow: 0 4px 20px rgba(0,0,0,0.08);
      position: relative;
      border: 1px solid #CBD5E1;
    }
    .letterhead-logo {
      max-height: 54px;
      width: auto;
    }
    .company-title {
      font-family: 'Montserrat', sans-serif;
      font-weight: 800;
      font-size: 1.45rem;
      color: var(--navy-900);
      margin: 0;
    }
    .gold-separator {
      height: 3px;
      background: linear-gradient(90deg, var(--gold-500), var(--orange-500));
      margin: 18px 0 24px;
    }
    .table-quote {
      margin-top: 20px;
    }
    .table-quote th {
      background: var(--navy-900);
      color: #FFFFFF;
      font-family: 'Montserrat', sans-serif;
      font-size: 0.8rem;
      font-weight: 700;
      text-transform: uppercase;
      padding: 10px 14px;
    }
    .table-quote td {
      padding: 14px;
      border-bottom: 1px solid #E2E8F0;
      vertical-align: middle;
      font-size: 0.88rem;
    }
    .stamp-box {
      border: 2px dashed #94A3B8;
      border-radius: 8px;
      padding: 16px;
      display: inline-block;
      text-align: center;
      min-width: 200px;
    }
    .corporate-seal {
      width: 70px;
      height: 70px;
      border: 2px solid var(--gold-500);
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      margin: 0 auto 6px;
      color: var(--gold-500);
      font-size: 1.8rem;
    }

    /* Print Optimization: Hide action bar and background */
    @media print {
      body {
        background: #FFFFFF;
        padding: 0;
      }
      .action-bar {
        display: none !important;
      }
      .quote-sheet {
        box-shadow: none;
        border: none;
        padding: 20px;
        max-width: 100%;
      }
    }
  </style>
</head>
<body>

  <!-- Top Action Bar (Hidden on Print) -->
  <div class="action-bar">
    <a href="dashboard.php" class="btn btn-sm btn-outline-dark">
      <i class="bi bi-arrow-left"></i> Back to Dashboard
    </a>
    <div class="d-flex gap-2">
      <a href="<?= $waShareUrl ?>" target="_blank" class="btn btn-sm btn-success fw-semibold">
        <i class="bi bi-whatsapp"></i> Share on WhatsApp
      </a>
      <button onclick="window.print()" class="btn btn-sm btn-primary fw-semibold" style="background: var(--navy-900); border: none;">
        <i class="bi bi-printer-fill me-1"></i> Print / Save as PDF
      </button>
    </div>
  </div>

  <!-- Printable Official Corporate Quotation -->
  <div class="quote-sheet">
    
    <!-- Header Letterhead -->
    <div class="d-flex align-items-center justify-content-between">
      <div>
        <h1 class="company-title">BALOCHISTAN MINERALS</h1>
        <div class="text-muted small fw-semibold" style="letter-spacing: 0.04em;">(PVT) LTD • MINING & COMMODITY EXPORT</div>
        <div class="text-muted" style="font-size: 0.76rem; margin-top: 4px;">
          Corporate Reg: SECP PK • NTN: Available on Request<br>
          Offices: Plot 42 Industrial Estate, Karachi | Mining Corridors: Khuzdar & Muslim Bagh
        </div>
      </div>
      <div class="text-end">
        <img src="../images/new-logo.png" alt="Balochistan Minerals" class="letterhead-logo" onerror="this.style.display='none'">
        <div class="text-end mt-2">
          <div class="fw-bold font-monospace" style="color: var(--navy-900); font-size: 1.05rem;"><?= $quoteRef ?></div>
          <div class="text-muted small">Date: <?= $quoteDate ?></div>
        </div>
      </div>
    </div>

    <div class="gold-separator"></div>

    <!-- Buyer & Commercial Profile -->
    <div class="row g-3 mb-4">
      <div class="col-6">
        <div class="text-muted small text-uppercase fw-bold mb-1" style="font-size: 0.72rem; letter-spacing: 0.05em;">COMMERCIAL QUOTATION PREPARED FOR:</div>
        <div class="fw-bold" style="font-size: 1.05rem; color: var(--navy-900);"><?= htmlspecialchars($inq['buyer_name']) ?></div>
        <div class="fw-semibold text-secondary small"><?= htmlspecialchars($inq['company_name'] ?: 'International Commodity Trader') ?></div>
        <div class="small text-muted"><i class="bi bi-globe me-1"></i>Country / Region: <?= htmlspecialchars($inq['country'] ?? 'International') ?></div>
        <div class="small text-muted"><i class="bi bi-envelope me-1"></i><?= htmlspecialchars($inq['email']) ?></div>
        <div class="small text-muted"><i class="bi bi-telephone me-1"></i><?= htmlspecialchars($inq['whatsapp']) ?></div>
      </div>
      <div class="col-6 text-end">
        <div class="text-muted small text-uppercase fw-bold mb-1" style="font-size: 0.72rem; letter-spacing: 0.05em;">COMMERCIAL PARAMETERS:</div>
        <div class="small">Delivery Terms: <strong><?= htmlspecialchars($inq['destination_port'] ?: 'FOB Gwadar / Karachi (KPT / Port Qasim) - Incoterms 2020') ?></strong></div>
        <div class="small">Validity: <strong><?= $validDate ?> (7 Days Spot)</strong></div>
        <div class="small">Currency: <strong>USD ($) United States Dollars</strong></div>
        <div class="small">Export Desk: <strong>sales@balochistanminerals.com</strong></div>
      </div>
    </div>

    <!-- Product Line Items -->
    <table class="table table-quote">
      <thead>
        <tr>
          <th>Description & Technical Specification</th>
          <th class="text-center">Origin</th>
          <th class="text-center">Quantity (MT)</th>
          <th class="text-end">Spot Price / MT</th>
          <th class="text-end">Total Amount (USD)</th>
        </tr>
      </thead>
      <tbody>
        <tr>
          <td>
            <div class="fw-bold" style="color: var(--navy-900);"><?= htmlspecialchars($inq['mineral_type']) ?></div>
            <div class="text-muted small">
              <?= htmlspecialchars($inq['grade_spec'] ?: 'Export Grade Run-of-Mine Lumps / Processed Material') ?>
            </div>
            <div class="text-muted" style="font-size: 0.74rem;">
              Packing: Bulk in Vessel Hold / 1.5 MT Jumbo Bags in 20ft FCL
            </div>
          </td>
          <td class="text-center small">Balochistan, PK</td>
          <td class="text-center font-monospace fw-bold"><?= number_format($tonnageNum) ?> MT</td>
          <td class="text-end font-monospace fw-bold">$<?= number_format($unitPrice, 2) ?></td>
          <td class="text-end font-monospace fw-bold" style="color: var(--navy-900); font-size: 1rem;">
            $<?= number_format($totalValue, 2) ?>
          </td>
        </tr>
      </tbody>
      <tfoot>
        <tr>
          <td colspan="3" class="border-0"></td>
          <td class="text-end fw-bold border-0">Total Contract Value:</td>
          <td class="text-end fw-bold font-monospace border-0" style="font-size: 1.15rem; color: var(--navy-900);">
            $<?= number_format($totalValue, 2) ?> USD
          </td>
        </tr>
      </tfoot>
    </table>

    <!-- Commercial Terms & Conditions -->
    <div class="p-3 bg-light rounded-3 my-4 border" style="font-size: 0.78rem; line-height: 1.6;">
      <div class="fw-bold text-uppercase mb-2" style="color: var(--navy-900); letter-spacing: 0.04em;">Standard International Export Terms & Conditions:</div>
      <ol class="ps-3 mb-0">
        <li><strong>Quality & Assay Certification:</strong> Sampling and chemical testing to be verified at Karachi port by an independent international surveyor (SGS, Alfred H. Knight, or Alex Stewart International) prior to vessel loading.</li>
        <li><strong>Payment Terms:</strong> 100% Irrevocable Confirmed Letter of Credit (L/C at sight) payable 100% against presentation of clean shipping documents (B/L, Commercial Invoice, Packing List, Certificate of Origin, and Final Assay Certificate); OR 30% Advance T/T deposit with 70% payable against scanned copies of shipping documents.</li>
        <li><strong>Weighbridge & Moisture:</strong> Final commercial billing based on certified port weighbridge scales and moisture deduction certificate.</li>
        <li><strong>Port Loading:</strong> Port Muhammad Bin Qasim (FOT/FOB) or Karachi Port Trust (KPT) berths, subject to maritime vessel nomination approval.</li>
      </ol>
    </div>

    <!-- Signatures & Seal -->
    <div class="d-flex align-items-end justify-content-between pt-3">
      <div>
        <div class="small fw-bold mb-1" style="color: var(--navy-900);">Authorized Commercial Director</div>
        <div class="font-monospace text-muted small">BALOCHISTAN MINERALS (PVT) LTD</div>
        <div style="font-family: 'Playfair Display', serif; font-size: 1.3rem; color: var(--navy-900); font-style: italic; margin-top: 6px;">
          Export Directorate
        </div>
        <div class="text-muted" style="font-size: 0.72rem;">Verified Signature & Timestamp</div>
      </div>

      <div class="stamp-box">
        <div class="corporate-seal">★</div>
        <div class="fw-bold" style="font-size: 0.72rem; color: var(--navy-900); letter-spacing: 0.05em;">CORPORATE COMMODITY SEAL</div>
        <div class="text-muted" style="font-size: 0.65rem;">Balochistan Minerals (Pvt) Ltd</div>
      </div>
    </div>

  </div>

</body>
</html>
