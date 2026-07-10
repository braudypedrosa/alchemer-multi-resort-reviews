<?php
/**
 * Plugin Name: Alchemer Review Carousel
 * Plugin URI: https://example.com/amrr-review-carousel
 * Description: A plugin to display customer reviews from Alchemer in multiple layouts
 * Version: 2.1.0
 * Author: Braudy Pedrosa
 * Author URI: https://example.com
 * Text Domain: amrr-review-carousel
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class AMRR_Review_Carousel {
    
    /**
     * Constructor
     */
    public function __construct() {
        // Register shortcodes
        add_shortcode('amrr_list', array($this, 'display_reviews_list'));
        add_shortcode('amrr_grid', array($this, 'display_reviews_grid'));
        add_shortcode('amrr_testimonial', array($this, 'display_reviews_testimonial'));
        add_action('init', array($this, 'register_legacy_shortcode'), 99);
        
        // Register scripts and styles
        add_action('wp_enqueue_scripts', array($this, 'register_scripts'));
    }

    public function register_legacy_shortcode() {
        add_shortcode('vmb_reviews', array($this, 'display_legacy_vmb_reviews'));
    }
    
    /**
     * Register scripts and styles
     */
    public function register_scripts() {
        wp_register_style('alchemer-multi-resort-reviews-style', plugins_url('assets/css/alchemer-multi-resort-reviews.css', __FILE__), array(), '2.1.0');
        wp_register_script('alchemer-multi-resort-reviews-script', plugins_url('assets/js/alchemer-multi-resort-reviews.js', __FILE__), array('jquery'), '2.1.0', true);
    }
    
    /**
     * Get reviews from the database or demo data
     */
    public function get_reviews($count = -1, $demo = false, $property = '') {
        if ($demo) {
            $demo_reviews = $this->get_demo_data();
            
            // Apply count limit if needed
            if ($count > 0 && count($demo_reviews) > $count) {
                $demo_reviews = array_slice($demo_reviews, 0, $count);
            }
            
            return $demo_reviews;
        }
        
        $args = array(
            'post_type' => 'amrr-review',
            'posts_per_page' => $count,
            'post_status' => 'publish',
            'orderby' => 'date',
            'order' => 'DESC',
            'meta_query' => array(
                array(
                    'relation' => 'OR',
                    array( 'key' => '_amrr_hidden', 'compare' => 'NOT EXISTS' ),
                    array( 'key' => '_amrr_hidden', 'value' => '1', 'compare' => '!=' ),
                ),
            ),
        );

        if ( $property ) {
            $args['meta_query'][] = array( 'key' => '_amrr_property_slug', 'value' => sanitize_title( $property ) );
        }
        
        $reviews = get_posts($args);
        $formatted_reviews = array();
        
        foreach ($reviews as $review) {
            $formatted_reviews[] = array(
                'id' => $review->ID,
                'name' => $review->post_title,
                'content' => $review->post_content,
                'rating' => get_post_meta($review->ID, '_alchemer_rating', true),
                'date' => get_post_meta($review->ID, '_alchemer_review_date', true),
            );
        }

        return $formatted_reviews;
    }
    
    /**
     * Display reviews in a vertical list layout
     */
    public function display_reviews_list($atts) {
        $atts = shortcode_atts(array(
            'count' => 3,
            'title' => 'What Our Customers Say',
            'demo' => false,
            'property' => '',
        ), $atts);
        
        wp_enqueue_style('alchemer-multi-resort-reviews-style');
        
        $demo = filter_var($atts['demo'], FILTER_VALIDATE_BOOLEAN);
        $reviews = $this->get_reviews($atts['count'], $demo, $atts['property']);
        
        ob_start();
        include plugin_dir_path(__FILE__) . 'templates/list-layout.php';
        return ob_get_clean();
    }
    
    /**
     * Display reviews in a grid layout
     */
    public function display_reviews_grid($atts) {
        $atts = shortcode_atts(array(
            'count' => 3,
            'title' => 'What Our Customers Say',
            'demo' => false,
            'property' => '',
        ), $atts);
        
        wp_enqueue_style('alchemer-multi-resort-reviews-style');
        wp_enqueue_script('alchemer-multi-resort-reviews-script');
        
        $demo = filter_var($atts['demo'], FILTER_VALIDATE_BOOLEAN);
        $reviews = $this->get_reviews($atts['count'], $demo, $atts['property']);
        
        ob_start();
        include plugin_dir_path(__FILE__) . 'templates/grid-layout.php';
        return ob_get_clean();
    }
    
    /**
     * Display reviews in a testimonial carousel layout
     */
    public function display_reviews_testimonial($atts) {
        $atts = shortcode_atts(array(
            'count' => -1,
            'title' => 'Customer Testimonials',
            'demo' => false,
            'center_mode' => false,
            'slides_to_show' => 3,
            'property' => '',
        ), $atts);
        
        wp_enqueue_style('alchemer-multi-resort-reviews-style');
        wp_enqueue_script('alchemer-multi-resort-reviews-script');
        
        $demo = filter_var($atts['demo'], FILTER_VALIDATE_BOOLEAN);
        $center_mode = filter_var($atts['center_mode'], FILTER_VALIDATE_BOOLEAN);
        
        $reviews = $this->get_reviews($atts['count'], $demo, $atts['property']);

        if (empty($reviews)) {
            return '';
        }
        
        // Ensure we have at least 3 reviews for the carousel
        if (count($reviews) < 3) {
            // Clone reviews to have at least 3
            while (count($reviews) < 3) {
                $reviews = array_merge($reviews, $reviews);
            }
            // Trim if we have more than needed
            if (count($reviews) > 6) {
                $reviews = array_slice($reviews, 0, 6);
            }
        }
        
        ob_start();
        include plugin_dir_path(__FILE__) . 'templates/testimonial-layout.php';
        return ob_get_clean();
    }
    
    /**
     * Generate star rating HTML
     */
    public function generate_stars($rating) {
        $rating = max(0, min(5, $rating)); // Ensure rating is between 0 and 5
        $full_stars = floor($rating);
        $half_star = ($rating - $full_stars) >= 0.5;
        $empty_stars = 5 - $full_stars - ($half_star ? 1 : 0);
        
        $stars = '';
        
        // Full stars
        for ($i = 0; $i < $full_stars; $i++) {
            $stars .= '<span class="alchemer-star alchemer-star-full">★</span>';
        }
        
        // Half star
        if ($half_star) {
            $stars .= '<span class="alchemer-star alchemer-star-half">★</span>';
        }
        
        // Empty stars
        for ($i = 0; $i < $empty_stars; $i++) {
            $stars .= '<span class="alchemer-star alchemer-star-empty">☆</span>';
        }
        
        return $stars;
    }

    /** Backward-compatible VMB shortcode using the new review store. */
    public function display_legacy_vmb_reviews( $atts ) {
        $atts = shortcode_atts( array( 'resort_id' => '', 'limit' => -1 ), $atts, 'vmb_reviews' );
        $property = '';
        if ( $atts['resort_id'] ) {
            $property = sanitize_title( get_the_title( intval( $atts['resort_id'] ) ) );
        } elseif ( is_singular( 'resort' ) ) {
            $property = sanitize_title( get_the_title() );
        }
        return $this->display_reviews_testimonial( array(
            'count' => intval( $atts['limit'] ),
            'property' => $property,
            'title' => '',
            'slides_to_show' => 2,
        ) );
    }
    
    /**
     * Format date
     */
    public function format_date($date_string) {
        $date = strtotime($date_string);
        return date('F j, Y', $date);
    }
    
    /**
     * Get demo review data
     */
    public function get_demo_data() {
        return array(
            array(
                'id' => 1,
                'name' => 'Alex Johnson',
                'content' => 'This product exceeded all my expectations. The quality is outstanding and customer service was top-notch. I would definitely recommend this to anyone looking for a reliable solution.',
                'rating' => 5,
                'date' => '2024-05-02',
            ),
            array(
                'id' => 2,
                'name' => 'Samantha Lee',
                'content' => 'Very satisfied with my purchase. The product works exactly as described and the shipping was faster than expected. The only minor issue was with the packaging, but that doesn\'t affect the product quality.',
                'rating' => 4,
                'date' => '2024-04-28',
            ),
            array(
                'id' => 3,
                'name' => 'Michael Chen',
                'content' => 'Absolutely love this! It\'s intuitive to use and has all the features I was looking for. The design is sleek and modern, and it integrates perfectly with my existing setup.',
                'rating' => 5,
                'date' => '2024-04-15',
            ),
            array(
                'id' => 4,
                'name' => 'Emily Rodriguez',
                'content' => 'I\'ve tried many similar products before, but this one stands out for its quality and attention to detail. The team behind it clearly cares about delivering an exceptional experience.',
                'rating' => 5,
                'date' => '2024-04-10',
            ),
            array(
                'id' => 5,
                'name' => 'David Wilson',
                'content' => 'Great value for the price. I\'ve been using it for a month now and it has significantly improved my workflow. The customer support team was also very helpful when I had questions.',
                'rating' => 4,
                'date' => '2024-03-22',
            ),
            array(
                'id' => 6,
                'name' => 'Jennifer Martinez',
                'content' => 'I was skeptical at first, but this product has proven to be worth every penny. It\'s durable, efficient, and the attention to detail is impressive. Highly recommended!',
                'rating' => 5,
                'date' => '2024-03-15',
            ),
        );
    }
}

// Initialize the plugin
$amrr_reviews = new AMRR_Review_Carousel();
