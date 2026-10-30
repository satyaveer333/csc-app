<?php
/**
 * CSC Digital Seva - Customer Portal (Light Mode Only)
 * Note: Strictly isolated from Owner/Admin portal with no external link.
 */

define('CSC_APP', true);
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/helpers.php';

ensureDirectories();
$pdo = getDbConnection();
$settings = getSettings($pdo);

// Fetch all active forms
$stmt = $pdo->prepare("
    SELECT * FROM forms 
    WHERE status = 'active' 
    ORDER BY CASE WHEN last_date IS NOT NULL AND last_date >= date('now') THEN 0 ELSE 1 END, last_date ASC, id DESC
");
$stmt->execute();
$forms = $stmt->fetchAll();

// Extract unique categories for filter tabs
$categories = [];
foreach ($forms as $f) {
    if (!empty($f['category']) && !in_array($f['category'], $categories)) {
        $categories[] = $f['category'];
    }
}

// Print rates for JS calculator
$rateBwSingle = (float)($settings['rate_bw_single'] ?? 3.00);
$rateBwDouble = (float)($settings['rate_bw_double'] ?? 5.00);
$rateColorSingle = (float)($settings['rate_color_single'] ?? 10.00);
$rateColorDouble = (float)($settings['rate_color_double'] ?? 18.00);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($settings['center_name'] ?? 'Digital Solutions') ?> - CSC & Online Citizen Services</title>
    <!-- Favicon (DS Monogram) -->
    <link rel="icon" type="image/svg+xml" href="favicon.svg">
    <link rel="alternate icon" href="favicon.svg">
    <!-- Tailwind CSS (Light Mode Optimized) -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Lucide Icons -->
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        /* Force strictly light theme */
        body {
            background-color: #f8fafc;
            color: #0f172a;
            font-family: system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
        }
        .gradient-brand {
            background: linear-gradient(135deg, #1e40af 0%, #2563eb 50%, #0284c7 100%);
        }
        .file-dropzone.dragover {
            border-color: #2563eb;
            background-color: #eff6ff;
        }
    </style>
</head>
<body class="min-h-screen flex flex-col bg-slate-50 text-slate-800 antialiased">

    <!-- Top Citizen Service Bar -->
    <header class="bg-white border-b border-slate-200 sticky top-0 z-40 shadow-sm">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between h-20">
                <div class="flex items-center space-x-3.5">
                    <!-- Brand DS Monogram Logo -->
                    <div class="w-12 h-12 rounded-2xl bg-gradient-to-br from-blue-700 via-blue-600 to-sky-500 text-white flex items-center justify-center shadow-md shadow-blue-500/20 font-black text-xl tracking-tighter border border-blue-400/40 select-none">
                        DS
                    </div>
                    <div>
                        <div class="flex items-center space-x-2">
                            <h1 class="text-xl sm:text-2xl font-black text-slate-900 tracking-tight leading-none">
                                <?= e($settings['center_name'] ?? 'Digital Solutions') ?>
                            </h1>
                            <?php if (!empty($settings['vle_id'])): ?>
                                <span class="hidden sm:inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold bg-blue-100 text-blue-800">
                                    VLE: <?= e($settings['vle_id']) ?>
                                </span>
                            <?php endif; ?>
                        </div>
                        <p class="text-xs sm:text-sm text-slate-500 mt-1 flex items-center gap-2">
                            <span class="flex items-center gap-1"><i data-lucide="map-pin" class="w-3.5 h-3.5 text-slate-400"></i> <?= e($settings['center_address'] ?? 'Customer Service Point') ?></span>
                            <span class="hidden md:inline-flex items-center gap-1"><i data-lucide="clock" class="w-3.5 h-3.5 text-slate-400"></i> <?= e($settings['opening_hours'] ?? '9 AM - 8 PM') ?></span>
                        </p>
                    </div>
                </div>

                <!-- Contact & WhatsApp button -->
                <div class="flex items-center space-x-2 sm:space-x-4">
                    <?php if (!empty($settings['owner_phone'])): ?>
                        <a href="tel:<?= e($settings['owner_phone']) ?>" class="hidden sm:inline-flex items-center gap-2 px-3 py-2 text-sm font-medium rounded-lg text-slate-700 bg-slate-100 hover:bg-slate-200 transition">
                            <i data-lucide="phone" class="w-4 h-4 text-blue-600"></i>
                            <span><?= e($settings['owner_phone']) ?></span>
                        </a>
                        <a href="https://wa.me/<?= preg_replace('/[^0-9]/', '', $settings['owner_phone']) ?>?text=Hello%20CSC%20Center,%20I%20need%20assistance" target="_blank" class="inline-flex items-center gap-2 px-3.5 py-2 text-sm font-semibold rounded-lg text-white bg-emerald-600 hover:bg-emerald-700 shadow-sm transition">
                            <i data-lucide="message-circle" class="w-4 h-4"></i>
                            <span class="hidden sm:inline">WhatsApp</span>
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Navigation Sub-bar -->
        <nav class="bg-slate-100 border-t border-slate-200">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 flex space-x-1 sm:space-x-8 overflow-x-auto text-sm font-medium">
                <button onclick="switchTab('forms-tab')" id="tab-btn-forms" class="nav-tab py-3 px-3 border-b-2 border-blue-600 text-blue-600 font-semibold flex items-center gap-2 whitespace-nowrap">
                    <i data-lucide="file-text" class="w-4 h-4"></i>
                    <span>Govt Forms & Vacancies</span>
                    <span class="bg-blue-100 text-blue-700 text-xs px-2 py-0.5 rounded-full font-bold"><?= count($forms) ?></span>
                </button>
                <button onclick="switchTab('print-tab')" id="tab-btn-print" class="nav-tab py-3 px-3 border-b-2 border-transparent text-slate-600 hover:text-slate-900 flex items-center gap-2 whitespace-nowrap">
                    <i data-lucide="printer" class="w-4 h-4"></i>
                    <span>Send Documents for Printing</span>
                    <span class="bg-emerald-100 text-emerald-700 text-xs px-2 py-0.5 rounded-full font-bold">Fast Email Service</span>
                </button>
                <button onclick="switchTab('track-tab')" id="tab-btn-track" class="nav-tab py-3 px-3 border-b-2 border-transparent text-slate-600 hover:text-slate-900 flex items-center gap-2 whitespace-nowrap">
                    <i data-lucide="search" class="w-4 h-4"></i>
                    <span>Track Print Order</span>
                </button>
            </div>
        </nav>
    </header>

    <main class="flex-1 max-w-7xl w-full mx-auto px-4 sm:px-6 lg:px-8 py-6">

        <!-- ================= SECTION 1: FORMS DIRECTORY ================= -->
        <div id="section-forms" class="space-y-6">
            
            <!-- Hero Notice Banner -->
            <div class="bg-white rounded-2xl p-6 border border-slate-200 shadow-sm flex flex-col md:flex-row md:items-center justify-between gap-4">
                <div>
                    <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold bg-blue-50 text-blue-700 border border-blue-200 mb-2">
                        <i data-lucide="bell" class="w-3.5 h-3.5 text-blue-600"></i> Active Schemes & Job Applications
                    </span>
                    <h2 class="text-2xl font-bold text-slate-900">Latest Government Forms & Schemes</h2>
                    <p class="text-slate-600 text-sm mt-1">
                        Check application deadlines, required documents checklist, and eligibility. Visit our CSC counter to apply!
                    </p>
                </div>
                <div class="flex items-center gap-3">
                    <div class="relative w-full md:w-80">
                        <i data-lucide="search" class="w-5 h-5 text-slate-400 absolute left-3.5 top-3"></i>
                        <input type="text" id="formSearchInput" placeholder="Search forms by name, job, scheme..." 
                               class="w-full pl-10 pr-4 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:bg-white transition"
                               onkeyup="filterForms()">
                    </div>
                </div>
            </div>

            <!-- Category Filter Pills -->
            <div class="flex items-center gap-2 overflow-x-auto pb-1 text-sm">
                <button onclick="setCategoryFilter('all', this)" class="category-pill px-4 py-2 rounded-xl bg-blue-600 text-white font-medium shadow-sm transition whitespace-nowrap">
                    All Categories (<?= count($forms) ?>)
                </button>
                <?php foreach ($categories as $cat): ?>
                    <button onclick="setCategoryFilter('<?= e($cat) ?>', this)" class="category-pill px-4 py-2 rounded-xl bg-white text-slate-700 border border-slate-200 hover:bg-slate-100 font-medium transition whitespace-nowrap">
                        <?= e($cat) ?>
                    </button>
                <?php endforeach; ?>
            </div>

            <!-- Forms Grid -->
            <div id="formsContainer" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <?php if (empty($forms)): ?>
                    <div class="col-span-full bg-white rounded-2xl p-12 text-center border border-slate-200">
                        <i data-lucide="inbox" class="w-12 h-12 text-slate-400 mx-auto mb-3"></i>
                        <h3 class="text-lg font-bold text-slate-700">No Forms Posted Yet</h3>
                        <p class="text-slate-500 text-sm mt-1">Please check back soon for latest recruitment and scheme notifications.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($forms as $form): 
                        $isClosingSoon = false;
                        $daysLeftText = 'No Last Date';
                        if (!empty($form['last_date'])) {
                            $diff = (strtotime($form['last_date']) - strtotime(date('Y-m-d'))) / (60 * 60 * 24);
                            if ($diff < 0) {
                                $daysLeftText = 'Expired';
                            } elseif ($diff == 0) {
                                $daysLeftText = 'Closing Today!';
                                $isClosingSoon = true;
                            } elseif ($diff <= 5) {
                                $daysLeftText = $diff . ' days remaining';
                                $isClosingSoon = true;
                            } else {
                                $daysLeftText = 'Last Date: ' . date('d M Y', strtotime($form['last_date']));
                            }
                        }
                    ?>
                        <div class="form-card bg-white rounded-2xl border border-slate-200 shadow-sm hover:shadow-md transition-all duration-200 flex flex-col justify-between overflow-hidden" 
                             data-title="<?= e(strtolower($form['title'])) ?>"
                             data-category="<?= e($form['category']) ?>"
                             data-dept="<?= e(strtolower($form['department'])) ?>">
                            
                            <div class="p-6">
                                <div class="flex items-center justify-between gap-2 mb-3">
                                    <span class="inline-flex items-center px-2.5 py-1 rounded-md text-xs font-semibold bg-blue-50 text-blue-700 border border-blue-100">
                                        <?= e($form['category']) ?>
                                    </span>
                                    <?php if ($isClosingSoon): ?>
                                        <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-md text-xs font-semibold bg-amber-50 text-amber-700 border border-amber-200 animate-pulse">
                                            <i data-lucide="clock" class="w-3 h-3 text-amber-600"></i> <?= e($daysLeftText) ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-xs text-slate-500 font-medium">
                                            <?= e($daysLeftText) ?>
                                        </span>
                                    <?php endif; ?>
                                </div>

                                <h3 class="text-lg font-bold text-slate-900 leading-snug line-clamp-2 hover:text-blue-600 transition">
                                    <?= e($form['title']) ?>
                                </h3>

                                <?php if (!empty($form['department'])): ?>
                                    <p class="text-xs text-slate-500 mt-1 font-medium flex items-center gap-1">
                                        <i data-lucide="building" class="w-3.5 h-3.5 text-slate-400"></i> <?= e($form['department']) ?>
                                    </p>
                                <?php endif; ?>

                                <?php if (!empty($form['summary'])): ?>
                                    <p class="text-sm text-slate-600 mt-3 line-clamp-3 leading-relaxed">
                                        <?= e($form['summary']) ?>
                                    </p>
                                <?php endif; ?>

                                <!-- Fee / Charges -->
                                <?php if (!empty($form['fee_details'])): ?>
                                    <div class="mt-4 pt-3 border-t border-slate-100 flex items-center justify-between text-xs">
                                        <span class="text-slate-500">Application Fee:</span>
                                        <span class="font-semibold text-slate-800"><?= e($form['fee_details']) ?></span>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <!-- Footer Card Action -->
                            <div class="p-4 bg-slate-50 border-t border-slate-100 flex items-center justify-between gap-2">
                                <button onclick="openFormModal(<?= htmlspecialchars(json_encode($form), ENT_QUOTES, 'UTF-8') ?>)" 
                                        class="w-full inline-flex items-center justify-center gap-2 px-4 py-2 text-sm font-semibold rounded-xl text-blue-600 bg-white border border-blue-200 hover:bg-blue-600 hover:text-white transition shadow-sm">
                                    <i data-lucide="file-check" class="w-4 h-4"></i> View Requirements & Details
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            
            <div id="noFormsMatch" class="hidden bg-white rounded-2xl p-12 text-center border border-slate-200">
                <i data-lucide="search-x" class="w-12 h-12 text-slate-400 mx-auto mb-3"></i>
                <h3 class="text-lg font-bold text-slate-700">No Matching Forms Found</h3>
                <p class="text-slate-500 text-sm mt-1">Try searching with a different keyword or select "All Categories".</p>
            </div>
        </div>

        <!-- ================= SECTION 2: PRINT MY DOCUMENTS VIA EMAIL ================= -->
        <div id="section-print" class="hidden space-y-6">
            
            <div class="max-w-4xl mx-auto">
                <div class="bg-white rounded-3xl p-6 sm:p-10 border border-slate-200 shadow-sm">
                    
                    <div class="border-b border-slate-200 pb-6 mb-8 text-center sm:text-left">
                        <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200 mb-2">
                            <i data-lucide="send" class="w-3.5 h-3.5 text-emerald-600"></i> Direct Document Dispatch
                        </span>
                        <h2 class="text-2xl sm:text-3xl font-bold text-slate-900">Send Documents for Printing</h2>
                        <p class="text-slate-600 text-sm sm:text-base mt-2">
                            Upload your documents from your phone or computer. They are securely dispatched via email to the CSC center operator and queued for printing. Collect them at the counter with your Token ID!
                        </p>
                    </div>

                    <!-- Printing Form -->
                    <form id="printOrderForm" onsubmit="handlePrintSubmit(event)" enctype="multipart/form-data" class="space-y-8">
                        
                        <!-- Step 1: Customer Info -->
                        <div>
                            <h3 class="text-base font-bold text-slate-900 flex items-center gap-2 mb-4">
                                <span class="w-6 h-6 rounded-full bg-blue-600 text-white text-xs flex items-center justify-center font-bold">1</span>
                                Customer Information
                            </h3>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1.5">
                                        Your Full Name <span class="text-red-500">*</span>
                                    </label>
                                    <div class="relative">
                                        <i data-lucide="user" class="w-4 h-4 text-slate-400 absolute left-3.5 top-3.5"></i>
                                        <input type="text" name="customer_name" required placeholder="e.g. Ramesh Kumar"
                                               class="w-full pl-10 pr-4 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:bg-white transition">
                                    </div>
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1.5">
                                        Mobile / WhatsApp Number <span class="text-red-500">*</span>
                                    </label>
                                    <div class="relative">
                                        <i data-lucide="phone" class="w-4 h-4 text-slate-400 absolute left-3.5 top-3.5"></i>
                                        <input type="tel" name="customer_phone" required placeholder="e.g. 9876543210"
                                               class="w-full pl-10 pr-4 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:bg-white transition">
                                    </div>
                                </div>
                                <div class="sm:col-span-2">
                                    <label class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1.5">
                                        Email Address <span class="text-slate-400 text-xs font-normal">(Optional, for receipt delivery)</span>
                                    </label>
                                    <div class="relative">
                                        <i data-lucide="mail" class="w-4 h-4 text-slate-400 absolute left-3.5 top-3.5"></i>
                                        <input type="email" name="customer_email" placeholder="e.g. customer@gmail.com"
                                               class="w-full pl-10 pr-4 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:bg-white transition">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Step 2: Upload Documents -->
                        <div>
                            <h3 class="text-base font-bold text-slate-900 flex items-center gap-2 mb-4">
                                <span class="w-6 h-6 rounded-full bg-blue-600 text-white text-xs flex items-center justify-center font-bold">2</span>
                                Select Documents (Multiple Supported)
                            </h3>

                            <!-- Drag & Drop Zone -->
                            <div id="dropzone" class="file-dropzone border-2 border-dashed border-slate-300 rounded-2xl p-8 text-center bg-slate-50 hover:bg-blue-50/50 hover:border-blue-400 transition cursor-pointer"
                                 onclick="document.getElementById('fileInput').click()">
                                <input type="file" id="fileInput" name="documents[]" multiple accept=".pdf,.doc,.docx,.jpg,.jpeg,.png,.txt,.xls,.xlsx" class="hidden" onchange="handleFileSelect(event)">
                                <div class="w-14 h-14 rounded-2xl bg-blue-100 text-blue-600 flex items-center justify-center mx-auto mb-3 shadow-sm">
                                    <i data-lucide="upload-cloud" class="w-8 h-8"></i>
                                </div>
                                <h4 class="text-base font-bold text-slate-800">Click to upload or drag & drop files here</h4>
                                <p class="text-xs text-slate-500 mt-1">
                                    Supported: PDF, Word (DOC/DOCX), Images (JPG, PNG), Excel, TXT • Max 25 MB total
                                </p>
                            </div>

                            <!-- Selected Files List -->
                            <div id="fileListContainer" class="mt-4 space-y-2 hidden">
                                <p class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Selected Files (<span id="fileCountBadge">0</span>):</p>
                                <div id="selectedFilesList" class="space-y-2"></div>
                            </div>
                        </div>

                        <!-- Step 3: Print Preferences -->
                        <div>
                            <h3 class="text-base font-bold text-slate-900 flex items-center gap-2 mb-4">
                                <span class="w-6 h-6 rounded-full bg-blue-600 text-white text-xs flex items-center justify-center font-bold">3</span>
                                Print Preferences
                            </h3>

                            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-4">
                                <div>
                                    <label class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1.5">Color Mode</label>
                                    <select name="print_type" id="prefPrintType" onchange="calculatePriceEstimate()"
                                            class="w-full px-3 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-sm font-medium focus:ring-2 focus:ring-blue-500 focus:bg-white transition">
                                        <option value="B&W" selected>Black & White (₹<?= $rateBwSingle ?>/pg)</option>
                                        <option value="Color">Color (₹<?= $rateColorSingle ?>/pg)</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1.5">Sides</label>
                                    <select name="sides" id="prefSides" onchange="calculatePriceEstimate()"
                                            class="w-full px-3 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-sm font-medium focus:ring-2 focus:ring-blue-500 focus:bg-white transition">
                                        <option value="Single Sided" selected>Single Sided</option>
                                        <option value="Both Sided (Duplex)">Both Sided (Duplex)</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1.5">Paper Size</label>
                                    <select name="paper_size" id="prefPaperSize" onchange="calculatePriceEstimate()"
                                            class="w-full px-3 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-sm font-medium focus:ring-2 focus:ring-blue-500 focus:bg-white transition">
                                        <option value="A4" selected>A4 (Standard)</option>
                                        <option value="Legal">Legal (Registry / Court)</option>
                                        <option value="Glossy Photo Paper">Glossy Photo Paper</option>
                                        <option value="A3">A3 Size</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1.5">Copies</label>
                                    <input type="number" name="copies" id="prefCopies" value="1" min="1" max="100" onchange="calculatePriceEstimate()" onkeyup="calculatePriceEstimate()"
                                           class="w-full px-3 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-sm font-medium focus:ring-2 focus:ring-blue-500 focus:bg-white transition">
                                </div>
                            </div>

                            <div class="mt-4">
                                <label class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1.5">
                                    Special Instructions / Notes <span class="text-slate-400 text-xs font-normal">(Optional)</span>
                                </label>
                                <textarea name="notes" rows="2" placeholder="e.g. Please staple top-left corner, print pages 1 to 5 only, or lamination needed."
                                          class="w-full px-4 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-sm focus:ring-2 focus:ring-blue-500 focus:bg-white transition"></textarea>
                            </div>
                        </div>

                        <!-- Price Estimate Card & Submission -->
                        <div class="bg-gradient-to-r from-blue-50 to-indigo-50 border border-blue-200 rounded-2xl p-6 flex flex-col sm:flex-row items-center justify-between gap-4">
                            <div>
                                <span class="text-xs font-semibold text-blue-700 uppercase tracking-wider">Estimated Print Cost</span>
                                <div class="flex items-baseline gap-2 mt-0.5">
                                    <span id="estimatedPrice" class="text-3xl font-extrabold text-blue-900">₹0.00</span>
                                    <span class="text-xs text-slate-500" id="estimateExplanation">(Upload files to estimate)</span>
                                </div>
                                <p class="text-xs text-slate-500 mt-1">Exact charges based on total page count will be verified at counter.</p>
                            </div>
                            <button type="submit" id="submitPrintBtn" class="w-full sm:w-auto inline-flex items-center justify-center gap-2.5 px-8 py-3.5 rounded-xl font-bold text-white bg-blue-600 hover:bg-blue-700 shadow-md hover:shadow-lg transition">
                                <i data-lucide="send" class="w-5 h-5"></i>
                                <span>Send for Printing</span>
                            </button>
                        </div>
                    </form>

                </div>
            </div>
        </div>

        <!-- ================= SECTION 3: TRACK ORDER ================= -->
        <div id="section-track" class="hidden space-y-6">
            <div class="max-w-2xl mx-auto">
                <div class="bg-white rounded-3xl p-6 sm:p-8 border border-slate-200 shadow-sm">
                    <div class="text-center mb-6">
                        <div class="w-12 h-12 rounded-2xl bg-blue-100 text-blue-600 flex items-center justify-center mx-auto mb-3 shadow-sm">
                            <i data-lucide="search" class="w-6 h-6"></i>
                        </div>
                        <h2 class="text-2xl font-bold text-slate-900">Track Your Print Order</h2>
                        <p class="text-slate-600 text-sm mt-1">Enter your Token ID (e.g. CSC-PRN-...) or Mobile Number to check status</p>
                    </div>

                    <form onsubmit="handleTrackOrder(event)" class="flex gap-2">
                        <input type="text" id="trackInput" required placeholder="Enter Token ID or 10-digit Phone"
                               class="flex-1 px-4 py-3 bg-slate-50 border border-slate-300 rounded-xl text-sm focus:ring-2 focus:ring-blue-500 focus:bg-white transition">
                        <button type="submit" class="px-6 py-3 bg-blue-600 hover:bg-blue-700 text-white font-semibold rounded-xl text-sm shadow-sm transition flex items-center gap-2">
                            <i data-lucide="search" class="w-4 h-4"></i>
                            <span>Check</span>
                        </button>
                    </form>

                    <div id="trackResultContainer" class="mt-6 hidden">
                        <!-- Loaded via JS -->
                    </div>
                </div>
            </div>
        </div>

    </main>

    <!-- Citizen Services Quick Strip -->
    <section class="bg-white border-t border-slate-200 py-8">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-center">
                <div class="p-4 rounded-xl bg-slate-50 border border-slate-100">
                    <i data-lucide="file-check-2" class="w-6 h-6 text-blue-600 mx-auto mb-2"></i>
                    <h4 class="font-bold text-slate-800 text-sm">Govt Schemes & eKYC</h4>
                    <p class="text-xs text-slate-500 mt-0.5">PM Kisan, Ayushman & Ration</p>
                </div>
                <div class="p-4 rounded-xl bg-slate-50 border border-slate-100">
                    <i data-lucide="printer" class="w-6 h-6 text-emerald-600 mx-auto mb-2"></i>
                    <h4 class="font-bold text-slate-800 text-sm">Laser Printing & Scanning</h4>
                    <p class="text-xs text-slate-500 mt-0.5">B&W, Color, A4, Legal, Photo</p>
                </div>
                <div class="p-4 rounded-xl bg-slate-50 border border-slate-100">
                    <i data-lucide="shield-check" class="w-6 h-6 text-indigo-600 mx-auto mb-2"></i>
                    <h4 class="font-bold text-slate-800 text-sm">PVC Card Printing</h4>
                    <p class="text-xs text-slate-500 mt-0.5">Aadhaar, PAN & Voter ID</p>
                </div>
                <div class="p-4 rounded-xl bg-slate-50 border border-slate-100">
                    <i data-lucide="graduation-cap" class="w-6 h-6 text-amber-600 mx-auto mb-2"></i>
                    <h4 class="font-bold text-slate-800 text-sm">Online Job Applications</h4>
                    <p class="text-xs text-slate-500 mt-0.5">Admit Cards & Result Printouts</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Public Footer (Strictly No Admin Link) -->
    <footer class="bg-slate-900 text-slate-400 py-10">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex flex-col md:flex-row justify-between items-center gap-6">
                <div class="text-center md:text-left">
                    <h3 class="text-white font-bold text-lg"><?= e($settings['center_name'] ?? 'Digital Solutions') ?></h3>
                    <p class="text-xs text-slate-400 mt-1 max-w-md">
                        Digital Solutions - Authorized Center delivering digital government services, welfare schemes, document printing, and citizen utilities.
                    </p>
                </div>
                <div class="text-center md:text-right text-xs space-y-1">
                    <p class="text-slate-300 font-semibold"><?= e($settings['center_address'] ?? '') ?></p>
                    <p>Phone: <?= e($settings['owner_phone'] ?? '') ?> | Timings: <?= e($settings['opening_hours'] ?? '9 AM - 8 PM') ?></p>
                    <p class="text-slate-500 pt-2">© <?= date('Y') ?> All Rights Reserved. Public Citizen Portal.</p>
                </div>
            </div>
        </div>
    </footer>

    <!-- ================= FORM DETAILS MODAL ================= -->
    <div id="formModal" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-50 flex items-center justify-center p-4 hidden">
        <div class="bg-white rounded-3xl max-w-2xl w-full max-h-[90vh] overflow-y-auto shadow-2xl border border-slate-200">
            <div class="sticky top-0 bg-white border-b border-slate-200 px-6 py-4 flex items-center justify-between z-10">
                <div>
                    <span id="modalCategory" class="px-2.5 py-0.5 rounded text-xs font-semibold bg-blue-100 text-blue-800">Category</span>
                    <h3 id="modalTitle" class="text-xl font-bold text-slate-900 mt-1">Form Title</h3>
                </div>
                <button onclick="closeFormModal()" class="w-8 h-8 rounded-full bg-slate-100 hover:bg-slate-200 text-slate-600 flex items-center justify-center transition">
                    <i data-lucide="x" class="w-5 h-5"></i>
                </button>
            </div>

            <div class="p-6 space-y-6">
                <!-- Department & Important Dates -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 bg-slate-50 p-4 rounded-2xl border border-slate-200 text-sm">
                    <div>
                        <span class="text-xs text-slate-500 font-semibold uppercase">Department</span>
                        <p id="modalDepartment" class="font-medium text-slate-800 mt-0.5">-</p>
                    </div>
                    <div>
                        <span class="text-xs text-slate-500 font-semibold uppercase">Application Deadline</span>
                        <p id="modalDeadline" class="font-bold text-blue-700 mt-0.5">-</p>
                    </div>
                    <div class="sm:col-span-2">
                        <span class="text-xs text-slate-500 font-semibold uppercase">Fee & Charges</span>
                        <p id="modalFee" class="font-medium text-slate-800 mt-0.5">-</p>
                    </div>
                </div>

                <!-- Summary -->
                <div>
                    <h4 class="text-sm font-bold text-slate-900 uppercase tracking-wider mb-2">Description / About Scheme</h4>
                    <p id="modalSummary" class="text-sm text-slate-600 leading-relaxed bg-white border border-slate-100 p-4 rounded-xl"></p>
                </div>

                <!-- Eligibility -->
                <div id="modalEligibilityContainer">
                    <h4 class="text-sm font-bold text-slate-900 uppercase tracking-wider mb-2">Eligibility Criteria</h4>
                    <div id="modalEligibility" class="text-sm text-slate-600 leading-relaxed bg-blue-50/50 border border-blue-100 p-4 rounded-xl"></div>
                </div>

                <!-- Required Documents Checklist -->
                <div id="modalDocsContainer">
                    <h4 class="text-sm font-bold text-slate-900 uppercase tracking-wider mb-2 flex items-center gap-1.5 text-emerald-800">
                        <i data-lucide="clipboard-check" class="w-4 h-4 text-emerald-600"></i> Required Documents to Bring
                    </h4>
                    <div id="modalDocs" class="text-sm text-slate-700 leading-relaxed bg-emerald-50/50 border border-emerald-200 p-4 rounded-xl whitespace-pre-line font-medium"></div>
                </div>

                <!-- Advisory Notice -->
                <div class="bg-amber-50 border border-amber-200 rounded-2xl p-4 flex items-start gap-3">
                    <i data-lucide="alert-circle" class="w-5 h-5 text-amber-600 shrink-0 mt-0.5"></i>
                    <p class="text-xs text-amber-800 leading-relaxed">
                        Please carry original and copies of the listed documents to our center. We verify and upload your application through official government servers.
                    </p>
                </div>

                <!-- Action Buttons -->
                <div class="flex flex-col sm:flex-row items-center justify-end gap-3 pt-4 border-t border-slate-200">
                    <a id="modalOfficialLink" href="#" target="_blank" class="w-full sm:w-auto inline-flex items-center justify-center gap-2 px-5 py-2.5 rounded-xl font-semibold text-blue-600 bg-blue-50 hover:bg-blue-100 text-sm transition">
                        <i data-lucide="external-link" class="w-4 h-4"></i> Official Portal
                    </a>
                    <button onclick="closeFormModal()" class="w-full sm:w-auto px-6 py-2.5 rounded-xl font-semibold text-slate-700 bg-slate-100 hover:bg-slate-200 text-sm transition">
                        Close
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- ================= PRINT SUCCESS RECEIPT MODAL ================= -->
    <div id="printSuccessModal" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-50 flex items-center justify-center p-4 hidden">
        <div class="bg-white rounded-3xl max-w-lg w-full p-6 sm:p-8 shadow-2xl border border-slate-200 text-center">
            <div class="w-16 h-16 rounded-full bg-emerald-100 text-emerald-600 flex items-center justify-center mx-auto mb-4">
                <i data-lucide="check-circle-2" class="w-9 h-9"></i>
            </div>
            <h3 class="text-2xl font-bold text-slate-900">Documents Submitted!</h3>
            <p class="text-slate-600 text-sm mt-1">Your documents have been dispatched to the CSC operator via email.</p>

            <div class="my-6 p-4 rounded-2xl bg-slate-50 border-2 border-dashed border-slate-300">
                <span class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Your Order Token ID</span>
                <div id="receiptTokenId" class="text-2xl font-black text-blue-600 tracking-wider mt-1">CSC-PRN-00000</div>
                <p class="text-xs text-slate-500 mt-1">Please show this Token ID at our CSC shop counter when picking up.</p>
            </div>

            <div class="bg-blue-50 border border-blue-200 rounded-xl p-3 text-left text-xs text-slate-700 space-y-1 mb-6">
                <div><strong>Customer:</strong> <span id="receiptCustomerName"></span></div>
                <div><strong>Phone:</strong> <span id="receiptCustomerPhone"></span></div>
                <div><strong>Files Count:</strong> <span id="receiptFileCount"></span> document(s)</div>
                <div><strong>Estimated Amount:</strong> ₹<span id="receiptAmount"></span></div>
            </div>

            <div class="flex flex-col sm:flex-row gap-3">
                <button onclick="window.print()" class="flex-1 px-4 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-800 font-semibold rounded-xl text-sm transition flex items-center justify-center gap-2">
                    <i data-lucide="printer" class="w-4 h-4"></i> Print Receipt Slip
                </button>
                <button onclick="closeSuccessModal()" class="flex-1 px-4 py-2.5 bg-blue-600 hover:bg-blue-700 text-white font-semibold rounded-xl text-sm transition">
                    Done
                </button>
            </div>
        </div>
    </div>

    <!-- Application Client-side Scripts -->
    <script>
        // Initialize Lucide icons
        lucide.createIcons();

        // Print rates from server config
        const rates = {
            bwSingle: <?= $rateBwSingle ?>,
            bwDouble: <?= $rateBwDouble ?>,
            colorSingle: <?= $rateColorSingle ?>,
            colorDouble: <?= $rateColorDouble ?>
        };

        // File upload tracking
        let selectedFiles = [];

        function switchTab(tabId) {
            document.querySelectorAll('.nav-tab').forEach(b => {
                b.classList.remove('border-blue-600', 'text-blue-600', 'font-semibold');
                b.classList.add('border-transparent', 'text-slate-600');
            });
            document.getElementById('section-forms').classList.add('hidden');
            document.getElementById('section-print').classList.add('hidden');
            document.getElementById('section-track').classList.add('hidden');

            if (tabId === 'forms-tab') {
                document.getElementById('section-forms').classList.remove('hidden');
                document.getElementById('tab-btn-forms').classList.add('border-blue-600', 'text-blue-600', 'font-semibold');
            } else if (tabId === 'print-tab') {
                document.getElementById('section-print').classList.remove('hidden');
                document.getElementById('tab-btn-print').classList.add('border-blue-600', 'text-blue-600', 'font-semibold');
            } else if (tabId === 'track-tab') {
                document.getElementById('section-track').classList.remove('hidden');
                document.getElementById('tab-btn-track').classList.add('border-blue-600', 'text-blue-600', 'font-semibold');
            }
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }

        // Search and Filter Forms
        function filterForms() {
            const query = document.getElementById('formSearchInput').value.toLowerCase().trim();
            const cards = document.querySelectorAll('.form-card');
            let visibleCount = 0;

            cards.forEach(card => {
                const title = card.getAttribute('data-title') || '';
                const dept = card.getAttribute('data-dept') || '';
                const cat = card.getAttribute('data-category') || '';

                if (title.includes(query) || dept.includes(query) || cat.toLowerCase().includes(query)) {
                    card.classList.remove('hidden');
                    visibleCount++;
                } else {
                    card.classList.add('hidden');
                }
            });

            document.getElementById('noFormsMatch').classList.toggle('hidden', visibleCount > 0);
        }

        function setCategoryFilter(category, btnElement) {
            document.querySelectorAll('.category-pill').forEach(b => {
                b.className = 'category-pill px-4 py-2 rounded-xl bg-white text-slate-700 border border-slate-200 hover:bg-slate-100 font-medium transition whitespace-nowrap';
            });
            btnElement.className = 'category-pill px-4 py-2 rounded-xl bg-blue-600 text-white font-medium shadow-sm transition whitespace-nowrap';

            const cards = document.querySelectorAll('.form-card');
            let visibleCount = 0;

            cards.forEach(card => {
                const cardCat = card.getAttribute('data-category') || '';
                if (category === 'all' || cardCat === category) {
                    card.classList.remove('hidden');
                    visibleCount++;
                } else {
                    card.classList.add('hidden');
                }
            });

            document.getElementById('noFormsMatch').classList.toggle('hidden', visibleCount > 0);
        }

        // View Form Modal
        function openFormModal(form) {
            document.getElementById('modalTitle').textContent = form.title;
            document.getElementById('modalCategory').textContent = form.category;
            document.getElementById('modalDepartment').textContent = form.department || 'Government / CSC Portal';
            document.getElementById('modalDeadline').textContent = form.last_date ? form.last_date : 'Open / No deadline';
            document.getElementById('modalFee').textContent = form.fee_details || 'As per norms';
            document.getElementById('modalSummary').textContent = form.summary || 'Details available at CSC counter.';
            
            const elig = document.getElementById('modalEligibility');
            if (form.eligibility) {
                elig.textContent = form.eligibility;
                document.getElementById('modalEligibilityContainer').classList.remove('hidden');
            } else {
                document.getElementById('modalEligibilityContainer').classList.add('hidden');
            }

            const docs = document.getElementById('modalDocs');
            if (form.required_documents) {
                docs.textContent = form.required_documents;
                document.getElementById('modalDocsContainer').classList.remove('hidden');
            } else {
                document.getElementById('modalDocsContainer').classList.add('hidden');
            }

            const linkBtn = document.getElementById('modalOfficialLink');
            if (form.official_url) {
                linkBtn.href = form.official_url;
                linkBtn.classList.remove('hidden');
            } else {
                linkBtn.classList.add('hidden');
            }

            document.getElementById('formModal').classList.remove('hidden');
        }

        function closeFormModal() {
            document.getElementById('formModal').classList.add('hidden');
        }

        // File Upload and Drag & Drop
        const dropzone = document.getElementById('dropzone');
        ['dragenter', 'dragover'].forEach(name => {
            dropzone.addEventListener(name, (e) => {
                e.preventDefault();
                dropzone.classList.add('dragover');
            });
        });
        ['dragleave', 'drop'].forEach(name => {
            dropzone.addEventListener(name, (e) => {
                e.preventDefault();
                dropzone.classList.remove('dragover');
            });
        });
        dropzone.addEventListener('drop', (e) => {
            if (e.dataTransfer.files && e.dataTransfer.files.length > 0) {
                appendFiles(e.dataTransfer.files);
            }
        });

        function handleFileSelect(e) {
            if (e.target.files && e.target.files.length > 0) {
                appendFiles(e.target.files);
            }
        }

        function appendFiles(fileList) {
            for (let i = 0; i < fileList.length; i++) {
                selectedFiles.push(fileList[i]);
            }
            renderFileList();
            calculatePriceEstimate();
        }

        function removeFile(index) {
            selectedFiles.splice(index, 1);
            renderFileList();
            calculatePriceEstimate();
        }

        function renderFileList() {
            const container = document.getElementById('selectedFilesList');
            const wrapper = document.getElementById('fileListContainer');
            const badge = document.getElementById('fileCountBadge');
            
            badge.textContent = selectedFiles.length;
            if (selectedFiles.length === 0) {
                wrapper.classList.add('hidden');
                container.innerHTML = '';
                return;
            }

            wrapper.classList.remove('hidden');
            let html = '';
            selectedFiles.forEach((file, idx) => {
                const sizeKb = (file.size / 1024).toFixed(1);
                html += `
                    <div class="flex items-center justify-between p-3 bg-white border border-slate-200 rounded-xl shadow-xs text-xs">
                        <div class="flex items-center gap-2 truncate">
                            <i data-lucide="file" class="w-4 h-4 text-blue-600 shrink-0"></i>
                            <span class="font-semibold text-slate-800 truncate">${file.name}</span>
                            <span class="text-slate-400">(${sizeKb} KB)</span>
                        </div>
                        <button type="button" onclick="removeFile(${idx})" class="text-red-500 hover:text-red-700 p-1 rounded-lg hover:bg-red-50 transition">
                            <i data-lucide="trash-2" class="w-4 h-4"></i>
                        </button>
                    </div>
                `;
            });
            container.innerHTML = html;
            lucide.createIcons();
        }

        // Live Cost Estimate Calculation
        function calculatePriceEstimate() {
            const count = selectedFiles.length;
            if (count === 0) {
                document.getElementById('estimatedPrice').textContent = '₹0.00';
                document.getElementById('estimateExplanation').textContent = '(Upload files to estimate)';
                return;
            }

            const printType = document.getElementById('prefPrintType').value;
            const sides = document.getElementById('prefSides').value;
            const copies = parseInt(document.getElementById('prefCopies').value) || 1;

            let ratePerDoc = rates.bwSingle;
            if (printType === 'Color') {
                ratePerDoc = (sides === 'Both Sided (Duplex)') ? rates.colorDouble : rates.colorSingle;
            } else {
                ratePerDoc = (sides === 'Both Sided (Duplex)') ? rates.bwDouble : rates.bwSingle;
            }

            const total = count * ratePerDoc * copies;
            document.getElementById('estimatedPrice').textContent = '₹' + total.toFixed(2);
            document.getElementById('estimateExplanation').textContent = `(${count} docs × ₹${ratePerDoc} × ${copies} copies)`;
        }

        // Submit Print Form via AJAX
        async function handlePrintSubmit(e) {
            e.preventDefault();
            if (selectedFiles.length === 0) {
                alert('Please upload at least one document to proceed.');
                return;
            }

            const btn = document.getElementById('submitPrintBtn');
            const originalText = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = `<i data-lucide="loader-2" class="w-5 h-5 animate-spin"></i> <span>Uploading & Dispatching...</span>`;
            lucide.createIcons();

            const form = document.getElementById('printOrderForm');
            const formData = new FormData(form);
            
            // Remove the default empty file input and append tracked files
            formData.delete('documents[]');
            selectedFiles.forEach(file => {
                formData.append('documents[]', file);
            });

            try {
                const response = await fetch('api/submit_print.php', {
                    method: 'POST',
                    body: formData
                });
                const result = await response.json();

                if (result.success) {
                    // Populate success modal
                    document.getElementById('receiptTokenId').textContent = result.token_id;
                    document.getElementById('receiptCustomerName').textContent = result.customer_name;
                    document.getElementById('receiptCustomerPhone').textContent = result.customer_phone;
                    document.getElementById('receiptFileCount').textContent = result.file_count;
                    document.getElementById('receiptAmount').textContent = result.estimated_amount;

                    document.getElementById('printSuccessModal').classList.remove('hidden');

                    // Reset form
                    form.reset();
                    selectedFiles = [];
                    renderFileList();
                    calculatePriceEstimate();
                } else {
                    alert('Submission Error: ' + (result.message || 'Something went wrong.'));
                }
            } catch (err) {
                alert('Connection error. Please check your internet connection.');
            } finally {
                btn.disabled = false;
                btn.innerHTML = originalText;
                lucide.createIcons();
            }
        }

        function closeSuccessModal() {
            document.getElementById('printSuccessModal').classList.add('hidden');
        }

        // Track Order
        async function handleTrackOrder(e) {
            e.preventDefault();
            const query = document.getElementById('trackInput').value.trim();
            const container = document.getElementById('trackResultContainer');
            container.classList.remove('hidden');
            container.innerHTML = `<div class="p-6 text-center text-slate-500"><i data-lucide="loader-2" class="w-6 h-6 animate-spin mx-auto mb-2 text-blue-600"></i> Searching records...</div>`;
            lucide.createIcons();

            try {
                const res = await fetch(`api/track_order.php?q=${encodeURIComponent(query)}`);
                const data = await res.json();

                if (!data.success || !data.orders.length) {
                    container.innerHTML = `
                        <div class="p-6 bg-red-50 border border-red-200 rounded-2xl text-center text-red-700 text-sm">
                            <i data-lucide="alert-triangle" class="w-6 h-6 mx-auto mb-2 text-red-500"></i>
                            <strong>No print orders found.</strong> Please verify your Token ID or phone number.
                        </div>
                    `;
                } else {
                    let html = `<div class="space-y-4">`;
                    data.orders.forEach(o => {
                        let statusColor = 'bg-amber-100 text-amber-800 border-amber-200';
                        if (o.status === 'Ready') statusColor = 'bg-emerald-100 text-emerald-800 border-emerald-200';
                        if (o.status === 'Delivered') statusColor = 'bg-blue-100 text-blue-800 border-blue-200';

                        html += `
                            <div class="p-4 bg-slate-50 border border-slate-200 rounded-2xl space-y-2">
                                <div class="flex items-center justify-between">
                                    <span class="text-xs font-bold text-slate-500">${o.created_at}</span>
                                    <span class="px-2.5 py-1 rounded-full text-xs font-bold border ${statusColor}">${o.status}</span>
                                </div>
                                <div class="flex items-baseline justify-between">
                                    <h4 class="font-bold text-slate-900 text-base">${o.token_id}</h4>
                                    <span class="text-sm font-semibold text-slate-700">${o.estimated_amount}</span>
                                </div>
                                <p class="text-xs text-slate-600">
                                    Customer: <strong>${o.customer_name}</strong> • ${o.print_type} (${o.sides}) • ${o.file_count} File(s) • ${o.copies} Copy
                                </p>
                            </div>
                        `;
                    });
                    html += `</div>`;
                    container.innerHTML = html;
                }
                lucide.createIcons();
            } catch (err) {
                container.innerHTML = `<div class="p-4 bg-red-50 text-red-600 text-sm text-center">Failed to fetch order status.</div>`;
            }
        }
    </script>
</body>
</html>
