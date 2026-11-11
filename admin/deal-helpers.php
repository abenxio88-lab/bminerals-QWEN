<?php
/**
 * Balochistan Minerals - Stage-Measured Export Deal Engine & Compliance Schema
 * Centralizes deal stages, mineral headline spec presets, idempotent MySQL migrations,
 * readiness scoring, and backward-compatible shipment syncing.
 */

require_once __DIR__ . '/db.php';

/**
 * Returns the 7 standardized commercial, physical, customs, and settlement stages.
 */
function getDealStageDefinitions() {
    return [
        [
            'key'             => 'contract_lc',
            'order'           => 1,
            'title'           => 'Contract & L/C Verified',
            'short'           => 'Contract & Bank',
            'legacy_stage'    => 'Contract & L/C Verified',
            'description'     => 'Lock buyer KYB, SPA, Proforma Invoice, payment terms (L/C or TT), and PSW Electronic Form-E.',
            'metrics'         => [
                ['key' => 'contracted_mt',  'label' => 'Contracted Tonnage',   'unit' => 'MT',     'placeholder' => 'e.g. 2500'],
                ['key' => 'incoterm',       'label' => 'Incoterm',             'unit' => '',       'placeholder' => 'FOB Karachi / CIF'],
                ['key' => 'payment_terms',  'label' => 'Payment Instrument',   'unit' => '',       'placeholder' => 'Irrevocable L/C at Sight / TT'],
                ['key' => 'unit_price_usd', 'label' => 'Unit Price',           'unit' => 'USD/MT', 'placeholder' => 'e.g. 245'],
                ['key' => 'form_e_ref',     'label' => 'PSW Form-E / FI #',    'unit' => '',       'placeholder' => 'PSW-FI-2026-XXXX'],
                ['key' => 'lc_number',      'label' => 'L/C or SWIFT Ref',     'unit' => '',       'placeholder' => 'MT700 / Wire Ref'],
            ],
            'doc_categories'  => [
                'spa'              => 'Sales Contract (SPA)',
                'proforma_invoice' => 'Proforma Invoice (PI)',
                'lc_swift'         => 'L/C MT700 / SWIFT Proof',
                'form_e'           => 'PSW Electronic Form-E (FI)',
            ],
            'mandatory_docs'  => ['spa', 'form_e'],
        ],
        [
            'key'             => 'mine_sourcing',
            'order'           => 2,
            'title'           => 'Mine Sourcing & Crushing',
            'short'           => 'Mine & Royalty',
            'legacy_stage'    => 'Mine Sourcing & Crushing',
            'description'     => 'Verify mine batch extraction, sizing, dispatched truck tonnage, and Balochistan Royalty/Rahdari passes.',
            'metrics'         => [
                ['key' => 'source_district', 'label' => 'Source District',      'unit' => '',       'placeholder' => 'Muslim Bagh / Khuzdar / Chagai'],
                ['key' => 'mine_batch_id',   'label' => 'Mine Batch / Lot ID',  'unit' => '',       'placeholder' => 'MB-LOT-2026-01'],
                ['key' => 'dispatched_mt',   'label' => 'Mine Dispatched MT',   'unit' => 'MT',     'placeholder' => 'e.g. 2540'],
                ['key' => 'truck_count',     'label' => 'Trucks Dispatched',    'unit' => 'trucks', 'placeholder' => 'e.g. 64'],
                ['key' => 'royalty_challan', 'label' => 'Royalty / Rahdari #',  'unit' => '',       'placeholder' => 'Challan Receipt No.'],
            ],
            'doc_categories'  => [
                'mine_photos'  => 'Mine Pit & Ore Photos',
                'royalty_pass' => 'Balochistan Royalty / Rahdari Pass',
                'mine_tally'   => 'Mine Dispatch Truck Tally',
            ],
            'mandatory_docs'  => ['mine_photos', 'royalty_pass'],
        ],
        [
            'key'             => 'yard_staging',
            'order'           => 3,
            'title'           => 'Inland Transit & Yard Staging',
            'short'           => 'Yard Weighbridge',
            'legacy_stage'    => 'Port Warehouse Stacking',
            'description'     => 'Reconcile port/warehouse weighbridge intake weight (Kanta), transit shrinkage, and jumbo bag stacking.',
            'metrics'         => [
                ['key' => 'yard_bay',            'label' => 'Warehouse / Yard Bay',   'unit' => '',     'placeholder' => 'KPT / Hub Yard Bay B-04'],
                ['key' => 'yard_weighbridge_mt', 'label' => 'Yard Weighbridge Weight','unit' => 'MT',   'placeholder' => 'e.g. 2518'],
                ['key' => 'shrinkage_pct',       'label' => 'Transit Shrinkage',      'unit' => '%',    'placeholder' => 'e.g. 0.86'],
                ['key' => 'packing_type',        'label' => 'Packing Format',         'unit' => '',     'placeholder' => '1.25 MT FIBC Bags / Bulk'],
                ['key' => 'bag_count',           'label' => 'Bags / Lots Count',      'unit' => 'bags', 'placeholder' => 'e.g. 2014'],
            ],
            'doc_categories'  => [
                'weighbridge_slips' => 'Weighbridge Slips (Kanta)',
                'yard_photos'       => 'Yard Stack & Bagging Photos',
                'yard_grn'          => 'Warehouse Goods Receiving Note (GRN)',
            ],
            'mandatory_docs'  => ['weighbridge_slips', 'yard_photos'],
        ],
        [
            'key'             => 'sgs_assay',
            'order'           => 4,
            'title'           => 'SGS / Independent Assay',
            'short'           => 'Assay & Weight',
            'legacy_stage'    => 'SGS / Assay Inspection',
            'description'     => 'Record independent lab chemical assay, composite seal numbers, moisture %, and payable Dry Metric Tons (DMT).',
            'metrics'         => [
                ['key' => 'surveyor_agency', 'label' => 'Surveyor / Lab',         'unit' => '',   'placeholder' => 'SGS / AHK / PCSIR / CCIC'],
                ['key' => 'coa_cert_no',     'label' => 'Assay Certificate #',    'unit' => '',   'placeholder' => 'SGS-KHI-2026-XXXX'],
                ['key' => 'sample_seal_no',  'label' => 'Composite Seal #',       'unit' => '',   'placeholder' => 'Seal #44182-A'],
                ['key' => 'wet_mt',          'label' => 'Wet Metric Tons (WMT)',  'unit' => 'MT', 'placeholder' => 'e.g. 2518.00'],
                ['key' => 'moisture_pct',    'label' => 'Moisture Content',       'unit' => '%',  'placeholder' => 'e.g. 1.40'],
                ['key' => 'dry_mt',          'label' => 'Dry Metric Tons (DMT)',  'unit' => 'MT', 'placeholder' => 'e.g. 2482.75'],
            ],
            'doc_categories'  => [
                'sampling_photos'     => 'Joint Sampling Photos',
                'coa_pdf'             => 'Certificate of Analysis (COA)',
                'weight_moisture_pdf' => 'Certificate of Weight & Moisture',
            ],
            'mandatory_docs'  => ['coa_pdf', 'weight_moisture_pdf'],
        ],
        [
            'key'             => 'customs_stuffing',
            'order'           => 5,
            'title'           => 'Stuffing & Customs (PSW / e-CO)',
            'short'           => 'Customs & e-CO',
            'legacy_stage'    => 'Customs Clearance',
            'description'     => 'Complete 4-shot container seal photography, WeBOC Goods Declaration, PSW e-CO, Final Invoice & Packing List.',
            'metrics'         => [
                ['key' => 'container_count', 'label' => 'Containers / Bulk Hold',  'unit' => '', 'placeholder' => '20 x 20ft FCL / Hold #2'],
                ['key' => 'seal_numbers',    'label' => 'ISO Bolt Seal List',      'unit' => '', 'placeholder' => 'Seal range or CSV count'],
                ['key' => 'gd_number',       'label' => 'WeBOC Export GD #',       'unit' => '', 'placeholder' => 'KAPW-SB-XXXXX'],
                ['key' => 'hs_code',         'label' => 'HS Code',                 'unit' => '', 'placeholder' => 'e.g. 2610.0000'],
                ['key' => 'gate_in_date',    'label' => 'Terminal Gate-In (EIR)',  'unit' => '', 'placeholder' => 'YYYY-MM-DD'],
            ],
            'doc_categories'  => [
                'container_seal_photos' => 'Container Stuffing & Seal Photos',
                'weboc_gd'              => 'WeBOC Export GD (Out of Charge)',
                'cert_origin'           => 'Certificate of Origin (e-CO / Chamber)',
                'commercial_invoice'    => 'Final Commercial Invoice',
                'packing_list'          => 'Detailed Packing List',
                'safety_phyto'          => 'MSDS / Non-Rad / ISPM-15 Phyto',
            ],
            'mandatory_docs'  => ['container_seal_photos', 'weboc_gd', 'cert_origin', 'commercial_invoice', 'packing_list'],
        ],
        [
            'key'             => 'vessel_bl',
            'order'           => 6,
            'title'           => 'Vessel Loaded & B/L Dispatch',
            'short'           => 'Vessel & B/L',
            'legacy_stage'    => 'Vessel Loaded',
            'description'     => 'Audit Draft B/L word-for-word against L/C clauses, record Final Shipped-on-Board B/L, vessel sailing & courier.',
            'metrics'         => [
                ['key' => 'vessel_name',       'label' => 'Vessel Name',            'unit' => '', 'placeholder' => 'MV PACIFIC TRADER'],
                ['key' => 'voyage_no',         'label' => 'Voyage / Carrier Line',  'unit' => '', 'placeholder' => 'MSC / COSCO V.042E'],
                ['key' => 'port_of_loading',   'label' => 'Port of Loading (POL)',  'unit' => '', 'placeholder' => 'KICT Karachi / Port Qasim'],
                ['key' => 'port_of_discharge', 'label' => 'Port of Discharge (POD)','unit' => '', 'placeholder' => 'Qingdao / Tianjin / Jebel Ali'],
                ['key' => 'bl_number',         'label' => 'Bill of Lading (B/L) #', 'unit' => '', 'placeholder' => 'COSU639182740'],
                ['key' => 'etd_eta',           'label' => 'ETD / ETA Window',       'unit' => '', 'placeholder' => 'ETD Nov 14 -> ETA Nov 29'],
                ['key' => 'dispatch_ref',      'label' => 'DHL / Telex Release Ref','unit' => '', 'placeholder' => 'DHL AWB / Telex Ref'],
            ],
            'doc_categories'  => [
                'bl_draft'         => 'Draft Bill of Lading (B/L)',
                'bl_final'         => 'Original / Telex Signed B/L',
                'marine_insurance' => 'Marine Cargo Insurance Policy',
            ],
            'mandatory_docs'  => ['bl_final'],
        ],
        [
            'key'             => 'discharge_closeout',
            'order'           => 7,
            'title'           => 'Discharge & Final Settlement',
            'short'           => 'Settlement & PRC',
            'legacy_stage'    => 'Discharged & Completed',
            'description'     => 'Reconcile destination port outturn weight/assay variance, finalize settlement balance invoice, and file Bank PRC.',
            'metrics'         => [
                ['key' => 'arrival_date',       'label' => 'Discharge Arrival Date',   'unit' => '',    'placeholder' => 'YYYY-MM-DD'],
                ['key' => 'outturn_dmt',        'label' => 'Buyer Outturn Dry Weight', 'unit' => 'DMT', 'placeholder' => 'e.g. 2479.10'],
                ['key' => 'assay_variance_pct', 'label' => 'Assay Variance vs Load',   'unit' => '%',   'placeholder' => 'e.g. -0.15'],
                ['key' => 'umpire_invoked',     'label' => 'Umpire Sample Invoked?',   'unit' => '',    'placeholder' => 'No / Yes (Inspector)'],
                ['key' => 'settled_usd',        'label' => 'Final Settled Total',      'unit' => 'USD', 'placeholder' => 'e.g. 608273'],
                ['key' => 'prc_ref',            'label' => 'Bank PRC Reference',       'unit' => '',    'placeholder' => 'PRC-2026-XXXXXX'],
            ],
            'doc_categories'  => [
                'outturn_report' => 'Discharge Outturn Weight Report',
                'final_invoice'  => 'Final Balance Settlement Invoice',
                'bank_prc'       => 'Proceeds Realization Certificate (PRC)',
            ],
            'mandatory_docs'  => ['bank_prc'],
        ],
    ];
}

