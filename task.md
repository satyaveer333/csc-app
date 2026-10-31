# CSC Digital Solutions - Issue Remediation & Feature Tracking Taskboard

This document serves as the master persistence and connection point between AI sessions. Any subsequent session should review this file first to understand current progress, architectural decisions, and verification status.

---

## 📌 Project Overview
* **Application**: Digital Solutions - CSC & Citizen Services Web Portal
* **Stack**: PHP 8.2 (Apache 2.4 mod_php), SQLite (default) / MySQL (optional), Tailwind CSS, Vanilla JS
* **Portals**:
  * Public Citizen Portal: `http://localhost:8080/` (`/index.php`, `/api/*`)
  * Isolated Owner Portal: `http://localhost:8080/owner/` (`/owner/*`, credentials: `admin` / `admin123`)
* **Database File**: `data/csc_database.sqlite`
* **Test Suite**: Scratch script at `/home/satyaveer/.gemini/antigravity-cli/brain/6465c756-5729-4afe-9b4f-efdbc3242a23/scratch/test_suite.py`

---

## 🎯 Master Remediation Checklist (All Completed & Verified)

### 1. Critical & Blocking Bugs
- [x] **1.1 Fix `updateSetting()` Fatal SQL Error in `includes/helpers.php`**
  - *Problem*: MySQL `ON DUPLICATE KEY UPDATE` prepared unconditionally before SQLite check, causing fatal PDOException (HTTP 500) whenever settings were saved.
  - *Fix*: Branch driver check (`$pdo->getAttribute(PDO::ATTR_DRIVER_NAME)`) before `$pdo->prepare()`, execute SQLite `ON CONFLICT(setting_key) DO UPDATE` or MySQL `ON DUPLICATE KEY UPDATE`, and invalidate static `$settingsCache` via `getSettings($pdo, true)`.
  - *Status*: ✅ Verified (Profile & Pricing settings update seamlessly).
- [x] **1.2 Fix Default Password Backdoor & Insecure Password Change**
  - *Problem*: `ownerLogin()` in `includes/auth.php` logged in with default `admin123` even after password change and silently overwritten the owner's custom hash back to default. `owner/settings.php` bypassed current password check if `admin123` was supplied.
  - *Fix*: Removed automatic password overwrite in `ownerLogin()`. Default fallback is strictly confined to initial setup when no hash exists. Enforced strict `password_verify` in `owner/settings.php`.
  - *Status*: ✅ Verified (Backdoor closed, custom password required once set).
- [x] **1.3 Block Direct Web Access to Citizens' Uploaded Documents**
  - *Problem*: `uploads/.htaccess` only disabled PHP script execution; citizens' private documents in `uploads/prints/` were directly accessible via HTTP without authentication.
  - *Fix*: Created `uploads/prints/.htaccess` denying all direct HTTP requests (`Require all denied`). All document downloads safely route exclusively through authenticated `owner/download.php`.
  - *Status*: ✅ Verified (Direct HTTP request returns `403 Forbidden`; authenticated download succeeds).

### 2. Security Vulnerabilities
- [x] **2.1 Fix Stored XSS in Order Tracking & Sanitize Print Submission Inputs**
  - *Problem*: `customer_name` stored raw in `api/submit_print.php` and concatenated directly into `container.innerHTML` in `index.php`.
  - *Fix*: Sanitized and trimmed inputs in `api/submit_print.php` (`strip_tags`, `trim`). Added `escapeHtml()` in `index.php` tracking renderer before updating DOM.
  - *Status*: ✅ Verified (Script payload `<script>alert("XSS")</script>` rendered safely as plain text).
- [x] **2.2 Implement CSRF Protection Across All Owner Actions**
  - *Problem*: No CSRF tokens or verification in POST handlers across `forms.php`, `inventory.php`, `print_orders.php`, and `settings.php`.
  - *Fix*: Embedded `<input type="hidden" name="csrf_token" value="<?= e(getCsrfToken()) ?>">` in all admin forms. Enforced `verifyCsrfToken($_POST['csrf_token'] ?? '')` in all POST action blocks with flash error and redirect.
  - *Status*: ✅ Verified (Forged POST requests with missing or invalid CSRF tokens strictly rejected).
