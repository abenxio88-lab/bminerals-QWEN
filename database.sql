-- ===================================================================
-- BALOCHISTAN MINERALS - COMPLETE OPERATIONAL DATABASE SCHEMA
-- Compatible with Hostinger MySQL (phpMyAdmin)
-- Character Set: utf8mb4 (Full Unicode support for all languages)
-- ===================================================================

CREATE TABLE IF NOT EXISTS `admin_users` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `username` VARCHAR(60) NOT NULL UNIQUE,
  `password_hash` VARCHAR(255) NOT NULL,
  `full_name` VARCHAR(120) NOT NULL DEFAULT 'Admin',
  `email` VARCHAR(150) NOT NULL,
  `role` VARCHAR(30) NOT NULL DEFAULT 'Owner',
  `last_login` DATETIME NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Initial Seed Admin (Default Login: admin / Admin@BM2026)
-- Password will auto-rehash to secure bcrypt on first login
INSERT INTO `admin_users` (`id`, `username`, `password_hash`, `full_name`, `email`, `role`)
VALUES (1, 'admin', 'Admin@BM2026', 'Balochistan Minerals Admin', 'info@balochistanminerals.com', 'Owner')
ON DUPLICATE KEY UPDATE `username` = `username`;


-- ===================================================================
-- INQUIRIES & RFQs TABLE
-- Tracks all buyer leads from website, grade requested, MT, and status
-- ===================================================================
CREATE TABLE IF NOT EXISTS `inquiries` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `buyer_name` VARCHAR(120) NOT NULL,
  `company_name` VARCHAR(160) NULL,
  `country` VARCHAR(100) NULL DEFAULT 'International',
  `email` VARCHAR(150) NOT NULL,
  `whatsapp` VARCHAR(60) NOT NULL,
  `inquiry_type` VARCHAR(60) NOT NULL DEFAULT 'Product Quote',
  `source` VARCHAR(60) NOT NULL DEFAULT 'Website Form',
  `email_message_id` VARCHAR(255) NULL,
  `mineral_type` VARCHAR(100) NOT NULL DEFAULT 'General Inquiry',
  `grade_spec` VARCHAR(120) NULL,
  `quantity` VARCHAR(100) NULL,
  `destination_port` VARCHAR(150) NULL DEFAULT 'FOB Karachi',
  `message` TEXT NULL,
  `status` ENUM('New', 'Contacted', 'Assay Sent', 'Quoted', 'Sample Sent', 'Contract Signed', 'Closed', 'Archived') NOT NULL DEFAULT 'New',
  `spot_price_quoted` DECIMAL(10,2) NULL,
  `currency` VARCHAR(10) NOT NULL DEFAULT 'USD',
  `internal_notes` TEXT NULL,
  `ip_address` VARCHAR(45) NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_status` (`status`),
  INDEX `idx_created` (`created_at`),
  UNIQUE KEY `uk_email_msg_id` (`email_message_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Sample Initial RFQ for Demonstration
INSERT INTO `inquiries` (`buyer_name`, `company_name`, `country`, `email`, `whatsapp`, `inquiry_type`, `mineral_type`, `grade_spec`, `quantity`, `destination_port`, `message`, `status`, `spot_price_quoted`, `internal_notes`)
VALUES 
('Zhang Wei', 'Tianjin Metallurgical Corp', 'China', 'zhang@tianjin-metal.cn', '+8613800138000', 'Product Quote', 'Chromite Ore', '42% - 44% Cr2O3 Lumps', '5,000 MT', 'CIF Tianjin', 'Looking for monthly supply of metallurgical chromite. Please provide assay and spot price.', 'New', NULL, 'High intent buyer from Tianjin smelter corridor.'),
('Tariq Mansoor', 'Gulf Drilling & Fluids', 'UAE', 't.mansoor@gulfdrilling.ae', '+971501234567', 'Product Quote', 'Barite', 'API Standard 4.2 SG Ground', '2,500 MT', 'FOB Karachi', 'Require drilling grade barite in 1.5 MT jumbo bags. Need delivery next month.', 'Assay Sent', 145.00, 'Assay sent via WhatsApp. Awaiting review.');


