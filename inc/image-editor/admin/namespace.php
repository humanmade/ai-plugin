<?php

namespace AI\Image_Editor\Admin;

function bootstrap() : void {
	add_action( 'admin_enqueue_scripts', enqueue_admin_script(...) );
}

function enqueue_admin_script() : void {
	$my_assistant = require __DIR__ . '/build/index.tsx.asset.php';
	wp_enqueue_script( 'ai-editor-js', plugin_dir_url( __FILE__ ) . '/build/index.tsx.js', $my_assistant['dependencies'], $my_assistant['version'], true );
	wp_enqueue_style( 'ai-editor-css', plugin_dir_url( __FILE__ ) . '/build/index.tsx.css', [], $my_assistant['version'] );
	wp_localize_script( 'ai-editor-js', 'aiEditor', [
		'api' => [
			'root' => rest_url(),
			'nonce' => wp_create_nonce( 'wp_rest' ),
		],
	] );
}
