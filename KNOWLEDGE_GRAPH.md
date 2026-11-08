# Balochistan Minerals (Pvt) Ltd — System Architecture & Knowledge Graph

This document provides a comprehensive operational blueprint and architectural knowledge graph of the **Balochistan Minerals (Pvt) Ltd** commercial export desk platform.

---

## 1. System Knowledge Graph

```mermaid
flowchart TD
    %% Public Inbound Channels
    subgraph PUBLIC_LAYER["1. Public Client & Acquisition Tier"]
        WEB["Balochistan Minerals Web Portal<br/>(index.html / products.html)"]
        VAULT_BANNER["Gated SGS Lab Assay Vault<br/>(js/assay-vault.js)"]
        INBOUND_MODAL["1-Click Commercial RFQ Desk<br/>(js/inbound-modal.js)"]
        RADAR_TRACKER["Live Traffic Telemetry<br/>(api/track-visitor.php)"]
    end

    %% API Ingestion Gateways
    subgraph API_GATEWAY["2. Secure Ingestion & Middleware Gateway"]
        API_INQUIRY["api/submit-inquiry.php<br/>(Anti-Bot Honeypot + DB Logger)"]
        API_ASSAY["api/download-assay.php<br/>(Gated COA Dispatcher)"]
        API_RADAR["api/track-visitor.php<br/>(Country & Demand Aggregator)"]
    end

    %% Core Commercial Operations Desk
    subgraph TRADE_DESK["3. Executive Trade Desk & Communications Portal"]
        DASHBOARD["Executive Trade Desk<br/>(admin/dashboard.php)<br/>• Real-time PKT Clock<br/>• Visible Buyer Email<br/>• 1-Click WhatsApp & Quote"]
        EMAIL_DESK["Dedicated Email Reply Desk<br/>(admin/email-desk.php)<br/>• Split Reading Pane<br/>• Outbox Thread Audit"]
        SMTP_RELAY["Pure PHP Hostinger SMTP Engine<br/>(admin/smtp-mailer.php)<br/>• SSL/TLS Socket Client<br/>• Hostinger Relay (465/587)"]
        PROSPECTS["Global Buyer Prospector<br/>(admin/prospects.php)<br/>• 18 Pre-Seeded Smelters<br/>• 1-Click WhatsApp & Email Pitch"]
        RADAR_UI["Live Visitor Radar<br/>(admin/radar.php)<br/>• Real-time Country Demand"]
        PRICES["Mineral Spot Price Desk<br/>(admin/prices.php)<br/>• USD/PKR Live FX Grid"]
        STOCKPILES["Stockpiles & Export Shipments<br/>(admin/stockpiles.php)"]
        PDF_GEN["SECP Corporate PDF Generator<br/>(admin/generate-quote.php)"]
    end

    %% Persistent Storage Tier
    subgraph DATA_TIER["4. Harmonized Data Tier (MySQL - utf8mb4_unicode_ci)"]
        DB_INQ[("inquiries<br/>(RFQs, Mailboxes, Timestamps)")]
        DB_MSG[("email_messages<br/>(Audit Trail, Inbound/Outbound)")]
        DB_PROSPECT[("buyer_prospects<br/>(Smelters, Refineries, Pipeline)")]
        DB_RADAR[("visitor_radar<br/>(IPs, Country Demand, Pings)")]
        DB_PRICES[("mineral_prices<br/>(Chromite, Barite, Antimony)")]
        DB_STOCKS[("stockpiles & shipments<br/>(Mine Concessions, FOB Karachi)")]
        DB_USERS[("admin_users<br/>(Auth & CSRF Protection)")]
    end

    %% External Infrastructure Connections
    subgraph EXT_SERVICES["5. External Trade & Transport Channels"]
        HOSTINGER_SMTP["smtp.hostinger.com<br/>(Port 465 SSL / Port 587 TLS)"]
        HOSTINGER_IMAP["mail.balochistanminerals.com<br/>(Port 993 SSL - sales@ & info@)"]
        WHATSAPP_NET["WhatsApp Web & Mobile Direct API"]
        FOREIGN_BUYERS["Smelters & Traders<br/>(China, UAE, Japan, EU, USA)"]
    end

    %% Wire Flows
    WEB --> INBOUND_MODAL
    WEB --> VAULT_BANNER
    WEB --> RADAR_TRACKER

    INBOUND_MODAL --> API_INQUIRY
    VAULT_BANNER --> API_ASSAY
    RADAR_TRACKER --> API_RADAR

    API_INQUIRY --> DB_INQ
    API_ASSAY --> DB_INQ
    API_RADAR --> DB_RADAR

    DB_INQ --> DASHBOARD
    DASHBOARD --> EMAIL_DESK
    DASHBOARD --> PDF_GEN
    DASHBOARD --> WHATSAPP_NET

    EMAIL_DESK --> SMTP_RELAY
    PROSPECTS --> SMTP_RELAY
    PROSPECTS --> WHATSAPP_NET

    SMTP_RELAY --> HOSTINGER_SMTP
    HOSTINGER_SMTP --> FOREIGN_BUYERS
    HOSTINGER_IMAP --> DASHBOARD

    DB_MSG <--> EMAIL_DESK
    DB_PROSPECT <--> PROSPECTS
    DB_RADAR <--> RADAR_UI
    DB_PRICES <--> PRICES
    DB_STOCKS <--> STOCKPILES
```

