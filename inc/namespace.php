<?php

namespace AI;

use WP_CLI;

function bootstrap() : void {
	ini_set( 'display_errors', 'on' );
	if ( defined( 'WP_CLI' ) && WP_CLI ) {
		require_once __DIR__ . '/class-cli-command.php';
		WP_CLI::add_command( 'ai', __NAMESPACE__ . '\\CLI_Command' );
	}
	Admin\bootstrap();
	REST_API\bootstrap();
}
