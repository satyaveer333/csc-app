<?php
/**
 * CSC Owner Portal - Government & Vacancy Forms Manager
 * Add, Edit, Delete, Toggle Active/Inactive forms
 */

define('CSC_APP', true);
require_once dirname(__DIR__) . '/includes/config.php';
require_once dirname(__DIR__) . '/includes/db.php';
require_once dirname(__DIR__) . '/includes/helpers.php';
require_once dirname(__DIR__) . '/includes/auth.php';

requireOwnerLogin();
ensureDirectories();
$pdo = getDbConnection();

$action = $_POST['action'] ?? $_GET['action'] ?? 'list';
$editId = (int)($_GET['edit_id'] ?? 0);

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // 1. ADD NEW FORM
    if ($action === 'create') {
        $title = trim($_POST['title'] ?? '');
        $category = trim($_POST['category'] ?? '');
        $department = trim($_POST['department'] ?? '');
        $summary = trim($_POST['summary'] ?? '');
        $eligibility = trim($_POST['eligibility'] ?? '');
        $requiredDocs = trim($_POST['required_documents'] ?? '');
        $feeDetails = trim($_POST['fee_details'] ?? '');
        $startDate = !empty($_POST['start_date']) ? $_POST['start_date'] : null;
        $lastDate = !empty($_POST['last_date']) ? $_POST['last_date'] : null;
        $officialUrl = trim($_POST['official_url'] ?? '');
        $status = $_POST['status'] ?? 'active';

        if (!empty($title) && !empty($category)) {
            $stmt = $pdo->prepare("
                INSERT INTO forms (
                    title, category, department, summary, eligibility, 
                    required_documents, fee_details, start_date, last_date, 
                    official_url, status, created_at, updated_at
                ) VALUES (
                    :title, :category, :department, :summary, :eligibility, 
                    :required_documents, :fee_details, :start_date, :last_date, 
                    :official_url, :status, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP
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
                ':status' => $status
            ]);
            setFlash('success', 'New form notification added successfully!');
        } else {
            setFlash('danger', 'Form Title and Category are required.');
        }
        header('Location: forms.php');
        exit;
    }

    // 2. UPDATE FORM
    if ($action === 'update') {
        $id = (int)$_POST['id'];
        $title = trim($_POST['title'] ?? '');
        $category = trim($_POST['category'] ?? '');
        $department = trim($_POST['department'] ?? '');
        $summary = trim($_POST['summary'] ?? '');
        $eligibility = trim($_POST['eligibility'] ?? '');
        $requiredDocs = trim($_POST['required_documents'] ?? '');
        $feeDetails = trim($_POST['fee_details'] ?? '');
        $startDate = !empty($_POST['start_date']) ? $_POST['start_date'] : null;
        $lastDate = !empty($_POST['last_date']) ? $_POST['last_date'] : null;
        $officialUrl = trim($_POST['official_url'] ?? '');
        $status = $_POST['status'] ?? 'active';

        if ($id > 0 && !empty($title)) {
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
            setFlash('success', 'Form details updated successfully!');
        }
        header('Location: forms.php');
        exit;
    }

    // 3. DELETE FORM
    if ($action === 'delete') {
        $id = (int)$_POST['id'];
        if ($id > 0) {
            $stmt = $pdo->prepare("DELETE FROM forms WHERE id = :id");
            $stmt->execute([':id' => $id]);
            setFlash('success', 'Form deleted successfully.');
        }
        header('Location: forms.php');
        exit;
    }

    // 4. TOGGLE STATUS
    if ($action === 'toggle_status') {
        $id = (int)$_POST['id'];
        $currentStatus = $_POST['current_status'] ?? 'active';
        $newStatus = ($currentStatus === 'active') ? 'inactive' : 'active';
        
        $stmt = $pdo->prepare("UPDATE forms SET status = :status WHERE id = :id");
        $stmt->execute([':status' => $newStatus, ':id' => $id]);
        setFlash('success', "Form status changed to {$newStatus}.");
        header('Location: forms.php');
        exit;
    }
}