/**
 * Maps a stage_key to legacy shipments.status_stage enum string cleanly.
 */
function mapStageKeyToLegacyStatus($stageKey) {
    foreach (getDealStageDefinitions() as $def) {
        if ($def['key'] === $stageKey) {
            return $def['legacy_stage'];
        }
    }
    return 'Contract & L/C Verified';
}

/**
 * Returns simple, editable Stage 4 assay headline presets for a commodity (non-bloated!).
 */
function getMineralAssayPresets($commodityName) {
    $low = strtolower(trim((string)$commodityName));
    if (strpos($low, 'chrom') !== false) {
        return [
            ['key' => 'assay_cr2o3', 'label' => 'Cr2O3 Content', 'unit' => '%',     'placeholder' => 'e.g. 46.8'],
            ['key' => 'assay_cr_fe', 'label' => 'Cr:Fe Ratio',   'unit' => 'ratio', 'placeholder' => 'e.g. 2.85'],
            ['key' => 'assay_sio2',  'label' => 'SiO2 Content',  'unit' => '%',     'placeholder' => 'e.g. 5.4'],
        ];
    }
    if (strpos($low, 'barite') !== false || strpos($low, 'baryte') !== false) {
        return [
            ['key' => 'assay_baso4', 'label' => 'BaSO4 Purity',      'unit' => '%',  'placeholder' => 'e.g. 94.5'],
            ['key' => 'assay_sg',    'label' => 'Specific Gravity',  'unit' => 'SG', 'placeholder' => 'e.g. 4.22'],
        ];
    }
    if (strpos($low, 'copper') !== false) {
        return [
            ['key' => 'assay_cu', 'label' => 'Cu Content', 'unit' => '%', 'placeholder' => 'e.g. 24.5'],
        ];
    }
    if (strpos($low, 'iron') !== false || strpos($low, 'magnetite') !== false || strpos($low, 'hematite') !== false) {
        return [
            ['key' => 'assay_fe',   'label' => 'Total Fe', 'unit' => '%', 'placeholder' => 'e.g. 62.8'],
            ['key' => 'assay_sio2', 'label' => 'SiO2',     'unit' => '%', 'placeholder' => 'e.g. 4.2'],
        ];
    }
    if (strpos($low, 'antimony') !== false || strpos($low, 'stibnite') !== false) {
        return [
            ['key' => 'assay_sb', 'label' => 'Sb Content', 'unit' => '%', 'placeholder' => 'e.g. 52.0'],
        ];
    }
    if (strpos($low, 'fluor') !== false) {
        return [
            ['key' => 'assay_caf2', 'label' => 'CaF2 Purity', 'unit' => '%', 'placeholder' => 'e.g. 88.5'],
        ];
    }
    if (strpos($low, 'manganese') !== false) {
        return [
            ['key' => 'assay_mn', 'label' => 'Mn Content', 'unit' => '%', 'placeholder' => 'e.g. 42.0'],
        ];
    }
    if (strpos($low, 'coal') !== false) {
        return [
            ['key' => 'assay_gcv', 'label' => 'GCV Calorific', 'unit' => 'kcal/kg', 'placeholder' => 'e.g. 6150'],
            ['key' => 'assay_ash', 'label' => 'Ash Content',   'unit' => '%',       'placeholder' => 'e.g. 12.5'],
        ];
    }
    if (strpos($low, 'marble') !== false || strpos($low, 'onyx') !== false || strpos($low, 'granite') !== false || strpos($low, 'stone') !== false) {
        return [
            ['key' => 'stone_dims',   'label' => 'Block / Slab Size', 'unit' => 'cm', 'placeholder' => 'e.g. 280x160x2'],
            ['key' => 'stone_finish', 'label' => 'Grade / Finish',    'unit' => '',   'placeholder' => 'Grade A Export Cut'],
        ];
    }
    return [
        ['key' => 'assay_primary', 'label' => 'Primary Grade Spec', 'unit' => '%', 'placeholder' => 'Assayed purity'],
    ];
}

