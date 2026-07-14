<?php
/**
* Alchemer Review Carousel - Documentation
* Version: 1.3.0
* Author: Braudy Pedrosa
*/

// Prevent direct access
if (!defined('ABSPATH')) {
   exit;
}

/**
* Documentation class for Alchemer Review Carousel
*/
class AMRR_Review_Carousel_Docs {
   
   /**
    * Constructor
    */
   public function __construct() {
       add_action('admin_menu', array($this, 'add_documentation_page'));
   }
   
   /**
    * Add documentation page to admin menu
    */
   public function add_documentation_page() {
       add_submenu_page(
           'edit.php?post_type=amrr-review',
           'Documentation',
           'Documentation',
           'manage_options',
           'amrr-review-carousel-docs',
           array($this, 'render_documentation_page')
       );
   }
   
   /**
    * Render the documentation page
    */
   public function render_documentation_page() {
       ?>
       <div class="wrap">
           <h1>Alchemer Review Carousel - Documentation</h1>
           
           <div class="card">
               <h2>Overview</h2>
               <p>Alchemer Review Carousel is a WordPress plugin that displays customer reviews from the "Alchemer Review" post type in multiple layouts. The plugin offers three different display options: list, grid, and testimonial carousel.</p>
           </div>
           
           <div class="card">
               <h2>Shortcodes</h2>
               <p>The plugin provides three main shortcodes to display reviews in different layouts:</p>
               
               <h3>1. List Layout</h3>
               <pre><code>[amrr_list]</code></pre>
               <p>Displays reviews in a vertical list format.</p>
               
               <h3>2. Grid Layout</h3>
               <pre><code>[amrr_grid]</code></pre>
               <p>Displays reviews in a grid layout with navigation arrows.</p>
               
               <h3>3. Testimonial Carousel</h3>
               <pre><code>[amrr_testimonial]</code></pre>
               <p>Displays reviews in a testimonial carousel with smooth animations.</p>
           </div>
           
           <div class="card">
               <h2>Parameters</h2>
               <p>All shortcodes accept the following common parameters:</p>
               
               <table class="widefat">
                   <thead>
                       <tr>
                           <th>Parameter</th>
                           <th>Description</th>
                           <th>Default</th>
                           <th>Example</th>
                       </tr>
                   </thead>
                   <tbody>
                       <tr>
                           <td><code>title</code></td>
                           <td>The heading text displayed above the reviews.</td>
                           <td>"What Our Customers Say" (list/grid)<br>"Customer Testimonials" (testimonial)</td>
                           <td><code>title="Client Feedback"</code></td>
                       </tr>
                       <tr>
                           <td><code>count</code></td>
                           <td>The number of reviews to display. Use -1 to show all reviews.</td>
                           <td>3</td>
                           <td><code>count="5"</code></td>
                       </tr>
                       <tr>
                           <td><code>demo</code></td>
                           <td>Whether to use demo data instead of actual reviews.</td>
                           <td>false</td>
                           <td><code>demo="true"</code></td>
                       </tr>
                   </tbody>
               </table>
               
               <h3>Testimonial Carousel Specific Parameters</h3>
               <p>The testimonial carousel shortcode accepts additional parameters:</p>
               
               <table class="widefat">
                   <thead>
                       <tr>
                           <th>Parameter</th>
                           <th>Description</th>
                           <th>Default</th>
                           <th>Example</th>
                       </tr>
                   </thead>
                   <tbody>
                       <tr>
                           <td><code>slides_to_show</code></td>
                           <td>The number of slides to display at once. Responsive design will adjust this on smaller screens.</td>
                           <td>1</td>
                           <td><code>slides_to_show="3"</code></td>
                       </tr>
                       <tr>
                           <td><code>center_mode</code></td>
                           <td>Whether to enable center mode, which highlights the active slide and shows partial views of adjacent slides.</td>
                           <td>false</td>
                           <td><code>center_mode="true"</code></td>
                       </tr>
                   </tbody>
               </table>
           </div>
           
           <div class="card">
               <h2>Usage Examples</h2>
               
               <h3>Basic Usage</h3>
               <pre><code>[amrr_list]
[amrr_grid]
[amrr_testimonial]</code></pre>
               
               <h3>With Common Parameters</h3>
               <pre><code>[amrr_list title="Customer Feedback" count="5"]
[amrr_grid title="Recent Reviews" count="6" demo="false"]
[amrr_testimonial title="What People Say" count="-1" demo="true"]</code></pre>
               
               <h3>Testimonial Carousel with Multiple Slides</h3>
               <pre><code>[amrr_testimonial slides_to_show="3" title="Customer Testimonials"]</code></pre>
               <p>This will display 3 testimonials at once in the carousel.</p>
               
               <h3>Testimonial Carousel with Center Mode</h3>
               <pre><code>[amrr_testimonial center_mode="true" title="Featured Reviews"]</code></pre>
               <p>This will enable center mode, highlighting the active slide.</p>
               
               <h3>Combining Multiple Parameters</h3>
               <pre><code>[amrr_testimonial slides_to_show="3" center_mode="true" title="What Our Clients Say" count="9" demo="true"]</code></pre>
               <p>This will display 3 demo testimonials at once with center mode enabled.</p>
               
               <h3>In a Template File</h3>
               <pre><code>&lt;?php echo do_shortcode('[amrr_testimonial slides_to_show="3" center_mode="true"]'); ?&gt;</code></pre>
           </div>
           
           <div class="card">
               <h2>Review Data Structure</h2>
               <p>The plugin pulls data from the "Alchemer Review" post type with the following structure:</p>
               <ul>
                   <li><strong>Review Content:</strong> Post content</li>
                   <li><strong>Reviewer Name:</strong> Post title</li>
                   <li><strong>Rating:</strong> Post meta "_alchemer_rating" (1-5)</li>
                   <li><strong>Review Date:</strong> Post meta "_alchemer_review_date" (YYYY-MM-DD format)</li>
               </ul>
           </div>
           
           <div class="card">
               <h2>Demo Data</h2>
               <p>The plugin includes demo data that can be displayed by setting the <code>demo</code> parameter to <code>true</code>. This is useful for testing or when you don't have any reviews yet.</p>
               <pre><code>[amrr_list demo="true"]</code></pre>
           </div>
           
           <div class="card">
               <h2>Layout Details</h2>
               
               <h3>List Layout</h3>
               <p>The list layout displays reviews in a vertical list with:</p>
               <ul>
                   <li>Reviewer name and date</li>
                   <li>Star rating</li>
                   <li>Review content</li>
               </ul>
               
               <h3>Grid Layout</h3>
               <p>The grid layout displays reviews in a responsive grid with:</p>
               <ul>
                   <li>Reviewer name and date</li>
                   <li>Star rating</li>
                   <li>Truncated review content</li>
                   <li>Navigation arrows (if more than 3 reviews)</li>
               </ul>
               
               <h3>Testimonial Carousel</h3>
               <p>The testimonial carousel displays reviews with:</p>
               <ul>
                   <li>Reviewer name and date</li>
                   <li>Star rating</li>
                   <li>Full review content</li>
                   <li>Navigation arrows and dots</li>
                   <li>Smooth animations between slides</li>
                   <li>Auto-rotation (pauses on hover)</li>
                   <li>Option to display multiple slides at once</li>
                   <li>Option to enable center mode for highlighting active slides</li>
               </ul>
           </div>
           
           <div class="card">
               <h2>Responsive Behavior</h2>
               <p>The plugin is fully responsive and will adjust to different screen sizes:</p>
               <ul>
                   <li>On screens smaller than 992px, 4-column layouts will become 3-column layouts</li>
                   <li>On screens smaller than 768px, grid layouts will become single-column, and 3 or 4-column carousels will become 2-column</li>
                   <li>On screens smaller than 576px, all carousel layouts will display a single slide regardless of the <code>slides_to_show</code> setting</li>
               </ul>
           </div>
           
           <div class="card">
               <h2>Styling</h2>
               <p>The plugin includes default styling that should work with most WordPress themes. If you want to customize the appearance, you can add custom CSS to your theme or use a custom CSS plugin.</p>
               
               <h3>CSS Classes</h3>
               <p>Here are the main CSS classes you can target for customization:</p>
               
               <h4>Common Classes</h4>
               <ul>
                   <li><code>.alchemer-multi-resort-reviews</code> - Main container for all layouts</li>
                   <li><code>.alchemer-multi-resort-reviews-title</code> - Title heading</li>
                   <li><code>.alchemer-star</code> - Star rating icons</li>
                   <li><code>.alchemer-nav-button</code> - Navigation buttons</li>
               </ul>
               
               <h4>List Layout Classes</h4>
               <ul>
                   <li><code>.alchemer-multi-resort-reviews-list</code> - List layout container</li>
                   <li><code>.amrr-review-item</code> - Individual review container</li>
                   <li><code>.amrr-reviewer-name</code> - Reviewer name</li>
                   <li><code>.amrr-review-date</code> - Review date</li>
                   <li><code>.amrr-review-rating</code> - Rating container</li>
                   <li><code>.amrr-review-content</code> - Review content</li>
               </ul>
               
               <h4>Grid Layout Classes</h4>
               <ul>
                   <li><code>.alchemer-multi-resort-reviews-grid</code> - Grid layout container</li>
                   <li><code>.alchemer-grid-container</code> - Grid items container</li>
                   <li><code>.alchemer-grid-item</code> - Individual grid item</li>
                   <li><code>.alchemer-grid-navigation</code> - Grid navigation container</li>
               </ul>
               
               <h4>Testimonial Layout Classes</h4>
               <ul>
                   <li><code>.alchemer-multi-resort-reviews-testimonial</code> - Testimonial layout container</li>
                   <li><code>.alchemer-testimonial-slider</code> - Slider container</li>
                   <li><code>.alchemer-testimonial-track</code> - Track that holds all slides</li>
                   <li><code>.alchemer-testimonial-slide</code> - Individual slide</li>
                   <li><code>.alchemer-testimonial-slide-inner</code> - Inner content of each slide</li>
                   <li><code>.alchemer-testimonial-navigation</code> - Navigation container</li>
                   <li><code>.alchemer-testimonial-dots</code> - Dots container</li>
               </ul>
           </div>
           
           <div class="card">
               <h2>Troubleshooting</h2>
               
               <h3>No Reviews Displaying</h3>
               <p>If no reviews are displaying, check the following:</p>
               <ol>
                   <li>Ensure you have published reviews in the "Alchemer Review" post type</li>
                   <li>Check that your reviews have the required meta fields (_alchemer_rating and _alchemer_review_date)</li>
                   <li>Try using the demo parameter: <code>[amrr_list demo="true"]</code></li>
               </ol>
               
               <h3>Styling Issues</h3>
               <p>If you experience styling issues:</p>
               <ol>
                   <li>Check if your theme is overriding the plugin styles</li>
                   <li>Try adding <code>!important</code> to your custom CSS rules</li>
                   <li>Inspect the elements using browser developer tools to identify conflicting styles</li>
               </ol>
               
               <h3>JavaScript Issues</h3>
               <p>If carousel or grid navigation isn't working:</p>
               <ol>
                   <li>Check your browser console for JavaScript errors</li>
                   <li>Ensure jQuery is loaded on your site</li>
                   <li>Check if there are JavaScript conflicts with other plugins</li>
               </ol>
               
               <h3>Multiple Slides Not Working</h3>
               <p>If multiple slides aren't displaying correctly:</p>
               <ol>
                   <li>Ensure you have enough reviews to display (at least as many as your slides_to_show value)</li>
                   <li>Check if your screen size is triggering the responsive breakpoints</li>
                   <li>Inspect the slider using browser developer tools to check for CSS conflicts</li>
               </ol>
           </div>
           
           <div class="card">
               <h2>Support</h2>
               <p>For support or feature requests, please contact the plugin author:</p>
               <p><strong>Author:</strong> Braudy Pedrosa</p>
               <p><strong>Version:</strong> 1.3.0</p>
           </div>
       </div>
       
       <style>
           .wrap {
               max-width: 1200px;
           }
           .card {
               background: #fff;
               border: 1px solid #ccd0d4;
               border-radius: 4px;
               margin-top: 20px;
               padding: 20px;
               box-shadow: 0 1px 1px rgba(0,0,0,0.04);
               min-width: 100%!important;
               max-width: 100%!important;

           }
           .card h2 {
               margin-top: 0;
               border-bottom: 1px solid #eee;
               padding-bottom: 10px;

           }
           .card h3 {
               margin: 1.5em 0 0.5em;
           }
           pre {
               background: #f5f5f5;
               padding: 15px;
               border-radius: 3px;
               overflow: auto;
           }
           code {
               background: #f5f5f5;
               padding: 2px 5px;
               border-radius: 3px;
           }
           table.widefat {
               border-collapse: collapse;
               width: 100%;
               margin: 1em 0;
               border: 1px solid #ccd0d4;
           }
           table.widefat th {
               background: #f5f5f5;
               text-align: left;
               padding: 8px;
           }
           table.widefat td {
               padding: 8px;
               border-top: 1px solid #f5f5f5;
           }
       </style>
       <?php
   }
}

// Initialize the documentation
new AMRR_Review_Carousel_Docs();
