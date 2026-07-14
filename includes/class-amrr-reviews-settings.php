<?php
/**
 * Class for managing Alchemer Reviews plugin settings
 *
 * @since 1.0.0
 */
class AMRR_Settings {

    /**
     * The option group name
     *
     * @var string
     */
    private $option_group = 'amrr_options';

    /**
     * The option name in the database
     *
     * @var string
     */
    private $option_name = 'amrr_settings';

    /**
     * Initialize the class and set its hooks.
     *
     * @return void
     */
    public function init() {
        // Add settings page
        add_action( 'admin_menu', array( $this, 'add_settings_page' ) );
        
        // Register settings
        add_action( 'admin_init', array( $this, 'register_settings' ) );
        
        // Add admin notices for settings updates
        add_action( 'admin_notices', array( $this, 'display_admin_notices' ) );
        
        // Register AJAX handler for testing API connection
        add_action( 'wp_ajax_test_alchemer_api_connection', array( $this, 'ajax_test_api_connection' ) );
        
        // Enqueue admin scripts and styles
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_styles' ), 100 );
    }

    /**
     * Add settings page to the Reviews menu
     *
     * @return void
     */
    public function add_settings_page() {
        add_submenu_page(
            'edit.php?post_type=amrr-review',
            __( 'Alchemer Settings', 'alchemer-multi-resort-reviews' ),
            __( 'Settings', 'alchemer-multi-resort-reviews' ),
            'manage_options',
            'amrr_settings',
            array( $this, 'render_settings_page' )
        );
    }

    /**
     * Register settings fields
     *
     * @return void
     */
    public function register_settings() {
        // Register setting
        register_setting(
            $this->option_group,
            $this->option_name,
            array( $this, 'sanitize_settings' )
        );

        // Add settings section
        add_settings_section(
            'alchemer_api_settings',
            __( 'Alchemer API Settings', 'alchemer-multi-resort-reviews' ),
            array( $this, 'render_api_section' ),
            'amrr_settings'
        );

        // Add settings fields
        add_settings_field(
            'api_token',
            __( 'API Token', 'alchemer-multi-resort-reviews' ),
            array( $this, 'render_api_token_field' ),
            'amrr_settings',
            'alchemer_api_settings'
        );

        add_settings_field(
            'api_token_secret',
            __( 'API Token Secret', 'alchemer-multi-resort-reviews' ),
            array( $this, 'render_api_token_secret_field' ),
            'amrr_settings',
            'alchemer_api_settings'
        );

        add_settings_field(
            'test_connection',
            __( 'Test Connection', 'alchemer-multi-resort-reviews' ),
            array( $this, 'render_test_connection_field' ),
            'amrr_settings',
            'alchemer_api_settings'
        );
    }

    /**
     * Sanitize the settings values
     *
     * @param array $input The settings values.
     * @return array
     */
    public function sanitize_settings( $input ) {
        $sanitized_input = array();

        if ( isset( $input['api_token'] ) ) {
            $sanitized_input['api_token'] = sanitize_text_field( $input['api_token'] );
        }

        if ( isset( $input['api_token_secret'] ) ) {
            // Use raw input for the API token secret to preserve special characters
            // These tokens may contain URL-encoded characters like %2F (/)
            $sanitized_input['api_token_secret'] = trim( $input['api_token_secret'] );
        }

        // Set transient for admin notice
        set_transient( 'amrr_settings_updated', true, 5 );
        
        return $sanitized_input;
    }

