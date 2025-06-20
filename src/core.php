<?php
/**
 * Functionality for registering blocks.
 *
 * @package wrd\wp-blocks
 *
 * @since 1.0.0
 */

namespace wrd\wp_blocks\core;

use WP_Block_Type_Registry;

use function wrd\wp_blocks\templating\block_is_editor;

/**
 * Returns an array of all the theme's block directories.
 *
 * @return string[] The block dirs.
 *
 * @since 1.0.0
 */
function get_all_theme_block_dirs(): array {
	$dir = apply_filters( 'wrd/wp-blocks/get_theme_blocks_dir', get_template_directory() . '/blocks' ); // phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores -- Namespaced hook.

	$subdirs    = array_filter( glob( "$dir/*" ), 'is_dir' );
	$block_dirs = array();

	foreach ( $subdirs as $block_dir ) {
		if ( ! file_exists( $block_dir . '/block.json' ) ) {
			continue;
		}

		$block_dirs[] = $block_dir;
	}

	$block_dirs = apply_filters( 'wrd/wp-blocks/get_all_theme_block_dirs', $block_dirs ); // phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores -- Namespaced hook.

	return $block_dirs;
}

/**
 * Get the names of every block registred by the theme.
 *
 * @return string[]
 *
 * @since 1.0.0
 */
function get_all_theme_block_names(): array {
	$dirs        = get_all_theme_block_dirs();
	$block_names = array();

	foreach ( $dirs as $block_dir ) {
		$block_json_file = file_get_contents( $block_dir . '/block.json' ); // phpcs:ignore -- Not a remote file.
		$block_json      = json_decode( $block_json_file );

		if ( $block_json && property_exists( $block_json, 'name' ) ) {
			$block_names[] = $block_json->name;
		}
	}

	return $block_names;
}

/**
 * Import an ACF JSON file for a block.
 *
 * @param string $acf_file Path to the file, without extension.
 *
 * @since 1.0.0
 */
function import_theme_block_acf( string $acf_file ): void {
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

		if ( function_exists( 'acf_add_local_field_group' ) ) {
			foreach ( $field_groups as $field_group ) {
				/**
				 * We check for the function first.
				 *
				 * @disregard P1010
				 */
				acf_add_local_field_group( $field_group );
			}
		} else {
			add_action(
				'acf/include_fields',
				function() use ( $field_groups ) {
					foreach ( $field_groups as $field_group ) {
						/**
						 * Only runs if the plugin is installed.
						 *
						 * @disregard P1010
						 */
						acf_add_local_field_group( $field_group );
					}
				}
			);
		}
	}

	if ( file_exists( $php_file ) ) {
		include $php_file;
	}
}

/**
 * Register an individual theme block.
 *
 * @param string $block_dir The directory of the block.
 *
 * @return \WP_Block_Type|false
 *
 * @since 1.0.0
 */
function register_theme_block( string $block_dir ): \WP_Block_Type|false {
	$acf_file = $block_dir . '/acf';
	import_theme_block_acf( $acf_file );

	return register_block_type( $block_dir );
}

/**
 * Registers all the blocks the themes block directory.
 *
 * @return void
 *
 * @since 1.0.0
 */
function register_all_theme_blocks(): void {
	if ( did_action( 'init' ) ) {
		foreach ( get_all_theme_block_dirs() as $block_dir ) {
			register_theme_block( $block_dir );
		}
	} else {
		add_action(
			'init',
			function() : void {
				foreach ( get_all_theme_block_dirs() as $block_dir ) {
					register_theme_block( $block_dir );
				}
			}
		);
	}
}

/**
 * Prints the styles for blocks before they are rendered and only when they are used.
 *
 * Combined with 'should_load_separate_core_block_assets' this reduces the unused CSS and prevents the FOUC the filter causes.
 *
 * Should only be called once.
 *
 * @return void
 *
 * @since 1.0.0
 */
function set_include_theme_block_styles_before_render(): void {
	add_filter( 'render_block', __NAMESPACE__ . '\\_include_theme_block_styles_before_render', 10, 2 );
	add_filter( 'should_load_separate_core_block_assets', '__return_true' );
}

/**
 * Internal function for printing block assets.
 *
 * @param string $html The HTML of the block.
 *
 * @param array  $block The block being rendered.
 *
 * @return string The unaltered HTML.
 */
function _include_theme_block_styles_before_render( $html, $block ) {
	if ( wp_doing_ajax() || defined( 'REST_REQUEST' ) || block_is_editor() ) {
		return $html;
	}

	if ( isset( $block['blockName'] ) && ! str_starts_with( $block['blockName'], 'core' ) ) {
		$registry   = WP_Block_Type_Registry::get_instance();
		$block_type = $registry->get_registered( $block['blockName'] );

		if ( ! $block_type ) {
			return $html;
		}

		if ( property_exists( $block_type, 'style_handles' ) ) {
			wp_print_styles( $block_type->style_handles );
		}

		if ( property_exists( $block_type, 'view_style_handles' ) ) {
			wp_print_styles( $block_type->view_style_handles );
		}
	}

	return $html;
}

/**
 * Filters the allowed blocks for the editor.
 *
 * @param array  $block_types The block whitelist.
 *
 * @param string $post_type The post type to filter. Optional.
 *
 * @return void
 *
 * @since 1.0.0
 */
