<?php
/**
 * Shortcode & Template Redirect – Mobile Shop Smart Management System
 *
 * Registers [mobile_shop_app] shortcode and hijacks the theme on the
 * dashboard page to render the standalone full-screen POS shell.
 *
 * Designed and Developed by Sikandar Hayat Baba
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// ─── Register Shortcode ───────────────────────────────────────────────────────
add_shortcode( 'mobile_shop_app', 'msp_shortcode_handler' );

function msp_shortcode_handler( $atts ) {
    // The real output is delivered via template_redirect; shortcode returns
    // an empty string so WordPress doesn't render anything inside the theme.
    return '';
}

// ─── Template Redirect ────────────────────────────────────────────────────────
add_action( 'template_redirect', 'msp_template_redirect' );

function msp_template_redirect() {
    $page_id = get_option( 'msp_dashboard_page_id' );

    if ( ! $page_id || ! is_page( $page_id ) ) {
        return;
    }

    // Only allow logged-in users with sufficient capability.
    if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'msp_shop_manager' ) ) {
        wp_die(
            esc_html__( 'You do not have permission to access the Mobile Shop Dashboard.', MSP_TEXT_DOMAIN ),
            esc_html__( 'Access Denied', MSP_TEXT_DOMAIN ),
            array( 'response' => 403 )
        );
    }

    // Kill the theme and serve our custom shell.
    msp_enqueue_dashboard_assets();
    include MSP_PLUGIN_DIR . 'templates/dashboard.php';
    exit;
}

/**
 * Enqueue CSS and JS for the dashboard shell.
 * Called just before including the template, so wp_head()/wp_footer()
 * hooks do NOT fire; we manually output <link> and <script> tags inside
 * the template for complete isolation.
 */
function msp_enqueue_dashboard_assets() {
    // Intentionally left blank — assets are hard-coded in the template
    // to guarantee full independence from the active theme.
}
