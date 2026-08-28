<?php

session_start();

require_once __DIR__ . '/functions.php';


/*
|--------------------------------------------------------------------------
| Get encoded response
|--------------------------------------------------------------------------
*/

$encodedData = $_GET['data'] ?? '';

if (empty($encodedData)) {
    exit('Invalid eSewa response.');
}


/*
|--------------------------------------------------------------------------
| Decode Base64 response
|--------------------------------------------------------------------------
*/

$decodedData = base64_decode(
    $encodedData,
    true
);

if ($decodedData === false) {
    exit('Unable to decode eSewa response.');
}


/*
|--------------------------------------------------------------------------
| Convert JSON to array
|--------------------------------------------------------------------------
*/

$response = json_decode(
    $decodedData,
    true
);

if (!is_array($response)) {
    exit('Invalid eSewa response data.');
}


/*
|--------------------------------------------------------------------------
| Verify response signature
|--------------------------------------------------------------------------
*/

if (!verifyEsewaResponseSignature($response)) {

    http_response_code(400);

    exit('Payment verification failed.');
}


/*
|--------------------------------------------------------------------------
| Read payment information
|--------------------------------------------------------------------------
*/

$status = $response['status'] ?? '';

$transactionUuid =
    $response['transaction_uuid'] ?? '';

$productCode =
    $response['product_code'] ?? '';

$totalAmount =
    $response['total_amount'] ?? '';

$transactionCode =
    $response['transaction_code'] ?? '';


/*
|--------------------------------------------------------------------------
| Validate product code
|--------------------------------------------------------------------------
*/

if ($productCode !== ESEWA_PRODUCT_CODE) {
    exit('Invalid product code.');
}


/*
|--------------------------------------------------------------------------
| Validate transaction against session
|--------------------------------------------------------------------------
*/

$paymentSession =
    $_SESSION['esewa_payment'] ?? null;

if (!$paymentSession) {
    exit('Payment session not found.');
}

if (
    $paymentSession['transaction_uuid']
    !== $transactionUuid
) {
    exit('Transaction mismatch.');
}


/*
|--------------------------------------------------------------------------
| Validate amount
|--------------------------------------------------------------------------
*/

if (
    number_format(
        (float)$paymentSession['total_amount'],
        2,
        '.',
        ''
    )
    !==
    number_format(
        (float)$totalAmount,
        2,
        '.',
        ''
    )
) {
    exit('Payment amount mismatch.');
}


/*
|--------------------------------------------------------------------------
| Check eSewa transaction status
|--------------------------------------------------------------------------
*/

$verification = checkEsewaTransactionStatus(
    $transactionUuid,
    $totalAmount
);

if (!$verification) {
    exit('Unable to verify payment with eSewa.');
}


/*
|--------------------------------------------------------------------------
| Only COMPLETE should be considered paid
|--------------------------------------------------------------------------
*/

if (
    ($verification['status'] ?? '')
    !== 'COMPLETE'
) {

    exit(
        'Payment has not been completed. Status: '
        . e(
            $verification['status'] ?? 'UNKNOWN'
        )
    );
}


/*
|--------------------------------------------------------------------------
| Payment is verified — update orders in DB
|--------------------------------------------------------------------------
*/

$orderNumber   = $paymentSession['order_number']    ?? '';
$orderId       = $paymentSession['order_id']        ?? null;

if (!empty($orderNumber)) {
    // Update all rows sharing this order_number
    $stmt = $conn->prepare(
        "UPDATE orders
            SET payment_status   = 'Paid',
                transaction_uuid = ?,
                transaction_code = ?
          WHERE order_number = ?"
    );
    if ($stmt) {
        $stmt->bind_param('sss', $transactionUuid, $transactionCode, $orderNumber);
        $stmt->execute();
        $stmt->close();
    }
} elseif ($orderId) {
    // Fallback: update by order_id
    $stmt = $conn->prepare(
        "UPDATE orders
            SET payment_status   = 'Paid',
                transaction_uuid = ?,
                transaction_code = ?
          WHERE order_id = ?"
    );
    if ($stmt) {
        $stmt->bind_param('ssi', $transactionUuid, $transactionCode, $orderId);
        $stmt->execute();
        $stmt->close();
    }
}

