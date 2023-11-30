<?php

namespace AI\Dashboard_Assistant;

use AI\OpenAI;
use AI\OpenAI\Assistant;
use AI\OpenAI\Function_Call;
use AI\OpenAI\Thread;
use AI\OpenAI\Thread_New_Message;
use AI\OpenAI\Thread_Message;
use AI\OpenAI\Thread_Run_Step;
use WP_CLI;

use function cli\prompt;

class CLI_Command {
	/**
	 * Run My Assistant
	 *
	 * ## OPTIONS
	 *
	 */
	public function my_assistant( $args, $args_assoc ) {
		$assistant_id = get_option( 'ai_my_assistant_id' );
		$openai = $openai = OpenAI\Client::get_instance();
		wp_set_current_user( 1 );

		$thread_id = get_user_meta( 1, 'ai_my_assistant_thread_id', true );
		if ( ! $thread_id ) {
			$thread_id = $openai->create_thread([])->id;
			update_user_meta( 1, 'ai_my_assistant_thread_id', $thread_id );
		}

		$thread = new Thread( id: $thread_id );
		$messages = $openai->get_thread_messages( $thread_id );

		foreach ( array_reverse( $messages ) as $message ) {
			$this->display_thread_message( $message );
		}

		// If the thread is currently running, resume it.
		$step_iterator = $thread->resume( $openai );
		if ( $step_iterator ) {
			foreach ( $step_iterator as $step ) {
				$this->display_thread_run_step( $step, $openai );
			}
		}

		while ( true ) {
			$prompt = prompt( '> ' );
			$message = $openai->create_thread_message( new Thread_New_Message(
				role: 'user',
				thread_id: $thread->id,
				content: $prompt,
				) );
			$this->display_thread_message( $message );

			foreach ( $thread->run( $assistant_id, $openai ) as $step ) {
				$this->display_thread_run_step( $step, $openai );
			}
		}
	}

	protected function display_thread_run_step( Thread_Run_Step $step, OpenAI\Client $client ) {
		switch ( $step->type ) {
			case 'message_creation':
				if ( $step->should_wait() ) {

				} else {
					$message = $client->get_thread_message( $step->thread_id, $step->step_details->message_creation->message_id );
					$this->display_thread_message( $message );
				}
				break;
			case 'tool_calls':
				foreach ( $step->step_details->tool_calls as $tool_call ) {
					switch ( $tool_call->type ) {
						case 'function':
							WP_CLI::line( sprintf( 'Calling function %s %s', $tool_call->function->name, $step->status ) );
							break;
						default:
							WP_CLI::warning( sprintf( 'Unknown tool call type %s', $tool_call->type ) );
					}
				}
				break;
			default:
				WP_CLI::warning( sprintf( 'Unknown step type %s', $step->type ) );
		}
	}

	protected function display_thread_message( Thread_Message $message ) {
		foreach ( $message->content as $content ) {
			switch ( $content->type ) {
				case 'text':
					WP_CLI::line( sprintf( '%s: %s', $message->role, $content->text->value ) );
					break;
				case 'image_file':
					WP_CLI::line( sprintf( '%s: An image file', $message->role ) );
					break;
				default:
					WP_CLI::error( sprintf( 'Unknown message type %s', $content->type ) );
			}
		}

		//WP_CLI::line( sprintf( '%s: %s', $message->role, $message->content- ) );
	}

	/**
	 * Call a registered function.
	 *
	 * @subcommand call-registered-function
	 *
	 * ## OPTIONS
	 *
	 * <function-name>
	 * : The registered name of the function.
	 *
	 * <args>
	 * : A JSON encoded list of the arguments to pass to the function.
	 */
	public function call_registered_function( $args, $args_assoc ) {
		$assistant = Assistant::get_by_id( get_option( 'ai_my_assistant_id' ) );
		$function_call = new Function_Call(
			$args[0],
			[ json_decode( $args[1] ) ],
			null,
		);
		$response = $assistant->call_registered_function( $function_call );
		echo json_encode( json_decode( $response->content ), JSON_PRETTY_PRINT );
	}

	/**
	 * List all the registered functions.
	 *
	 * @subcommand list-registered-functions
	 *
	 * ## OPTIONS
	 *
	 * [--format=<format>]
	 * : The list format to use.
	 */
	public function list_registered_functions( $args, $args_assoc ) {
		$args_assoc = array_merge( [
			'format' => 'table',
		], $args_assoc );

		$assistant = Assistant::get_by_id( get_option( 'ai_my_assistant_id' ) );
		$functions = $assistant->registered_functions;

		$items = [];
		foreach ( $functions as $function ) {
			$items[] = [
				'name' => $function->name,
			];
		}

		// List the functions through wp cli
		WP_CLI\Utils\format_items( $args_assoc['format'], $items, [ 'name' ] );
	}
}
