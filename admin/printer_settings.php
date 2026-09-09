<?php
/**
 * Admin — Printer & Receipt Setup
 * Mero Bhoj Restaurant Management System
 */
declare(strict_types=1);
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/role_check.php';
$user = require_role($conn, ['admin', 'manager']);
$csrf = panel_csrf_token();

function esc(mixed $val): string {
    return htmlspecialchars((string)($val ?? ''), ENT_QUOTES, 'UTF-8');
}

$flash_success = '';
$flash_error = '';

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && array_key_exists('restaurant_name', $_POST)) {
    $restaurant_name = trim($_POST['restaurant_name'] ?? '');
    $address         = trim($_POST['address'] ?? '');
    $phone           = trim($_POST['phone'] ?? '');
    $pan_vat         = trim($_POST['pan_vat'] ?? '');
    $footer_text     = trim($_POST['footer_text'] ?? '');

    if (!verify_panel_csrf((string)($_POST['csrf'] ?? ''))) {
        $flash_error = 'Your session token expired. Refresh the page and try again.';
    } elseif ($restaurant_name === '') {
        $flash_error = 'Restaurant Name is required.';
    } else {
        $stmt = $conn->prepare("INSERT INTO `receipt_settings` (`id`, `restaurant_name`, `address`, `phone`, `pan_vat`, `footer_text`) VALUES (1, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE `restaurant_name` = VALUES(`restaurant_name`), `address` = VALUES(`address`), `phone` = VALUES(`phone`), `pan_vat` = VALUES(`pan_vat`), `footer_text` = VALUES(`footer_text`)");
        if ($stmt) {
            $stmt->bind_param("sssss", $restaurant_name, $address, $phone, $pan_vat, $footer_text);
            if ($stmt->execute()) {
                $flash_success = 'Receipt header & footer settings updated successfully!';
            } else {
                $flash_error = 'Failed to update settings: ' . $stmt->error;
                error_log('Printer settings save failed: ' . $stmt->error);
            }
            $stmt->close();
        } else {
            $flash_error = 'Database statement preparation error: ' . $conn->error;
            error_log('Printer settings prepare failed: ' . $conn->error);
        }
    }
}

