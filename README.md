# Digital Solutions - CSC & Citizen Services Web Application

A modern, high-performance web application designed for **Digital Solutions (CSC / Jan Seva Kendra)**, fully optimized for hosting on **Serverbyt with a Blaze Plan** (StackCP / cPanel / Apache / PHP 7.4 - 8.3).

---

## 🌟 Key Features

---

## 💻 Quick Local Testing (Choose Either Method)

### Method 1: Using Docker (Recommended)
We have provided a [`Dockerfile`](file:///home/satyaveer/Desktop/Projects/csc-app/Dockerfile) and [`docker-compose.yml`](file:///home/satyaveer/Desktop/Projects/csc-app/docker-compose.yml) configured with PHP 8.2, Apache, and SQLite/MySQL extensions matching Serverbyt Blaze hosting.

1. **Start the application with Docker Compose:**
   ```bash
   docker compose up -d --build
   ```
   *(Or using standard Docker CLI: `docker build -t csc-app . && docker run -d -p 8080:80 --name csc-web csc-app`)*

2. **Open in your web browser:**
   * **Customer Portal**: [http://localhost:8080/](http://localhost:8080/)
   * **Owner Portal**: [http://localhost:8080/owner/](http://localhost:8080/owner/)
     * **Username**: `admin`
     * **Password**: `admin123`

3. **To stop the container:**
   ```bash
   docker compose down
   ```

---

### Method 2: Without Docker (Instant PHP Built-in Server)
If you have PHP installed locally on your machine, you can run it immediately without Docker:

```bash
php -S localhost:8000
```
Then visit:
* Customer: [http://localhost:8000/](http://localhost:8000/)
* Owner: [http://localhost:8000/owner/](http://localhost:8000/owner/)

---

### 1. 👥 Customer Portal (`/` or `index.php`)
- **Strictly Light Mode Only**: Crisp, clean, accessible government-style interface designed for all citizens on mobile and desktop.
- **Complete Isolation**: Contains **zero links, buttons, or references** to the Owner / Admin Portal.
- **Government & Vacancy Forms Directory**:
  - Live search by title, department, or keywords.
  - Category filters: Welfare Schemes, Recruitment & Jobs, Scholarships & Education, Health & Insurance, Certificates & Identity, Bill & Utility, etc.
  - Details Modal: Eligibility criteria, **Required Documents checklist** (what citizens must bring to the center), application deadlines, official website links, and government fees.
- **Online Document Printing via Email**:
  - Citizens can upload documents directly from their mobile phone or PC without standing in queues.
  - **Multiple Document Types Supported**: `.pdf`, `.doc`, `.docx`, `.jpg`, `.jpeg`, `.png`, `.txt`, `.xls`, `.xlsx`.
  - Print preferences: Black & White / Color, Single Sided / Duplex (Both sides), Paper size (A4, Legal, Photo Paper, A3), and Number of Copies.
  - Live price estimation calculator.
  - Direct email dispatch to the CSC owner's inbox with attachments + records saved to the portal.
  - Generates a unique **Print Order Token ID** (e.g. `CSC-PRN-30-7B9A2`) with a printable counter slip.
- **Order Tracking Lookup**:
  - Citizens can check real-time status of their print job (`Pending` -> `Printing` -> `Ready for Pickup`) using their Token ID or Phone Number.

---

### 2. 🔐 Owner / Admin Portal (`/owner/`)
- Accessed via a **distinct, separate URL** (`https://yourdomain.com/owner/`).
- Secure session-based authentication.
- **Default Login Credentials**:
  - **Username**: `admin`
  - **Password**: `admin123` *(Can be updated anytime in Owner Settings)*
- **Executive Dashboard**:
  - Key Performance Indicators (Active Forms, Pending Print Queue, Low Stock Inventory Alerts).
  - Recent online customer print requests.
  - Quick action stock adjustments (+5 restock button).
- **Forms Manager (`/owner/forms.php`)**:
  - Add new government forms, job vacancies, and welfare scheme notifications.
  - Edit, delete, and 1-click toggle visibility (`Active` / `Inactive`).
  - Pre-configure required documents checklist for citizens.
- **CSC Inventory Management (`/owner/inventory.php`)**:
  - Complete tracking of center supplies: A4 Paper Reams, Legal Paper, Glossy Photo Paper, Lamination Pouches (A4 / ID size), Blank PVC Cards, Printer Inks (Black / CMY), Stationery & Envelopes.
  - Real-time stock status with **Low Stock Warnings**.
  - One-click quick adjustment buttons (`+1`, `-1`, `+5 Restock`) for fast counter operation.
  - Automatic inventory valuation calculation (Total Cost Value & Retail Value).
- **Print Orders Hub (`/owner/print_orders.php`)**:
  - View all incoming citizen print requests.
  - **One-Click Download / Open**: Download any uploaded document (PDF, Word, Images, etc.) directly.
  - Update workflow status: `Pending` ➔ `Printing` ➔ `Ready for Pickup` ➔ `Delivered`.
  - **Direct WhatsApp Notification**: 1-click button to message the customer: *"Hello [Name], your document print order is ready for pickup at our center!"*
- **Center Profile & Settings (`/owner/settings.php`)**:
  - Center Name, VLE ID, Owner Name, Contact Phone/WhatsApp, Timings, Address.
  - **Email Notification Configuration**: Native Serverbyt PHP Mail or Custom SMTP.
  - One-click **Send Test Email** button to verify mail delivery.
  - Configurable print rates (B&W single/double, Color single/double, Lamination).
  - Change Owner Password.

---

## 🚀 Serverbyt Blaze Plan Deployment Guide

The Serverbyt Blaze Plan provides unlimited SSD storage, unlimited bandwidth, unlimited MySQL databases, and unlimited email accounts running on cloud infrastructure (StackCP / cPanel).

### Step 1: Upload Files
1. Log in to your **Serverbyt Client Portal** or **StackCP Control Panel**.
2. Go to **File Manager** (or connect via FTP / SFTP using FileZilla).
3. Navigate to the root public folder:
   - For primary domain: `public_html/` (or `public_html/csc/` if in a subfolder).
4. Upload all files from this project into the folder.

### Step 2: Database Setup (Zero-Config SQLite vs MySQL)
- **Default (Zero Setup - Works Instantly)**:
  - The application is pre-configured with **SQLite** by default.
  - You do **not** need to create a database or configure usernames/passwords. It creates the database file automatically in `data/csc_database.sqlite` and auto-populates sample government forms and inventory items on your first visit!
- **Optional (Using MySQL on Serverbyt Blaze)**:
  - If you prefer MySQL:
    1. In StackCP, open **MySQL Databases** and click **Create Database**.
    2. Note down Database Name, Database User, and Password.
    3. Open `includes/config.php` and update:
       ```php
       define('DB_DRIVER', 'mysql');
       define('DB_HOST', 'localhost');
       define('DB_NAME', 'your_serverbyt_dbname');
       define('DB_USER', 'your_serverbyt_dbuser');
       define('DB_PASS', 'your_serverbyt_dbpassword');
       ```
    4. Save the file. The database tables and initial seed data will auto-create on your first page load!

### Step 3: Setting Up Email for Print Orders
Serverbyt Blaze plan includes unlimited email accounts:
1. In StackCP, go to **Email Accounts** and create a mailbox, e.g. `prints@yourdomain.com` or `info@yourdomain.com`.
2. Open the **Owner Portal** at `https://yourdomain.com/owner/` and log in.
3. Go to **Settings & Email**:
   - Set **Receiving Email Address** to your mailbox where you want customer print notifications sent.
   - Set **Email Delivery Method** to `Native PHP Mail` (which works out of the box on Serverbyt) or configure your SMTP details.
   - Click **Send Test Email** to verify!

---

## 📂 Project Directory Structure

```text
csc-app/
├── index.php                 # Customer Portal (Light mode only, Forms, Search, Print Submission)
├── api/
│   ├── submit_print.php      # Uploads customer documents, saves order & emails owner
│   └── track_order.php       # Live tracking lookup for citizens
├── owner/                    # Isolated Owner/Admin Portal
│   ├── index.php             # Owner Dashboard & KPIs
│   ├── login.php             # Owner Secure Login
│   ├── logout.php            # Owner Session Logout
│   ├── forms.php             # Govt Forms Manager (Add, Edit, Delete, Toggle)
│   ├── inventory.php         # CSC Inventory Manager (Stock in/out, Low-stock alerts)
│   ├── print_orders.php      # Customer Print Orders Hub (Downloads, WhatsApp, Status)
│   ├── download.php          # Secure Document Download Handler
│   ├── settings.php          # Center Profile, Email/SMTP Setup, Rates, Password
│   └── nav.php               # Owner Portal Header & Navigation Component
├── includes/
│   ├── config.php            # Core Configuration & Paths
│   ├── db.php                # Database Connection (PDO SQLite/MySQL) & Auto-migrations
│   ├── auth.php              # Session-based owner authentication
│   ├── mailer.php            # Email sender supporting multipart attachments
│   └── helpers.php           # Formatting, CSRF, Token generators, Helpers
├── uploads/
│   ├── prints/               # Stored customer documents (.htaccess protected)
│   └── forms/                # Form attachments (.htaccess protected)
├── data/
│   └── .htaccess             # Denies all direct HTTP access to SQLite database
├── .htaccess                 # Apache rewrite and security headers
└── README.md                 # Deployment & setup documentation
```

---

## 🔒 Security & Optimization on Serverbyt

1. **Strict Portal Separation**:
   - The customer portal has zero references to `/owner/` to prevent public discovery.
2. **File Execution Prevention**:
   - `uploads/.htaccess` disables PHP script execution inside the upload directory.
3. **Database Protection**:
   - `data/.htaccess` blocks all direct HTTP access to SQLite databases.
4. **Prepared Statements**:
   - All database queries use PDO prepared statements to safeguard against SQL injection.
5. **Sanitization & Safe Filenames**:
   - Uploaded files are renamed using cryptographic hashes while preserving original names in the database.
