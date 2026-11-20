# WordPress Gutenberg block import

## Status

Implemented

## Problem and goals

The WordPress importer receives saved or rendered Gutenberg HTML. The shared sanitizer preserves basic text and links, but some block types lose useful structure, attribution, or the link to their original media. Preserve more of that content in the existing editable Morfira blocks and fields.

## Flow and contracts

- WXR and REST share an import-only normalization step for recognized Gutenberg block classes, before image segmentation and rich-text sanitization.
- Heading levels outside Morfira's editable range map to levels 2–4. Buttons become text links. Quotes and pullquotes retain attribution. Details keep their summary and body. Verse keeps line breaks. Tables retain rows and readable cell boundaries.
- File, audio, video, and embed blocks can retain a safe link to the source when the saved block provides one. Galleries, media with text, and nested layout containers preserve content order through existing native image and rich-text conversion.
- Static navigation, social, page-list, RSS, read-more, home, login/logout, and post-excerpt links preserve readable labels. Icon-only links can use an explicit accessible label, title, or image alternative text. Navigation control buttons are omitted from the static projection.
- Site, post, query, and comment block headings use supported heading levels. Code and preformatted blocks retain line breaks, and content with significant whitespace keeps its preformatted representation. Archive and category dropdowns can become lists when their options expose explicit usable URLs.
- Classic gallery shortcodes with an explicit bounded ID list and legacy gallery image IDs use the existing attachment index. Each unresolved image is counted. Escaped, unsupported, and malformed shortcodes remain text.
- Native pages and schema-based posts use the same normalization rules. No API, database, dependency, or global sanitizer changes are required.

## Failure handling and security

Only recognized static HTML is normalized. Existing sanitization and import limits still apply. Executable markup and active embeds are discarded. Source files and links introduced by normalization are never fetched, and image resolution still uses the existing attachment policy. WordPress-specific styling, layout, playback, and interactive behavior require editorial review after import.

## Acceptance criteria

- [x] Recognized text and media blocks produce editable text, safe links, and existing image blocks in source order.
- [x] Malformed or unsupported variants keep the existing safe fallback. No active embed is introduced.
- [x] WXR and REST fixtures verify the stored and published result. Dry-run and replay retain their existing write guarantees.
- [x] Classic galleries and additional static text and link blocks retain their content in the repeated WXR/REST integration journey and targeted tests.

## Verification plan

Run the focused normalization and segmentation tests, the full backend Docker gate, and the WordPress production CLI journey.
