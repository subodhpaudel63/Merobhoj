<?php

require_once __DIR__ . '/functions.php';

$transactionUuid =
    $_GET['transaction_uuid'] ?? '';

$totalAmount =
    $_GET['total_amount'] ?? '';

if (
    empty($transactionUuid) ||
    empty($totalAmount)
) {
    exit('Missing transaction information.');
}

$result = checkEsewaTransactionStatus(
    $transactionUuid,
    $totalAmount
);

header(
    'Content-Type: application/json'
);

echo json_encode(
    $result ?: [
        'status' => 'ERROR',
        'message' => 'Unable to contact eSewa.'
    ],
    JSON_PRETTY_PRINT
);