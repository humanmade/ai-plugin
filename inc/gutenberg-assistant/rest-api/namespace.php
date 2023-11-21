<?php

namespace AI\Gutenberg_Assistant\REST_API;

use AI;
use AI\OpenAI\Message;
use AI\OpenAI;
use Exception;
use WP_Error;
use WP_REST_Response;
use WP_REST_Request;

function bootstrap() : void {
	add_action( 'rest_api_init', register_rest_routes(...) );
}

function register_rest_routes() : void {
	$messages = [
		'type' => 'array',
		'required' => true,
		'items' => [
			'type' => [
				'object'
			],
			'properties' => [
				'role' => [
					'type' => 'string',
					'enum' => [
						'user',
						'assistant'
					],
				],
				'content' => [
					'type' => 'string',
					'required' => true,
				]
			],
		],
	];
	$post = [
		'type' => 'object',
		'properties' => [
			'title' => [
				'type' => 'string',
			],
			'content' => [
				'type' => 'string',
			],
			'type' => [
				'type' => 'string',
			],
		],
	];
	register_rest_route( 'ai/v1', 'insert', [
		[
			'methods' => 'POST',
			'callback' => insert_callback(...),
			'args' => [
				'content' => [
					'type' => 'string',
				],
				'messages' => $messages,
				'post' => $post,
				'available_blocks' => [
					'type' => 'array',
					'items' => [
						'type' => 'string',
					],
				],
				'stream' => [
					'type' => 'boolean',
					'default' => false,
				],
			],
		]
	] );


	register_rest_route( 'ai/v1', 'chat', [
		[
			'methods' => 'POST',
			'callback' => chat_callback( ... ),
			'permission_callback' => function () : bool {
				return is_user_logged_in();
			},
			'args' => [
				'messages' => $messages,
				'post' => $post,
				'stream' => [
					'type' => 'boolean',
					'default' => false,
				],
			],
		]
	] );
}

function insert_callback( WP_REST_Request $request ) : WP_REST_Response|WP_Error {

	$openai = OpenAI\Client::get_instance();
	/**
	 * @var Message[] $messages
	 */
	$messages = [];

	if ( isset( $request['post']['content'] ) ) {
		$content_preamble = 'We are writing a post with the content that is below. The "{{selected_block_placeholder}}" text in the content signifies where the user is currently editing / where the caret position is. \n\n' . $request['post']['content'] . "\n\n";
	} else {
		$content_preamble = '';
	}

	$post_type = $request['post']['type'] ?? 'post';
	$site_name = get_bloginfo( 'name' );

	if ( isset( $request['post']['title'] ) ) {
		$title_preamble = 'with the title "' . $request['post']['title'] . '"';
	} else {
		$title_preamble = '';
	}

	$system_prompt = <<<EOF
	$content_preamble You are an assistant that writes WordPress gutenberg code and says nothing else.
	You only reply in Gutenberg HTML format including HTML comments for WordPress blocks.
	All responses must be valid Gutenberg HTML code. Any formatting should always use HTML
	when I ask you to link things, make them bold, etc. Don't make any remarks about what
	you're doing, just give me the Gutenberg code.

	If you ever generate image blocks, use unsplash image URLs for placeholders and images.

	You are writing a $post_type on the website "$site_name" $title_preamble.

	Remember to output in Gutenberg HTML format including HTML comments for WordPress blocks. Nothing extra.
	EOF;

	$messages[] = new Message(
		role: 'system',
		content: $system_prompt,
	);

	foreach ( $request['messages'] as $message ) {
		$messages[] = new Message(
			role: $message['role'],
			content: $message['content'],
		);
	}

	$messages[] = new Message(
		role: 'system',
		content: 'Remember to output in Gutenberg HTML format including HTML comments for WordPress blocks. Nothing extra.',
	);

	if ( $request['stream'] ) {
		$stream = $openai->chat_streamed(
			messages: $messages,
			temperature: 0,
		);
		AI\REST_API\stream_response( $stream );
		exit;
	}
	try {
		$response = $openai->chat(
			messages: $messages,
			temperature: 0,
			model: 'gpt-4-1106-preview',
		);
	} catch ( Exception $e ) {
		return rest_ensure_response( new WP_Error( 'openai-api-error', $e->getMessage(), [ 'code' => 400 ] ) );
	}

	return rest_ensure_response( $response->choices[0]->message );
}

/**
 * Run a Chat AI call.
 */
function chat_callback( WP_REST_Request $request ) {
	$params = $request->get_params();
	$params['site_title'] = get_bloginfo( 'name' );
	$params['query'] = 'chat';

	$response = get_streaming_client()->post( '/ai', [
		'json' => $params,
		'headers' => [
			'Authorization' => 'Bearer ' . Accelerate\get_altis_dashboard_oauth2_client_id(),
		],
	] );

	if ( $request['stream'] ) {
		$message = stream_response( $response );
		exit;
	}
	$response = $response->getBody()->getContents();
	$response = json_decode( $response );
	return rest_ensure_response( $response->choices[0]->message );
}