- [x] **2.3 Prevent Email Header Injection in `api/submit_print.php` & `includes/mailer.php`**
  - *Problem*: `customer_email` was inserted into `Reply-To:` without `FILTER_VALIDATE_EMAIL`, allowing newline carriage return header injection.
  - *Fix*: Strictly validated `customer_email` using `filter_var(..., FILTER_VALIDATE_EMAIL)`. Sanitized headers in `includes/mailer.php` with `preg_replace('/[\r\n]+/', '', ...)`.
  - *Status*: ✅ Verified.

### 3. Functional & Logical Fixes
- [x] **3.1 Make Token ID & Phone Tracking Case-Insensitive & Flexible**
  - *Problem*: Citizens typing lowercase `csc-prn-30-xxxx` got "No orders found" due to case-sensitive SQLite comparison.
  - *Fix*: Updated SQL to `UPPER(token_id) = :upperQ OR REPLACE(customer_phone, ' ', '') LIKE :likePhone OR customer_phone = :q`.
  - *Status*: ✅ Verified (Both lowercase tokens and phone numbers without spaces resolve orders).
- [x] **3.2 Implement Dashboard "Open" Link Filtering in `owner/print_orders.php`**
  - *Problem*: `print_orders.php?order=ID` was ignored by `print_orders.php`.
  - *Fix*: Added `$_GET['order']` filter handling in `owner/print_orders.php` with a clear "Filter Active" alert and reset link.
  - *Status*: ✅ Verified (Clicking Open on Dashboard filters to the exact order).
- [x] **3.3 Fix Multi-line Protocol Handling in Custom SMTP Socket**
  - *Problem*: Single `fgets()` call left multi-line `EHLO` responses in socket buffer, breaking `STARTTLS` and authentication on standard SMTP servers.
  - *Fix*: Implemented RFC 5321 multiline SMTP response reader loop that awaits status code completion (`XYZ ` vs `XYZ-`), plus byte dot-stuffing for message body (`preg_replace('/^\./m', '..', $body)`).
  - *Status*: ✅ Verified.
- [x] **3.4 Fix MySQL Compatibility Issues (Cross-DB ANSI Standard)**
  - *Problem*: `date('now')` in `index.php` breaks in MySQL. `MAX(0, quantity + delta)` in `owner/inventory.php` throws group function syntax error in MySQL.
  - *Fix*: Passed PHP `:today` parameter into `index.php` query. Used driver-specific scalar check (`MAX` on SQLite, `GREATEST` on MySQL) in `owner/inventory.php`.
  - *Status*: ✅ Verified across SQLite with MySQL fallback queries.
- [x] **3.5 Complete Form Attachments Feature**
  - *Problem*: Database column `attachment_file` and `uploads/forms` folder existed but lacked upload UI and download handler.
  - *Fix*: Added file upload handling to `owner/forms.php` Add/Edit modal, uploaded files stored in `uploads/forms/`, and added "Download Official Form / Notification" button on citizen modal in `index.php`.
  - *Status*: ✅ Verified (Attachments upload, replace old files, and download properly).
- [x] **3.6 Add "Cancelled" Orders Filter Tab in `owner/print_orders.php`**
  - *Problem*: Orders marked "Cancelled" disappeared from status tabs and only showed under "All".
  - *Fix*: Added `cancelled` status count query and dedicated filter tab with red badge indicator.
  - *Status*: ✅ Verified.
- [x] **3.7 Implement Owner Session Idle Expiration**
  - *Problem*: `$_SESSION['last_activity']` was stored but never checked.
  - *Fix*: Added 30-minute idle check in `requireOwnerLogin()` in `includes/auth.php`, with `?timeout=1` banner on `owner/login.php`.
  - *Status*: ✅ Verified.
- [x] **3.8 Fix Tailwind Typo in `owner/nav.php`**
  - *Problem*: `py-0.2` was an invalid class.
  - *Fix*: Updated to `py-0.5`.
  - *Status*: ✅ Verified.

### 4. Database Migration & Production Server Readiness (MySQL)
- [x] **4.1 Git Ignore & Untrack SQLite Database File**
  - *Action*: Configured `.gitignore` to ignore `csc_database.sqlite`, `data/csc_database.sqlite`, `*.sqlite`, `*.db`, and citizen upload directories (`uploads/prints/*`). Removed `data/csc_database.sqlite` from the Git tracking index via `git rm --cached` while preserving the local physical file.
  - *Status*: ✅ Completed.
