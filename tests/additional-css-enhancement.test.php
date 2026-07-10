<?php
/**
 * Tests for Additional CSS Enhancement.
 *
 * @package ACSSE
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', dirname( __DIR__ ) . '/' );
}

if ( ! function_exists( 'add_action' ) ) {
	/**
	 * Stub WordPress hooks so the plugin can be loaded without WordPress.
	 */
	function add_action() {
		return true;
	}
}

if ( ! function_exists( 'add_filter' ) ) {
	/**
	 * Stub WordPress hooks so the plugin can be loaded without WordPress.
	 */
	function add_filter() {
		return true;
	}
}

if ( ! function_exists( 'wp_enqueue_script' ) ) {
	/**
	 * Stub WordPress script enqueueing.
	 */
	function wp_enqueue_script() {
		return true;
	}
}

if ( ! function_exists( 'plugins_url' ) ) {
	/**
	 * Minimal plugins_url polyfill for tests.
	 *
	 * @param string $path Path relative to plugin file.
	 * @return string
	 */
	function plugins_url( $path ) {
		return $path;
	}
}

if ( ! function_exists( 'wp_style_is' ) ) {
	/**
	 * Stub WordPress style checks.
	 */
	function wp_style_is() {
		return false;
	}
}

if ( ! function_exists( 'wp_register_style' ) ) {
	/**
	 * Stub WordPress style registration.
	 */
	function wp_register_style() {
		return true;
	}
}

if ( ! function_exists( 'wp_enqueue_style' ) ) {
	/**
	 * Stub WordPress style enqueueing.
	 */
	function wp_enqueue_style() {
		return true;
	}
}

if ( ! function_exists( 'wp_add_inline_style' ) ) {
	/**
	 * Capture inline styles for render tests.
	 *
	 * @param string $handle Style handle.
	 * @param string $css    Inline CSS.
	 * @return bool
	 */
	function wp_add_inline_style( $handle, $css ) {
		$GLOBALS['acsse_test_inline_styles'][] = array( $handle, $css );
		return true;
	}
}

if ( ! function_exists( '_wp_array_set' ) ) {
	/**
	 * Minimal _wp_array_set polyfill for render tests.
	 *
	 * @param array $array Array to mutate.
	 * @param array $path  Path of keys.
	 * @param mixed $value Value to set.
	 */
	function _wp_array_set( &$array, $path, $value ) {
		$ref = &$array;
		foreach ( $path as $key ) {
			if ( ! isset( $ref[ $key ] ) || ! is_array( $ref[ $key ] ) ) {
				$ref[ $key ] = array();
			}
			$ref = &$ref[ $key ];
		}
		$ref = $value;
	}
}

if ( ! function_exists( 'sanitize_html_class' ) ) {
	/**
	 * Minimal sanitize_html_class polyfill for render tests.
	 *
	 * @param string $class_name Class name.
	 * @return string
	 */
	function sanitize_html_class( $class_name ) {
		return preg_replace( '/[^A-Za-z0-9_-]/', '', $class_name );
	}
}

if ( ! function_exists( 'wp_json_encode' ) ) {
	/**
	 * Minimal wp_json_encode polyfill for render tests.
	 *
	 * @param mixed $value Value to encode.
	 * @return string
	 */
	function wp_json_encode( $value ) {
		return json_encode( $value );
	}
}

require dirname( __DIR__ ) . '/additional-css-enhancement.php';

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	define( 'WP_UNINSTALL_PLUGIN', true );
}

if ( ! defined( 'ACSSE_UNINSTALL_TESTING' ) ) {
	define( 'ACSSE_UNINSTALL_TESTING', true );
}

require dirname( __DIR__ ) . '/uninstall.php';

/**
 * Assert that two values are identical.
 *
 * @param mixed  $expected Expected value.
 * @param mixed  $actual   Actual value.
 * @param string $message  Assertion message.
 */
function acsse_assert_same( $expected, $actual, $message ) {
	if ( $expected !== $actual ) {
		throw new Exception(
			sprintf(
				'%s Expected %s, got %s.',
				$message,
				var_export( $expected, true ),
				var_export( $actual, true )
			)
		);
	}
}

$token = ACSSE_SELECTOR_TOKEN;

