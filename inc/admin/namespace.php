<?php

namespace AI\Admin;

function bootstrap() : void {
	add_action( 'init', __NAMESPACE__ . '\\register_blocks' );
	add_action( 'admin_enqueue_scripts', __NAMESPACE__ . '\\enqueue_admin_script' );
	add_action( 'admin_menu', __NAMESPACE__ . '\\add_plugin_menu' );
	add_action( 'admin_init', __NAMESPACE__ . '\\register_settings_fields' );
	add_action( 'init', __NAMESPACE__ . '\\register_settings' );
	add_action( 'wp_ajax_image-editor', __NAMESPACE__ . '\\change_attachment_mimetype_on_background_removal', 0 );
}

function register_blocks() : void {
	register_block_type( __DIR__ . '/blocks/ai' );
	register_block_type( __DIR__ . '/blocks/chart' );
}

function enqueue_admin_script() : void {
	// wp_enqueue_script( 'alt-text-js', plugin_dir_url( __FILE__ ) . '/src/alt-text.js', [ 'jquery', 'wp-api-fetch' ], null, true );

	// $upscale = include __DIR__ . '/build/upscale.asset.php';
	// wp_enqueue_script( 'upscale-js', plugin_dir_url( __FILE__ ) . '/build/upscale.js', $upscale['dependencies'], $upscale['version'], true );

	$my_assistant = include __DIR__ . '/build/my-assistant.asset.php';
	wp_enqueue_script( 'my-assistant-js', plugin_dir_url( __FILE__ ) . '/build/my-assistant.js', $my_assistant['dependencies'], $my_assistant['version'], true );

	wp_localize_script(
		'ai-insert-editor-script',
		'AIBlock',
		[
			'nonce' => wp_create_nonce( 'wp_rest' ),
		]
	);

	wp_enqueue_style( 'ai-tailwind', plugin_dir_url( __FILE__ ) . '/build/tailwind.css' );
}

