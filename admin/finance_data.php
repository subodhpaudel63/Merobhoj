<?php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/../includes/admin_auth.php';
require_admin();
require_once __DIR__ . '/../includes/db.php';

header('Content-Type: application/json; charset=utf-8');

// Get filter parameters
$fiscal_year = $_GET['fiscalYear'] ?? '';
$start_date = $_GET['startDate'] ?? '';
$end_date = $_GET['endDate'] ?? '';

// Default to last 30 days if no dates provided
if (empty($start_date)) {
    $start_date = date('Y-m-d', strtotime('-30 days'));
}
if (empty($end_date)) {
    $end_date = date('Y-m-d');
}

// Fetch orders within date range
$orders = [];
$order_query = $conn->prepare("
    SELECT 
        order_id,
        order_number,
        order_date,
        total_price,
        status,
        payment_method,
        order_type,
        email,
        menu_name,
        quantity,
        price
    FROM orders
    WHERE DATE(order_date) BETWEEN ? AND ?
    ORDER BY order_date DESC
");
$order_query->bind_param("ss", $start_date, $end_date);
$order_query->execute();
$order_result = $order_query->get_result();

while ($row = $order_result->fetch_assoc()) {
    $orders[] = [
        'id' => $row['order_number'] ?: ('RW-' . date('Y') . '-' . str_pad((string)$row['order_id'], 6, '0', STR_PAD_LEFT)),
        'date' => $row['order_date'],
        'orderType' => $row['order_type'] ?? 'Delivery',
        'paymentMethod' => $row['payment_method'] ?? 'Cash on Delivery',
        'grossAmount' => (float)$row['total_price'],
        'discount' => 0.00,
        'status' => $row['status'] ?? 'Pending',
        'items' => [
            [
                'category' => 'Food',
                'name' => $row['menu_name'] ?? 'Unknown Item',
                'qty' => (int)$row['quantity'],
                'unitPrice' => (float)$row['price']
            ]
        ]
    ];
}

// Calculate totals
$gross_revenue = 0;
$total_orders = count($orders);
$order_type_breakdown = [];
$category_breakdown = [];
$daily_sales = [];

foreach ($orders as $order) {
    $gross_revenue += $order['grossAmount'];
    
    $type = $order['orderType'];
    if (!isset($order_type_breakdown[$type])) {
        $order_type_breakdown[$type] = ['orderType' => $type, 'revenue' => 0, 'count' => 0];
    }
    $order_type_breakdown[$type]['revenue'] += $order['grossAmount'];
    $order_type_breakdown[$type]['count']++;
    
    foreach ($order['items'] as $item) {
        $cat = $item['category'];
        if (!isset($category_breakdown[$cat])) {
            $category_breakdown[$cat] = ['category' => $cat, 'revenue' => 0, 'qty' => 0];
        }
        $category_breakdown[$cat]['revenue'] += $item['qty'] * $item['unitPrice'];
        $category_breakdown[$cat]['qty'] += $item['qty'];
    }
    
    $date = date('Y-m-d', strtotime($order['date']));
    if (!isset($daily_sales[$date])) {
        $daily_sales[$date] = ['date' => $date, 'revenue' => 0, 'orders' => 0];
    }
    $daily_sales[$date]['revenue'] += $order['grossAmount'];
    $daily_sales[$date]['orders']++;
}

ksort($daily_sales);

// Calculate expenses (if expenses table exists)
$expenses = [];
$total_expenses = 0;
try {
    $expense_query = $conn->prepare("
        SELECT date, category, amount, title, description
        FROM expenses
        WHERE date BETWEEN ? AND ?
        ORDER BY date DESC
    ");
    $expense_query->bind_param("ss", $start_date, $end_date);
    $expense_query->execute();
    $expense_result = $expense_query->get_result();
    while ($row = $expense_result->fetch_assoc()) {
        $expenses[] = [
            'date' => $row['date'],
            'account' => $row['category'] ?? 'General',
            'amount' => (float)$row['amount'],
            // The note is optional, so fall back to the expense's own short
            // title before the category — a ledger line reading "Ingredients"
            // tells the reader nothing about what was actually bought.
            'description' => ($row['description'] ?? '') !== ''
                ? $row['description']
                : ($row['title'] ?? '')
        ];
        $total_expenses += (float)$row['amount'];
    }
} catch (Exception $e) {
    // Expenses table may not exist, continue without it
}

// Calculate tax (13% VAT)
$taxable_sales = $gross_revenue;
$vat_rate = 13;
$output_vat = $taxable_sales * ($vat_rate / 100);
$input_vat = $total_expenses * ($vat_rate / 100);
$net_vat_payable = $output_vat - $input_vat;

$tax_summary = [
    ['item' => 'Taxable Sales', 'base' => $taxable_sales, 'rate' => $vat_rate, 'tax' => $output_vat],
    ['item' => 'Taxable Expenses', 'base' => $total_expenses, 'rate' => $vat_rate, 'tax' => $input_vat]
];

// Calculate net profit
$net_profit = $gross_revenue - $total_expenses - $output_vat;

// Get unique customers count
$customer_query = $conn->prepare("
    SELECT COUNT(DISTINCT email) as customer_count 
    FROM orders 
    WHERE DATE(order_date) BETWEEN ? AND ?
");
$customer_query->bind_param("ss", $start_date, $end_date);
$customer_query->execute();
$customer_result = $customer_query->get_result()->fetch_assoc();
$unique_customers = (int)($customer_result['customer_count'] ?? 0);

// Build cash vouchers from orders
$cash_vouchers = [];
foreach ($orders as $order) {
    $cash_vouchers[] = [
        'date' => date('Y-m-d', strtotime($order['date'])),
        'voucher' => 'SV-' . date('Y') . '-' . substr($order['id'], -6),
        'narration' => 'Sales invoice ' . $order['id'],
        'cashIn' => $order['grossAmount'],
        'cashOut' => 0.00
    ];
}

// Build general ledger
$general_ledger = [
    '5000' => [],
    '4000' => [],
    '100' => [],
    '2000' => []
];

foreach ($orders as $order) {
    $date = date('Y-m-d', strtotime($order['date']));
    $general_ledger['100'][] = [
        'date' => $date,
        'voucher' => 'SV-' . substr($order['id'], -6),
        'narration' => 'Sales - ' . $order['id'],
        'debit' => $order['grossAmount'],
        'credit' => 0
    ];
    $general_ledger['4000'][] = [
        'date' => $date,
        'voucher' => 'SV-' . substr($order['id'], -6),
        'narration' => 'Sales - ' . $order['id'],
        'debit' => 0,
        'credit' => $order['grossAmount']
    ];
}

foreach ($expenses as $expense) {
    $general_ledger['5000'][] = [
        'date' => $expense['date'],
        'voucher' => 'PV-' . substr(md5($expense['date'] . $expense['description']), 0, 6),
        'narration' => $expense['description'] ?: $expense['account'],
        'debit' => $expense['amount'],
        'credit' => 0
    ];
    $general_ledger['100'][] = [
        'date' => $expense['date'],
        'voucher' => 'PV-' . substr(md5($expense['date'] . $expense['description']), 0, 6),
        'narration' => $expense['description'] ?: $expense['account'],
        'debit' => 0,
        'credit' => $expense['amount']
    ];
}

// Return the response
echo json_encode([
    'success' => true,
    'meta' => [
        'fiscalYear' => $fiscal_year,
        'startDate' => $start_date,
        'endDate' => $end_date,
        'today' => date('Y-m-d')
    ],
    'orders' => $orders,
    'expenses' => $expenses,
    'cashVouchers' => $cash_vouchers,
    'taxSummary' => $tax_summary,
    'generalLedger' => $general_ledger,
    'summary' => [
        'grossRevenue' => $gross_revenue,
        'totalOrders' => $total_orders,
        'totalExpenses' => $total_expenses,
        'outputVat' => $output_vat,
        'inputVat' => $input_vat,
        'netVatPayable' => $net_vat_payable,
        'netProfit' => $net_profit,
        'uniqueCustomers' => $unique_customers
    ],
    'orderTypes' => array_values($order_type_breakdown),
    'categoryBreakdown' => array_values($category_breakdown),
    'dailySales' => array_values($daily_sales)
], JSON_UNESCAPED_UNICODE);
