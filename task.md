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

### 9. 2FA OTP Authentication, Registered Email Workflow, Rate Limiting & Session Management
- [x] **9.1 Two-Factor Authentication (2FA) via 6-Digit Email OTP (`owner/login.php`)**
  - *Action*: Implemented a 2-step owner login process. After owner enters valid username and password, portal generates a cryptographically secure 6-digit random code dispatched to owner's email. Login session is only established after successful OTP verification.
  - *Status*: ✅ Completed & Verified.
- [x] **9.2 Registered Email Fallback & Persistent Unverified Nag Prompt (`owner/nav.php`)**
  - *Action*: If no dedicated registered email is configured, the system transparently falls back to `owner_email` (receiving email). A prominent amber alert banner is displayed across all owner portal pages until an official email is registered and verified via 6-digit OTP (`registered_owner_email_verified = '1'`).
  - *Status*: ✅ Completed & Verified.
- [x] **9.3 Official Registered Email Verification Workflow (`owner/settings.php`)**
  - *Action*: Added Card 2 in settings allowing the owner to update and verify their official registered email address. Requires entering a 6-digit OTP sent to the new email address before saving. Once verified, the nag banner disappears immediately.
  - *Status*: ✅ Completed & Verified.
- [x] **9.4 Forgot Password via Registered Email OTP (`owner/login.php` & `owner/settings.php`)**
  - *Action*: Added "Forgot Password" workflows on both login and settings pages. Owners can request a 6-digit recovery OTP dispatched to their registered email to set a new password without needing the old password.
  - *Status*: ✅ Completed & Verified.
- [x] **9.5 Brute-Force Rate Limiting & Accidental Double-Click Protection**
  - *Action*: Implemented login attempt rate limiting (max 5 failed attempts per 15 minutes per IP, with automatic lockout) and OTP dispatch cooldown (60 seconds between resend requests + hourly IP cap). Added global double-touch/double-click prevention scripts across all forms, disabling buttons (`disabled`, `opacity-60`, `pointer-events-none`) for 2.5-3 seconds on submit to prevent accidental double submissions.
  - *Status*: ✅ Completed & Verified.
- [x] **9.6 Hashed OTP Storage (SHA-256) & Expired OTP Daily Cleanup (`otps` Table)**
  - *Action*: All OTPs are stored in the database exclusively as cryptographic SHA-256 hashes (`otp_hash`). Plaintext OTPs are never stored in the DB. Implemented `cleanExpiredOtps()` to purge expired and previous-day OTPs automatically at midnight rollover and opportunistically on OTP generation.
  - *Status*: ✅ Completed & Verified.
- [x] **9.7 Login Attempt Security Audit Log (`login_logs` Table)**
  - *Action*: Every authentication attempt (successful login, failed credentials, OTP dispatched, password reset, rate-limited lockout) is recorded with timestamp, username, IP address, user-agent, status badge, and client device fingerprint. Displayed in an audit table on `owner/settings.php`.
  - *Status*: ✅ Completed & Verified.
- [x] **9.8 Client Device Fingerprinting & Interactive Session Management (`active_sessions` Table)**
  - *Action*: On login, client device fingerprint (combining screen dimensions, color depth, canvas rendering, timezone, and user-agent) is computed and stored. The owner settings page displays all active device sessions with browser, OS/platform, IP, fingerprint, and last activity time. Includes "Revoke" button to remotely kick out individual devices and "Log Out All Other Devices" to terminate all remote sessions at once.
  - *Status*: ✅ Completed & Verified.

### 10. Centralized Environment Configuration (`.env` & `.env.example`)
- [x] **10.1 Structured `.env` & `.env.example` Creation**
  - *Action*: Created `.env` and `.env.example` containing all configurable parameters: app environment (`APP_ENV`, `APP_DEBUG`, `APP_URL`, `APP_TIMEZONE`, `APP_SECRET_KEY`), database options (`DB_DRIVER`, `SQLITE_FILE`, `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASS`, `DB_CHARSET`), owner defaults (`DEFAULT_ADMIN_USER`, `DEFAULT_ADMIN_PASS`, `DEVELOPER_EMAIL`, `DEFAULT_CENTER_NAME`, `DEFAULT_OWNER_PHONE`, `DEFAULT_OWNER_EMAIL`), mail settings (`MAIL_METHOD`, `SMTP_HOST`, `SMTP_PORT`, `SMTP_SECURE`, `SMTP_USER`, `SMTP_PASS`), upload limits (`MAX_FILE_SIZE_MB`, `ALLOWED_EXTENSIONS`), and session/security parameters (`SESSION_LIFETIME`, `MIDNIGHT_POSTPONE_WINDOW`, `LOGIN_RATE_LIMIT_ATTEMPTS`, `OTP_EXPIRY_MINUTES`).
  - *Status*: ✅ Completed & Verified.
