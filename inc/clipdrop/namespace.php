<?php

namespace AI\Clipdrop;

use WP_CLI;

function bootstrap() : void {
	if ( defined( 'WP_CLI' ) && WP_CLI ) {
		require_once __DIR__ . '/class-cli-command.php';
		WP_CLI::add_command( 'ai clipdrop', CLI_Command::class );
	}
}
