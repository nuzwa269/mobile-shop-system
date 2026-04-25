<?php
/**
 * AJAX Handlers – Mobile Shop Smart Management System
 *
 * All handlers are nonce-verified and capability-checked.
 * Capability: manage_options  OR  custom role msp_shop_manager.
 *
 * Designed and Developed by Sikandar Hayat Baba
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// ─── Helper ───────────────────────────────────────────────────────────────────

/**
 * Verify nonce and capability, then die with JSON error on failure.
 */
function msp_check_request( $nonce_action = 'msp_nonce' ) {
    if ( ! check_ajax_referer( $nonce_action, 'nonce', false ) ) {
        wp_send_json_error( array( 'message' => 'Security check failed.' ), 403 );
    }
    if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'msp_shop_manager' ) ) {
        wp_send_json_error( array( 'message' => 'Insufficient permissions.' ), 403 );
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// SECTION 1: DASHBOARD METRICS
// ─────────────────────────────────────────────────────────────────────────────

add_action( 'wp_ajax_msp_get_metrics', 'msp_ajax_get_metrics' );

function msp_ajax_get_metrics() {
    msp_check_request();
    global $wpdb;

    $today = current_time( 'Y-m-d' );

    // Daily sales total
    $daily_sales = (float) $wpdb->get_var( $wpdb->prepare(
        "SELECT COALESCE(SUM(net_total), 0) FROM {$wpdb->prefix}ms_pos_sales
         WHERE DATE(sale_date) = %s AND payment_status != 'credit'",
        $today
    ) );

    // Daily gross profit = sum of (price - cost_price) * quantity for today's sales
    $daily_profit = (float) $wpdb->get_var( $wpdb->prepare(
        "SELECT COALESCE(SUM((pi.price - inv.cost_price) * pi.quantity), 0)
         FROM {$wpdb->prefix}ms_pos_items pi
         JOIN {$wpdb->prefix}ms_pos_sales ps ON ps.id = pi.sale_id
         JOIN {$wpdb->prefix}ms_inventory inv ON inv.id = pi.product_id
         WHERE DATE(ps.sale_date) = %s",
        $today
    ) );

    // Pending (credit) payments total
    $pending_payments = (float) $wpdb->get_var(
        "SELECT COALESCE(SUM(net_total), 0) FROM {$wpdb->prefix}ms_pos_sales
         WHERE payment_status = 'credit'"
    );

    // Low stock items (quantity <= 5)
    $low_stock = (int) $wpdb->get_var(
        "SELECT COUNT(*) FROM {$wpdb->prefix}ms_inventory WHERE stock_quantity <= 5"
    );

    // Total products
    $total_products = (int) $wpdb->get_var(
        "SELECT COUNT(*) FROM {$wpdb->prefix}ms_inventory"
    );

    // Open repair jobs
    $open_repairs = (int) $wpdb->get_var(
        "SELECT COUNT(*) FROM {$wpdb->prefix}ms_repair_lab
         WHERE status IN ('pending','repairing')"
    );

    // Monthly revenue (current month)
    $monthly_revenue = (float) $wpdb->get_var( $wpdb->prepare(
        "SELECT COALESCE(SUM(net_total), 0) FROM {$wpdb->prefix}ms_pos_sales
         WHERE MONTH(sale_date) = %d AND YEAR(sale_date) = %d",
        current_time( 'n' ),
        current_time( 'Y' )
    ) );

    // Recent 5 sales
    $recent_sales = $wpdb->get_results(
        "SELECT ps.id, ps.net_total, ps.payment_status, ps.sale_date,
                COALESCE(u.display_name, 'Walk-in') AS customer_name
         FROM {$wpdb->prefix}ms_pos_sales ps
         LEFT JOIN {$wpdb->users} u ON u.ID = ps.customer_id
         ORDER BY ps.sale_date DESC LIMIT 5",
        ARRAY_A
    );

    wp_send_json_success( array(
        'daily_sales'      => number_format( $daily_sales, 2 ),
        'daily_profit'     => number_format( $daily_profit, 2 ),
        'pending_payments' => number_format( $pending_payments, 2 ),
        'low_stock'        => $low_stock,
        'total_products'   => $total_products,
        'open_repairs'     => $open_repairs,
        'monthly_revenue'  => number_format( $monthly_revenue, 2 ),
        'recent_sales'     => $recent_sales,
    ) );
}

// ─────────────────────────────────────────────────────────────────────────────
// SECTION 2: INVENTORY CRUD
// ─────────────────────────────────────────────────────────────────────────────

add_action( 'wp_ajax_msp_get_inventory',    'msp_ajax_get_inventory' );
add_action( 'wp_ajax_msp_add_product',      'msp_ajax_add_product' );
add_action( 'wp_ajax_msp_update_product',   'msp_ajax_update_product' );
add_action( 'wp_ajax_msp_delete_product',   'msp_ajax_delete_product' );
add_action( 'wp_ajax_msp_add_imei',         'msp_ajax_add_imei' );
add_action( 'wp_ajax_msp_get_imei_list',    'msp_ajax_get_imei_list' );

function msp_ajax_get_inventory() {
    msp_check_request();
    global $wpdb;

    $search   = isset( $_POST['search'] ) ? sanitize_text_field( wp_unslash( $_POST['search'] ) ) : '';
    $category = isset( $_POST['category'] ) ? sanitize_text_field( wp_unslash( $_POST['category'] ) ) : '';

    $where  = 'WHERE 1=1';
    $params = array();

    if ( $search ) {
        $where   .= ' AND product_name LIKE %s';
        $params[] = '%' . $wpdb->esc_like( $search ) . '%';
    }
    if ( $category ) {
        $where   .= ' AND category = %s';
        $params[] = $category;
    }

    if ( $params ) {
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $query = $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}ms_inventory $where ORDER BY id DESC LIMIT 200", ...$params );
    } else {
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $query = "SELECT * FROM {$wpdb->prefix}ms_inventory $where ORDER BY id DESC LIMIT 200";
    }

    // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
    $rows = $wpdb->get_results( $query, ARRAY_A );
    wp_send_json_success( $rows );
}