    /**
     * Display admin notices when settings are updated
     *
     * @return void
     */
    public function display_admin_notices() {
        // Check if we're on the settings page
        $screen = get_current_screen();
        if ( $screen->id !== 'amrr-review_page_amrr_settings' ) {
            return;
        }
        
        // Check if settings were just updated
        if ( get_transient( 'amrr_settings_updated' ) ) {
            ?>
            <div class="alchemer-admin-area mb-4">
                <div class="alert alert-success">
                    <div class="flex items-center">
                        <span class="dashicons dashicons-yes-alt mr-2"></span>
                        <p><?php _e( 'Alchemer API settings have been saved successfully.', 'alchemer-multi-resort-reviews' ); ?></p>
                    </div>
                </div>
            </div>
            <?php
            delete_transient( 'amrr_settings_updated' );
        }
        
        // Check if API credentials are set
        $options = $this->get_settings();
        if ( 
            empty( $options['api_token'] ) ||
            empty( $options['api_token_secret'] )
        ) {
            ?>
            <div class="alchemer-admin-area mb-4">
                <div class="alert alert-warning">
                    <div class="flex items-center">
                        <span class="dashicons dashicons-warning mr-2"></span>
                        <p><?php _e( 'Please enter the global Alchemer API token and token secret.', 'alchemer-multi-resort-reviews' ); ?></p>
                    </div>
                </div>
            </div>
            <?php
        }
    }

    /**
     * Render the API settings section
     *
     * @return void
     */
    public function render_api_section() {
        echo '<p>' . __( 'Enter your Alchemer API credentials to connect to your surveys.', 'alchemer-multi-resort-reviews' ) . '</p>';
    }

    /**
     * Render the API Token field
     *
     * @return void
     */
    public function render_api_token_field() {
        $options = get_option( $this->option_name );
        $value = isset( $options['api_token'] ) ? $options['api_token'] : '';
        
        echo '<input type="text" id="api_token" name="' . esc_attr( $this->option_name ) . '[api_token]" value="' . esc_attr( $value ) . '" class="regular-text">';
        echo '<p class="description">' . __( 'Enter your Alchemer API Token (e.g., 3577a1b61ad7ef19043038e6c3ae21d085dbc0d72a33c0b2ca). Do not include any spaces.', 'alchemer-multi-resort-reviews' ) . '</p>';
    }

    /**
     * Render the API Token Secret field
     *
     * @return void
     */
    public function render_api_token_secret_field() {
        $options = get_option( $this->option_name );
        $value = isset( $options['api_token_secret'] ) ? $options['api_token_secret'] : '';
        
        echo '<input type="text" id="api_token_secret" name="' . esc_attr( $this->option_name ) . '[api_token_secret]" value="' . esc_attr( $value ) . '" class="regular-text">';
        echo '<p class="description">' . __( 'Enter your Alchemer API Token Secret (e.g., A9J%2FCA2zvJRcQ). Make sure to copy it exactly as shown in your Alchemer account, including any URL-encoded characters like %2F.', 'alchemer-multi-resort-reviews' ) . '</p>';
        echo '<p class="description" style="color: #d63638;"><strong>' . __( 'Important: Do not modify special characters like %2F in the token. These are part of the API key and must be preserved exactly as shown in your Alchemer account.', 'alchemer-multi-resort-reviews' ) . '</strong></p>';
    }

    /**
     * Render the Survey ID field
     *
     * @return void
     */
    public function render_survey_id_field() {
        $options = get_option( $this->option_name );
        $value = isset( $options['survey_id'] ) ? $options['survey_id'] : '';
        
        echo '<input type="text" id="survey_id" name="' . esc_attr( $this->option_name ) . '[survey_id]" value="' . esc_attr( $value ) . '" class="regular-text">';
        echo '<p class="description">' . __( 'Enter the Alchemer Survey ID to pull reviews from.', 'alchemer-multi-resort-reviews' ) . '</p>';
    }
    
    /**
     * Render the Test Connection field
     *
     * @return void
     */
    public function render_test_connection_field() {
        echo '<button type="button" id="test-alchemer-connection" class="button">' . __( 'Test API Connection', 'alchemer-multi-resort-reviews' ) . '</button>';
        echo '<span class="spinner" id="connection-spinner" style="float: none; margin-top: 0;"></span>';
        echo '<div id="test-connection-result" style="margin-top: 10px;"></div>';
        echo '<p class="description">' . __( 'Sample API call format: https://api.alchemer.com/v5/survey/YOUR_SURVEY_ID/surveyresponse?api_token=YOUR_TOKEN&api_token_secret=YOUR_SECRET', 'alchemer-multi-resort-reviews' ) . '</p>';
    }

