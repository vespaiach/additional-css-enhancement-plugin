<?php
/**
 * Uninstall cleanup for Additional CSS Enhancement.
 *
 * @package ACSSE
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

/**
 * Remove generated compiled CSS cache data from parsed blocks.
 *
 * @param array $blocks Parsed blocks.
 * @return array{blocks: array, changed: bool}
 */
function acsse_remove_compiled_css_from_blocks( $blocks ) {
	$changed = false;

	foreach ( $blocks as $index => $block ) {
		if ( isset( $block['attrs'] ) && is_array( $block['attrs'] ) && array_key_exists( 'acsseCompiledCss', $block['attrs'] ) ) {
			unset( $block['attrs']['acsseCompiledCss'] );
			$changed = true;
		}

		if ( isset( $block['innerBlocks'] ) && is_array( $block['innerBlocks'] ) ) {
			$inner_result = acsse_remove_compiled_css_from_blocks( $block['innerBlocks'] );
			if ( $inner_result['changed'] ) {
				$block['innerBlocks'] = $inner_result['blocks'];
				$changed              = true;
			}
		}

		$blocks[ $index ] = $block;
	}

	return array(
		'blocks'  => $blocks,
		'changed' => $changed,
	);
}

/**
 * Remove generated compiled CSS cache data from saved post content.
 */
function acsse_cleanup_saved_block_content() {
	if ( ! function_exists( 'get_posts' ) || ! function_exists( 'parse_blocks' ) || ! function_exists( 'serialize_blocks' ) ) {
		return;
	}

	$post_ids = get_posts(
		array(
			'fields'                 => 'ids',
			'post_type'              => 'any',
			'post_status'            => 'any',
			'posts_per_page'         => -1,
			's'                      => 'acsseCompiledCss',
			'no_found_rows'          => true,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
		)
	);

	foreach ( $post_ids as $post_id ) {
		$post = get_post( $post_id );
		if ( ! $post || ! is_string( $post->post_content ) || ! str_contains( $post->post_content, 'acsseCompiledCss' ) ) {
			continue;
		}

		$result = acsse_remove_compiled_css_from_blocks( parse_blocks( $post->post_content ) );
		if ( ! $result['changed'] ) {
			continue;
		}

		$next_content = serialize_blocks( $result['blocks'] );
		if ( $next_content === $post->post_content ) {
			continue;
		}

		wp_update_post(
			array(
				'ID'           => $post_id,
				'post_content' => $next_content,
			)
		);

		clean_post_cache( $post_id );
	}
}

if ( ! defined( 'ACSSE_UNINSTALL_TESTING' ) || ! ACSSE_UNINSTALL_TESTING ) {
	acsse_cleanup_saved_block_content();
}