-- ===================================================================
-- REVIEWS & TESTIMONIALS TABLE
-- Moderated buyer reviews that display on the public website
-- ===================================================================
CREATE TABLE IF NOT EXISTS `reviews` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `reviewer_name` VARCHAR(120) NOT NULL,
  `company_name` VARCHAR(160) NULL,
  `country` VARCHAR(100) NULL,
  `rating` TINYINT NOT NULL DEFAULT 5,
  `product_reviewed` VARCHAR(120) NOT NULL,
  `review_text` TEXT NOT NULL,
  `publish_consent` TINYINT(1) NOT NULL DEFAULT 1,
  `status` ENUM('Pending', 'Approved', 'Rejected') NOT NULL DEFAULT 'Pending',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seed Approved Sample Reviews
INSERT INTO `reviews` (`reviewer_name`, `company_name`, `country`, `rating`, `product_reviewed`, `review_text`, `publish_consent`, `status`)
VALUES 
('Hakan Demir', 'Anatolia Steel & Mining', 'Turkey', 5, 'Chromite Lumps (44%)', 'Consistently meets Certificate of Analysis specifications. Seamless port handling and prompt shipping documentation.', 1, 'Approved'),
('Sultan Al-Otaibi', 'Arabian Industrial Resources', 'Saudi Arabia', 5, 'API Grade Barite', 'High specific gravity barite supplied on schedule. Professional communication directly with their export director on WhatsApp.', 1, 'Approved');


-- ===================================================================
-- INDICATIVE MINERAL PRICES (GRADE BY GRADE)
-- Owner updates weekly market price indications
-- ===================================================================
CREATE TABLE IF NOT EXISTS `mineral_prices` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `mineral_name` VARCHAR(100) NOT NULL,
  `grade_name` VARCHAR(120) NOT NULL,
  `purity_spec` VARCHAR(100) NOT NULL,
  `price_low_usd` DECIMAL(10,2) NOT NULL,
  `price_high_usd` DECIMAL(10,2) NOT NULL,
  `unit` VARCHAR(20) NOT NULL DEFAULT '1 Ton',
  `delivery_terms` VARCHAR(60) NOT NULL DEFAULT 'FOB Karachi',
  `notes` VARCHAR(255) NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `sort_order` INT NOT NULL DEFAULT 0,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `mineral_prices` (`mineral_name`, `grade_name`, `purity_spec`, `price_low_usd`, `price_high_usd`, `unit`, `delivery_terms`, `notes`, `sort_order`)
VALUES
('Chromite', 'High Grade Lumps', '46% - 48% Cr2O3', 210.00, 235.00, '1 Ton', 'FOB Karachi', 'Muslim Bagh origin, low silica', 1),
('Chromite', 'Standard Metallurgical', '42% - 44% Cr2O3', 175.00, 195.00, '1 Ton', 'FOB Karachi', 'Bulk or 1.5 MT bags', 2),
('Barite', 'Drilling Grade', 'API Standard 4.20+ SG', 135.00, 155.00, '1 Ton', 'FOB Karachi', 'Ground powder API 13A Spec', 3),
('Iron Ore', 'Magnetite / Hematite', '62% - 64% Fe Basis', 95.00, 115.00, '1 Ton', 'FOB Karachi', 'Kharan district source', 4),
('Copper Ore', 'Chagai Exploration Lumps', '15% - 22% Cu Run-of-Mine', 480.00, 580.00, '1 Ton', 'FOB Karachi', 'Subject to lot assay verification', 5),
('Antimony', 'Stibnite Lumps / Ore', '60% - 65% Sb Grade', 14500.00, 16800.00, '1 Ton', 'FOB Karachi', 'Qila Abdullah / Chagai source, high antimony purity', 6),
('Fluorspar', 'Metallurgical & Ceramic', '85% - 90% CaF2 Basis', 240.00, 285.00, '1 Ton', 'FOB Karachi', 'Kalat / Koh-e-Maran deposit, low silica', 7),
('Magnesite', 'Raw Industrial Lumps', '45% - 47% MgO Basis', 120.00, 145.00, '1 Ton', 'FOB Karachi', 'Refractory & metallurgical flux use', 8),
('Gypsum', 'Cement & Industrial Grade', '90% - 95% CaSO4·2H2O', 35.00, 48.00, '1 Ton', 'FOB Karachi', 'Bulk shipments, low moisture', 9),
('Silver Steam Marble', 'Quarry Blocks / Slabs', 'First Choice Architectural', 380.00, 520.00, '1 Ton', 'FOB Karachi', 'Gang-saw sized blocks, crack-free', 10),
('Coal', 'Balochistan Steam Coal', '5,800 - 6,200 kcal/kg GCV', 140.00, 165.00, '1 Ton', 'Ex-Mine Quetta', 'Dukki / Sor-Range mines, low ash', 11),
('Manganese Ore', 'Metallurgical Grade', '38% - 44% Mn Basis', 160.00, 195.00, '1 Ton', 'FOB Karachi', 'Lasbela district deposit', 12);


