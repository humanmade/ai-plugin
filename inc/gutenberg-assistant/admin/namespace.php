<?php

namespace AI\Gutenberg_Assistant\Admin;

function bootstrap() : void {
	add_action( 'init', register_blocks( ... ) );
}

function register_blocks() : void {
	register_block_type( __DIR__ . '/blocks/ai' );
}

function enqueue_admin_script() : void {
	wp_localize_script(
		'ai-insert-editor-script',
		'AIBlock',
		[
			'nonce' => wp_create_nonce( 'wp_rest' ),
		]
	);

	wp_enqueue_style( 'ai-tailwind', plugin_dir_url( __FILE__ ) . '/build/tailwind.css' );
}
