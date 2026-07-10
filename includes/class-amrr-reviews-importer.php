<?php
/**
 * Class for importing Alchemer survey responses as reviews
 *
 * @since 1.0.0
 */
class AMRR_Importer {

    /**
     * API instance
     *
     * @var AMRR_API
     */
    private $api;

    /**
     * Settings instance
     *
     * @var AMRR_Settings
     */
    private $settings;

    private $context = array();

    /**
     * Default field mappings
     *
     * @var array
     */
    private $default_field_mappings = array(
        'rating_question' => '',
        'reviewer_name' => '',
        'reviewer_email' => '',
        'review_date' => ''
    );

    /**
     * Constructor
     */
    public function __construct( $context = array() ) {
        // Load dependencies
        require_once AMRR_PLUGIN_DIR . 'includes/class-amrr-reviews-api.php';
        require_once AMRR_PLUGIN_DIR . 'includes/class-amrr-reviews-settings.php';
        
        // Initialize objects
        $this->context = (array) $context;
        $settings = get_option( 'amrr_settings', array() );
        $mappings = array(
            'rating_question' => $this->context['rating_question'] ?? '',
            'reviewer_name' => $this->context['reviewer_name'] ?? '',
        );
        $this->api = new AMRR_API(
            $settings['api_token'] ?? '',
            $settings['api_token_secret'] ?? '',
            $this->context['survey_id'] ?? '',
            $mappings
        );
        $this->settings = new AMRR_Settings();
    }

    /**
     * Initialize the class and set its hooks
     *
     * @return void
     */
    public function init() {
        // Add import button to the settings page
        add_action('amrr_after_settings', array($this, 'render_import_button'));
        
        // Register AJAX handlers for importing reviews
        add_action('wp_ajax_import_alchemer_reviews', array($this, 'ajax_import_reviews'));
        add_action('wp_ajax_process_alchemer_review', array($this, 'ajax_process_review'));
        
        // Add admin menu for field mapping
        add_action('admin_menu', array($this, 'add_field_mapping_page'));
        
        // Register settings for field mappings
        add_action('admin_init', array($this, 'register_field_mapping_settings'));
        
        // The multi-resort manager owns the daily cron and creates a scoped importer per resort.

        // Surface pending imported reviews on the WordPress Dashboard.
        add_action('admin_notices', array($this, 'render_pending_reviews_dashboard_notice'));
        
        // Enqueue Tailwind for admin pages
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_styles'), 100);
    }

    /**
     * Enqueue Tailwind resources for admin pages
     *
     * @param string $hook The current admin page.
     * @return void
     */
    public function enqueue_admin_bootstrap($hook) {
        // Only load on our plugin pages
        if (!in_array($hook, array('amrr-review_page_amrr_field_mapping', 'amrr-review_page_amrr_settings'))) {
            return;
        }
        
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
    }

