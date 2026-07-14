<?php
/**
 * Multi-resort configuration, synchronization, and admin filtering.
 */
class AMRR_Multi_Resort_Manager {
    const OPTION = 'amrr_resorts';

    public function init() {
        add_action( 'admin_menu', array( $this, 'add_admin_page' ) );
        add_action( 'admin_post_amrr_save_resorts', array( $this, 'save_resorts' ) );
        add_action( 'admin_post_amrr_sync_resort', array( $this, 'sync_resort_action' ) );
        add_action( 'admin_post_amrr_sync_all_resorts', array( $this, 'sync_all_action' ) );
        add_action( 'amrr_daily_import', array( $this, 'run_daily_sync_all' ) );
        add_filter( 'manage_amrr-review_posts_columns', array( $this, 'add_property_column' ) );
        add_action( 'manage_amrr-review_posts_custom_column', array( $this, 'render_property_column' ), 10, 2 );
        add_action( 'restrict_manage_posts', array( $this, 'render_property_filter' ) );
        add_action( 'pre_get_posts', array( $this, 'apply_property_filter' ) );
    }

    public static function get_resorts() {
        return array_values( array_filter( (array) get_option( self::OPTION, array() ), function ( $resort ) {
            return is_array( $resort ) && ! empty( $resort['slug'] );
        } ) );
    }

    public static function get_resort( $slug ) {
        foreach ( self::get_resorts() as $resort ) {
            if ( $resort['slug'] === $slug ) {
                return $resort;
            }
        }
        return null;
    }

    public function add_admin_page() {
        add_submenu_page(
            'edit.php?post_type=amrr-review',
            __( 'Resorts', 'alchemer-multi-resort-reviews' ),
            __( 'Resorts', 'alchemer-multi-resort-reviews' ),
            'manage_options',
            'amrr_resorts',
            array( $this, 'render_admin_page' )
        );
    }

