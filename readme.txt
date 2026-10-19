=== Insight - Levinger - Video Schema ===
Contributors: insightmarketing
Tags: schema, seo, video, json-ld, structured-data
Requires at least: 6.0
Tested up to: 6.7
Requires PHP: 8.0
Stable tag: 1.1.1
License: Proprietary
License URI: https://insight-marketing.co.il

Outputs consultant-supplied VideoObject JSON-LD per post in the document head, alongside Yoast SEO.

== Description ==

Adds a "Video Schema (JSON-LD)" repeater (via ACF) to content post types. Paste the
VideoObject JSON-LD blocks supplied by the SEO consultant — one row per video — and the
plugin validates each block and prints it in the <head> as its own
<script type="application/ld+json"> tag, next to (not merged into) the Yoast graph.

Standard Yoast SEO does not emit VideoObject schema, so this fills that gap without
duplicating Yoast's output. Blocks whose top-level @type is Organization, WebSite, or
WebPage are skipped, so they can never collide with the Yoast graph.

= Which post types =

The field and output apply to these post types: posts, pages, and the `procedure`,
`medical-center`, `movie`, `doctor`, and `sector` custom post types. Add or remove
slugs with the `insight_levinger_video_schema_post_types` filter, e.g.:

    add_filter( 'insight_levinger_video_schema_post_types', function () {
        return array( 'post', 'page', 'procedure', 'medical-center', 'movie', 'doctor', 'sector' );
    } );

= Requirements =

* Advanced Custom Fields (ACF) — the repeater field needs ACF active. An admin notice
  appears if ACF is missing. (Repeater is included in ACF 6.3+ free, or any ACF Pro.)

== Changelog ==

= 1.1.1 =
* Normalise pasted JSON-LD before decoding: non-breaking spaces become plain spaces and
  the BOM and zero-width / direction marks (U+200B to U+200F) are removed, so blocks
  copied from Google Docs or Word no longer fail silently.
* Show a red warning under any row whose JSON is still invalid on the post edit screen.

= 1.1.0 =
* Scope the field/output to an explicit post-type list: posts, pages, and the procedure,
  medical-center, movie, doctor, and sector CPTs (still filterable).

= 1.0.0 =
* Initial release: per-post VideoObject JSON-LD output in the head, ACF repeater storage,
  public-post-type targeting with a filter, malformed-JSON is ignored, and Yoast-owned
  node types are skipped to avoid conflicts.
