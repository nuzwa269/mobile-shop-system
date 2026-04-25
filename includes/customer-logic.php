<?php
/**
 * Customer Logic – Mobile Shop Smart Management System
 *
 * CRUD AJAX handlers for the centralized Customer table (ms_customers).
 * Also provides customer search (for POS/Repair/Ledger) and a full
 * Customer Statement endpoint.
 *
 * Designed and Developed by Sikandar Hayat Baba | Powered by CoachPro AI
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// ─── Action Registrations ─────────────────────────────────────────────────────

add_action( 'wp_ajax_msp_get_customer_list',      'msp_ajax_get_customer_list' );
add_action( 'wp_ajax_msp_add_customer',           'msp_ajax_add_customer' );
add_action( 'wp_ajax_msp_update_customer',        'msp_ajax_update_customer' );
add_action( 'wp_ajax_msp_delete_customer',        'msp_ajax_delete_customer' );
add_action( 'wp_ajax_msp_search_customers',       'msp_ajax_search_customers' );
add_action( 'wp_ajax_msp_get_customer_statement', 'msp_ajax_get_customer_statement' );

// ─── Handlers ─────────────────────────────────────────────────────────────────

/**
 * Return the full customer list (for the Customers management tab).
 */
function msp_ajax_get_customer_list() {
    msp_check_request();
    global $wpdb;

    $search = isset( $_POST['search'] ) ? sanitize_text_field( wp_unslash( $_POST['search'] ) ) : '';

    if ( $search ) {
        $rows = $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}ms_customers
             WHERE name LIKE %s OR phone LIKE %s OR email LIKE %s
             ORDER BY name ASC LIMIT 300",
            '%' . $wpdb->esc_like( $search ) . '%',
            '%' . $wpdb->esc_like( $search ) . '%',
            '%' . $wpdb->esc_like( $search ) . '%'
        ), ARRAY_A );
    } else {
        $rows = $wpdb->get_results(
            "SELECT * FROM {$wpdb->prefix}ms_customers ORDER BY name ASC LIMIT 300",
            ARRAY_A
        );
    }

    wp_send_json_success( $rows );
}

/**
 * Add a new customer.
 */
function msp_ajax_add_customer() {
    msp_check_request();
    global $wpdb;

    $name    = sanitize_text_field( wp_unslash( $_POST['name'] ?? '' ) );
    $phone   = sanitize_text_field( wp_unslash( $_POST['phone'] ?? '' ) );
    $email   = sanitize_email( wp_unslash( $_POST['email'] ?? '' ) );
    $address = sanitize_textarea_field( wp_unslash( $_POST['address'] ?? '' ) );

    if ( empty( $name ) ) {
        wp_send_json_error( array( 'message' => 'Customer name is required.' ) );
    }
    if ( empty( $phone ) ) {
        wp_send_json_error( array( 'message' => 'Phone number is required.' ) );
    }

    // Check duplicate phone (excluding Walk-in Customer placeholder).
    $existing = $wpdb->get_var( $wpdb->prepare(
        "SELECT id FROM {$wpdb->prefix}ms_customers WHERE phone = %s",
        $phone
    ) );
    if ( $existing ) {
        wp_send_json_error( array( 'message' => 'A customer with this phone number already exists.' ) );
    }

    $result = $wpdb->insert(
        $wpdb->prefix . 'ms_customers',
        array(
            'name'    => $name,
            'phone'   => $phone,
            'email'   => $email,
            'address' => $address,
        ),
        array( '%s', '%s', '%s', '%s' )
    );

    if ( $result ) {
        wp_send_json_success( array(
            'message' => 'Customer added.',
            'id'      => $wpdb->insert_id,
            'name'    => $name,
            'phone'   => $phone,
        ) );
    } else {
        wp_send_json_error( array( 'message' => 'Database error: ' . $wpdb->last_error ) );
    }
}

/**
 * Update an existing customer.
 */
function msp_ajax_update_customer() {
    msp_check_request();
    global $wpdb;

    $id      = (int) ( $_POST['id'] ?? 0 );
    $name    = sanitize_text_field( wp_unslash( $_POST['name'] ?? '' ) );
    $phone   = sanitize_text_field( wp_unslash( $_POST['phone'] ?? '' ) );
    $email   = sanitize_email( wp_unslash( $_POST['email'] ?? '' ) );
    $address = sanitize_textarea_field( wp_unslash( $_POST['address'] ?? '' ) );

    if ( ! $id ) {
        wp_send_json_error( array( 'message' => 'Invalid customer ID.' ) );
    }
    if ( empty( $name ) ) {
        wp_send_json_error( array( 'message' => 'Customer name is required.' ) );
    }
    if ( empty( $phone ) ) {
        wp_send_json_error( array( 'message' => 'Phone number is required.' ) );
    }

    // Check duplicate phone (allow same customer's own phone).
    $existing = $wpdb->get_var( $wpdb->prepare(
        "SELECT id FROM {$wpdb->prefix}ms_customers WHERE phone = %s AND id != %d",
        $phone,
        $id
    ) );
    if ( $existing ) {
        wp_send_json_error( array( 'message' => 'Another customer already uses this phone number.' ) );
    }

    $result = $wpdb->update(
        $wpdb->prefix . 'ms_customers',
        array(
            'name'    => $name,
            'phone'   => $phone,
            'email'   => $email,
            'address' => $address,
        ),
        array( 'id' => $id ),
        array( '%s', '%s', '%s', '%s' ),
        array( '%d' )
    );

    if ( false !== $result ) {
        wp_send_json_success( array( 'message' => 'Customer updated.' ) );
    } else {
        wp_send_json_error( array( 'message' => 'Update failed.' ) );
    }
}

