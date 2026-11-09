<?php
/**
 * Balochistan Minerals - AI Chatbot Real-Time Knowledge & Database Engine
 * Connects frontend assistant to live MySQL mineral prices, stockpiles, FAQs, and RFQ ingestion.
 */

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

require_once __DIR__ . '/../admin/db.php';
require_once __DIR__ . '/../admin/flag-helper.php';

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
            'title' => 'Chromite Marketing & Sourcing',
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

    // Default High-Level Executive Greeting (Upfront Portfolio Clarity)
    echo json_encode([
        'success' => true,
        'type' => 'greeting',
        'title' => 'Balochistan Minerals Marketing Department',
        'text' => "Welcome to **Balochistan Minerals (Pvt) Ltd**. We are active concession owners and direct exporters of **Chromite (42%–52%)**, **API 13A Barite (SG 4.20+)**, **Chagai Copper DSO (2%–10%)**, **Iron Ore (40%–62%)**, **Fluorspar**, **Antimony**, and **Marble** from Balochistan.\n\nAre you looking for specs and live spot rates on our active mining concessions, or inquiring about custom mineral sourcing in Pakistan?",
        'chips' => ['Chromite 42-52%', 'Barite 4.2+ SG', 'Chagai Copper', 'Live Spot Prices', 'Ready Stockpiles', 'Custom Sourcing']
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
    $rawCountry = cleanInput($json['country'] ?? $_POST['country'] ?? '');
    $country  = detectCountryName($rawCountry, $email, $phone, $company, $message);

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
    $waText = "Hello Balochistan Minerals Marketing Department, I have registered RFQ #" . ($leadId ?: 'New') . " for " . $mineral . " (" . $quantity . "). My email is " . $email . ". Please advise current lot availability.";
    $waUrl = "https://wa.me/923348888104?text=" . urlencode($waText);

    echo json_encode([
        'success' => true,
        'lead_id' => $leadId,
        'text' => "✅ **RFQ Registered Successfully (Ref #BM-" . ($leadId ?: rand(100, 999)) . ")**\n\nThank you, **" . htmlspecialchars($name) . "**" . ($company ? " (" . htmlspecialchars($company) . ")" : "") . ". Your procurement inquiry for **" . htmlspecialchars($quantity) . "** of **" . htmlspecialchars($mineral) . "** has been logged into our Marketing & Export Department.\n\nOur marketing director will review your specifications and dispatch an official quotation to `" . htmlspecialchars($email) . "` within 2–4 business hours.",
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

// Define Unlisted Minerals & Honest Pivot Registry
$unlistedMinerals = [
    'lithium' => ['name' => 'Lithium Ore (Spodumene / Lepidolite)', 'context' => 'Hard-rock spodumene and lepidolite pegmatites occur in northern Pakistan and regional belts, but are not in regular commercial mine production in Balochistan.'],
    'spodumene' => ['name' => 'Lithium Spodumene', 'context' => 'Hard-rock spodumene pegmatites require specialized site extraction and custom laboratory assay validation.'],
    'lepidolite' => ['name' => 'Lithium Lepidolite', 'context' => 'Lithium mica pegmatites are evaluated per client custom procurement contracts.'],
    'talc' => ['name' => 'Talc / Soapstone', 'context' => 'High-whiteness deposits occur in Sherani and KPK, sourced on client-specific quarry procurement orders.'],
    'soapstone' => ['name' => 'Talc / Soapstone', 'context' => 'High-whiteness deposits occur in Sherani and KPK, sourced on client-specific quarry procurement orders.'],
    'manganese' => ['name' => 'Manganese Ore (Mn)', 'context' => 'Lumpy manganese occurrences in Lasbela and Waziristan are evaluated on custom smelter contract terms.'],
    'quartz' => ['name' => 'Silica Quartz (SiO2)', 'context' => 'High-purity white quartz vein occurrences across Loralai are procured per custom sizing orders.'],
    'silica' => ['name' => 'Silica Quartz (SiO2)', 'context' => 'High-purity white quartz vein occurrences across Loralai are procured per custom sizing orders.'],
    'lead' => ['name' => 'Lead-Zinc Sulfide Ore', 'context' => 'Galena deposits along the Lasbela-Khuzdar corridor are evaluated on custom contract terms.'],
    'zinc' => ['name' => 'Lead-Zinc Sulfide Ore', 'context' => 'Sphalerite deposits along the Lasbela-Khuzdar corridor are evaluated on custom contract terms.'],
    'bauxite' => ['name' => 'Bauxite Ore (Al2O3)', 'context' => 'Alumina bauxite is evaluated per custom industrial cement/refractory procurement contract.'],
    'phosphate' => ['name' => 'Phosphate Rock', 'context' => 'SSP fertilizer feedstock is evaluated per seasonal bulk allocations.'],
    'cobalt' => ['name' => 'Cobalt Ore', 'context' => 'Cobalt is not in active commercial production in Balochistan.'],
    'nickel' => ['name' => 'Nickel Ore', 'context' => 'Nickel is not in active commercial extraction in Balochistan.'],
    'gold' => ['name' => 'Gold / Precious Metals', 'context' => 'Porphyry copper-gold deposits (such as Reko Diq) are state-regulated megaprojects and not open to merchant spot trading.'],
    'rare earth' => ['name' => 'Rare Earth Elements (REE)', 'context' => 'Carbonatite occurrences are in exploratory phases and not in merchant export stock.']
];

$matchedUnlisted = null;
foreach ($unlistedMinerals as $kw => $info) {
    if (str_contains($clean, $kw)) {
        $matchedUnlisted = $info;
        break;
    }
}

// Core active mineral keywords
$coreMineralKeywords = [
    'chromite' => 'Chromite Ore',
    'chrome' => 'Chromite Ore',
    'barite' => 'Barite API 13A',
    'baryte' => 'Barite API 13A',
    'copper' => 'Copper Ore DSO',
    'iron' => 'Iron Ore',
    'magnetite' => 'Iron Ore',
    'hematite' => 'Iron Ore',
    'antimony' => 'Antimony Stibnite',
    'stibnite' => 'Antimony Stibnite',
    'fluorite' => 'Fluorspar (CaF2)',
    'fluorspar' => 'Fluorspar (CaF2)',
    'coal' => 'Steam Coal',
    'marble' => 'Marble & Onyx',
    'onyx' => 'Marble & Onyx',
    'granite' => 'Granite Blocks',
    'gypsum' => 'Gypsum',
    'magnesite' => 'Magnesite'
];

$detectedMineral = 'General Inquiries';
$isUnlisted = 0;

if ($matchedUnlisted !== null) {
    $detectedMineral = $matchedUnlisted['name'];
    $isUnlisted = 1;
} else {
    foreach ($coreMineralKeywords as $kw => $mName) {
        if (str_contains($clean, $kw)) {
            $detectedMineral = $mName;
            break;
        }
    }
}

// Silent Ingestion: Log buyer queries into chatbot_queries for market demand discovery & blog topics
if ($pdo !== null && strlen($clean) >= 3) {
    try {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        $buyerCountry = 'International';
        if (!empty($_SERVER['HTTP_CF_IPCOUNTRY'])) {
            $buyerCountry = normalizeCountryName($_SERVER['HTTP_CF_IPCOUNTRY']);
        }
        $logStmt = $pdo->prepare("INSERT INTO chatbot_queries (query_text, detected_mineral, is_unlisted, buyer_country, page_origin, ip_address) VALUES (?, ?, ?, ?, ?, ?)");
        $logStmt->execute([$query, $detectedMineral, $isUnlisted, $buyerCountry, $page, $ip]);
    } catch (Exception $e) {}
}

// ------------------------------------------
// 0. Unlisted Mineral Honest Pivot (No false promises!)
// ------------------------------------------
if ($matchedUnlisted !== null) {
    echo json_encode([
        'success' => true,
        'type' => 'unlisted_mineral',
        'title' => $matchedUnlisted['name'] . ' — Concession Scope & Sourcing',
        'badge' => 'Custom Sourcing Evaluation',
        'image' => 'images/baochistan-mineral-resources-480.avif',
        'text' => "**Balochistan Minerals (Pvt) Ltd** does not directly mine or hold **" . $matchedUnlisted['name'] . "** in our active export inventory.\n\nOur direct extraction concessions and ready port stockpiles are focused strictly on:\n• **Metallurgical Chromite (42%–52% Cr₂O₃)** — Muslim Bagh Belt\n• **API 13A High-Gravity Barite (SG 4.20+)** — Khuzdar Concessions\n• **Chagai Copper DSO (2%–10% Cu)** — Smelter Feedstock\n• **Iron Ore (Fe 40%–62% Magnetite/Hematite)**\n• **Fluorspar (50%–90%+ CaF₂)** & **Dimensional Marble**\n\n**Custom Procurement & Sourcing:**\n" . $matchedUnlisted['context'] . " If your plant or processing facility requires verified supply from Pakistani deposits on a custom contract basis, our Marketing Department can evaluate your target specification upon receiving your required grade and tonnage.",
        'productsList' => [
            ['name' => 'Chromite Ore', 'grade' => '42% - 52% Cr₂O₃', 'image' => 'images/chromite-new-480.avif', 'query' => 'Chromite 42-52%'],
            ['name' => 'Barite API 13A', 'grade' => 'SG 4.20+ High Gravity', 'image' => 'images/barite-card-480.avif', 'query' => 'Barite 4.2+ SG'],
            ['name' => 'Copper DSO', 'grade' => '2% - 10% Cu Direct Ship', 'image' => 'images/copper-new-480.avif', 'query' => 'Chagai Copper'],
            ['name' => 'Iron Ore', 'grade' => 'Fe 40% - 62% Magnetite', 'image' => 'images/iron-ore-new-480.avif', 'query' => 'Iron Ore']
        ],
        'showRfqCard' => true,
        'rfqData' => [
            'mineralName' => 'Custom Sourcing: ' . $matchedUnlisted['name'],
            'defaultGrade' => 'Custom Chemical Assay Required',
            'url' => 'contact.html#inquiry-form'
        ],
        'chips' => ['Browse Active Concessions', 'Request Custom Sourcing', 'Live Spot Prices', 'Contact Marketing Department']
    ]);
    exit;
}

// ------------------------------------------
// 0.4 Dedicated Mining Projects & Concessions Intent
// ------------------------------------------
$isProjectsIntent = false;
if (preg_match('/\b(project|projects|concessions?|mining\s+assets?|mines?|mining\s+sites?|extraction\s+sites?|footprint|where\s+do\s+you\s+mine)\b/i', $clean)) {
    $isProjectsIntent = true;
}

if ($isProjectsIntent) {
    echo json_encode([
        'success' => true,
        'type' => 'projects',
        'title' => 'Balochistan Minerals Active Mining Projects',
        'image' => 'images/hero/projects-page-hero2.avif',
        'badge' => 'Active Mining Concessions',
        'url' => 'projects.html',
        'urlLabel' => 'Explore All Mining Projects & Site Operations',
        'text' => "**Balochistan Minerals (Pvt) Ltd** operates, extracts, and manages commercial concessions across four primary commodity corridors in Balochistan:\n\n• **Muslim Bagh Chromite Project (Zhob Belt):** High-grade metallurgical chromite extraction (42%–52% Cr₂O₃) with active open-pit and underground adits, dedicated sorting stockyards, and direct transit to Karachi port terminals.\n• **Khuzdar Barite Project (Shekran/Gunga):** Premium API 13A high-gravity drilling barite (SG 4.20+ g/cm³) supplying both domestic oilfield operators and export break-bulk vessel shipments.\n• **Chagai Copper Asset (Tethyan Belt):** Direct shipping copper ore (DSO 2%–10% Cu) and exploration concessions within the globally renowned Tethyan metallogenic arc.\n• **Dilband & Mastung Iron Ore Project:** High-reserve extraction operations producing 40%–62% Fe magnetite and hematite for domestic foundries and international smelting works.\n• **Dimension Stone & Marble Quarries:** Dimensional quarrying across Lasbela, Khuzdar, and Loralai for natural marble (Persian Silk, Pietra Grey, Silver Steam).\n• **Degari & Mach Energy Assets:** Sub-bituminous industrial steam coal extraction supplying regional energy and manufacturing requirements.\n\n*Select an operational mineral project below for extraction specs or explore our complete project map:*",
        'productsList' => [
            ['name' => 'Muslim Bagh Chromite', 'grade' => 'Zhob Belt • 42%–52% Cr₂O₃', 'image' => 'images/chromite-new-480.avif', 'query' => 'Chromite 42-52%'],
            ['name' => 'Khuzdar Barite', 'grade' => 'Khuzdar Belt • SG 4.20+', 'image' => 'images/barite-card-480.avif', 'query' => 'Barite 4.2+ SG'],
            ['name' => 'Chagai Copper DSO', 'grade' => 'Tethyan Arc • 2%–10% Cu', 'image' => 'images/copper-new-480.avif', 'query' => 'Chagai Copper'],
            ['name' => 'Dilband Iron Ore', 'grade' => 'Mastung • Fe 40%–62%', 'image' => 'images/iron-ore-new-480.avif', 'query' => 'Iron Ore']
        ],
        'chips' => ['Explore Projects Page', 'Muslim Bagh Chromite', 'Khuzdar Barite', 'Chagai Copper', 'Live Spot Prices', 'Contact Marketing Department']
    ]);
    exit;
}

// ------------------------------------------
// 0.5 Comprehensive Concession Portfolio / Catalog Intent
// ------------------------------------------
$cleanWithoutCompany = preg_replace('/\bbalochistan\s+minerals\b/i', '', $clean);
$isCatalogIntent = false;
if (preg_match('/\b(other|available|all)\s+(m[ie]nerals?|products?|commodit\w+)\b/i', $cleanWithoutCompany) ||
    preg_match('/\b(what|which)\b.*\b(m[ie]nerals?|products?|commodit\w+)\b/i', $cleanWithoutCompany) ||
    preg_match('/\b(show|list|tell|view|see)\b.*\b(m[ie]nerals?|products?|commodit\w+)\b/i', $cleanWithoutCompany) ||
    preg_match('/\bwhat\s+(else\s+)?do\s+you\s+(have|hvae|sell|offer|supply|export)\b/i', $cleanWithoutCompany) ||
    preg_match('/\b(your|our)\s+(m[ie]nerals?|products?|commodit\w+)\b/i', $cleanWithoutCompany) ||
    preg_match('/\b(catalog|catalogue|portfolio|product\s+list|m[ie]nerals?\s+list|offerings)\b/i', $cleanWithoutCompany)) {
    $isCatalogIntent = true;
}

if ($isCatalogIntent) {
    echo json_encode([
        'success' => true,
        'type' => 'catalog',
        'title' => 'Balochistan Minerals Concession Portfolios',
        'image' => 'images/baochistan-mineral-resources-480.avif',
        'badge' => 'Active Export Concessions',
        'url' => 'products.html',
        'urlLabel' => 'View Complete Technical Catalog & Specifications',
        'text' => "**Balochistan Minerals (Pvt) Ltd** quarries, processes, and exports three primary commodity portfolios directly from Balochistan concessions:\n\n• **Metallic Ores:** Metallurgical Chromite (42%–52% Cr₂O₃), Chagai Copper DSO (2%–10% Cu), Iron Ore Magnetite/Hematite (Fe 40%–62%), and Antimony Stibnite (4%–58% Sb).\n• **Industrial Minerals:** API 13A High-Gravity Barite (SG 4.20+), Fluorspar (50%–90%+ CaF₂), and Natural Gypsum (90%–95%).\n• **Dimensional Stones & Coal:** Persian Silk & Pietra Grey Marble slabs and blocks, and Industrial Steam Coal.\n\n**Custom Procurement Desk:**\nFor unlisted mineral requirements from Pakistani deposits (such as Talc, Manganese, or Quartz), our Marketing Department evaluates buyer specifications on a custom contract procurement basis.\n\nSelect any active commodity below for assay specs, port stockpile availability, and FOB spot pricing:",
        'productsList' => [
            ['name' => 'Chromite Ore', 'grade' => '42% - 52% Cr₂O₃', 'image' => 'images/chromite-new-480.avif', 'query' => 'Chromite 42-52%'],
            ['name' => 'Barite API 13A', 'grade' => 'SG 4.20+ High Gravity', 'image' => 'images/barite-card-480.avif', 'query' => 'Barite 4.2+ SG'],
            ['name' => 'Copper DSO', 'grade' => '2% - 10% Cu Direct Ship', 'image' => 'images/copper-new-480.avif', 'query' => 'Chagai Copper'],
            ['name' => 'Iron Ore', 'grade' => 'Fe 40% - 62% Magnetite', 'image' => 'images/iron-ore-new-480.avif', 'query' => 'Iron Ore'],
            ['name' => 'Fluorspar', 'grade' => '50% - 90%+ CaF₂', 'image' => 'images/fluorite-480.avif', 'query' => 'Fluorite CaF2'],
            ['name' => 'Marble Slabs', 'grade' => 'Persian Silk & Pietra', 'image' => 'images/silver-steam-white-marble-1-480.avif', 'query' => 'Marble & Stone Slabs']
        ],
        'chips' => ['Chromite 42-52%', 'Barite 4.2+ SG', 'Chagai Copper', 'Live Spot Prices', 'Stockpiles Ready', 'Custom Sourcing']
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

        $stopWords = ['what', 'the', 'is', 'are', 'you', 'for', 'and', 'can', 'how', 'our', 'with', 'your', 'from', 'this', 'that', 'have', 'hvae', 'does', 'when', 'where', 'why', 'who', 'about', 'some', 'more', 'other', 'which', 'will', 'would', 'could', 'please', 'tell'];

        foreach ($faqs as $faq) {
            $qClean = strtolower(trim($faq['question']));

            // Direct full phrase match
            if (str_contains($clean, $qClean) || (strlen($clean) >= 8 && str_contains($qClean, $clean))) {
                $hitCount = 99;
            } else {
                $rawKeywords = preg_split('/[\s,\?!\(\)\/]+/', $qClean);
                $meaningfulKeywords = array_filter($rawKeywords, function($w) use ($stopWords) {
                    return strlen($w) >= 3 && !in_array($w, $stopWords);
                });

                $hitCount = 0;
                foreach ($meaningfulKeywords as $kw) {
                    if (preg_match('/\b' . preg_quote($kw, '/') . '\b/i', $clean)) {
                        $hitCount++;
                    }
                }
            }

            $isAcronymMatch = (str_contains($clean, 'moq') && str_contains($qClean, 'moq'));
            if ($hitCount >= 2 || $isAcronymMatch) {
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