    /**
     * Render the settings page with Alchemer API testing.
     *
     * @return void
     */
    public function render_settings_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        ?>
        <div class="wrap">
            <div class="alchemer-admin-area w-full p-6">
                <h1 class="text-2xl font-bold mb-6"><?php echo esc_html( get_admin_page_title() ); ?></h1>
                <!-- API Settings Header -->
                <div class="alchemer-tab-content alchemer-tab-active" id="alchemer-tab">
                    <!-- Alchemer API Settings and Test Button (existing content) -->
                    <div class="dashboard-card w-full fade-in">
                        <h2 class="text-xl font-medium text-gray-800 mb-4"><?php _e('Alchemer API Settings', 'alchemer-multi-resort-reviews'); ?></h2>
                        <p class="text-gray-600 mb-6"><?php _e('Enter your Alchemer API credentials to connect to your surveys.', 'alchemer-multi-resort-reviews'); ?></p>
                        <form action="options.php" method="post" class="w-full">
                            <?php settings_fields( $this->option_group ); ?>
                            <div class="form-input-container w-full">
                                <label for="api_token" class="form-label"><?php _e('API Token', 'alchemer-multi-resort-reviews'); ?></label>
                                <input type="text" id="api_token" name="<?php echo esc_attr($this->option_name); ?>[api_token]" value="<?php echo esc_attr(isset($this->get_settings()['api_token']) ? $this->get_settings()['api_token'] : ''); ?>" class="form-input">
                                <div class="form-help-text"><?php _e('Enter your Alchemer API Token (e.g., 3577a1b61ad7ef19043038e6c3ae21d085dbc0d72a33c0b2ca). Do not include any spaces.', 'alchemer-multi-resort-reviews'); ?></div>
                            </div>
                            <div class="form-input-container w-full">
                                <label for="api_token_secret" class="form-label"><?php _e('API Token Secret', 'alchemer-multi-resort-reviews'); ?></label>
                                <input type="text" id="api_token_secret" name="<?php echo esc_attr($this->option_name); ?>[api_token_secret]" value="<?php echo esc_attr(isset($this->get_settings()['api_token_secret']) ? $this->get_settings()['api_token_secret'] : ''); ?>" class="form-input">
                                <div class="form-help-text"><?php _e('Enter your Alchemer API Token Secret (e.g., A9J%2FCA2zvJRcQ). Make sure to copy it exactly as shown in your Alchemer account, including any URL-encoded characters like %2F.', 'alchemer-multi-resort-reviews'); ?></div>
                                <div class="form-help-text text-red-600 font-medium mt-1"><?php _e('Important: Do not modify special characters like %2F in the token. These are part of the API key and must be preserved exactly as shown in your Alchemer account.', 'alchemer-multi-resort-reviews'); ?></div>
                            </div>
                            <div class="mt-6">
                                <button type="submit" class="alchemer-button alchemer-button-primary"><?php _e('Save Settings', 'alchemer-multi-resort-reviews'); ?></button>
                            </div>
                        </form>
                    </div>
                    <div class="dashboard-card w-full mt-6 fade-in">
                        <h2 class="text-xl font-medium text-gray-800 mb-4"><?php _e('Test API Connection', 'alchemer-multi-resort-reviews'); ?></h2>
                        <p class="text-gray-600 mb-4"><?php _e('Verify your API credentials by testing the connection to Alchemer.', 'alchemer-multi-resort-reviews'); ?></p>
                        <div class="bg-gray-50 p-4 rounded-lg mb-4">
                            <div class="flex items-center">
                                <button type="button" id="test-alchemer-connection" class="alchemer-button alchemer-button-secondary">
                                    <span class="dashicons dashicons-database-view mr-1"></span>
                                    <?php _e('Test API Connection', 'alchemer-multi-resort-reviews'); ?>
                                </button>
                                <div class="spinner ml-3 hidden" id="connection-spinner"></div>
                            </div>
                            <div id="test-connection-result" class="mt-3"></div>
                        </div>
                        <p class="text-sm text-gray-500"><?php _e( 'Survey IDs and field mappings are configured per property under Reviews → Resorts.', 'alchemer-multi-resort-reviews' ); ?></p>
                    </div>
                </div>

