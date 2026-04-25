<?php
/**
 * Plugin Name:       Mobile Shop Smart Management System
 * Plugin URI:        https://github.com/nuzwa269/mobile-shop-system
 * Description:       A complete, standalone POS, Inventory, Repair Lab, CRM, and Ledger management system for mobile shops. Designed and Developed by Sikandar Hayat Baba.
 * Version:           1.0.0
 * Author:            Sikandar Hayat Baba
 * Author URI:        #
 * License:           GPL-2.0+
 * Text Domain:       mobile-shop-pro
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

// ─── Plugin Constants ────────────────────────────────────────────────────────
define( 'MSP_VERSION',    '1.0.0' );
define( 'MSP_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'MSP_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'MSP_TEXT_DOMAIN', 'mobile-shop-pro' );
define( 'MSP_DEVELOPER',  'Designed and Developed by Sikandar Hayat Baba' );

// ─── Include Core Files ──────────────────────────────────────────────────────
require_once MSP_PLUGIN_DIR . 'includes/db-schema.php';
require_once MSP_PLUGIN_DIR . 'includes/ajax-handlers.php';
require_once MSP_PLUGIN_DIR . 'includes/shortcode.php';

// ─── Activation / Deactivation Hooks ────────────────────────────────────────
register_activation_hook( __FILE__, 'msp_activate' );
register_deactivation_hook( __FILE__, 'msp_deactivate' );

/**
 * Plugin activation: create DB tables and the dashboard page.
 */
function msp_activate() {
    msp_create_tables();
    msp_create_dashboard_page();
}

/**
 * Plugin deactivation: flush rewrite rules.
 */
function msp_deactivate() {
    flush_rewrite_rules();
}

// ─── Admin Menu ──────────────────────────────────────────────────────────────
add_action( 'admin_menu', 'msp_admin_menu' );

function msp_admin_menu() {
    add_menu_page(
        __( 'Mobile Shop', MSP_TEXT_DOMAIN ),
        __( 'Mobile Shop', MSP_TEXT_DOMAIN ),
        'manage_options',
        'mobile-shop-pro',
        'msp_admin_page',
        'dashicons-smartphone',
        25
    );
}

function msp_admin_page() {
    $page_id  = get_option( 'msp_dashboard_page_id' );
    $page_url = $page_id ? get_permalink( $page_id ) : '';
    ?>
    <div class="wrap">
        <h1><?php esc_html_e( 'Mobile Shop Smart Management System', MSP_TEXT_DOMAIN ); ?></h1>
        <p><?php esc_html_e( 'Version: ', MSP_TEXT_DOMAIN ); ?><?php echo esc_html( MSP_VERSION ); ?></p>
        <p><em><?php echo esc_html( MSP_DEVELOPER ); ?></em></p>
        <?php if ( $page_url ) : ?>
            <p>
                <?php esc_html_e( 'Your dashboard is ready at: ', MSP_TEXT_DOMAIN ); ?>
                <a href="<?php echo esc_url( $page_url ); ?>" target="_blank"><?php echo esc_url( $page_url ); ?></a>
            </p>
        <?php else : ?>
            <p class="notice notice-warning"><?php esc_html_e( 'Dashboard page not found. Please deactivate and reactivate the plugin.', MSP_TEXT_DOMAIN ); ?></p>
        <?php endif; ?>
    </div>
    <?php
}
