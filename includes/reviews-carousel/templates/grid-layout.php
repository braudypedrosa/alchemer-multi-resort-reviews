<div class="alchemer-multi-resort-reviews alchemer-multi-resort-reviews-grid">
    <?php if (!empty($atts['title'])): ?>
        <h2 class="alchemer-multi-resort-reviews-title"><?php echo esc_html($atts['title']); ?></h2>
    <?php endif; ?>
    
    <div class="alchemer-grid-container">
        <?php foreach ($reviews as $review) : ?>
            <div class="alchemer-grid-item">
                <div class="amrr-reviewer-info">
                    <div class="amrr-reviewer-meta">
                        <h3 class="amrr-reviewer-name"><?php echo esc_html($review['name']); ?></h3>
                        <div class="amrr-review-date"><?php echo date('F j, Y', strtotime($review['date'])); ?></div>
                    </div>
                </div>
                <div class="amrr-review-rating">
                    <?php echo $this->generate_stars($review['rating']); ?>
                </div>
                <div class="amrr-review-content">
                    <?php echo wp_trim_words(wpautop($review['content']), 30, '...'); ?>
                </div>
            </div>
        <?php endforeach; ?>
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