// Fetch Current Settings
$res = $conn->query("SELECT * FROM `receipt_settings` WHERE `id` = 1 LIMIT 1");
$settings = ($res && $res->num_rows > 0) ? $res->fetch_assoc() : [
    'restaurant_name' => 'Mero Bhoj Restaurant',
    'address'         => 'Lakeside-6, Pokhara, Nepal',
    'phone'           => '+977 61-460000',
    'pan_vat'         => '600987123',
    'footer_text'     => 'Thank you! Visit again.'
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Printer & Receipt Settings - Mero Bhoj</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Sharp:opsz,wght,FILL,GRAD@48,400,0,0" />
    <link rel="stylesheet" href="../assets/css/adminstyle.css?v=<?= filemtime(__DIR__ . '/../assets/css/adminstyle.css') ?>">
    <link rel="stylesheet" href="../assets/css/admin2.css?v=<?= filemtime(__DIR__ . '/../assets/css/admin2.css') ?>">
</head>
<body class="admin-page">
    <?php include_once __DIR__ . '/topbar.php'; ?>

    <div class="container flex-container-gap">
        <?php include_once __DIR__ . '/sidebar.php'; ?>

        <main class="main-content-padded">
            <div class="header-action-row">
                <div>
                    <h1 class="settings-header-title">Printer & Receipt Setup</h1>
                    <p class="settings-header-subtitle">Configure thermal receipt headers, footers, and test 80mm printing.</p>
                </div>
                <button type="button" class="btn-test-print" onclick="openPrintModal()">
                    <span class="material-symbols-sharp">print</span>
                    Test Print 80mm Receipt
                </button>
            </div>

            <?php if ($flash_success !== ''): ?>
                <div class="alert alert-success">
                    <span class="material-symbols-sharp">check_circle</span>
                    <?= esc($flash_success) ?>
                </div>
            <?php endif; ?>

            <?php if ($flash_error !== ''): ?>
                <div class="alert alert-danger">
                    <span class="material-symbols-sharp">error</span>
                    <?= esc($flash_error) ?>
                </div>
            <?php endif; ?>

            <div class="settings-container">
                <!-- Header & Footer Settings Form -->
                <div class="settings-card">
                    <h3>
                        <span class="material-symbols-sharp">receipt_long</span>
                        Receipt Header & Footer Info
                    </h3>
                    <form method="POST" action="">
                        <input type="hidden" name="csrf" value="<?= esc($csrf) ?>">
                        <div class="form-group">
                            <label for="restaurant_name">Restaurant Name *</label>
                            <input type="text" id="restaurant_name" name="restaurant_name" class="form-control" value="<?= esc($settings['restaurant_name']) ?>" required>
                        </div>

                        <div class="form-group">
                            <label for="address">Address</label>
                            <input type="text" id="address" name="address" class="form-control" value="<?= esc($settings['address']) ?>">
                        </div>

                        <div class="form-group">
                            <label for="phone">Phone Number(s)</label>
                            <input type="text" id="phone" name="phone" class="form-control" value="<?= esc($settings['phone']) ?>">
                        </div>

                        <div class="form-group">
                            <label for="pan_vat">PAN / VAT Number</label>
                            <input type="text" id="pan_vat" name="pan_vat" class="form-control" value="<?= esc($settings['pan_vat']) ?>">
                        </div>

                        <div class="form-group">
                            <label for="footer_text">Footer Note</label>
                            <textarea id="footer_text" name="footer_text" class="form-control"><?= esc($settings['footer_text']) ?></textarea>
                        </div>

                        <button type="submit" name="save_receipt_settings" class="btn-submit">
                            <span class="material-symbols-sharp">save</span>
                            Save Receipt Settings
                        </button>
                    </form>
                </div>

                <!-- Receipt Preview Panel -->
                <div class="settings-card">
                    <h3>
                        <span class="material-symbols-sharp">preview</span>
                        80mm Thermal Receipt Layout Preview
                    </h3>
                    <p class="text-muted-sm">
                        This live preview displays how your customer receipt will look on standard 80mm thermal paper.
                    </p>

                    <div class="receipt-preview-box">
                        <div class="receipt-header">
                            <h2><?= esc($settings['restaurant_name']) ?></h2>
                            <div><?= esc($settings['address']) ?></div>
                            <div>Tel: <?= esc($settings['phone']) ?></div>
                            <div>VAT/PAN: <?= esc($settings['pan_vat']) ?></div>
                        </div>

                        <div class="receipt-divider"></div>

                        <div class="receipt-row"><span>Date: <?= date('Y-m-d H:i') ?></span></div>
                        <div class="receipt-row"><span>Order: #ORD-TEST-101</span></div>
                        <div class="receipt-row"><span>Table: Table 05</span></div>

                        <div class="receipt-divider"></div>

                        <table class="receipt-table">
                            <thead>
                                <tr>
                                    <th class="col-width-55">Item</th>
                                    <th class="col-width-15 text-center">Qty</th>
                                    <th class="col-width-30 text-right">Amt</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>Chicken Momo (Steam)</td>
                                    <td class="text-center">2</td>
                                    <td class="text-right">Rs. 400.00</td>
                                </tr>
                                <tr>
                                    <td>Mutton Sekuwa</td>
                                    <td class="text-center">1</td>
                                    <td class="text-right">Rs. 550.00</td>
                                </tr>
                                <tr>
                                    <td>Fresh Lemon Soda</td>
                                    <td class="text-center">2</td>
                                    <td class="text-right">Rs. 180.00</td>
                                </tr>
                            </tbody>
                        </table>

                        <div class="receipt-divider"></div>

                        <div class="receipt-totals">
                            <div class="receipt-row"><span>Subtotal:</span><span>Rs. 1,130.00</span></div>
                            <div class="receipt-row"><span>Discount (10%):</span><span>- Rs. 113.00</span></div>
                            <div class="receipt-row"><span>VAT (13%):</span><span>Rs. 132.21</span></div>
                            <div class="receipt-divider"></div>
                            <div class="receipt-row bold"><span>GRAND TOTAL:</span><span>Rs. 1,149.21</span></div>
                            <div class="receipt-row"><span>Paid via Cash:</span><span>Rs. 1,200.00</span></div>
                            <div class="receipt-row"><span>Change:</span><span>Rs. 50.79</span></div>
                        </div>

                        <div class="receipt-divider"></div>
                        <div class="receipt-footer">
                            <?= esc($settings['footer_text']) ?>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Print Modal -->
    <div class="modal-overlay" id="printModal">
        <div class="modal-card">
            <h3 class="modal-title-row">
                <span class="material-symbols-sharp">print</span>
                Test Thermal Print Preview
            </h3>
            <p class="modal-desc-text">
                Click "Print Now" to send this receipt to your connected 80mm thermal receipt printer.
            </p>

            <div id="printable-receipt-area" class="receipt-preview-box">
                <div class="receipt-header">
                    <h2><?= esc($settings['restaurant_name']) ?></h2>
                    <div><?= esc($settings['address']) ?></div>
                    <div>Tel: <?= esc($settings['phone']) ?></div>
                    <div>VAT/PAN: <?= esc($settings['pan_vat']) ?></div>
                </div>

                <div class="receipt-divider"></div>

                <div class="receipt-row"><span>Date: <?= date('Y-m-d H:i') ?></span></div>
                <div class="receipt-row"><span>Order: #ORD-TEST-101</span></div>
                <div class="receipt-row"><span>Table: Table 05</span></div>

                <div class="receipt-divider"></div>

                <table class="receipt-table">
                    <thead>
                        <tr>
                            <th class="col-width-55">Item</th>
                            <th class="col-width-15 text-center">Qty</th>
                            <th class="col-width-30 text-right">Amt</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>Chicken Momo (Steam)</td>
                            <td class="text-center">2</td>
                            <td class="text-right">Rs. 400.00</td>
                        </tr>
                        <tr>
                            <td>Mutton Sekuwa</td>
                            <td class="text-center">1</td>
                            <td class="text-right">Rs. 550.00</td>
                        </tr>
                        <tr>
                            <td>Fresh Lemon Soda</td>
                            <td class="text-center">2</td>
                            <td class="text-right">Rs. 180.00</td>
                        </tr>
                    </tbody>
                </table>

                <div class="receipt-divider"></div>

                <div class="receipt-totals">
                    <div class="receipt-row"><span>Subtotal:</span><span>Rs. 1,130.00</span></div>
                    <div class="receipt-row"><span>Discount (10%):</span><span>- Rs. 113.00</span></div>
                    <div class="receipt-row"><span>VAT (13%):</span><span>Rs. 132.21</span></div>
                    <div class="receipt-divider"></div>
                    <div class="receipt-row bold"><span>GRAND TOTAL:</span><span>Rs. 1,149.21</span></div>
                    <div class="receipt-row"><span>Paid via Cash:</span><span>Rs. 1,200.00</span></div>
                    <div class="receipt-row"><span>Change:</span><span>Rs. 50.79</span></div>
                </div>

                <div class="receipt-divider"></div>
                <div class="receipt-footer">
                    <?= esc($settings['footer_text']) ?>
                </div>
            </div>

            <div class="modal-actions">
                <button type="button" class="btn-close" onclick="closePrintModal()">Close</button>
                <button type="button" class="btn-test-print" onclick="triggerThermalPrint()">
                    <span class="material-symbols-sharp">print</span>
                    Print Now
                </button>
            </div>
        </div>
    </div>

    <script src="../assets/js/adminscript.js?v=<?= filemtime(__DIR__ . '/../assets/js/adminscript.js') ?>"></script>
    <script src="../assets/js/admin2.js?v=<?= filemtime(__DIR__ . '/../assets/js/admin2.js') ?>"></script>
</body>
</html>