    /**
     * Enqueue enhanced Tailwind styles with overrides for admin pages
     *
     * @param string $hook The current admin page.
     * @return void
     */
    public function enqueue_admin_styles($hook) {
        // Only load on our plugin pages
        if (!in_array($hook, array('amrr-review_page_amrr_field_mapping', 'amrr-review_page_amrr_settings'))) {
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

    /**
     * Add field mapping page to the Reviews menu
     *
     * @return void
     */
    public function add_field_mapping_page() {
        add_submenu_page(
            'edit.php?post_type=amrr-review',
            __('Tools', 'alchemer-multi-resort-reviews'),
            __('Tools', 'alchemer-multi-resort-reviews'),
            'manage_options',
            'amrr_field_mapping',
            array($this, 'render_field_mapping_page')
        );
    }

    /**
     * Register settings for field mappings
     *
     * @return void
     */
    public function register_field_mapping_settings() {
        register_setting(
            'amrr_field_mappings',
            'amrr_field_mappings',
            array($this, 'sanitize_field_mappings')
        );

        add_settings_section(
            'alchemer_field_mappings',
            __('Map Survey Questions to Review Fields', 'alchemer-multi-resort-reviews'),
            array($this, 'render_field_mappings_section'),
            'amrr_field_mapping'
        );

        // Add field mapping fields
        add_settings_field(
            'rating_question_field',
            __('Rating Question', 'alchemer-multi-resort-reviews'),
            array($this, 'render_rating_question_field'),
            'amrr_field_mapping',
            'alchemer_field_mappings'
        );

        add_settings_field(
            'reviewer_name_field',
            __('Reviewer Name', 'alchemer-multi-resort-reviews'),
            array($this, 'render_reviewer_name_field'),
            'amrr_field_mapping',
            'alchemer_field_mappings'
        );

        add_settings_field(
            'auto_import',
            __('Auto Import', 'alchemer-multi-resort-reviews'),
            array($this, 'render_auto_import_field'),
            'amrr_field_mapping',
            'alchemer_field_mappings'
        );
    }

    /**
     * Sanitize field mappings
     *
     * @param array $input The field mappings input.
     * @return array
     */
    public function sanitize_field_mappings($input) {
        $sanitized_input = array();
        $current_mappings = $this->get_field_mappings();

        foreach ($this->default_field_mappings as $field => $default) {
            if (isset($input[$field])) {
                $sanitized_input[$field] = sanitize_text_field($input[$field]);
            } else {
                $sanitized_input[$field] = isset($current_mappings[$field]) ? $current_mappings[$field] : $default;
            }
        }

        $auto_import_submitted = isset($input['auto_import_submitted']);

        if ($auto_import_submitted) {
            $sanitized_input['auto_import'] = !empty($input['auto_import']);
        } else {
            $sanitized_input['auto_import'] = !empty($current_mappings['auto_import']);
        }

        // Schedule or unschedule the daily import event only when the setting changes or needs repair.
        if ($sanitized_input['auto_import']) {
            if (!wp_next_scheduled('amrr_daily_import')) {
                wp_schedule_event(time() + MINUTE_IN_SECONDS, 'daily', 'amrr_daily_import');
            }
        } elseif ($auto_import_submitted) {
            wp_clear_scheduled_hook('amrr_daily_import');
        }

        return $sanitized_input;
    }

    /**
     * Render field mappings section
     *
     * @return void
     */
    public function render_field_mappings_section() {
        echo '<p class="text-gray-600 text-lg mb-6 w-full">' . __('Map your Alchemer survey questions to the review fields. You need to enter the question IDs from your survey.', 'alchemer-multi-resort-reviews') . '</p>';
        
        // Fetch and display available survey questions if possible
        $survey_questions = $this->get_survey_questions();
        if (!empty($survey_questions)) {
            echo '<div class="dashboard-card w-full">';
            echo '<h3 class="text-lg font-medium text-gray-700 mb-3 flex items-center">';
            echo '<span class="dashicons dashicons-list-view mr-2"></span> ' . __('Available Survey Questions', 'alchemer-multi-resort-reviews');
            echo '</h3>';
            echo '<p class="form-help-text mb-4">' . __('Use the question IDs below for your field mappings.', 'alchemer-multi-resort-reviews') . '</p>';
            echo '<div class="overflow-x-auto">';
            echo '<table class="alchemer-table">';
            echo '<thead><tr><th class="w-24">' . __('Question ID', 'alchemer-multi-resort-reviews') . '</th><th>' . __('Question Text', 'alchemer-multi-resort-reviews') . '</th></tr></thead>';
            echo '<tbody>';
            
            foreach ($survey_questions as $id => $text) {
                echo '<tr>';
                echo '<td><code class="bg-gray-100 px-2 py-1 rounded">' . esc_html($id) . '</code></td>';
                echo '<td>' . esc_html($text) . '</td>';
                echo '</tr>';
            }
            
            echo '</tbody></table>';
            echo '</div>';
            echo '</div>';
        }
    }

    /**
     * Render rating question field
     *
     * @return void
     */
    public function render_rating_question_field() {
        $options = $this->get_field_mappings();
        $value = isset($options['rating_question']) ? $options['rating_question'] : '';
        
        echo '<div class="form-input-container w-full">';
        echo '<input type="text" id="rating_question_field" name="amrr_field_mappings[rating_question]" value="' . esc_attr($value) . '" class="form-input">';
        echo '<div class="form-help-text">' . __('Enter the ID of the rating question (e.g., "83"). This is the question that contains both the rating value and the review comments.', 'alchemer-multi-resort-reviews') . '</div>';
        echo '</div>';
    }

    /**
     * Render reviewer name field
     *
     * @return void
     */
    public function render_reviewer_name_field() {
        $options = $this->get_field_mappings();
        $value = isset($options['reviewer_name']) ? $options['reviewer_name'] : '';
        
        echo '<div class="form-input-container w-full">';
        echo '<input type="text" id="reviewer_name_field" name="amrr_field_mappings[reviewer_name]" value="' . esc_attr($value) . '" class="form-input">';
        echo '<div class="form-help-text">' . __('Enter the question ID for the reviewer\'s name.', 'alchemer-multi-resort-reviews') . '</div>';
        echo '</div>';
    }

    /**
     * Render auto import field
     *
     * @return void
     */
    public function render_auto_import_field() {
        $options = $this->get_field_mappings();
        $checked = isset($options['auto_import']) && $options['auto_import'] ? 'checked' : '';
        
        echo '<div class="form-input-container w-full">';
        echo '<div class="flex items-center">';
        echo '<input type="hidden" name="amrr_field_mappings[auto_import_submitted]" value="1">';
        echo '<input class="h-4 w-4 text-blue-600 rounded border-gray-300 focus:ring-blue-500" type="checkbox" id="auto_import" name="amrr_field_mappings[auto_import]" value="1" ' . $checked . '>';
        echo '<label class="ml-2 block text-sm text-gray-700" for="auto_import">' . __('Automatically import new reviews daily', 'alchemer-multi-resort-reviews') . '</label>';
        echo '</div>';
        echo '<div class="form-help-text">' . __('When enabled, the plugin will check for new survey responses daily and import them as reviews.', 'alchemer-multi-resort-reviews') . '</div>';
        echo '</div>';
    }

    /**
     * Render field mapping page
     *
     * @return void
     */
    public function render_field_mapping_page() {
        if (!current_user_can('manage_options')) {
            return;
        }

        // Get current tab
        $current_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'field_mapping';
        
        ?>
        <div class="wrap">
            <div class="alchemer-admin-area w-full p-6">
                <h1 class="text-2xl font-bold mb-6"><?php echo esc_html(get_admin_page_title()); ?></h1>
                
                <?php
                // Display connection status
                $settings = $this->settings->get_settings();
                if (empty($settings['api_token']) || empty($settings['api_token_secret']) || empty($settings['survey_id'])) {
                    ?>
                    <div class="alert alert-warning flex items-center">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 mr-3 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                        </svg>
                        <div>
                            <?php _e('API connection is not configured. Please go to the Settings page to set up your Alchemer API connection first.', 'alchemer-multi-resort-reviews'); ?>
                        </div>
                    </div>
                    <?php
                }
                ?>
                
                <div class="tab-nav mb-8">
                    <a href="<?php echo admin_url('edit.php?post_type=amrr-review&page=amrr_field_mapping&tab=field_mapping'); ?>" 
                       class="tab-link <?php echo $current_tab === 'field_mapping' ? 'active' : ''; ?>">
                        <span class="dashicons dashicons-editor-table mr-1"></span>
                        <?php _e('Field Mapping', 'alchemer-multi-resort-reviews'); ?>
                    </a>
                    <a href="<?php echo admin_url('edit.php?post_type=amrr-review&page=amrr_field_mapping&tab=import_reviews'); ?>" 
                       class="tab-link <?php echo $current_tab === 'import_reviews' ? 'active' : ''; ?>">
                        <span class="dashicons dashicons-download mr-1"></span>
                        <?php _e('Import Reviews', 'alchemer-multi-resort-reviews'); ?>
                    </a>
                </div>

                <?php if ($current_tab === 'field_mapping'): ?>
                
                <div class="dashboard-card w-full fade-in">
                    <h2 class="text-xl font-medium text-gray-800 mb-4">
                        <?php _e('Map Survey Questions to Review Fields', 'alchemer-multi-resort-reviews'); ?>
                    </h2>
                    <form action="options.php" method="post" class="w-full">
                        <?php
                        settings_fields('amrr_field_mappings');
                        do_settings_sections('amrr_field_mapping');
                        ?>
                        <div class="mt-6">
                            <button type="submit" class="alchemer-button alchemer-button-primary">
                                <?php _e('Save Field Mappings', 'alchemer-multi-resort-reviews'); ?>
                            </button>
                        </div>
                    </form>
                </div>
                
                <?php elseif ($current_tab === 'import_reviews'): ?>
                
                <div class="dashboard-card w-full fade-in">
                    <h2 class="text-xl font-medium text-gray-800 mb-4">
                        <?php _e('Import Reviews from Alchemer', 'alchemer-multi-resort-reviews'); ?>
                    </h2>
                    <p class="text-gray-600 mb-6">
                        <?php _e('Click the button below to manually import reviews from your Alchemer survey.', 'alchemer-multi-resort-reviews'); ?>
                    </p>
                    
                    <?php
                    $mappings = $this->get_field_mappings();
                    if (empty($mappings['rating_question'])) {
                        ?>
                        <div class="alert alert-warning">
                            <p class="mb-3"><?php _e('Please configure your field mappings before importing reviews.', 'alchemer-multi-resort-reviews'); ?></p>
                            <a href="<?php echo admin_url('edit.php?post_type=amrr-review&page=amrr_field_mapping&tab=field_mapping'); ?>" 
                               class="alchemer-button alchemer-button-primary">
                                <span class="dashicons dashicons-editor-table mr-1"></span>
                                <?php _e('Configure Field Mappings', 'alchemer-multi-resort-reviews'); ?>
                            </a>
                        </div>
                        <?php
                    } else {
                        ?>
                        <div class="bg-gray-50 p-6 rounded-lg mb-6">
                            <h3 class="text-lg font-medium text-gray-700 mb-4">
                                <?php _e('Import Settings', 'alchemer-multi-resort-reviews'); ?>
                            </h3>
                            
                            <!-- Import Filters -->
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                                <!-- Maximum Reviews -->
                                <div>
                                    <label for="max-reviews" class="block text-sm font-medium text-gray-700 mb-1">
                                        <?php _e('Maximum Reviews to Import', 'alchemer-multi-resort-reviews'); ?>
                                    </label>
                                    <div class="flex items-center">
                                        <input type="number" id="max-reviews" name="max_reviews" min="1" max="100" value="10"
                                               class="form-input block w-full sm:text-sm rounded-md" />
                                        <div class="ml-2 text-sm text-gray-500">
                                            <?php _e('(1-100)', 'alchemer-multi-resort-reviews'); ?>
                                        </div>
                                    </div>
                                    <p class="text-xs text-gray-500 mt-1">
                                        <?php _e('The importer scans newest responses first and keeps paginating until it finds this many net-new reviews or reaches the last API page.', 'alchemer-multi-resort-reviews'); ?>
                                    </p>
                                </div>
                                
                                <!-- Filter by Rating -->
                                <div>
                                    <label for="target-rating" class="block text-sm font-medium text-gray-700 mb-1">
                                        <?php _e('Filter by Rating', 'alchemer-multi-resort-reviews'); ?>
                                    </label>
                                    <div class="flex items-center">
                                        <select id="target-rating" name="target_rating" class="form-input block w-full sm:text-sm rounded-md">
                                            <option value="0"><?php _e('All Ratings', 'alchemer-multi-resort-reviews'); ?></option>
                                            <option value="5">★★★★★ (5 <?php _e('stars only', 'alchemer-multi-resort-reviews'); ?>)</option>
                                            <option value="4">★★★★☆ (4 <?php _e('stars only', 'alchemer-multi-resort-reviews'); ?>)</option>
                                            <option value="3">★★★☆☆ (3 <?php _e('stars only', 'alchemer-multi-resort-reviews'); ?>)</option>
                                            <option value="2">★★☆☆☆ (2 <?php _e('stars only', 'alchemer-multi-resort-reviews'); ?>)</option>
                                            <option value="1">★☆☆☆☆ (1 <?php _e('star only', 'alchemer-multi-resort-reviews'); ?>)</option>
                                        </select>
                                    </div>
                                    <p class="text-xs text-gray-500 mt-1">
                                        <?php _e('Optionally filter reviews by specific rating. The importer will search multiple pages if needed to find your target number of reviews.', 'alchemer-multi-resort-reviews'); ?>
                                    </p>
                                </div>
                            </div>
                            
                            <!-- Import Button -->
                            <div class="text-center mt-6">
                                <button type="button" id="import-alchemer-multi-resort-reviews" class="alchemer-button alchemer-button-primary alchemer-button-lg">
                                    <span class="dashicons dashicons-download mr-1"></span>
                                    <?php _e('Import Reviews Now', 'alchemer-multi-resort-reviews'); ?>
                                </button>
                                <div class="spinner mt-4 hidden" id="import-spinner"></div>
                            </div>
                        </div>
                        
                        <div id="import-result" class="hidden">
                            <!-- Results will be loaded here via JavaScript -->
                        </div>
                        <?php
                    }
                    ?>
                </div>
                
                <!-- Auto Import Section -->
                <div class="dashboard-card w-full mt-6 fade-in">
                    <h2 class="text-xl font-medium text-gray-800 mb-4">
                        <?php _e('Automatic Import Settings', 'alchemer-multi-resort-reviews'); ?>
                    </h2>
                    <form action="options.php" method="post" class="w-full">
                        <?php
                        settings_fields('amrr_field_mappings');
                        $options = $this->get_field_mappings();
                        $checked = isset($options['auto_import']) && $options['auto_import'] ? 'checked' : '';
                        ?>
                        <input type="hidden" name="amrr_field_mappings[auto_import_submitted]" value="1">
                        
                        <div class="flex items-center mb-4">
                            <input class="h-4 w-4 text-blue-600 rounded border-gray-300 focus:ring-blue-500" 
                                   type="checkbox" id="auto_import_option" 
                                   name="amrr_field_mappings[auto_import]" 
                                   value="1" <?php echo $checked; ?>>
                            <label class="ml-2 text-gray-700" for="auto_import_option">
                                <?php _e('Automatically import new reviews daily', 'alchemer-multi-resort-reviews'); ?>
                            </label>
                        </div>
                        <p class="text-sm text-gray-500 mb-4">
                            <?php _e('When enabled, the plugin will check for new survey responses daily and import them as reviews.', 'alchemer-multi-resort-reviews'); ?>
                        </p>
                        
                        <button type="submit" class="alchemer-button alchemer-button-secondary">
                            <?php _e('Save Auto-Import Setting', 'alchemer-multi-resort-reviews'); ?>
                        </button>
                    </form>
                </div>
                
                <?php endif; ?>
            </div>
        </div>
        
        <?php
        // Enqueue the admin script
        wp_enqueue_script(
            'alchemer-multi-resort-reviews-importer',
            AMRR_PLUGIN_URL . 'assets/js/importer.js',
            array('jquery'),
            AMRR_VERSION,
            true
        );
        
        wp_localize_script(
            'alchemer-multi-resort-reviews-importer',
            'alchemerReviewsImporter',
            array(
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('import_alchemer_reviews'),
                'importingText' => __('Importing reviews from Alchemer...', 'alchemer-multi-resort-reviews'),
                'errorText' => __('Error: ', 'alchemer-multi-resort-reviews'),
                'createdText' => __('New Reviews', 'alchemer-multi-resort-reviews'),
                'updatedText' => __('Updated', 'alchemer-multi-resort-reviews'),
                'skippedText' => __('Skipped', 'alchemer-multi-resort-reviews'),
                'starsOnlyText' => __('stars only', 'alchemer-multi-resort-reviews'),
                'filteredText' => __('Filtered by rating', 'alchemer-multi-resort-reviews'),
            )
        );

        // Enqueue import-specific CSS
        wp_enqueue_style(
            'alchemer-import-styles',
            AMRR_PLUGIN_URL . 'assets/css/admin-import.css',
            array(),
            AMRR_VERSION . '.' . time()
        );
    }

    /**
     * Render import button on settings page
     *
     * @return void
     */
    public function render_import_button() {
        ?>
        <div class="dashboard-card w-full mt-6 fade-in">
            <h2 class="text-xl font-medium text-gray-800 mb-4">
                <?php _e('Import Reviews', 'alchemer-multi-resort-reviews'); ?>
            </h2>
            <p class="text-gray-600 mb-4">
                <?php _e('Go to the Tools section to import reviews from your Alchemer survey.', 'alchemer-multi-resort-reviews'); ?>
            </p>
            
            <div class="flex items-center mt-4">
                <a href="<?php echo admin_url('edit.php?post_type=amrr-review&page=amrr_field_mapping&tab=import_reviews'); ?>" 
                   class="alchemer-button alchemer-button-primary">
                    <span class="dashicons dashicons-download mr-1"></span>
                    <?php _e('Go to Import Reviews', 'alchemer-multi-resort-reviews'); ?>
                </a>
            </div>
        </div>
        <?php
    }

    /**
     * AJAX handler for importing reviews
     *
     * @return void
     */
    public function ajax_import_reviews() {
        // Check nonce
        check_ajax_referer('import_alchemer_reviews', 'nonce');
        
        // Check user capability
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array(
                'message' => __('You do not have permission to perform this action.', 'alchemer-multi-resort-reviews'),
            ));
        }
        
        // Get import parameters. Manual imports are always net-new and max-bound.
        $max_reviews = isset($_POST['max_reviews']) ? max(1, intval($_POST['max_reviews'])) : 20;
        $target_rating = isset($_POST['target_rating']) ? intval($_POST['target_rating']) : 0;
        
        // Prepare import arguments
        $import_args = array(
            'max_reviews' => $max_reviews,
            'target_rating' => $target_rating,
            'import_all_new' => false,
        );
        
        // Import reviews
        $result = $this->import_reviews($import_args);
        
        if ($result['success']) {
            wp_send_json_success(array(
                'message' => $result['message'],
                'reviews' => $result['reviews'],
                'total_found' => $result['total_found'],
                'skipped' => $result['skipped'],
                'skipped_existing' => isset($result['skipped_existing']) ? $result['skipped_existing'] : 0,
                'skipped_no_content' => isset($result['skipped_no_content']) ? $result['skipped_no_content'] : 0,
                'skipped_wrong_rating' => isset($result['skipped_wrong_rating']) ? $result['skipped_wrong_rating'] : 0,
                'pages_fetched' => isset($result['pages_fetched']) ? $result['pages_fetched'] : 0,
                'import_all_new' => !empty($result['import_all_new'])
            ));
        } else {
            wp_send_json_error(array(
                'message' => $result['message'],
            ));
        }
    }

