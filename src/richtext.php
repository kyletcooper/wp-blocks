<?php
/**
 * Functionality for richtext.
 *
 * @package wrd\wp-blocks
 */

namespace wrd\wp_blocks\richtext;

/**
 * Disallow certain richtext formats in the editor.
 *
 * @param string[] $formats The format names to disallowed.
 *
 * @return void
 *
 * @since 1.0.0
 */
function set_richtext_disallow_formats( $formats ) {
	if ( ! is_array( $formats ) ) {
		$formats = array( $formats );
	}

	add_action(
		'enqueue_block_editor_assets',
		function () use ( $formats ) {
			foreach ( $formats as $format ) {
				printf( "<script id='disallow-richtext-%s' defer>window.addEventListener('DOMContentLoaded', () => wp.richText.unregisterFormatType( '%s' ))</script>", esc_attr( $format ), esc_attr( $format ) );
			}
		}
	);
}

/**
 * Register a richtext format via PHP.
 *
 * @param string $slug The name of the format.
 *
 * @param array  $args Arguments for the format. Can include 'title', 'icon', 'tagName' and 'className' keys.
 *
 * @return void
 *
 * @since 1.0.0
 */
function register_theme_richtext_format( $slug, $args = array() ) {
	add_action(
		'enqueue_block_editor_assets',
		function () use ( $slug, $args ) {
			printf(
				"<script id='add-richtext-%s' defer>
						window.addEventListener('DOMContentLoaded', () => {
							const { registerFormatType, toggleFormat } = wp.richText;
							const { RichTextToolbarButton } = wp.blockEditor;
							const { createElement } = wp.element;
							const { title, icon, tagName, className } = JSON.parse('%s');

							const FormatButton = ( { isActive, onChange, value } ) => {
								return createElement( RichTextToolbarButton, {
									icon,
									title,
									isActive,
									onClick: () => {
										onChange(
											toggleFormat( value, {
												type: '%s',
											} )
										);
									},
								});
							}

							registerFormatType( '%s', {
								title,
								icon,
								tagName,
								className,
								edit: FormatButton
							} );
						})
					</script>",
				esc_attr( $slug ),
				wp_json_encode( $args ),
				esc_attr( $slug ),
				esc_attr( $slug ),
			);
		}
	);
}
