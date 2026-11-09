<?php
/**
 * Balochistan Minerals - AI Chatbot Real-Time Knowledge & Database Engine
 * Connects frontend assistant to live MySQL mineral prices, stockpiles, FAQs, and RFQ ingestion.
 */

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

require_once __DIR__ . '/../admin/db.php';

$pdo = getDB();

// Accept both JSON payload and POST form data
$raw = file_get_contents('php://input');
$json = json_decode($raw, true);

$action = cleanInput($json['action'] ?? $_POST['action'] ?? 'query');
$query  = cleanInput($json['query'] ?? $_POST['query'] ?? '');
$page   = cleanInput($json['page'] ?? $_POST['page'] ?? '');

// ==========================================
// 1. ACTION: INIT (Smart Context-Aware Greeting)
// ==========================================
if ($action === 'init') {
    $cleanPath = strtolower($page);

    // Context: Chromite
    if (str_contains($cleanPath, 'chromite')) {
        echo json_encode([
            'success' => true,
            'type' => 'greeting',
            'title' => 'Chromite Sourcing Desk',
            'image' => 'images/chromite-new-480.avif',
            'badge' => '42% - 52% Cr₂O₃',
            'origin' => 'Muslim Bagh Belt',
            'text' => "Welcome to **Balochistan Minerals**. Looking for metallurgical or refractory-grade **Chromite Ore (42%–52% Cr₂O₃)**?\n\nWe have active ready stockpiles at Karachi Port Trust (KPT) and active extraction at Muslim Bagh. Would you like current FOB Karachi spot pricing or pre-shipment assay certificates?",
            'chips' => ['Chromite Spot Price', 'Stock at KPT', 'SGS Assay Specs', 'Request Chromite Quote', 'All Minerals']
        ]);
        exit;
    }

    // Context: Barite
    if (str_contains($cleanPath, 'barite')) {
        echo json_encode([
            'success' => true,
            'type' => 'greeting',
            'title' => 'API 13A Barite Supply',
            'image' => 'images/barite-card-480.avif',
            'badge' => 'SG 4.20+ High Gravity',
            'origin' => 'Khuzdar Concessions',
            'text' => "Inquiring about **API 13A Drilling Grade Barite (SG 4.20+)**?\n\nWe supply both crude lumps and 200-mesh milled powder packaged in 1.5 MT jumbo bags directly from Port Qasim and Khuzdar. How may we assist your drilling project?",
            'chips' => ['Barite Spot Rate', 'Port Qasim Stock', 'API 13A Certificate', 'Request Barite Quote', 'All Minerals']
        ]);
        exit;
    }

    // Context: Copper
    if (str_contains($cleanPath, 'copper')) {
        echo json_encode([
            'success' => true,
            'type' => 'greeting',
            'title' => 'Chagai Copper Exploration',
            'image' => 'images/copper-new-480.avif',
            'badge' => '2% - 10% Cu DSO',
            'origin' => 'Chagai Metallogenic Arc',
            'text' => "Exploring **Chagai Direct Shipping Copper Ore (DSO 2%–10% Cu)**?\n\nWe supply verified smelter lots with independent SGS assay verification. Would you like recent lot assays or indicative delivery terms?",
            'chips' => ['Copper Spot Price', 'Lot Assays', 'FOB Delivery Terms', 'Request Quote']
        ]);
        exit;
    }

    // Context: Iron Ore
    if (str_contains($cleanPath, 'iron')) {
        echo json_encode([
            'success' => true,
            'type' => 'greeting',
            'title' => 'Iron Ore Magnetite / Hematite',
            'image' => 'images/iron-ore-new-480.avif',
            'badge' => 'Fe 40% - 62%',
            'origin' => 'Balochistan Deposits',
            'text' => "Sourcing **Iron Ore (Fe 40%–62%)** in direct reduction lumps or magnetite concentrate?\n\nWe provide break-bulk and container vessel charters from Karachi ports. Would you like current spot pricing or lot specifications?",
            'chips' => ['Iron Ore Price', 'Kharan Stockpiles', 'Port Loading Terms', 'Request Quote']
        ]);
        exit;
    }

    // Context: Fluorite / Fluorspar
    if (str_contains($cleanPath, 'fluor')) {
        echo json_encode([
            'success' => true,
            'type' => 'greeting',
            'title' => 'Fluorspar (CaF₂)',
            'image' => 'images/fluorite-480.avif',
            'badge' => '50% - 90%+ CaF₂',
            'origin' => 'Kalat Belt',
            'text' => "Sourcing **Fluorspar (50%–90%+ CaF₂)** for steelmaking flux or ceramic glaze?\n\nWe have graded lumps and gravel ready for container shipment. Would you like lot availability or pricing?",
            'chips' => ['Fluorspar Price', 'Chemical Grade', 'Request Quote', 'All Minerals']
        ]);
        exit;
    }

    // Default High-Level Executive Greeting
    echo json_encode([
        'success' => true,
        'type' => 'greeting',
        'title' => 'Balochistan Minerals Export Desk',
        'text' => "Welcome to **Balochistan Minerals (Pvt) Ltd**. We quarry, process, and export certified mineral commodities directly from Balochistan concessions through Karachi Port Trust (KPT), Port Qasim, and Gwadar.\n\nSelect a priority commodity or ask about live FOB spot pricing, ready stockpiles, or port assay terms:",
        'chips' => ['Chromite 42-52%', 'Barite 4.2+ SG', 'Chagai Copper', 'Live Spot Prices', 'Stockpiles Ready', 'Request Quote']
    ]);
    exit;
}

