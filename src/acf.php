<?php
/**
 * Functionality for integrating with ACF.
 *
 * @package wrd\wp-blocks
 *
 * @since 1.0.0
 */

namespace wrd\wp_blocks\acf;

use function wrd\wp_blocks\core\get_theme_block_name;
use function wrd\wp_blocks\templating\get_block_directory;

/**
 * Setup the ACF local JSON feature to save block related field groups to that block's directory and load JSON field groups from there too.
 *
 * @param string $block_dir The block directory.
 *
 * @return void
 *
 * @internal
 *
 * @since 1.3.2
 */
function register_theme_block_acf_json( string $block_dir ): void {
	register_theme_block_acf_json_for_non_synced_files( $block_dir );

	// Update the load path.
	add_filter(
		'acf/settings/load_json',
		function( array $paths ) use ( $block_dir ): array {
			$paths[] = $block_dir;
			return $paths;
		},
		10,
		1
	);

	// Update the save path.
	add_filter(
		'acf/json/save_paths',
		function( array $paths, array $post ) use ( $block_dir ): array {
			$block_name = get_theme_block_name( $block_dir );

			if ( ! is_acf_post_for_theme_block( $post, $block_name ) ) {
				return $paths;
			}

			return array( $block_dir );
		},
		10,
		2
	);
}

/**
 * Checks if an ACF post data is a field group for a given theme block.
 *
 * @param array  $post An array of settings for the field group, post type, taxonomy, or options page being saved.
 *
 * @param string $block_name The name of the block to look for.
 *
 * @return bool
 *
 * @internal
 *
 * @since 1.3.2
 */
function is_acf_post_for_theme_block( array $post, string $block_name ): bool {
	if ( ! array_key_exists( 'location', $post ) ) {
		return false;
	}

	$and_rules = $post['location'];

	if ( count( $and_rules ) !== 1 ) {
		// ACF groups we recognize are for a single block.
		return false;
	}

	$or_rules = $and_rules[0];

	if ( count( $or_rules ) !== 1 ) {
		// ACF groups we recognize are for a single block.
		return false;
	}

	$rule = $or_rules[0];

	if ( ! array_key_exists( 'param', $rule ) || 'block' !== $rule['param'] ) {
		// Rule is not for a block.
		return false;
	}

	if ( ! array_key_exists( 'operator', $rule ) || '==' !== $rule['operator'] ) {
		// Rule has something odd with it's operators - bail!
		return false;
	}

	if ( ! array_key_exists( 'value', $rule ) || $block_name !== $rule['value'] ) {
		// Rule is for another block.
		return false;
	}

	return true;
}

/**
 * Toggle whether ACF should add a wrapper to the inner blocks.
 *
 * Blocks are always wrapped when displaying in the editor! You might use display: 'contents' to help.
 *
 * @param string $block_name The name of the block to stop wrapper in.
 *
 * @param ?bool  $use_wrapper Whether to use the wrapper. Optional, defaults to false.
 *
 * @return void
 *
 * @since 1.0.0
 */
function set_use_inner_blocks_wrapper( $block_name, bool $use_wrapper = false ): void {
	add_filter(
		'acf/blocks/wrap_frontend_innerblocks',
		function ( $wrap, $name ) use ( $block_name, $use_wrapper ) {
			if ( $name === $block_name ) {
				return $use_wrapper;
			}

			return $wrap;
		},
		10,
		2
	);
}

/**
 * Import an ACF JSON file for a block.
 *
 * This is kept for older files that use the "acf.json" approach and need manually registering.
 *
 * @param string $block_dir Path to the block directory.
 *
 * @since 1.0.0
 *
 * @internal
 */
function register_theme_block_acf_json_for_non_synced_files( string $block_dir ): void {
	$acf_file  = $block_dir . '/acf';
	$php_file  = $acf_file . '.php';
	$json_file = $acf_file . '.json';

	if ( file_exists( $json_file ) ) {
		$file_content = file_get_contents( $json_file ); // phpcs:ignore -- Not a remote file.
		$field_groups = json_decode( $file_content, true );

		if ( ! $field_groups ) {
			return;
		}

		$has_string_keys = count( array_filter( array_keys( $field_groups ), 'is_string' ) ) > 0;

		if ( $has_string_keys ) {
			$field_groups = array( $field_groups );
		}

		foreach ( $field_groups as $field_group ) {
			if ( function_exists( 'acf_add_local_field_group' ) ) {
				/**
				 * We check for the function first.
				 *
				 * @disregard P1010
				 */
				acf_add_local_field_group( $field_group );
			} else {
				add_action(
					'acf/include_fields',
					function() use ( $field_group ) {
						/**
						 * Only runs if the plugin is installed.
						 *
						 * @disregard P1010
						 */
						acf_add_local_field_group( $field_group );
					}
				);
			}
		}
	} elseif ( file_exists( $php_file ) ) {
		// Allow PHP export files to be used instead.
		include $php_file;
	}
}
