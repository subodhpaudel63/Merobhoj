<?php
/**
 * eSewa Configuration
 *
 * TEST/UAT configuration.
 * Change these values when moving to production.
 */

define('ESEWA_ENVIRONMENT', 'uat');

// eSewa merchant product code
define('ESEWA_PRODUCT_CODE', 'EPAYTEST');

// UAT secret key provided by eSewa
define('ESEWA_SECRET_KEY', '8gBm/:&EnhH.1/q');

// UAT payment URL
define(
    'ESEWA_PAYMENT_URL',
    'https://rc-epay.esewa.com.np/api/epay/main/v2/form'
);

// UAT transaction status URL
define(
    'ESEWA_STATUS_URL',
    'https://rc.esewa.com.np/api/epay/transaction/status/'
);



define('SITE_URL', 'http://localhost/Merobhoj');

define(
    'ESEWA_SUCCESS_URL',
    SITE_URL . '/esewa/success.php'
);

define(
    'ESEWA_FAILURE_URL',
    SITE_URL . '/esewa/failure.php'
);