function msp_ajax_add_product() {
    msp_check_request();
    global $wpdb;

    $data = array(
        'product_name'  => sanitize_text_field( wp_unslash( $_POST['product_name'] ?? '' ) ),
        'category'      => sanitize_text_field( wp_unslash( $_POST['category'] ?? 'mobile' ) ),
        'variant'       => sanitize_text_field( wp_unslash( $_POST['variant'] ?? '' ) ),
        'cost_price'    => (float) ( $_POST['cost_price'] ?? 0 ),
        'selling_price' => (float) ( $_POST['selling_price'] ?? 0 ),
        'stock_quantity' => (int) ( $_POST['stock_quantity'] ?? 0 ),
    );

    if ( empty( $data['product_name'] ) ) {
        wp_send_json_error( array( 'message' => 'Product name is required.' ) );
    }

    $allowed_categories = array( 'mobile', 'accessory', 'part' );
    if ( ! in_array( $data['category'], $allowed_categories, true ) ) {
        $data['category'] = 'mobile';
    }

    $result = $wpdb->insert(
        $wpdb->prefix . 'ms_inventory',
        $data,
        array( '%s', '%s', '%s', '%f', '%f', '%d' )
    );

    if ( $result ) {
        wp_send_json_success( array( 'message' => 'Product added.', 'id' => $wpdb->insert_id ) );
    } else {
        wp_send_json_error( array( 'message' => 'Database error: ' . $wpdb->last_error ) );
    }
}

function msp_ajax_update_product() {
    msp_check_request();
    global $wpdb;

    $id = (int) ( $_POST['id'] ?? 0 );
    if ( ! $id ) {
        wp_send_json_error( array( 'message' => 'Invalid product ID.' ) );
    }

    $data = array(
        'product_name'   => sanitize_text_field( wp_unslash( $_POST['product_name'] ?? '' ) ),
        'category'       => sanitize_text_field( wp_unslash( $_POST['category'] ?? 'mobile' ) ),
        'variant'        => sanitize_text_field( wp_unslash( $_POST['variant'] ?? '' ) ),
        'cost_price'     => (float) ( $_POST['cost_price'] ?? 0 ),
        'selling_price'  => (float) ( $_POST['selling_price'] ?? 0 ),
        'stock_quantity' => (int) ( $_POST['stock_quantity'] ?? 0 ),
    );

    $allowed_categories = array( 'mobile', 'accessory', 'part' );
    if ( ! in_array( $data['category'], $allowed_categories, true ) ) {
        $data['category'] = 'mobile';
    }

    $result = $wpdb->update(
        $wpdb->prefix . 'ms_inventory',
        $data,
        array( 'id' => $id ),
        array( '%s', '%s', '%s', '%f', '%f', '%d' ),
        array( '%d' )
    );

    if ( false !== $result ) {
        wp_send_json_success( array( 'message' => 'Product updated.' ) );
    } else {
        wp_send_json_error( array( 'message' => 'Update failed.' ) );
    }
}

function msp_ajax_delete_product() {
    msp_check_request();
    global $wpdb;

    $id = (int) ( $_POST['id'] ?? 0 );
    if ( ! $id ) {
        wp_send_json_error( array( 'message' => 'Invalid product ID.' ) );
    }

    $wpdb->delete( $wpdb->prefix . 'ms_inventory', array( 'id' => $id ), array( '%d' ) );
    wp_send_json_success( array( 'message' => 'Product deleted.' ) );
}

