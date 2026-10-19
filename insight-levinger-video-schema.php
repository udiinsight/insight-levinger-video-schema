<?php
/**
 * Plugin Name: Insight - Levinger - Video Schema
 * Plugin URI: https://github.com/udiinsight/insight-levinger-video-schema
 * Description: Outputs consultant-supplied VideoObject JSON-LD (stored per post in an ACF repeater) in the document head, alongside Yoast SEO.
 * Version: 1.1.1
 * Author: Insight Marketing
 * Author URI: https://insight-marketing.co.il
 * Text Domain: insight-levinger-video-schema
 * Domain Path: /languages
 * Requires at least: 6.0
 * Requires PHP: 8.0
 *
 * @package Insight\Levinger\VideoSchema
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'INSIGHT_LEVINGER_VIDEO_SCHEMA_VERSION', '1.1.1' );
define( 'INSIGHT_LEVINGER_VIDEO_SCHEMA_PATH', plugin_dir_path( __FILE__ ) );
define( 'INSIGHT_LEVINGER_VIDEO_SCHEMA_URL', plugin_dir_url( __FILE__ ) );

/**
 * Load the plugin text domain for translations (Hebrew RTL support).
 *
 * @return void
 */
function insight_levinger_video_schema_load_textdomain() {
	load_plugin_textdomain( 'insight-levinger-video-schema', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
}
add_action( 'plugins_loaded', 'insight_levinger_video_schema_load_textdomain' );

/**
 * Resolve the post types that should carry the video-schema field and output.
 *
 * Targets the content types that carry consultant-supplied video schema: posts,
 * pages, and the procedure / medical-center / movie / doctor / sector CPTs.
 * Filter to add or remove slugs.
 *
 * @return string[] List of post type slugs.
 */
function insight_levinger_video_schema_post_types() {
	$post_types = array(
		'post',
		'page',
		'procedure',
		'medical-center',
		'movie',
		'doctor',
		'sector',
	);

	/**
	 * Filters the post types that expose the video-schema field and front-end output.
	 *
	 * @param string[] $post_types List of post type slugs.
	 */
	$post_types = apply_filters( 'insight_levinger_video_schema_post_types', $post_types );

	return array_values( array_unique( array_filter( (array) $post_types ) ) );
}

/**
 * Register the ACF repeater that stores one or more JSON-LD blocks per post.
 *
 * Registered on `init` (priority 20) so every custom post type is available
 * when the location rules are built. No-ops when ACF is not active.
 *
 * @return void
 */
function insight_levinger_video_schema_register_fields() {
	if ( ! function_exists( 'acf_add_local_field_group' ) ) {
		return;
	}

	$post_types = insight_levinger_video_schema_post_types();

	if ( empty( $post_types ) ) {
		return;
	}

	$location = array();

	foreach ( $post_types as $post_type ) {
		$location[] = array(
			array(
				'param'    => 'post_type',
				'operator' => '==',
				'value'    => $post_type,
			),
		);
	}

	acf_add_local_field_group(
		array(
			'key'         => 'group_insight_levinger_video_schema',
			'title'       => __( 'Video Schema (JSON-LD)', 'insight-levinger-video-schema' ),
			'fields'      => array(
				array(
					'key'          => 'field_insight_levinger_video_schemas',
					'label'        => __( 'Videos', 'insight-levinger-video-schema' ),
					'name'         => 'video_schemas',
					'type'         => 'repeater',
					'instructions' => __( 'Paste each VideoObject JSON-LD block supplied by the SEO consultant. One row per video. Invalid JSON is ignored on the front end.', 'insight-levinger-video-schema' ),
					'layout'       => 'block',
					'button_label' => __( 'Add video', 'insight-levinger-video-schema' ),
					'sub_fields'   => array(
						array(
							'key'         => 'field_insight_levinger_video_schema_json',
							'label'       => __( 'VideoObject JSON-LD', 'insight-levinger-video-schema' ),
							'name'        => 'schema_json',
							'type'        => 'textarea',
							'rows'        => 10,
							'placeholder' => '{ "@context": "https://schema.org", "@type": "VideoObject", ... }',
						),
					),
				),
			),
			'location'    => $location,
			'menu_order'  => 0,
			'position'    => 'normal',
			'style'       => 'default',
			'active'      => true,
			'description' => __( 'Front-end VideoObject structured data, printed in the head alongside Yoast.', 'insight-levinger-video-schema' ),
		)
	);
}
add_action( 'init', 'insight_levinger_video_schema_register_fields', 20 );

/**
 * Determine whether a decoded JSON-LD block should be skipped.
 *
 * Blocks whose top-level @type is owned by the Yoast graph (Organization,
 * WebSite, WebPage) are skipped to avoid duplicate or conflicting nodes.
 *
 * @param mixed $data Decoded JSON-LD value.
 * @return bool True when the block must not be printed.
 */
function insight_levinger_video_schema_is_blocked( $data ) {
	if ( ! is_array( $data ) || ! isset( $data['@type'] ) ) {
		return false;
	}

	$blocked = array( 'Organization', 'WebSite', 'WebPage' );
	$types   = (array) $data['@type'];

	return (bool) array_intersect( $blocked, $types );
}

/**
 * Normalise pasted JSON-LD before decoding.
 *
 * Text copied from Google Docs or Word often carries non-breaking spaces in the
 * indentation, a BOM, or zero-width / direction marks, which json_decode rejects.
 * NBSP becomes a plain space; the BOM and U+200B to U+200F are removed.
 *
 * @param string $json Raw textarea value.
 * @return string Cleaned string.
 */
function insight_levinger_video_schema_normalize( $json ) {
	$json = str_replace( "\xC2\xA0", ' ', (string) $json );
	$json = str_replace(
		array( "\xEF\xBB\xBF", "\xE2\x80\x8B", "\xE2\x80\x8C", "\xE2\x80\x8D", "\xE2\x80\x8E", "\xE2\x80\x8F" ),
		'',
		$json
	);

	return trim( $json );
}

/**
 * Decode one stored JSON-LD block.
 *
 * @param string $json Raw textarea value.
 * @return array|null Decoded block, or null when the JSON is invalid or not an object/array.
 */
function insight_levinger_video_schema_decode( $json ) {
	$data = json_decode( insight_levinger_video_schema_normalize( $json ), true );

	if ( JSON_ERROR_NONE !== json_last_error() || ! is_array( $data ) ) {
		return null;
	}

	return $data;
}

/**
 * Print the stored VideoObject JSON-LD blocks in the document head.
 *
 * @return void
 */
function insight_levinger_video_schema_output() {
	if ( ! function_exists( 'get_field' ) ) {
		return;
	}

	if ( ! is_singular( insight_levinger_video_schema_post_types() ) ) {
		return;
	}

	$rows = get_field( 'video_schemas' );

	if ( empty( $rows ) || ! is_array( $rows ) ) {
		return;
	}

	foreach ( $rows as $row ) {
		if ( empty( $row['schema_json'] ) ) {
			continue;
		}

		$data = insight_levinger_video_schema_decode( $row['schema_json'] );

		if ( null === $data ) {
			continue;
		}

		if ( insight_levinger_video_schema_is_blocked( $data ) ) {
			continue;
		}

		$json = wp_json_encode( $data, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG );

		if ( false === $json ) {
			continue;
		}

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wp_json_encode with JSON_HEX_TAG escapes < and > so the value is safe inside an inline <script>.
		echo "\n" . '<script type="application/ld+json">' . $json . '</script>' . "\n";
	}
}
add_action( 'wp_head', 'insight_levinger_video_schema_output' );

/**
 * Warn under a JSON-LD textarea in the editor when its stored value still fails to decode.
 *
 * Such a row is skipped on the front end, so without this the editor gets no signal.
 *
 * @param array $field ACF field being rendered.
 * @return void
 */
function insight_levinger_video_schema_render_field_warning( $field ) {
	if ( empty( $field['value'] ) || ! is_string( $field['value'] ) ) {
		return;
	}

	if ( null !== insight_levinger_video_schema_decode( $field['value'] ) ) {
		return;
	}

	$error = JSON_ERROR_NONE !== json_last_error() ? json_last_error_msg() : __( 'not a JSON object', 'insight-levinger-video-schema' );

	echo '<p style="color:#b32d2e;font-weight:600;margin-top:6px;">';
	/* translators: %s: JSON parser error message. */
	echo esc_html( sprintf( __( 'This JSON-LD is invalid and is NOT printed on the page (%s). Validate it at validator.schema.org and paste it again.', 'insight-levinger-video-schema' ), $error ) );
	echo '</p>';
}
add_action( 'acf/render_field/key=field_insight_levinger_video_schema_json', 'insight_levinger_video_schema_render_field_warning', 20 );

/**
 * Show an admin notice when ACF is missing, since the field cannot render without it.
 *
 * @return void
 */
function insight_levinger_video_schema_acf_notice() {
	if ( function_exists( 'acf_add_local_field_group' ) ) {
		return;
	}

	if ( ! current_user_can( 'activate_plugins' ) ) {
		return;
	}

	echo '<div class="notice notice-warning"><p>';
	echo esc_html__( 'Insight – Levinger – Video Schema needs Advanced Custom Fields (ACF) active to store and output video schema. Please activate ACF.', 'insight-levinger-video-schema' );
	echo '</p></div>';
}
add_action( 'admin_notices', 'insight_levinger_video_schema_acf_notice' );