$valid_cases = array(
	'accepts a scoped top-level declaration rule' => ':root ' . $token . ' {color: red; padding: 10px}',
	'accepts a scoped nested selector rule'       => ':root ' . $token . ':hover { color: blue }',
	'accepts scoped comma-separated selectors'    => ':root ' . $token . ':hover, :root ' . $token . '.is-active { color: blue }',
	'accepts comments with brace characters'      => '/* before { } */ :root ' . $token . ' { color: red; /* inside } */ padding: 10px }',
	'accepts quoted brace characters'             => ':root ' . $token . '::before { content: "}"; mask-image: url("data:image/svg+xml,%7B%7D") }',
	'accepts escaped quoted strings'              => ':root ' . $token . '::after { content: "He said \"{\""; }',
	'accepts supported wrapper at-rules'          => implode(
		"\n",
		array(
			'@media (min-width: 600px) { :root ' . $token . ' { color: red } }',
			'@supports (display: grid) { :root ' . $token . ' { display: grid } }',
			'@container (min-width: 400px) { :root ' . $token . ' { gap: 20px } }',
		)
	),
	'accepts case-insensitive wrapper at-rules'   => '@MEDIA (min-width: 600px) { :root ' . $token . ' { color: red } }',
	'accepts nested supported wrapper at-rules'   => '@media (min-width: 600px) { @supports (display: grid) { :root ' . $token . ' { display: grid } } }',
);

$invalid_cases = array(
	'rejects an empty prelude'                             => '{ color: red }',
	'rejects an unscoped rule'                             => 'body { color: red }',
	'rejects an unscoped rule inside a wrapper'            => '@media (min-width: 600px) { body { color: red } }',
	'rejects unsupported at-rules'                         => '@font-face { font-family: Bad; src: url(bad.woff2) }',
	'rejects unsupported at-rules even with the token'     => '@layer ' . $token . ' { :root ' . $token . ' { color: red } }',
	'rejects missing closing braces'                       => ':root ' . $token . ' { color: red',
	'rejects extra closing braces'                         => ':root ' . $token . ' { color: red }}',
	'rejects trailing non-whitespace text'                 => ':root ' . $token . ' { color: red } color: blue;',
	'rejects at-rules without blocks'                      => '@media (min-width: 600px);',
	'rejects unclosed quoted strings'                      => ':root ' . $token . ' { content: "oops }',
	'rejects unclosed block comments'                      => ':root ' . $token . ' { color: red /* oops }',
	'rejects selector tokens that appear only in comments' => '/* ' . $token . ' */ body { color: red }',
);

$failures        = array();
$assertion_count = 0;

foreach ( $valid_cases as $message => $css ) {
	++$assertion_count;
	try {
		acsse_assert_same( true, acsse_is_valid_additional_css_enhancement_template( $css ), $message );
		printf( "PASS valid: %s\n", $message );
	} catch ( Exception $exception ) {
		printf( "FAIL valid: %s\n", $message );
		$failures[] = $exception->getMessage();
	}
}

foreach ( $invalid_cases as $message => $css ) {
	++$assertion_count;
	try {
		acsse_assert_same( false, acsse_is_valid_additional_css_enhancement_template( $css ), $message );
		printf( "PASS invalid: %s\n", $message );
	} catch ( Exception $exception ) {
		printf( "FAIL invalid: %s\n", $message );
		$failures[] = $exception->getMessage();
	}
}

if ( count( $failures ) > 0 ) {
	fwrite( STDERR, "Additional CSS Enhancement failures:\n" );
	foreach ( $failures as $failure ) {
		fwrite( STDERR, '- ' . $failure . "\n" );
	}
	exit( 1 );
}

$supported_blocks = acsse_get_supported_blocks();

++ $assertion_count;
try {
	acsse_assert_same( true, count( $supported_blocks ) > 0, 'supported block list is loaded' );
	printf( "PASS supported block list loaded\n" );
} catch ( Exception $exception ) {
	printf( "FAIL supported block list loaded\n" );
	$failures[] = $exception->getMessage();
}

foreach ( $supported_blocks as $block_name ) {
	++$assertion_count;
	try {
		acsse_assert_same( true, acsse_block_supports_additional_css_enhancement( $block_name ), $block_name . ' is supported' );
		printf( "PASS supported block: %s\n", $block_name );
	} catch ( Exception $exception ) {
		printf( "FAIL supported block: %s\n", $block_name );
		$failures[] = $exception->getMessage();
	}

	++$assertion_count;
	try {
		$args = acsse_register_additional_css_enhancement_attribute( array(), $block_name );
		acsse_assert_same( array( 'type' => 'object', 'default' => array() ), $args['attributes']['acsseCompiledCss'], $block_name . ' registers compiled cache attribute' );
		acsse_assert_same( false, $args['supports']['customCSS'], $block_name . ' disables core custom CSS' );
		printf( "PASS register attribute: %s\n", $block_name );
	} catch ( Exception $exception ) {
		printf( "FAIL register attribute: %s\n", $block_name );
		$failures[] = $exception->getMessage();
	}
}

