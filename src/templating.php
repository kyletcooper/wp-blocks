<?php
/**
 * Templating functions for within blocks.
 *
 * @package wrd\wp-blocks;
 */

namespace wrd\wp_blocks\templating;

/**
 * Displays the block's attributes.
 *
 * @param array  $block The block to show attributes for.
 *
 * @param array  $atts Additional attributes to add.
 *
 * @param string $return_format The format to return the value as. Accepts '' for string or ARRAY_N, defaults to string.
 *
 * @return array|string The attributes.
 *
 * @since 1.0.0
 */
function get_block_atts( array $block, array $atts = array(), string $return_format = '' ): string|array {
	if ( ! isset( $atts['class'] ) ) {
		$atts['class'] = '';
	}

	// Always put the block's base class first.
	$atts['class'] = 'wp-block-' . sanitize_title( $block['name'] ) . ' ' . $atts['class'];

	// Support for 'anchor'.
	if ( ! empty( $block['anchor'] ) ) {
		$atts['id'] = $block['anchor'];
	}

	// Support for 'className'.
	if ( ! empty( $block['className'] ) ) {
		$atts['class'] .= ' ' . $block['className'];
	}

	// Support for 'align'.
	if ( ! empty( $block['align'] ) ) {
		$atts['class'] .= ' align' . $block['align'];
	}

	// Support for 'alignContent'.
	if ( ! empty( $block['alignContent'] ) ) {
		if ( 'matrix' === $block['supports']['alignContent'] ) {
			$atts['class'] .= ' has-custom-content-position  is-position-' . str_replace( ' ', '-', $block['alignContent'] );
		} else {
			$atts['class'] .= ' is-vertically-aligned-' . $block['alignContent'];
		}
	}

	// Support for 'fullHeight'.
	if ( ! empty( $block['fullHeight'] ) ) {
		$atts['class'] .= ' is-full-height';
	}

	// Support for 'colors.background'.
	if ( ! empty( $block['backgroundColor'] ) ) {
		$atts['class'] .= ' has-background';
		$atts['class'] .= ' has-' . $block['backgroundColor'] . '-background-color';
	}

	// Support for 'colors.text'.
	if ( ! empty( $block['textColor'] ) ) {
		$atts['class'] .= ' has-text-color';
		$atts['class'] .= ' has-' . $block['textColor'] . '-color';
	}

	$styles = array();

	// Support for 'spacing.padding'.
	if ( ! empty( $block['style']['spacing']['padding'] ) ) {
		$styles['padding-top']    = $block['style']['spacing']['padding']['top'];
		$styles['padding-right']  = $block['style']['spacing']['padding']['right'];
		$styles['padding-bottom'] = $block['style']['spacing']['padding']['bottom'];
		$styles['padding-left']   = $block['style']['spacing']['padding']['left'];
	}

	// Support for 'spacing.margin'.
	if ( ! empty( $block['style']['spacing']['margin'] ) ) {
		$styles['margin-top']    = $block['style']['spacing']['margin']['top'];
		$styles['margin-right']  = $block['style']['spacing']['margin']['right'];
		$styles['margin-bottom'] = $block['style']['spacing']['margin']['bottom'];
		$styles['margin-left']   = $block['style']['spacing']['margin']['left'];
	}

	$atts = apply_filters( 'wrd/wp-blocks/get_block_atts', $atts, $block ); // phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores -- Namespaced hook.

	// Remove any styles without values.
	$styles = array_filter( $styles, fn( $v ) => ! empty( $v ) );

	if ( count( $styles ) > 0 ) {
		// Styles are stored as an associative array, so we must flatten them into a string.
		$styles_rules  = array_map( fn( $k, $v ) => "$k = $v", array_keys( $styles ), array_values( $styles ) );
		$styles_string = join( '; ', $styles_rules );

		if ( ! empty( $atts['style'] ) ) {
			$atts['style'] .= '; ' . $styles_string;
		} else {
			$atts['style'] = $styles_string;
		}
	}

	if ( ARRAY_N === $return_format ) {
		return $atts;
	}

	$atts_str = '';

	foreach ( $atts as $attr => $value ) {
		$atts_str .= esc_html( $attr ) . '="' . esc_attr( $value ) . '" ';
	}

	return $atts_str;
}

/**
 * Displays the block's attributes.
 *
 * @param array $block The block to show attributes for.
 *
 * @param array $atts Additional attributes to add.
 *
 * @return void
 *
 * @since 1.0.0
 */
