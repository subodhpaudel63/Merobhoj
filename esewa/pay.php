<?php
// filepath: c:\xampp\htdocs\Merobhoj\esewa\pay.php

session_start();

require_once __DIR__ . '/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Invalid request method.');
}

$orderId     = filter_input(INPUT_POST, 'order_id',     FILTER_VALIDATE_INT);
$orderNumber = trim($_POST['order_number'] ?? '');

if ((!$orderId || $orderId <= 0) && empty($orderNumber)) {
    exit('Invalid order information.');
}

// Look up the total by order_number (sums all items in the cart order)
if (!empty($orderNumber)) {
    $totalAmount = getOrderAmountByNumber($orderNumber);
} else {
    $totalAmount = getOrderAmountFromDatabase($orderId);
}

if (
    $totalAmount === false ||
    !is_numeric($totalAmount) ||
    (float) $totalAmount <= 0
) {
    exit('Unable to calculate payment amount.');
}

$amount = number_format((float) $totalAmount, 2, '.', '');

$taxAmount       = '0.00';
$serviceCharge   = '0.00';
$deliveryCharge  = '0.00';

$total = number_format(
    (float) $amount +
    (float) $taxAmount +
    (float) $serviceCharge +
    (float) $deliveryCharge,
    2,
    '.',
    ''
);

$transactionUuid = generateTransactionUuid();

$signature = generateEsewaSignature(
    $total,
    $transactionUuid,
    ESEWA_PRODUCT_CODE
);

$_SESSION['esewa_payment'] = [
    'order_id'        => $orderId,
    'order_number'    => $orderNumber,
    'transaction_uuid'=> $transactionUuid,
    'total_amount'    => $total
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Redirecting to eSewa</title>
    <style>
        body {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            margin: 0;
            background: #f5f5f5;
            font-family: Arial, sans-serif;
        }
        .redirect-box {
            text-align: center;
            background: #fff;
            padding: 40px;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,.08);
        }
        .esewa-logo { width: 120px; margin-bottom: 16px; }
        .spinner {
            width: 36px; height: 36px;
            border: 4px solid #eee;
            border-top-color: #60bb46;
            border-radius: 50%;
            animation: spin .7s linear infinite;
            margin: 16px auto 0;
        }
        @keyframes spin { to { transform: rotate(360deg); } }
    </style>
</head>
<body>
    <div class="redirect-box">
        <img src="<?= e(SITE_URL) ?>/assets/img/esewa/esewalogo.png" alt="eSewa" class="esewa-logo">
        <p>Redirecting to eSewa for secure payment...</p>
        <div class="spinner"></div>
    </div>

    <form
        id="esewaForm"
        action="<?= e(ESEWA_PAYMENT_URL) ?>"
        method="POST"
    >
        <input type="hidden" name="amount"                   value="<?= e($amount) ?>">
        <input type="hidden" name="tax_amount"               value="<?= e($taxAmount) ?>">
        <input type="hidden" name="total_amount"             value="<?= e($total) ?>">
        <input type="hidden" name="transaction_uuid"         value="<?= e($transactionUuid) ?>">
        <input type="hidden" name="product_code"             value="<?= e(ESEWA_PRODUCT_CODE) ?>">
        <input type="hidden" name="product_service_charge"   value="<?= e($serviceCharge) ?>">
        <input type="hidden" name="product_delivery_charge"  value="<?= e($deliveryCharge) ?>">
        <input type="hidden" name="success_url"              value="<?= e(ESEWA_SUCCESS_URL) ?>">
        <input type="hidden" name="failure_url"              value="<?= e(ESEWA_FAILURE_URL) ?>">
        <input type="hidden" name="signed_field_names"       value="total_amount,transaction_uuid,product_code">
        <input type="hidden" name="signature"                value="<?= e($signature) ?>">

        <noscript>
            <button type="submit">Continue to eSewa</button>
        </noscript>
    </form>

    <script>
        document.getElementById('esewaForm').submit();
    </script>
</body>
</html>