<?php

/**
 * Plugin Name: AI
 * Plugin Author: Joe Hoyle | Human Made
 */

require_once __DIR__ . '/inc/namespace.php';
require_once __DIR__ . '/inc/admin/namespace.php';
require_once __DIR__ . '/inc/rest-api/namespace.php';
require_once __DIR__ . '/inc/azure-vision/class-http-client.php';
require_once __DIR__ . '/inc/aws-rekognition/namespace.php';
require_once __DIR__ . '/inc/openai/class-client.php';
require_once __DIR__ . '/inc/openai/class-http-client.php';
require_once __DIR__ . '/inc/openai/class-test-client.php';
require_once __DIR__ . '/inc/openai/class-edit.php';
require_once __DIR__ . '/inc/openai/class-function-call.php';
require_once __DIR__ . '/inc/openai/class-function_.php';
require_once __DIR__ . '/inc/openai/class-message.php';
require_once __DIR__ . '/inc/openai/class-chat.php';
require_once __DIR__ . '/inc/openai/class-chat-stream.php';
require_once __DIR__ . '/inc/openai/class-test-chat-stream.php';
require_once __DIR__ . '/inc/openai/class-usage.php';
require_once __DIR__ . '/inc/openai/class-assistant.php';
require_once __DIR__ . '/inc/openai/class-thread.php';
require_once __DIR__ . '/inc/openai/class-thread-message.php';
require_once __DIR__ . '/inc/openai/class-thread-new-message.php';
require_once __DIR__ . '/inc/openai/class-thread-run.php';
require_once __DIR__ . '/inc/openai/class-thread-run-step.php';
require_once __DIR__ . '/inc/openai/class-embedding.php';
require_once __DIR__ . '/inc/segmind/class-client.php';
require_once __DIR__ . '/inc/semantic-search/namespace.php';
require_once __DIR__ . '/inc/semantic-search/class-cli-command.php';

AI\bootstrap();
AI\Semantic_Search\bootstrap();