function msp_ajax_add_imei() {
    msp_check_request();
    global $wpdb;

    $product_id  = (int) ( $_POST['product_id'] ?? 0 );
    $imei_serial = sanitize_text_field( wp_unslash( $_POST['imei_serial'] ?? '' ) );
    $supplier_id = (int) ( $_POST['supplier_id'] ?? 0 );

    if ( ! $product_id || empty( $imei_serial ) ) {
        wp_send_json_error( array( 'message' => 'Product ID and IMEI/Serial are required.' ) );
    }

    $result = $wpdb->insert(
        $wpdb->prefix . 'ms_imei_tracking',
        array(
            'product_id'  => $product_id,
            'imei_serial' => $imei_serial,
            'status'      => 'in_stock',
            'supplier_id' => $supplier_id ?: null,
        ),
        array( '%d', '%s', '%s', '%d' )
    );

    if ( $result ) {
        // Increment stock
        $wpdb->query( $wpdb->prepare(
            "UPDATE {$wpdb->prefix}ms_inventory SET stock_quantity = stock_quantity + 1 WHERE id = %d",
            $product_id
        ) );
        wp_send_json_success( array( 'message' => 'IMEI/Serial added.', 'id' => $wpdb->insert_id ) );
    } else {
        $err = $wpdb->last_error;
        if ( strpos( $err, 'Duplicate' ) !== false ) {
            wp_send_json_error( array( 'message' => 'This IMEI/Serial already exists.' ) );
        }
        wp_send_json_error( array( 'message' => 'Database error.' ) );
    }
}

function msp_ajax_get_imei_list() {
    msp_check_request();
    global $wpdb;

    $product_id = (int) ( $_POST['product_id'] ?? 0 );
    if ( ! $product_id ) {
        wp_send_json_error( array( 'message' => 'Invalid product ID.' ) );
    }

    $rows = $wpdb->get_results( $wpdb->prepare(
        "SELECT id, imei_serial, status FROM {$wpdb->prefix}ms_imei_tracking
         WHERE product_id = %d ORDER BY id DESC",
        $product_id
    ), ARRAY_A );

    wp_send_json_success( $rows );
}

// ─────────────────────────────────────────────────────────────────────────────
// SECTION 3: POS – PRODUCT LOOKUP & CHECKOUT
// ─────────────────────────────────────────────────────────────────────────────

add_action( 'wp_ajax_msp_pos_lookup',   'msp_ajax_pos_lookup' );
add_action( 'wp_ajax_msp_pos_checkout', 'msp_ajax_pos_checkout' );
add_action( 'wp_ajax_msp_get_sales',    'msp_ajax_get_sales' );

/**
 * Look up a product by IMEI/Serial or product name for the POS cart.
 */
function msp_ajax_pos_lookup() {
    msp_check_request();
    global $wpdb;

    $term = sanitize_text_field( wp_unslash( $_POST['term'] ?? '' ) );

    if ( empty( $term ) ) {
        wp_send_json_error( array( 'message' => 'Search term required.' ) );
    }

    // Try IMEI/Serial first (exact match)
    $imei_row = $wpdb->get_row( $wpdb->prepare(
        "SELECT it.id AS imei_id, it.imei_serial, it.status,
                inv.id AS product_id, inv.product_name, inv.variant,
                inv.selling_price, inv.cost_price
         FROM {$wpdb->prefix}ms_imei_tracking it
         JOIN {$wpdb->prefix}ms_inventory inv ON inv.id = it.product_id
         WHERE it.imei_serial = %s AND it.status = 'in_stock'",
        $term
    ), ARRAY_A );

    if ( $imei_row ) {
        wp_send_json_success( array( 'type' => 'imei', 'item' => $imei_row ) );
    }

    // Fall back to product name search
    $products = $wpdb->get_results( $wpdb->prepare(
        "SELECT id AS product_id, product_name, variant, selling_price, cost_price, stock_quantity
         FROM {$wpdb->prefix}ms_inventory
         WHERE product_name LIKE %s AND stock_quantity > 0
         ORDER BY product_name LIMIT 10",
        '%' . $wpdb->esc_like( $term ) . '%'
    ), ARRAY_A );

    wp_send_json_success( array( 'type' => 'product', 'items' => $products ) );
}

/**
 * Process POS checkout: deduct IMEI, record sale, adjust inventory.
 */