// Clear the cart session now that payment is confirmed
$_SESSION['cart'] = [];

unset($_SESSION['esewa_payment']);

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Successful – Mero Bhoj</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css">

    <style>
        body {
            background: #f8f9fa;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Segoe UI', Arial, sans-serif;
        }

        .success-card {
            background: #fff;
            border-radius: 20px;
            box-shadow: 0 8px 40px rgba(0,0,0,.10);
            padding: 48px 40px;
            text-align: center;
            max-width: 440px;
            width: 100%;
        }

        .esewa-checkmark {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            display: block;
            stroke-width: 3;
            stroke: #fff;
            stroke-miterlimit: 10;
            margin: 0 auto 20px auto;
            box-shadow: inset 0px 0px 0px #60bb46;
            animation: fill .4s ease-in-out .4s forwards, scale .3s ease-in-out .9s both;
        }
        .esewa-checkmark__circle {
            stroke-dasharray: 166;
            stroke-dashoffset: 166;
            stroke-width: 3;
            stroke-miterlimit: 10;
            stroke: #60bb46;
            fill: none;
            animation: stroke 0.6s cubic-bezier(0.65, 0, 0.45, 1) forwards;
        }
        .esewa-checkmark__check {
            transform-origin: 50% 50%;
            stroke-dasharray: 48;
            stroke-dashoffset: 48;
            animation: stroke 0.3s cubic-bezier(0.65, 0, 0.45, 1) 0.8s forwards;
        }
        @keyframes stroke {
            100% { stroke-dashoffset: 0; }
        }
        @keyframes scale {
            0%, 100% { transform: none; }
            50% { transform: scale3d(1.1, 1.1, 1); }
        }
        @keyframes fill {
            100% { box-shadow: inset 0px 0px 0px 50px #60bb46; }
        }

        .success-card h1 {
            font-size: 1.6rem;
            font-weight: 800;
            color: #1f1f1f;
            margin-bottom: 10px;
        }

        .success-card p {
            color: #6b7280;
            margin-bottom: 6px;
        }

        .btn-orders {
            background: #16a34a;
            color: #fff;
            border: none;
            border-radius: 10px;
            padding: 12px 32px;
            font-weight: 700;
            font-size: 1rem;
            margin-top: 20px;
            display: inline-block;
            text-decoration: none;
            transition: background .2s;
        }
        .btn-orders:hover { background: #15803d; color: #fff; }

        .btn-home {
            display: inline-block;
            margin-top: 12px;
            color: #6b7280;
            text-decoration: none;
            font-size: .9rem;
        }
        .btn-home:hover { color: #16a34a; }
    </style>
</head>

<body>

<div class="success-card">
    <svg class="esewa-checkmark" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 52 52">
        <circle class="esewa-checkmark__circle" cx="26" cy="26" r="25" fill="none"/>
        <path class="esewa-checkmark__check" fill="none" d="M14.1 27.2l7.1 7.2 16.7-16.8"/>
    </svg>

    <h1>Payment Successful!</h1>

    <p>Your order has been confirmed.</p>

    <?php if ($orderNumber): ?>
        <p class="text-muted" style="font-size:.88rem;">
            Order Ref: <strong><?= e((string)$orderNumber) ?></strong>
        </p>
    <?php endif; ?>
    
    <p class="text-muted" style="font-size:.88rem;">
        eSewa Ref: <strong><?= e($transactionCode) ?></strong>
    </p>

    <a href="<?= e(SITE_URL) ?>/client/myorder.php" class="btn-orders">
        View My Orders
    </a>

    <br>
    <a href="<?= e(SITE_URL) ?>/client/index.php" class="btn-home">
        Return to Home
    </a>
</div>

</body>
</html>