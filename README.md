# wrd\wp-blocks

Automatically creates blocks from the theme's blocks directory.

---

## Usage

Use `register_all_theme_blocks` to register all blocks in the the themes' block directory. This defaults to `blocks/`.

You can use the `wrd\wp-blocks\get_theme_blocks_dir` filter to change the location of the theme's blocks dir.

---

## Registration

**`register_all_theme_blocks`**

Registers all blocks in the theme's block directory.

**`register_theme_block`**

Registers an individual block. Blocks are defined as a directory with a block.json file. Will also import an Advanced Custom Fields JSON file with the name _acf.json_ if it exists.

**`register_block_category`**

Register a new block category.

**`set_include_theme_block_styles_before_render`**

Force WordPress to include block styles individually, with the block when it is rendered.

**`set_allowed_block_types`**

Whitelists the allowed blocks. Optionally whitelists a post type which is given a higher precedence.

**`set_use_block_editor`**

Enables or disables the block editor. Optionally takes a post type which is given a higher precedence.

**`set_use_inner_blocks_wrapper`**

Enable to disable the wrapper around inner blocks. Only works on the front-end.

**`set_use_core_styles`**

Used to dequeue the core block styles & global styles.

---

## Templating Functions

`block_atts`

Output the block's attributes. You can pass attributes of your own to be merged in.

`block_is_editor`

Checks if the block is currently being rendered for preview in the block editor.

`block_has_style`

Check if the block has a style selected.

`get_block_directory`

Get the absolute filepath for the block's directory, with an optional append.

`get_block_directory_uri`

Get the URL for the block's directory, with an optional append.

`get_prose_blocks`

Prose blocks are a set of blocks that are typically allowed as text content.

`the_inner_blocks`

Display a slot for the user to add child blocks.

`the_bg_image`

Display a style attribute for a background image.

`the_link`

Easily display an ACF link field.
