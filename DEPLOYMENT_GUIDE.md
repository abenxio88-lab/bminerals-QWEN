# 🚀 BALOCHISTAN MINERALS - TRADE DESK PORTAL ACTIVATION GUIDE

Everything has been created cleanly in your project with zero external dependencies and zero added monthly costs.

---

## 📁 WHAT WAS CREATED

1. **`bm-before-portla.zip`**: Complete 225 MB backup of 9,133 files before any modifications.
2. **`database.sql`**: Full MySQL schema (Tables: `admin_users`, `inquiries`, `reviews`, `mineral_prices`, `faqs`).
3. **`admin/`** (The Owner's Portal):
   - [db.php](file:///C:/Users/Lenovo/Documents/GitHub/bminerals-QWEN/admin/db.php): Hostinger database connection & WhatsApp URL generator.
   - [auth.php](file:///C:/Users/Lenovo/Documents/GitHub/bminerals-QWEN/admin/auth.php): Session protection and CSRF security.
   - [login.php](file:///C:/Users/Lenovo/Documents/GitHub/bminerals-QWEN/admin/login.php): Executive light-luxury sign-in screen.
   - [dashboard.php](file:///C:/Users/Lenovo/Documents/GitHub/bminerals-QWEN/admin/dashboard.php): Main RFQ Lead Command Center + 1-Click WhatsApp Direct + Spot Quote modal.
   - [reviews.php](file:///C:/Users/Lenovo/Documents/GitHub/bminerals-QWEN/admin/reviews.php): Testimonials moderation (Approve / Reject).
   - [prices.php](file:///C:/Users/Lenovo/Documents/GitHub/bminerals-QWEN/admin/prices.php): Weekly indicative mineral pricing manager.
   - [faqs.php](file:///C:/Users/Lenovo/Documents/GitHub/bminerals-QWEN/admin/faqs.php): Buyer Q&A and policy manager.
4. **`api/`**:
   - [submit-inquiry.php](file:///C:/Users/Lenovo/Documents/GitHub/bminerals-QWEN/api/submit-inquiry.php): Replaces Web3Forms. Saves RFQs to MySQL, alerts owner, generates direct WhatsApp chat.
   - [submit-review.php](file:///C:/Users/Lenovo/Documents/GitHub/bminerals-QWEN/api/submit-review.php): Saves reviews for owner approval.
5. **`contact.html`**: Upgraded to submit directly to the new backend and prompt WhatsApp connection upon submission.

---

## ⚡ 3-STEP ACTIVATION ON HOSTINGER

### Step 1: Import Database into Hostinger
1. Log into your **Hostinger hPanel**.
2. Go to **Databases → MySQL Databases**.
3. Create a database (e.g. `u123456_minerals`) and a user with a strong password. Note down:
   - Database Name
   - Username
   - Password
4. Open **phpMyAdmin** for that database.
5. Click the **Import** tab at the top.
6. Choose the file `database.sql` from your project and click **Import** (or paste its text into the **SQL** tab and click **Go**).

---

### Step 2: Add Database Credentials in `admin/db.php`
Open [admin/db.php](file:///C:/Users/Lenovo/Documents/GitHub/bminerals-QWEN/admin/db.php) and update lines 10-12 with your Hostinger credentials:
```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'YOUR_HOSTINGER_DB_NAME');
define('DB_USER', 'YOUR_HOSTINGER_DB_USER');
define('DB_PASS', 'YOUR_HOSTINGER_PASSWORD');
```

---

### Step 3: Deploy to Hostinger
Because your repository has an automated FTP GitHub Action (`.github/workflows/deploy.yml`), deploying is as simple as running:
```bash
git add .
git commit -m "Deploy Balochistan Minerals Trade Desk Portal"
git push origin main
```
GitHub will automatically compile your CSS and upload all new files directly to your live Hostinger website!

*(Alternatively, you can drag and drop the `admin` folder, `api` folder, and `contact.html` directly into Hostinger's File Manager inside `public_html`)*.

---

## 🔑 ACCESSING THE PORTAL

Once deployed, the owner can log in from any phone or computer at:
👉 **`https://balochistanminerals.com/admin/`**

* **Default Username:** `admin`
* **Default Password:** `Admin@BM2026`

*(The password will automatically rehash to secure encrypted bcrypt upon first successful login).*
