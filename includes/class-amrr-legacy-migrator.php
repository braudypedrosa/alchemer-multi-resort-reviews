<?php
/**
 * Migrates legacy VMB review posts without deleting or altering the originals.
 */
class AMRR_Legacy_Migrator {
    public function init() {
        add_action( 'admin_menu', array( $this, 'add_page' ) );
        add_action( 'admin_post_amrr_migrate_vmb_reviews', array( $this, 'handle_migration' ) );
    }

    public function add_page() {
        add_submenu_page( 'edit.php?post_type=amrr-review', __( 'VMB Migration', 'alchemer-multi-resort-reviews' ), __( 'VMB Migration', 'alchemer-multi-resort-reviews' ), 'manage_options', 'amrr_vmb_migration', array( $this, 'render_page' ) );
    }

    public function handle_migration() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You do not have permission to migrate reviews.', 'alchemer-multi-resort-reviews' ) );
        }
        check_admin_referer( 'amrr_migrate_vmb_reviews' );
        $dry_run = empty( $_POST['commit'] );
        $result = array(
            'configuration' => $this->migrate_configuration( $dry_run ),
            'reviews' => $this->migrate( $dry_run ),
        );
        set_transient( 'amrr_migration_result', $result, MINUTE_IN_SECONDS );
        wp_safe_redirect( admin_url( 'edit.php?post_type=amrr-review&page=amrr_vmb_migration' ) );
        exit;
    }

    public function migrate( $dry_run = true ) {
        $ids = get_posts( array( 'post_type' => 'vmb_reviews', 'post_status' => array( 'publish', 'draft', 'pending', 'private' ), 'posts_per_page' => -1, 'fields' => 'ids' ) );
        $result = array( 'dry_run' => (bool) $dry_run, 'found' => count( $ids ), 'created' => 0, 'skipped' => 0, 'errors' => array() );
        foreach ( $ids as $legacy_id ) {
            if ( get_posts( array( 'post_type' => 'amrr-review', 'post_status' => 'any', 'meta_key' => '_amrr_legacy_post_id', 'meta_value' => $legacy_id, 'posts_per_page' => 1, 'fields' => 'ids' ) ) ) {
                $result['skipped']++;
                continue;
            }
            if ( $dry_run ) {
                $result['created']++;
                continue;
            }
            $legacy = get_post( $legacy_id );
            $property_name = get_post_meta( $legacy_id, 'connected_property', true );
            $property_slug = sanitize_title( $property_name );
            $legacy_unique = get_post_meta( $legacy_id, 'vmb_review_id', true );
            $response_id = preg_replace( '/^' . preg_quote( $property_slug, '/' ) . '-/', '', $legacy_unique );
            $post_id = wp_insert_post( array(
                'post_type' => 'amrr-review', 'post_status' => $legacy->post_status,
                'post_title' => $legacy->post_title, 'post_content' => $legacy->post_content,
                'post_date' => $legacy->post_date, 'post_date_gmt' => $legacy->post_date_gmt,
            ), true );
            if ( is_wp_error( $post_id ) ) {
                $result['errors'][] = $post_id->get_error_message();
                continue;
            }
            update_post_meta( $post_id, '_amrr_legacy_post_id', $legacy_id );
            update_post_meta( $post_id, '_alchemer_unique_id', $legacy_unique ?: $property_slug . '-' . $response_id );
            update_post_meta( $post_id, '_alchemer_response_id', $response_id );
            update_post_meta( $post_id, '_alchemer_reviewer_name', get_post_meta( $legacy_id, 'vmb_review_firstname', true ) );
            update_post_meta( $post_id, '_alchemer_rating', intval( get_post_meta( $legacy_id, 'vmb_review_rating', true ) ) );
            update_post_meta( $post_id, '_alchemer_review_date', get_the_date( 'F j, Y', $legacy_id ) );
            update_post_meta( $post_id, '_amrr_property_name', $property_name );
            update_post_meta( $post_id, '_amrr_property_slug', $property_slug );
            update_post_meta( $post_id, '_amrr_hidden', get_post_meta( $legacy_id, 'hide_from_query', true ) ? '1' : '0' );
            update_post_meta( $post_id, '_alchemer_manually_edited', get_post_meta( $legacy_id, 'review_modified', true ) && ! get_post_meta( $legacy_id, 'include_in_sync', true ) ? '1' : '0' );
            update_post_meta( $post_id, '_alchemer_reviewed', '1' );
            update_post_meta( $post_id, '_alchemer_review_decision', 'accepted' );
            $result['created']++;
        }
        $result['success'] = empty( $result['errors'] );
        return $result;
    }

    /** Copy VMB API credentials and per-resort survey mappings. */
    public function migrate_configuration( $dry_run = true ) {
        $legacy_settings = json_decode( get_option( 'vmb_settings', '{}' ), true );
        $resort_ids = get_posts( array( 'post_type' => 'resort', 'post_status' => 'publish', 'posts_per_page' => -1, 'fields' => 'ids' ) );
        $resorts = array();
        foreach ( $resort_ids as $resort_id ) {
            $survey_id = function_exists( 'get_field' ) ? get_field( 'site_id', $resort_id ) : get_post_meta( $resort_id, 'site_id', true );
            $rating_question = function_exists( 'get_field' ) ? get_field( 'review_field_id', $resort_id ) : get_post_meta( $resort_id, 'review_field_id', true );
            if ( ! $survey_id || ! $rating_question ) {
                continue;
            }
            $resorts[] = array(
                'name' => get_the_title( $resort_id ),
                'slug' => sanitize_title( get_the_title( $resort_id ) ),
                'survey_id' => sanitize_text_field( $survey_id ),
                'rating_question' => sanitize_text_field( $rating_question ),
                'reviewer_name' => '',
                'minimum_rating' => 5,
                'enabled' => 1,
                'auto_import' => 1,
            );
        }
        $result = array(
            'dry_run' => (bool) $dry_run,
            'resorts_found' => count( $resorts ),
            'credentials_found' => ! empty( $legacy_settings['alchemer_token'] ) && ! empty( $legacy_settings['alchemer_secret'] ),
        );
        if ( ! $dry_run ) {
            update_option( 'amrr_resorts', $resorts, false );
            update_option( 'amrr_settings', array(
                'api_token' => sanitize_text_field( $legacy_settings['alchemer_token'] ?? '' ),
                'api_token_secret' => trim( $legacy_settings['alchemer_secret'] ?? '' ),
                'survey_id' => '',
            ), false );
            amrr_maybe_schedule_daily_import();
        }
        return $result;
    }

    public function render_page() {
        $result = get_transient( 'amrr_migration_result' );
        delete_transient( 'amrr_migration_result' );
        ?>
        <div class="wrap"><h1><?php esc_html_e( 'Migrate VMB Reviews', 'alchemer-multi-resort-reviews' ); ?></h1>
        <p><?php esc_html_e( 'Copies VMB API credentials, resort survey mappings, and legacy vmb_reviews posts into this plugin. Originals remain untouched, and reruns skip already migrated posts.', 'alchemer-multi-resort-reviews' ); ?></p>
        <?php if ( $result ) : ?><div class="notice notice-<?php echo empty( $result['errors'] ) ? 'success' : 'error'; ?>"><p><?php echo esc_html( wp_json_encode( $result ) ); ?></p></div><?php endif; ?>
        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><input type="hidden" name="action" value="amrr_migrate_vmb_reviews"><?php wp_nonce_field( 'amrr_migrate_vmb_reviews' ); ?>
        <p><button class="button" type="submit"><?php esc_html_e( 'Dry Run', 'alchemer-multi-resort-reviews' ); ?></button> <button class="button button-primary" type="submit" name="commit" value="1"><?php esc_html_e( 'Run Migration', 'alchemer-multi-resort-reviews' ); ?></button></p></form></div>
        <?php
    }
}
