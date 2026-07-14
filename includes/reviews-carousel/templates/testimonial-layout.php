<?php
// Generate a unique ID for this slider instance
$slider_id = 'alchemer-testimonial-' . uniqid();

// Get slides_to_show from shortcode attributes or default to 3
$slides_to_show = isset($atts['slides_to_show']) ? intval($atts['slides_to_show']) : 3;

// Convert center_mode to boolean
$center_mode = isset($atts['center_mode']) ? filter_var($atts['center_mode'], FILTER_VALIDATE_BOOLEAN) : false;
?>
<div class="alchemer-multi-resort-reviews alchemer-multi-resort-reviews-testimonial">
    <?php if (!empty($atts['title'])): ?>
        <h2 class="alchemer-multi-resort-reviews-title"><?php echo esc_html($atts['title']); ?></h2>
    <?php endif; ?>

    <div class="alchemer-testimonial-slider" data-slider-id="<?php echo esc_attr($slider_id); ?>" data-center-mode="<?php echo esc_attr($center_mode); ?>" data-slides-to-show="<?php echo esc_attr($slides_to_show); ?>">
        <div class="alchemer-testimonial-track">
            <?php foreach ($reviews as $index => $review) : ?>
                <div class="alchemer-testimonial-slide <?php echo $index === 1 ? 'active center' : ''; ?>">
                    <div class="alchemer-testimonial-content alchemer-testimonial-slide-inner">
                        <div class="amrr-reviewer-info">
                            <div class="amrr-reviewer-meta">
                                <h3 class="amrr-reviewer-name"><?php echo esc_html($review['name']); ?></h3>
                                <div class="amrr-review-date"><?php echo date('F j, Y', strtotime($review['date'])); ?></div>
                            </div>
                        </div>
                        <div class="amrr-review-rating">
                            <?php echo $this->generate_stars($review['rating']); ?>
                        </div>
                        <div class="alchemer-testimonial-content">
                            <?php echo wpautop($review['content']); ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="alchemer-testimonial-navigation">
        <button class="alchemer-testimonial-prev" data-slider-id="<?php echo esc_attr($slider_id); ?>" aria-label="Previous testimonial">&larr;</button>
        <div class="alchemer-testimonial-dots">
            <?php for ($i = 1; $i <= count($reviews); $i++) : ?>
                <button class="alchemer-testimonial-dot <?php echo $i === 1 ? 'active' : ''; ?>" 
                        data-slider-id="<?php echo esc_attr($slider_id); ?>" 
                        data-slide="<?php echo $i; ?>" 
                        aria-label="Go to testimonial <?php echo $i; ?>"></button>
            <?php endfor; ?>
        </div>
        <button class="alchemer-testimonial-next" data-slider-id="<?php echo esc_attr($slider_id); ?>" aria-label="Next testimonial">&rarr;</button>
    </div>
</div>

<?php
// Calculate aggregate rating
$total_rating = 0;
$review_count = count($reviews);
foreach ($reviews as $review) {
    $total_rating += $review['rating'];
}
$average_rating = $review_count > 0 ? round($total_rating / $review_count, 1) : 0;

// Generate schema markup
$schema = array(
    '@context' => 'https://schema.org',
    '@type' => 'Product',
    'name' => !empty($atts['title']) ? $atts['title'] : get_bloginfo('name'),
    'aggregateRating' => array(
        '@type' => 'AggregateRating',
        'ratingValue' => $average_rating,
        'reviewCount' => $review_count,
        'bestRating' => 5,
        'worstRating' => 1
    ),
    'review' => array()
);

// Add individual reviews to schema
foreach ($reviews as $review) {
    $schema['review'][] = array(
        '@type' => 'Review',
        'author' => array(
            '@type' => 'Person',
            'name' => $review['name']
        ),
        'datePublished' => date('Y-m-d', strtotime($review['date'])),
        'reviewRating' => array(
            '@type' => 'Rating',
            'ratingValue' => $review['rating'],
            'bestRating' => 5,
            'worstRating' => 1
        ),
        'reviewBody' => wp_strip_all_tags($review['content'])
    );
}

// Output schema markup
echo '<script type="application/ld+json">' . wp_json_encode($schema, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . '</script>';
?>