- [x] **4.2 Provide Production-Ready MySQL Schema with Seed Data (`schema.sql`)**
  - *Action*: Exported complete MySQL schema (`schema.sql`) with `InnoDB` engine, `utf8mb4` character set, auto-increment primary keys, performance indexes (`status`, `category`, `phone`, `token_id`), and all 4 tables (`settings`, `forms`, `inventory`, `print_orders`) prepopulated with initial data and bcrypt password hash.
  - *Status*: ✅ Completed.
- [x] **4.3 Dynamic MySQL / SQLite Configuration (`includes/config.php`)**
  - *Action*: Enhanced database configuration to support both environment variable injection (`getenv('DB_DRIVER')`, `getenv('DB_HOST')`, etc.) and direct editing.
  - *Status*: ✅ Completed.
- [x] **4.4 Engine Options & Informative MySQL Connection Diagnostics (`includes/db.php`)**
  - *Action*: Added `ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci` table options for MySQL driver in `initDatabaseTables()`. Updated connection exception handler to display actionable troubleshooting diagnostics tailored to MySQL (credential check, database existence, schema import reminder).
  - *Status*: ✅ Completed.
- [x] **4.5 Server Migration Runbook for Deployed Commit (`ff91a30`)**
  - *Action*: Documented exact server-side steps to pull latest code, resolve local SQLite untrack conflict, import `schema.sql` in phpMyAdmin/MySQL CLI, switch config to MySQL, and verify server PHP extensions (`pdo_mysql`).
  - *Status*: ✅ Completed.

### 5. UI Modernization, Field Checks, Security Gate & Responsive Scaling
- [x] **5.1 Weekday Checklist & Time Selector Menu for Opening Hours**
  - *Action*: Replaced raw opening hours text field in `owner/settings.php` with 7 individual weekday checkboxes (Mon-Sun), quick preset buttons (Mon-Sat, Mon-Fri, All 7 Days), Start & End time pickers, and live customer preview renderer. Formats and stores standard human-readable string alongside structured schedule settings.
  - *Status*: ✅ Completed.
- [x] **5.2 Strict 10-Digit Phone Number Enforcement**
  - *Action*: Enforced 10-digit mobile number validation across owner profile (`owner/settings.php`), citizen print submission (`index.php`, `api/submit_print.php`), and tracking lookup (`api/track_order.php`). Added HTML pattern `[0-9]{10}`, `maxlength="10"`, client-side non-digit stripping, and strict PHP regex validation (`preg_match('/^[0-9]{10}$/')`).
  - *Status*: ✅ Completed.
- [x] **5.3 Developer Email Confirmation Gate for Email Delivery Method**
  - *Action*: Enforced developer verification check when altering `mail_method` or configuring SMTP in `owner/settings.php`. Changes are strictly blocked unless confirmed by providing developer email.
  - *Status*: ✅ Completed.
- [x] **5.4 Comprehensive Data Sanitization & Input Validation**
  - *Action*: Applied `strip_tags()`, `trim()`, string length limits, date consistency checks (`last_date >= start_date` in `owner/forms.php`), URL validation (`filter_var(..., FILTER_VALIDATE_URL)`), numeric non-negative clamps (`max(0, ...)` in `owner/inventory.php` & pricing rates), and email validation (`FILTER_VALIDATE_EMAIL`) across all owner forms and citizen APIs.
  - *Status*: ✅ Completed.
- [x] **5.5 Owner Portal Light Mode Support & Persistent Theme Switcher**
  - *Action*: Configured Tailwind CSS `darkMode: 'class'` across all owner portal pages (`owner/index.php`, `owner/forms.php`, `owner/inventory.php`, `owner/print_orders.php`, `owner/settings.php`, `owner/login.php`). Added interactive Sun/Moon theme toggle in `owner/nav.php` and `owner/login.php` with instant `localStorage` persistence and zero-flicker `<head>` initializers.
  - *Status*: ✅ Completed.
- [x] **5.6 Multi-Device Responsive Scaling (Customer & Owner Portals)**
  - *Action*: Optimized layouts, typography, flex-wrapping, modals (`w-[96%] sm:w-full max-h-[92vh]`), and tables (`overflow-x-auto`) to scale cleanly on all screen sizes from small mobile displays (320px-375px) up to ultra-wide desktop monitors.
  - *Status*: ✅ Completed.

