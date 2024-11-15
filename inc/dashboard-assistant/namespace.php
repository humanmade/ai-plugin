<?php

namespace AI\Dashboard_Assistant;

use AI\OpenAI;
use Exception;
use WP_CLI;

function bootstrap() : void {
	Admin\bootstrap();
	REST_API\bootstrap();
	Functions\bootstrap();

	if ( defined( 'WP_CLI' ) && WP_CLI ) {
		require_once __DIR__ . '/class-cli-command.php';
		WP_CLI::add_command( 'ai dashboard-assistant', __NAMESPACE__ . '\\CLI_Command' );
	}

	$assistant_id = get_option( 'ai_my_assistant_id' );

	try {
		if ( ! $assistant_id ) {
			$assistant = create_assisant();
		} else {
			$assistant = OpenAI\Client::get_instance()->get_assistant( get_option( 'ai_my_assistant_id' ) );
		}
	} catch ( Exception $e ) {
		var_dump(	$e );
		return;
	}

	do_action( 'dashboard_assistant_init', $assistant );

	$assistant->register_code_interpreter();

	OpenAI\Assistant::register( $assistant );
}

function create_assisant() : OpenAI\Assistant {
	$assistant = OpenAI\Client::get_instance()->create_assistant(
		model: 'gpt-4o',
		name: 'WordPress Assistant',
		instructions: 'You are an assistant for the WordPress CMS admin interface. Users interactive with you to discuss content, publishing actions and site updates. You should perform actions asked by the user and respond to requests for information by using the available functions to get content. You should use code_interpreter to run functions and code that are not provided by user functions.',
	);

	update_option( 'ai_my_assistant_id', $assistant->id );

	return $assistant;
}
