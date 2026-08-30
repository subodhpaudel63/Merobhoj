<?php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Synchronizes the admin_notifications table with orders, bookings, and feedback.
 */
function sync_notifications(mysqli $conn): void {
    // Check if the admin_notifications table is currently empty
    $emptyCheck = $conn->query("SELECT COUNT(*) FROM `admin_notifications`");
    $isFirstInit = false;
    if ($emptyCheck) {
        $isFirstInit = ((int)$emptyCheck->fetch_row()[0] === 0);
    }

    $now = time();
    // Notifications older than 2 hours are marked as read by default during background sync to avoid old alerts
    $twoHoursAgo = $now - (2 * 3600);

    $upsertNotification = function(string $type, string $resourceId, string $createdAtStr) use ($conn, $isFirstInit, $twoHoursAgo): void {
        $createdAtTs = strtotime($createdAtStr);
        if ($createdAtTs === false) {
            $createdAtTs = time();
        }

        $isRead = 1;
        if (!$isFirstInit && $createdAtTs >= $twoHoursAgo) {
            $isRead = 0;
        }

        $stmt = $conn->prepare("INSERT IGNORE INTO `admin_notifications` (`type`, `resource_id`, `is_read`, `created_at`) VALUES (?, ?, ?, ?)");
        if ($stmt) {
            $formattedTime = date('Y-m-d H:i:s', $createdAtTs);
            $stmt->bind_param('ssis', $type, $resourceId, $isRead, $formattedTime);
            $stmt->execute();
            $stmt->close();
        }
    };

    // 1. Sync Orders (latest 50)
    $ordersRes = $conn->query("
        SELECT 
            COALESCE(NULLIF(order_number, ''), CONCAT('ORD-', LPAD(MAX(order_id), 4, '0'))) as order_num,
            MAX(created_at) as created_at
        FROM orders
        GROUP BY COALESCE(NULLIF(order_number, ''), CONCAT('ORD-', LPAD(order_id, 4, '0')))
        ORDER BY MAX(created_at) DESC
        LIMIT 50
    ");
    if ($ordersRes) {
        while ($row = $ordersRes->fetch_assoc()) {
            $upsertNotification('order', $row['order_num'], $row['created_at']);
        }
    }

    // 2. Sync Bookings (latest 50)
    $bookingsRes = $conn->query("
        SELECT id, created_at FROM bookings
        ORDER BY created_at DESC
        LIMIT 50
    ");
    if ($bookingsRes) {
        while ($row = $bookingsRes->fetch_assoc()) {
            $upsertNotification('booking', (string)$row['id'], $row['created_at']);
        }
    }

    // 3. Sync Feedback (latest 50)
    $feedbackRes = $conn->query("
        SELECT feedback_id, created_at FROM feedback
        ORDER BY created_at DESC
        LIMIT 50
    ");
    if ($feedbackRes) {
        while ($row = $feedbackRes->fetch_assoc()) {
            $upsertNotification('feedback', (string)$row['feedback_id'], $row['created_at']);
        }
    }
}

/**
 * Returns the unread notifications count.
 */
function get_unread_notifications_count(mysqli $conn): int {
    $res = $conn->query("SELECT COUNT(*) FROM `admin_notifications` WHERE `is_read` = 0");
    if ($res) {
        return (int)$res->fetch_row()[0];
    }
    return 0;
}

/**
 * Marks a single notification as read by type and resource_id.
 */
function mark_notification_as_read(mysqli $conn, string $type, string $resourceId): bool {
    $stmt = $conn->prepare("UPDATE `admin_notifications` SET `is_read` = 1 WHERE `type` = ? AND `resource_id` = ?");
    if ($stmt) {
        $stmt->bind_param('ss', $type, $resourceId);
        $success = $stmt->execute();
        $stmt->close();
        return $success;
    }
    return false;
}

/**
 * Fetches recent notifications, with details filled from respective tables.
 */
function get_recent_notifications(mysqli $conn, int $limit = 10): array {
    $notifications = [];
    $res = $conn->query("
        SELECT id, type, resource_id, is_read, created_at
        FROM `admin_notifications`
        ORDER BY created_at DESC, id DESC
        LIMIT " . (int)$limit
    );

    if (!$res) {
        return [];
    }

    while ($row = $res->fetch_assoc()) {
        $notifId = (int)$row['id'];
        $type = $row['type'];
        $resourceId = $row['resource_id'];
        $isRead = (int)$row['is_read'];
        $createdAt = $row['created_at'];

        $details = [
            'id' => $notifId,
            'type' => $type,
            'resource_id' => $resourceId,
            'is_read' => $isRead,
            'created_at' => $createdAt,
            'title' => '',
            'description' => '',
            'url' => '',
            'data' => null
        ];

        if ($type === 'order') {
            $orderStmt = $conn->prepare("
                SELECT 
                    MAX(order_id) as order_id, 
                    MIN(email) as customer_email, 
                    MIN(mobile) as customer_phone, 
                    MIN(address) as customer_address, 
                    MIN(order_type) as order_type, 
                    MIN(status) as status, 
                    MIN(order_time) as order_time, 
                    COALESCE(SUM(total_price), 0) as total_amount, 
                    COUNT(*) as item_count 
                FROM orders 
                WHERE order_number = ?
            ");
            if ($orderStmt) {
                $orderStmt->bind_param('s', $resourceId);
                $orderStmt->execute();
                $orderRes = $orderStmt->get_result();
                if ($orderRes && $orderData = $orderRes->fetch_assoc()) {
                    if ($orderData['order_id'] !== null) {
                        $details['title'] = 'New Order ' . $resourceId;
                        $details['description'] = ($orderData['customer_email'] ?: 'Guest') . ' - ' . $orderData['item_count'] . ' items - Rs. ' . number_format((float)$orderData['total_amount'], 2);
                        $details['url'] = '/Merobhoj/admin/orders_page.php';
                        
                        // Fetch order items for the quick preview in modal popup
                        $items = [];
                        $itemStmt = $conn->prepare("
                            SELECT o.menu_name, o.quantity, o.price, o.total_price, m.menu_image
                            FROM orders o
                            LEFT JOIN menu m ON o.menu_id = m.menu_id
                            WHERE o.order_number = ?
                        ");
                        if ($itemStmt) {
                            $itemStmt->bind_param('s', $resourceId);
                            $itemStmt->execute();
                            $itemRes = $itemStmt->get_result();
                            while ($itemRow = $itemRes->fetch_assoc()) {
                                $items[] = [
                                    'menu_name' => $itemRow['menu_name'],
                                    'quantity' => (int)$itemRow['quantity'],
                                    'price' => (float)$itemRow['price'],
                                    'total_price' => (float)$itemRow['total_price'],
                                    'menu_image' => $itemRow['menu_image'] ? '../assets/img/menu/' . basename($itemRow['menu_image']) : null
                                ];
                            }
                            $itemStmt->close();
                        }

                        $details['data'] = [
                            'order_number' => $resourceId,
                            'customer' => $orderData['customer_email'],
                            'phone' => $orderData['customer_phone'],
                            'address' => $orderData['customer_address'],
                            'order_type' => $orderData['order_type'] ?: 'Delivery',
                            'total_amount' => (float)$orderData['total_amount'],
                            'order_time' => $orderData['order_time'],
                            'status' => $orderData['status'],
                            'items' => $items
                        ];
                    }
                }
                $orderStmt->close();
            }
        } elseif ($type === 'booking') {
            $bookingStmt = $conn->prepare("
                SELECT b.id, b.name, b.phone, b.booking_date, b.booking_time, b.people, b.message, b.status, b.table_id, t.table_name
                FROM bookings b
                LEFT JOIN restaurant_tables t ON t.id = b.table_id
                WHERE b.id = ?
            ");
            if ($bookingStmt) {
                $bookingStmt->bind_param('s', $resourceId);
                $bookingStmt->execute();
                $bookingRes = $bookingStmt->get_result();
                if ($bookingRes && $bookingData = $bookingRes->fetch_assoc()) {
                    $details['title'] = 'New Table Booking!';
                    $details['description'] = 'Booking from ' . ($bookingData['name'] ?: 'Guest') . ' for ' . $bookingData['people'] . ' guests';
                    $details['url'] = '/Merobhoj/admin/bookings.php';
                    $details['data'] = [
                        'booking_id' => '#TBK-' . str_pad($resourceId, 4, '0', STR_PAD_LEFT),
                        'customer' => $bookingData['name'],
                        'phone' => $bookingData['phone'],
                        'booking_date' => date('M d, Y', strtotime($bookingData['booking_date'])),
                        'booking_time' => date('h:i A', strtotime($bookingData['booking_time'])),
                        'people' => (int)$bookingData['people'],
                        'table' => $bookingData['table_name'] ?: 'Table ' . $bookingData['table_id'],
                        'message' => $bookingData['message'] ?: 'No special request',
                        'status' => $bookingData['status']
                    ];
                }
                $bookingStmt->close();
            }
        } elseif ($type === 'feedback') {
            $feedbackStmt = $conn->prepare("
                SELECT feedback_id, feedback_name, feedback_email, feedback_rating, feedback_message, created_at
                FROM feedback
                WHERE feedback_id = ?
            ");
            if ($feedbackStmt) {
                $feedbackStmt->bind_param('s', $resourceId);
                $feedbackStmt->execute();
                $feedbackRes = $feedbackStmt->get_result();
                if ($feedbackRes && $feedbackData = $feedbackRes->fetch_assoc()) {
                    $details['title'] = 'New Feedback';
                    $details['description'] = 'Feedback from ' . ($feedbackData['feedback_name'] ?: 'Guest') . ' - ' . $feedbackData['feedback_rating'] . ' Stars';
                    $details['url'] = '/Merobhoj/admin/feedback.php';
                    $details['data'] = [
                        'feedback_id' => $resourceId,
                        'customer' => $feedbackData['feedback_name'],
                        'email' => $feedbackData['feedback_email'],
                        'rating' => (int)$feedbackData['feedback_rating'],
                        'message' => $feedbackData['feedback_message'],
                        'created_at' => $feedbackData['created_at']
                    ];
                }
                $feedbackStmt->close();
            }
        }

        // Only include in results if details/record actually exist in original tables
        if (!empty($details['title'])) {
            $notifications[] = $details;
        }
    }

    return $notifications;
}