/**
 * Generates an unguessable public/buyer tracking code & URL token.
 */
function generateDealTrackingIdentifiers() {
    $yr   = date('Y');
    $rand = strtoupper(substr(bin2hex(random_bytes(3)), 0, 4));
    $num  = random_int(100, 999);
    return [
        'deal_code'     => "BM-DEAL-{$yr}-{$num}{$rand}",
        'tracking_code' => "BM-{$yr}-{$num}-{$rand}",
        'access_token'  => bin2hex(random_bytes(16)),
    ];
}

/**
 * Idempotent schema installer & migrator for Deal Vault tables and email_messages upgrade.
 */
function ensureDealVaultSchema($pdo) {
    static $done = false;
    if ($done || !$pdo) {
        return;
    }
    if (function_exists('isSchemaBootstrapped') && isSchemaBootstrapped('deal_vault_v2')) {
        $done = true;
        return;
    }

    try {
        // 1. Deals master table
        $pdo->exec("CREATE TABLE IF NOT EXISTS `deals` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `deal_code` VARCHAR(48) NOT NULL UNIQUE,
            `tracking_code` VARCHAR(48) NOT NULL UNIQUE,
            `inquiry_id` INT NULL,
            `legacy_shipment_id` INT NULL,
            `buyer_name` VARCHAR(120) NOT NULL,
            `company_name` VARCHAR(160) NOT NULL DEFAULT '',
            `country` VARCHAR(80) NOT NULL DEFAULT '',
            `commodity` VARCHAR(120) NOT NULL,
            `grade_spec` VARCHAR(120) NOT NULL DEFAULT '',
            `contracted_mt` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
            `incoterm` VARCHAR(40) NOT NULL DEFAULT 'FOB Karachi',
            `payment_terms` VARCHAR(80) NOT NULL DEFAULT 'Irrevocable L/C at Sight',
            `unit_price_usd` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
            `port_of_loading` VARCHAR(100) NOT NULL DEFAULT 'Karachi Port / Port Qasim',
            `port_of_discharge` VARCHAR(120) NOT NULL DEFAULT '',
            `vessel_name` VARCHAR(120) NOT NULL DEFAULT '',
            `bill_of_lading` VARCHAR(80) NOT NULL DEFAULT '',
            `form_e_ref` VARCHAR(80) NOT NULL DEFAULT '',
            `gd_number` VARCHAR(80) NOT NULL DEFAULT '',
            `current_stage_key` VARCHAR(40) NOT NULL DEFAULT 'contract_lc',
            `current_stage_label` VARCHAR(80) NOT NULL DEFAULT 'Contract & L/C Verified',
            `estimated_departure` DATE NULL,
            `is_public_demo` TINYINT(1) NOT NULL DEFAULT 0,
            `buyer_public_notes` TEXT NULL,
            `internal_notes` TEXT NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX `idx_inquiry_id` (`inquiry_id`),
            INDEX `idx_stage` (`current_stage_key`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        // 2. Link deals to physical stockpiles
        $pdo->exec("CREATE TABLE IF NOT EXISTS `deal_stockpile_allocations` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `deal_id` INT NOT NULL,
            `stockpile_id` INT NOT NULL,
            `allocated_mt` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
            `lot_code` VARCHAR(80) NOT NULL DEFAULT '',
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY `uniq_deal_stockpile` (`deal_id`, `stockpile_id`),
            INDEX `idx_deal_id` (`deal_id`),
            INDEX `idx_stockpile_id` (`stockpile_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        // 3. 7-stage milestone tracker per deal
        $pdo->exec("CREATE TABLE IF NOT EXISTS `deal_milestones` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `deal_id` INT NOT NULL,
            `stage_key` VARCHAR(40) NOT NULL,
            `stage_order` TINYINT NOT NULL,
            `stage_title` VARCHAR(100) NOT NULL,
            `status` ENUM('Pending','In Progress','Awaiting Verification','Verified Complete') NOT NULL DEFAULT 'Pending',
            `buyer_summary` TEXT NULL,
            `internal_remarks` TEXT NULL,
            `started_at` DATETIME NULL,
            `completed_at` DATETIME NULL,
            `verified_by` VARCHAR(100) NULL,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY `uniq_deal_stage` (`deal_id`, `stage_key`),
            INDEX `idx_deal_order` (`deal_id`, `stage_order`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        // 4. Structured quantitative measurements per milestone
        $pdo->exec("CREATE TABLE IF NOT EXISTS `deal_measurements` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `deal_id` INT NOT NULL,
            `milestone_id` INT NOT NULL,
            `metric_key` VARCHAR(60) NOT NULL,
            `metric_label` VARCHAR(100) NOT NULL,
            `metric_value` VARCHAR(160) NOT NULL DEFAULT '',
            `unit` VARCHAR(30) NOT NULL DEFAULT '',
            `source_ref` VARCHAR(100) NOT NULL DEFAULT '',
            `is_buyer_visible` TINYINT(1) NOT NULL DEFAULT 1,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY `uniq_milestone_metric` (`milestone_id`, `metric_key`),
            INDEX `idx_deal_metrics` (`deal_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        // 5. Content-addressed physical asset blobs (SHA-256 deduplicated)
        $pdo->exec("CREATE TABLE IF NOT EXISTS `file_blobs` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `sha256` CHAR(64) NOT NULL UNIQUE,
            `storage_driver` VARCHAR(20) NOT NULL DEFAULT 'local',
            `storage_key` VARCHAR(255) NOT NULL,
            `thumb_key` VARCHAR(255) NULL,
            `mime_type` VARCHAR(120) NOT NULL,
            `original_ext` VARCHAR(16) NOT NULL,
            `byte_size` BIGINT UNSIGNED NOT NULL DEFAULT 0,
            `orig_client_bytes` BIGINT UNSIGNED NOT NULL DEFAULT 0,
            `ref_count` INT NOT NULL DEFAULT 1,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        // 6. Deal documents & photos linked to blobs with versioning and visibility
        $pdo->exec("CREATE TABLE IF NOT EXISTS `deal_documents` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `deal_id` INT NOT NULL,
            `milestone_id` INT NOT NULL,
            `blob_id` INT NOT NULL,
            `doc_category` VARCHAR(60) NOT NULL,
            `title` VARCHAR(180) NOT NULL,
            `original_filename` VARCHAR(180) NOT NULL,
            `doc_version` INT NOT NULL DEFAULT 1,
            `doc_status` ENUM('Draft','Under Review','Verified Final','Superseded') NOT NULL DEFAULT 'Draft',
            `visibility` ENUM('internal_only','buyer_portal','public_sample') NOT NULL DEFAULT 'internal_only',
            `is_locked` TINYINT(1) NOT NULL DEFAULT 0,
            `uploaded_by` VARCHAR(100) NOT NULL DEFAULT 'Admin',
            `verified_by` VARCHAR(100) NULL,
            `verified_at` DATETIME NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX `idx_deal_docs` (`deal_id`, `milestone_id`),
            INDEX `idx_doc_vis` (`visibility`, `doc_category`),
            INDEX `idx_blob_id` (`blob_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        // 7. Buyer portal tokenized links
        $pdo->exec("CREATE TABLE IF NOT EXISTS `deal_access_tokens` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `deal_id` INT NOT NULL,
            `token` VARCHAR(64) NOT NULL UNIQUE,
            `label` VARCHAR(80) NOT NULL DEFAULT 'Primary Buyer Link',
            `is_active` TINYINT(1) NOT NULL DEFAULT 1,
            `last_viewed_at` DATETIME NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX `idx_token_deal` (`deal_id`, `is_active`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        // 7b. Upgrade deals table with lifecycle state, sealing audit & stockpile deduction guard
        $dealCols = $pdo->query("SHOW COLUMNS FROM `deals`")->fetchAll(PDO::FETCH_COLUMN);
        if (!in_array('deal_state', $dealCols, true)) {
            $pdo->exec("ALTER TABLE `deals` ADD COLUMN `deal_state` ENUM('Active','Disputed','Closed Settled','Archived') NOT NULL DEFAULT 'Active' AFTER `current_stage_label`");
        }
        if (!in_array('sealed_at', $dealCols, true)) {
            $pdo->exec("ALTER TABLE `deals` ADD COLUMN `sealed_at` DATETIME NULL AFTER `deal_state`");
        }
        if (!in_array('sealed_by', $dealCols, true)) {
            $pdo->exec("ALTER TABLE `deals` ADD COLUMN `sealed_by` VARCHAR(100) NULL AFTER `sealed_at`");
        }
        if (!in_array('stockpile_deducted', $dealCols, true)) {
            $pdo->exec("ALTER TABLE `deals` ADD COLUMN `stockpile_deducted` TINYINT(1) NOT NULL DEFAULT 0 AFTER `sealed_by`");
        }

        // 7c. Partial Shipments / Multi-Vessel Dispatch Lots
        $pdo->exec("CREATE TABLE IF NOT EXISTS `deal_lots` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `deal_id` INT NOT NULL,
            `lot_code` VARCHAR(60) NOT NULL,
            `tonnage_mt` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
            `vessel_name` VARCHAR(120) NOT NULL DEFAULT '',
            `bill_of_lading` VARCHAR(80) NOT NULL DEFAULT '',
            `etd` DATE NULL,
            `arrival_date` DATE NULL,
            `outturn_dmt` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
            `lot_status` ENUM('Staged','Loaded','In Transit','Discharged') NOT NULL DEFAULT 'Staged',
            `notes` VARCHAR(255) NOT NULL DEFAULT '',
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX `idx_lot_deal` (`deal_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        // 7d. Tranche Payments & Settlement Ledger
        $pdo->exec("CREATE TABLE IF NOT EXISTS `deal_payments` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `deal_id` INT NOT NULL,
            `tranche_label` VARCHAR(100) NOT NULL,
            `tranche_type` ENUM('Advance','Provisional','Final Settlement','Credit/Debit Note') NOT NULL DEFAULT 'Provisional',
            `expected_usd` DECIMAL(14,2) NOT NULL DEFAULT 0.00,
            `received_usd` DECIMAL(14,2) NOT NULL DEFAULT 0.00,
            `payment_ref` VARCHAR(100) NOT NULL DEFAULT '',
            `status` ENUM('Pending','Received','Settled') NOT NULL DEFAULT 'Pending',
            `paid_date` DATE NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX `idx_pay_deal` (`deal_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        // 7e. Append-only Audit Trail Log
        $pdo->exec("CREATE TABLE IF NOT EXISTS `deal_audit_log` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `deal_id` INT NOT NULL,
            `actor_name` VARCHAR(100) NOT NULL,
            `actor_role` VARCHAR(40) NOT NULL DEFAULT 'Owner',
            `action_type` VARCHAR(60) NOT NULL,
            `summary` VARCHAR(255) NOT NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX `idx_audit_deal` (`deal_id`, `created_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        // 7f. Scoped Field Supervisor / Clearing Agent Mobile Upload Links
        $pdo->exec("CREATE TABLE IF NOT EXISTS `deal_field_links` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `deal_id` INT NOT NULL,
            `stage_key` VARCHAR(40) NOT NULL,
            `token` VARCHAR(64) NOT NULL UNIQUE,
            `assignee_label` VARCHAR(100) NOT NULL DEFAULT 'Field Supervisor / C&F Agent',
            `is_active` TINYINT(1) NOT NULL DEFAULT 1,
            `uploads_count` INT NOT NULL DEFAULT 0,
            `last_used_at` DATETIME NULL,
            `expires_at` DATETIME NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX `idx_fl_deal` (`deal_id`, `is_active`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        // 8. Upgrade email_messages columns from TEXT (64KB cap) to MEDIUMTEXT safely
        try {
            $colCheck = $pdo->query("SHOW COLUMNS FROM `email_messages` WHERE Field IN ('body_text','body_html')")->fetchAll(PDO::FETCH_ASSOC);
            foreach ($colCheck as $col) {
                if (strtolower((string)($col['Type'] ?? '')) === 'text') {
                    $field = $col['Field'];
                    $pdo->exec("ALTER TABLE `email_messages` MODIFY COLUMN `{$field}` MEDIUMTEXT NULL");
                }
            }
        } catch (Exception $e) {
            error_log('email_messages column upgrade note: ' . $e->getMessage());
        }

        // 9. Safe multi-row migration from legacy shipments without overwriting live production rows
        syncLegacyShipmentsIntoDeals($pdo);

        if (function_exists('markSchemaBootstrapped')) {
            markSchemaBootstrapped('deal_vault_v2');
        }
        $done = true;
    } catch (Exception $e) {
        error_log('ensureDealVaultSchema error: ' . $e->getMessage());
    }
}

/**
 * Seeds the 7 milestones and headline mineral measurements for a newly created deal.
 */
function initializeDealMilestonesAndPresets($pdo, $dealId, $commodityName, $preFill = []) {
    $stages = getDealStageDefinitions();
    $presets = getMineralAssayPresets($commodityName);

    $insM = $pdo->prepare("INSERT IGNORE INTO `deal_milestones`
        (`deal_id`, `stage_key`, `stage_order`, `stage_title`, `status`)
        VALUES (?, ?, ?, ?, ?)");

    $insMetric = $pdo->prepare("INSERT IGNORE INTO `deal_measurements`
        (`deal_id`, `milestone_id`, `metric_key`, `metric_label`, `metric_value`, `unit`, `is_buyer_visible`)
        VALUES (?, ?, ?, ?, ?, ?, ?)");

    foreach ($stages as $s) {
        $initStatus = ($s['key'] === 'contract_lc' && !empty($preFill['mark_contract_active']))
            ? 'In Progress'
            : ($preFill['stage_statuses'][$s['key']] ?? 'Pending');

        $insM->execute([$dealId, $s['key'], $s['order'], $s['title'], $initStatus]);

        $mIdStmt = $pdo->prepare("SELECT id FROM `deal_milestones` WHERE deal_id = ? AND stage_key = ? LIMIT 1");
        $mIdStmt->execute([$dealId, $s['key']]);
        $milestoneId = (int)$mIdStmt->fetchColumn();
        if ($milestoneId <= 0) {
            continue;
        }

        $metrics = $s['metrics'];
        if ($s['key'] === 'sgs_assay') {
            $metrics = array_merge($metrics, $presets);
        }

        foreach ($metrics as $m) {
            $k = $m['key'];
            $val = (string)($preFill['metrics'][$k] ?? '');
            $isBuyerVis = in_array($k, ['unit_price_usd', 'royalty_challan', 'settled_usd', 'prc_ref'], true) ? 0 : 1;
            $insMetric->execute([$dealId, $milestoneId, $k, $m['label'], $val, $m['unit'], $isBuyerVis]);
        }
    }
}

/**
 * Records an append-only audit entry for compliance accountability.
 */
function logDealAudit($pdo, $dealId, $actionType, $summary, $actorName = null, $actorRole = null) {
    if (!$pdo || $dealId <= 0) return;
    try {
        $name = $actorName ?: ($_SESSION['bm_admin_name'] ?? 'System');
        $role = $actorRole ?: ($_SESSION['bm_admin_role'] ?? 'Owner');
        $pdo->prepare("INSERT INTO `deal_audit_log` (`deal_id`, `actor_name`, `actor_role`, `action_type`, `summary`) VALUES (?, ?, ?, ?, ?)")
            ->execute([$dealId, substr((string)$name, 0, 100), substr((string)$role, 0, 40), substr((string)$actionType, 0, 60), substr((string)$summary, 0, 255)]);
    } catch (Exception $e) {
        error_log('logDealAudit notice: ' . $e->getMessage());
    }
}

/**
 * Safely migrates all unlinked rows from legacy `shipments` into `deals` without inventing sample metrics on real shipments.
 */
function syncLegacyShipmentsIntoDeals($pdo) {
    try {
        $rows = $pdo->query("SELECT * FROM `shipments` ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);
        if (!$rows) {
            return;
        }

        $chk = $pdo->prepare("SELECT id FROM `deals` WHERE tracking_code = ? OR legacy_shipment_id = ? LIMIT 1");
        foreach ($rows as $legacy) {
            $tracking = trim((string)($legacy['tracking_code'] ?? ''));
            if ($tracking === '') {
                $tracking = 'BM-EXP-' . date('Y') . '-' . str_pad((string)$legacy['id'], 3, '0', STR_PAD_LEFT);
            }

            $chk->execute([$tracking, (int)$legacy['id']]);
            if ($chk->fetchColumn()) {
                continue; // already migrated
            }

            $isSample = ($tracking === 'BM-EXP-2026-081') ? 1 : 0;
            $numMt    = (float)preg_replace('/[^0-9.]/', '', (string)($legacy['tonnage'] ?? '0'));

            $stageKey = 'contract_lc';
            $legacyStage = (string)($legacy['status_stage'] ?? 'Contract & L/C Verified');
            foreach (getDealStageDefinitions() as $def) {
                if (strcasecmp($def['legacy_stage'], $legacyStage) === 0) {
                    $stageKey = $def['key'];
                    break;
                }
            }
            if ($legacyStage === 'Transit to Karachi Port' || $legacyStage === 'Port Warehouse Stacking') {
                $stageKey = 'yard_staging';
            } elseif ($legacyStage === 'In Maritime Transit' || $legacyStage === 'Vessel Loaded') {
                $stageKey = 'vessel_bl';
            }

            $dealState = ($stageKey === 'discharge_closeout') ? 'Closed Settled' : 'Active';
            $dealCode  = $isSample ? 'BM-DEAL-2026-081' : ('BM-DEAL-L' . (int)$legacy['id']);

            $ins = $pdo->prepare("INSERT INTO `deals`
                (`deal_code`, `tracking_code`, `legacy_shipment_id`, `buyer_name`, `company_name`, `country`, `commodity`, `contracted_mt`,
                 `port_of_loading`, `port_of_discharge`, `vessel_name`, `bill_of_lading`, `current_stage_key`, `current_stage_label`,
                 `deal_state`, `estimated_departure`, `is_public_demo`, `buyer_public_notes`)
                VALUES (?, ?, ?, ?, ?, '', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

            $ins->execute([
                $dealCode,
                $tracking,
                (int)$legacy['id'],
                $legacy['buyer_name'] ?: ($isSample ? 'Sample Trade Account' : 'Client Account'),
                $legacy['company_name'] ?: '',
                $legacy['commodity'] ?: 'Chromite Lumps',
                $numMt > 0 ? $numMt : ($isSample ? 5000.00 : 0.00),
                $legacy['port_of_loading'] ?: 'Karachi Port / Port Qasim',
                $legacy['port_of_discharge'] ?: '',
                $legacy['vessel_name'] ?? '',
                $legacy['bill_of_lading'] ?? '',
                $stageKey,
                $legacyStage,
                $dealState,
                $legacy['estimated_departure'] ?? null,
                $isSample,
                $legacy['notes'] ?: '',
            ]);

            $dealId = (int)$pdo->lastInsertId();
            $stageOrderMap = ['contract_lc' => 1, 'mine_sourcing' => 2, 'yard_staging' => 3, 'sgs_assay' => 4, 'customs_stuffing' => 5, 'vessel_bl' => 6, 'discharge_closeout' => 7];
            $curOrder = $stageOrderMap[$stageKey] ?? 1;
            $stageStatuses = [];
            foreach ($stageOrderMap as $k => $ord) {
                if ($ord < $curOrder) $stageStatuses[$k] = 'Verified Complete';
                elseif ($ord === $curOrder) $stageStatuses[$k] = ($stageKey === 'discharge_closeout' ? 'Verified Complete' : 'In Progress');
                else $stageStatuses[$k] = 'Pending';
            }

            // Use real values only for live rows; reserve sample demo showcase fields strictly for BM-EXP-2026-081
            $seedMetrics = [
                'contracted_mt'     => $numMt > 0 ? (string)$numMt : '',
                'port_of_loading'   => $legacy['port_of_loading'] ?: 'Karachi Port',
                'port_of_discharge' => $legacy['port_of_discharge'] ?? '',
                'vessel_name'       => $legacy['vessel_name'] ?? '',
                'bl_number'         => $legacy['bill_of_lading'] ?? '',
            ];
            if ($isSample) {
                $seedMetrics = array_merge($seedMetrics, [
                    'incoterm'            => 'FOB Karachi',
                    'payment_terms'       => 'Irrevocable L/C at Sight',
                    'source_district'     => 'Muslim Bagh',
                    'dispatched_mt'       => '2525',
                    'yard_bay'            => 'Karachi Port Export Yard #3',
                    'yard_weighbridge_mt' => '2512',
                ]);
            }

            initializeDealMilestonesAndPresets($pdo, $dealId, (string)($legacy['commodity'] ?? 'Chromite Lumps'), [
                'stage_statuses' => $stageStatuses,
                'metrics'        => $seedMetrics,
            ]);

            $pdo->prepare("INSERT INTO `deal_access_tokens` (`deal_id`, `token`, `label`) VALUES (?, ?, ?)")
                ->execute([$dealId, bin2hex(random_bytes(16)), $isSample ? 'Public Demo Token' : 'Migrated Buyer Link']);

            logDealAudit($pdo, $dealId, 'legacy_import', "Safely synced legacy shipment {$tracking} without modifying source row", 'System Migration', 'System');
        }
    } catch (Exception $e) {
        error_log('syncLegacyShipmentsIntoDeals notice: ' . $e->getMessage());
    }
}

/**
 * Calculates deal compliance readiness, missing mandatory checklists, cross-stage reconciliation & exception alerts.
 */
function computeDealReadiness($pdo, $dealId) {
    $defs = getDealStageDefinitions();
    $mandatoryMap = [];
    foreach ($defs as $d) {
        foreach ($d['mandatory_docs'] as $cat) {
            $mandatoryMap[$cat] = [
                'stage' => $d['stage_title'] ?? $d['title'],
                'label' => $d['doc_categories'][$cat] ?? $cat,
            ];
        }
    }
    $totalMandatory = count($mandatoryMap);

    $stmtM = $pdo->prepare("SELECT status FROM `deal_milestones` WHERE deal_id = ?");
    $stmtM->execute([$dealId]);
    $milestones = $stmtM->fetchAll(PDO::FETCH_COLUMN);
    $verifiedStages = 0;
    foreach ($milestones as $st) {
        if ($st === 'Verified Complete') $verifiedStages++;
    }

    $stmtD = $pdo->prepare("SELECT doc_category, doc_status FROM `deal_documents` WHERE deal_id = ? AND doc_status != 'Superseded'");
    $stmtD->execute([$dealId]);
    $docs = $stmtD->fetchAll(PDO::FETCH_ASSOC);

    $verifiedCats = [];
    $presentCats  = [];
    $totalDocs    = count($docs);
    foreach ($docs as $doc) {
        $c = $doc['doc_category'];
        if (isset($mandatoryMap[$c])) {
            $presentCats[$c] = true;
            if ($doc['doc_status'] === 'Verified Final') {
                $verifiedCats[$c] = true;
            }
        }
    }

    $missingDocs = [];
    $unverifiedDocs = [];
    foreach ($mandatoryMap as $catKey => $meta) {
        if (!isset($presentCats[$catKey])) {
            $missingDocs[] = $meta['label'] . ' (' . $meta['stage'] . ')';
        } elseif (!isset($verifiedCats[$catKey])) {
            $unverifiedDocs[] = $meta['label'] . ' (' . $meta['stage'] . ')';
        }
    }

    $pct = count($milestones) > 0 ? (int)round(($verifiedStages / count($milestones)) * 100) : 0;

    return [
        'total_stages'        => count($milestones),
        'verified_stages'     => $verifiedStages,
        'stage_pct'           => $pct,
        'mandatory_total'     => $totalMandatory,
        'mandatory_uploaded'  => count($presentCats),
        'mandatory_verified'  => count($verifiedCats),
        'total_active_docs'   => $totalDocs,
        'missing_docs'        => $missingDocs,
        'unverified_docs'     => $unverifiedDocs,
    ];
}

/**
 * Computes automated weight shrinkage, dry-weight sanity, payment balance & executive exception flags.
 */
function computeDealIntelligence($pdo, $deal, $readiness = null) {
    $dealId = (int)$deal['id'];
    if ($readiness === null) {
        $readiness = computeDealReadiness($pdo, $dealId);
    }

    $metStmt = $pdo->prepare("SELECT metric_key, metric_value FROM `deal_measurements` WHERE deal_id = ?");
    $metStmt->execute([$dealId]);
    $m = [];
    foreach ($metStmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $m[$r['metric_key']] = (float)preg_replace('/[^0-9.\-]/', '', (string)$r['metric_value']);
    }

    $mineMt    = $m['dispatched_mt'] ?? 0;
    $yardMt    = $m['yard_weighbridge_mt'] ?? 0;
    $wetMt     = ($m['wet_mt'] ?? 0) > 0 ? $m['wet_mt'] : $yardMt;
    $moistPct  = $m['moisture_pct'] ?? 0;
    $dryMt     = $m['dry_mt'] ?? 0;
    $outturnMt = $m['outturn_dmt'] ?? 0;

    $shrinkageMt  = ($mineMt > 0 && $yardMt > 0) ? round($yardMt - $mineMt, 2) : null;
    $shrinkagePct = ($mineMt > 0 && $yardMt > 0) ? round((($mineMt - $yardMt) / $mineMt) * 100, 2) : null;
    $shrinkAlert  = ($shrinkagePct !== null && $shrinkagePct > 2.0);

    $expectedDry  = ($wetMt > 0 && $moistPct >= 0) ? round($wetMt * (1 - ($moistPct / 100)), 2) : null;
    $dryMismatch  = ($expectedDry > 0 && $dryMt > 0 && abs($expectedDry - $dryMt) / $expectedDry > 0.015);
    $oceanDiffMt  = ($dryMt > 0 && $outturnMt > 0) ? round($outturnMt - $dryMt, 2) : null;

    // Payments tranche rollup
    $payStmt = $pdo->prepare("SELECT COALESCE(SUM(expected_usd),0) AS exp_sum, COALESCE(SUM(received_usd),0) AS rec_sum FROM `deal_payments` WHERE deal_id = ?");
    $payStmt->execute([$dealId]);
    $paySums = $payStmt->fetch(PDO::FETCH_ASSOC);

    $basisMt       = $dryMt > 0 ? $dryMt : ((float)$deal['contracted_mt'] > 0 ? (float)$deal['contracted_mt'] : 0);
    $contractTotal = round($basisMt * (float)$deal['unit_price_usd'], 2);
    $receivedUsd   = round((float)($paySums['rec_sum'] ?? 0), 2);
    $settledVal    = ($m['settled_usd'] ?? 0) > 0 ? $m['settled_usd'] : $contractTotal;
    $balanceDueUsd = $settledVal > 0 ? round(max(0, $settledVal - $receivedUsd), 2) : 0;

    // Exceptions for active deals
    $alerts = [];
    if ($shrinkAlert) {
        $alerts[] = ['type' => 'shrinkage', 'level' => 'danger', 'text' => "Transit loss {$shrinkagePct}% (Mine {$mineMt} MT -> Yard {$yardMt} MT)"];
    }
    if ($dryMismatch) {
        $alerts[] = ['type' => 'dmt_check', 'level' => 'warning', 'text' => "DMT check: Wet {$wetMt} @ {$moistPct}% moist expects ~{$expectedDry} DMT vs entered {$dryMt} DMT"];
    }
    if (($deal['deal_state'] ?? 'Active') === 'Active') {
        if (!empty($deal['estimated_departure'])) {
            $daysToEtd = (strtotime($deal['estimated_departure']) - time()) / 86400;
            if ($daysToEtd <= 5 && $readiness['mandatory_uploaded'] < $readiness['mandatory_total']) {
                $etdLabel = $daysToEtd < 0 ? 'Past ETD' : ceil($daysToEtd) . 'd to ETD';
                $alerts[] = ['type' => 'cutoff', 'level' => 'danger', 'text' => "{$etdLabel}: Missing " . count($readiness['missing_docs']) . " mandatory docs"];
            }
        }
        $stageKey = $deal['current_stage_key'] ?? '';
        if (($stageKey === 'discharge_closeout' || $outturnMt > 0) && $balanceDueUsd > 0) {
            $alerts[] = ['type' => 'balance', 'level' => 'warning', 'text' => "Discharged / Outturn logged with \$" . number_format($balanceDueUsd, 0) . " receivable open"];
        }
    }

    return [
        'mine_mt'          => $mineMt,
        'yard_mt'          => $yardMt,
        'shrinkage_mt'     => $shrinkageMt,
        'shrinkage_pct'    => $shrinkagePct,
        'shrink_alert'     => $shrinkAlert,
        'expected_dry_mt'  => $expectedDry,
        'dry_mt'           => $dryMt,
        'dry_mismatch'     => $dryMismatch,
        'outturn_mt'       => $outturnMt,
        'ocean_diff_mt'    => $oceanDiffMt,
        'contract_usd'     => $settledVal,
        'received_usd'     => $receivedUsd,
        'balance_due_usd'  => $balanceDueUsd,
        'alerts'           => $alerts,
    ];
}