// ==========================================
// 2. ACTION: SUBMIT RFQ (Direct In-Chat Lead Ingestion)
// ==========================================
if ($action === 'submit_rfq') {
    $name     = cleanInput($json['name'] ?? $_POST['name'] ?? '');
    $company  = cleanInput($json['company'] ?? $_POST['company'] ?? '');
    $email    = filter_var(trim($json['email'] ?? $_POST['email'] ?? ''), FILTER_SANITIZE_EMAIL);
    $phone    = cleanInput($json['phone'] ?? $_POST['phone'] ?? '');
    $mineral  = cleanInput($json['mineral'] ?? $_POST['mineral'] ?? 'General Mineral');
    $quantity = cleanInput($json['quantity'] ?? $_POST['quantity'] ?? 'FCL Trial Order');
    $port     = cleanInput($json['destination_port'] ?? $_POST['destination_port'] ?? 'FOB Karachi');
    $message  = cleanInput($json['message'] ?? $_POST['message'] ?? '');
    $country  = cleanInput($json['country'] ?? $_POST['country'] ?? 'International');

    if (empty($name) || empty($email)) {
        echo json_encode(['success' => false, 'error' => 'Please provide your name and corporate email address.']);
        exit;
    }

    $leadId = 0;
    if ($pdo !== null) {
        try {
            $ip = $_SERVER['REMOTE_ADDR'] ?? '';
            $fullMsg = "In-Chat AI RFQ:\nCommodity: $mineral\nTonnage: $quantity\nPort: $port" . ($message ? "\nNotes: $message" : "");
            
            $stmt = $pdo->prepare('INSERT INTO inquiries 
                (buyer_name, company_name, country, email, whatsapp, inquiry_type, source, mineral_type, quantity, destination_port, message, status, ip_address) 
                VALUES (?, ?, ?, ?, ?, "Chatbot RFQ", "AI Portal Assistant", ?, ?, ?, ?, "New", ?)');
            $stmt->execute([$name, $company, $country, $email, $phone, $mineral, $quantity, $port, $fullMsg, $ip]);
            $leadId = (int)$pdo->lastInsertId();
        } catch (Exception $e) {
            error_log("Chatbot RFQ Error: " . $e->getMessage());
        }
    }

    // Build owner alert email
    $alertSubject = "🔥 New AI Chatbot RFQ #" . ($leadId ?: 'Desk') . ": " . $mineral . " from " . $name;
    $alertBody = "New Direct In-Chat RFQ Received via AI Portal Assistant:\n\n"
               . "Ref ID: #" . ($leadId ?: 'N/A') . "\n"
               . "Buyer Name: $name\n"
               . "Company: " . ($company ?: 'N/A') . "\n"
               . "Country: $country\n"
               . "Email: $email\n"
               . "WhatsApp: $phone\n"
               . "Commodity: $mineral\n"
               . "Quantity: $quantity\n"
               . "Destination Port: $port\n\n"
               . "View & Follow Up in Trade Desk: https://balochistanminerals.com/admin/dashboard.php";

    $headers = "From: Balochistan Minerals Web <" . COMPANY_EMAIL . ">\r\n"
             . "Reply-To: $email\r\n"
             . "X-Mailer: PHP/" . phpversion();
    @mail(COMPANY_EMAIL, $alertSubject, $alertBody, $headers);

    // Pre-filled WhatsApp link
    $waText = "Hello Balochistan Minerals Export Desk, I have registered RFQ #" . ($leadId ?: 'New') . " for " . $mineral . " (" . $quantity . "). My email is " . $email . ". Please advise current lot availability.";
    $waUrl = "https://wa.me/923348888104?text=" . urlencode($waText);

    echo json_encode([
        'success' => true,
        'lead_id' => $leadId,
        'text' => "✅ **RFQ Registered Successfully (Ref #BM-" . ($leadId ?: rand(100, 999)) . ")**\n\nThank you, **" . htmlspecialchars($name) . "**" . ($company ? " (" . htmlspecialchars($company) . ")" : "") . ". Your procurement inquiry for **" . htmlspecialchars($quantity) . "** of **" . htmlspecialchars($mineral) . "** has been logged into our Commercial Export Desk.\n\nOur trading desk will review your specifications and dispatch an official quotation to `" . htmlspecialchars($email) . "` within 2–4 business hours.",
        'wa_url' => $waUrl,
        'chips' => ['Track via WhatsApp', 'Check Stockpiles', 'Mineral Spot Prices', 'Return to Catalog']
    ]);
    exit;
}

// ==========================================
// 3. ACTION: QUERY (Intelligent DB Query Matcher)
// ==========================================
$clean = strtolower(trim($query));

if (empty($clean)) {
    echo json_encode([
        'success' => true,
        'type' => 'greeting',
        'text' => 'How can we assist your mineral sourcing today? Select a commodity or enter your target specifications.',
        'chips' => ['Chromite 42-52%', 'Barite 4.2+ SG', 'Chagai Copper', 'Live Spot Prices', 'Request Quote']
    ]);
    exit;
}

// ------------------------------------------
// A. Real-Time Spot Price Intent
// ------------------------------------------
$priceTerms = ['price', 'pricing', 'spot rate', 'spot price', 'cost', 'rate', 'how much', 'quote', 'quotation', 'usd/mt', 'usd per ton'];
$isPriceIntent = false;
foreach ($priceTerms as $term) {
    if (str_contains($clean, $term)) {
        $isPriceIntent = true;
        break;
    }
}

if ($isPriceIntent && $pdo !== null) {
    // Check if query targets a specific mineral
    $matchedPrices = [];
    try {
        $stmt = $pdo->query('SELECT mineral_name, grade_name, purity_spec, price_low_usd, price_high_usd, unit, delivery_terms, notes FROM mineral_prices WHERE is_active = 1 ORDER BY sort_order ASC, id ASC');
        $allPrices = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($allPrices as $p) {
            $minClean = strtolower($p['mineral_name']);
            if (str_contains($clean, $minClean) || 
               ($minClean === 'chromite' && (str_contains($clean, 'chrome') || str_contains($clean, 'cr2o3'))) ||
               ($minClean === 'barite' && (str_contains($clean, 'baryte') || str_contains($clean, 'baso4') || str_contains($clean, 'api'))) ||
               ($minClean === 'copper ore' && (str_contains($clean, 'copper') || str_contains($clean, 'cu'))) ||
               ($minClean === 'iron ore' && (str_contains($clean, 'iron') || str_contains($clean, 'fe') || str_contains($clean, 'magnetite'))) ||
               ($minClean === 'antimony' && (str_contains($clean, 'stibnite') || str_contains($clean, 'sb'))) ||
               ($minClean === 'fluorspar' && (str_contains($clean, 'fluorite') || str_contains($clean, 'caf2')))) {
                $matchedPrices[] = $p;
            }
        }

        // If no specific mineral matched, show top benchmark prices
        if (empty($matchedPrices) && (str_contains($clean, 'price') || str_contains($clean, 'pricing') || str_contains($clean, 'rates') || str_contains($clean, 'cost'))) {
            $matchedPrices = array_slice($allPrices, 0, 5);
        }
    } catch (Exception $e) {
        $matchedPrices = [];
    }

    if (!empty($matchedPrices)) {
        $priceText = "### 📊 Live Export Spot Prices (FOB Karachi / Port Qasim)\n\n";
        foreach ($matchedPrices as $mp) {
            $priceText .= "• **" . $mp['mineral_name'] . " (" . $mp['grade_name'] . "):** \$" . number_format($mp['price_low_usd'], 2) . " – \$" . number_format($mp['price_high_usd'], 2) . " " . $mp['unit'] . " *" . $mp['delivery_terms'] . "*\n";
            if (!empty($mp['purity_spec'])) {
                $priceText .= "  *(Spec: " . $mp['purity_spec'] . ")*\n";
            }
        }
        $priceText .= "\n*All export consignments include independent SGS / AHK pre-shipment assay certificates and draft survey weight slips. Standard terms: Irrevocable L/C at sight or T/T deposit.*";

        echo json_encode([
            'success' => true,
            'type' => 'spot_prices',
            'title' => 'Live Mineral Spot Quotes',
            'text' => $priceText,
            'showRfqCard' => true,
            'rfqData' => [
                'mineralName' => $matchedPrices[0]['mineral_name'],
                'defaultGrade' => $matchedPrices[0]['grade_name']
            ],
            'chips' => ['Check Stockpiles', 'Assay Testing Procedures', 'Request Official Quotation', 'Payment Terms (L/C)']
        ]);
        exit;
    }
}

// ------------------------------------------
// B. Real-Time Stockpiles & Yard Availability Intent
// ------------------------------------------
$stockTerms = ['stock', 'stockpile', 'stockpiles', 'available', 'availability', 'ready', 'yard', 'kpt', 'port qasim', 'karachi port', 'how much stock', 'ready to ship', 'inventory'];
$isStockIntent = false;
foreach ($stockTerms as $term) {
    if (str_contains($clean, $term)) {
        $isStockIntent = true;
        break;
    }
}

if ($isStockIntent && $pdo !== null) {
    try {
        $stmt = $pdo->query('SELECT mineral_name, location, tonnage_available, grade_spec, status, last_survey_date FROM stockpiles ORDER BY tonnage_available DESC');
        $stocks = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (!empty($stocks)) {
            $stockText = "### ⚓ Verified Export Stockpiles & Yard Status\n\n";
            $stockText .= "We maintain active bulk and container storage across prime port and regional depots:\n\n";
            foreach ($stocks as $s) {
                $badge = ($s['status'] === 'Ready for Loading') ? '✅ Ready for Loading' : '🚚 ' . $s['status'];
                $stockText .= "• **" . $s['mineral_name'] . " (" . number_format($s['tonnage_available']) . " MT):** " . $s['location'] . "\n";
                $stockText .= "  *Grade: " . $s['grade_spec'] . " • Status: " . $badge . "*\n";
            }
            $stockText .= "\n*Port stock is verified with periodic SGS / ALS survey weight slips for prompt vessel dispatch.*";

            echo json_encode([
                'success' => true,
                'type' => 'stockpiles',
                'title' => 'Port Stockpile Inventory',
                'text' => $stockText,
                'showRfqCard' => true,
                'rfqData' => [
                    'mineralName' => $stocks[0]['mineral_name'],
                    'defaultGrade' => $stocks[0]['grade_spec']
                ],
                'chips' => ['Live Spot Prices', 'Request Vessel Allocation', 'SGS Pre-Shipment Assay', 'Payment Terms']
            ]);
            exit;
        }
    } catch (Exception $e) {}
}

// ------------------------------------------
// C. Dynamic FAQs Knowledge Matcher
// ------------------------------------------
if ($pdo !== null) {
    try {
        $stmt = $pdo->query('SELECT category, question, answer FROM faqs WHERE is_active = 1 ORDER BY sort_order ASC');
        $faqs = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($faqs as $faq) {
            $qClean = strtolower($faq['question']);
            $keywords = preg_split('/[\s,\?]+/', $qClean);
            $hitCount = 0;
            foreach ($keywords as $kw) {
                if (strlen($kw) >= 3 && str_contains($clean, $kw)) {
                    $hitCount++;
                }
            }

            if ($hitCount >= 2 || str_contains($clean, $qClean)) {
                echo json_encode([
                    'success' => true,
                    'type' => 'faq',
                    'title' => $faq['question'],
                    'badge' => $faq['category'],
                    'text' => "**" . $faq['question'] . "**\n\n" . $faq['answer'],
                    'chips' => ['Live Spot Prices', 'Stockpiles Ready', 'Talk to Sales Specialist', 'Request Quote']
                ]);
                exit;
            }
        }
    } catch (Exception $e) {}
}

// Fallback to client-side knowledge engine
echo json_encode([
    'success' => false,
    'fallback' => true,
    'message' => 'Query delegated to client-side neural knowledge matcher.'
]);