++ $assertion_count;
try {
	acsse_assert_same( false, acsse_block_supports_additional_css_enhancement( 'core/html' ), 'core/html is not supported' );
	$args = array( 'attributes' => array(), 'supports' => array() );
	acsse_assert_same( $args, acsse_register_additional_css_enhancement_attribute( $args, 'core/html' ), 'unsupported blocks are unchanged' );
	printf( "PASS unsupported block: core/html\n" );
} catch ( Exception $exception ) {
	printf( "FAIL unsupported block: core/html\n" );
	$failures[] = $exception->getMessage();
}

$raw_css      = 'color: red;';
$compiled_css = ':root ' . $token . ' {color: red}';
$parsed_block = array(
	'blockName' => 'core/paragraph',
	'attrs'     => array(
		'style'           => array(
			'css' => $raw_css,
		),
		'acsseCompiledCss' => array(
			'version'    => ACSSE_CACHE_VERSION,
			'sourceHash' => hash( 'sha256', $raw_css ),
			'css'        => $compiled_css,
		),
	),
);

$GLOBALS['acsse_test_inline_styles'] = array();
++ $assertion_count;
try {
	$rendered = acsse_render_additional_css_enhancement( $parsed_block );
	acsse_assert_same( true, isset( $rendered['attrs']['className'] ), 'render adds a custom CSS class' );
	acsse_assert_same( true, str_starts_with( $rendered['attrs']['className'], 'wp-custom-css-' ), 'render class uses the core custom CSS prefix' );
	acsse_assert_same( array( array( 'wp-block-custom-css', ':root .' . $rendered['attrs']['className'] . ' {color: red}' ) ), $GLOBALS['acsse_test_inline_styles'], 'render adds scoped inline CSS' );
	printf( "PASS render supported block\n" );
} catch ( Exception $exception ) {
	printf( "FAIL render supported block\n" );
	$failures[] = $exception->getMessage();
}

$GLOBALS['acsse_test_inline_styles'] = array();
++ $assertion_count;
try {
	$html_block              = $parsed_block;
	$html_block['blockName'] = 'core/html';
	acsse_assert_same( $html_block, acsse_render_additional_css_enhancement( $html_block ), 'unsupported render block is unchanged' );
	acsse_assert_same( array(), $GLOBALS['acsse_test_inline_styles'], 'unsupported render block adds no inline CSS' );
	printf( "PASS render unsupported block\n" );
} catch ( Exception $exception ) {
	printf( "FAIL render unsupported block\n" );
	$failures[] = $exception->getMessage();
}

$blocks = array(
	array(
		'blockName'    => 'core/group',
		'attrs'        => array(
			'style'           => array(
				'css' => 'color: red;',
			),
			'acsseCompiledCss' => array(
				'version' => 1,
			),
		),
		'innerBlocks'  => array(
			array(
				'blockName' => 'core/paragraph',
				'attrs'     => array(
					'acsseCompiledCss' => array(
						'version' => 1,
					),
				),
			),
		),
		'innerContent' => array(),
	),
);

$cleanup_result = acsse_remove_compiled_css_from_blocks( $blocks );
++ $assertion_count;
try {
	acsse_assert_same( true, $cleanup_result['changed'], 'uninstall cleanup reports changed blocks' );
	acsse_assert_same( false, isset( $cleanup_result['blocks'][0]['attrs']['acsseCompiledCss'] ), 'uninstall removes top-level compiled cache' );
	acsse_assert_same( 'color: red;', $cleanup_result['blocks'][0]['attrs']['style']['css'], 'uninstall preserves raw style.css' );
	acsse_assert_same( false, isset( $cleanup_result['blocks'][0]['innerBlocks'][0]['attrs']['acsseCompiledCss'] ), 'uninstall removes nested compiled cache' );
	printf( "PASS uninstall compiled cache cleanup\n" );
} catch ( Exception $exception ) {
	printf( "FAIL uninstall compiled cache cleanup\n" );
	$failures[] = $exception->getMessage();
}

if ( count( $failures ) > 0 ) {
	fwrite( STDERR, "Additional CSS Enhancement failures:\n" );
	foreach ( $failures as $failure ) {
		fwrite( STDERR, '- ' . $failure . "\n" );
	}
	exit( 1 );
}

printf(
	"Additional CSS Enhancement: %d assertions passed.\n",
	$assertion_count
);