function msp_ajax_pos_checkout() {
    msp_check_request();
    global $wpdb;

    $cart_items     = isset( $_POST['cart_items'] ) ? (array) $_POST['cart_items'] : array();
    $customer_id    = (int) ( $_POST['customer_id'] ?? 0 );
    $discount       = (float) ( $_POST['discount'] ?? 0 );
    $payment_status = sanitize_text_field( wp_unslash( $_POST['payment_status'] ?? 'paid' ) );

    $allowed_payment = array( 'paid', 'credit', 'partial' );
    if ( ! in_array( $payment_status, $allowed_payment, true ) ) {
        $payment_status = 'paid';
    }

    if ( empty( $cart_items ) ) {
        wp_send_json_error( array( 'message' => 'Cart is empty.' ) );
    }

    $wpdb->query( 'START TRANSACTION' );

    try {
        $total_amount = 0.0;

        // Validate each cart item before inserting.
        $validated_items = array();
        foreach ( $cart_items as $item ) {
            $product_id = (int) ( $item['product_id'] ?? 0 );
            $imei_id    = isset( $item['imei_id'] ) ? (int) $item['imei_id'] : null;
            $quantity   = (int) ( $item['quantity'] ?? 1 );
            $price      = (float) ( $item['price'] ?? 0 );

            if ( ! $product_id || $quantity < 1 || $price < 0 ) {
                $wpdb->query( 'ROLLBACK' );
                wp_send_json_error( array( 'message' => 'Invalid cart item data.' ) );
            }

            // If IMEI-tracked, verify it's still in_stock.
            if ( $imei_id ) {
                $imei_status = $wpdb->get_var( $wpdb->prepare(
                    "SELECT status FROM {$wpdb->prefix}ms_imei_tracking WHERE id = %d",
                    $imei_id
                ) );
                if ( 'in_stock' !== $imei_status ) {
                    $wpdb->query( 'ROLLBACK' );
                    wp_send_json_error( array( 'message' => "IMEI ID $imei_id is not available." ) );
                }
            } else {
                // Non-IMEI: check stock_quantity.
                $stock = (int) $wpdb->get_var( $wpdb->prepare(
                    "SELECT stock_quantity FROM {$wpdb->prefix}ms_inventory WHERE id = %d",
                    $product_id
                ) );
                if ( $stock < $quantity ) {
                    $wpdb->query( 'ROLLBACK' );
                    wp_send_json_error( array( 'message' => "Insufficient stock for product ID $product_id." ) );
                }
            }

            $total_amount       += $price * $quantity;
            $validated_items[]   = compact( 'product_id', 'imei_id', 'quantity', 'price' );
        }

        $net_total = max( 0, $total_amount - $discount );

        // Insert sale record.
        $wpdb->insert(
            $wpdb->prefix . 'ms_pos_sales',
            array(
                'customer_id'    => $customer_id ?: null,
                'total_amount'   => $total_amount,
                'discount'       => $discount,
                'net_total'      => $net_total,
                'payment_status' => $payment_status,
            ),
            array( '%d', '%f', '%f', '%f', '%s' )
        );
        $sale_id = $wpdb->insert_id;

        foreach ( $validated_items as $item ) {
            // Insert sale line item.
            $wpdb->insert(
                $wpdb->prefix . 'ms_pos_items',
                array(
                    'sale_id'    => $sale_id,
                    'product_id' => $item['product_id'],
                    'imei_id'    => $item['imei_id'],
                    'quantity'   => $item['quantity'],
                    'price'      => $item['price'],
                ),
                array( '%d', '%d', '%d', '%d', '%f' )
            );

            if ( $item['imei_id'] ) {
                // Mark IMEI as sold.
                $wpdb->update(
                    $wpdb->prefix . 'ms_imei_tracking',
                    array( 'status' => 'sold' ),
                    array( 'id'     => $item['imei_id'] ),
                    array( '%s' ),
                    array( '%d' )
                );
            }

            // Deduct inventory stock.
            $wpdb->query( $wpdb->prepare(
                "UPDATE {$wpdb->prefix}ms_inventory
                 SET stock_quantity = GREATEST(0, stock_quantity - %d)
                 WHERE id = %d",
                $item['quantity'],
                $item['product_id']
            ) );
        }

        // If credit sale, add ledger debit entry for the customer.
        if ( $customer_id && $payment_status === 'credit' ) {
            $wpdb->insert(
                $wpdb->prefix . 'ms_ledgers',
                array(
                    'user_id'          => $customer_id,
                    'transaction_type' => 'debit',
                    'amount'           => $net_total,
                    'description'      => "Credit sale #$sale_id",
                ),
                array( '%d', '%s', '%f', '%s' )
            );
        }

        $wpdb->query( 'COMMIT' );

        // Build receipt data.
        $receipt = msp_build_receipt( $sale_id, $validated_items, $total_amount, $discount, $net_total, $payment_status );

        wp_send_json_success( array(
            'message' => 'Sale completed.',
            'sale_id' => $sale_id,
            'receipt' => $receipt,
        ) );

    } catch ( Exception $e ) {
        $wpdb->query( 'ROLLBACK' );
        wp_send_json_error( array( 'message' => 'Transaction failed: ' . $e->getMessage() ) );
    }
}

/**
 * Build printable thermal receipt HTML.
 */
