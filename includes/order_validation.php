<?php
// Centralized order statuses and flow definitions

const ORDER_STATUSES = ['Pending', 'Confirmed', 'Preparing', 'Ready', 'Delivering', 'Completed', 'Cancelled'];

/**
 * Validates order transitions based on order type, user role, current and target status.
 * Returns array: ['valid' => bool, 'error' => ?string]
 */
function validate_order_transition(string $order_type, string $current_status, string $new_status, bool $is_admin_or_staff): array {
    if (!in_array($new_status, ORDER_STATUSES, true)) {
        return ['valid' => false, 'error' => 'Invalid status: ' . $new_status];
    }

    if (in_array($current_status, ['Completed', 'Cancelled'], true)) {
        return ['valid' => false, 'error' => 'Completed and Cancelled orders cannot be modified.'];
    }

    if ($new_status === $current_status) {
        return ['valid' => false, 'error' => 'Status is already ' . $current_status];
    }

    // Verify order type compatibility
    if ($new_status === 'Delivering' && in_array($order_type, ['Dine In', 'Takeaway'], true)) {
        return ['valid' => false, 'error' => 'Dine-In and Takeaway orders cannot have Delivering status.'];
    }

    // Handle cancellation
    if ($new_status === 'Cancelled') {
        if ($is_admin_or_staff) {
            $allowed_to_cancel = ['Pending', 'Confirmed', 'Preparing', 'Ready', 'Delivering'];
            if (in_array($current_status, $allowed_to_cancel, true)) {
                return ['valid' => true];
            }
            return ['valid' => false, 'error' => 'Completed or Cancelled orders cannot be cancelled.'];
        } else {
            // Customer cancellation
            $allowed_to_cancel = ['Pending', 'Confirmed', 'Preparing', 'Ready'];
            if (in_array($current_status, $allowed_to_cancel, true)) {
                return ['valid' => true];
            }
            if ($current_status === 'Delivering') {
                return ['valid' => false, 'error' => 'Your order is already on the way and can no longer be cancelled.'];
            }
            return ['valid' => false, 'error' => 'This order cannot be cancelled at this stage.'];
        }
    }

    // Forward status transitions (admin/staff only)
    if (!$is_admin_or_staff) {
        return ['valid' => false, 'error' => 'Customers cannot transition orders to non-Cancelled statuses.'];
    }

    if ($order_type === 'Delivery') {
        $next_map = [
            'Pending' => 'Confirmed',
            'Confirmed' => 'Preparing',
            'Preparing' => 'Ready',
            'Ready' => 'Delivering',
            'Delivering' => 'Completed'
        ];
    } else {
        // Dine In or Takeaway
        $next_map = [
            'Pending' => 'Confirmed',
            'Confirmed' => 'Preparing',
            'Preparing' => 'Ready',
            'Ready' => 'Completed'
        ];
    }

    $expected_next = $next_map[$current_status] ?? null;
    if ($new_status === $expected_next) {
        return ['valid' => true];
    }

    return ['valid' => false, 'error' => "Cannot transition status from '$current_status' to '$new_status'."];
}