- [x] **10.2 Native Zero-Dependency `.env` Parser in `includes/config.php`**
  - *Action*: Implemented a clean, dependency-free `.env` file parser that populates `putenv()`, `$_ENV`, and `$_SERVER` without requiring external Composer libraries.
  - *Status*: ✅ Completed & Verified.
- [x] **10.3 Automatic Fallbacks in Email Dispatcher (`includes/mailer.php`)**
  - *Action*: Enhanced `sendViaSmtpSocket()` and `sendGeneralEmail()` so that if database settings are empty or default, credentials and delivery methods smoothly fall back to values provided in `.env`.
  - *Status*: ✅ Completed & Verified.
- [x] **10.4 Web Security & Git Exclusion Verification**
  - *Action*: Verified `.env` is ignored by Git in `.gitignore` and protected against direct browser HTTP access with HTTP 403 Forbidden in `.htaccess`. Created `.env.example` as a version-controlled deployment template.
  - *Status*: ✅ Completed & Verified.

### 11. Production MySQL Compatibility & Email Deliverability Hardening
- [x] **11.1 Fix MySQL Native Prepared Statement Placeholder Count Mismatch**
  - *Action*: In `registerActiveSession()` ([`includes/auth.php`](file:///home/satyaveer/Desktop/Projects/csc-app/includes/auth.php)), replaced duplicate `:now` and `:ip` tokens in `INSERT ... ON DUPLICATE KEY UPDATE` with unique parameters and MySQL's standard `VALUES(col)` expression. Updated `updateSetting()` ([`includes/helpers.php`](file:///home/satyaveer/Desktop/Projects/csc-app/includes/helpers.php)) to use `VALUES(setting_value)` on MySQL and `excluded.setting_value` on SQLite. Enabled `PDO::ATTR_EMULATE_PREPARES => true` in [`includes/db.php`](file:///home/satyaveer/Desktop/Projects/csc-app/includes/db.php) to ensure client-side parameter substitution, eliminating `SQLSTATE[HY093]` fatal errors across MySQL and MariaDB.
  - *Status*: ✅ Completed & Verified.
- [x] **11.2 Graceful `mbstring` Extension Fallback**
  - *Action*: Replaced raw `mb_substr()` calls in [`includes/auth.php`](file:///home/satyaveer/Desktop/Projects/csc-app/includes/auth.php) with `function_exists('mb_substr') ? mb_substr(...) : substr(...)` so environments without `php-mbstring` do not throw fatal undefined function errors.
  - *Status*: ✅ Completed & Verified.
- [x] **11.3 Strict Server Domain Sender for cPanel SPF Deliverability**
  - *Action*: In [`includes/mailer.php`](file:///home/satyaveer/Desktop/Projects/csc-app/includes/mailer.php), configured native `php_mail` to strictly use the server's own domain address (`noreply@` + domain) as `From:` header while setting `Reply-To:` to the owner's actual contact email. Prevents cPanel Exim from dropping outgoing emails due to SPF / anti-spoofing violations against `@gmail.com` addresses.
  - *Status*: ✅ Completed & Verified.

---

## 📈 Automated Verification Suite Results

* **Execution Command**: `python3 /home/satyaveer/.gemini/antigravity-cli/brain/cc5ca20f-3011-4b7b-97c4-141042fa14fa/scratch/verify_features.py`
* **Test Count**: 9 Automated End-to-End Test Suites (100% Passing)

| Test Suite | Coverage & Validations | Result |
| :--- | :--- | :--- |
| **TEST 1: Owner Login Step 1** | Credentials verification, 2FA OTP generation, fallback email resolution (`vyasthperson@gmail.com`), SHA-256 hash storage in DB, audit logging (`otp_dispatched`) | ✅ PASS |
| **TEST 2: Invalid 2FA OTP** | Bad code rejection, attempt counter incrementation, failure audit logging | ✅ PASS |
| **TEST 3: Valid 2FA OTP Verification** | Timing-safe verification, active session creation in `active_sessions`, device fingerprint association, successful login audit logging | ✅ PASS |
| **TEST 4: Unverified Email Nag Prompt** | Banner presence check across owner portal, link to settings `#registeredEmailSection` | ✅ PASS |
| **TEST 5: Registered Email Setup** | OTP dispatch to new email, 60-second cooldown rate limit enforcement, OTP verification, DB update (`verified = 1`), instant disappearance of nag banner | ✅ PASS |
| **TEST 6: Forgot Password from Login** | Recovery OTP dispatch to registered email, new password submission (min 6 chars + confirmation), password hash update in DB, old password rejection, new password login | ✅ PASS |
| **TEST 7: Brute-Force Rate Limiting** | 5 consecutive failed logins trigger 15-minute IP lockout with descriptive error message | ✅ PASS |
| **TEST 8: Session Management** | Concurrent multi-device session tracking, remote device session revocation, immediate kickoff on next request (`login.php?reason=revoked`) | ✅ PASS |
| **TEST 9: Expired OTP Midnight Purge** | Automatic cleanup of expired OTPs from `otps` table via `cleanExpiredOtps()` | ✅ PASS |

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