-- ===================================================================
-- DYNAMIC FAQS & BUYER Q&A
-- ===================================================================
CREATE TABLE IF NOT EXISTS `faqs` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `category` VARCHAR(60) NOT NULL DEFAULT 'Export & Shipping',
  `question` VARCHAR(255) NOT NULL,
  `answer` TEXT NOT NULL,
  `sort_order` INT NOT NULL DEFAULT 0,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `faqs` (`category`, `question`, `answer`, `sort_order`)
VALUES
('Export & Shipping', 'What is the Minimum Order Quantity (MOQ)?', 'Our standard export MOQ is 500 Metric Tons for bulk commodities and 100 Metric Tons for containerized cargo (20ft FCL in 1.5 MT jumbo bags).', 1),
('Quality & Assays', 'Can we inspect materials prior to vessel loading?', 'Yes. We welcome international third-party inspection agencies including SGS, Alfred H. Knight (AHK), and Alex Stewart International for sampling, lot testing, and Certificate of Analysis (COA) verification at Karachi port.', 2),
('Payment Terms', 'What commercial payment terms are accepted?', 'We operate under standard international trade terms compliant with State Bank of Pakistan regulations: 100% Irrevocable Confirmed Letter of Credit (L/C at sight) from a prime international bank, or 30% Advance T/T deposit with 70% payable against clean shipping documents.', 3),
('Logistics', 'Which ports do you export from?', 'We export primarily through Port Muhammad Bin Qasim and Karachi Port Trust (KPT), with direct rail and dedicated fleet road transit corridors from our Balochistan mining sites.', 4);


-- ===================================================================
-- STAGE 4: STOCKPILES & MARITIME EXPORT SHIPMENT TRACKING
-- ===================================================================

CREATE TABLE IF NOT EXISTS `stockpiles` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `mineral_name` VARCHAR(100) NOT NULL,
  `location` VARCHAR(120) NOT NULL,
  `tonnage_available` INT NOT NULL DEFAULT 0,
  `grade_spec` VARCHAR(120) NOT NULL,
  `status` ENUM('Ready for Loading', 'In Transit to Port', 'Mine Sourcing', 'Assay Testing') NOT NULL DEFAULT 'Ready for Loading',
  `last_survey_date` DATE NULL,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `stockpiles` (`mineral_name`, `location`, `tonnage_available`, `grade_spec`, `status`, `last_survey_date`)
VALUES
('Chromite Lumps', 'Karachi Port Trust (KPT) Yard', 3500, '42% - 44% Cr2O3 (Low Silica)', 'Ready for Loading', CURDATE()),
('API Grade Barite', 'Port Qasim Industrial Terminal', 5000, '4.20+ Specific Gravity (API 13A)', 'Ready for Loading', CURDATE()),
('Chromite High Grade', 'Muslim Bagh Active Mine Site', 2000, '46% - 48% Cr2O3', 'Mine Sourcing', CURDATE()),
('Iron Ore Magnetite', 'Kharan Regional Sourcing Yard', 8000, '62% - 64% Fe Basis', 'In Transit to Port', CURDATE());

