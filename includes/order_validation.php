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

/**
 * Applies the role-specific workflow guard on top of the shared state machine.
 * Keeping this in one place prevents a UI button from becoming an API bypass.
 */
function validate_role_transition(
    string $role,
    string $order_type,
    string $current_status,
    string $new_status
): array {
    if ($role === 'chef') {
        if ($current_status === 'Confirmed' && $new_status === 'Preparing') {
            return ['valid' => true];
        }
        if ($current_status === 'Preparing' && $new_status === 'Ready') {
            return ['valid' => true];
        }
        return ['valid' => false, 'error' => 'Chef workflow only allows Start Preparing and Mark Ready.'];
    }

    if ($role === 'rider') {
        if ($current_status === 'Ready' && $new_status === 'Delivering') {
            return ['valid' => true];
        }
        if ($current_status === 'Delivering' && $new_status === 'Completed') {
            return ['valid' => true];
        }
        return ['valid' => false, 'error' => 'Riders can only pick up ready orders and mark picked-up orders delivered.'];
    }

    if ($role === 'staff' && $order_type === 'Delivery') {
        if ($current_status === 'Pending' && $new_status === 'Confirmed') {
            return ['valid' => true];
        }
        if ($current_status === 'Ready' && $new_status === 'Delivering') {
            return ['valid' => true];
        }
        if ($new_status === 'Cancelled' && in_array($current_status, ['Pending', 'Confirmed', 'Preparing', 'Ready'], true)) {
            return ['valid' => true];
        }
        return ['valid' => false, 'error' => 'Staff delivery workflow only allows Accept, Reject/Cancel, and Send Out.'];
    }

    return validate_order_transition($order_type, $current_status, $new_status, true);
}