function msp_build_receipt( $sale_id, $items, $total, $discount, $net_total, $payment_status ) {
    global $wpdb;

    $lines = '';
    foreach ( $items as $item ) {
        $product_name = $wpdb->get_var( $wpdb->prepare(
            "SELECT product_name FROM {$wpdb->prefix}ms_inventory WHERE id = %d",
            $item['product_id']
        ) );
        $line_total = $item['price'] * $item['quantity'];
        $lines .= '<tr>
            <td>' . esc_html( $product_name ) . '</td>
            <td style="text-align:center">' . esc_html( $item['quantity'] ) . '</td>
            <td style="text-align:right">' . number_format( $item['price'], 2 ) . '</td>
            <td style="text-align:right">' . number_format( $line_total, 2 ) . '</td>
        </tr>';
    }

    $site_name = get_bloginfo( 'name' );
    $date_now  = current_time( 'Y-m-d H:i:s' );

    $html = '<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Receipt #' . esc_html( $sale_id ) . '</title>
<style>
  body{font-family:monospace;font-size:12px;width:300px;margin:0 auto;padding:10px}
  h2,p{text-align:center;margin:4px 0}
  table{width:100%;border-collapse:collapse}
  th,td{padding:3px 4px;font-size:11px}
  th{border-bottom:1px dashed #000}
  .totals td{font-weight:bold}
  .footer{text-align:center;margin-top:10px;font-size:10px;border-top:1px dashed #000;padding-top:6px}
</style>
</head>
<body>
<h2>' . esc_html( $site_name ) . '</h2>
<p>Receipt #' . esc_html( $sale_id ) . '</p>
<p>' . esc_html( $date_now ) . '</p>
<hr>
<table>
  <thead><tr><th>Item</th><th>Qty</th><th>Price</th><th>Total</th></tr></thead>
  <tbody>' . $lines . '</tbody>
</table>
<hr>
<table>
  <tr><td>Subtotal</td><td style="text-align:right">' . number_format( $total, 2 ) . '</td></tr>
  <tr><td>Discount</td><td style="text-align:right">-' . number_format( $discount, 2 ) . '</td></tr>
  <tr class="totals"><td>Net Total</td><td style="text-align:right">' . number_format( $net_total, 2 ) . '</td></tr>
  <tr><td>Payment</td><td style="text-align:right">' . esc_html( strtoupper( $payment_status ) ) . '</td></tr>
</table>
<div class="footer">
  <p>Thank you for your business!</p>
  <p style="font-size:9px">Designed and Developed by Sikandar Hayat Baba</p>
</div>
</body></html>';

    return $html;
}

function msp_ajax_get_sales() {
    msp_check_request();
    global $wpdb;

    $rows = $wpdb->get_results(
        "SELECT ps.id, ps.total_amount, ps.discount, ps.net_total, ps.payment_status, ps.sale_date,
                COALESCE(u.display_name, 'Walk-in') AS customer_name
         FROM {$wpdb->prefix}ms_pos_sales ps
         LEFT JOIN {$wpdb->users} u ON u.ID = ps.customer_id
         ORDER BY ps.sale_date DESC LIMIT 100",
        ARRAY_A
    );

    wp_send_json_success( $rows );
}

// ─────────────────────────────────────────────────────────────────────────────
// SECTION 4: REPAIR LAB CRUD
// ─────────────────────────────────────────────────────────────────────────────

add_action( 'wp_ajax_msp_get_repairs',    'msp_ajax_get_repairs' );
add_action( 'wp_ajax_msp_add_repair',     'msp_ajax_add_repair' );
add_action( 'wp_ajax_msp_update_repair',  'msp_ajax_update_repair' );
add_action( 'wp_ajax_msp_delete_repair',  'msp_ajax_delete_repair' );

function msp_ajax_get_repairs() {
    msp_check_request();
    global $wpdb;

    $status = sanitize_text_field( wp_unslash( $_POST['status'] ?? '' ) );
    $where  = 'WHERE 1=1';
    $params = array();

    if ( $status ) {
        $allowed_statuses = array( 'pending', 'repairing', 'fixed', 'unrepairable' );
        if ( in_array( $status, $allowed_statuses, true ) ) {
            $where   .= ' AND rl.status = %s';
            $params[] = $status;
        }
    }

    if ( $params ) {
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $sql = $wpdb->prepare(
            "SELECT rl.*, COALESCE(u.display_name, 'N/A') AS customer_name
             FROM {$wpdb->prefix}ms_repair_lab rl
             LEFT JOIN {$wpdb->users} u ON u.ID = rl.customer_id
             $where ORDER BY rl.received_date DESC LIMIT 200",
            ...$params
        );
    } else {
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $sql = "SELECT rl.*, COALESCE(u.display_name, 'N/A') AS customer_name
                FROM {$wpdb->prefix}ms_repair_lab rl
                LEFT JOIN {$wpdb->users} u ON u.ID = rl.customer_id
                $where ORDER BY rl.received_date DESC LIMIT 200";
    }

    // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
    $rows = $wpdb->get_results( $sql, ARRAY_A );

    wp_send_json_success( $rows );
}

function msp_ajax_add_repair() {
    msp_check_request();
    global $wpdb;

    $customer_id = (int) ( $_POST['customer_id'] ?? 0 );

    // Auto-generate job card number: JC-YYYYMMDD-{5 digit counter suffix}
    $job_card = 'JC-' . current_time( 'Ymd' ) . '-' . str_pad( wp_rand( 1, 99999 ), 5, '0', STR_PAD_LEFT );

    $data = array(
        'job_card_number' => $job_card,
        'customer_id'     => $customer_id ?: null,
        'device_model'    => sanitize_text_field( wp_unslash( $_POST['device_model'] ?? '' ) ),
        'issue_desc'      => sanitize_textarea_field( wp_unslash( $_POST['issue_desc'] ?? '' ) ),
        'est_cost'        => (float) ( $_POST['est_cost'] ?? 0 ),
        'status'          => 'pending',
    );

    if ( empty( $data['device_model'] ) ) {
        wp_send_json_error( array( 'message' => 'Device model is required.' ) );
    }

    $result = $wpdb->insert(
        $wpdb->prefix . 'ms_repair_lab',
        $data,
        array( '%s', '%d', '%s', '%s', '%f', '%s' )
    );

    if ( $result ) {
        wp_send_json_success( array(
            'message'         => 'Repair job created.',
            'id'              => $wpdb->insert_id,
            'job_card_number' => $job_card,
        ) );
    } else {
        wp_send_json_error( array( 'message' => 'Database error.' ) );
    }
}

function msp_ajax_update_repair() {
    msp_check_request();
    global $wpdb;

    $id = (int) ( $_POST['id'] ?? 0 );
    if ( ! $id ) {
        wp_send_json_error( array( 'message' => 'Invalid repair ID.' ) );
    }

    $allowed_statuses = array( 'pending', 'repairing', 'fixed', 'unrepairable' );
    $status = sanitize_text_field( wp_unslash( $_POST['status'] ?? 'pending' ) );
    if ( ! in_array( $status, $allowed_statuses, true ) ) {
        $status = 'pending';
    }

    $data = array(
        'device_model' => sanitize_text_field( wp_unslash( $_POST['device_model'] ?? '' ) ),
        'issue_desc'   => sanitize_textarea_field( wp_unslash( $_POST['issue_desc'] ?? '' ) ),
        'est_cost'     => (float) ( $_POST['est_cost'] ?? 0 ),
        'status'       => $status,
    );

    $result = $wpdb->update(
        $wpdb->prefix . 'ms_repair_lab',
        $data,
        array( 'id' => $id ),
        array( '%s', '%s', '%f', '%s' ),
        array( '%d' )
    );

    if ( false !== $result ) {
        wp_send_json_success( array( 'message' => 'Repair job updated.' ) );
    } else {
        wp_send_json_error( array( 'message' => 'Update failed.' ) );
    }
}

function msp_ajax_delete_repair() {
    msp_check_request();
    global $wpdb;

    $id = (int) ( $_POST['id'] ?? 0 );
    if ( ! $id ) {
        wp_send_json_error( array( 'message' => 'Invalid ID.' ) );
    }

    $wpdb->delete( $wpdb->prefix . 'ms_repair_lab', array( 'id' => $id ), array( '%d' ) );
    wp_send_json_success( array( 'message' => 'Repair job deleted.' ) );
}

// ─────────────────────────────────────────────────────────────────────────────
// SECTION 5: CRM / LEDGER
// ─────────────────────────────────────────────────────────────────────────────

add_action( 'wp_ajax_msp_get_ledger',    'msp_ajax_get_ledger' );
add_action( 'wp_ajax_msp_add_ledger',    'msp_ajax_add_ledger' );
add_action( 'wp_ajax_msp_get_customers', 'msp_ajax_get_customers' );

function msp_ajax_get_customers() {
    msp_check_request();

    $users = get_users( array(
        'fields'  => array( 'ID', 'display_name', 'user_email' ),
        'orderby' => 'display_name',
        'order'   => 'ASC',
        'number'  => 200,
    ) );

    $result = array();
    foreach ( $users as $user ) {
        $result[] = array(
            'id'    => $user->ID,
            'name'  => $user->display_name,
            'email' => $user->user_email,
        );
    }

    wp_send_json_success( $result );
}

function msp_ajax_get_ledger() {
    msp_check_request();
    global $wpdb;

    $user_id = (int) ( $_POST['user_id'] ?? 0 );
    $where   = 'WHERE 1=1';
    $params  = array();

    if ( $user_id ) {
        $where   .= ' AND l.user_id = %d';
        $params[] = $user_id;
    }

    if ( $params ) {
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $sql = $wpdb->prepare(
            "SELECT l.*, COALESCE(u.display_name, 'Unknown') AS user_name
             FROM {$wpdb->prefix}ms_ledgers l
             LEFT JOIN {$wpdb->users} u ON u.ID = l.user_id
             $where ORDER BY l.transaction_date DESC LIMIT 200",
            ...$params
        );
    } else {
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $sql = "SELECT l.*, COALESCE(u.display_name, 'Unknown') AS user_name
                FROM {$wpdb->prefix}ms_ledgers l
                LEFT JOIN {$wpdb->users} u ON u.ID = l.user_id
                $where ORDER BY l.transaction_date DESC LIMIT 200";
    }

    // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
    $rows = $wpdb->get_results( $sql, ARRAY_A );

    // Calculate balance per user
    $balance = 0;
    if ( $user_id && ! empty( $rows ) ) {
        foreach ( $rows as $row ) {
            if ( $row['transaction_type'] === 'debit' ) {
                $balance += (float) $row['amount'];
            } else {
                $balance -= (float) $row['amount'];
            }
        }
    }

    wp_send_json_success( array( 'rows' => $rows, 'balance' => number_format( $balance, 2 ) ) );
}

function msp_ajax_add_ledger() {
    msp_check_request();
    global $wpdb;

    $user_id = (int) ( $_POST['user_id'] ?? 0 );
    $type    = sanitize_text_field( wp_unslash( $_POST['transaction_type'] ?? 'credit' ) );
    $amount  = (float) ( $_POST['amount'] ?? 0 );
    $desc    = sanitize_textarea_field( wp_unslash( $_POST['description'] ?? '' ) );

    $allowed_types = array( 'credit', 'debit' );
    if ( ! in_array( $type, $allowed_types, true ) ) {
        $type = 'credit';
    }

    if ( ! $user_id || $amount <= 0 ) {
        wp_send_json_error( array( 'message' => 'User and a positive amount are required.' ) );
    }

    $result = $wpdb->insert(
        $wpdb->prefix . 'ms_ledgers',
        array(
            'user_id'          => $user_id,
            'transaction_type' => $type,
            'amount'           => $amount,
            'description'      => $desc,
        ),
        array( '%d', '%s', '%f', '%s' )
    );

    if ( $result ) {
        wp_send_json_success( array( 'message' => 'Ledger entry added.' ) );
    } else {
        wp_send_json_error( array( 'message' => 'Database error.' ) );
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// SECTION 6: EXPENSES
// ─────────────────────────────────────────────────────────────────────────────

add_action( 'wp_ajax_msp_get_expenses',  'msp_ajax_get_expenses' );
add_action( 'wp_ajax_msp_add_expense',   'msp_ajax_add_expense' );
add_action( 'wp_ajax_msp_delete_expense', 'msp_ajax_delete_expense' );

function msp_ajax_get_expenses() {
    msp_check_request();
    global $wpdb;

    $rows = $wpdb->get_results(
        "SELECT * FROM {$wpdb->prefix}ms_expenses ORDER BY expense_date DESC LIMIT 200",
        ARRAY_A
    );
    wp_send_json_success( $rows );
}

function msp_ajax_add_expense() {
    msp_check_request();
    global $wpdb;

    $allowed_types = array( 'rent', 'bill', 'salary', 'misc' );
    $type          = sanitize_text_field( wp_unslash( $_POST['expense_type'] ?? 'misc' ) );
    if ( ! in_array( $type, $allowed_types, true ) ) {
        $type = 'misc';
    }

    $amount = (float) ( $_POST['amount'] ?? 0 );
    if ( $amount <= 0 ) {
        wp_send_json_error( array( 'message' => 'Amount must be positive.' ) );
    }

    $result = $wpdb->insert(
        $wpdb->prefix . 'ms_expenses',
        array(
            'expense_type' => $type,
            'amount'       => $amount,
            'description'  => sanitize_textarea_field( wp_unslash( $_POST['description'] ?? '' ) ),
        ),
        array( '%s', '%f', '%s' )
    );

    if ( $result ) {
        wp_send_json_success( array( 'message' => 'Expense logged.', 'id' => $wpdb->insert_id ) );
    } else {
        wp_send_json_error( array( 'message' => 'Database error.' ) );
    }
}

function msp_ajax_delete_expense() {
    msp_check_request();
    global $wpdb;

    $id = (int) ( $_POST['id'] ?? 0 );
    if ( ! $id ) {
        wp_send_json_error( array( 'message' => 'Invalid ID.' ) );
    }

    $wpdb->delete( $wpdb->prefix . 'ms_expenses', array( 'id' => $id ), array( '%d' ) );
    wp_send_json_success( array( 'message' => 'Expense deleted.' ) );
}

// ─────────────────────────────────────────────────────────────────────────────
// SECTION 7: REPORTS
// ─────────────────────────────────────────────────────────────────────────────

add_action( 'wp_ajax_msp_get_report', 'msp_ajax_get_report' );

function msp_ajax_get_report() {
    msp_check_request();
    global $wpdb;

    $date_from = sanitize_text_field( wp_unslash( $_POST['date_from'] ?? '' ) );
    $date_to   = sanitize_text_field( wp_unslash( $_POST['date_to'] ?? '' ) );

    if ( empty( $date_from ) ) {
        $date_from = current_time( 'Y-m-01' );   // First day of month.
    }
    if ( empty( $date_to ) ) {
        $date_to = current_time( 'Y-m-d' );
    }

    // Validate date format.
    $date_from_obj = DateTime::createFromFormat( 'Y-m-d', $date_from );
    $date_to_obj   = DateTime::createFromFormat( 'Y-m-d', $date_to );
    if ( ! $date_from_obj || ! $date_to_obj ) {
        wp_send_json_error( array( 'message' => 'Invalid date format. Use YYYY-MM-DD (e.g. 2024-01-15).' ) );
    }

    // Total sales and discount in period.
    $sales_summary = $wpdb->get_row( $wpdb->prepare(
        "SELECT COALESCE(SUM(net_total),0) AS total_revenue,
                COALESCE(SUM(discount),0) AS total_discount,
                COUNT(*) AS total_sales
         FROM {$wpdb->prefix}ms_pos_sales
         WHERE DATE(sale_date) BETWEEN %s AND %s",
        $date_from,
        $date_to
    ), ARRAY_A );

    // Gross profit.
    $gross_profit = (float) $wpdb->get_var( $wpdb->prepare(
        "SELECT COALESCE(SUM((pi.price - inv.cost_price) * pi.quantity), 0)
         FROM {$wpdb->prefix}ms_pos_items pi
         JOIN {$wpdb->prefix}ms_pos_sales ps ON ps.id = pi.sale_id
         JOIN {$wpdb->prefix}ms_inventory inv ON inv.id = pi.product_id
         WHERE DATE(ps.sale_date) BETWEEN %s AND %s",
        $date_from,
        $date_to
    ) );

    // Total expenses in period.
    $total_expenses = (float) $wpdb->get_var( $wpdb->prepare(
        "SELECT COALESCE(SUM(amount),0) FROM {$wpdb->prefix}ms_expenses
         WHERE DATE(expense_date) BETWEEN %s AND %s",
        $date_from,
        $date_to
    ) );

    // Net profit after expenses.
    $net_profit = $gross_profit - $total_expenses;

    // Sales by category.
    $category_breakdown = $wpdb->get_results( $wpdb->prepare(
        "SELECT inv.category, COUNT(*) AS items_sold,
                COALESCE(SUM(pi.price * pi.quantity),0) AS revenue
         FROM {$wpdb->prefix}ms_pos_items pi
         JOIN {$wpdb->prefix}ms_pos_sales ps ON ps.id = pi.sale_id
         JOIN {$wpdb->prefix}ms_inventory inv ON inv.id = pi.product_id
         WHERE DATE(ps.sale_date) BETWEEN %s AND %s
         GROUP BY inv.category",
        $date_from,
        $date_to
    ), ARRAY_A );

    // Daily sales chart data.
    $daily_chart = $wpdb->get_results( $wpdb->prepare(
        "SELECT DATE(sale_date) AS day, COALESCE(SUM(net_total),0) AS revenue, COUNT(*) AS sales
         FROM {$wpdb->prefix}ms_pos_sales
         WHERE DATE(sale_date) BETWEEN %s AND %s
         GROUP BY DATE(sale_date)
         ORDER BY day ASC",
        $date_from,
        $date_to
    ), ARRAY_A );

    // Expenses by type.
    $expense_breakdown = $wpdb->get_results( $wpdb->prepare(
        "SELECT expense_type, COALESCE(SUM(amount),0) AS total
         FROM {$wpdb->prefix}ms_expenses
         WHERE DATE(expense_date) BETWEEN %s AND %s
         GROUP BY expense_type",
        $date_from,
        $date_to
    ), ARRAY_A );

    wp_send_json_success( array(
        'period'             => array( 'from' => $date_from, 'to' => $date_to ),
        'sales_summary'      => $sales_summary,
        'gross_profit'       => number_format( $gross_profit, 2 ),
        'total_expenses'     => number_format( $total_expenses, 2 ),
        'net_profit'         => number_format( $net_profit, 2 ),
        'category_breakdown' => $category_breakdown,
        'daily_chart'        => $daily_chart,
        'expense_breakdown'  => $expense_breakdown,
    ) );
}
