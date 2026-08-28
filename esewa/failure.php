<?php

session_start();

require_once __DIR__ . '/functions.php';

$paymentSession =
    $_SESSION['esewa_payment'] ?? null;

$orderId =
    $paymentSession['order_id'] ?? null;

$orderNumber =
    $paymentSession['order_number'] ?? null;


/*
|--------------------------------------------------------------------------
| Mark orders as failed in DB
|--------------------------------------------------------------------------
*/

if (!empty($orderNumber)) {
    $stmt = $conn->prepare(
        "UPDATE orders SET payment_status = 'Failed' WHERE order_number = ?"
    );
    if ($stmt) {
        $stmt->bind_param('s', $orderNumber);
        $stmt->execute();
        $stmt->close();
    }
}

/*
|--------------------------------------------------------------------------
| Clear temporary payment session
|--------------------------------------------------------------------------
*/

unset($_SESSION['esewa_payment']);

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Failed – Mero Bhoj</title>

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

        .failure-card {
            background: #fff;
            border-radius: 20px;
            box-shadow: 0 8px 40px rgba(0,0,0,.10);
            padding: 48px 40px;
            text-align: center;
            max-width: 440px;
            width: 100%;
        }

        .failure-icon {
            width: 72px;
            height: 72px;
            border-radius: 50%;
            background: #fee2e2;
            color: #dc2626;
            font-size: 2rem;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 20px;
        }

        .failure-card h1 {
            font-size: 1.6rem;
            font-weight: 800;
            color: #1f1f1f;
            margin-bottom: 10px;
        }

        .failure-card p {
            color: #6b7280;
            margin-bottom: 6px;
        }

        .btn-retry {
            background: #f05a22;
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
        .btn-retry:hover { background: #d94d1b; color: #fff; }

        .btn-orders {
            display: inline-block;
            margin-top: 12px;
            color: #6b7280;
            text-decoration: none;
            font-size: .9rem;
        }
        .btn-orders:hover { color: #f05a22; }
    </style>
</head>

<body>

<div class="failure-card">
    <div class="failure-icon">
        <i class="fa fa-xmark"></i>
    </div>

    <h1>Payment Failed</h1>

    <p>Your eSewa payment was not completed successfully.</p>

    <?php if ($orderNumber): ?>
        <p class="text-muted" style="font-size:.88rem;">
            Order Ref: <strong><?= e((string)$orderNumber) ?></strong>
        </p>
    <?php endif; ?>

    <a href="<?= e(SITE_URL) ?>/client/cart.php" class="btn-retry">
        <i class="fa fa-arrow-left me-2"></i> Return to Cart
    </a>

    <br>
    <a href="<?= e(SITE_URL) ?>/client/myorder.php" class="btn-orders">
        View My Orders
    </a>
</div>

</body>
</html>