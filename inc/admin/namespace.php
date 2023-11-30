<?php

namespace AI\Admin;

function bootstrap() : void {
	add_action( 'admin_menu', add_plugin_menu( ... ) );
	add_action( 'admin_init', register_settings_fields( ... ) );
	add_action( 'init', register_settings( ... ) );
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

	add_settings_field(
		'clipdrop_api_key',
		'Clipdrop API Key',
		function () {
			$value = get_option( 'clipdrop_api_key' );
			?>
			<input type="text" name="clipdrop_api_key" value="<?php echo esc_attr( $value ); ?>" />
			<p class="description">E.g. <code>p9898jcp98cjp38jdp38jdp8o3jdpo3jdp8jw3pd98j3wpf8j3sp9f8jpdw3upf893wf</code>.</p>
			<?php
		},
		'ai-plugin',
		'segmind',
	);

	add_settings_field(
		'dreamstudio_api_key',
		'DreamStudio API Key',
		function () {
			$value = get_option( 'dreamstudio_api_key' );
			?>
			<input type="text" name="dreamstudio_api_key" value="<?php echo esc_attr( $value ); ?>" />
			<p class="description">E.g. <code>sk-1234567898765432123456789</code>.</p>
			<?php
		},
		'ai-plugin',
		'segmind',
	);
}