function add_plugin_menu() : void {
	add_submenu_page(
		'options-general.php',
		'AI Plugin Settings',
		'AI Plugin',
		'manage_options',
		'ai-plugin-settings',
		__NAMESPACE__ . '\\render_plugin_settings_page'
	);

	add_submenu_page(
		'index.php',
		'My Assistant',
		'My Assistant',
		'manage_options',
		'ai-assistant',
		__NAMESPACE__ . '\\render_ai_assistant_page'
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

function render_plugin_settings_page() : void {
	?>
	<div class="wrap">
		<h1><?php _e( 'AI Plugin Settings' ) ?></h1>
		<form method="post" action="options.php">
			<?php
			settings_fields( 'ai-plugin' );
			do_settings_sections( 'ai-plugin' );
			submit_button( 'Save Settings' );
			?>
		</form>
	</div>
	<?php
}

function register_settings() : void {
	register_setting( 'ai-plugin', 'openai_api_key', 'sanitize_text_field' );
	register_setting( 'ai-plugin', 'microsoft_azure_vision_api_key', 'sanitize_text_field' );
	register_setting( 'ai-plugin', 'microsoft_azure_vision_endpoint', 'sanitize_text_field' );
	register_setting( 'ai-plugin', 'microsoft_azure_vision_api_version', 'sanitize_text_field' );
	register_setting( 'ai-plugin', 'microsoft_azure_openai_endpoint', 'sanitize_text_field' );
	register_setting( 'ai-plugin', 'microsoft_azure_openai_api_key', 'sanitize_text_field' );
	register_setting( 'ai-plugin', 'microsoft_azure_openai_api_version', 'sanitize_text_field' );
	register_setting( 'ai-plugin', 'aws_rekognition_api_key', 'sanitize_text_field' );
	register_setting( 'ai-plugin', 'aws_rekognition_api_secret', 'sanitize_text_field' );
	register_setting( 'ai-plugin', 'aws_rekognition_region', 'sanitize_text_field' );
	register_setting( 'ai-plugin', 'segmind_api_key', 'sanitize_text_field' );
}

function register_settings_fields() : void {
	add_settings_section(
		'open-ai',
		'OpenAI',
		null,
		'ai-plugin',
	);

	add_settings_section(
		'microsoft-azure',
		'Microsoft Azure',
		null,
		'ai-plugin',
	);

	add_settings_section(
		'aws-rekognition',
		'AWS Rekognition',
		null,
		'ai-plugin',
	);

	add_settings_section(
		'segmind',
		'Segmind',
		null,
		'ai-plugin',
	);

	add_settings_field(
		'openai_api_key',
		'API Key',
		function () {
			$value = get_option( 'openai_api_key' );
			?>
			<input type="text" class="regular-text" name="openai_api_key" value="<?php echo esc_attr( $value ); ?>" />
			<p class="description">Enter your OpenAI API key here. The API Key should support GPT-4.</p>
			<?php
		},
		'ai-plugin',
		'open-ai',
	);

	add_settings_field(
		'microsoft_azure_vision_api_key',
		'Vision API Key',
		function () {
			$value = get_option( 'microsoft_azure_vision_api_key' );
			?>
			<input type="text" class="regular-text" name="microsoft_azure_vision_api_key" value="<?php echo esc_attr( $value ); ?>" />
			<p class="description">Enter your Microsoft Azure Vision Key here.</p>
			<?php
		},
		'ai-plugin',
		'microsoft-azure',
	);

	add_settings_field(
		'microsoft_azure_vision_endpoint',
		'Vision API Endpoint',
		function () {
			$value = get_option( 'microsoft_azure_vision_endpoint' );
			?>
			<input type="text" class="regular-text" name="microsoft_azure_vision_endpoint" value="<?php echo esc_attr( $value ); ?>" />
			<p class="description">E.g. <code>https://my-config.cognitiveservices.azure.com/computervision</code>.</p>
			<?php
		},
		'ai-plugin',
		'microsoft-azure',
	);

	add_settings_field(
		'microsoft_azure_vision_api_version',
		'Vision API Version',
		function () {
			$value = get_option( 'microsoft_azure_vision_api_version' );
			?>
			<input type="text" name="microsoft_azure_vision_api_version" value="<?php echo esc_attr( $value ); ?>" />
			<p class="description">E.g. <code>2023-02-01-preview</code>.</p>
			<?php
		},
		'ai-plugin',
		'microsoft-azure',
	);

	add_settings_field(
		'microsoft_azure_openai_endpoint',
		'OpenAI API Endpoint',
		function () {
			$value = get_option( 'microsoft_azure_openai_endpoint' );
			?>
			<input type="text" class="regular-text" name="microsoft_azure_openai_endpoint" value="<?php echo esc_attr( $value ); ?>" />
			<p class="description">To use OpenAI via Microsoft Azure instead of OpenAI directly, enter your Azure OpenAI endpoint. E.g. <code>https://project-name.openai.azure.com/openai/deployments/production</code>.</p>
			<?php
		},
		'ai-plugin',
		'microsoft-azure',
	);

	add_settings_field(
		'microsoft_azure_openai_api_key',
		'OpenAI API Key',
		function () {
			$value = get_option( 'microsoft_azure_openai_api_key' );
			?>
			<input type="text" class="regular-text" name="microsoft_azure_openai_api_key" value="<?php echo esc_attr( $value ); ?>" />
			<p class="description">Enter your Microsoft Azure OpenAI Key here.</p>
			<?php
		},
		'ai-plugin',
		'microsoft-azure',
	);

	add_settings_field(
		'microsoft_azure_openai_api_version',
		'OpenAI API Version',
		function () {
			$value = get_option( 'microsoft_azure_openai_api_version' );
			?>
			<input type="text" name="microsoft_azure_openai_api_version" value="<?php echo esc_attr( $value ); ?>" />
			<p class="description">E.g. <code>2023-03-15-preview</code>.</p>
			<?php
		},
		'ai-plugin',
		'microsoft-azure',
	);

	add_settings_field(
		'aws_rekognition_api_key',
		'AWS API Key',
		function () {
			$value = get_option( 'aws_rekognition_api_key' );
			?>
			<input type="text" class="regular-text" name="aws_rekognition_api_key" value="<?php echo esc_attr( $value ); ?>" />
			<p class="description">E.g. <code>AKIAIOSFODNN7EXAMPLE</code>.</p>
			<?php
		},
		'ai-plugin',
		'aws-rekognition',
	);

	add_settings_field(
		'aws_rekognition_api_secret',
		'AWS API Secret',
		function () {
			$value = get_option( 'aws_rekognition_api_secret' );
			?>
			<input type="text" class="regular-text" name="aws_rekognition_api_secret" value="<?php echo esc_attr( $value ); ?>" />
			<p class="description">E.g. <code>wJalrXUtnFEMI/K7MDENG/bPxRfiCYEXAMPLEKEY</code>.</p>
			<?php
		},
		'ai-plugin',
		'aws-rekognition',
	);

	add_settings_field(
		'aws_rekognition_region',
		'AWS Region',
		function () {
			$value = get_option( 'aws_rekognition_region' );
			?>
			<input type="text" name="aws_rekognition_region" value="<?php echo esc_attr( $value ); ?>" />
			<p class="description">E.g. <code>us-east-1</code>.</p>
			<?php
		},
		'ai-plugin',
		'aws-rekognition',
	);

	add_settings_field(
		'segmind_api_key',
		'Segmind API Key',
		function () {
			$value = get_option( 'segmind_api_key' );
			?>
			<input type="text" name="segmind_api_key" value="<?php echo esc_attr( $value ); ?>" />
			<p class="description">E.g. <code>SG_1234567890qwerty</code>.</p>
			<?php
		},
		'ai-plugin',
		'segmind',
	);
}

function change_attachment_mimetype_on_background_removal() {
	if ( ! isset( $_REQUEST['remove_background'] ) ) {
		return;
	}

	$post = get_post( (int) $_REQUEST['postid'] );
	$mime_types_with_transparency = [
		'image/png',
		'image/webp',
		'image/gif',
	];

	if ( in_array( $post->post_mime_type, $mime_types_with_transparency, true ) ) {
		return;
	}

	// Convert the image to PNG.
	$file = get_attached_file( $post->ID );
	$editor = wp_get_image_editor( $file );
	if ( is_wp_error( $editor ) ) {
		return;
	}

	$new_file = substr( $file, 0, - strlen( pathinfo( $file, PATHINFO_EXTENSION ) ) ) . 'png';
	$editor->save( $new_file );

	update_attached_file( $post->ID, $new_file );
	wp_update_post( [
		'ID' => $post->ID,
		'post_mime_type' => 'image/png',
	] );
}