---

## 2. Relational Database Schema (ERD)

All tables use `ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci`:

```mermaid
erDiagram
    inquiries ||--o{ email_messages : "has email thread"
    inquiries {
        int id PK
        string buyer_name
        string company_name
        string country
        string email
        string whatsapp
        string mineral_type
        string grade_spec
        string quantity
        string destination_port
        string source
        string status
        decimal spot_price_quoted
        text message
        text internal_notes
        timestamp created_at
    }

    email_messages {
        int id PK
        int inquiry_id FK
        enum direction
        string from_email
        string to_email
        string cc_email
        string subject
        text body_text
        text body_html
        string attachments
        string status
        string message_id
        timestamp sent_at
    }

    buyer_prospects {
        int id PK
        string company_name
        string contact_person
        string country
        string city
        string email
        string phone
        string whatsapp
        string target_mineral
        string buyer_type
        string annual_demand
        string target_grade
        string status
        text notes
        timestamp updated_at
    }

    visitor_radar {
        int id PK
        string ip_address
        string country
        string city
        string page_url
        string mineral_interest
        string referrer
        timestamp visited_at
    }

    mineral_prices {
        int id PK
        string mineral_name
        string grade_desc
        string purity_spec
        decimal price_usd_min
        decimal price_usd_max
        string price_unit
        string delivery_terms
        string origin_notes
        tinyint is_active
        timestamp updated_at
    }

    stockpiles {
        int id PK
        string mineral_type
        string grade_spec
        decimal tonnage_available
        string mine_location
        string export_readiness
        string sgs_assay_ref
        timestamp updated_at
    }
```

---

## 3. Operational Systems & File Map

* **Executive Dashboard (`admin/dashboard.php`)**: Full commercial overview, live Pakistan Standard Time (`Asia/Karachi`), visible buyer emails with 1-click clipboard copy, and landing mailbox clarity.
* **Email Desk (`admin/email-desk.php`)**: Dedicated split-pane B2B corporate communications terminal.
* **SMTP Mailer Engine (`admin/smtp-mailer.php`)**: Socket client connecting to `smtp.hostinger.com` on port 465 (SSL) / 587 (TLS).
* **Global Prospector (`admin/prospects.php`)**: 18 pre-seeded international smelter & refinery profiles with 1-click WhatsApp and email outreach.
* **Live Visitor Radar (`admin/radar.php`)**: International IP demand telemetry and real-time visitor logs.
* **Gated Lab Assay Vault (`js/assay-vault.js` & `api/download-assay.php`)**: Captures high-intent buyers seeking certified COA reports (`documents/Balochistan_Minerals_Assay_Summary.html`).
