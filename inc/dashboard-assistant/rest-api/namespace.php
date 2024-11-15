<?php

namespace AI\Dashboard_Assistant\REST_API;

use AI\OpenAI\Message;
use AI\OpenAI;
use AI\Azure_Vision;
use AI\AWS_Rekognition;
use AI\OpenAI\Function_;
use AI\OpenAI\Function_Call;
use AI\OpenAI\Thread;
use AI\OpenAI\Thread_Message;
use AI\OpenAI\Thread_New_Message;
use Exception;
use Iterator;
use OpenAI\Responses\Threads\Runs\ThreadRunStreamResponse;
use Traversable;
use WP_Error;
use WP_REST_Response;
use WP_REST_Request;

function bootstrap() : void {
	add_action( 'rest_api_init', register_rest_routes(...) );
}

function register_rest_routes() : void {
	register_rest_route( 'ai/v1', 'my-assistant', [
		[
			'methods' => 'POST',
			'callback' => my_assistant_post_callback(...),
			'permission_callback' => function () : bool {
				return current_user_can( 'ai_dashboard_assistant' );
			},
			'args' => [
				'content' => [
					'type' => 'string',
					'required' => true,
					'validate_callback' => fn ( $value ) => is_string( $value ) && $value !== '',
				],
				'stream' => [
					'type' => 'boolean',
					'default' => false,
				],
			],
		],
		[
			'methods' => 'GET',
			'callback' => my_assistant_get_callback(...),
			'args' => [
				'stream' => [
					'type' => 'boolean',
					'default' => false,
				],
			],
		],
		[
			'methods' => 'DELETE',
			'callback' => my_assistant_delete_callback(...),
		],
	] );
}

function start_stream() {
	ini_set( 'output_buffering', 'off' ); // @codingStandardsIgnoreLine
	ini_set( 'zlib.output_compression', false ); // @codingStandardsIgnoreLine
	header( 'X-Accel-Buffering: no' );
}

/**
 *
 * @param \AI\OpenAI\Thread_Run_Step[] $stream
 * @return void
 */
function stream_thread_run_steps( Iterator $stream, OpenAI\Client $client ) : void {
	foreach ( $stream as $step ) {
		printf( "id: %s\n", $step->id ); // phpcs:ignore
		echo "event: " . $step->type . "\n"; // phpcs:ignore
		echo 'response: ' . wp_json_encode( $step ) . "\n\n";
		flush();
		wp_ob_end_flush_all();
	}
}

/**
 *
 * @param ThreadRunStreamResponse[] $stream
 * @return void
 */
function stream_thread_run_streamed_steps( Iterator $stream, OpenAI\Client $client ) : void {
	foreach ( $stream as $step ) {
		printf( "id: %s\n", $step->response->id ); // phpcs:ignore
		echo "event: " . $step->event . "\n"; // phpcs:ignore
		echo 'response: ' . wp_json_encode( $step->response ) . "\n\n";
		flush();
		wp_ob_end_flush_all();
	}
}

function stream_thread_messages( array $stream, OpenAI\Client $client ) : void {
	foreach ( $stream as $message ) {
		$message->object = "thread.message";
		printf( "id: %s\n", $message->id ); // phpcs:ignore
		echo "event: thread.message.completed\n"; // phpcs:ignore
		echo 'response: ' . wp_json_encode( $message ) . "\n\n";
		flush();
		wp_ob_end_flush_all();
	}
}

function stream_error( WP_Error $error ) : void {
	static $error_no = 0;
	$error_no++;

	printf( "id: %s\n", $error_no ); // phpcs:ignore
	echo "event: error\n"; // phpcs:ignore
	echo 'response: ' . wp_json_encode( $error ) . "\n\n";
}

function stream_exception( Exception $error ) : void {
	static $error_no = 0;
	$error_no++;

	printf( "id: exception-%s\n", $error_no ); // phpcs:ignore
	echo "event: error\n"; // phpcs:ignore
	echo 'response: ' . wp_json_encode( new WP_Error( $error->getCode() ?: 'unknown-error', $error->getMessage() ) ) . "\n\n";
}

function my_assistant_get_callback( WP_REST_Request $request ) {
	$openai = $openai = OpenAI\Client::get_instance();

	$thread_id = get_user_meta( 1, 'ai_my_assistant_thread_id', true );
	if ( ! $thread_id ) {
		$thread = $openai->create_thread();
		update_user_meta( 1, 'ai_my_assistant_thread_id', $thread->id );
	}

	$thread = new Thread( id: $thread_id );
	$messages = array_reverse( $openai->get_thread_messages( $thread_id, 20, 'desc' ) );

	// If the thread is currently running, resume it.
	if ( $request['stream'] ) {
		start_stream();
		stream_thread_messages( $messages, $openai );
		$resumed_steps_iterator = $thread->resume( $openai );
		if ( $resumed_steps_iterator ) {
			stream_thread_run_steps( $resumed_steps_iterator, $openai );
		}
		exit;
	} else {
		return $messages;
	}
}

function my_assistant_post_callback( WP_REST_Request $request ) {
	$openai = $openai = OpenAI\Client::get_instance();
	$assistant_id = get_option( 'ai_my_assistant_id' );

	if ( ! $assistant_id ) {
		$error = new WP_Error(
			'no-assistant-created',
			'No OpenAI Assistant has been created'
		);
		if ( $request['stream'] ) {
			stream_error( $error );
			exit;
		} else {
			return $error;
		}
	}
	$thread_id = get_user_meta( 1, 'ai_my_assistant_thread_id', true );

	$thread = new Thread( id: $thread_id );

	try {
		$message = $openai->create_thread_message( new Thread_New_Message(
			role: 'user',
			thread_id: $thread->id,
			content: $request['content'],
		) );
	} catch ( Exception $e ) {
		if ( $request['stream'] ) {
			stream_exception( $e );
			exit;
		} else {
			return new WP_Error(
				'no-assistant-created',
				'No OpenAI Assistant has been created'
			);
		}
	}

	if ( $request['stream'] ) {
		start_stream();
		stream_thread_messages( [ $message ], $openai );
		stream_thread_run_streamed_steps( $thread->run( $assistant_id, $openai ), $openai );
		exit;
	} else {
		$messages = [ $message ];
		foreach ( $thread->run( $assistant_id, $openai ) as $message ) {
			$messages[] = $message;
		}
		return $messages;
	}
}

function my_assistant_delete_callback() {
	$openai = $openai = OpenAI\Client::get_instance();
	$thread_id = get_user_meta( 1, 'ai_my_assistant_thread_id', true );
	$openai->delete_thread( $thread_id );
	// Create a new thread for the user, as we always want a thread for the dashboard assistant.
	$thread = $openai->create_thread();
	update_user_meta( 1, 'ai_my_assistant_thread_id', $thread->id );
}
