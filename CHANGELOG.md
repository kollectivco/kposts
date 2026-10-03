# Changelog

## 7.0.0

- Added a Sources tab in Settings to enable, remove, and manage News Feed sources.
- Added custom source setup with a listing page URL and optional XPath link selector.
- Included enabled custom sources in the News Feed filters and article-source allowlist.

## 6.9.0

- Require explicit, separate `TITLE:` and `TAGLINE:` lines so headlines stay separate from article paragraphs.
- Parse the title and tagline independently for Foxiz and WordPress drafts while hiding internal labels in preview and copy.

## 6.8.0

- Read generated image bytes from the Gemini REST API's `steps` response format.
- Set the current Interactions API revision explicitly for image generation.
- Retry temporary Gemini image generation errors with exponential backoff.

## 6.7.0

- Use exponential backoff with jitter for Gemini transient errors, retrying up to four times.
- Explain persistent Gemini HTTP 503 errors as temporary service overloads.

## 6.6.0

- Save Foxiz taglines in both the theme's `rb_global_meta` field and its `ruby_tagline` compatibility field.
- Backfill the Foxiz editor field when opening drafts created by earlier plugin versions.
- Consistently convert article and SEO text numbers to Arabic-Indic digits while preserving Latin SEO slugs.
- Retry Gemini article requests after temporary 429, 500, 502, or 503 responses.

## 6.5.0

- Added featured image choices: generate an image with Gemini, use the story link image, or add no image.
- Save Gemini-generated images in the WordPress media library and set them as the post's featured image.
- Keep article draft creation successful when the image provider returns an error, and show the image error to the user.

## 6.4.0

- Save the generated article subheadline to Foxiz's `ruby_tagline` post meta field.
- Keep the tagline separate from the article body when creating WordPress drafts.

## 6.3.0

- Generated articles now include concise section headings for easier scanning.
- Styled article titles, subheadlines, section headings, and paragraphs in the preview.
- Converted section headings into WordPress H2 blocks when creating drafts.
- Kept copied article text free of Markdown heading markers.

## 6.2.1

- Updated the Gemini default to the supported Gemini 3.8 Flash model.
- Automatically migrated the retired Gemini 2.0 Flash setting.
- Added an actionable error message for Gemini model or endpoint 404 responses.

## 6.2.0

- Added server-side permission checks and validation for article generation inputs.
- Improved AI provider error handling and escaped error messages in the admin UI.
- Refreshed the writer and news feed interface for responsive layouts and keyboard access.
- Added protection against stale article fetch responses and inserting content while generation is in progress.
- Kept GitHub branch updates enabled through Plugin Update Checker.