    public function save_resorts() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You do not have permission to manage resorts.', 'alchemer-multi-resort-reviews' ) );
        }
        check_admin_referer( 'amrr_save_resorts' );

        $resorts = array();
        foreach ( (array) wp_unslash( $_POST['resorts'] ?? array() ) as $row ) {
            $name = sanitize_text_field( $row['name'] ?? '' );
            $slug = sanitize_title( $row['slug'] ?? $name );
            $survey_id = sanitize_text_field( $row['survey_id'] ?? '' );
            if ( ! $name || ! $slug || ! $survey_id ) {
                continue;
            }
            $resorts[] = array(
                'name' => $name,
                'slug' => $slug,
                'survey_id' => $survey_id,
                'rating_question' => sanitize_text_field( $row['rating_question'] ?? '' ),
                'reviewer_name' => sanitize_text_field( $row['reviewer_name'] ?? '' ),
                'minimum_rating' => min( 5, max( 1, intval( $row['minimum_rating'] ?? 5 ) ) ),
                'enabled' => ! empty( $row['enabled'] ) ? 1 : 0,
                'auto_import' => ! empty( $row['auto_import'] ) ? 1 : 0,
            );
        }
        update_option( self::OPTION, $resorts, false );
        amrr_maybe_schedule_daily_import();
        wp_safe_redirect( add_query_arg( 'updated', '1', admin_url( 'edit.php?post_type=amrr-review&page=amrr_resorts' ) ) );
        exit;
    }

    public function sync_resort_action() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You do not have permission to synchronize reviews.', 'alchemer-multi-resort-reviews' ) );
        }
        $slug = sanitize_title( wp_unslash( $_GET['resort'] ?? '' ) );
        check_admin_referer( 'amrr_sync_resort_' . $slug );
        $result = $this->sync_resort( self::get_resort( $slug ), false );
        $this->redirect_with_result( $result );
    }

    public function sync_all_action() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You do not have permission to synchronize reviews.', 'alchemer-multi-resort-reviews' ) );
        }
        check_admin_referer( 'amrr_sync_all_resorts' );
        $this->redirect_with_result( $this->sync_all( false ) );
    }

    private function redirect_with_result( $result ) {
        wp_safe_redirect( add_query_arg(
            array(
                'amrr_status' => ! empty( $result['success'] ) ? 'success' : 'error',
                'amrr_message' => rawurlencode( $result['message'] ?? '' ),
            ),
            admin_url( 'edit.php?post_type=amrr-review&page=amrr_resorts' )
        ) );
        exit;
    }

    public function run_daily_sync_all() {
        return $this->sync_all( true );
    }

    public function sync_all( $automatic = true ) {
        $results = array();
        foreach ( self::get_resorts() as $resort ) {
            if ( empty( $resort['enabled'] ) || ( $automatic && empty( $resort['auto_import'] ) ) ) {
                continue;
            }
            $results[] = $this->sync_resort( $resort, $automatic );
        }
        $failed = array_filter( $results, function ( $result ) { return empty( $result['success'] ); } );
        $created = array_sum( array_map( function ( $result ) { return intval( $result['imported_count'] ?? 0 ); }, $results ) );
        return array(
            'success' => empty( $failed ),
            'imported_count' => $created,
            'message' => sprintf( __( 'Synchronized %1$d resorts and created %2$d pending reviews.', 'alchemer-multi-resort-reviews' ), count( $results ), $created ),
            'results' => $results,
        );
    }

    public function sync_resort( $resort, $automatic = true ) {
        if ( ! is_array( $resort ) || empty( $resort['survey_id'] ) ) {
            return array( 'success' => false, 'message' => __( 'Invalid resort configuration.', 'alchemer-multi-resort-reviews' ), 'imported_count' => 0 );
        }
        $importer = new AMRR_Importer( $resort );
        return $importer->run_daily_sync( array(
            'target_rating' => 0,
            'minimum_rating' => intval( $resort['minimum_rating'] ?? 5 ),
            'stop_at_existing' => $automatic,
            'import_all_new' => true,
            'max_reviews' => 0,
        ) );
    }

    public function add_property_column( $columns ) {
        $columns['amrr_property'] = __( 'Resort', 'alchemer-multi-resort-reviews' );
        return $columns;
    }

    public function render_property_column( $column, $post_id ) {
        if ( 'amrr_property' === $column ) {
            echo esc_html( get_post_meta( $post_id, '_amrr_property_name', true ) );
        }
    }

    public function render_property_filter( $post_type ) {
        if ( 'amrr-review' !== $post_type ) {
            return;
        }
        $selected = sanitize_title( wp_unslash( $_GET['amrr_property'] ?? '' ) );
        echo '<select name="amrr_property"><option value="">' . esc_html__( 'All resorts', 'alchemer-multi-resort-reviews' ) . '</option>';
        foreach ( self::get_resorts() as $resort ) {
            printf( '<option value="%1$s" %2$s>%3$s</option>', esc_attr( $resort['slug'] ), selected( $selected, $resort['slug'], false ), esc_html( $resort['name'] ) );
        }
        echo '</select>';
    }

    public function apply_property_filter( $query ) {
        if ( ! is_admin() || ! $query->is_main_query() || 'amrr-review' !== $query->get( 'post_type' ) || empty( $_GET['amrr_property'] ) ) {
            return;
        }
        $query->set( 'meta_key', '_amrr_property_slug' );
        $query->set( 'meta_value', sanitize_title( wp_unslash( $_GET['amrr_property'] ) ) );
    }

    public function render_admin_page() {
        $resorts = self::get_resorts();
        $resorts[] = array( 'name' => '', 'slug' => '', 'survey_id' => '', 'rating_question' => '', 'reviewer_name' => '', 'minimum_rating' => 5, 'enabled' => 1, 'auto_import' => 1 );
        ?>
        <div class="wrap"><h1><?php esc_html_e( 'Alchemer Resorts', 'alchemer-multi-resort-reviews' ); ?></h1>
        <p><?php esc_html_e( 'Configure one Alchemer survey and field mapping per resort. The final empty row adds another resort.', 'alchemer-multi-resort-reviews' ); ?></p>
        <?php if ( isset( $_GET['amrr_message'] ) ) : ?><div class="notice notice-<?php echo 'error' === ( $_GET['amrr_status'] ?? '' ) ? 'error' : 'success'; ?>"><p><?php echo esc_html( rawurldecode( wp_unslash( $_GET['amrr_message'] ) ) ); ?></p></div><?php endif; ?>
        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
        <input type="hidden" name="action" value="amrr_save_resorts"><?php wp_nonce_field( 'amrr_save_resorts' ); ?>
        <table class="widefat striped"><thead><tr><th>Name</th><th>Slug</th><th>Survey ID</th><th>Rating question</th><th>Name field</th><th>Min rating</th><th>Enabled</th><th>Daily</th><th>Action</th></tr></thead><tbody>
        <?php foreach ( $resorts as $index => $resort ) : ?><tr>
            <?php foreach ( array( 'name', 'slug', 'survey_id', 'rating_question', 'reviewer_name' ) as $field ) : ?><td><input type="text" name="resorts[<?php echo intval( $index ); ?>][<?php echo esc_attr( $field ); ?>]" value="<?php echo esc_attr( $resort[$field] ?? '' ); ?>"></td><?php endforeach; ?>
            <td><input type="number" min="1" max="5" name="resorts[<?php echo intval( $index ); ?>][minimum_rating]" value="<?php echo intval( $resort['minimum_rating'] ?? 5 ); ?>" style="width:65px"></td>
            <td><input type="checkbox" name="resorts[<?php echo intval( $index ); ?>][enabled]" value="1" <?php checked( ! empty( $resort['enabled'] ) ); ?>></td>
            <td><input type="checkbox" name="resorts[<?php echo intval( $index ); ?>][auto_import]" value="1" <?php checked( ! empty( $resort['auto_import'] ) ); ?>></td>
            <td><?php if ( ! empty( $resort['slug'] ) ) : ?><a class="button" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=amrr_sync_resort&resort=' . rawurlencode( $resort['slug'] ) ), 'amrr_sync_resort_' . $resort['slug'] ) ); ?>"><?php esc_html_e( 'Sync Reviews', 'alchemer-multi-resort-reviews' ); ?></a><?php endif; ?></td>
        </tr><?php endforeach; ?></tbody></table>
        <?php submit_button( __( 'Save Resorts', 'alchemer-multi-resort-reviews' ) ); ?></form>
        <p><a class="button button-primary" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=amrr_sync_all_resorts' ), 'amrr_sync_all_resorts' ) ); ?>"><?php esc_html_e( 'Sync All Enabled Resorts', 'alchemer-multi-resort-reviews' ); ?></a></p></div>
        <?php
    }
}