// Fetch form for editing if edit_id is given
$editForm = null;
if ($editId > 0) {
    $stmt = $pdo->prepare("SELECT * FROM forms WHERE id = :id");
    $stmt->execute([':id' => $editId]);
    $editForm = $stmt->fetch();
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
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
</head>
<body class="min-h-screen bg-slate-900 text-slate-100 flex flex-col antialiased">

    <?php include __DIR__ . '/nav.php'; ?>

    <main class="flex-1 max-w-7xl w-full mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-6">
        
        <!-- Header -->
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-4 border-b border-slate-800">
            <div>
                <h1 class="text-2xl sm:text-3xl font-bold text-white tracking-tight">Government & Vacancy Forms</h1>
                <p class="text-slate-400 text-sm mt-1">Add, update, or remove forms visible to citizens on your customer page.</p>
            </div>
            <button onclick="openFormModal()" class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl font-semibold bg-emerald-600 hover:bg-emerald-500 text-white shadow-sm transition">
                <i data-lucide="plus-circle" class="w-4 h-4"></i>
                <span>Add New Form</span>
            </button>
        </div>

        <?php if ($flash): ?>
            <div class="p-4 rounded-xl <?= $flash['type'] === 'success' ? 'bg-emerald-950/70 border border-emerald-800 text-emerald-300' : 'bg-red-950/70 border border-red-800 text-red-300' ?> text-sm flex items-center gap-2">
                <i data-lucide="<?= $flash['type'] === 'success' ? 'check-circle' : 'alert-circle' ?>" class="w-5 h-5 shrink-0"></i>
                <span><?= e($flash['message']) ?></span>
            </div>
        <?php endif; ?>

        <!-- Forms Table -->
        <div class="bg-slate-800 rounded-2xl border border-slate-700 overflow-hidden shadow-lg">
            <div class="p-4 bg-slate-800/80 border-b border-slate-700 flex flex-col sm:flex-row justify-between items-center gap-3">
                <div class="relative w-full sm:w-80">
                    <i data-lucide="search" class="w-4 h-4 text-slate-400 absolute left-3.5 top-3"></i>
                    <input type="text" id="ownerFormSearch" onkeyup="filterOwnerForms()" placeholder="Search posted forms..."
                           class="w-full pl-9 pr-4 py-2 bg-slate-900 border border-slate-700 rounded-xl text-xs text-white focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
                <div class="text-xs text-slate-400">
                    Total Forms: <strong class="text-white"><?= count($allForms) ?></strong>
                </div>
            </div>

            <?php if (empty($allForms)): ?>
                <div class="p-12 text-center text-slate-400">
                    <i data-lucide="inbox" class="w-12 h-12 mx-auto mb-3 opacity-50"></i>
                    <h3 class="text-base font-bold text-slate-300">No Forms Found</h3>
                    <p class="text-xs text-slate-500 mt-1">Click "Add New Form" above to post your first form notification.</p>
                </div>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm" id="ownerFormsTable">
                        <thead class="bg-slate-900/60 text-xs uppercase text-slate-400 border-b border-slate-700">
                            <tr>
                                <th class="px-5 py-3.5">Form Details</th>
                                <th class="px-4 py-3.5">Category</th>
                                <th class="px-4 py-3.5">Deadline</th>
                                <th class="px-4 py-3.5">Fee</th>
                                <th class="px-4 py-3.5 text-center">Status</th>
                                <th class="px-5 py-3.5 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-700/60">
                            <?php foreach ($allForms as $f): ?>
                                <tr class="hover:bg-slate-700/40 transition form-row" data-search="<?= e(strtolower($f['title'] . ' ' . $f['category'] . ' ' . $f['department'])) ?>">
                                    <td class="px-5 py-4">
                                        <div class="font-bold text-white text-base"><?= e($f['title']) ?></div>
                                        <?php if (!empty($f['department'])): ?>
                                            <div class="text-xs text-slate-400 mt-0.5"><?= e($f['department']) ?></div>
                                        <?php endif; ?>
                                        <?php if (!empty($f['official_url'])): ?>
                                            <a href="<?= e($f['official_url']) ?>" target="_blank" class="inline-flex items-center gap-1 text-xs text-blue-400 hover:text-blue-300 mt-1">
                                                <span>Official Link</span>
                                                <i data-lucide="external-link" class="w-3 h-3"></i>
                                            </a>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-4 py-4 text-xs">
                                        <span class="px-2.5 py-1 rounded-md bg-blue-950/70 border border-blue-800 text-blue-300 font-medium">
                                            <?= e($f['category']) ?>
                                        </span>
                                    </td>
                                    <td class="px-4 py-4 text-xs text-slate-300 whitespace-nowrap">
                                        <?= formatDate($f['last_date']) ?>
                                    </td>
                                    <td class="px-4 py-4 text-xs text-slate-300">
                                        <?= e($f['fee_details'] ?: 'N/A') ?>
                                    </td>
                                    <td class="px-4 py-4 text-center whitespace-nowrap">
                                        <form method="POST" class="inline-block">
                                            <input type="hidden" name="action" value="toggle_status">
                                            <input type="hidden" name="id" value="<?= $f['id'] ?>">
                                            <input type="hidden" name="current_status" value="<?= $f['status'] ?>">
                                            <button type="submit" title="Click to toggle status" class="px-2.5 py-1 rounded-full text-xs font-semibold border transition <?= $f['status'] === 'active' ? 'bg-emerald-950/60 text-emerald-300 border-emerald-800 hover:bg-emerald-900' : 'bg-slate-700 text-slate-400 border-slate-600 hover:bg-slate-600' ?>">
                                                <?= ucfirst($f['status']) ?>
                                            </button>
                                        </form>
                                    </td>
                                    <td class="px-5 py-4 text-right whitespace-nowrap">
                                        <div class="flex items-center justify-end gap-2">
                                            <!-- Edit -->
                                            <button onclick="editForm(<?= htmlspecialchars(json_encode($f), ENT_QUOTES, 'UTF-8') ?>)" class="p-2 rounded-lg bg-slate-700 hover:bg-slate-600 text-slate-200 transition" title="Edit Form">
                                                <i data-lucide="pencil" class="w-4 h-4"></i>
                                            </button>
                                            <!-- Delete -->
                                            <form method="POST" onsubmit="return confirm('Are you sure you want to delete this form?');" class="inline-block">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="id" value="<?= $f['id'] ?>">
                                                <button type="submit" class="p-2 rounded-lg bg-red-950/60 hover:bg-red-800 text-red-300 transition" title="Delete Form">
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

    <!-- ================= ADD / EDIT FORM MODAL ================= -->
    <div id="modalFormContainer" class="fixed inset-0 bg-black/70 backdrop-blur-sm z-50 flex items-center justify-center p-4 <?= $editForm ? '' : 'hidden' ?>">
        <div class="bg-slate-800 rounded-3xl max-w-2xl w-full max-h-[90vh] overflow-y-auto shadow-2xl border border-slate-700">
            <div class="sticky top-0 bg-slate-800 border-b border-slate-700 px-6 py-4 flex items-center justify-between z-10">
                <h3 id="modalHeading" class="text-lg font-bold text-white flex items-center gap-2">
                    <i data-lucide="file-plus" class="w-5 h-5 text-emerald-400"></i>
                    <span><?= $editForm ? 'Edit Form Details' : 'Post New Government / Vacancy Form' ?></span>
                </h3>
                <button type="button" onclick="closeFormModal()" class="w-8 h-8 rounded-full bg-slate-700 hover:bg-slate-600 text-slate-300 flex items-center justify-center transition">
                    <i data-lucide="x" class="w-5 h-5"></i>
                </button>
            </div>

            <form method="POST" id="formPostForm" class="p-6 space-y-4">
                <input type="hidden" name="action" id="formAction" value="<?= $editForm ? 'update' : 'create' ?>">
                <input type="hidden" name="id" id="formId" value="<?= $editForm['id'] ?? '' ?>">

                <!-- Title -->
                <div>
                    <label class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-1.5">
                        Form / Scheme / Job Title <span class="text-red-400">*</span>
                    </label>
                    <input type="text" name="title" id="inpTitle" required value="<?= e($editForm['title'] ?? '') ?>" placeholder="e.g. PM Kisan eKYC or SSC CGL 2026 Recruitment"
                           class="w-full px-4 py-2.5 bg-slate-900 border border-slate-700 rounded-xl text-white text-sm focus:ring-2 focus:ring-blue-500">
                </div>

                <!-- Category & Department -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-1.5">Category <span class="text-red-400">*</span></label>
                        <select name="category" id="inpCategory" required class="w-full px-3 py-2.5 bg-slate-900 border border-slate-700 rounded-xl text-white text-sm focus:ring-2 focus:ring-blue-500">
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?= e($cat) ?>" <?= (isset($editForm['category']) && $editForm['category'] === $cat) ? 'selected' : '' ?>><?= e($cat) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-1.5">Department / Ministry</label>
                        <input type="text" name="department" id="inpDepartment" value="<?= e($editForm['department'] ?? '') ?>" placeholder="e.g. Ministry of Agriculture"
                               class="w-full px-4 py-2.5 bg-slate-900 border border-slate-700 rounded-xl text-white text-sm focus:ring-2 focus:ring-blue-500">
                    </div>
                </div>

                <!-- Summary -->
                <div>
                    <label class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-1.5">Summary / Short Description</label>
                    <textarea name="summary" id="inpSummary" rows="2" placeholder="Brief explanation of the scheme or vacancy benefits..."
                              class="w-full px-4 py-2.5 bg-slate-900 border border-slate-700 rounded-xl text-white text-sm focus:ring-2 focus:ring-blue-500"><?= e($editForm['summary'] ?? '') ?></textarea>
                </div>

                <!-- Eligibility -->
                <div>
                    <label class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-1.5">Eligibility Criteria</label>
                    <textarea name="eligibility" id="inpEligibility" rows="2" placeholder="Age limit, qualification, family income, etc."
                              class="w-full px-4 py-2.5 bg-slate-900 border border-slate-700 rounded-xl text-white text-sm focus:ring-2 focus:ring-blue-500"><?= e($editForm['eligibility'] ?? '') ?></textarea>
                </div>

                <!-- Required Documents Checklist -->
                <div>
                    <label class="block text-xs font-semibold text-emerald-400 uppercase tracking-wider mb-1.5">
                        Required Documents Checklist (What customer must bring)
                    </label>
                    <textarea name="required_documents" id="inpDocs" rows="3" placeholder="1. Aadhaar Card&#10;2. Ration Card&#10;3. Bank Passbook&#10;4. 2 Passport Photos"
                              class="w-full px-4 py-2.5 bg-slate-900 border border-slate-700 rounded-xl text-white text-sm focus:ring-2 focus:ring-blue-500 font-mono"><?= e($editForm['required_documents'] ?? '') ?></textarea>
                    <p class="text-xs text-slate-500 mt-1">Each line is displayed as a checklist item to help citizens prepare before visiting.</p>
                </div>

                <!-- Dates & Fees -->
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-1.5">Start Date</label>
                        <input type="date" name="start_date" id="inpStartDate" value="<?= e($editForm['start_date'] ?? '') ?>"
                               class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-xl text-white text-sm focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-1.5">Last Date (Deadline)</label>
                        <input type="date" name="last_date" id="inpLastDate" value="<?= e($editForm['last_date'] ?? '') ?>"
                               class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-xl text-white text-sm focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-1.5">Fee & Service Charges</label>
                        <input type="text" name="fee_details" id="inpFee" value="<?= e($editForm['fee_details'] ?? '') ?>" placeholder="Govt Fee + CSC Fee"
                               class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-xl text-white text-sm focus:ring-2 focus:ring-blue-500">
                    </div>
                </div>

                <!-- Official URL & Status -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-1.5">Official Website / Portal Link</label>
                        <input type="url" name="official_url" id="inpUrl" value="<?= e($editForm['official_url'] ?? '') ?>" placeholder="https://..."
                               class="w-full px-4 py-2.5 bg-slate-900 border border-slate-700 rounded-xl text-white text-sm focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-1.5">Visibility Status</label>
                        <select name="status" id="inpStatus" class="w-full px-3 py-2.5 bg-slate-900 border border-slate-700 rounded-xl text-white text-sm focus:ring-2 focus:ring-blue-500">
                            <option value="active" <?= (!isset($editForm['status']) || $editForm['status'] === 'active') ? 'selected' : '' ?>>Active (Visible on Customer Page)</option>
                            <option value="inactive" <?= (isset($editForm['status']) && $editForm['status'] === 'inactive') ? 'selected' : '' ?>>Inactive (Hidden from Customers)</option>
                        </select>
                    </div>
                </div>

                <div class="flex items-center justify-end gap-3 pt-4 border-t border-slate-700">
                    <button type="button" onclick="closeFormModal()" class="px-5 py-2.5 rounded-xl bg-slate-700 hover:bg-slate-600 text-slate-300 text-sm font-semibold transition">
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
            document.getElementById('modalFormContainer').classList.remove('hidden');
        }

        function closeFormModal() {
            document.getElementById('modalFormContainer').classList.add('hidden');
        }

        function editForm(form) {
            document.getElementById('formAction').value = 'update';
            document.getElementById('formId').value = form.id;
            document.getElementById('modalHeading').querySelector('span').textContent = 'Edit Form: ' + form.title;
            
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

            document.getElementById('modalFormContainer').classList.remove('hidden');
        }
    </script>
</body>
</html>