            </div>
        </div>
        <?php
    }
    
    /**
     * Enqueue admin scripts
     *
     * @param string $hook Current admin page.
     * @return void
     */
    public function enqueue_admin_scripts( $hook ) {
        // Only enqueue script on plugin settings page
        if ( $hook !== 'amrr-review_page_amrr_settings' ) {
            return;
        }
        
        // Enqueue admin JS
        wp_enqueue_script(
            'alchemer-multi-resort-reviews-admin',
            AMRR_PLUGIN_URL . 'assets/js/admin.js',
            array( 'jquery' ),
            AMRR_VERSION,
            true
        );
        
        wp_localize_script(
            'alchemer-multi-resort-reviews-admin',
            'alchemerReviewsAdmin',
            array(
                'ajaxUrl' => admin_url( 'admin-ajax.php' ),
                'nonce' => wp_create_nonce( 'test_alchemer_api_connection' ),
                'testingText' => __( 'Testing connection...', 'alchemer-multi-resort-reviews' ),
                'errorText' => __( 'Error: ', 'alchemer-multi-resort-reviews' ),
                'successClass' => 'alert alert-success',
                'errorClass' => 'alert alert-error',
            )
        );
    }
    
    /**
     * AJAX handler for testing API connection
     *
     * @return void
     */
    public function ajax_test_api_connection() {
        // Check nonce
        check_ajax_referer( 'test_alchemer_api_connection', 'nonce' );
        
        // Check user capability
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array(
                'message' => __( 'You do not have permission to perform this action.', 'alchemer-multi-resort-reviews' ),
            ) );
        }
        
        // Load API class
        require_once AMRR_PLUGIN_DIR . 'includes/class-amrr-reviews-api.php';
        
        // Test the global account credentials. Survey IDs belong to resorts.
        $settings = $this->get_settings();
        $api = new AMRR_API(
            $settings['api_token'] ?? '',
            $settings['api_token_secret'] ?? '',
            ''
        );
        
        // Test connection
        $result = $api->test_connection();
        
        if ( $result['success'] ) {
            wp_send_json_success( array(
                'message' => $result['message'],
            ) );
        } else {
            wp_send_json_error( array(
                'message' => $result['message'],
            ) );
        }
    }



    /**
     * Get plugin settings
     *
     * @return array
     */
    public function get_settings() {
        return get_option( $this->option_name, array() );
    }

    /**
     * Enqueue Tailwind CSS and plugin styles for admin pages
     *
     * @param string $hook The current admin page.
     * @return void
     */
    public function enqueue_admin_styles($hook) {
        // Only load Tailwind on the plugin settings page
        if ( $hook !== 'amrr-review_page_amrr_settings' ) {
            return;
        }
        
        // Force dequeue conflicting WordPress styles for our admin pages
        wp_dequeue_style('wp-admin');
        wp_dequeue_style('wp-admin-css');
        
        // Register and enqueue Tailwind CSS
        wp_register_style(
            'tailwind-alchemer',
            'https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css',
            array(),
            '2.2.19'
        );
        
        wp_enqueue_style('tailwind-alchemer');
        
        // Register and enqueue custom Tailwind admin CSS
        wp_register_style(
            'alchemer-tailwind-admin',
            AMRR_PLUGIN_URL . 'assets/css/admin-tailwind.css',
            array('tailwind-alchemer'),
            AMRR_VERSION
        );
        
        wp_enqueue_style('alchemer-tailwind-admin');
        
        // Register and enqueue override CSS with highest specificity
        wp_register_style(
            'alchemer-tailwind-override',
            AMRR_PLUGIN_URL . 'assets/css/admin-tailwind-override.css',
            array('tailwind-alchemer', 'alchemer-tailwind-admin'),
            AMRR_VERSION . '.' . time() // Add timestamp to prevent caching
        );
        
        wp_enqueue_style('alchemer-tailwind-override');
    }
}
