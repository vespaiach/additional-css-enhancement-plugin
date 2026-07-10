<?php
/**
 * Plugin Name: Additional CSS Enhancement
 * Description: Adds scoped Additional CSS controls and safe frontend rendering for selected core blocks.
 * Version: 1.0.0
 * Requires at least: 6.7
 * Requires PHP: 7.2
 * Author: Trinh Nguyen
 * Author URI: https://github.com/vespaiach
 * License: MIT
 * License URI: https://opensource.org/license/mit
 * Text Domain: acsse
 *
 * @package ACSSE
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'ACSSE_VERSION' ) ) {
	define( 'ACSSE_VERSION', '1.0.0' );
}

if ( ! defined( 'ACSSE_CACHE_VERSION' ) ) {
	define( 'ACSSE_CACHE_VERSION', 1 );
}

if ( ! defined( 'ACSSE_SELECTOR_TOKEN' ) ) {
	define( 'ACSSE_SELECTOR_TOKEN', '__ACSSE_CUSTOM_CSS_SELECTOR__' );
}

/**
 * Resolve the editor script asset.
 *
 * @return string
 */
function acsse_editor_asset() {
	return 'build/additional-css-enhancement.js';
}

/**
 * Resolve editor script dependencies and version.
 *
 * @return array
 */
function acsse_editor_asset_metadata() {
	$asset_path = plugin_dir_path( __FILE__ ) . 'build/additional-css-enhancement.asset.php';

	if ( file_exists( $asset_path ) ) {
		return require $asset_path;
	}

	$editor_asset_path = plugin_dir_path( __FILE__ ) . acsse_editor_asset();

	return array(
		'dependencies' => array(
			'react-jsx-runtime',
			'wp-block-editor',
			'wp-blocks',
			'wp-components',
			'wp-compose',
			'wp-data',
			'wp-element',
			'wp-hooks',
			'wp-i18n',
		),
		'version'      => file_exists( $editor_asset_path ) ? filemtime( $editor_asset_path ) : ACSSE_VERSION,
	);
}

/**
 * Enqueue the editor script.
 */
function acsse_enqueue_editor_assets() {
	$editor_js       = acsse_editor_asset();
	$editor_js_path  = plugin_dir_path( __FILE__ ) . $editor_js;
	$editor_js_asset = acsse_editor_asset_metadata();

	wp_enqueue_script(
		'acsse-editor',
		plugins_url( $editor_js, __FILE__ ),
		$editor_js_asset['dependencies'] ?? array(),
		$editor_js_asset['version'] ?? ( file_exists( $editor_js_path ) ? filemtime( $editor_js_path ) : ACSSE_VERSION ),
		true
	);
}
add_action( 'enqueue_block_editor_assets', 'acsse_enqueue_editor_assets' );

/**
 * Get the block types supported by Additional CSS Enhancement.
 *
 * @return array
 */
function acsse_get_supported_blocks() {
	static $supported_blocks = null;

	if ( null !== $supported_blocks ) {
		return $supported_blocks;
	}

	$supported_blocks_path = __DIR__ . '/supported-blocks.json';
	$decoded_blocks        = array();

	if ( is_readable( $supported_blocks_path ) ) {
		$decoded_blocks = json_decode( file_get_contents( $supported_blocks_path ), true );
	}

	if ( ! is_array( $decoded_blocks ) ) {
		$supported_blocks = array();
		return $supported_blocks;
	}

	$supported_blocks = array_values(
		array_filter(
			$decoded_blocks,
			function ( $block_name ) {
				return is_string( $block_name ) && '' !== $block_name;
			}
		)
	);

	return $supported_blocks;
}

/**
 * Determine whether a block supports Additional CSS Enhancement.
 *
 * @param string|null $block_type Block type name.
 * @return bool
 */
function acsse_block_supports_additional_css_enhancement( $block_type ) {
	return in_array( $block_type, acsse_get_supported_blocks(), true );
}

/**
 * Register the compiled custom CSS cache attribute on supported blocks.
 *
 * @param array  $args       Block type arguments.
 * @param string $block_type Block type name.
 * @return array
 */
