<?php
/**
 * Static regression checks for importer behavior that can run without WordPress.
 */

$plugin_dir = dirname(__DIR__);

$files = array(
    'bootstrap' => $plugin_dir . '/alchemer-multi-resort-reviews.php',
    'build' => $plugin_dir . '/build.js',
    'api' => $plugin_dir . '/includes/class-amrr-reviews-api.php',
    'importer' => $plugin_dir . '/includes/class-amrr-reviews-importer.php',
    'post_types' => $plugin_dir . '/includes/class-amrr-reviews-post-types.php',
    'settings' => $plugin_dir . '/includes/class-amrr-reviews-settings.php',
    'puc' => $plugin_dir . '/includes/plugin-update-checker/plugin-update-checker.php',
    'importer_js' => $plugin_dir . '/assets/js/importer.js',
    'carousel' => $plugin_dir . '/includes/reviews-carousel/amrr-review-carousel.php',
    'multi_resort' => $plugin_dir . '/includes/class-amrr-multi-resort-manager.php',
    'migrator' => $plugin_dir . '/includes/class-amrr-legacy-migrator.php',
);

$source = array();
foreach ($files as $key => $path) {
    if (!file_exists($path)) {
        fwrite(STDERR, "Missing expected file: {$path}\n");
        exit(1);
    }

    $source[$key] = file_get_contents($path);
}