function set_allowed_block_types( array $block_types, ?string $post_type = null ): void {
	$priority = $post_type ? '20' : '10';

	add_filter(
		'allowed_block_types_all',
		function( $allowed_blocks, $context ) use ( $block_types, $post_type ) {
			if ( ! isset( $context->post ) ) {
				return $allowed_blocks;
			}

			if ( $post_type && $context->post->post_type !== $post_type ) {
				return $allowed_blocks;
			}

			return $block_types;
		},
		$priority,
		2
	);
}

/**
 * Set whether the block editor should be used imperatively.
 *
 * @param bool    $use_editor Whether the block editor should be used.
 *
 * @param ?string $post_type The post type this declaration applies to. Default to null, meaning all post types.
 *
 * @return void
 *
 * @since 1.0.0
 */
function set_use_block_editor( bool $use_editor, ?string $post_type = null ): void {
	$priority = $post_type ? '20' : '10';

	add_filter(
		'use_block_editor_for_post_type',
		function( $allow, $post_type_to_check ) use ( $post_type, $use_editor ) {
			if ( $post_type_to_check === $post_type ) {
				return $use_editor;
			}
			return $allow;
		},
		$priority,
		2
	);
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
 * Registers a new block category imperatively.
 *
 * @param string $slug The category slug.
 *
 * @param string $title The category label.
 *
 * @return void;
 *
 * @since 1.0.0
 */
function register_block_category( string $slug, string $title ):void {
	add_filter(
		'block_categories_all',
		function ( $categories ) use ( $slug, $title ) {
			array_push(
				$categories,
				array(
					'slug'  => $slug,
					'title' => $title,
				)
			);

			return $categories;
		}
	);
}

/**
 * Unregister a style from an existing block.
 *
 * This is different from WordPress core's function, which only targets styles registed via PHP.
 *
 * @param string $block The block slug.
 *
 * @param string $slug The slug of the style.
 *
 * @return void
 *
 * @since 1.2.1
 */
function unregister_js_block_style( string $block, string $slug ): void {
	add_action(
		'enqueue_block_editor_assets',
		function () use ( $block, $slug ) {
			printf(
				"<script id='unregister-block-style-%s' defer>
						window.addEventListener('DOMContentLoaded', () => {
							const { unregisterBlockStyle } = wp.blocks;

							unregisterBlockStyle('%s', '%s');
						})
					</script>",
				esc_attr( $slug ),
				esc_attr( $block ),
				esc_attr( $slug ),
			);
		}
	);
}

/**
 * Toggle whether to dequeue the core block styles.
 *
 * @param bool $use_core_styles Default false.
 *
 * @return void
 *
 * @since 1.0.0
 */
function set_use_core_styles( bool $use_core_styles = false ): void {
	if ( ! $use_core_styles ) {
		add_action( 'wp_footer', __NAMESPACE__ . '\\_set_use_core_styles' );
		add_action( 'wp_print_styles', __NAMESPACE__ . '\\_set_use_core_styles', 100 );
	} else {
		remove_action( 'wp_footer', __NAMESPACE__ . '\\_set_use_core_styles' );
		remove_action( 'wp_print_styles', __NAMESPACE__ . '\\_set_use_core_styles', 100 );
	}
}

/**
 * Internal function for dequeuing several core block styles.
 *
 * @return void
 *
 * @since 1.0.0
 */
function _set_use_core_styles() {
	wp_dequeue_style( 'classic-theme-styles' );
	wp_dequeue_style( 'wp-block-library' );
	wp_dequeue_style( 'wp-block-library-theme' );
	wp_dequeue_style( 'global-styles' );

	wp_dequeue_style( 'core-block-supports' );
	wp_dequeue_style( 'wp-block-heading' );
	wp_dequeue_style( 'wp-block-paragraph' );
	wp_dequeue_style( 'wp-block-list' );
	wp_dequeue_style( 'wp-block-list-item' );
	wp_dequeue_style( 'wp-block-button' );
	wp_dequeue_style( 'wp-block-separator' );
	wp_dequeue_style( 'wp-block-table' );
}

/**
 * Remove one of the core editors CSS rules, to prevent it colliding with your own.
 *
 * Rules are removed from 'wp-edit-post-css'.
 *
 * @param string $selector The selector of the rule to remove.
 *
 * @return void
 *
 * @since 1.3.0
 */
function remove_core_editor_rule( string $selector ) {
	add_action(
		'enqueue_block_editor_assets',
		function () use ( $selector ) {
			printf(
				"<script id='remove_core_editor_rule-%s' defer>
					window.addEventListener('DOMContentLoaded', () => {
						const wpEditPostSheet = document.getElementById('wp-edit-post-css').sheet;
						const badRuleSelector = '%s';

						const badRuleIndex = [...wpEditPostSheet.cssRules].findIndex((rule) => {
							if (rule instanceof CSSStyleRule) {
								return rule.selectorText === badRuleSelector;
							}

							return false;
						});

						wpEditPostSheet.deleteRule(badRuleIndex);
					});
				</script>",
				esc_attr( sanitize_title( $selector ) ),
				esc_attr( $selector ),
			);
		}
	);
}
