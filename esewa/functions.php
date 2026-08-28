<?php
// filepath: c:\xampp\htdocs\Merobhoj\esewa\functions.php

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../includes/db.php';

function getOrderAmountFromDatabase(int $orderId)
{
    global $conn;

    // Get order_number for this order_id, then sum all rows with that order_number
    $stmt = $conn->prepare(
        'SELECT order_number FROM orders WHERE order_id = ? LIMIT 1'
    );

    if (!$stmt) {
        return false;
    }

    $stmt->bind_param('i', $orderId);
    $stmt->execute();
    $stmt->bind_result($orderNumber);
    $found = $stmt->fetch();
    $stmt->close();

    if (!$found || !$orderNumber) {
        return false;
    }

    return getOrderAmountByNumber($orderNumber);
}

function getOrderAmountByNumber(string $orderNumber)
{
    global $conn;

    $stmt = $conn->prepare(
        'SELECT SUM(total_price) FROM orders WHERE order_number = ?'
    );

    if (!$stmt) {
        return false;
    }

    $stmt->bind_param('s', $orderNumber);
    $stmt->execute();
    $stmt->bind_result($amount);

    $found = $stmt->fetch();
    $stmt->close();

    return ($found && $amount !== null) ? $amount : false;
}

function generateEsewaSignature(
    string $totalAmount,
    string $transactionUuid,
    string $productCode
): string {
    $message =
        'total_amount=' . $totalAmount .
        ',transaction_uuid=' . $transactionUuid .
        ',product_code=' . $productCode;

    return base64_encode(
        hash_hmac(
            'sha256',
            $message,
            ESEWA_SECRET_KEY,
            true
        )
    );
}

function generateTransactionUuid(): string
{
    return 'ORDER-' . date('YmdHis') . '-' . bin2hex(random_bytes(4));
}

function e($value): string
{
    return htmlspecialchars(
        (string) $value,
        ENT_QUOTES,
        'UTF-8'
    );
}

function verifyEsewaResponseSignature(array $response): bool
{
    if (
        empty($response['signed_field_names']) ||
        empty($response['signature'])
    ) {
        return false;
    }

    $fields = explode(',', $response['signed_field_names']);
    $parts = [];

    foreach ($fields as $field) {
        if (!array_key_exists($field, $response)) {
            return false;
        }

        $parts[] = $field . '=' . $response[$field];
    }

    $expectedSignature = base64_encode(
        hash_hmac(
            'sha256',
            implode(',', $parts),
            ESEWA_SECRET_KEY,
            true
        )
    );

    return hash_equals(
        $expectedSignature,
        $response['signature']
    );
}

function checkEsewaTransactionStatus(
    string $transactionUuid,
    string $totalAmount
): ?array {
    $url = ESEWA_STATUS_URL .
        '?product_code=' . urlencode(ESEWA_PRODUCT_CODE) .
        '&total_amount=' . urlencode($totalAmount) .
        '&transaction_uuid=' . urlencode($transactionUuid);

    $ch = curl_init($url);

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_HTTPGET => true
    ]);

    $response = curl_exec($ch);

    if ($response === false) {
        curl_close($ch);
        return null;
    }

    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode < 200 || $httpCode >= 300) {
        return null;
    }

    $data = json_decode($response, true);

    return is_array($data) ? $data : null;
}