$checks = array(
    'AI analysis method removed' => strpos($source['importer'], 'get_review_analysis') === false,
    'AI meta writes removed' => strpos($source['importer'], 'ai_sentiment') === false && strpos($source['importer'], 'ai_suggestion') === false,
    'AI AJAX flag removed' => strpos($source['importer'], 'use_ai') === false && strpos($source['importer_js'], 'useAI') === false,
    'Gemini settings copy removed' => stripos($source['settings'], 'Gemini') === false,
    'dotenv bootstrap removed' => stripos($source['bootstrap'], 'Dotenv') === false && stripos($source['bootstrap'], '.env') === false,
    'API filters by existing response IDs' => strpos($source['api'], '$exclude_response_ids') !== false && strpos($source['api'], 'skipped_existing') !== false,
    'API counts existing skips after rating/content validation' => strpos($source['api'], 'already exists in WordPress') > strpos($source['api'], 'response_has_content'),
    'API supports all-new pagination mode' => strpos($source['api'], '$import_all_new') !== false && strpos($source['api'], 'import_all_new') !== false,
    'manual import passes existing response IDs to API' => strpos($source['importer'], 'get_existing_response_ids') !== false && strpos($source['importer'], '$exclude_response_ids') !== false,
    'manual import is new-only by default without all-new checkbox' => strpos($source['importer'], 'id="import-all-new"') === false && strpos($source['importer_js'], '#import-all-new') === false,
    'manual import remains max-bound while paginating net-new reviews' => strpos($source['importer'], "'import_all_new' => false") !== false && strpos($source['api'], 'count($valid_responses) < $max_reviews') !== false,
    'daily sync requests all new reviews' => strpos($source['importer'], "'import_all_new' => true") !== false && strpos($source['importer'], "'max_reviews' => 0") !== false,
    'daily sync fetches only from latest back to existing boundary' => strpos($source['importer'], "'stop_at_existing' => true") !== false && strpos($source['api'], '$stop_at_existing') !== false && strpos($source['api'], '$reached_existing_boundary') !== false,
    'dashboard notice reports pending review queue' => strpos($source['importer'], "admin_notices") !== false && strpos($source['importer'], 'render_pending_reviews_dashboard_notice') !== false && strpos($source['importer'], 'count_pending_reviews') !== false,
    'stale mappings can resolve rating question from response content' => strpos($source['api'], 'resolve_review_question') !== false && strpos($source['importer'], 'resolve_review_question') !== false,
    'empty import preserves pagination diagnostics' => strpos($source['importer'], 'pages_fetched') !== false && strpos($source['importer'], 'skipped_no_content') !== false,
    'API only treats comment fields as review content' => strpos($source['api'], 'strlen($value) > 10') === false && strpos($source['api'], "\$key !== 'question'") === false,
    'fallback review question requires review-like text' => strpos($source['api'], 'is_review_question_candidate') !== false && strpos($source['api'], 'overall experience') !== false && strpos($source['importer'], 'is_review_question_candidate') !== false,
    'rating extraction rejects non-star values' => strpos($source['api'], '$rating < 1 || $rating > 5') !== false && strpos($source['importer'], '$rating >= 1 && $rating <= 5') !== false,
    'admin import button uses delegated binding' => strpos($source['importer_js'], 'click.alchemerReviewsImport') !== false,
    'cron uses multi-resort daily sync path' => strpos($source['multi_resort'], "add_action( 'amrr_daily_import', array( \$this, 'run_daily_sync_all' ) )") !== false,
    'resort sync uses scoped importer' => strpos($source['multi_resort'], 'new AMRR_Importer( $resort )') !== false,
    'property scoped unique IDs are preserved' => strpos($source['importer'], "\$property_slug . '-' . \$response_id") !== false,
    'legacy migration is idempotent' => strpos($source['migrator'], '_amrr_legacy_post_id') !== false && strpos($source['migrator'], "'skipped'") !== false,
    'daily sync persists pending reviews' => strpos($source['importer'], 'save_synced_review_as_pending') !== false && strpos($source['importer'], "'pending'") !== false,
    'accept and reject mark reviewed' => strpos($source['importer'], '_alchemer_reviewed') !== false && strpos($source['importer'], '_alchemer_review_decision') !== false,
    'edited imports enable overwrite protection' => strpos($source['importer'], '$edited') !== false && strpos($source['importer_js'], 'edited ? 1 : 0') !== false,
    'review lookup includes all statuses' => strpos($source['importer'], "'post_status' => array('publish', 'future', 'draft', 'pending', 'private', 'trash')") !== false,
    'activation schedules saved auto import setting' => strpos($source['bootstrap'], 'amrr_maybe_schedule_daily_import') !== false,
    'deactivation clears scheduled auto import' => strpos($source['bootstrap'], "wp_clear_scheduled_hook('amrr_daily_import')") !== false,
    'field mapping saves preserve auto import setting' => strpos($source['importer'], 'auto_import_submitted') !== false && strpos($source['importer'], "\$current_mappings['auto_import']") !== false,
    'empty import message prioritizes existing response IDs' => strpos($source['importer'], 'All fetched matching responses already exist in WordPress.') < strpos($source['importer'], 'No review cards were created because fetched responses did not have a usable rating/comment field.'),
    'post editor loads review meta box CSS' => strpos($source['post_types'], "array( 'edit.php', 'post.php', 'post-new.php' )") !== false,
    'review details meta box uses stacked fields' => strpos($source['post_types'], 'amrr-review-details-fields') !== false && strpos($source['post_types'], 'amrr-review-checkbox-label') !== false,
    'admin list exposes review decision' => strpos($source['post_types'], "'review_decision'") !== false,
    'testimonial carousel handles empty reviews' => strpos($source['carousel'], 'empty($reviews)') !== false,
    'API debug logs do not expose signed URLs' => strpos($source['api'], 'Built API URL: ' . '$url') === false && strpos($source['api'], 'Fetching survey responses with URL: ' . '$url') === false,
    'plugin declares GitHub update URI' => strpos($source['bootstrap'], 'Update URI: https://github.com/braudypedrosa/alchemer-multi-resort-reviews') !== false,
    'bootstrap includes Plugin Update Checker' => strpos($source['bootstrap'], 'plugin-update-checker/plugin-update-checker.php') !== false && strpos($source['bootstrap'], 'PucFactory::buildUpdateChecker') !== false,
    'Plugin Update Checker library is vendored' => strpos($source['puc'], 'Plugin Update Checker Library 5.7') !== false && file_exists($plugin_dir . '/includes/plugin-update-checker/Puc/v5/PucFactory.php'),
    'Plugin Update Checker points to GitHub repo' => strpos($source['bootstrap'], 'https://github.com/braudypedrosa/alchemer-multi-resort-reviews/') !== false,
    'Plugin Update Checker uses release zip assets' => strpos($source['bootstrap'], 'enableReleaseAssets') !== false && strpos($source['bootstrap'], 'alchemer-multi-resort-reviews\.zip') !== false,
    'custom GitHub updater was removed' => ! file_exists($plugin_dir . '/includes/class-alchemer-multi-resort-reviews-github-updater.php') && strpos($source['bootstrap'], 'AMRR_GitHub_Updater') === false,
    'release package builder adds each file once' => strpos($source['build'], 'addedFiles') !== false && strpos($source['build'], 'addFileToArchive') !== false && strpos($source['build'], "fs.readdirSync(sourceDir)") === false,
);

$failed = array();
foreach ($checks as $label => $passed) {
    if (!$passed) {
        $failed[] = $label;
    }
}

if ($failed) {
    fwrite(STDERR, "Static checks failed:\n");
    foreach ($failed as $label) {
        fwrite(STDERR, "- {$label}\n");
    }
    exit(1);
}

echo "All static checks passed.\n";
