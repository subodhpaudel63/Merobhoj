<?php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../includes/admin_auth.php';
require_admin();
require_once __DIR__ . '/../../includes/db.php';

header('Content-Type: application/json; charset=utf-8');

try {
    // ----------------------------------------------------
    // 1. Date Range Handling
    // ----------------------------------------------------
    $range = $_GET['range'] ?? '30days';
    $start_date = $_GET['startDate'] ?? '';
    $end_date = $_GET['endDate'] ?? '';

    $today = date('Y-m-d');

    if ($range === 'today') {
        $start_date = $today;
        $end_date = $today;
    } elseif ($range === '7days') {
        $start_date = date('Y-m-d', strtotime('-6 days'));
        $end_date = $today;
    } elseif ($range === '30days') {
        $start_date = date('Y-m-d', strtotime('-29 days'));
        $end_date = $today;
    } elseif ($range === 'month') {
        $start_date = date('Y-m-01');
        $end_date = $today;
    } elseif ($range === 'all') {
        $min_order_res = $conn->query("SELECT MIN(order_date) AS min_d FROM orders WHERE order_date IS NOT NULL AND order_date != '0000-00-00'");
        $min_d = $min_order_res ? $min_order_res->fetch_assoc()['min_d'] : null;
        $start_date = $min_d ? $min_d : date('Y-m-d', strtotime('-90 days'));
        $end_date = $today;
    } else {
        if (empty($start_date) || !strtotime($start_date)) {
            $start_date = date('Y-m-d', strtotime('-29 days'));
        }
        if (empty($end_date) || !strtotime($end_date)) {
            $end_date = $today;
        }
    }

    if ($start_date > $end_date) {
        $temp = $start_date;
        $start_date = $end_date;
        $end_date = $temp;
    }

    $days_count = max(1, (int)ceil((strtotime($end_date) - strtotime($start_date)) / 86400) + 1);

    // ----------------------------------------------------
    // 2. Aggregate KPI Metrics (Revenue, Orders, Items, Customers)
    // ----------------------------------------------------
    $kpi_query = $conn->prepare("
        SELECT 
            COALESCE(SUM(total_price), 0) AS gross_revenue,
            COUNT(DISTINCT IF(order_number != '', order_number, order_id)) AS total_orders,
            COUNT(DISTINCT IF(status = 'Completed', IF(order_number != '', order_number, order_id), NULL)) AS completed_orders,
            COUNT(DISTINCT IF(status = 'Cancelled', IF(order_number != '', order_number, order_id), NULL)) AS cancelled_orders,
            COALESCE(SUM(quantity), 0) AS total_items_sold,
            COUNT(DISTINCT IF(email != '', email, NULL)) AS unique_customers
        FROM orders
        WHERE order_date BETWEEN ? AND ? AND status <> 'Cancelled'
    ");
    $kpi_query->bind_param("ss", $start_date, $end_date);
    $kpi_query->execute();
    $kpi_res = $kpi_query->get_result()->fetch_assoc();

    $gross_revenue = (float)($kpi_res['gross_revenue'] ?? 0);
    $total_orders = (int)($kpi_res['total_orders'] ?? 0);
    $completed_orders = (int)($kpi_res['completed_orders'] ?? 0);
    $cancelled_orders = (int)($kpi_res['cancelled_orders'] ?? 0);
    $total_items_sold = (int)($kpi_res['total_items_sold'] ?? 0);
    $unique_customers = (int)($kpi_res['unique_customers'] ?? 0);
    $avg_order_value = $total_orders > 0 ? round($gross_revenue / $total_orders, 2) : 0.0;

    // ----------------------------------------------------
    // 3. Peak Hours Aggregation (0 - 23 Hours)
    // ----------------------------------------------------
    $hourly_data = [];
    for ($h = 0; $h < 24; $h++) {
        $display_h = ($h === 0) ? '12 AM' : (($h < 12) ? "{$h} AM" : (($h === 12) ? '12 PM' : ($h - 12) . ' PM'));
        $hourly_data[$h] = [
            'hour' => $h,
            'label' => $display_h,
            'orders' => 0,
            'revenue' => 0.0,
            'bookings' => 0
        ];
    }

    $order_hour_query = $conn->prepare("
        SELECT 
            HOUR(order_time) AS hr,
            COUNT(DISTINCT IF(order_number != '', order_number, order_id)) AS order_cnt,
            COALESCE(SUM(total_price), 0) AS rev
        FROM orders
        WHERE order_date BETWEEN ? AND ? AND status <> 'Cancelled'
        GROUP BY HOUR(order_time)
    ");
    $order_hour_query->bind_param("ss", $start_date, $end_date);
    $order_hour_query->execute();
    $order_hour_res = $order_hour_query->get_result();
    while ($row = $order_hour_res->fetch_assoc()) {
        $h = (int)$row['hr'];
        if (isset($hourly_data[$h])) {
            $hourly_data[$h]['orders'] = (int)$row['order_cnt'];
            $hourly_data[$h]['revenue'] = (float)$row['rev'];
        }
    }

    $booking_hour_query = $conn->prepare("
        SELECT 
            HOUR(booking_time) AS hr,
            COUNT(*) AS b_cnt
        FROM bookings
        WHERE booking_date BETWEEN ? AND ? AND status NOT IN ('Cancelled')
        GROUP BY HOUR(booking_time)
    ");
    $booking_hour_query->bind_param("ss", $start_date, $end_date);
    $booking_hour_query->execute();
    $booking_hour_res = $booking_hour_query->get_result();
    while ($row = $booking_hour_res->fetch_assoc()) {
        $h = (int)$row['hr'];
        if (isset($hourly_data[$h])) {
            $hourly_data[$h]['bookings'] = (int)$row['b_cnt'];
        }
    }

    $peak_hour_val = 0;
    $peak_hour_orders = -1;
    $peak_rev_hour_val = 0;
    $peak_rev_amount = -1.0;

    $meal_periods = [
        'breakfast' => ['label' => 'Breakfast (7AM-11AM)', 'hours' => [7, 8, 9, 10], 'orders' => 0, 'revenue' => 0.0, 'color' => '#f59e0b'],
        'lunch' => ['label' => 'Lunch (11AM-3PM)', 'hours' => [11, 12, 13, 14], 'orders' => 0, 'revenue' => 0.0, 'color' => '#10b981'],
        'afternoon' => ['label' => 'Afternoon / Tea (3PM-6PM)', 'hours' => [15, 16, 17], 'orders' => 0, 'revenue' => 0.0, 'color' => '#3b82f6'],
        'dinner' => ['label' => 'Dinner (6PM-10PM)', 'hours' => [18, 19, 20, 21], 'orders' => 0, 'revenue' => 0.0, 'color' => '#ef4444'],
        'latenight' => ['label' => 'Late Night (10PM-7AM)', 'hours' => [22, 23, 0, 1, 2, 3, 4, 5, 6], 'orders' => 0, 'revenue' => 0.0, 'color' => '#8b5cf6'],
    ];

    foreach ($hourly_data as $h => $h_info) {
        if ($h_info['orders'] > $peak_hour_orders) {
            $peak_hour_orders = $h_info['orders'];
            $peak_hour_val = $h;
        }
        if ($h_info['revenue'] > $peak_rev_amount) {
            $peak_rev_amount = $h_info['revenue'];
            $peak_rev_hour_val = $h;
        }

        foreach ($meal_periods as $key => &$mp) {
            if (in_array($h, $mp['hours'], true)) {
                $mp['orders'] += $h_info['orders'];
                $mp['revenue'] += $h_info['revenue'];
            }
        }
        unset($mp);
    }

    $busiest_meal_key = 'dinner';
    $busiest_meal_orders = -1;
    foreach ($meal_periods as $key => $mp) {
        if ($mp['orders'] > $busiest_meal_orders) {
            $busiest_meal_orders = $mp['orders'];
            $busiest_meal_key = $key;
        }
    }
    // ----------------------------------------------------
    // 4. Day of Week Distribution
    // ----------------------------------------------------
    $days_of_week = [
        'Sunday' => ['orders' => 0, 'revenue' => 0.0],
        'Monday' => ['orders' => 0, 'revenue' => 0.0],
        'Tuesday' => ['orders' => 0, 'revenue' => 0.0],
        'Wednesday' => ['orders' => 0, 'revenue' => 0.0],
        'Thursday' => ['orders' => 0, 'revenue' => 0.0],
        'Friday' => ['orders' => 0, 'revenue' => 0.0],
        'Saturday' => ['orders' => 0, 'revenue' => 0.0]
    ];

    $dow_query = $conn->prepare("
        SELECT 
            DAYNAME(order_date) AS dow,
            COUNT(DISTINCT IF(order_number != '', order_number, order_id)) AS order_cnt,
            COALESCE(SUM(total_price), 0) AS rev
        FROM orders
        WHERE order_date BETWEEN ? AND ? AND status <> 'Cancelled'
        GROUP BY DAYNAME(order_date)
    ");
    $dow_query->bind_param("ss", $start_date, $end_date);
    $dow_query->execute();
    $dow_res = $dow_query->get_result();
    while ($row = $dow_res->fetch_assoc()) {
        $dow = $row['dow'];
        if (isset($days_of_week[$dow])) {
            $days_of_week[$dow]['orders'] = (int)$row['order_cnt'];
            $days_of_week[$dow]['revenue'] = (float)$row['rev'];
        }
    }

    // ----------------------------------------------------
    // 5. Daily Sales Trend (Dates timeline)
    // ----------------------------------------------------
    $daily_sales_map = [];
    $curr_ts = strtotime($start_date);
    $end_ts = strtotime($end_date);
    while ($curr_ts <= $end_ts) {
        $d_str = date('Y-m-d', $curr_ts);
        $daily_sales_map[$d_str] = [
            'date' => $d_str,
            'label' => date('M j', $curr_ts),
            'dayName' => date('D', $curr_ts),
            'revenue' => 0.0,
            'orders' => 0,
            'avgOrderValue' => 0.0
        ];
        $curr_ts = strtotime('+1 day', $curr_ts);
    }

    $daily_query = $conn->prepare("
        SELECT 
            order_date,
            COUNT(DISTINCT IF(order_number != '', order_number, order_id)) AS order_cnt,
            COALESCE(SUM(total_price), 0) AS rev
        FROM orders
        WHERE order_date BETWEEN ? AND ? AND status <> 'Cancelled'
        GROUP BY order_date
        ORDER BY order_date ASC
    ");
    $daily_query->bind_param("ss", $start_date, $end_date);
    $daily_query->execute();
    $daily_res = $daily_query->get_result();
    while ($row = $daily_res->fetch_assoc()) {
        $row_date_val = $row['order_date'];
        if (isset($daily_sales_map[$row_date_val])) {
            $r = (float)$row['rev'];
            $c = (int)$row['order_cnt'];
            $daily_sales_map[$row_date_val]['revenue'] = $r;
            $daily_sales_map[$row_date_val]['orders'] = $c;
            $daily_sales_map[$row_date_val]['avgOrderValue'] = $c > 0 ? round($r / $c, 2) : 0.0;
        }
    }

    // ----------------------------------------------------
    // 6. Table Turnover & Seating Utilization
    // ----------------------------------------------------
    $restaurant_tables = [];
    $tables_res = $conn->query("SELECT id, table_name, capacity FROM restaurant_tables ORDER BY id ASC");
    if ($tables_res) {
        while ($t = $tables_res->fetch_assoc()) {
            $restaurant_tables[$t['id']] = [
                'id' => (int)$t['id'],
                'name' => $t['table_name'],
                'capacity' => (int)$t['capacity'],
                'dinein_orders' => 0,
                'bookings_count' => 0,
                'total_turns' => 0,
                'revenue' => 0.0
            ];
        }
    }

    $table_orders_query = $conn->prepare("
        SELECT 
            table_number,
            COUNT(DISTINCT IF(order_number != '', order_number, order_id)) AS order_cnt,
            COALESCE(SUM(total_price), 0) AS rev
        FROM orders
        WHERE order_date BETWEEN ? AND ? 
          AND status <> 'Cancelled'
          AND table_number IS NOT NULL 
          AND table_number != ''
        GROUP BY table_number
    ");
    $table_orders_query->bind_param("ss", $start_date, $end_date);
    $table_orders_query->execute();
    $table_orders_res = $table_orders_query->get_result();

    $unmatched_table_orders = 0;
    $unmatched_table_revenue = 0.0;

    while ($row = $table_orders_res->fetch_assoc()) {
        $tbl_raw = trim((string)$row['table_number']);
        $tbl_id = (int)preg_replace('/[^0-9]/', '', $tbl_raw);
        $cnt = (int)$row['order_cnt'];
        $rev = (float)$row['rev'];

        if ($tbl_id > 0 && isset($restaurant_tables[$tbl_id])) {
            $restaurant_tables[$tbl_id]['dinein_orders'] += $cnt;
            $restaurant_tables[$tbl_id]['revenue'] += $rev;
        } else {
            $unmatched_table_orders += $cnt;
            $unmatched_table_revenue += $rev;
        }
    }

    $table_booking_query = $conn->prepare("
        SELECT 
            table_id,
            COUNT(*) AS b_cnt,
            COALESCE(AVG(TIMESTAMPDIFF(MINUTE, start_time, end_time)), 0) AS avg_duration_mins
        FROM bookings
        WHERE booking_date BETWEEN ? AND ? 
          AND status IN ('Confirmed', 'Completed', 'pending')
          AND table_id > 0
        GROUP BY table_id
    ");
    $table_booking_query->bind_param("ss", $start_date, $end_date);
    $table_booking_query->execute();
    $table_booking_res = $table_booking_query->get_result();

    $total_duration_sum = 0;
    $duration_count = 0;

    while ($row = $table_booking_res->fetch_assoc()) {
        $t_id = (int)$row['table_id'];
        $b_cnt = (int)$row['b_cnt'];
        $avg_dur = (float)$row['avg_duration_mins'];

        if (isset($restaurant_tables[$t_id])) {
            $restaurant_tables[$t_id]['bookings_count'] += $b_cnt;
        }
        if ($avg_dur > 0) {
            $total_duration_sum += ($avg_dur * $b_cnt);
            $duration_count += $b_cnt;
        }
    }

    $total_table_turns = 0;
    $total_table_capacity = 0;
    $table_count = count($restaurant_tables);
    $busiest_table_name = 'Table 1';
    $highest_turns = -1;
    $highest_rev_table_name = 'Table 1';
    $highest_table_rev = -1.0;

    $operating_minutes_per_day = 720;
    $total_operating_minutes = $operating_minutes_per_day * $days_count;

    $avg_seating_duration = $duration_count > 0 ? round($total_duration_sum / $duration_count) : 65;
    if ($avg_seating_duration <= 0 || $avg_seating_duration > 240) {
        $avg_seating_duration = 65;
    }

    foreach ($restaurant_tables as $id => &$tbl) {
        $total_turns = $tbl['dinein_orders'] + $tbl['bookings_count'];
        $tbl['total_turns'] = $total_turns;
        $total_table_turns += $total_turns;
        $total_table_capacity += $tbl['capacity'];

        $tbl['turnover_rate_per_day'] = round($total_turns / $days_count, 2);
        $tbl['avg_turn_rev'] = $total_turns > 0 ? round($tbl['revenue'] / $total_turns, 2) : 0.0;

        $occupied_minutes = $total_turns * $avg_seating_duration;
        $utilization = min(100.0, round(($occupied_minutes / $total_operating_minutes) * 100, 1));
        $tbl['utilization_pct'] = $utilization;

        if ($total_turns > $highest_turns) {
            $highest_turns = $total_turns;
            $busiest_table_name = $tbl['name'];
        }
        if ($tbl['revenue'] > $highest_table_rev) {
            $highest_table_rev = $tbl['revenue'];
            $highest_rev_table_name = $tbl['name'];
        }
    }
    unset($tbl);

    $avg_overall_turnover_rate = ($table_count > 0 && $days_count > 0)
        ? round($total_table_turns / ($table_count * $days_count), 2)
        : 0.0;

    $avg_table_utilization = $table_count > 0
        ? round(array_sum(array_column($restaurant_tables, 'utilization_pct')) / $table_count, 1)
        : 0.0;

    // ----------------------------------------------------
    // 7. Order Types / Channels Breakdown
    // ----------------------------------------------------
    $order_types_map = [
        'Dine In' => ['label' => 'Dine In', 'count' => 0, 'revenue' => 0.0, 'color' => '#10b981'],
        'Delivery' => ['label' => 'Delivery', 'count' => 0, 'revenue' => 0.0, 'color' => '#3b82f6'],
        'Takeaway' => ['label' => 'Takeaway', 'count' => 0, 'revenue' => 0.0, 'color' => '#f59e0b'],
        'QR Self Order' => ['label' => 'QR Self Order', 'count' => 0, 'revenue' => 0.0, 'color' => '#8b5cf6'],
    ];

    $type_query = $conn->prepare("
        SELECT 
            COALESCE(NULLIF(order_type, ''), 'Delivery') AS otype,
            COUNT(DISTINCT IF(order_number != '', order_number, order_id)) AS order_cnt,
            COALESCE(SUM(total_price), 0) AS rev
        FROM orders
        WHERE order_date BETWEEN ? AND ? AND status <> 'Cancelled'
        GROUP BY otype
    ");
    $type_query->bind_param("ss", $start_date, $end_date);
    $type_query->execute();
    $type_res = $type_query->get_result();
    while ($row = $type_res->fetch_assoc()) {
        $t = $row['otype'];
        $c = (int)$row['order_cnt'];
        $r = (float)$row['rev'];
        if (!isset($order_types_map[$t])) {
            $order_types_map[$t] = ['label' => $t, 'count' => 0, 'revenue' => 0.0, 'color' => '#ec4899'];
        }
        $order_types_map[$t]['count'] += $c;
        $order_types_map[$t]['revenue'] += $r;
    }

    // ----------------------------------------------------
    // 8. Payment Method Distribution
    // ----------------------------------------------------
    $payment_methods_map = [];
    $pay_query = $conn->prepare("
        SELECT 
            COALESCE(NULLIF(payment_method, ''), 'Cash on Delivery') AS pmethod,
            COUNT(DISTINCT IF(order_number != '', order_number, order_id)) AS order_cnt,
            COALESCE(SUM(total_price), 0) AS rev
        FROM orders
        WHERE order_date BETWEEN ? AND ? AND status <> 'Cancelled'
        GROUP BY pmethod
    ");
    $pay_query->bind_param("ss", $start_date, $end_date);
    $pay_query->execute();
    $pay_res = $pay_query->get_result();
    while ($row = $pay_res->fetch_assoc()) {
        $payment_methods_map[] = [
            'method' => $row['pmethod'],
            'orders' => (int)$row['order_cnt'],
            'revenue' => (float)$row['rev']
        ];
    }

    // ----------------------------------------------------
    // 9. Top 10 Best-Selling Menu Items
    // ----------------------------------------------------
    $top_items = [];
    $top_query = $conn->prepare("
        SELECT 
            menu_name,
            SUM(quantity) AS qty_sold,
            SUM(total_price) AS total_sales,
            ROUND(AVG(price), 2) AS unit_price
        FROM orders
        WHERE order_date BETWEEN ? AND ? AND status <> 'Cancelled'
        GROUP BY menu_name
        ORDER BY qty_sold DESC, total_sales DESC
        LIMIT 10
    ");
    $top_query->bind_param("ss", $start_date, $end_date);
    $top_query->execute();
    $top_res = $top_query->get_result();
    $rank = 1;
    while ($row = $top_res->fetch_assoc()) {
        $sales = (float)$row['total_sales'];
        $top_items[] = [
            'rank' => $rank++,
            'name' => $row['menu_name'],
            'quantity' => (int)$row['qty_sold'],
            'revenue' => $sales,
            'unit_price' => (float)$row['unit_price'],
            'share_pct' => $gross_revenue > 0 ? round(($sales / $gross_revenue) * 100, 1) : 0.0
        ];
    }

    // ----------------------------------------------------
    // 10. Bookings & Reservations Performance
    // ----------------------------------------------------
    $booking_kpi_query = $conn->prepare("
        SELECT 
            COUNT(*) AS total_bookings,
            COUNT(IF(status = 'Completed', 1, NULL)) AS completed_b,
            COUNT(IF(status = 'Confirmed', 1, NULL)) AS confirmed_b,
            COUNT(IF(status = 'pending', 1, NULL)) AS pending_b,
            COUNT(IF(status = 'Cancelled', 1, NULL)) AS cancelled_b,
            COUNT(IF(status = 'No-show', 1, NULL)) AS noshow_b,
            COALESCE(SUM(people), 0) AS total_guests
        FROM bookings
        WHERE booking_date BETWEEN ? AND ?
    ");
    $booking_kpi_query->bind_param("ss", $start_date, $end_date);
    $booking_kpi_query->execute();
    $booking_kpi_res = $booking_kpi_query->get_result()->fetch_assoc();

    $total_bookings = (int)($booking_kpi_res['total_bookings'] ?? 0);
    $completed_bookings = (int)($booking_kpi_res['completed_b'] ?? 0);
    $confirmed_bookings = (int)($booking_kpi_res['confirmed_b'] ?? 0);
    $cancelled_bookings = (int)($booking_kpi_res['cancelled_b'] ?? 0);
    $noshow_bookings = (int)($booking_kpi_res['noshow_b'] ?? 0);
    $total_guests = (int)($booking_kpi_res['total_guests'] ?? 0);

    $completion_rate = $total_bookings > 0 ? round(($completed_bookings / $total_bookings) * 100, 1) : 0.0;
    $noshow_rate = $total_bookings > 0 ? round(($noshow_bookings / $total_bookings) * 100, 1) : 0.0;
    $cancellation_rate = $total_bookings > 0 ? round(($cancelled_bookings / $total_bookings) * 100, 1) : 0.0;

    // ----------------------------------------------------
    // 11. Automated Management Insights
    // ----------------------------------------------------
    $peak_hour_display = $hourly_data[$peak_hour_val]['label'] ?? '8 PM';
    $peak_rev_hour_display = $hourly_data[$peak_rev_hour_val]['label'] ?? '8 PM';
    $busiest_meal_label = $meal_periods[$busiest_meal_key]['label'] ?? 'Dinner';
    $top_item_name = !empty($top_items) ? $top_items[0]['name'] : 'N/A';

    $insights = [
        [
            'type' => 'peak_hours',
            'title' => 'Peak Operational Rush',
            'icon' => 'schedule',
            'color' => 'danger',
            'text' => "Busiest traffic occurs at <strong>{$peak_hour_display}</strong> with <strong>{$peak_hour_orders} orders</strong>. Peak revenue is generated around <strong>{$peak_rev_hour_display}</strong> (Rs " . number_format($peak_rev_amount, 0) . ")."
        ],
        [
            'type' => 'table_turnover',
            'title' => 'Table Turnover Efficiency',
            'icon' => 'table_restaurant',
            'color' => 'success',
            'text' => "Average table turnover is <strong>{$avg_overall_turnover_rate} turns/table/day</strong> with an average dining duration of <strong>{$avg_seating_duration} mins</strong>. <strong>{$busiest_table_name}</strong> is the most active table."
        ],
        [
            'type' => 'meal_periods',
            'title' => 'Highest Yield Meal Window',
            'icon' => 'restaurant',
            'color' => 'warning',
            'text' => "<strong>{$busiest_meal_label}</strong> produces the largest order volume with <strong>{$busiest_meal_orders} orders</strong> (" . ($total_orders > 0 ? round(($busiest_meal_orders / $total_orders) * 100, 1) : 0) . "% of all orders)."
        ],
        [
            'type' => 'top_performer',
            'title' => 'Top Performing Product',
            'icon' => 'stars',
            'color' => 'primary',
            'text' => "<strong>{$top_item_name}</strong> is your top seller, contributing <strong>" . (!empty($top_items) ? $top_items[0]['share_pct'] : 0) . "%</strong> of total sales volume."
        ]
    ];

    echo json_encode([
        'success' => true,
        'meta' => [
            'range' => $range,
            'startDate' => $start_date,
            'endDate' => $end_date,
            'daysCount' => $days_count,
            'today' => $today,
            'generatedAt' => date('Y-m-d H:i:s')
        ],
        'kpis' => [
            'grossRevenue' => $gross_revenue,
            'totalOrders' => $total_orders,
            'completedOrders' => $completed_orders,
            'cancelledOrders' => $cancelled_orders,
            'avgOrderValue' => $avg_order_value,
            'totalItemsSold' => $total_items_sold,
            'uniqueCustomers' => $unique_customers,
            'avgTableTurnover' => $avg_overall_turnover_rate,
            'avgDiningDurationMins' => $avg_seating_duration,
            'tableUtilizationPct' => $avg_table_utilization,
            'peakHour' => $peak_hour_display,
            'peakHourOrders' => $peak_hour_orders,
            'peakRevenueHour' => $peak_rev_hour_display,
            'peakRevenueAmount' => $peak_rev_amount,
            'busiestTable' => $busiest_table_name,
            'highestRevenueTable' => $highest_rev_table_name,
            'totalTableTurns' => $total_table_turns,
            'totalBookings' => $total_bookings,
            'totalGuests' => $total_guests,
            'noshowRate' => $noshow_rate,
            'cancellationRate' => $cancellation_rate
        ],
        'peakHours' => array_values($hourly_data),
        'mealPeriods' => array_values($meal_periods),
        'dayOfWeek' => $days_of_week,
        'salesTrend' => array_values($daily_sales_map),
        'tableTurnover' => array_values($restaurant_tables),
        'orderTypes' => array_values($order_types_map),
        'paymentMethods' => $payment_methods_map,
        'topItems' => $top_items,
        'bookingHealth' => [
            'total' => $total_bookings,
            'completed' => $completed_bookings,
            'confirmed' => $confirmed_bookings,
            'cancelled' => $cancelled_bookings,
            'noshow' => $noshow_bookings,
            'completionRate' => $completion_rate,
            'noshowRate' => $noshow_rate,
            'cancellationRate' => $cancellation_rate,
            'totalGuests' => $total_guests
        ],
        'insights' => $insights
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}