function block_atts( $block, $atts = array() ): void {
	echo get_block_atts( $block, $atts ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Trusted.
}

/**
 * Checks if the block is currently being rendered for the editor.
 *
 * @return bool True if in editor, otherwise false.
 *
 * @since 1.0.0
 */
function block_is_editor(): bool {
	if ( function_exists( 'get_current_screen' ) ) {
		$screen = get_current_screen();
		if ( $screen && method_exists( $screen, 'is_block_editor' ) ) {
			return $screen->is_block_editor();
		}
	}

	return false;
}

/**
 * Checks if the current block render has a specific style.
 *
 * @param array  $block The block to show attributes for.
 *
 * @param string $style The name of the style to look for.
 *
 * @return bool If the style is on this block.
 *
 * @since 1.0.0
 */
function block_has_style( $block, $style ): bool {
	if ( ! isset( $block['className'] ) ) {
		return false;
	}

	return str_contains( $block['className'], "is-style-$style" );
}

/**
 * Gets an absolute path for a file in a block's directory.
 *
 * @param array  $block The block.
 *
 * @param string $path Path inside the directory. Can be relative, is resolved using realpath.
 *
 * @return string The absolute path.
 *
 * @since 1.0.0
 */
function get_block_directory( $block, $path = '' ): string {
	if ( path_is_absolute( $path ) ) {
		return $path;
	}

	return realpath( $block['path'] . DIRECTORY_SEPARATOR . $path );
}

/**
 * Gets the URL path for a block's directory.
 *
 * This function only works for theme blocks registered in the parent theme.
 *
 * @param array $block The block.
 *
 * @return string The block URI.
 *
 * @since 1.0.0
 */
function get_block_directory_uri( $block ): string {
	$theme_slug          = str_replace( '%2F', '/', rawurlencode( get_template() ) );
	$block_path          = $block['path'];
	$block_path_relative = explode( "/$theme_slug/", $block_path )[1];

	return get_template_directory_uri() . '/' . $block_path_relative;
}

/**
 * Get the prose blocks, a set of blocks that are typically allowed as text content.
 *
 * @return string[]
 *
 * @since 1.0.0
 */
function get_prose_blocks(): array {
	return apply_filters( 'wrd/wp-blocks/get_prose_blocks', array( 'core/heading', 'core/list', 'core/list-item', 'core/buttons', 'core/button', 'core/paragraph' ) ); // phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores -- Namespaced hook.
}

/**
 * Display the inner blocks.
 *
 * ACF adds a wrapper for these. You can use wrd\wp_block\set_use_inner_blocks_wrapper to disable this, but only on the front-end.
 *
 * @param string    $classes Classes for the wrapper. Optional, defaults to empty string.
 *
 * @param ?string[] $allowed_blocks List of blocks that can be used inside this inner blocks. Optional, defaults to the prose blocks. Pass an empty array for none.
 *
 * @param ?array    $template Default child blocks. Uses a specific array format, @see https://github.com/WordPress/gutenberg/blob/22c55f658ed349254bd146c15bc4150c59f68d3d/docs/reference-guides/block-api/block-templates.md. Defaults to a H2 and paragraph with placeholder 'lorem ipsum' text.
 *
 * @param ?string   $template_lock Template locking to prevent changing the inner blocks. Optional, default none. @see https://github.com/WordPress/gutenberg/tree/HEAD/packages/block-editor/src/components/inner-blocks/README.md#templatelock.
 *
 * @param string    $orientation Direction the blocks go. Can be either "horizontal" or "veritcal" Optional, defaults to "vertical".
 *
 * @return void
 *
 * @since 1.0.0
 */
function the_inner_blocks( string $classes = '', ?array $allowed_blocks = null, ?array $template = null, ?string $template_lock = null, string $orientation = 'vertical' ): void {
	if ( null === $allowed_blocks ) {
		$allowed_blocks = get_prose_blocks();
	}

	if ( array() === $allowed_blocks ) {
		$allowed_blocks = null;
	}

	if ( null === $template ) {
		$template = array(
			array(
				'core/heading',
				array(
					'level'       => 2,
					'placeholder' => 'Vestibulum aliquet turpis et elementum dapibus',
				),
				array(),
			),
			array(
				'core/paragraph',
				array(
					'placeholder' => 'Lorem ipsum dolor sit amet, consectetur adipiscing elit. Vestibulum aliquet, turpis et elementum dapibus, turpis leo porttitor est, vitae vehicula sem velit et dui. Nunc nunc dui, pellentesque id tincidunt non, vehicula vitae ex. Etiam et arcu sollicitudin, laoreet nisi eget, condimentum nunc. Integer fermentum sem lacinia, mattis est et, fringilla lorem. Donec nec ullamcorper nunc. Fusce non mauris aliquam',
				),
				array(),
			),
		);
	}

	printf( '<InnerBlocks class="%s" template="%s" allowedBlocks="%s" templateLock="%s" orientation="%s" />', esc_attr( $classes ), esc_attr( wp_json_encode( $template ) ), esc_attr( wp_json_encode( $allowed_blocks ) ), esc_attr( $template_lock ), esc_attr( $orientation ) );
}

/**
 * Quickly generate link markup from an ACF link field.
 *
 * The field must be setup to have an 'array' return format.
 *
 * @param string|array $link The link field. Can either pass the value or the name of a field.
 *
 * @param string       $class Classes to add to the link. Optional.
 *
 * @return string
 *
 * @since 1.0.0
 */
function get_the_link( string|array $link, string $class = '' ): string {
	if ( is_string( $link ) && function_exists( 'get_field' ) ) {
		/**
		 * We check for the function first.
		 *
		 * @disregard P1010
		 */
		$link = get_field( $link );
	}

	if ( ! $link ) {
		return '';
	}

	// Automatically add a relation of 'noopener' to external links.
	$attr        = '';
	$parsed_link = wp_parse_url( $link['url'] );
	$parsed_home = wp_parse_url( get_home_url() );

	if ( array_key_exists( 'host', $parsed_link ) && $parsed_link['host'] !== $parsed_home['host'] ) {
		$attr .= " rel='noopener' ";
	}

	$url    = $link['url'];
	$title  = $link['title'];
	$target = $link['target'] ? $link['target'] : '_self';

	return sprintf(
		'<a href="%s" target="%s" class="%s" %s>%s</a>',
		esc_url( $url ),
		esc_attr( $target ),
		esc_attr( $class ),
		$attr,
		esc_html( $title )
	);
}

/**
 * Quickly display link markup from an ACF link field.
 *
 * The field must be setup to have an 'array' return format.
 *
 * @param string|array $link The link field. Can either pass the value or the name of a field.
 *
 * @param string       $class Classes to add to the link. Optional.
 *
 * @return void
 *
 * @since 1.0.0
 */
function the_link( string|array $link, string $class = '' ): void {
	echo get_the_link( $link, $class ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Trusted.
}

/**
 * Get a style attribute for a background image, given an image URL.
 *
 * @param int|string $url URL for the image to use.
 *
 * @param string     $style Additional style values to add. Optional.
 *
 * @return string
 *
 * @since 1.0.0
 */
function get_bg_image_url( string $url, string $style = '' ): string {
	return sprintf( 'style="background-image: url(\'%s\'); %s"', esc_url( $url ), esc_attr( $style ) );
}

/**
 * Output a style attribute for a background image, given an image URL.
 *
 * @param int|string $url URL for the image to use.
 *
 * @param string     $style Additional style values to add. Optional.
 *
 * @return void
 *
 * @since 1.0.0
 */
function the_bg_image_url( string $url, string $style = '' ): void {
	echo get_bg_image_url( $url, $style ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Trusted.
}

/**
 * Get a style attribute for a background image, given an attachment ID.
 *
 * @param int|string $image_id ID for the attachment to use.
 *
 * @param string     $size The image resolution to get. Optional, defaults to 'large'.
 *
 * @param string     $style Additional style values to add. Optional.
 *
 * @return string
 *
 * @since 1.0.0
 */
function get_bg_image( string|int $image_id, string $size = 'large', string $style = '' ) {
	$image_url = wp_get_attachment_image_url( $image_id, $size );

	return get_bg_image_url( $image_url, $style );
}

/**
 * Output a style attribute for a background image, given an attachment ID.
 *
 * @param int|string $image_id ID for the attachment to use.
 *
 * @param string     $size The image resolution to get. Optional, defaults to 'large'.
 *
 * @param string     $style Additional style values to add. Optional.
 *
 * @return void
 *
 * @since 1.0.0
 */
function the_bg_image( string|int $image_id, string $size = 'large', string $style = '' ) {
	echo get_bg_image( $image_id, $size, $style ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Trusted.
}

/**
 * Get the number of children the current block has.
 *
 * $wp_block can sometimes be null in certain contexts. If this happens this function returns 0.
 *
 * @param \WP_Block|null $wp_block The block.
 *
 * @return int
 */
function get_block_child_count( \WP_Block|null $wp_block ): int {
	if ( is_null( $wp_block ) ) {
		return 0;
	}

	return count( $wp_block->parsed_block['innerBlocks'] );
}
