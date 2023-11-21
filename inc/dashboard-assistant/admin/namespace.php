<?php

namespace AI\Dashboard_Assistant\Admin;

function bootstrap() : void {
	add_action( 'admin_print_scripts-dashboard_page_ai-assistant', enqueue_admin_script( ... ) );
	add_action( 'admin_menu', add_plugin_menu( ... ) );
}

function enqueue_admin_script() : void {
	$my_assistant = require __DIR__ . '/build/index.tsx.asset.php';
	wp_enqueue_script( 'my-assistant-js', plugin_dir_url( __FILE__ ) . '/build/index.tsx.js', $my_assistant['dependencies'], $my_assistant['version'], true );
	wp_enqueue_style( 'ai-dashboard-assistant-tailwind', plugin_dir_url( __FILE__ ) . '/build/tailwind.css' );
	wp_localize_script( 'my-assistant-js', 'dashboardAssistant', [
		'api' => [
			'root' => rest_url(),
			'nonce' => wp_create_nonce( 'wp_rest' ),
		],
	] );
}

function add_plugin_menu() : void {
	add_submenu_page(
		'index.php',
		'My Assistant',
		'My Assistant',
		'manage_options',
		'ai-assistant',
		render_ai_assistant_page( ... ),
	);
}

function render_ai_assistant_page() : void {
	?>
	<div id="my-assistant-wrapper" class="tailwind" style="height: calc(100vh - 32px); display: flex; margin-left: -20px; background: white;"></div>
	<style>
		#wpfooter {
			display: none;
		}
		#wpbody-content {
			padding-bottom: 0;;
		}
	</style>
	<?php
}
