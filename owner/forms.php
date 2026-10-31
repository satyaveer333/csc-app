<?php
/**
 * CSC Owner Portal - Government Forms & Vacancy Manager
 */

define('CSC_APP', true);
require_once dirname(__DIR__) . '/includes/config.php';
require_once dirname(__DIR__) . '/includes/db.php';
require_once dirname(__DIR__) . '/includes/helpers.php';
require_once dirname(__DIR__) . '/includes/auth.php';

requireOwnerLogin();
ensureDirectories();
$pdo = getDbConnection();

$action = $_POST['action'] ?? $_GET['action'] ?? '';

// Handle Form Submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // Verify CSRF Token
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        setFlash('danger', 'Security validation failed (Invalid CSRF Token). Please try again.');
        header('Location: forms.php');
        exit;
    }

    // Helper to upload form attachments
    $uploadAttachment = function() {
        if (!empty($_FILES['attachment_file']['name']) && $_FILES['attachment_file']['error'] === UPLOAD_ERR_OK) {
            $ext = strtolower(pathinfo($_FILES['attachment_file']['name'], PATHINFO_EXTENSION));
            $allowed = ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png'];
            if (in_array($ext, $allowed) && $_FILES['attachment_file']['size'] <= MAX_FILE_SIZE) {
                $safeName = 'form_' . date('Ymd_His') . '_' . bin2hex(random_bytes(3)) . '.' . $ext;
                if (move_uploaded_file($_FILES['attachment_file']['tmp_name'], FORMS_DIR . '/' . $safeName)) {
                    return $safeName;
                }
            }
        }
        return null;
    };

    // 1. ADD NEW FORM
    if ($action === 'create') {
        $title = trim(strip_tags($_POST['title'] ?? ''));
        $category = trim(strip_tags($_POST['category'] ?? ''));
        $department = trim(strip_tags($_POST['department'] ?? ''));
        $summary = trim(strip_tags($_POST['summary'] ?? ''));
        $eligibility = trim(strip_tags($_POST['eligibility'] ?? ''));
        $requiredDocs = trim(strip_tags($_POST['required_documents'] ?? ''));
        $feeDetails = trim(strip_tags($_POST['fee_details'] ?? ''));
        $startDate = !empty($_POST['start_date']) ? trim($_POST['start_date']) : null;
        $lastDate = !empty($_POST['last_date']) ? trim($_POST['last_date']) : null;
        $officialUrl = trim(strip_tags($_POST['official_url'] ?? ''));
        $status = in_array($_POST['status'] ?? '', ['active', 'inactive']) ? $_POST['status'] : 'active';
        $attachmentFile = $uploadAttachment() ?: '';

        // Validation checks
        if (empty($title) || empty($category)) {
            setFlash('danger', 'Form Title and Category are required.');
            header('Location: forms.php');
            exit;
        }
        if (mb_strlen($title) < 3 || mb_strlen($title) > 255) {
            setFlash('danger', 'Form Title must be between 3 and 255 characters.');
            header('Location: forms.php');
            exit;
        }
        if (!empty($officialUrl)) {
            if (!filter_var($officialUrl, FILTER_VALIDATE_URL)) {
                if (filter_var('https://' . $officialUrl, FILTER_VALIDATE_URL)) {
                    $officialUrl = 'https://' . $officialUrl;
                } else {
                    setFlash('danger', 'Official URL must be a valid web address.');
                    header('Location: forms.php');
                    exit;
                }
            }
        }
        if ($startDate && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $startDate)) $startDate = null;
        if ($lastDate && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $lastDate)) $lastDate = null;
        if ($startDate && $lastDate && strtotime($lastDate) < strtotime($startDate)) {
            setFlash('danger', 'Application deadline (Last Date) cannot be earlier than start date.');
            header('Location: forms.php');
            exit;
        }

        $stmt = $pdo->prepare("
            INSERT INTO forms (
                title, category, department, summary, eligibility, 
                required_documents, fee_details, start_date, last_date, 
                official_url, attachment_file, status, created_at, updated_at
            ) VALUES (
                :title, :category, :department, :summary, :eligibility, 
                :required_documents, :fee_details, :start_date, :last_date, 
                :official_url, :attachment_file, :status, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP
            )
        ");
        $stmt->execute([
            ':title' => $title,
            ':category' => $category,
            ':department' => $department,
            ':summary' => $summary,
            ':eligibility' => $eligibility,
            ':required_documents' => $requiredDocs,
            ':fee_details' => $feeDetails,
            ':start_date' => $startDate,
            ':last_date' => $lastDate,
            ':official_url' => $officialUrl,
            ':attachment_file' => $attachmentFile,
            ':status' => $status
        ]);
        setFlash('success', 'New form notification added successfully!');
        header('Location: forms.php');
        exit;
    }

    // 2. UPDATE FORM
    if ($action === 'update') {
        $id = (int)$_POST['id'];
        $title = trim(strip_tags($_POST['title'] ?? ''));
        $category = trim(strip_tags($_POST['category'] ?? ''));
        $department = trim(strip_tags($_POST['department'] ?? ''));
        $summary = trim(strip_tags($_POST['summary'] ?? ''));
        $eligibility = trim(strip_tags($_POST['eligibility'] ?? ''));
        $requiredDocs = trim(strip_tags($_POST['required_documents'] ?? ''));
        $feeDetails = trim(strip_tags($_POST['fee_details'] ?? ''));
        $startDate = !empty($_POST['start_date']) ? trim($_POST['start_date']) : null;
        $lastDate = !empty($_POST['last_date']) ? trim($_POST['last_date']) : null;
        $officialUrl = trim(strip_tags($_POST['official_url'] ?? ''));
        $status = in_array($_POST['status'] ?? '', ['active', 'inactive']) ? $_POST['status'] : 'active';
        $newAttachment = $uploadAttachment();

        if ($id <= 0 || empty($title) || empty($category)) {
            setFlash('danger', 'Invalid form ID or missing required fields.');
            header('Location: forms.php');
            exit;
        }
        if (mb_strlen($title) < 3 || mb_strlen($title) > 255) {
            setFlash('danger', 'Form Title must be between 3 and 255 characters.');
            header('Location: forms.php');
            exit;
        }
        if (!empty($officialUrl)) {
            if (!filter_var($officialUrl, FILTER_VALIDATE_URL)) {
                if (filter_var('https://' . $officialUrl, FILTER_VALIDATE_URL)) {
                    $officialUrl = 'https://' . $officialUrl;
                } else {
                    setFlash('danger', 'Official URL must be a valid web address.');
                    header('Location: forms.php');
                    exit;
                }
            }
        }
        if ($startDate && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $startDate)) $startDate = null;
        if ($lastDate && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $lastDate)) $lastDate = null;
        if ($startDate && $lastDate && strtotime($lastDate) < strtotime($startDate)) {
            setFlash('danger', 'Application deadline (Last Date) cannot be earlier than start date.');
            header('Location: forms.php');
            exit;
        }

        if ($newAttachment !== null) {
            $prev = $pdo->prepare("SELECT attachment_file FROM forms WHERE id = :id");
            $prev->execute([':id' => $id]);
            $oldFile = $prev->fetchColumn();
            if ($oldFile && file_exists(FORMS_DIR . '/' . $oldFile)) {
                @unlink(FORMS_DIR . '/' . $oldFile);
            }

            $stmt = $pdo->prepare("
                UPDATE forms SET 
                    title = :title, 
                    category = :category, 
                    department = :department, 
                    summary = :summary, 
                    eligibility = :eligibility, 
                    required_documents = :required_documents, 
                    fee_details = :fee_details, 
                    start_date = :start_date, 
                    last_date = :last_date, 
                    official_url = :official_url,
                    attachment_file = :attachment_file,
                    status = :status,
                    updated_at = CURRENT_TIMESTAMP
                WHERE id = :id
            ");
            $stmt->execute([
                ':id' => $id,
                ':title' => $title,
                ':category' => $category,
                ':department' => $department,
                ':summary' => $summary,
                ':eligibility' => $eligibility,
                ':required_documents' => $requiredDocs,
                ':fee_details' => $feeDetails,
                ':start_date' => $startDate,
                ':last_date' => $lastDate,
                ':official_url' => $officialUrl,
                ':attachment_file' => $newAttachment,
                ':status' => $status
            ]);
        } else {
            $stmt = $pdo->prepare("
                UPDATE forms SET 
                    title = :title, 
                    category = :category, 
                    department = :department, 
                    summary = :summary, 
                    eligibility = :eligibility, 
                    required_documents = :required_documents, 
                    fee_details = :fee_details, 
                    start_date = :start_date, 
                    last_date = :last_date, 
                    official_url = :official_url, 
                    status = :status,
                    updated_at = CURRENT_TIMESTAMP
                WHERE id = :id
            ");
            $stmt->execute([
                ':id' => $id,
                ':title' => $title,
                ':category' => $category,
                ':department' => $department,
                ':summary' => $summary,
                ':eligibility' => $eligibility,
                ':required_documents' => $requiredDocs,
                ':fee_details' => $feeDetails,
                ':start_date' => $startDate,
                ':last_date' => $lastDate,
                ':official_url' => $officialUrl,
                ':status' => $status
            ]);
        }

        setFlash('success', "Form '{$title}' updated successfully.");
        header('Location: forms.php');
        exit;
    }

    // 3. DELETE FORM
    if ($action === 'delete') {
        $id = (int)$_POST['id'];
        if ($id > 0) {
            $prev = $pdo->prepare("SELECT attachment_file FROM forms WHERE id = :id");
            $prev->execute([':id' => $id]);
            $oldFile = $prev->fetchColumn();
            if ($oldFile && file_exists(FORMS_DIR . '/' . $oldFile)) {
                @unlink(FORMS_DIR . '/' . $oldFile);
            }

            $stmt = $pdo->prepare("DELETE FROM forms WHERE id = :id");
            $stmt->execute([':id' => $id]);
            setFlash('success', 'Form notification removed.');
        }
        header('Location: forms.php');
        exit;
    }

    // 4. TOGGLE STATUS
    if ($action === 'toggle_status') {
        $id = (int)$_POST['id'];
        $status = ($_POST['status'] === 'active') ? 'active' : 'inactive';
        if ($id > 0) {
            $stmt = $pdo->prepare("UPDATE forms SET status = :status WHERE id = :id");
            $stmt->execute([':status' => $status, ':id' => $id]);
            setFlash('success', 'Form visibility updated.');
        }
        header('Location: forms.php');
        exit;
    }
}

