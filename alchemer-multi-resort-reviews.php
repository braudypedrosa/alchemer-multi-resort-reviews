<?php
/**
 * Plugin Name: Alchemer Multi-Resort Reviews
 * Description: Import, moderate, and display Alchemer reviews across multiple resorts or properties.
 * Version: 1.0.2
 * Author: Braudy Pedrosa
 * Text Domain: alchemer-multi-resort-reviews
 * Domain Path: /languages
 * Update URI: https://github.com/braudypedrosa/alchemer-multi-resort-reviews
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
    die;
}

// Define plugin constants
define( 'AMRR_VERSION', '1.0.2' );
define( 'AMRR_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'AMRR_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'AMRR_PLUGIN_FILE', __FILE__ );
define( 'AMRR_GITHUB_REPO', 'braudypedrosa/alchemer-multi-resort-reviews' );
define( 'AMRR_GITHUB_ASSET_NAME', 'alchemer-multi-resort-reviews.zip' );

// Include the file that registers the custom post type
require_once AMRR_PLUGIN_DIR . 'includes/class-amrr-reviews-post-types.php';

// Include the file that handles settings
require_once AMRR_PLUGIN_DIR . 'includes/class-amrr-reviews-settings.php';

// Include the file that handles API communication
require_once AMRR_PLUGIN_DIR . 'includes/class-amrr-reviews-api.php';

// Include the file that handles importing reviews
require_once AMRR_PLUGIN_DIR . 'includes/class-amrr-reviews-importer.php';
require_once AMRR_PLUGIN_DIR . 'includes/class-amrr-multi-resort-manager.php';
require_once AMRR_PLUGIN_DIR . 'includes/class-amrr-legacy-migrator.php';

// Include the plugin update checker library
require_once AMRR_PLUGIN_DIR . 'includes/plugin-update-checker/plugin-update-checker.php';

// Include dependencies
require_once AMRR_PLUGIN_DIR . 'includes/reviews-carousel/amrr-review-carousel.php';

// Register admin menu
require_once AMRR_PLUGIN_DIR . 'includes/reviews-carousel/amrr-review-carousel-docs.php';

// Hook to initialize the plugin
add_action( 'plugins_loaded', 'amrr_init' );

/**
 * Initialize the plugin functionalities
 * 
 * @return void
 */
function amrr_init() {
    // Initialize custom post types
    $post_types = new AMRR_Post_Types();
    $post_types->init();
    
    // Initialize settings
    $settings = new AMRR_Settings();
    $settings->init();
    
    // Initialize importer
    $importer = new AMRR_Importer();
    $importer->init();

    $multi_resort = new AMRR_Multi_Resort_Manager();
    $multi_resort->init();

    $migrator = new AMRR_Legacy_Migrator();
    $migrator->init();

    amrr_init_github_updater();

    amrr_maybe_schedule_daily_import();
}

/**
 * Initialize GitHub release updates through Plugin Update Checker.
 *
 * @return void
 */
function amrr_init_github_updater() {
    if ( ! class_exists( '\YahnisElsts\PluginUpdateChecker\v5\PucFactory' ) ) {
        return;
    }

    $update_checker = \YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker(
        'https://github.com/braudypedrosa/alchemer-multi-resort-reviews/',
        AMRR_PLUGIN_FILE,
        'alchemer-multi-resort-reviews'
    );

    $update_checker->setBranch( 'master' );
    $update_checker->getVcsApi()->enableReleaseAssets( '/alchemer-multi-resort-reviews\.zip($|[?&#])/i' );
}

// Register activation hook
register_activation_hook( __FILE__, 'amrr_activate' );

/**
 * Plugin activation callback
 * 
 * @return void
 */
function amrr_activate() {
    // Trigger post type registration
    $post_types = new AMRR_Post_Types();
    $post_types->register_review_post_type();

    amrr_maybe_schedule_daily_import();
    
    // Clear the permalinks
    flush_rewrite_rules();
}

/**
 * Schedule the daily importer if the saved setting is enabled.
 *
 * @return void
 */
function amrr_maybe_schedule_daily_import() {
    $resorts = get_option( 'amrr_resorts', array() );

    if ( ! array_filter( (array) $resorts, function ( $resort ) {
        return ! empty( $resort['enabled'] ) && ! empty( $resort['auto_import'] );
    } ) ) {
        return;
    }

    if ( ! wp_next_scheduled( 'amrr_daily_import' ) ) {
        wp_schedule_event( time() + MINUTE_IN_SECONDS, 'daily', 'amrr_daily_import' );
    }
}

// Register deactivation hook
register_deactivation_hook( __FILE__, 'amrr_deactivate' );

/**
 * Plugin deactivation callback
 * 
 * @return void
 */
function amrr_deactivate() {
    wp_clear_scheduled_hook('amrr_daily_import');

    // Clear the permalinks
    flush_rewrite_rules();
}