function acsse_register_additional_css_enhancement_attribute( $args, $block_type ) {
	if ( ! acsse_block_supports_additional_css_enhancement( $block_type ) ) {
		return $args;
	}

	if ( ! isset( $args['attributes'] ) || ! is_array( $args['attributes'] ) ) {
		$args['attributes'] = array();
	}

	$args['attributes']['acsseCompiledCss'] = array(
		'type'    => 'object',
		'default' => array(),
	);

	if ( ! isset( $args['supports'] ) || ! is_array( $args['supports'] ) ) {
		$args['supports'] = array();
	}

	$args['supports']['customCSS'] = false;

	return $args;
}
add_filter( 'register_block_type_args', 'acsse_register_additional_css_enhancement_attribute', 10, 2 );

/**
 * Determine whether a custom CSS value is usable.
 *
 * @param mixed $css CSS value.
 * @return bool
 */
function acsse_is_valid_custom_css_value( $css ) {
	return is_string( $css ) && '' !== trim( $css ) && ! preg_match( '#</?\w+#', $css );
}

/**
 * Read the first core custom CSS class from a className attribute.
 *
 * @param array $attrs Block attributes.
 * @return string|null
 */
function acsse_get_block_custom_css_class_name( $attrs ) {
	$class_name_attr = $attrs['className'] ?? null;

	if ( ! is_string( $class_name_attr ) || ! str_contains( $class_name_attr, 'wp-custom-css-' ) ) {
		return null;
	}

	$token_delimiter = " \t\f\r\n";
	$class_token     = strtok( $class_name_attr, $token_delimiter );
	while ( false !== $class_token ) {
		if ( str_starts_with( $class_token, 'wp-custom-css-' ) ) {
			return $class_token;
		}
		$class_token = strtok( $token_delimiter );
	}

	return null;
}

/**
 * Register the inline style handle used by core block custom CSS.
 *
 * The enhanced custom CSS support disables core custom CSS, so core will not register this
 * handle for these blocks. This mirrors core's handle/dependency model.
 */
function acsse_register_block_custom_css_style_handle() {
	if ( ! wp_style_is( 'wp-block-custom-css', 'registered' ) ) {
		wp_register_style( 'wp-block-custom-css', false, array( 'global-styles' ) );
	}
}

/**
 * Enqueue the inline style handle used by compiled enhanced custom CSS.
 */
function acsse_enqueue_block_custom_css_style_handle() {
	acsse_register_block_custom_css_style_handle();
	wp_enqueue_style( 'wp-block-custom-css' );
}
add_action( 'wp_enqueue_scripts', 'acsse_enqueue_block_custom_css_style_handle', 1 );

/**
 * Determine whether a compiled CSS template is safe to render.
 *
 * This validates the editor-compiled cache only. It is not a raw CSS parser.
 * Every CSS rule must include the selector token, and only the supported
 * wrapper at-rules may omit it.
 *
 * @param string $css Compiled CSS template.
 * @return bool
 */
function acsse_is_valid_additional_css_enhancement_template( $css ) {
	$segment    = '';
	$depth      = 0;
	$length     = strlen( $css );
	$quote      = null;
	$is_escaped = false;
	$is_comment = false;

	for ( $index = 0; $index < $length; $index++ ) {
		$character = $css[ $index ];

		if ( $is_comment ) {
			if ( '*' === $character && isset( $css[ $index + 1 ] ) && '/' === $css[ $index + 1 ] ) {
				$is_comment = false;
				++$index;
			}
			continue;
		}

		if ( null !== $quote ) {
			if ( $is_escaped ) {
				$is_escaped = false;
				continue;
			}

			if ( '\\' === $character ) {
				$is_escaped = true;
				continue;
			}

			if ( $quote === $character ) {
				$quote = null;
			}
			continue;
		}

		if ( '/' === $character && isset( $css[ $index + 1 ] ) && '*' === $css[ $index + 1 ] ) {
			$is_comment = true;
			++$index;
			continue;
		}

		if ( '"' === $character || "'" === $character ) {
			$quote = $character;
			continue;
		}

		if ( '{' === $character ) {
			$prelude = trim( $segment );

			if ( '' === $prelude ) {
				return false;
			}

			$is_supported_at_rule = (bool) preg_match( '/^@(media|supports|container)\b/i', $prelude );
			$is_at_rule           = str_starts_with( $prelude, '@' );
			$has_selector_token   = str_contains( $prelude, ACSSE_SELECTOR_TOKEN );

			if ( $is_at_rule && ! $is_supported_at_rule ) {
				return false;
			}

			if ( ! $is_supported_at_rule && ! $has_selector_token ) {
				return false;
			}

			++$depth;
			$segment = '';
			continue;
		}

		if ( '}' === $character ) {
			--$depth;
			if ( $depth < 0 ) {
				return false;
			}

			$segment = '';
			continue;
		}

		$segment .= $character;
	}

	return ! $is_comment && null === $quote && 0 === $depth && '' === trim( $segment );
}