// Fetch all forms
$stmt = $pdo->query("SELECT * FROM forms ORDER BY id DESC");
$allForms = $stmt->fetchAll();

$categories = [
    'Welfare Schemes',
    'Recruitment & Jobs',
    'Scholarships & Education',
    'Health & Insurance',
    'Certificates & Identity',
    'Bill & Utility',
    'Banking & Pension',
    'Other Services'
];

$flash = getFlash();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Forms - <?= e(getSetting('center_name', 'Digital Solutions', $pdo)) ?></title>
    <!-- Favicon (DS Monogram) -->
    <link rel="icon" type="image/svg+xml" href="../favicon.svg">
    <link rel="alternate icon" href="../favicon.svg">
    <script>
        if (localStorage.getItem('owner_theme') === 'light') {
            document.documentElement.classList.remove('dark');
        } else {
            document.documentElement.classList.add('dark');
        }
    </script>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class'
        };
    </script>
    <script src="https://unpkg.com/lucide@latest"></script>
</head>
<body class="min-h-screen bg-slate-100 text-slate-800 dark:bg-slate-900 dark:text-slate-100 flex flex-col antialiased transition-colors duration-150">

    <?php include __DIR__ . '/nav.php'; ?>

    <main class="flex-1 max-w-7xl w-full mx-auto px-4 sm:px-6 lg:px-8 py-6 sm:py-8 space-y-6">
        
        <!-- Header -->
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-4 border-b border-slate-200 dark:border-slate-800">
            <div>
                <h1 class="text-2xl sm:text-3xl font-bold text-slate-900 dark:text-white tracking-tight">Government & Vacancy Forms</h1>
                <p class="text-slate-500 dark:text-slate-400 text-xs sm:text-sm mt-1">Add, update, or remove citizen services visible on the public portal.</p>
            </div>
            <button onclick="openFormModal()" class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl font-semibold bg-emerald-600 hover:bg-emerald-500 text-white shadow-sm transition">
                <i data-lucide="plus-circle" class="w-4 h-4"></i>
                <span>Add New Form</span>
            </button>
        </div>

        <?php if ($flash): ?>
            <div class="p-4 rounded-xl <?= $flash['type'] === 'success' ? 'bg-emerald-50 border border-emerald-200 text-emerald-800 dark:bg-emerald-950/70 dark:border-emerald-800 dark:text-emerald-300' : 'bg-red-50 border border-red-200 text-red-800 dark:bg-red-950/70 dark:border-red-800 dark:text-red-300' ?> text-xs sm:text-sm flex items-center gap-2 shadow-sm">
                <i data-lucide="<?= $flash['type'] === 'success' ? 'check-circle' : 'alert-circle' ?>" class="w-5 h-5 shrink-0"></i>
                <span><?= e($flash['message']) ?></span>
            </div>
        <?php endif; ?>

        <!-- Forms Table Container -->
        <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 overflow-hidden shadow-sm">
            <div class="p-4 bg-slate-50 dark:bg-slate-800/80 border-b border-slate-200 dark:border-slate-700 flex flex-col sm:flex-row justify-between items-center gap-3">
                <div class="relative w-full sm:w-80">
                    <i data-lucide="search" class="w-4 h-4 text-slate-400 absolute left-3.5 top-3"></i>
                    <input type="text" id="ownerFormSearch" onkeyup="filterOwnerForms()" placeholder="Search posted forms..."
                           class="w-full pl-9 pr-4 py-2 bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-xl text-xs text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
                <div class="text-xs text-slate-500 dark:text-slate-400">
                    Total Forms: <strong class="text-slate-900 dark:text-white"><?= count($allForms) ?></strong>
                </div>
            </div>

            <?php if (empty($allForms)): ?>
                <div class="p-12 text-center text-slate-400">
                    <i data-lucide="inbox" class="w-12 h-12 mx-auto mb-3 opacity-50"></i>
                    <h3 class="text-base font-bold text-slate-700 dark:text-slate-300">No Forms Found</h3>
                    <p class="text-xs text-slate-500 mt-1">Click "Add New Form" above to post your first form notification.</p>
                </div>
            <?php else: ?>
                <div class="overflow-x-auto w-full">
                    <table class="w-full text-left text-sm" id="ownerFormsTable">
                        <thead class="bg-slate-100 dark:bg-slate-900/60 text-xs uppercase text-slate-600 dark:text-slate-400 border-b border-slate-200 dark:border-slate-700">
                            <tr>
                                <th class="px-5 py-3.5">Form Details</th>
                                <th class="px-4 py-3.5">Category</th>
                                <th class="px-4 py-3.5">Deadline</th>
                                <th class="px-4 py-3.5">Fee</th>
                                <th class="px-4 py-3.5 text-center">Status</th>
                                <th class="px-5 py-3.5 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200 dark:divide-slate-700/60">
                            <?php foreach ($allForms as $f): ?>
                                <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/40 transition form-row" data-search="<?= e(strtolower($f['title'] . ' ' . $f['category'] . ' ' . $f['department'])) ?>">
                                    <td class="px-5 py-4">
                                        <div class="font-bold text-slate-900 dark:text-white text-base"><?= e($f['title']) ?></div>
                                        <?php if (!empty($f['department'])): ?>
                                            <div class="text-xs text-slate-500 dark:text-slate-400 mt-0.5"><?= e($f['department']) ?></div>
                                        <?php endif; ?>
                                        <?php if (!empty($f['official_url'])): ?>
                                            <a href="<?= e($f['official_url']) ?>" target="_blank" class="inline-flex items-center gap-1 text-xs text-blue-600 dark:text-blue-400 hover:underline mt-1">
                                                <span>Official Portal</span>
                                                <i data-lucide="external-link" class="w-3 h-3"></i>
                                            </a>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-4 py-4 text-xs whitespace-nowrap">
                                        <span class="px-2.5 py-1 rounded-md bg-blue-50 border border-blue-200 text-blue-800 dark:bg-blue-950/70 dark:border-blue-800 dark:text-blue-300 font-medium">
                                            <?= e($f['category']) ?>
                                        </span>
                                    </td>
                                    <td class="px-4 py-4 text-xs text-slate-600 dark:text-slate-300 whitespace-nowrap font-medium">
                                        <?= formatDate($f['last_date']) ?>
                                    </td>
                                    <td class="px-4 py-4 text-xs text-slate-600 dark:text-slate-300">
                                        <?= !empty($f['fee_details']) ? e($f['fee_details']) : '<span class="text-slate-400">Standard</span>' ?>
                                    </td>
                                    <td class="px-4 py-4 text-center whitespace-nowrap">
                                        <form method="POST" class="inline-block">
                                            <input type="hidden" name="csrf_token" value="<?= e(getCsrfToken()) ?>">
                                            <input type="hidden" name="action" value="toggle_status">
                                            <input type="hidden" name="id" value="<?= $f['id'] ?>">
                                            <input type="hidden" name="status" value="<?= $f['status'] === 'active' ? 'inactive' : 'active' ?>">
                                            <button type="submit" class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-semibold cursor-pointer <?= $f['status'] === 'active' ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-400 border border-emerald-300 dark:border-emerald-800' : 'bg-slate-200 text-slate-700 dark:bg-slate-700 dark:text-slate-400 border border-slate-300 dark:border-slate-600' ?>">
                                                <span class="w-1.5 h-1.5 rounded-full <?= $f['status'] === 'active' ? 'bg-emerald-500' : 'bg-slate-400' ?>"></span>
                                                <span><?= ucfirst($f['status']) ?></span>
                                            </button>
                                        </form>
                                    </td>
                                    <td class="px-5 py-4 text-right whitespace-nowrap">
                                        <div class="flex items-center justify-end gap-2">
                                            <button type="button" onclick="editForm(<?= htmlspecialchars(json_encode($f), ENT_QUOTES, 'UTF-8') ?>)"
                                                    class="p-1.5 rounded-lg bg-slate-100 hover:bg-blue-50 text-slate-600 hover:text-blue-600 dark:bg-slate-700 dark:hover:bg-blue-900/40 dark:text-slate-300 dark:hover:text-blue-300 transition" title="Edit Form">
                                                <i data-lucide="edit-3" class="w-4 h-4"></i>
                                            </button>
                                            <form method="POST" onsubmit="return confirm('Do you really want to delete this form?');" class="inline-block">
                                                <input type="hidden" name="csrf_token" value="<?= e(getCsrfToken()) ?>">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="id" value="<?= $f['id'] ?>">
                                                <button type="submit" class="p-1.5 rounded-lg bg-slate-100 hover:bg-red-50 text-slate-600 hover:text-red-600 dark:bg-slate-700 dark:hover:bg-red-950 dark:text-slate-300 dark:hover:text-red-400 transition" title="Delete Form">
                                                    <i data-lucide="trash-2" class="w-4 h-4"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

    </main>

    <!-- Modal Form (Add / Edit) -->
    <div id="modalFormContainer" class="fixed inset-0 z-50 bg-slate-900/70 backdrop-blur-sm flex items-center justify-center p-3 sm:p-4 hidden">
        <div class="bg-white dark:bg-slate-800 rounded-3xl max-w-2xl w-full p-5 sm:p-6 border border-slate-200 dark:border-slate-700 shadow-2xl max-h-[92vh] overflow-y-auto">
            
            <div class="flex items-center justify-between pb-4 border-b border-slate-200 dark:border-slate-700 mb-5">
                <h3 id="modalHeading" class="text-base sm:text-lg font-bold text-slate-900 dark:text-white flex items-center gap-2">
                    <i data-lucide="file-plus" class="w-5 h-5 text-blue-500"></i>
                    <span>Post New Form</span>
                </h3>
                <button onclick="closeFormModal()" class="text-slate-400 hover:text-slate-600 dark:hover:text-white p-1 rounded-lg">
                    <i data-lucide="x" class="w-5 h-5"></i>
                </button>
            </div>

            <form method="POST" enctype="multipart/form-data" id="formPostForm" class="space-y-4">
                <input type="hidden" name="csrf_token" value="<?= e(getCsrfToken()) ?>">
                <input type="hidden" name="action" id="formAction" value="create">
                <input type="hidden" name="id" id="formId" value="">

                <div>
                    <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-1.5">
                        Form / Scheme Title <span class="text-red-500">*</span>
                    </label>
                    <input type="text" name="title" id="inpTitle" required minlength="3" maxlength="255" placeholder="e.g. PM Kisan Samman Nidhi - New Registration"
                           class="w-full px-4 py-2.5 bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-xl text-slate-900 dark:text-white text-sm focus:ring-2 focus:ring-blue-500">
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-1.5">Category <span class="text-red-500">*</span></label>
                        <select name="category" id="inpCategory" required class="w-full px-3 py-2.5 bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-xl text-slate-900 dark:text-white text-sm focus:ring-2 focus:ring-blue-500">
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?= e($cat) ?>"><?= e($cat) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-1.5">Department / Ministry</label>
                        <input type="text" name="department" id="inpDepartment" placeholder="e.g. Ministry of Agriculture"
                               class="w-full px-4 py-2.5 bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-xl text-slate-900 dark:text-white text-sm focus:ring-2 focus:ring-blue-500">
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-1.5">Summary / Short Description</label>
                    <textarea name="summary" id="inpSummary" rows="2" placeholder="Brief explanation of the scheme or vacancy..."
                              class="w-full px-4 py-2.5 bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-xl text-slate-900 dark:text-white text-sm focus:ring-2 focus:ring-blue-500"></textarea>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-1.5">Eligibility Criteria</label>
                    <textarea name="eligibility" id="inpEligibility" rows="2" placeholder="Age limit, qualification, family income, etc."
                              class="w-full px-4 py-2.5 bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-xl text-slate-900 dark:text-white text-sm focus:ring-2 focus:ring-blue-500"></textarea>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-emerald-600 dark:text-emerald-400 uppercase tracking-wider mb-1.5">
                        Required Documents Checklist (What citizen must bring)
                    </label>
                    <textarea name="required_documents" id="inpDocs" rows="3" placeholder="1. Aadhaar Card&#10;2. Ration Card&#10;3. Bank Passbook&#10;4. 2 Passport Photos"
                              class="w-full px-4 py-2.5 bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-xl text-slate-900 dark:text-white text-sm focus:ring-2 focus:ring-blue-500 font-mono"></textarea>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 sm:gap-4">
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-1.5">Start Date</label>
                        <input type="date" name="start_date" id="inpStartDate"
                               class="w-full px-3 py-2 bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-xl text-slate-900 dark:text-white text-xs sm:text-sm focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-1.5">Last Date (Deadline)</label>
                        <input type="date" name="last_date" id="inpLastDate"
                               class="w-full px-3 py-2 bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-xl text-slate-900 dark:text-white text-xs sm:text-sm focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-1.5">Fee & Service Charges</label>
                        <input type="text" name="fee_details" id="inpFee" placeholder="Govt Fee + CSC Fee"
                               class="w-full px-3 py-2 bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-xl text-slate-900 dark:text-white text-xs sm:text-sm focus:ring-2 focus:ring-blue-500">
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-1.5">Official Website / Portal Link</label>
                        <input type="url" name="official_url" id="inpUrl" placeholder="https://..."
                               class="w-full px-4 py-2.5 bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-xl text-slate-900 dark:text-white text-sm focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-1.5">Visibility Status</label>
                        <select name="status" id="inpStatus" class="w-full px-3 py-2.5 bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-xl text-slate-900 dark:text-white text-sm focus:ring-2 focus:ring-blue-500">
                            <option value="active">Active (Visible on Customer Page)</option>
                            <option value="inactive">Inactive (Hidden from Customers)</option>
                        </select>
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-1.5">Official Document Attachment (Optional)</label>
                    <input type="file" name="attachment_file" id="inpAttachment" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png"
                           class="w-full px-3 py-2 bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-xl text-slate-900 dark:text-white text-xs file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-blue-600 file:text-white hover:file:bg-blue-500">
                    <p id="attachmentInfo" class="text-xs text-blue-500 mt-1 hidden"></p>
                </div>

                <div class="flex items-center justify-end gap-3 pt-4 border-t border-slate-200 dark:border-slate-700">
                    <button type="button" onclick="closeFormModal()" class="px-5 py-2.5 rounded-xl bg-slate-200 hover:bg-slate-300 dark:bg-slate-700 dark:hover:bg-slate-600 text-slate-700 dark:text-slate-300 text-sm font-semibold transition">
                        Cancel
                    </button>
                    <button type="submit" class="px-6 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white text-sm font-bold shadow-md transition flex items-center gap-2">
                        <i data-lucide="check" class="w-4 h-4"></i>
                        <span>Save Form Notification</span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        lucide.createIcons();

        function filterOwnerForms() {
            const query = document.getElementById('ownerFormSearch').value.toLowerCase().trim();
            const rows = document.querySelectorAll('.form-row');
            rows.forEach(r => {
                const text = r.getAttribute('data-search') || '';
                r.style.display = text.includes(query) ? '' : 'none';
            });
        }

        function openFormModal() {
            document.getElementById('formAction').value = 'create';
            document.getElementById('formId').value = '';
            document.getElementById('modalHeading').querySelector('span').textContent = 'Post New Government / Vacancy Form';
            document.getElementById('formPostForm').reset();
            const attInfo = document.getElementById('attachmentInfo');
            if (attInfo) attInfo.classList.add('hidden');
            document.getElementById('modalFormContainer').classList.remove('hidden');
        }

        function closeFormModal() {
            document.getElementById('modalFormContainer').classList.add('hidden');
        }

        function editForm(form) {
            document.getElementById('formAction').value = 'update';
            document.getElementById('formId').value = form.id;
            document.getElementById('modalHeading').querySelector('span').textContent = 'Edit Form: ' + (form.title || '');
            
            document.getElementById('inpTitle').value = form.title || '';
            document.getElementById('inpCategory').value = form.category || 'Welfare Schemes';
            document.getElementById('inpDepartment').value = form.department || '';
            document.getElementById('inpSummary').value = form.summary || '';
            document.getElementById('inpEligibility').value = form.eligibility || '';
            document.getElementById('inpDocs').value = form.required_documents || '';
            document.getElementById('inpStartDate').value = form.start_date || '';
            document.getElementById('inpLastDate').value = form.last_date || '';
            document.getElementById('inpFee').value = form.fee_details || '';
            document.getElementById('inpUrl').value = form.official_url || '';
            document.getElementById('inpStatus').value = form.status || 'active';

            const attInfo = document.getElementById('attachmentInfo');
            if (attInfo) {
                if (form.attachment_file) {
                    attInfo.textContent = 'Current Attachment: ' + form.attachment_file + ' (choose file above to replace)';
                    attInfo.classList.remove('hidden');
                } else {
                    attInfo.classList.add('hidden');
                }
            }

            document.getElementById('modalFormContainer').classList.remove('hidden');
        }
    </script>
</body>
</html>