    /**
     * AJAX handler for processing a single review
     *
     * @return void
     */
    public function ajax_process_review() {
        // Check nonce
        check_ajax_referer('import_alchemer_reviews', 'nonce');
        
        // Check user capability
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array(
                'message' => __('You do not have permission to perform this action.', 'alchemer-multi-resort-reviews'),
            ));
        }
        
        // Get review data
        $review_data = isset($_POST['review_data']) ? $_POST['review_data'] : array();
        $accept = isset($_POST['accept']) ? (bool) $_POST['accept'] : false;
        $edited = isset($_POST['edited']) ? (bool) $_POST['edited'] : false;
        
        if (empty($review_data)) {
            wp_send_json_error(array(
                'message' => __('No review data provided.', 'alchemer-multi-resort-reviews'),
            ));
        }
        
        // Process the review
        $result = $this->process_review($review_data, $accept, $edited);
        
        if ($result['success']) {
            wp_send_json_success(array(
                'message' => sprintf(
                    __('Review %s successfully.', 'alchemer-multi-resort-reviews'),
                    $accept ? 'accepted' : 'rejected and saved as draft'
                ),
                'post_id' => $result['post_id'],
                'status' => $result['status']
            ));
        } else {
            wp_send_json_error(array(
                'message' => $result['message'],
            ));
        }
    }

    /**
     * Import reviews from Alchemer
     *
     * @param array $import_args Arguments for importing
     * @return array Result with success status, message and count info
     */
    public function import_reviews($import_args = array()) {
        // Check if API settings are configured
        $settings = $this->settings->get_settings();
        $survey_id = $this->context['survey_id'] ?? ( $settings['survey_id'] ?? '' );
        if (empty($settings['api_token']) || empty($settings['api_token_secret']) || empty($survey_id)) {
            return array(
                'success' => false,
                'message' => __('API connection is not configured. Please go to the Settings page to set up your Alchemer API connection.', 'alchemer-multi-resort-reviews'),
                'imported_count' => 0,
            );
        }
        
        // Check if field mappings are configured
        $mappings = $this->get_field_mappings();
        if (empty($mappings['rating_question'])) {
            return array(
                'success' => false,
                'message' => __('Field mappings are not configured. Please go to the Field Mapping page to set up your field mappings.', 'alchemer-multi-resort-reviews'),
                'imported_count' => 0,
            );
        }
        
        // Default import arguments
        $default_args = array(
            'max_reviews' => 20,
            'target_rating' => 0, // 0 means all ratings
            'minimum_rating' => 0,
            'import_all_new' => false,
            'stop_at_existing' => false,
        );
        
        // Merge with provided arguments
        $import_args = wp_parse_args($import_args, $default_args);
        
        // Extract import arguments
        $import_all_new = !empty($import_args['import_all_new']) || intval($import_args['max_reviews']) <= 0;
        $max_reviews = $import_all_new ? 0 : max(1, intval($import_args['max_reviews']));
        $target_rating = intval($import_args['target_rating']);
        $minimum_rating = intval($import_args['minimum_rating']);
        $stop_at_existing = !empty($import_args['stop_at_existing']);
        $exclude_response_ids = isset($import_args['exclude_response_ids'])
            ? array_filter(array_map('strval', (array) $import_args['exclude_response_ids']))
            : $this->get_existing_response_ids();
        
        // Initialize counters
        $skipped_count = 0;
        
        // Get the responses from the API
        $responses = $this->api->get_filtered_responses(array(
            'stop_at_existing' => $stop_at_existing,
            'minimum_rating' => $minimum_rating,
        ), $max_reviews, $target_rating, $exclude_response_ids);
        
        if (!$responses['success']) {
            return array(
                'success' => false,
                'message' => $responses['message'],
                'imported_count' => 0,
            );
        }
        
        if (empty($responses['data'])) {
            $skipped_existing = isset($responses['skipped_existing']) ? intval($responses['skipped_existing']) : 0;
            $skipped_no_content = isset($responses['skipped_no_content']) ? intval($responses['skipped_no_content']) : 0;
            $skipped_wrong_rating = isset($responses['skipped_wrong_rating']) ? intval($responses['skipped_wrong_rating']) : 0;
            $pages_fetched = isset($responses['pages_fetched']) ? intval($responses['pages_fetched']) : 0;
            $message = !empty($responses['message']) ? $responses['message'] : __('No new responses found to import.', 'alchemer-multi-resort-reviews');

            if ($skipped_existing > 0) {
                $message .= ' ' . __('All fetched matching responses already exist in WordPress.', 'alchemer-multi-resort-reviews');
            } elseif ($skipped_no_content > 0) {
                $message .= ' ' . __('No review cards were created because fetched responses did not have a usable rating/comment field. Check the Rating Question mapping.', 'alchemer-multi-resort-reviews');
            }

            return array(
                'success' => true,
                'message' => $message,
                'imported_count' => 0,
                'reviews' => array(),
                'total_found' => 0,
                'skipped' => $skipped_existing,
                'skipped_existing' => $skipped_existing,
                'skipped_no_content' => $skipped_no_content,
                'skipped_wrong_rating' => $skipped_wrong_rating,
                'pages_fetched' => $pages_fetched,
                'import_all_new' => $import_all_new,
                'reached_existing_boundary' => !empty($responses['reached_existing_boundary']),
            );
        }

        // Process responses and prepare for review
        $reviews_to_process = array();
        foreach ($responses['data'] as $response) {
            // Get basic review data
            $review_data = $this->prepare_review_data($response);
            
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('=== Processing Review ===');
                error_log('Review Data: ' . print_r($review_data, true));
            }
            
            if (!$review_data['success']) {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('Failed to prepare review data: ' . $review_data['message']);
                }
                $skipped_count++;
                continue;
            }

            $response_id = isset($review_data['data']['response_id']) ? strval($review_data['data']['response_id']) : '';
            if ($response_id !== '' && in_array($response_id, $exclude_response_ids, true)) {
                $skipped_count++;
                continue;
            }

            if ($response_id !== '') {
                $exclude_response_ids[] = $response_id;
            }
            
            // Add to reviews to process
            $reviews_to_process[] = array(
                'response' => $response,
                'review_data' => $review_data['data']
            );
        }

        // Return the reviews for processing
        $skipped_existing = isset($responses['skipped_existing']) ? intval($responses['skipped_existing']) : 0;

        return array(
            'success' => true,
            'message' => sprintf(__('Found %d new reviews to process.', 'alchemer-multi-resort-reviews'), count($reviews_to_process)),
            'reviews' => $reviews_to_process,
            'total_found' => count($reviews_to_process),
            'skipped' => $skipped_count + $skipped_existing,
            'skipped_existing' => $skipped_existing,
            'skipped_no_content' => isset($responses['skipped_no_content']) ? intval($responses['skipped_no_content']) : 0,
            'skipped_wrong_rating' => isset($responses['skipped_wrong_rating']) ? intval($responses['skipped_wrong_rating']) : 0,
            'pages_fetched' => isset($responses['pages_fetched']) ? intval($responses['pages_fetched']) : 0,
            'import_all_new' => !empty($responses['import_all_new']),
            'reached_existing_boundary' => !empty($responses['reached_existing_boundary']),
        );
    }

    /**
     * Daily cron sync: fetch unseen responses and save them as pending draft reviews.
     *
     * @param array $sync_args Optional sync arguments.
     * @return array Sync result.
     */
    public function run_daily_sync($sync_args = array()) {
        $default_args = array(
            'max_reviews' => 0,
            'target_rating' => 0,
            'import_all_new' => true,
            'stop_at_existing' => true,
        );

        $sync_args = wp_parse_args($sync_args, $default_args);
        $result = $this->import_reviews($sync_args);

        if (!$result['success']) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('Alchemer daily sync failed: ' . $result['message']);
            }

            return $result;
        }

        $created_count = 0;
        $skipped_count = isset($result['skipped']) ? intval($result['skipped']) : 0;
        $errors = array();

        foreach ($result['reviews'] as $review) {
            if (empty($review['review_data'])) {
                $skipped_count++;
                continue;
            }

            $save_result = $this->save_synced_review_as_pending($review['review_data']);

            if ($save_result['success']) {
                if (empty($save_result['skipped'])) {
                    $created_count++;
                } else {
                    $skipped_count++;
                }
            } else {
                $errors[] = $save_result['message'];
            }
        }

        update_option('amrr_last_sync_at', current_time('mysql'));
        update_option('amrr_last_sync_result', array(
            'imported_count' => $created_count,
            'skipped' => $skipped_count,
            'pages_fetched' => isset($result['pages_fetched']) ? intval($result['pages_fetched']) : 0,
            'reached_existing_boundary' => !empty($result['reached_existing_boundary']),
        ));

        return array(
            'success' => empty($errors),
            'message' => sprintf(
                __('Daily sync saved %d new pending reviews and skipped %d existing reviews.', 'alchemer-multi-resort-reviews'),
                $created_count,
                $skipped_count
            ),
            'imported_count' => $created_count,
            'skipped' => $skipped_count,
            'pages_fetched' => isset($result['pages_fetched']) ? intval($result['pages_fetched']) : 0,
            'import_all_new' => !empty($result['import_all_new']),
            'reached_existing_boundary' => !empty($result['reached_existing_boundary']),
            'errors' => $errors,
        );
    }

    /**
     * Show a Dashboard notice when imported reviews are waiting for approval.
     *
     * @return void
     */
    public function render_pending_reviews_dashboard_notice() {
        if (!current_user_can('edit_posts')) {
            return;
        }

        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        if (!$screen || $screen->id !== 'dashboard') {
            return;
        }

        $pending_count = $this->count_pending_reviews();
        if ($pending_count < 1) {
            return;
        }

        $review_queue_url = admin_url('edit.php?post_type=amrr-review&post_status=draft');
        ?>
        <div class="notice notice-info">
            <p>
                <?php
                printf(
                    esc_html(_n(
                        '%d Alchemer review is pending for review.',
                        '%d Alchemer reviews are pending for review.',
                        $pending_count,
                        'alchemer-multi-resort-reviews'
                    )),
                    intval($pending_count)
                );
                ?>
                <a href="<?php echo esc_url($review_queue_url); ?>"><?php esc_html_e('Review now', 'alchemer-multi-resort-reviews'); ?></a>
            </p>
        </div>
        <?php
    }

    /**
     * Count imported reviews still waiting for approval.
     *
     * @return int Pending review count.
     */
    private function count_pending_reviews() {
        $query = new WP_Query(array(
            'post_type' => 'amrr-review',
            'post_status' => array('draft', 'pending'),
            'posts_per_page' => 1,
            'fields' => 'ids',
            'no_found_rows' => false,
            'meta_query' => array(
                'relation' => 'OR',
                array(
                    'key' => '_alchemer_reviewed',
                    'value' => '0',
                ),
                array(
                    'key' => '_alchemer_review_decision',
                    'value' => 'pending',
                ),
            ),
        ));

        return intval($query->found_posts);
    }

    /**
     * Save a synced response as an unreviewed draft.
     *
     * @param array $review_data Prepared review data.
     * @return array Save result.
     */
    private function save_synced_review_as_pending($review_data) {
        $review_data = isset($review_data['data']) ? $review_data['data'] : $review_data;
        $response_id = isset($review_data['response_id']) ? sanitize_text_field($review_data['response_id']) : '';

        if (empty($response_id)) {
            return array(
                'success' => false,
                'message' => __('Missing response ID', 'alchemer-multi-resort-reviews'),
            );
        }

        if ($this->get_review_by_response_id($response_id)) {
            return array(
                'success' => true,
                'skipped' => true,
                'message' => __('Review already exists.', 'alchemer-multi-resort-reviews'),
            );
        }

        $reviewer_name = isset($review_data['reviewer_name']) ? sanitize_text_field($review_data['reviewer_name']) : __('Anonymous', 'alchemer-multi-resort-reviews');
        $content = isset($review_data['content']) ? wp_kses_post($review_data['content']) : '';
        $post_date = isset($review_data['post_date']) ? sanitize_text_field($review_data['post_date']) : current_time('mysql');

        if (empty($content)) {
            return array(
                'success' => false,
                'message' => __('Missing review content', 'alchemer-multi-resort-reviews'),
            );
        }

        $post_id = wp_insert_post(array(
            'post_title' => $reviewer_name,
            'post_content' => $content,
            'post_status' => 'draft',
            'post_type' => 'amrr-review',
            'post_date' => $post_date,
        ));

        if (is_wp_error($post_id)) {
            return array(
                'success' => false,
                'message' => $post_id->get_error_message(),
            );
        }

        $this->save_review_meta($post_id, $review_data, array(
            'reviewed' => '0',
            'decision' => 'pending',
            'manual_protection' => '0',
        ));

        return array(
            'success' => true,
            'post_id' => $post_id,
            'status' => 'pending',
            'skipped' => false,
        );
    }

    /**
     * Prepare review data from response
     *
     * @param array $response Survey response data
     * @return array Review data
     */
    private function prepare_review_data($response) {
        // Debug logging for incoming response
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('=== Preparing Review Data ===');
            error_log('Response ID: ' . (isset($response['id']) ? $response['id'] : 'unknown'));
            error_log('Raw response structure: ' . print_r($response, true));
        }

        // Get field mappings
        $mappings = $this->get_field_mappings();
        $rating_question_id = $mappings['rating_question'];
        $reviewer_name_field = $mappings['reviewer_name'];
        
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Rating Question ID: ' . $rating_question_id);
            error_log('Reviewer Name Field: ' . $reviewer_name_field);
        }
        
        // Get survey data
        $survey_data = isset($response['survey_data']) ? $response['survey_data'] : $response;
        
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Survey Data Keys: ' . implode(', ', array_keys($survey_data)));
        }
        
        $resolved_question = $this->resolve_review_question($survey_data, $rating_question_id);

        if (!$resolved_question) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('Review rating/comment question not found. Available keys: ' . implode(', ', array_keys($survey_data)));
            }
            return array(
                'success' => false,
                'message' => sprintf(__('No valid rating/comment question found. Please verify the mapped rating question (currently ID: %s).', 'alchemer-multi-resort-reviews'), $rating_question_id)
            );
        }
        
        // Get rating question data
        $rating_question = $resolved_question['question'];
        
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Resolved Rating Question ID: ' . $resolved_question['id']);
            error_log('Rating Question Data: ' . print_r($rating_question, true));
        }
        
        // Extract rating
        $rating = $this->extract_rating_value_from_question($rating_question);
        
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Extracted Rating: ' . $rating);
        }
        
        if ($rating <= 0) {
            return array(
                'success' => false,
                'message' => __('No rating value found', 'alchemer-multi-resort-reviews')
            );
        }
        
        // Extract content - try multiple possible locations
        $content = '';
        
        // First try the rating question's comments
        $resolved_content = $this->get_content_from_question($rating_question);
        if (!empty($resolved_content)) {
            $content = $resolved_content;
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('Found content in resolved rating question: ' . substr($content, 0, 100));
            }
        }
        
        // If no content in rating question, try response level comments
        if (empty($content)) {
            if (isset($response['comments']) && !empty($response['comments'])) {
            $content = $response['comments'];
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('Found content in response[comments]: ' . substr($content, 0, 100));
                }
            } elseif (isset($response['comment']) && !empty($response['comment'])) {
            $content = $response['comment'];
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('Found content in response[comment]: ' . substr($content, 0, 100));
                }
            }
        }
        
        // If still no content, try looking in survey_data for text fields
        if (empty($content) && is_array($survey_data)) {
            foreach ($survey_data as $key => $value) {
                if (is_array($value)) {
                    if (isset($value['comments']) && !empty($value['comments'])) {
                        $content = $value['comments'];
                        if (defined('WP_DEBUG') && WP_DEBUG) {
                            error_log("Found content in survey_data[{$key}][comments]: " . substr($content, 0, 100));
                        }
                        break;
                    } elseif (isset($value['comment']) && !empty($value['comment'])) {
                        $content = $value['comment'];
                        if (defined('WP_DEBUG') && WP_DEBUG) {
                            error_log("Found content in survey_data[{$key}][comment]: " . substr($content, 0, 100));
                        }
                        break;
                    }
                } elseif (is_string($value) && strlen($value) > 10) {
                    $content = $value;
                    if (defined('WP_DEBUG') && WP_DEBUG) {
                        error_log("Found content in survey_data[{$key}]: " . substr($content, 0, 100));
                    }
                    break;
                }
            }
        }
        
        if (empty($content)) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('No content found in any location');
                error_log('Survey Data Structure: ' . print_r($survey_data, true));
            }
            return array(
                'success' => false,
                'message' => __('No review content found in any expected location', 'alchemer-multi-resort-reviews')
            );
        }
        
        // Extract reviewer name
        $reviewer_name = __('Anonymous', 'alchemer-multi-resort-reviews');
        if (!empty($reviewer_name_field)) {
            if (isset($survey_data[$reviewer_name_field])) {
                $name_data = $survey_data[$reviewer_name_field];
                if (is_array($name_data) && isset($name_data['answer'])) {
                    $reviewer_name = $name_data['answer'];
                } elseif (is_string($name_data)) {
                    $reviewer_name = $name_data;
                }
            } elseif (isset($response[$reviewer_name_field])) {
                $reviewer_name = $response[$reviewer_name_field];
            }
        }

        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Extracted Reviewer Name: ' . $reviewer_name);
        }

        // Format the review date from date_submitted
        $review_date = '';
        if (isset($response['date_submitted'])) {
            try {
                $date = new DateTime($response['date_submitted']);
                $review_date = $date->format('F j, Y'); // Format as "May 4, 2025"
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('Formatted review date: ' . $review_date);
                }
            } catch (Exception $e) {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('Error formatting review date: ' . $e->getMessage());
                }
                $review_date = current_time('F j, Y');
            }
        } else {
            $review_date = current_time('F j, Y');
        }
        
        // Prepare the review data with a property-scoped unique ID.
        $property_slug = sanitize_title( $this->context['slug'] ?? '' );
        $property_name = sanitize_text_field( $this->context['name'] ?? '' );
        $response_id = isset($response['id']) ? strval($response['id']) : uniqid('review_');
        $review_data = array(
            'response_id' => $response_id,
            'unique_id' => $property_slug ? $property_slug . '-' . $response_id : $response_id,
            'site_id' => sanitize_text_field( $this->context['survey_id'] ?? '' ),
            'property_slug' => $property_slug,
            'property_name' => $property_name,
            'rating' => $rating,
            'content' => $content,
            'reviewer_name' => $reviewer_name,
            'post_date' => isset($response['date_submitted']) ? $response['date_submitted'] : current_time('mysql'),
            'review_date' => $review_date
        );
        
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Final Review Data: ' . print_r($review_data, true));
            error_log('=== End Preparing Review Data ===');
        }
        
        return array(
            'success' => true,
            'data' => $review_data
        );
    }

    /**
     * Get a review post by its Alchemer response ID
     *
     * @param string $response_id The Alchemer response ID
     * @return WP_Post|null The review post if found, null otherwise
     */
    private function get_review_by_response_id($response_id) {
        $meta_query = array(
            array( 'key' => '_alchemer_response_id', 'value' => $response_id ),
        );
        if ( ! empty( $this->context['survey_id'] ) ) {
            $meta_query[] = array( 'key' => '_alchemer_site_id', 'value' => sanitize_text_field( $this->context['survey_id'] ) );
        }
        $args = array(
            'post_type' => 'amrr-review',
            'meta_query' => $meta_query,
            'posts_per_page' => 1,
            'post_status' => array('publish', 'future', 'draft', 'pending', 'private', 'trash')
        );

        $query = new WP_Query($args);
        
        if ($query->have_posts()) {
            return $query->posts[0];
        }
        
        return null;
    }

    /**
     * Get all response IDs already represented by review posts.
     *
     * @return array Response IDs.
     */
    private function get_existing_response_ids() {
        $meta_query = array( array( 'key' => '_alchemer_response_id', 'compare' => 'EXISTS' ) );
        if ( ! empty( $this->context['survey_id'] ) ) {
            $meta_query[] = array( 'key' => '_alchemer_site_id', 'value' => sanitize_text_field( $this->context['survey_id'] ) );
        }
        $post_ids = get_posts(array(
            'post_type' => 'amrr-review',
            'post_status' => array('publish', 'future', 'draft', 'pending', 'private', 'trash'),
            'posts_per_page' => -1,
            'fields' => 'ids',
            'meta_query' => $meta_query,
        ));

        $response_ids = array();
        foreach ($post_ids as $post_id) {
            $response_id = get_post_meta($post_id, '_alchemer_response_id', true);
            if ($response_id !== '') {
                $response_ids[] = strval($response_id);
            }
        }

        return array_values(array_unique($response_ids));
    }

    /**
     * Save normalized review metadata.
     *
     * @param int $post_id Review post ID.
     * @param array $review_data Prepared review data.
     * @param array $state Review state values.
     * @return void
     */
    private function save_review_meta($post_id, $review_data, $state = array()) {
        $review_data = isset($review_data['data']) ? $review_data['data'] : $review_data;

        $response_id = isset($review_data['response_id']) ? sanitize_text_field($review_data['response_id']) : '';
        $reviewer_name = isset($review_data['reviewer_name']) ? sanitize_text_field($review_data['reviewer_name']) : __('Anonymous', 'alchemer-multi-resort-reviews');
        $rating = isset($review_data['rating']) ? intval($review_data['rating']) : 0;
        $review_date = isset($review_data['review_date']) ? sanitize_text_field($review_data['review_date']) : current_time('F j, Y');

        update_post_meta($post_id, '_alchemer_response_id', $response_id);
        update_post_meta($post_id, '_alchemer_unique_id', sanitize_text_field( $review_data['unique_id'] ?? $response_id ));
        update_post_meta($post_id, '_alchemer_site_id', sanitize_text_field( $review_data['site_id'] ?? ( $this->context['survey_id'] ?? '' ) ));
        update_post_meta($post_id, '_amrr_property_slug', sanitize_title( $review_data['property_slug'] ?? ( $this->context['slug'] ?? '' ) ));
        update_post_meta($post_id, '_amrr_property_name', sanitize_text_field( $review_data['property_name'] ?? ( $this->context['name'] ?? '' ) ));
        update_post_meta($post_id, '_alchemer_reviewer_name', $reviewer_name);
        update_post_meta($post_id, '_alchemer_rating', $rating);
        update_post_meta($post_id, '_alchemer_review_date', $review_date);
        update_post_meta($post_id, '_alchemer_reviewed', isset($state['reviewed']) ? $state['reviewed'] : '0');
        update_post_meta($post_id, '_alchemer_review_decision', isset($state['decision']) ? $state['decision'] : 'pending');
        update_post_meta($post_id, '_alchemer_manually_edited', isset($state['manual_protection']) ? $state['manual_protection'] : '0');
    }

    /**
     * Process a single review decision, handling duplicates and skip overwrite.
     *
     * @param array $review_data Review data.
     * @param bool $accept Whether to accept the review
     * @param bool $edited Whether the importer content was edited before saving
     * @return array Result of processing
     */
    public function process_review($review_data, $accept = false, $edited = false) {
        // Debug logging
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('=== Processing Review ===');
            error_log('Review Data: ' . print_r($review_data, true));
            error_log('Accept: ' . ($accept ? 'true' : 'false'));
            error_log('Edited during import: ' . ($edited ? 'true' : 'false'));
        }

        // Check if review data is valid
        if (empty($review_data) || !is_array($review_data)) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('Invalid review data: ' . print_r($review_data, true));
            }
            return array(
                'success' => false,
                'message' => __('Invalid review data', 'alchemer-multi-resort-reviews')
            );
        }

        // Extract the actual review data if it's nested
        $review_data = isset($review_data['data']) ? $review_data['data'] : $review_data;
        
        // Validate and sanitize required fields
        $response_id = isset($review_data['response_id']) ? sanitize_text_field($review_data['response_id']) : '';
        $reviewer_name = isset($review_data['reviewer_name']) ? sanitize_text_field($review_data['reviewer_name']) : __('Anonymous', 'alchemer-multi-resort-reviews');
        $rating = isset($review_data['rating']) ? intval($review_data['rating']) : 0;
        $content = isset($review_data['content']) ? wp_kses_post($review_data['content']) : '';
        $post_date = isset($review_data['post_date']) ? sanitize_text_field($review_data['post_date']) : current_time('mysql');
        $review_date = isset($review_data['review_date']) ? sanitize_text_field($review_data['review_date']) : current_time('F j, Y');
        $decision = $accept ? 'accepted' : 'rejected';
        $manual_protection = $edited ? '1' : '0';
        
        // Validate required fields
        if (empty($response_id) || empty($content)) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('Missing required fields. Response ID: ' . $response_id . ', Content: ' . $content);
            }
            return array(
                'success' => false,
                'message' => __('Missing required fields', 'alchemer-multi-resort-reviews')
            );
        }
        
        // Check if review already exists
        $existing_post = $this->get_review_by_response_id($response_id);
        
        if ($existing_post) {
            $post_id = $existing_post->ID;
            
            // Check if review was manually edited
            $manually_edited = get_post_meta($post_id, '_alchemer_manually_edited', true);
            if ($manually_edited === '1') {
                return array(
                    'success' => false,
                    'message' => __('Review was manually edited and cannot be updated', 'alchemer-multi-resort-reviews')
                );
            }
            
            // Update post data
            $post_data = array(
                'ID' => $post_id,
                'post_title' => $reviewer_name,
                'post_content' => $content,
                'post_status' => $accept ? 'publish' : 'draft',
                'post_date' => $post_date,
            );
            $update_result = wp_update_post($post_data, true);

            if (is_wp_error($update_result)) {
                return array(
                    'success' => false,
                    'message' => $update_result->get_error_message()
                );
            }
            
            $review_data['review_date'] = $review_date;
            $this->save_review_meta($post_id, $review_data, array(
                'reviewed' => '1',
                'decision' => $decision,
                'manual_protection' => $manual_protection,
            ));

            return array(
                'success' => true,
                'post_id' => $post_id,
                'status' => $accept ? 'published' : 'draft',
                'updated' => true
            );
        }

        // Prepare post data for new review
        $post_data = array(
            'post_title' => $reviewer_name,
            'post_content' => $content,
            'post_status' => $accept ? 'publish' : 'draft',
            'post_type' => 'amrr-review',
            'post_date' => $post_date,
        );
        
        // Debug log the post data
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Inserting post with data: ' . print_r($post_data, true));
        }
        
        $post_id = wp_insert_post($post_data);
        if (is_wp_error($post_id)) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('Error inserting post: ' . $post_id->get_error_message());
            }
            return array(
                'success' => false,
                'message' => $post_id->get_error_message()
            );
        }
        
        $review_data['review_date'] = $review_date;
        $this->save_review_meta($post_id, $review_data, array(
            'reviewed' => '1',
            'decision' => $decision,
            'manual_protection' => $manual_protection,
        ));

        return array(
            'success' => true,
            'post_id' => $post_id,
            'status' => $accept ? 'published' : 'draft',
            'updated' => false
        );
    }

    /**
     * Format a name to proper case (first letter uppercase, rest lowercase)
     * 
     * @param string $name The name to format
     * @return string The formatted name
     */
    private function format_name_proper_case($name) {
        // If empty, return as is
        if (empty($name)) {
            return $name;
        }
        
        // Handle multi-word names
        $parts = explode(' ', $name);
        $formatted_parts = array();
        
        foreach ($parts as $part) {
            // Skip empty parts
            if (empty($part)) {
                continue;
            }
            
            // Handle hyphenated names like "Mary-Jane"
            if (strpos($part, '-') !== false) {
                $hyphen_parts = explode('-', $part);
                $formatted_hyphen_parts = array();
                
                foreach ($hyphen_parts as $h_part) {
                    if (!empty($h_part)) {
                        $formatted_hyphen_parts[] = ucfirst(strtolower($h_part));
                    }
                }
                
                $formatted_parts[] = implode('-', $formatted_hyphen_parts);
            } else {
                // Regular name part
                $formatted_parts[] = ucfirst(strtolower($part));
            }
        }
        
        return implode(' ', $formatted_parts);
    }

    /**
     * Resolve the rating/comment question from a response.
     *
     * @param array $survey_data Survey data.
     * @param string $preferred_question_id Saved field mapping.
     * @return array|null Resolved question with id and question data.
     */
    private function resolve_review_question($survey_data, $preferred_question_id = '') {
        $preferred_question = $this->find_question_by_id($survey_data, $preferred_question_id);

        if ($preferred_question && $this->extract_rating_value_from_question($preferred_question['question']) > 0 && $this->get_content_from_question($preferred_question['question']) !== '') {
            return $preferred_question;
        }

        foreach ($survey_data as $key => $question) {
            if (!is_array($question)) {
                continue;
            }

            if ($this->is_review_question_candidate($question) && $this->extract_rating_value_from_question($question) > 0 && $this->get_content_from_question($question) !== '') {
                return array(
                    'id' => $key,
                    'question' => $question,
                );
            }
        }

        return null;
    }

    /**
     * Check whether a question looks like the overall review/testimonial question.
     *
     * @param array $question Question data.
     * @return bool True when this question should be used as review content.
     */
    private function is_review_question_candidate($question) {
        $question_text = isset($question['question']) ? strtolower(trim(strip_tags((string) $question['question']))) : '';

        if ($question_text === '') {
            return false;
        }

        $review_keywords = array(
            'overall experience',
            'review',
            'testimonial',
        );

        foreach ($review_keywords as $keyword) {
            if (strpos($question_text, $keyword) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Find a question by Alchemer question ID.
     *
     * @param array $survey_data Survey data.
     * @param string $question_id Question ID.
     * @return array|null Resolved question.
     */
    private function find_question_by_id($survey_data, $question_id) {
        if ($question_id === '') {
            return null;
        }

        if (isset($survey_data[$question_id]) && is_array($survey_data[$question_id])) {
            return array(
                'id' => $question_id,
                'question' => $survey_data[$question_id],
            );
        }

        foreach ($survey_data as $key => $question) {
            if ((strval($key) === strval($question_id) || strval($key) === 'question' . strval($question_id)) && is_array($question)) {
                return array(
                    'id' => $key,
                    'question' => $question,
                );
            }
        }

        return null;
    }

    /**
     * Extract a rating value from a question.
     *
     * @param array $question Question data.
     * @return int Rating value.
     */
    private function extract_rating_value_from_question($question) {
        if (isset($question['answer']) && is_numeric($question['answer'])) {
            $rating = intval($question['answer']);
            return ($rating >= 1 && $rating <= 5) ? $rating : 0;
        }

        if (isset($question['answer_id']) && is_numeric($question['answer_id'])) {
            $rating = intval($question['answer_id']);
            return ($rating >= 1 && $rating <= 5) ? $rating : 0;
        }

        $rating = isset($question['answer']) && is_numeric($question['answer']) ? intval($question['answer']) : 0;

        return ($rating >= 1 && $rating <= 5) ? $rating : 0;
    }

    /**
     * Get review content from a question.
     *
     * @param array $question Question data.
     * @return string Review content.
     */
    private function get_content_from_question($question) {
        if (isset($question['comments']) && trim((string) $question['comments']) !== '') {
            return trim((string) $question['comments']);
        }

        if (isset($question['comment']) && trim((string) $question['comment']) !== '') {
            return trim((string) $question['comment']);
        }

        return '';
    }

    /**
     * Get survey questions from Alchemer
     *
     * @return array|bool Array of question IDs and texts, or false on error
     */
    private function get_survey_questions() {
        // Load API class
        $api = $this->api;
        
        // Fetch survey details
        $survey = $api->get_survey_details();
        
        if (!$survey['success'] || empty($survey['data']['pages'])) {
            return false;
        }
        
        $questions = array();
        
        // Extract questions from survey
        foreach ($survey['data']['pages'] as $page) {
            if (empty($page['questions'])) {
                continue;
            }
            
            foreach ($page['questions'] as $question) {
                $questions[$question['id']] = isset($question['title']) ? $question['title']['English'] : __('Untitled Question', 'alchemer-multi-resort-reviews');
            }
        }
        
        return $questions;
    }

    /**
     * Get field mappings from options
     *
     * @return array
     */
    private function get_field_mappings() {
        $mappings = get_option('amrr_field_mappings', $this->default_field_mappings);
        if ( ! empty( $this->context ) ) {
            $mappings['rating_question'] = $this->context['rating_question'] ?? ( $mappings['rating_question'] ?? '' );
            $mappings['reviewer_name'] = $this->context['reviewer_name'] ?? ( $mappings['reviewer_name'] ?? '' );
        }
        return wp_parse_args($mappings, $this->default_field_mappings);
    }
}
