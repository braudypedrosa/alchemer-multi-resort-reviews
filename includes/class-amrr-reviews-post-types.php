<?php
/**
 * Class for registering custom post types for the Alchemer Reviews plugin
 *
 * @since 1.0.0
 */
class AMRR_Post_Types {

    /**
     * Initialize the class and set its hooks.
     *
     * @return void
     */
    public function init() {
        // Register custom post types
        add_action( 'init', array( $this, 'register_review_post_type' ) );
        
        // Add meta boxes
        add_action( 'add_meta_boxes', array( $this, 'add_review_meta_boxes' ) );
        
        // Save meta box data
        add_action( 'save_post_amrr-review', array( $this, 'save_review_meta' ) );
        
        // Automatically set manually_edited flag when content is updated through the editor
        add_action( 'post_updated', array( $this, 'maybe_set_manually_edited_flag' ), 10, 3 );
        
        // Add columns to the reviews list
        add_filter( 'manage_amrr-review_posts_columns', array( $this, 'add_review_columns' ) );
        
        // Display column content
        add_action( 'manage_amrr-review_posts_custom_column', array( $this, 'display_review_column_content' ), 10, 2 );
        
        // Make columns sortable
        add_filter( 'manage_edit-amrr-review_sortable_columns', array( $this, 'make_review_columns_sortable' ) );
        
        // Sort by custom columns
        add_action( 'pre_get_posts', array( $this, 'sort_reviews_by_custom_column' ) );
        
        // Enqueue admin scripts and styles
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ) );
        
        // AJAX handler for toggle skip overwrite
        add_action( 'wp_ajax_toggle_skip_overwrite', array( $this, 'ajax_toggle_skip_overwrite' ) );
        

    }

    /**
     * Register the 'amrr-review' custom post type
     *
     * @return void
     */
    public function register_review_post_type() {
        $labels = array(
            'name'                  => _x( 'Reviews', 'Post type general name', 'alchemer-multi-resort-reviews' ),
            'singular_name'         => _x( 'Review', 'Post type singular name', 'alchemer-multi-resort-reviews' ),
            'menu_name'             => _x( 'Reviews', 'Admin Menu text', 'alchemer-multi-resort-reviews' ),
            'name_admin_bar'        => _x( 'Review', 'Add New on Toolbar', 'alchemer-multi-resort-reviews' ),
            'add_new'               => __( 'Add New', 'alchemer-multi-resort-reviews' ),
            'add_new_item'          => __( 'Add New Review', 'alchemer-multi-resort-reviews' ),
            'new_item'              => __( 'New Review', 'alchemer-multi-resort-reviews' ),
            'edit_item'             => __( 'Edit Review', 'alchemer-multi-resort-reviews' ),
            'view_item'             => __( 'View Review', 'alchemer-multi-resort-reviews' ),
            'all_items'             => __( 'All Reviews', 'alchemer-multi-resort-reviews' ),
            'search_items'          => __( 'Search Reviews', 'alchemer-multi-resort-reviews' ),
            'parent_item_colon'     => __( 'Parent Reviews:', 'alchemer-multi-resort-reviews' ),
            'not_found'             => __( 'No reviews found.', 'alchemer-multi-resort-reviews' ),
            'not_found_in_trash'    => __( 'No reviews found in Trash.', 'alchemer-multi-resort-reviews' ),
            'featured_image'        => _x( 'Review Cover Image', 'Overrides the "Featured Image" phrase', 'alchemer-multi-resort-reviews' ),
            'set_featured_image'    => _x( 'Set cover image', 'Overrides the "Set featured image" phrase', 'alchemer-multi-resort-reviews' ),
            'remove_featured_image' => _x( 'Remove cover image', 'Overrides the "Remove featured image" phrase', 'alchemer-multi-resort-reviews' ),
            'use_featured_image'    => _x( 'Use as cover image', 'Overrides the "Use as featured image" phrase', 'alchemer-multi-resort-reviews' ),
            'archives'              => _x( 'Review archives', 'The post type archive label used in nav menus', 'alchemer-multi-resort-reviews' ),
            'insert_into_item'      => _x( 'Insert into review', 'Overrides the "Insert into post" phrase', 'alchemer-multi-resort-reviews' ),
            'uploaded_to_this_item' => _x( 'Uploaded to this review', 'Overrides the "Uploaded to this post" phrase', 'alchemer-multi-resort-reviews' ),
            'filter_items_list'     => _x( 'Filter reviews list', 'Screen reader text for the filter links', 'alchemer-multi-resort-reviews' ),
            'items_list_navigation' => _x( 'Reviews list navigation', 'Screen reader text for the pagination', 'alchemer-multi-resort-reviews' ),
            'items_list'            => _x( 'Reviews list', 'Screen reader text for the items list', 'alchemer-multi-resort-reviews' ),
        );

        $args = array(
            'labels'             => $labels,
            'public'             => true,
            'publicly_queryable' => true,
            'show_ui'            => true,
            'show_in_menu'       => true,
            'query_var'          => true,
            'rewrite'            => array( 'slug' => 'alchemer-multi-resort-reviews' ),
            'capability_type'    => 'post',
            'has_archive'        => true,
            'hierarchical'       => false,
            'menu_position'      => null,
            'menu_icon'          => 'dashicons-star-filled',
            'supports'           => array( 'title', 'editor', 'author', 'thumbnail', 'excerpt' ),
            'show_in_rest'       => true,
        );

        register_post_type( 'amrr-review', $args );
    }

    /**
     * Add meta boxes to the review edit screen
     *
     * @return void
     */
    public function add_review_meta_boxes() {
        add_meta_box(
            'alchemer_review_details',
            __( 'Review Details', 'alchemer-multi-resort-reviews' ),
            array( $this, 'render_review_details_meta_box' ),
            'amrr-review',
            'side',
            'high'
        );

        add_meta_box(
            'alchemer_original_review',
            __( 'Original Review Content', 'alchemer-multi-resort-reviews' ),
            array( $this, 'render_original_review_meta_box' ),
            'amrr-review',
            'normal',
            'high'
        );
    }

    /**
     * Render the review details meta box
     *
     * @param WP_Post $post The post object.
     * @return void
     */
    public function render_review_details_meta_box( $post ) {
        // Add nonce for security
        wp_nonce_field( 'alchemer_review_details', 'alchemer_review_details_nonce' );
        
        // Get meta values
        $reviewer_name = get_post_meta( $post->ID, '_alchemer_reviewer_name', true );
        $rating = get_post_meta( $post->ID, '_alchemer_rating', true );
        $skip_overwrite = get_post_meta( $post->ID, '_alchemer_manually_edited', true );
        $reviewed = get_post_meta( $post->ID, '_alchemer_reviewed', true );
        $review_decision = get_post_meta( $post->ID, '_alchemer_review_decision', true );
        
        // Get response ID
        $response_id = get_post_meta( $post->ID, '_alchemer_response_id', true );
        
        ?>
        <div class="amrr-review-details-fields">
            <div class="amrr-review-field">
                <label for="alchemer_reviewer_name"><?php _e( 'Reviewer Name:', 'alchemer-multi-resort-reviews' ); ?></label>
                <input type="text" id="alchemer_reviewer_name" name="alchemer_reviewer_name" value="<?php echo esc_attr( $reviewer_name ); ?>" class="widefat">
            </div>

            <div class="amrr-review-field">
                <label for="alchemer_rating"><?php _e( 'Rating:', 'alchemer-multi-resort-reviews' ); ?></label>
                <select id="alchemer_rating" name="alchemer_rating" class="widefat">
                    <option value="0" <?php selected( $rating, 0 ); ?>><?php _e( 'No rating', 'alchemer-multi-resort-reviews' ); ?></option>
                    <option value="1" <?php selected( $rating, 1 ); ?>>1 - <?php _e( 'Poor', 'alchemer-multi-resort-reviews' ); ?></option>
                    <option value="2" <?php selected( $rating, 2 ); ?>>2 - <?php _e( 'Fair', 'alchemer-multi-resort-reviews' ); ?></option>
                    <option value="3" <?php selected( $rating, 3 ); ?>>3 - <?php _e( 'Average', 'alchemer-multi-resort-reviews' ); ?></option>
                    <option value="4" <?php selected( $rating, 4 ); ?>>4 - <?php _e( 'Good', 'alchemer-multi-resort-reviews' ); ?></option>
                    <option value="5" <?php selected( $rating, 5 ); ?>>5 - <?php _e( 'Excellent', 'alchemer-multi-resort-reviews' ); ?></option>
                </select>
            </div>

            <div class="amrr-review-field alchemer-rating-display">
                <?php echo $this->get_rating_stars( $rating ); ?>
            </div>

            <div class="amrr-review-field amrr-review-inline-meta">
                <span class="amrr-review-field-label"><?php _e( 'Import Status:', 'alchemer-multi-resort-reviews' ); ?></span>
                <span><?php echo esc_html( $this->get_review_decision_label( $review_decision, $reviewed ) ); ?></span>
            </div>

            <div class="amrr-review-field">
                <label class="amrr-review-checkbox-label">
                    <input type="checkbox" name="alchemer_manually_edited" value="1" <?php checked( $skip_overwrite, '1' ); ?>>
                    <span><?php _e( 'Skip Overwrite (protect from updates during imports)', 'alchemer-multi-resort-reviews' ); ?></span>
                </label>
            </div>

            <?php if ( $response_id ) : ?>
                <div class="amrr-review-field amrr-review-inline-meta">
                    <span class="amrr-review-field-label"><?php _e( 'Alchemer Response ID:', 'alchemer-multi-resort-reviews' ); ?></span>
                    <span class="alchemer-response-id"><?php echo esc_html( $response_id ); ?></span>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }



    /**
     * Render the original review content meta box
     *
     * @param WP_Post $post The post object.
     * @return void
     */
    public function render_original_review_meta_box( $post ) {
        wp_nonce_field( 'alchemer_original_review', 'alchemer_original_review_nonce' );
        $original_content = $post->post_content;
        echo '<div class="original-review-content">' . wp_kses_post( $original_content ) . '</div>';
    }

    /**
     * Save review meta data
     *
     * @param int $post_id The post ID.
     * @return void
     */
    public function save_review_meta( $post_id ) {
        // Check if nonce is set
        if ( ! isset( $_POST['alchemer_review_details_nonce'] ) ) {
            return;
        }
        
        // Verify nonce
        if ( ! wp_verify_nonce( $_POST['alchemer_review_details_nonce'], 'alchemer_review_details' ) ) {
            return;
        }
        
        // If auto-saving, do nothing
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return;
        }
        
        // Check permissions
        if ( ! current_user_can( 'edit_post', $post_id ) ) {
            return;
        }
        
        // Save reviewer name
        if ( isset( $_POST['alchemer_reviewer_name'] ) ) {
            update_post_meta( $post_id, '_alchemer_reviewer_name', sanitize_text_field( $_POST['alchemer_reviewer_name'] ) );
        }
        
        // Save rating
        if ( isset( $_POST['alchemer_rating'] ) ) {
            update_post_meta( $post_id, '_alchemer_rating', intval( $_POST['alchemer_rating'] ) );
        }
        
        // Save manually edited flag
        $skip_overwrite = isset( $_POST['alchemer_manually_edited'] ) ? '1' : '0';
        update_post_meta( $post_id, '_alchemer_manually_edited', $skip_overwrite );
    }

    /**
     * Automatically set manually_edited flag when content is updated through the editor
     *
     * @param int     $post_id     The post ID.
     * @param WP_Post $post_after  The post object after the update.
     * @param WP_Post $post_before The post object before the update.
     * @return void
     */
    public function maybe_set_manually_edited_flag( $post_id, $post_after, $post_before ) {
        // Check if the post type is 'amrr-review'
        if ( $post_after->post_type !== 'amrr-review' ) {
            return;
        }
        
        // Check if the content has been updated
        if ( $post_after->post_content === $post_before->post_content ) {
            return;
        }
        
        // Set manually_edited flag to 1
        update_post_meta( $post_id, '_alchemer_manually_edited', '1' );
    }

    /**
     * Add custom columns to the reviews list
     *
     * @param array $columns The default columns.
     * @return array Modified columns.
     */
    public function add_review_columns( $columns ) {
        $new_columns = array();
        
        // Insert columns after title
        foreach ( $columns as $key => $value ) {
            $new_columns[ $key ] = $value;
            
            if ( $key === 'title' ) {
                $new_columns['review_content'] = __( 'Review Content', 'alchemer-multi-resort-reviews' );
                $new_columns['rating'] = __( 'Rating', 'alchemer-multi-resort-reviews' );
                $new_columns['review_decision'] = __( 'Import Status', 'alchemer-multi-resort-reviews' );
                $new_columns['skip_overwrite'] = __( 'Skip Overwrite', 'alchemer-multi-resort-reviews' );
                $new_columns['actions'] = __( 'Actions', 'alchemer-multi-resort-reviews' );
            }
        }
        
        return $new_columns;
    }

    /**
     * Display content for custom columns
     *
     * @param string $column_name The column name.
     * @param int    $post_id     The post ID.
     * @return void
     */
    public function display_review_column_content( $column_name, $post_id ) {
        switch ( $column_name ) {
            case 'review_content':
                $post = get_post($post_id);
                $excerpt = wp_trim_words($post->post_content, 20, '...');
                echo esc_html($excerpt);
                break;
                
            case 'rating':
                $rating = get_post_meta( $post_id, '_alchemer_rating', true );
                echo $this->get_rating_stars( $rating );
                break;

            case 'review_decision':
                $reviewed = get_post_meta( $post_id, '_alchemer_reviewed', true );
                $review_decision = get_post_meta( $post_id, '_alchemer_review_decision', true );
                echo esc_html( $this->get_review_decision_label( $review_decision, $reviewed ) );
                break;
                
            case 'skip_overwrite':
                $skip_overwrite = get_post_meta( $post_id, '_alchemer_manually_edited', true );
                $current_status = $skip_overwrite === '1' ? 'on' : 'off';
                $nonce = wp_create_nonce( 'alchemer_toggle_skip_overwrite_' . $post_id );
                
                echo '<div class="alchemer-toggle-container">';
                echo '<label class="alchemer-toggle-switch">';
                echo '<input type="checkbox" class="alchemer-toggle-skip-overwrite" ' . checked( $current_status, 'on', false ) . ' ';
                echo 'data-post-id="' . esc_attr( $post_id ) . '" ';
                echo 'data-nonce="' . esc_attr( $nonce ) . '" ';
                echo 'data-status="' . esc_attr( $current_status ) . '">';
                echo '<span class="alchemer-toggle-slider"></span>';
                echo '</label>';
                echo '<span class="alchemer-toggle-status screen-reader-text">' . 
                     ($current_status === 'on' ? __('Protected from import overwriting', 'alchemer-multi-resort-reviews') : 
                                               __('Can be overwritten during import', 'alchemer-multi-resort-reviews')) . 
                     '</span>';
                echo '</div>';
                break;
                
            case 'actions':
                echo '<!-- Actions column -->';
                break;
        }
    }

    /**
     * Make custom columns sortable
     *
     * @param array $columns The sortable columns.
     * @return array Modified sortable columns.
     */
    public function make_review_columns_sortable( $columns ) {
        $columns['rating'] = 'rating';
        $columns['review_content'] = 'review_content';
        $columns['review_decision'] = 'review_decision';
        $columns['skip_overwrite'] = 'manually_edited';
        
        return $columns;
    }

    /**
     * Sort reviews by custom column
     *
     * @param WP_Query $query The query object.
     * @return void
     */
    public function sort_reviews_by_custom_column( $query ) {
        if ( ! is_admin() || ! $query->is_main_query() || $query->get( 'post_type' ) !== 'amrr-review' ) {
            return;
        }
        
        $orderby = $query->get( 'orderby' );
        
        switch ( $orderby ) {
            case 'review_content':
                $query->set( 'orderby', 'content' );
                break;
                
            case 'rating':
                $query->set( 'meta_key', '_alchemer_rating' );
                $query->set( 'orderby', 'meta_value_num' );
                break;

            case 'review_decision':
                $query->set( 'meta_key', '_alchemer_review_decision' );
                $query->set( 'orderby', 'meta_value' );
                break;
                
            case 'manually_edited':
                $query->set( 'meta_key', '_alchemer_manually_edited' );
                $query->set( 'orderby', 'meta_value' );
                break;
        }
    }

    /**
     * Get a readable label for the import review state.
     *
     * @param string $decision Review decision.
     * @param string $reviewed Reviewed flag.
     * @return string Readable label.
     */
    private function get_review_decision_label( $decision, $reviewed ) {
        if ( $decision === 'accepted' ) {
            return __( 'Accepted', 'alchemer-multi-resort-reviews' );
        }

        if ( $decision === 'rejected' ) {
            return __( 'Rejected', 'alchemer-multi-resort-reviews' );
        }

        if ( $reviewed === '1' ) {
            return __( 'Reviewed', 'alchemer-multi-resort-reviews' );
        }

        return __( 'Pending Review', 'alchemer-multi-resort-reviews' );
    }

    /**
     * Get HTML for rating stars
     *
     * @param int $rating The rating value (0-5).
     * @return string HTML for rating stars.
     */
    private function get_rating_stars( $rating ) {
        $rating = intval( $rating );
        
        if ( $rating <= 0 ) {
            return '<span class="rating-text">' . __( 'No rating', 'alchemer-multi-resort-reviews' ) . '</span>';
        }
        
        $stars = '';
        $max_stars = 5;
        
        // Add filled stars
        for ( $i = 1; $i <= $rating; $i++ ) {
            $stars .= '<span class="dashicons dashicons-star-filled" style="color: #ffb900;"></span>';
        }
        
        // Add empty stars
        for ( $i = $rating + 1; $i <= $max_stars; $i++ ) {
            $stars .= '<span class="dashicons dashicons-star-empty" style="color: #ccc;"></span>';
        }
        
        // Add numeric rating
        $stars .= ' <span class="rating-text">(' . $rating . ' / ' . $max_stars . ')</span>';
        
        return $stars;
    }

    /**
     * Enqueue admin scripts and styles
     *
     * @param string $hook The current admin page.
     * @return void
     */
    public function enqueue_admin_scripts( $hook ) {
        $screen = get_current_screen();

        if ( ! $screen || 'amrr-review' !== $screen->post_type ) {
            return;
        }

        if ( ! in_array( $hook, array( 'edit.php', 'post.php', 'post-new.php' ), true ) ) {
            return;
        }

        wp_register_style(
            'alchemer-multi-resort-reviews-admin',
            AMRR_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            AMRR_VERSION
        );
        wp_enqueue_style( 'alchemer-multi-resort-reviews-admin' );

        if ( 'edit.php' !== $hook ) {
            return;
        }

        // Enqueue Tailwind and plugin button styles for the reviews list page
        $tailwind_url = 'https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css';
        $admin_tailwind_url = AMRR_PLUGIN_URL . 'assets/css/admin-tailwind.css';
        $admin_tailwind_override_url = AMRR_PLUGIN_URL . 'assets/css/admin-tailwind-override.css';
        if ( defined('WP_DEBUG') && WP_DEBUG ) {
            error_log('Enqueuing Tailwind: ' . $tailwind_url);
            error_log('Enqueuing admin-tailwind: ' . $admin_tailwind_url);
            error_log('Enqueuing admin-tailwind-override: ' . $admin_tailwind_override_url);
        }
        wp_enqueue_style(
            'tailwind-alchemer',
            $tailwind_url,
            array(),
            '2.2.19'
        );
        wp_enqueue_style(
            'alchemer-tailwind-admin',
            $admin_tailwind_url,
            array('tailwind-alchemer'),
            AMRR_VERSION
        );
        wp_enqueue_style(
            'alchemer-tailwind-override',
            $admin_tailwind_override_url,
            array('tailwind-alchemer', 'alchemer-tailwind-admin'),
            AMRR_VERSION . '.' . time()
        );
        
        // Register and enqueue JavaScript
        wp_register_script(
            'alchemer-multi-resort-reviews-admin',
            AMRR_PLUGIN_URL . 'assets/js/admin.js',
            array( 'jquery' ),
            AMRR_VERSION,
            true
        );
        wp_enqueue_script( 'alchemer-multi-resort-reviews-admin' );
        
        // Localize script with data for AJAX
        wp_localize_script(
            'alchemer-multi-resort-reviews-admin',
            'alchemerReviewsAdmin',
            array(
                'ajaxUrl' => admin_url( 'admin-ajax.php' ),
                'toggleSkipText' => __( 'Toggling skip overwrite status...', 'alchemer-multi-resort-reviews' ),
                'errorText' => __( 'Error: ', 'alchemer-multi-resort-reviews' ),
                'protectedText' => __( 'Protected from import overwriting', 'alchemer-multi-resort-reviews' ),
                'unprotectedText' => __( 'Can be overwritten during import', 'alchemer-multi-resort-reviews' ),
                'nonce' => wp_create_nonce( 'test_alchemer_api_connection' ),
            )
        );

        // Add inline style for .alchemer-button for debugging
        add_action('admin_footer', function() {
            echo '<style>.alchemer-button { background: #2563eb !important; color: #fff !important; border-radius: 0.5rem !important; border: none !important; padding: 0.5rem 1.5rem !important; font-weight: 600 !important; font-size: 1rem !important; margin: 0 0.25rem; box-shadow: 0 1px 2px rgba(0,0,0,0.04); transition: background 0.2s; } .alchemer-button-primary { background: #2563eb !important; color: #fff !important; } .alchemer-button-secondary { background: #e5e7eb !important; color: #374151 !important; } .alchemer-button:hover { background: #1d4ed8 !important; }</style>';
        });

        // Add admin-only scroll fix for .fixed and .wp-list-table
        add_action('admin_head', function() {
            echo '<style>.fixed, .wp-list-table { position: static !important; overflow: visible !important; max-height: none !important; }</style>';
        });
    }
    
    /**
     * AJAX handler for toggling skip overwrite status
     *
     * @return void
     */
    public function ajax_toggle_skip_overwrite() {
        // Check if we have the required data
        if ( ! isset( $_POST['post_id'] ) || ! isset( $_POST['nonce'] ) || ! isset( $_POST['status'] ) ) {
            wp_send_json_error( array(
                'message' => __( 'Missing required data', 'alchemer-multi-resort-reviews' ),
            ) );
        }
        
        // Get and sanitize data
        $post_id = intval( $_POST['post_id'] );
        $nonce = sanitize_text_field( $_POST['nonce'] );
        $current_status = sanitize_text_field( $_POST['status'] );
        
        // Verify nonce
        if ( ! wp_verify_nonce( $nonce, 'alchemer_toggle_skip_overwrite_' . $post_id ) ) {
            wp_send_json_error( array(
                'message' => __( 'Security check failed', 'alchemer-multi-resort-reviews' ),
            ) );
        }
        
        // Verify user capabilities
        if ( ! current_user_can( 'edit_post', $post_id ) ) {
            wp_send_json_error( array(
                'message' => __( 'You do not have permission to edit this review', 'alchemer-multi-resort-reviews' ),
            ) );
        }
        
        // Get the post to verify it exists and is a review
        $post = get_post( $post_id );
        if ( ! $post || 'amrr-review' !== $post->post_type ) {
            wp_send_json_error( array(
                'message' => __( 'Invalid review', 'alchemer-multi-resort-reviews' ),
            ) );
        }
        
        // Toggle the status
        $new_status = $current_status === 'on' ? '0' : '1';
        $result = update_post_meta( $post_id, '_alchemer_manually_edited', $new_status );
        
        if ( $result ) {
            wp_send_json_success( array(
                'message' => __( 'Status updated successfully', 'alchemer-multi-resort-reviews' ),
                'new_status' => $new_status === '1' ? 'on' : 'off',
                'post_id' => $post_id,
            ) );
        } else {
            wp_send_json_error( array(
                'message' => __( 'Failed to update status', 'alchemer-multi-resort-reviews' ),
            ) );
        }
    }


}
