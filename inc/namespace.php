<?php

namespace AI;

use WP_CLI;

function bootstrap() : void {
	Admin\bootstrap();
	REST_API\bootstrap();
}
