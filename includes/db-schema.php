<?php
/**
 * DB Schema – Mobile Shop Smart Management System
 *
 * Creates all 7 custom tables on activation using dbDelta and also
 * programmatically creates the "Mobile Shop Dashboard" WordPress page.
 *
 * Designed and Developed by Sikandar Hayat Baba
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Create all custom database tables.
 */
function msp_create_tables() {
    global $wpdb;
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';

    $charset_collate = $wpdb->get_charset_collate();

    // ── 1. Inventory ─────────────────────────────────────────────────────────
    $sql_inventory = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}ms_inventory (
        id            BIGINT(20)     UNSIGNED NOT NULL AUTO_INCREMENT,
        product_name  VARCHAR(255)   NOT NULL,
        category      ENUM('mobile','accessory','part') NOT NULL DEFAULT 'mobile',
        variant       VARCHAR(255)   DEFAULT NULL COMMENT 'e.g. 128GB/Black (storage/color)',
        cost_price    DECIMAL(12,2)  NOT NULL DEFAULT 0.00,
        selling_price DECIMAL(12,2)  NOT NULL DEFAULT 0.00,
        stock_quantity INT(11)        NOT NULL DEFAULT 0,
        created_at    DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY category (category)
    ) $charset_collate;";

    // ── 2. IMEI / Serial Tracking ─────────────────────────────────────────────
    $sql_imei = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}ms_imei_tracking (
        id          BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        product_id  BIGINT(20) UNSIGNED NOT NULL,
        imei_serial VARCHAR(50)         NOT NULL,
        status      ENUM('in_stock','sold','returned') NOT NULL DEFAULT 'in_stock',
        supplier_id BIGINT(20) UNSIGNED DEFAULT NULL,
        created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY imei_serial (imei_serial),
        KEY product_id (product_id),
        KEY status (status)
    ) $charset_collate;";

    // ── 3. POS Sales ──────────────────────────────────────────────────────────
    $sql_sales = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}ms_pos_sales (
        id             BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        customer_id    BIGINT(20) UNSIGNED DEFAULT NULL,
        total_amount   DECIMAL(12,2) NOT NULL DEFAULT 0.00,
        discount       DECIMAL(12,2) NOT NULL DEFAULT 0.00,
        net_total      DECIMAL(12,2) NOT NULL DEFAULT 0.00,
        payment_status ENUM('paid','credit','partial') NOT NULL DEFAULT 'paid',
        sale_date      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY customer_id (customer_id),
        KEY payment_status (payment_status),
        KEY sale_date (sale_date)
    ) $charset_collate;";

    // ── 4. POS Sale Items ─────────────────────────────────────────────────────
    $sql_items = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}ms_pos_items (
        id         BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        sale_id    BIGINT(20) UNSIGNED NOT NULL,
        product_id BIGINT(20) UNSIGNED NOT NULL,
        imei_id    BIGINT(20) UNSIGNED DEFAULT NULL,
        quantity   INT(11) NOT NULL DEFAULT 1,
        price      DECIMAL(12,2) NOT NULL DEFAULT 0.00,
        PRIMARY KEY (id),
        KEY sale_id (sale_id),
        KEY product_id (product_id)
    ) $charset_collate;";

    // ── 5. Repair Lab ─────────────────────────────────────────────────────────
    $sql_repair = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}ms_repair_lab (
        id              BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        job_card_number VARCHAR(50)  NOT NULL,
        customer_id     BIGINT(20) UNSIGNED DEFAULT NULL,
        device_model    VARCHAR(255) NOT NULL,
        issue_desc      TEXT         DEFAULT NULL,
        est_cost        DECIMAL(12,2) NOT NULL DEFAULT 0.00,
        status          ENUM('pending','repairing','fixed','unrepairable') NOT NULL DEFAULT 'pending',
        received_date   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY job_card_number (job_card_number),
        KEY customer_id (customer_id),
        KEY status (status)
    ) $charset_collate;";

    // ── 6. Ledgers ────────────────────────────────────────────────────────────
    $sql_ledgers = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}ms_ledgers (
        id               BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        user_id          BIGINT(20) UNSIGNED NOT NULL COMMENT 'customer or supplier ID',
        transaction_type ENUM('credit','debit') NOT NULL,
        amount           DECIMAL(12,2) NOT NULL DEFAULT 0.00,
        description      TEXT DEFAULT NULL,
        transaction_date DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY user_id (user_id),
        KEY transaction_type (transaction_type)
    ) $charset_collate;";

    // ── 7. Expenses ───────────────────────────────────────────────────────────
    $sql_expenses = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}ms_expenses (
        id           BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        expense_type ENUM('rent','bill','salary','misc') NOT NULL DEFAULT 'misc',
        amount       DECIMAL(12,2) NOT NULL DEFAULT 0.00,
        description  TEXT DEFAULT NULL,
        expense_date DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY expense_type (expense_type),
        KEY expense_date (expense_date)
    ) $charset_collate;";

    // ── 8. Customers ──────────────────────────────────────────────────────────
    $sql_customers = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}ms_customers (
        id            BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        name          VARCHAR(255)   NOT NULL,
        phone         VARCHAR(50)    NOT NULL DEFAULT '',
        email         VARCHAR(255)   DEFAULT NULL,
        address       TEXT           DEFAULT NULL,
        total_balance DECIMAL(12,2)  NOT NULL DEFAULT 0.00,
        created_at    DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY phone (phone),
        KEY name (name)
    ) $charset_collate;";

    dbDelta( $sql_inventory );
    dbDelta( $sql_imei );
    dbDelta( $sql_sales );
    dbDelta( $sql_items );
    dbDelta( $sql_repair );
    dbDelta( $sql_ledgers );
    dbDelta( $sql_expenses );
    dbDelta( $sql_customers );

    // Insert the default Walk-in Customer (ID 1) if not already present.
    $walk_in = $wpdb->get_var( "SELECT id FROM {$wpdb->prefix}ms_customers WHERE name = 'Walk-in Customer' LIMIT 1" );
    if ( ! $walk_in ) {
        $wpdb->insert(
            $wpdb->prefix . 'ms_customers',
            array(
                'name'  => 'Walk-in Customer',
                'phone' => '0000000000',
                'email' => '',
            ),
            array( '%s', '%s', '%s' )
        );
    }

    update_option( 'msp_db_version', MSP_VERSION );
}

/**
 * Create the "Mobile Shop Dashboard" WordPress page if it doesn't exist.
 * Injects [mobile_shop_app] shortcode and saves the page ID in options.
 */
function msp_create_dashboard_page() {
    $page_id = get_option( 'msp_dashboard_page_id' );

    // Check whether the saved page still exists.
    if ( $page_id && get_post_status( $page_id ) === 'publish' ) {
        return;
    }

    // Search for an existing page with this slug.
    $existing = get_page_by_path( 'ms-dashboard' );
    if ( $existing ) {
        update_option( 'msp_dashboard_page_id', $existing->ID );
        return;
    }

    // Create a fresh page.
    $new_page_id = wp_insert_post( array(
        'post_title'   => 'Mobile Shop Dashboard',
        'post_name'    => 'ms-dashboard',
        'post_status'  => 'publish',
        'post_type'    => 'page',
        'post_content' => '[mobile_shop_app]',
        'post_author'  => 1,
    ) );

    if ( ! is_wp_error( $new_page_id ) ) {
        update_option( 'msp_dashboard_page_id', $new_page_id );
    }
}
