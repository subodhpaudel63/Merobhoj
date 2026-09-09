<?php
declare(strict_types=1);
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/role_check.php';
$user = require_role($conn, ['admin', 'manager']);
$csrf = panel_csrf_token();
$serverMessage = '';
$serverMessageType = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['supplier_fallback'])) {
    if (!verify_panel_csrf((string)($_POST['csrf'] ?? ''))) {
        $serverMessage = 'Your session token expired. Refresh the page and try again.';
    } else {
        $name = trim((string)($_POST['name'] ?? ''));
        $phone = trim((string)($_POST['phone'] ?? ''));
        if ($name === '' || $phone === '') {
            $serverMessage = 'Supplier name and phone number are required.';
        } else {
            $contact = trim((string)($_POST['contact_person'] ?? ''));
            $email = trim((string)($_POST['email'] ?? ''));
            $address = trim((string)($_POST['address'] ?? ''));
            $id = (int)($_POST['id'] ?? 0);
            $isUpdate = $id > 0;
            $stmt = $conn->prepare($isUpdate
                ? 'UPDATE suppliers SET name=?, contact_person=?, phone=?, email=?, address=? WHERE id=?'
                : 'INSERT INTO suppliers (name, contact_person, phone, email, address) VALUES (?, ?, ?, ?, ?)');
            if ($stmt) {
                if ($isUpdate) {
                    $stmt->bind_param('sssssi', $name, $contact, $phone, $email, $address, $id);
                } else {
                    $stmt->bind_param('sssss', $name, $contact, $phone, $email, $address);
                }
                if ($stmt->execute()) {
                    $serverMessage = $isUpdate ? 'Supplier updated successfully.' : 'Supplier added successfully.';
                    $serverMessageType = 'success';
                } else {
                    $serverMessage = 'Unable to save supplier: ' . $stmt->error;
                }
                $stmt->close();
            } else {
                $serverMessage = 'Unable to prepare supplier save: ' . $conn->error;
            }
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Suppliers · Mero Bhoj</title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Sharp:opsz,wght,FILL,GRAD@48,400,0,0">
    <link rel="stylesheet" href="../assets/css/adminstyle.css">
    <link rel="stylesheet" href="../assets/css/admin2.css?v=<?= filemtime(__DIR__ . '/../assets/css/admin2.css') ?>">
    <link rel="stylesheet" href="../assets/css/panel.css">
</head>
<body class="admin-page" data-csrf="<?= htmlspecialchars($csrf, ENT_QUOTES) ?>">
<?php include_once __DIR__ . '/topbar.php'; ?>
<div class="container">
    <?php include_once __DIR__ . '/sidebar.php'; ?>
    <main class="admin-page-main po-module">
        <div class="po-header">
            <div>
                <span class="module-eyebrow">Procurement</span>
                <h1>Suppliers</h1>
                <p>Keep vendor contact details ready for purchase orders.</p>
            </div>
            <button type="button" class="qrm-btn qrm-btn-primary" data-action="supplier-new">
                <span class="material-symbols-sharp">add</span> Add supplier
            </button>
        </div>

        <?php if ($serverMessage !== ''): ?><div class="supplier-server-message <?= htmlspecialchars($serverMessageType, ENT_QUOTES) ?>"><?= htmlspecialchars($serverMessage, ENT_QUOTES) ?></div><?php endif; ?>
        <div class="po-toolbar supplier-toolbar">
            <div class="supplier-search">
                <span class="material-symbols-sharp">search</span>
                <input id="supplier-search" type="search" placeholder="Search by supplier, phone or email" aria-label="Search suppliers">
            </div>
            <button type="button" class="qrm-btn qrm-btn-secondary" data-action="supplier-refresh">Search</button>
        </div>

        <div class="panel-table-wrap">
            <table class="panel-table supplier-table">
                <thead>
                    <tr><th>Name</th><th>Contact</th><th>Phone</th><th>Email</th><th>Status</th><th>Actions</th></tr>
                </thead>
                <tbody id="supplier-body"></tbody>
            </table>
        </div>
    </main>
</div>

<div class="po-modal" id="supplier-modal" hidden>
    <form id="supplier-form" class="po-modal-box supplier-modal-box" method="post" action="suppliers.php" novalidate>
        <button type="button" class="supplier-modal-close" data-action="modal-close" aria-label="Close supplier form">
            <span class="material-symbols-sharp">close</span>
        </button>
        <div class="supplier-modal-heading">
            <span class="supplier-modal-icon material-symbols-sharp">store</span>
            <div>
                <span class="module-eyebrow">Vendor details</span>
                <h2 id="supplier-modal-title">Add supplier</h2>
                <p>Enter the supplier information used on purchase orders.</p>
            </div>
        </div>
        <p class="supplier-required-note"><b>*</b> Required fields</p>
        <input type="hidden" name="id">
        <input type="hidden" name="supplier_fallback" value="1">
        <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES) ?>">
        <div class="supplier-form-grid">
            <label class="supplier-field supplier-field-wide">
                <span>Supplier name <b aria-hidden="true">*</b></span>
                <input name="name" required maxlength="255" autocomplete="organization" placeholder="e.g. Kalimati Fresh Foods">
                <small>Use the official business or vendor name.</small>
            </label>
            <label class="supplier-field">
                <span>Contact person</span>
                <input name="contact_person" maxlength="100" autocomplete="name" placeholder="e.g. Ram Thapa">
            </label>
            <label class="supplier-field">
                <span>Phone number <b aria-hidden="true">*</b></span>
                <input name="phone" required maxlength="20" inputmode="tel" autocomplete="tel" placeholder="e.g. 9841234567">
            </label>
            <label class="supplier-field supplier-field-wide">
                <span>Email address</span>
                <input name="email" type="email" maxlength="100" autocomplete="email" placeholder="vendor@example.com">
            </label>
            <label class="supplier-field supplier-field-wide">
                <span>Address</span>
                <textarea name="address" maxlength="2000" rows="3" autocomplete="street-address" placeholder="Street, area, city"></textarea>
            </label>
        </div>
        <div class="supplier-form-message" id="supplier-form-message" role="alert" hidden></div>
        <div class="po-actions supplier-form-actions">
            <button type="button" class="qrm-btn qrm-btn-secondary" data-action="modal-close">Cancel</button>
            <button type="submit" class="qrm-btn qrm-btn-primary" id="supplier-save">
                <span class="material-symbols-sharp">save</span> Save supplier
            </button>
        </div>
    </form>
</div>
<script src="../assets/js/adminscript.js"></script>
<script src="../assets/js/admin2.js?v=<?= filemtime(__DIR__ . '/../assets/js/admin2.js') ?>"></script>
</body>
</html>