/**
 * Read a valid Additional CSS Enhancement cache from block attributes.
 *
 * The raw style.css value is the source of truth. The compiled cache is only
 * usable when it matches the current raw source hash and contains the selector
 * token that PHP replaces at render time.
 *
 * @param array $attrs Block attributes.
 * @return string|null
 */
function acsse_get_additional_css_enhancement_cache( $attrs ) {
	$raw_css = $attrs['style']['css'] ?? null;

	if ( ! acsse_is_valid_custom_css_value( $raw_css ) ) {
		return null;
	}

	$compiled_css = $attrs['acsseCompiledCss'] ?? null;
	if ( ! is_array( $compiled_css ) ) {
		return null;
	}

	if ( ACSSE_CACHE_VERSION !== (int) ( $compiled_css['version'] ?? 0 ) ) {
		return null;
	}

	$source_hash = $compiled_css['sourceHash'] ?? null;
	if ( ! is_string( $source_hash ) || ! hash_equals( hash( 'sha256', $raw_css ), $source_hash ) ) {
		return null;
	}

	$css = $compiled_css['css'] ?? null;
	if ( ! acsse_is_valid_custom_css_value( $css ) ) {
		return null;
	}

	if ( ! str_contains( $css, ACSSE_SELECTOR_TOKEN ) ) {
		return null;
	}

	if ( ! acsse_is_valid_additional_css_enhancement_template( $css ) ) {
		return null;
	}

	if ( str_contains( $css, 'wp-custom-css-' ) || str_contains( $css, '[data-block=' ) ) {
		return null;
	}

	return $css;
}

/**
 * Add a stable custom CSS class and enqueue compiled CSS for supported blocks.
 *
 * @param array $parsed_block Parsed block data.
 * @return array
 */
function acsse_render_additional_css_enhancement( $parsed_block ) {
	if ( ! acsse_block_supports_additional_css_enhancement( $parsed_block['blockName'] ?? null ) ) {
		return $parsed_block;
	}

	$attrs        = $parsed_block['attrs'] ?? array();
	$compiled_css = acsse_get_additional_css_enhancement_cache( $attrs );

	if ( null === $compiled_css ) {
		return $parsed_block;
	}

	$class_name = acsse_get_block_custom_css_class_name( $attrs );
	if ( null === $class_name && function_exists( 'wp_unique_id_from_values' ) ) {
		$class_name = wp_unique_id_from_values( $parsed_block, 'wp-custom-css-' );
	}

	if ( null === $class_name ) {
		$class_name = 'wp-custom-css-' . substr( md5( wp_json_encode( $parsed_block ) ), 0, 8 );
	}

	if ( null === $class_name ) {
		return $parsed_block;
	}

	$existing_class_name = $attrs['className'] ?? null;
	if ( ! is_string( $existing_class_name ) || ! str_contains( $existing_class_name, $class_name ) ) {
		$updated_class_name = is_string( $existing_class_name )
			? trim( $existing_class_name . ' ' . $class_name )
			: $class_name;

		_wp_array_set( $parsed_block, array( 'attrs', 'className' ), $updated_class_name );
	}

	$selector = '.' . sanitize_html_class( $class_name );
	$css      = str_replace( ACSSE_SELECTOR_TOKEN, $selector, $compiled_css );

	acsse_register_block_custom_css_style_handle();
	wp_add_inline_style( 'wp-block-custom-css', $css );

	return $parsed_block;
}
add_filter( 'render_block_data', 'acsse_render_additional_css_enhancement', 11, 1 );
