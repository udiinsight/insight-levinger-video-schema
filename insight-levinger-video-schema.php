<?php
/**
 * Plugin Name: Insight - Levinger - Video Schema
 * Plugin URI: https://github.com/udiinsight/insight-levinger-video-schema
 * Description: Outputs consultant-supplied VideoObject JSON-LD (stored per post in an ACF repeater) in the document head, alongside Yoast SEO.
 * Version: 1.0.0
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

define( 'INSIGHT_LEVINGER_VIDEO_SCHEMA_VERSION', '1.0.0' );
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
 * Defaults to every public post type except pages and attachments, which covers
 * articles (post) plus custom types such as treatments and doctors without
 * hard-coding their slugs. Filter to narrow the list to specific slugs.
 *
 * @return string[] List of post type slugs.
 */
function insight_levinger_video_schema_post_types() {
	$post_types = get_post_types( array( 'public' => true ), 'names' );
	$post_types = array_diff( $post_types, array( 'page', 'attachment' ) );

	/**
	 * Filters the post types that expose the video-schema field and front-end output.
	 *
	 * @param string[] $post_types List of post type slugs.
	 */
	$post_types = apply_filters( 'insight_levinger_video_schema_post_types', array_values( $post_types ) );

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

		$data = json_decode( (string) $row['schema_json'], true );

		if ( JSON_ERROR_NONE !== json_last_error() ) {
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