### 6. Production Hardening: Sensitive Information Scrubbing & Error Sanitization
- [x] **6.1 Masking Developer Email from All Public & Owner Interfaces**
  - *Action*: Scrubbed the developer's raw email address from UI placeholders and error flash messages in `owner/settings.php`. Configured `DEVELOPER_EMAIL` constant in `includes/config.php` (with `getenv('DEVELOPER_EMAIL')` support) so that verification logic functions without ever exposing the address to users or administrators.
  - *Status*: ✅ Completed.
- [x] **6.2 Neutral Login Placeholders & Elimination of Default Credential Hints**
  - *Action*: Removed `placeholder="e.g. admin"` and `placeholder="Enter owner password"` hints from `owner/login.php`, replacing them with neutral `placeholder="Enter your username"` and `placeholder="Enter your password"`. Configured `DEFAULT_ADMIN_USER` and `DEFAULT_ADMIN_PASS` in `includes/config.php` to accept environment overrides.
  - *Status*: ✅ Completed.
- [x] **6.3 Cloaking Database Connection Failures (Zero Information Disclosure)**
  - *Action*: Replaced verbose PDO exception screen in `includes/db.php` that formerly echoed `$e->getMessage()`, driver names, and `DB_NAME`. Now logs detailed connection errors privately to PHP `error_log()` and renders a clean, styled HTTP 503 "Service Temporarily Unavailable" screen.
  - *Status*: ✅ Completed.
- [x] **6.4 API Exception Sanitization (`api/submit_print.php`)**
  - *Action*: Removed raw `$e->getMessage()` output from the JSON error response on failed print submissions. Orders errors are logged to `error_log()` and replaced with a clean, user-friendly message (`"An error occurred while saving your order. Please try again or visit our center."`).
  - *Status*: ✅ Completed.
- [x] **6.5 Sanitizing Production Schema Seed Data (`schema.sql`)**
  - *Action*: Replaced developer personal email address in `schema.sql` seed records with generic `csc.center@example.com` for production MySQL installations.
  - *Status*: ✅ Completed.

### 7. Database Sensitive Information Hashing & At-Rest Encryption
- [x] **7.1 Owner Login Password Hashing (Bcrypt / `PASSWORD_DEFAULT`)**
  - *Action*: Confirmed owner passwords in `settings.admin_password` are strictly stored using salted one-way bcrypt hashes (`$2y$10$...`) via `password_hash()` and verified via `password_verify()`. No plaintext passwords are ever persisted or logged.
  - *Status*: ✅ Completed.
- [x] **7.2 Developer Authorization Secret Hashing (SHA-256 in DB & Config)**
  - *Action*: Replaced all plaintext developer email checks with cryptographic SHA-256 hash comparison. Stored `developer_email_hash` in `settings` table (`0dc6d2bc23045997492ae6458d7626550d2af7f225663466561342c5d71384f7`). The developer confirmation check hashes the user input on the fly and verifies using `hash_equals()`. Even with full database access, an attacker cannot read the developer email.
  - *Status*: ✅ Completed.
- [x] **7.3 Reversible Credential At-Rest Encryption (`smtp_pass` with OpenSSL AES-256-CBC + HMAC)**
  - *Action*: Because SMTP servers require plaintext credentials to authenticate over TLS/SSL (making one-way hashing infeasible), implemented authenticated symmetric encryption for `smtp_pass` in `includes/helpers.php` (`encryptSensitiveData()` / `decryptSensitiveData()`). Stored in DB as `ENC:<base64(iv . hmac . ciphertext)>`. Decrypted in memory only when establishing SMTP socket connection in `includes/mailer.php`.
  - *Status*: ✅ Completed.
- [x] **7.4 Citizen Document Random Hash Storage (`stored_name`)**
  - *Action*: Document filenames uploaded by citizens are randomized with timestamp and cryptographic random hex (`doc_YYYYMMDD_HHMMSS_<8hex>.<ext>`) in `print_orders.files_json`, preventing directory indexing, IDOR, or enumeration attacks.
  - *Status*: ✅ Completed.

### 8. Midnight Session Auto-Logout & 30-Minute Dynamic Postponement Window
- [x] **8.1 Elimination of Daytime 30-Minute Inactivity Timeout**
  - *Action*: Removed the daytime 30-minute idle session termination in `includes/auth.php`. Operators now remain authenticated all day long without needing to re-login multiple times.
  - *Status*: ✅ Completed.
