# Alchemer Multi-Resort Reviews

A standalone WordPress plugin to fetch Alchemer survey responses for multiple resorts or properties, moderate them, and display property-filtered reviews. It is an independent fork of `braudypedrosa/alchemer-reviews` and can coexist with the original plugin.

## Description

Alchemer Reviews creates a bridge between your Alchemer (formerly SurveyGizmo) surveys and WordPress. The plugin fetches survey responses and stores them as review posts in WordPress. It's perfect for businesses looking to collect and manage customer feedback from Alchemer surveys.

## Multi-resort features

- Independent `amrr-review` post type, hooks, options, cron job, PHP classes, shortcodes, updater, and release ZIP
- Shared Alchemer API credentials with per-resort survey IDs and field mappings
- Enable/disable and daily-sync controls per resort
- Bulk sync for every enabled resort and manual sync for one resort
- Property-scoped duplicate IDs in `property-slug-response-id` format
- Conservative daily sync that stops at the first existing response and saves new reviews as drafts
- Property column and filter in the Reviews admin screen
- Property-filtered list, grid, and testimonial shortcodes using the `property` attribute
- Backward-compatible `[vmb_reviews resort_id="..."]` shortcode
- Idempotent VMB migration tool that copies legacy `vmb_reviews` posts and preserves the originals
- Migration of ratings, reviewer names, property links, visibility, manual-edit protection, and legacy IDs

## Inherited features

- Custom 'Reviews' post type for managing review content
- Seamless integration with the Alchemer API
- Field mapping to convert survey questions to review fields
- Manual and automatic import of survey responses
- Newest responses imported first (reverse chronological order)
- Uses reviewer name as the review title for better identification
- Automatically skips responses with no rating or empty comments
- Protection for manually edited reviews to prevent overwriting during imports
- Custom meta boxes for rating information
- Admin columns with review content and rating for easy management

## Installation

1. Upload the `alchemer-multi-resort-reviews` folder to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Configure shared Alchemer API credentials in Reviews > Settings
4. Add each property in Reviews > Resorts with its survey ID, rating question, and reviewer-name field
5. Use VMB Migration when replacing the legacy VMB reviews implementation

## API Configuration

1. Navigate to 'Reviews > Settings' in your WordPress admin panel
2. Enter your Alchemer API Token and API Token Secret (available in your Alchemer account)
3. Enter the Survey ID for the survey containing your reviews
4. Click 'Test API Connection' to verify your credentials work
5. Save your settings

> **Important:** When entering your API Token Secret, make sure to copy it exactly as shown in your Alchemer account, including any special characters like '%2F'. These characters are part of the token and must be preserved.

## Field Mapping

After configuring your API settings, you need to map your survey questions to the appropriate review fields:

1. Navigate to 'Reviews > Field Mapping'
2. Enter the ID of your rating question (e.g., "83") - this is the question that contains both the rating value (in the "answer" field) and the review content (in the "comments" field)
3. Optionally, map the reviewer name question if your survey collects this information
4. Enable or disable automatic daily imports based on your preference
5. Save your settings

Note: Reviews without ratings or with empty comments will be skipped during import. Reviewer names will be used as review titles.

## Importing Reviews

### Manual Import

1. Go to 'Reviews > Field Mapping' or 'Reviews > Settings'
2. Click 'Import Reviews Now'
3. Wait for the import process to complete

### Automatic Import

1. Go to 'Reviews > Field Mapping'
2. Check the 'Automatically import new reviews daily' option
3. Save your settings

The plugin will always import the newest responses first. This ensures that your most recent customer feedback gets added to your site immediately.

## Manually Edited Reviews Protection

The plugin includes a system to protect reviews that have been manually edited from being overwritten during imports:

1. When you edit a review's content in WordPress, it's automatically marked as "Manually Edited"
2. You can see this status in the "Manually Edited" column in the reviews list
3. You can also manually toggle this status in the review edit screen
4. During imports, if a survey response matches an existing review that is marked as manually edited, it will be skipped
5. This allows you to make corrections or improvements to reviews without worrying about them being overwritten

This feature is particularly useful when you want to fix typos, improve formatting, or update content in reviews while still regularly importing new survey responses.

## Troubleshooting

- **API Connection Issues**: Ensure your API Token and API Token Secret are entered exactly as shown in your Alchemer account, including any special characters.
- **No Reviews Importing**: Check your field mappings to ensure they correspond to the correct question IDs in your survey.
- **Missing Reviews**: Remember that reviews without ratings or empty comments are skipped during import.
- **Protected Reviews**: Reviews marked as "Manually Edited" won't be updated during imports. If you want a review to be updated, uncheck the "Manually edited" option in the review edit screen.

## Credits

Developed by Braudy Pedrosa / Buildup Bookings.
