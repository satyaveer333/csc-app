<?php
/**
 * Database Connection & Schema Auto-migrator
 * Compatible with PDO SQLite and MySQL for Serverbyt Blaze Plan
 */

require_once __DIR__ . '/config.php';

function getDbConnection() {
    static $pdo = null;
    if ($pdo !== null) {
        return $pdo;
    }

    try {
        if (DB_DRIVER === 'sqlite') {
            if (!is_dir(DATA_DIR)) {
                @mkdir(DATA_DIR, 0777, true);
            }
            @chmod(DATA_DIR, 0777);
            if (file_exists(SQLITE_FILE)) {
                @chmod(SQLITE_FILE, 0666);
            }
            $dsn = 'sqlite:' . SQLITE_FILE;
            $pdo = new PDO($dsn);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            $pdo->exec('PRAGMA foreign_keys = ON;');
            $pdo->exec('PRAGMA journal_mode = WAL;');
        } else {
            $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s', DB_HOST, DB_PORT, DB_NAME, DB_CHARSET);
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => true,
            ]);
        }

        // Check if tables are initialized
        initDatabaseTables($pdo);

        return $pdo;
    } catch (PDOException $e) {
        error_log('Database Connection Error: ' . $e->getMessage());
        if (!headers_sent()) {
            http_response_code(503);
        }
        die("<!DOCTYPE html>
<html lang='en'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>Service Unavailable - Digital Seva Portal</title>
    <style>
        body { font-family: system-ui, -apple-system, sans-serif; background: #0f172a; color: #f8fafc; display: flex; align-items: center; justify-content: center; min-height: 100vh; margin: 0; padding: 20px; box-sizing: border-box; }
        .card { max-width: 480px; width: 100%; background: #1e293b; border: 1px solid #334155; border-radius: 16px; padding: 32px; text-align: center; box-shadow: 0 10px 25px -5px rgba(0,0,0,0.3); }
        .icon { width: 48px; height: 48px; margin: 0 auto 16px; color: #f59e0b; }
        h1 { font-size: 20px; font-weight: 700; margin: 0 0 10px; color: #ffffff; }
        p { font-size: 14px; color: #94a3b8; line-height: 1.6; margin: 0 0 24px; }
        .btn { display: inline-block; padding: 10px 20px; background: #2563eb; color: #ffffff; text-decoration: none; border-radius: 10px; font-size: 14px; font-weight: 600; transition: background 0.2s; }
        .btn:hover { background: #1d4ed8; }
    </style>
</head>
<body>
    <div class='card'>
        <svg class='icon' fill='none' stroke='currentColor' viewBox='0 0 24 24'><path stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z'/></svg>
        <h1>Service Temporarily Unavailable</h1>
        <p>Our service is currently experiencing technical difficulties. Our system administrators have been notified. Please try again in a few moments.</p>
        <a href='javascript:location.reload()' class='btn'>Refresh Page</a>
    </div>
</body>
</html>");
    }
}

function initDatabaseTables($pdo) {
    $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    $autoInc = ($driver === 'sqlite') ? 'INTEGER PRIMARY KEY AUTOINCREMENT' : 'INT AUTO_INCREMENT PRIMARY KEY';
    $tableOptions = ($driver === 'sqlite') ? '' : ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';

    // 1. Settings Table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS settings (
            setting_key VARCHAR(100) PRIMARY KEY,
            setting_value TEXT,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )$tableOptions;
    ");

    // 2. Forms Table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS forms (
            id $autoInc,
            title VARCHAR(255) NOT NULL,
            category VARCHAR(100) NOT NULL,
            department VARCHAR(200) DEFAULT '',
            summary TEXT,
            eligibility TEXT,
            required_documents TEXT,
            fee_details VARCHAR(255) DEFAULT '',
            start_date DATE NULL,
            last_date DATE NULL,
            official_url VARCHAR(500) DEFAULT '',
            attachment_file VARCHAR(255) DEFAULT '',
            status VARCHAR(20) DEFAULT 'active',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )$tableOptions;
    ");

    // 3. Inventory Table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS inventory (
            id $autoInc,
            item_name VARCHAR(200) NOT NULL,
            category VARCHAR(100) NOT NULL,
            quantity INT NOT NULL DEFAULT 0,
            unit VARCHAR(50) NOT NULL DEFAULT 'Pieces',
            low_stock_threshold INT NOT NULL DEFAULT 10,
            cost_price DECIMAL(10,2) DEFAULT 0.00,
            selling_price DECIMAL(10,2) DEFAULT 0.00,
            notes TEXT,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )$tableOptions;
    ");

    // 4. Print Orders Table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS print_orders (
            id $autoInc,
            token_id VARCHAR(50) UNIQUE NOT NULL,
            customer_name VARCHAR(150) NOT NULL,
            customer_phone VARCHAR(25) NOT NULL,
            customer_email VARCHAR(150) DEFAULT '',
            print_type VARCHAR(50) DEFAULT 'B&W',
            sides VARCHAR(50) DEFAULT 'Single Sided',
            paper_size VARCHAR(50) DEFAULT 'A4',
            copies INT NOT NULL DEFAULT 1,
            notes TEXT,
            files_json TEXT NOT NULL,
            estimated_amount DECIMAL(10,2) DEFAULT 0.00,
            status VARCHAR(30) DEFAULT 'pending',
            email_sent INT DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )$tableOptions;
    ");

    // 5. OTPs Table (hashed OTPs with expiration and attempt limits)
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS otps (
            id $autoInc,
            email VARCHAR(150) NOT NULL,
            otp_hash VARCHAR(64) NOT NULL,
            purpose VARCHAR(50) NOT NULL,
            attempts INT NOT NULL DEFAULT 0,
            max_attempts INT NOT NULL DEFAULT 5,
            expires_at DATETIME NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )$tableOptions;
    ");

    // 6. Login & Security Audit Logs Table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS login_logs (
            id $autoInc,
            username VARCHAR(100) NOT NULL,
            ip_address VARCHAR(45) NOT NULL,
            user_agent TEXT,
            device_fingerprint VARCHAR(64) NOT NULL,
            status VARCHAR(30) NOT NULL,
            failure_reason VARCHAR(255) DEFAULT '',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )$tableOptions;
    ");

    // 7. Active Sessions Table (Session Management & Device Tracking)
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS active_sessions (
            id $autoInc,
            session_id VARCHAR(128) UNIQUE NOT NULL,
            device_fingerprint VARCHAR(64) NOT NULL,
            ip_address VARCHAR(45) NOT NULL,
            user_agent TEXT,
            device_type VARCHAR(50) DEFAULT 'Desktop',
            browser VARCHAR(50) DEFAULT 'Browser',
            platform VARCHAR(50) DEFAULT 'Unknown',
            last_activity DATETIME DEFAULT CURRENT_TIMESTAMP,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )$tableOptions;
    ");

    // Seed default settings if empty
    $stmt = $pdo->query("SELECT COUNT(*) FROM settings");
    if ($stmt->fetchColumn() == 0) {
        $defaultPassHash = password_hash(DEFAULT_ADMIN_PASS, PASSWORD_DEFAULT);
        $defaults = [
            'admin_username' => DEFAULT_ADMIN_USER,
            'admin_password' => $defaultPassHash,
            'center_name' => DEFAULT_CENTER_NAME,
            'owner_name' => DEFAULT_OWNER_NAME,
            'owner_phone' => DEFAULT_OWNER_PHONE,
            'owner_email' => DEFAULT_OWNER_EMAIL,
            'center_address' => DEFAULT_CENTER_ADDRESS,
            'vle_id' => 'CSC-98745612',
            'opening_hours' => '9:00 AM - 8:00 PM (Mon-Sat)',
            'rate_bw_single' => '3.00',
            'rate_bw_double' => '5.00',
            'rate_color_single' => '10.00',
            'rate_color_double' => '18.00',
            'rate_lamination' => '20.00',
            'mail_method' => 'php_mail', // 'php_mail' or 'smtp'
            'smtp_host' => 'smtp.example.com',
            'smtp_port' => '587',
            'smtp_user' => '',
            'smtp_pass' => '',
            'smtp_secure' => 'tls',
            'developer_email_hash' => DEFAULT_DEVELOPER_EMAIL_HASH,
            'registered_owner_email' => '',
            'registered_owner_email_verified' => '0',
        ];

        $ins = $pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (:k, :v)");
        foreach ($defaults as $k => $v) {
            $ins->execute([':k' => $k, ':v' => $v]);
        }
    } else {
        // Upgrade existing install if it has old default name
        $pdo->exec("UPDATE settings SET setting_value = 'Digital Solutions' WHERE setting_key = 'center_name' AND (setting_value LIKE '%Jan Seva Kendra%' OR setting_value = '')");

        // Ensure registered 2FA email settings exist
        $chk = $pdo->query("SELECT COUNT(*) FROM settings WHERE setting_key = 'registered_owner_email'");
        if ($chk && $chk->fetchColumn() == 0) {
            $pdo->exec("INSERT INTO settings (setting_key, setting_value) VALUES ('registered_owner_email', '')");
        }
        $chk2 = $pdo->query("SELECT COUNT(*) FROM settings WHERE setting_key = 'registered_owner_email_verified'");
        if ($chk2 && $chk2->fetchColumn() == 0) {
            $pdo->exec("INSERT INTO settings (setting_key, setting_value) VALUES ('registered_owner_email_verified', '0')");
        }
    }

    // Seed sample forms if empty
    $stmt = $pdo->query("SELECT COUNT(*) FROM forms");
    if ($stmt->fetchColumn() == 0) {
        $sampleForms = [
            [
                'title' => 'PM Kisan Samman Nidhi - eKYC & New Registration',
                'category' => 'Welfare Schemes',
                'department' => 'Ministry of Agriculture & Farmers Welfare',
                'summary' => 'Income support of Rs. 6000 per year in three equal installments to all landholding farmers families.',
                'eligibility' => 'All landholding farmer families having cultivable land in their name.',
                'required_documents' => "1. Aadhaar Card\n2. Land Ownership Papers (Khatauni / Khasra)\n3. Bank Passbook\n4. Mobile number linked with Aadhaar",
                'fee_details' => 'Government Fee: Free | CSC Portal Service Charge: Rs. 30',
                'start_date' => date('Y-01-01'),
                'last_date' => date('Y-12-31'),
                'official_url' => 'https://pmkisan.gov.in',
                'status' => 'active'
            ],
            [
                'title' => 'Ayushman Bharat Golden Card (PM-JAY)',
                'category' => 'Health & Insurance',
                'department' => 'National Health Authority',
                'summary' => 'Free health insurance cover up to Rs. 5 Lakhs per family per year for secondary and tertiary care hospitalization.',
                'eligibility' => 'Families listed in SECC 2011 database or holding valid Ration Card under eligible category.',
                'required_documents' => "1. Ration Card\n2. Aadhaar Card of all family members\n3. Active Mobile Number",
                'fee_details' => 'Free Card Generation | PVC Print: Rs. 30',
                'start_date' => date('Y-01-01'),
                'last_date' => null,
                'official_url' => 'https://setu.pmjay.gov.in',
                'status' => 'active'
            ],
            [
                'title' => 'SSC Combined Graduate Level (CGL) 2026 Examination',
                'category' => 'Recruitment & Jobs',
                'department' => 'Staff Selection Commission (SSC)',
                'summary' => 'Online application for various Group B and Group C posts in Ministries and Departments of Government of India.',
                'eligibility' => 'Bachelor Degree in any stream from recognized university. Age: 18-30/32 years (relaxation applicable).',
                'required_documents' => "1. 10th & 12th Marksheets\n2. Graduation Degree/Marksheet\n3. Passport Photo (Live webcam capture)\n4. Signature\n5. Category Certificate (OBC/SC/ST/EWS)",
                'fee_details' => 'General/OBC: Rs. 100 | SC/ST/Female: Rs. 0 | CSC Filing Fee: Rs. 70',
                'start_date' => date('Y-m-01'),
                'last_date' => date('Y-m-d', strtotime('+20 days')),
                'official_url' => 'https://ssc.gov.in',
                'status' => 'active'
            ],
            [
                'title' => 'State Post-Matric & Pre-Matric Scholarship Scheme',
                'category' => 'Scholarships & Education',
                'department' => 'Social Welfare Department',
                'summary' => 'Financial assistance and fee reimbursement for students studying in Class 9th, 10th, 11th, 12th, Degree, Diploma, and Professional courses.',
                'eligibility' => 'Annual family income less than Rs. 2,00,000 (SC/ST: Rs. 2,50,000). Regular enrolled student.',
                'required_documents' => "1. Income Certificate (Aay Praman Patra)\n2. Caste Certificate (Jati Praman Patra)\n3. Domicile (Niwas Praman Patra)\n4. Bank Passbook linked to Aadhaar NPCI\n5. College Fee Receipt & ID Card\n6. Previous Year Marksheet",
                'fee_details' => 'Government Fee: Free | CSC Form Filling: Rs. 50',
                'start_date' => date('Y-08-01'),
                'last_date' => date('Y-11-30'),
                'official_url' => 'https://scholarship.up.gov.in',
                'status' => 'active'
            ],
            [
                'title' => 'New PAN Card (Form 49A) & Correction Services',
                'category' => 'Certificates & Identity',
                'department' => 'Income Tax Department (UTI / NSDL)',
                'summary' => 'Instant e-PAN card generation and physical PVC card delivery to home address via Aadhaar e-KYC or biometric authentication.',
                'eligibility' => 'Any Indian citizen above or below 18 years of age.',
                'required_documents' => "1. Aadhaar Card\n2. 2 Passport size color photos\n3. Proof of Date of Birth",
                'fee_details' => 'Government Fee: Rs. 107 | CSC Processing: Rs. 40 (Total: Rs. 147)',
                'start_date' => date('Y-01-01'),
                'last_date' => null,
                'official_url' => 'https://www.pan.utiitsl.com',
                'status' => 'active'
            ]
        ];

        $ins = $pdo->prepare("INSERT INTO forms (title, category, department, summary, eligibility, required_documents, fee_details, start_date, last_date, official_url, status) VALUES (:title, :category, :department, :summary, :eligibility, :required_documents, :fee_details, :start_date, :last_date, :official_url, :status)");
        foreach ($sampleForms as $f) {
            $ins->execute($f);
        }
    }

    // Seed sample inventory if empty
    $stmt = $pdo->query("SELECT COUNT(*) FROM inventory");
    if ($stmt->fetchColumn() == 0) {
        $sampleInventory = [
            ['item_name' => 'JK Copier A4 Paper (75 GSM)', 'category' => 'Paper & Sheets', 'quantity' => 12, 'unit' => 'Reams (500 sheets)', 'low_stock_threshold' => 5, 'cost_price' => 280.00, 'selling_price' => 350.00, 'notes' => 'Primary paper used for general printing and photocopying.'],
            ['item_name' => 'Legal Size Bond Paper (80 GSM)', 'category' => 'Paper & Sheets', 'quantity' => 4, 'unit' => 'Reams (500 sheets)', 'low_stock_threshold' => 3, 'cost_price' => 340.00, 'selling_price' => 420.00, 'notes' => 'For affidavits, registry copies, court stamps.'],
            ['item_name' => 'Glossy Photo Paper A4 (180 GSM)', 'category' => 'Photo Supplies', 'quantity' => 150, 'unit' => 'Sheets', 'low_stock_threshold' => 50, 'cost_price' => 2.50, 'selling_price' => 10.00, 'notes' => 'For passport photos and certificates.'],
            ['item_name' => 'Lamination Pouches A4 (125 Micron)', 'category' => 'Lamination', 'quantity' => 35, 'unit' => 'Pouches', 'low_stock_threshold' => 40, 'cost_price' => 4.50, 'selling_price' => 20.00, 'notes' => 'Marked as LOW STOCK automatically to demonstrate alert.'],
            ['item_name' => 'ID Card / Aadhaar Lamination Pouches', 'category' => 'Lamination', 'quantity' => 120, 'unit' => 'Pouches', 'low_stock_threshold' => 50, 'cost_price' => 1.20, 'selling_price' => 10.00, 'notes' => 'For PAN card and Voter ID lamination.'],
            ['item_name' => 'Blank PVC Smart Cards', 'category' => 'ID Cards', 'quantity' => 85, 'unit' => 'Cards', 'low_stock_threshold' => 30, 'cost_price' => 6.00, 'selling_price' => 50.00, 'notes' => 'For printing PVC Aadhaar, Ayushman, E-Shram cards.'],
            ['item_name' => 'Epson 003 Black Ink Bottle (Original)', 'category' => 'Printer Inks', 'quantity' => 2, 'unit' => 'Bottles', 'low_stock_threshold' => 3, 'cost_price' => 420.00, 'selling_price' => 0.00, 'notes' => 'For EcoTank L3150 / L3250 printer. Low stock alert.'],
            ['item_name' => 'Epson 003 Color Set (C, M, Y)', 'category' => 'Printer Inks', 'quantity' => 3, 'unit' => 'Sets', 'low_stock_threshold' => 2, 'cost_price' => 1200.00, 'selling_price' => 0.00, 'notes' => 'Color refill bottles.'],
            ['item_name' => 'Plastic Document Folders / Files', 'category' => 'Stationery', 'quantity' => 45, 'unit' => 'Pieces', 'low_stock_threshold' => 20, 'cost_price' => 7.00, 'selling_price' => 15.00, 'notes' => 'Button closure transparent document folders.']
        ];

        $ins = $pdo->prepare("INSERT INTO inventory (item_name, category, quantity, unit, low_stock_threshold, cost_price, selling_price, notes) VALUES (:item_name, :category, :quantity, :unit, :low_stock_threshold, :cost_price, :selling_price, :notes)");
        foreach ($sampleInventory as $item) {
            $ins->execute($item);
        }
    }
}
