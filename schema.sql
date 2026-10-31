-- ====================================================================
-- CSC Digital Solutions - Production MySQL Database Schema & Initial Data
-- Character Set: utf8mb4 | Collation: utf8mb4_unicode_ci | Engine: InnoDB
-- Compatible with MySQL 5.7+, MySQL 8.0+, MariaDB 10.3+
-- ====================================================================

SET FOREIGN_KEY_CHECKS = 0;
SET NAMES utf8mb4;
SET time_zone = '+00:00';

-- (Optional) If you have root access and need to create the database:
-- CREATE DATABASE IF NOT EXISTS `csc_db` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
-- USE `csc_db`;

-- --------------------------------------------------------------------
-- 1. Table structure for table `settings`
-- --------------------------------------------------------------------
DROP TABLE IF EXISTS `settings`;
CREATE TABLE `settings` (
  `setting_key` VARCHAR(100) NOT NULL,
  `setting_value` TEXT NULL,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------------------
-- Initial data for table `settings`
-- --------------------------------------------------------------------
INSERT INTO `settings` (`setting_key`, `setting_value`) VALUES
  ('admin_username', 'admin'),
  ('admin_password', '$2y$10$fg6.UfqK7ZMF8XNmE.xMROEhiLjU2//sccaPo.BNtjdA6Qjc4wm6i'),
  ('center_name', 'Digital veer'),
  ('owner_name', 'VLE Operator'),
  ('owner_phone', '+91 9876543210'),
  ('owner_email', 'csc.center@example.com'),
  ('center_address', 'Near Bus Stand, Main Market'),
  ('vle_id', 'VLE-123456'),
  ('opening_hours', '8:30 AM - 8:30 PM'),
  ('rate_bw_single', '3.00'),
  ('rate_bw_double', '5.00'),
  ('rate_color_single', '10.00'),
  ('rate_color_double', '18.00'),
  ('rate_lamination', '20.00'),
  ('mail_method', 'php_mail'),
  ('smtp_host', 'smtp.example.com'),
  ('smtp_port', '587'),
  ('smtp_user', ''),
  ('smtp_pass', ''),
  ('smtp_secure', 'tls'),
  ('developer_email_hash', '0dc6d2bc23045997492ae6458d7626550d2af7f225663466561342c5d71384f7');

-- --------------------------------------------------------------------
-- 2. Table structure for table `forms`
-- --------------------------------------------------------------------
DROP TABLE IF EXISTS `forms`;
CREATE TABLE `forms` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `title` VARCHAR(255) NOT NULL,
  `category` VARCHAR(100) NOT NULL,
  `department` VARCHAR(200) NOT NULL DEFAULT '',
  `summary` TEXT NULL,
  `eligibility` TEXT NULL,
  `required_documents` TEXT NULL,
  `fee_details` VARCHAR(255) NOT NULL DEFAULT '',
  `start_date` DATE NULL DEFAULT NULL,
  `last_date` DATE NULL DEFAULT NULL,
  `official_url` VARCHAR(500) NOT NULL DEFAULT '',
  `attachment_file` VARCHAR(255) NOT NULL DEFAULT '',
  `status` VARCHAR(20) NOT NULL DEFAULT 'active',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_forms_status` (`status`),
  KEY `idx_forms_category` (`category`),
  KEY `idx_forms_last_date` (`last_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------------------
-- Initial data for table `forms`
-- --------------------------------------------------------------------
INSERT INTO `forms` (`id`, `title`, `category`, `department`, `summary`, `eligibility`, `required_documents`, `fee_details`, `start_date`, `last_date`, `official_url`, `attachment_file`, `status`, `created_at`, `updated_at`) VALUES
  (1, 'PM Kisan Samman Nidhi - eKYC & New Registration', 'Welfare Schemes', 'Ministry of Agriculture & Farmers Welfare', 'Income support of Rs. 6000 per year in three equal installments to all landholding farmers families.', 'All landholding farmer families having cultivable land in their name.', '1. Aadhaar Card\n2. Land Ownership Papers (Khatauni / Khasra)\n3. Bank Passbook\n4. Mobile number linked with Aadhaar', 'Government Fee: Free | CSC Portal Service Charge: Rs. 30', '2026-01-01', '2026-12-31', 'https://pmkisan.gov.in', '', 'active', '2026-10-30 13:04:15', '2026-10-30 13:04:15'),
  (2, 'Ayushman Bharat Golden Card (PM-JAY)', 'Health & Insurance', 'National Health Authority', 'Free health insurance cover up to Rs. 5 Lakhs per family per year for secondary and tertiary care hospitalization.', 'Families listed in SECC 2011 database or holding valid Ration Card under eligible category.', '1. Ration Card\n2. Aadhaar Card of all family members\n3. Active Mobile Number', 'Free Card Generation | PVC Print: Rs. 30', '2026-01-01', NULL, 'https://setu.pmjay.gov.in', '', 'active', '2026-10-30 13:04:15', '2026-10-30 13:04:15'),
  (3, 'SSC Combined Graduate Level (CGL) 2026 Examination', 'Recruitment & Jobs', 'Staff Selection Commission (SSC)', 'Online application for various Group B and Group C posts in Ministries and Departments of Government of India.', 'Bachelor Degree in any stream from recognized university. Age: 18-30/32 years (relaxation applicable).', '1. 10th & 12th Marksheets\n2. Graduation Degree/Marksheet\n3. Passport Photo (Live webcam capture)\n4. Signature\n5. Category Certificate (OBC/SC/ST/EWS)', 'General/OBC: Rs. 100 | SC/ST/Female: Rs. 0 | CSC Filing Fee: Rs. 70', '2026-10-01', '2026-11-19', 'https://ssc.gov.in', '', 'active', '2026-10-30 13:04:15', '2026-10-30 13:04:15'),
  (4, 'State Post-Matric & Pre-Matric Scholarship Scheme', 'Scholarships & Education', 'Social Welfare Department', 'Financial assistance and fee reimbursement for students studying in Class 9th, 10th, 11th, 12th, Degree, Diploma, and Professional courses.', 'Annual family income less than Rs. 2,00,000 (SC/ST: Rs. 2,50,000). Regular enrolled student.', '1. Income Certificate (Aay Praman Patra)\n2. Caste Certificate (Jati Praman Patra)\n3. Domicile (Niwas Praman Patra)\n4. Bank Passbook linked to Aadhaar NPCI\n5. College Fee Receipt & ID Card\n6. Previous Year Marksheet', 'Government Fee: Free | CSC Form Filling: Rs. 50', '2026-08-01', '2026-11-30', 'https://scholarship.up.gov.in', '', 'active', '2026-10-30 13:04:15', '2026-10-30 13:04:15'),
  (5, 'New PAN Card (Form 49A) & Correction Services', 'Certificates & Identity', 'Income Tax Department (UTI / NSDL)', 'Instant e-PAN card generation and physical PVC card delivery to home address via Aadhaar e-KYC or biometric authentication.', 'Any Indian citizen above or below 18 years of age.', '1. Aadhaar Card\n2. 2 Passport size color photos\n3. Proof of Date of Birth', 'Government Fee: Rs. 107 | CSC Processing: Rs. 40 (Total: Rs. 147)', '2026-01-01', NULL, 'https://www.pan.utiitsl.com', '', 'active', '2026-10-30 13:04:15', '2026-10-30 13:04:15');

-- --------------------------------------------------------------------
-- 3. Table structure for table `inventory`
-- --------------------------------------------------------------------
DROP TABLE IF EXISTS `inventory`;
CREATE TABLE `inventory` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `item_name` VARCHAR(200) NOT NULL,
  `category` VARCHAR(100) NOT NULL,
  `quantity` INT NOT NULL DEFAULT 0,
  `unit` VARCHAR(50) NOT NULL DEFAULT 'Pieces',
  `low_stock_threshold` INT NOT NULL DEFAULT 10,
  `cost_price` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `selling_price` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `notes` TEXT NULL,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_inventory_category` (`category`),
  KEY `idx_inventory_stock` (`quantity`, `low_stock_threshold`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------------------
-- Initial data for table `inventory`
-- --------------------------------------------------------------------
INSERT INTO `inventory` (`id`, `item_name`, `category`, `quantity`, `unit`, `low_stock_threshold`, `cost_price`, `selling_price`, `notes`, `updated_at`) VALUES
  (1, 'JK Copier A4 Paper (75 GSM)', 'Paper & Sheets', 22, 'Reams (500 sheets)', 5, 280, 350, 'Primary paper used for general printing and photocopying.', '2026-10-30 15:18:31'),
  (2, 'Legal Size Bond Paper (80 GSM)', 'Paper & Sheets', 4, 'Reams (500 sheets)', 3, 340, 420, 'For affidavits, registry copies, court stamps.', '2026-10-30 13:04:15'),
  (3, 'Glossy Photo Paper A4 (180 GSM)', 'Photo Supplies', 150, 'Sheets', 50, 2.5, 10, 'For passport photos and certificates.', '2026-10-30 13:04:15'),
  (4, 'Lamination Pouches A4 (125 Micron)', 'Lamination', 35, 'Pouches', 40, 4.5, 20, 'Marked as LOW STOCK automatically to demonstrate alert.', '2026-10-30 13:04:15'),
  (5, 'ID Card / Aadhaar Lamination Pouches', 'Lamination', 120, 'Pouches', 50, 1.2, 10, 'For PAN card and Voter ID lamination.', '2026-10-30 13:04:15'),
  (6, 'Blank PVC Smart Cards', 'ID Cards', 85, 'Cards', 30, 6, 50, 'For printing PVC Aadhaar, Ayushman, E-Shram cards.', '2026-10-30 13:04:15'),
  (7, 'Epson 003 Black Ink Bottle (Original)', 'Printer Inks', 2, 'Bottles', 3, 420, 0, 'For EcoTank L3150 / L3250 printer. Low stock alert.', '2026-10-30 13:04:15'),
  (8, 'Epson 003 Color Set (C, M, Y)', 'Printer Inks', 3, 'Sets', 2, 1200, 0, 'Color refill bottles.', '2026-10-30 13:04:15'),
  (9, 'Plastic Document Folders / Files', 'Stationery', 45, 'Pieces', 20, 7, 15, 'Button closure transparent document folders.', '2026-10-30 13:04:15');

-- --------------------------------------------------------------------
-- 4. Table structure for table `print_orders`
-- --------------------------------------------------------------------
DROP TABLE IF EXISTS `print_orders`;
CREATE TABLE `print_orders` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `token_id` VARCHAR(50) NOT NULL,
  `customer_name` VARCHAR(150) NOT NULL,
  `customer_phone` VARCHAR(25) NOT NULL,
  `customer_email` VARCHAR(150) NOT NULL DEFAULT '',
  `print_type` VARCHAR(50) NOT NULL DEFAULT 'B&W',
  `sides` VARCHAR(50) NOT NULL DEFAULT 'Single Sided',
  `paper_size` VARCHAR(50) NOT NULL DEFAULT 'A4',
  `copies` INT NOT NULL DEFAULT 1,
  `notes` TEXT NULL,
  `files_json` LONGTEXT NOT NULL,
  `estimated_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `status` VARCHAR(30) NOT NULL DEFAULT 'pending',
  `email_sent` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_token_id` (`token_id`),
  KEY `idx_orders_customer_phone` (`customer_phone`),
  KEY `idx_orders_status` (`status`),
  KEY `idx_orders_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