- [x] **8.2 Timezone & Server Session Lifetime Configuration**
  - *Action*: Configured `date_default_timezone_set('Asia/Kolkata')` and set `ini_set('session.gc_maxlifetime', 172800)` (48 hours) in `includes/config.php` to prevent premature server-side garbage collection of active sessions during working hours.
  - *Status*: ✅ Completed.
- [x] **8.3 Midnight Rollover Check with 30-Minute Rolling Postponement**
  - *Action*: Implemented calendar day comparison (`date('Y-m-d') !== $_SESSION['login_date']`) in `requireOwnerLogin()`. If the owner is actively interacting with the website around midnight (activity within the last 30 minutes), the auto-logout is dynamically postponed by 30 minutes on each interaction.
  - *Status*: ✅ Completed.
- [x] **8.4 Inactivity Detection and Graceful Redirect**
  - *Action*: If the owner stops interacting for >30 minutes past midnight (or opens the tab the next morning), the session terminates and redirects to `login.php?reason=midnight`. Added an informative alert banner in `owner/login.php` welcoming the operator to sign in for the new day.
  - *Status*: ✅ Completed.

---

## 📈 Automated Verification Suite Results

* **Execution Command**: `python3 /home/satyaveer/.gemini/antigravity-cli/brain/6465c756-5729-4afe-9b4f-efdbc3242a23/scratch/test_suite.py`
* **Test Count**: 40 Automated End-to-End Tests
* **Outcome**: **40 Passed | 0 Warnings | 0 Failed** (100% Passing)

| Category | Tests Executed | Result |
| :--- | :--- | :--- |
| **1. Customer Portal** | Home Load, Isolation Check, Tracking Empty Query, Tracking Non-Existent, Print GET Rejection, Missing Inputs Validation, File Extension Restrictions, Submission & Price Calc Formula, Case-Insensitive Token Lookup, Phone Lookup | 10 / 10 PASS |
| **2. Security Controls** | SQLite Direct Access Block (403), Data `.htaccess` (403), 5x Owner Routes Auth Redirect, Unauthenticated Download Redirect, Direct Document Download Exposure Block (403), Stored XSS Prevention | 10 / 10 PASS |
| **3. Owner Authentication** | Invalid Credential Rejection, Valid Credential Login & Session Establishment | 2 / 2 PASS |
| **4. Owner Dashboard** | KPI Cards & Overview Tables, Open Order Query Param Link | 2 / 2 PASS |
| **5. Forms Manager** | Create Form, Update Form, Inactive Form Public Isolation, Delete Form | 4 / 4 PASS |
| **6. Store Inventory** | Create Item, Quick Stock Increment (+5), Negative Stock Clamping (0 floor), Delete Item | 4 / 4 PASS |
| **7. Customer Print Hub** | Order Listing, Status Transition Workflow (`Pending` -> `Ready`), WhatsApp Notification URL Generation, Secure File Download via `download.php`, Directory Traversal Block (`basename` defense) | 5 / 5 PASS |
| **8. Settings & Security** | Center Profile Update, Print Pricing Rates Dynamic Update, Strict CSRF Protection Enforcement (Missing & Tampered Token Rejection) | 3 / 3 PASS |

---

## 💡 Notes for Next AI Session
1. **Container Bind Mount**: Docker container `csc-app-container` runs on port 8080 and mounts host directory `/home/satyaveer/Desktop/Projects/csc-app` to `/var/www/html`.
2. **Database Engine**: Dual-driver architecture supporting SQLite (`data/csc_database.sqlite`, ignored in git) and MySQL (`schema.sql` provided at root with InnoDB / utf8mb4). Toggle via `DB_DRIVER` in `includes/config.php` or `DB_DRIVER` environment variable.
3. **Pristine State**: Test print records and temporary test uploads are cleaned up after test runs.
4. **Git Index**: `data/csc_database.sqlite` is untracked from Git (`git rm --cached`) so local writes do not pollute Git commits. Server uses MySQL via `schema.sql`.
5. **Strict Git Policy (User Mandate)**: The AI assistant must **NEVER** execute `git add`, `git commit`, `git push`, or `git pull`. All staging, committing, pushing, and pulling are strictly managed by the user. Do not revert commit `b0774de`.