/**
 * Delete a customer (not allowed for Walk-in Customer).
 */
function msp_ajax_delete_customer() {
    msp_check_request();
    global $wpdb;

    $id = (int) ( $_POST['id'] ?? 0 );
    if ( ! $id ) {
        wp_send_json_error( array( 'message' => 'Invalid customer ID.' ) );
    }

    // Protect the Walk-in Customer record.
    $name = $wpdb->get_var( $wpdb->prepare(
        "SELECT name FROM {$wpdb->prefix}ms_customers WHERE id = %d",
        $id
    ) );
    if ( 'Walk-in Customer' === $name ) {
        wp_send_json_error( array( 'message' => 'The Walk-in Customer record cannot be deleted.' ) );
    }

    $wpdb->delete( $wpdb->prefix . 'ms_customers', array( 'id' => $id ), array( '%d' ) );
    wp_send_json_success( array( 'message' => 'Customer deleted.' ) );
}

/**
 * Live search – used by POS AJAX customer search and Repair quick-select.
 * Returns id, name, phone, email for matching records.
 */
function msp_ajax_search_customers() {
    msp_check_request();
    global $wpdb;

    $term = sanitize_text_field( wp_unslash( $_POST['term'] ?? '' ) );

    if ( strlen( $term ) < 1 ) {
        wp_send_json_success( array() );
    }

    $rows = $wpdb->get_results( $wpdb->prepare(
        "SELECT id, name, phone, email FROM {$wpdb->prefix}ms_customers
         WHERE name LIKE %s OR phone LIKE %s
         ORDER BY name ASC LIMIT 10",
        '%' . $wpdb->esc_like( $term ) . '%',
        '%' . $wpdb->esc_like( $term ) . '%'
    ), ARRAY_A );

    wp_send_json_success( $rows );
}

/**
 * Return combined statement: Sales + Repairs + Ledger entries for one customer.
 */
function msp_ajax_get_customer_statement() {
    msp_check_request();
    global $wpdb;

    $customer_id = (int) ( $_POST['customer_id'] ?? 0 );
    if ( ! $customer_id ) {
        wp_send_json_error( array( 'message' => 'Invalid customer ID.' ) );
    }

    $customer = $wpdb->get_row( $wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}ms_customers WHERE id = %d",
        $customer_id
    ), ARRAY_A );

    if ( ! $customer ) {
        wp_send_json_error( array( 'message' => 'Customer not found.' ) );
    }

    $sales = $wpdb->get_results( $wpdb->prepare(
        "SELECT id, net_total, payment_status, sale_date
         FROM {$wpdb->prefix}ms_pos_sales
         WHERE customer_id = %d ORDER BY sale_date DESC LIMIT 50",
        $customer_id
    ), ARRAY_A );

    $repairs = $wpdb->get_results( $wpdb->prepare(
        "SELECT job_card_number, device_model, est_cost, status, received_date
         FROM {$wpdb->prefix}ms_repair_lab
         WHERE customer_id = %d ORDER BY received_date DESC LIMIT 50",
        $customer_id
    ), ARRAY_A );

    $ledger = $wpdb->get_results( $wpdb->prepare(
        "SELECT transaction_type, amount, description, transaction_date
         FROM {$wpdb->prefix}ms_ledgers
         WHERE user_id = %d ORDER BY transaction_date DESC LIMIT 50",
        $customer_id
    ), ARRAY_A );

    // Calculate running balance (debit = money owed by customer, credit = paid).
    $balance = 0.0;
    foreach ( $ledger as $entry ) {
        if ( 'debit' === $entry['transaction_type'] ) {
            $balance += (float) $entry['amount'];
        } else {
            $balance -= (float) $entry['amount'];
        }
    }

    wp_send_json_success( array(
        'customer' => $customer,
        'sales'    => $sales,
        'repairs'  => $repairs,
        'ledger'   => $ledger,
        'balance'  => number_format( $balance, 2 ),
    ) );
}
