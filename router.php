<?php
session_start();
ini_set('memory_limit', '1024M');

$request_uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$base = '/Merobhoj/';
if (strpos($request_uri, $base) === 0) {
    $request_uri = substr($request_uri, strlen($base));
}
$request_uri = trim($request_uri, '/');






// Handle dynamic routes before the switch
if (preg_match('/^order\/([a-zA-Z0-9]+)$/', $request_uri, $matches)) {
    $_GET['token'] = $matches[1];
    require __DIR__ . '/order_qr.php';
    exit;
}
if ($request_uri === 'admin/api/api_qr_requests_client') {
    require __DIR__ . '/admin/api/api_qr_requests_client.php';
    exit;
}

switch ($request_uri) {
    // Public routes
    case '':
        require __DIR__ . '/index.php';
        break;
    case 'home':
    case 'index.php':
    case 'aboutus':
    case 'menu':
    case 'contactus':
    case 'login':
    case 'register':
    

        require __DIR__ . "/$request_uri.php";
        break;

    // User routes (auth required)
    case 'users/index':
    case 'users/menu':
    case 'users/aboutus':
    case 'users/myorder':
    case 'users/contactus':

     
        require __DIR__ . "/client/" . basename($request_uri) . ".php";
        break;

    // Admin routes (auth required)
    case 'admin/index':
    case 'admin/menu':
    case 'admin/myorder':
    case 'admin/users':
    case 'admin/bookings':
       
        if ($request_uri === 'admin/myorder') {
            require __DIR__ . '/admin/orders_page.php';
            break;
        }
        require __DIR__ . "/admin/" . basename($request_uri) . ".php";
        break;

   case 'logout':
            include './includes/logout.php';
            break;

    // Default 404
    default:
        http_response_code(404);
        require __DIR__ . '/404.php';
        break;
}

        