CREATE TABLE IF NOT EXISTS `shipments` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `tracking_code` VARCHAR(40) NOT NULL UNIQUE,
  `buyer_name` VARCHAR(120) NOT NULL,
  `company_name` VARCHAR(160) NOT NULL,
  `commodity` VARCHAR(120) NOT NULL,
  `tonnage` VARCHAR(80) NOT NULL,
  `vessel_name` VARCHAR(120) NULL,
  `bill_of_lading` VARCHAR(80) NULL,
  `port_of_loading` VARCHAR(100) NOT NULL DEFAULT 'Karachi Port / Port Qasim',
  `port_of_discharge` VARCHAR(120) NOT NULL,
  `status_stage` ENUM('Contract & L/C Verified', 'Mine Sourcing & Crushing', 'Transit to Karachi Port', 'Port Warehouse Stacking', 'SGS / Assay Inspection', 'Customs Clearance', 'Vessel Loaded', 'In Maritime Transit', 'Discharged & Completed') NOT NULL DEFAULT 'Contract & L/C Verified',
  `estimated_departure` DATE NULL,
  `notes` TEXT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `shipments` (`tracking_code`, `buyer_name`, `company_name`, `commodity`, `tonnage`, `vessel_name`, `bill_of_lading`, `port_of_discharge`, `status_stage`, `notes`)
VALUES
('BM-EXP-2026-081', 'Zhang Wei', 'Tianjin Metallurgical Corp', 'Chromite Ore Lumps (44%)', '5,000 MT', 'MV PACIFIC TRADER', 'BL-KHI-TJ-9921', 'Tianjin Port, China', 'Port Warehouse Stacking', 'SGS sampling scheduled for Tuesday. Clean shipping documents under 100% L/C at sight.');


-- --------------------------------------------------------
-- Table structure for `buyer_prospects` (Global Buyer Directory & Outbound Prospector)
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `buyer_prospects` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `company_name` VARCHAR(150) NOT NULL,
  `contact_person` VARCHAR(100),
  `country` VARCHAR(80) NOT NULL,
  `city` VARCHAR(80),
  `email` VARCHAR(120),
  `phone` VARCHAR(50),
  `whatsapp` VARCHAR(50),
  `target_mineral` VARCHAR(100) NOT NULL,
  `buyer_type` VARCHAR(80) DEFAULT 'Smelter / End User',
  `annual_demand` VARCHAR(100),
  `target_grade` VARCHAR(100),
  `status` VARCHAR(40) DEFAULT 'Identified',
  `last_contacted_at` DATETIME NULL,
  `notes` TEXT,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_target_mineral` (`target_mineral`),
  INDEX `idx_status` (`status`),
  INDEX `idx_country` (`country`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for `visitor_radar` (Live Visitor & Country Demand Tracker)
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `visitor_radar` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `ip_address` VARCHAR(45) NOT NULL,
  `country` VARCHAR(80) DEFAULT 'International',
  `city` VARCHAR(80) DEFAULT 'Unknown',
  `page_url` VARCHAR(255),
  `mineral_interest` VARCHAR(80),
  `referrer` VARCHAR(255),
  `user_agent` VARCHAR(255),
  `visited_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_visited_at` (`visited_at`),
  INDEX `idx_mineral` (`mineral_interest`),
  INDEX `idx_country` (`country`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- --------------------------------------------------------
-- Table structure for `email_messages` (Two-Way Commercial Email Desk Archive)
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `email_messages` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `inquiry_id` INT NULL,
  `direction` ENUM('inbound', 'outbound') NOT NULL DEFAULT 'outbound',
  `from_email` VARCHAR(150) NOT NULL,
  `to_email` VARCHAR(150) NOT NULL,
  `cc_email` VARCHAR(255) NULL,
  `subject` VARCHAR(255) NOT NULL,
  `body_text` TEXT NOT NULL,
  `body_html` TEXT NULL,
  `attachments` TEXT NULL,
  `status` VARCHAR(50) DEFAULT 'Sent',
  `message_id` VARCHAR(255) NULL,
  `sent_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_inquiry` (`inquiry_id`),
  INDEX `idx_direction` (`direction`),
  INDEX `idx_to_email` (`to_email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
