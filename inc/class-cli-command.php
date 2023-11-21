<?php

namespace AI;

use AI\OpenAI\Assistant;
use AI\OpenAI\Function_Call;
use AI\OpenAI\Thread;
use AI\OpenAI\Thread_New_Message;
use AI\OpenAI\Thread_Message;
use AI\OpenAI\Thread_Run_Step;
use stdClass;
use WP_CLI;

use function cli\prompt;

class CLI_Command {
	/**
	 * Update a post's content based off a prompt.
	 *
	 * ## OPTIONS
	 *
	 * <id>
	 * : The ID of the post.
	 *
	 * [<prompt>]
	 * : What to do with the post.
	 */
	public function edit( array $args, array $args_assoc ) : void {
		$id = $args[0];
		$prompt = $args[1] ?? null;
		$post = get_post( $id );
		$content = wp_strip_all_tags( $post->post_content );
		WP_CLI::line( "Ok, here's the current content: \n\n" );
		WP_CLI::line( $content );
		WP_CLI::line( "\n\nWhat do you want to do with it?\n" );

		if ( ! $prompt ) {
			$prompt = fgets( STDIN );
		}

		$openai = OpenAI\Client::get_instance();
		$edit = $openai->edit(
			input: $post->post_content,
			instruction: $prompt,
		);


		WP_CLI::line("");
		echo $edit->choices[0]->text;
	}

	/**
	 * Run a chat session
	 *
	 * ## OPTIONS
	 *
	 * [--model=<model>]
	 * : The model to use, "gpt-4", "gpt-4-0314", "gpt-4-32k", "gpt-4-32k-0314", "gpt-3.5-turbo", "gpt-3.5-turbo-0301"
	 *
	 * [--system-message[=<system-message>]]
	 * : Provide a system message as a prime to the conversation. E.g. "Speak in french"
	 *
	 * [--stream]
	 * : Whether to stream the output or not.
	 *
	 * [--test-client]
	 * : Whether to use a stubbed test client with data from the "fixtures" directory.
	 */
	public function chat( array $args, array $args_assoc ) : void {
		ini_set( 'display_errors', 1 );

		$openai = OpenAI\Client::get_instance();

		$messages = [];

		if ( ! empty( $args_assoc['system-message'] ) ) {
			if ( $args_assoc['system-message'] === true ) {
				echo "Enter system message > ";
				$args_assoc['system-message'] = fgets( STDIN );
			}
			$messages[] = new OpenAI\Message(
				role: "system",
				content: $args_assoc['system-message'],
			);
		}

		while ( true ) {
			echo "\n> ";
			$prompt = fgets( STDIN );
			$message = new OpenAI\Message(
				role: "user",
				content: $prompt,
			);
			$messages[] = $message;

			if ( empty( $args_assoc['stream'] ) ) {
				$chat = $openai->chat(
					messages: $messages,
					model: $args_assoc['model'] ?? "gpt-3.5-turbo",
				);
				$messages[] = $chat->choices[0]->message;
				echo $chat->choices[0]->message->content . "\n";
			} else {
				$chat_stream = $openai->chat_streamed(
					messages: $messages,
					model: $args_assoc['model'] ?? "gpt-3.5-turbo",
				);

				foreach ( $chat_stream as $chat ) {
					echo $chat->choices[0]->message->content;
				}
				echo "\n";
			}
		}
	}

	/**
	 * Run a chat session
	 *
	 * ## OPTIONS
	 *
	 *
	 * [--test-client]
	 * : Whether to use a stubbed test client with data from the "fixtures" directory.
	 */
	public function prompt( array $args, array $args_assoc ) : void {
		ini_set( 'display_errors', 1 );

		$openai = OpenAI\Client::get_instance();

		$messages = [];

		$messages[] = new OpenAI\Message(
			role: "system",
			content: "You are an assistant that writes WordPress WP CLI shell commands, you know all the WP CLI commands, subcommands and arguments, and respond to queries with only a wp cli command that can be run directly.",
		);

		WP_CLI::line( 'What would you like to do? For example type "show all my posts"' );
		while ( true ) {
			echo "\n> ";
			$prompt = fgets( STDIN );
			$message = new OpenAI\Message(
				role: "user",
				content: $prompt . ". Just give me the CLI command only.",
			);
			$messages[] = $message;

			$chat_stream = $openai->chat_streamed(
				messages: $messages,
				model: 'gpt-4',
			);

			$command = '';
			echo "Command: $ ";
			foreach ( $chat_stream as $chat ) {
				$command .= $chat->choices[0]->message->content;
				echo $chat->choices[0]->message->content;
			}

			if ( strpos( $command, 'wp ' ) !== 0 ) {
				echo "\n\nThat's not a WP CLI command. Try again.\n";
				continue;
			}

			$command = substr( $command, 3 );
			echo "\nRun? [Y/n]";

			$should_run = trim( fgets( STDIN ) );

			if ( $should_run === "Y" || $should_run === "y" || $should_run === "") {
				WP_CLI::runcommand( $command, [
					'exit_error' => false,
				] );
			}
		}
	}

	/**
	 * Upscale an image. Will output the raw image data to stdout.
	 *
	 * ## OPTIONS
	 *
	 * <image-path>
	 * : The path to the image
	 *
	 * <destination-path>
	 * : The path to the output
	 *
	 * [--scale=<scale>]
	 * : Integer to upscale the image by.
	 */
	public function esrgan( $args, $args_assoc ) {
		$segmind = Segmind\Client::get_instance();
		$response = $segmind->esrgan( file_get_contents( $args[0] ), $args_assoc['scale'] );
		file_put_contents( $args[1], $response );
	}

	/**
	 * Run My Assistant
	 *
	 * ## OPTIONS
	 *
	 */
	public function my_assistant( $args, $args_assoc ) {
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

			foreach ( $thread->run( $openai ) as $step ) {
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
	 * Get embeddings for a string
	 *
	 * @subcommand get-embeddings
	 *
	 * ## OPTIONS
	 *
	 * <string>
	 * : The string to get embeddings for
	 *
	 */
	public function get_embeddings( $args ) {
		$openai = OpenAI\Client::get_instance();
		$embeddings = $openai->get_embeddings( $args[0] );
		print_r( $embeddings );
	}

	/**
	 * Create an image generation
	 *
	 * @subcommand create-image-generation
	 *
	 * ## OPTIONS
	 *
	 * <prompt>
	 * : The image generation prompt
	 *
	 * <output-directory>
	 * : The directory to output the images to
	 *
	 * [--model=<model>]
	 * : The model to use for image generation.
	 *
	 */
	public function create_image_generation( $args, $args_assoc ) {
		$args_assoc = wp_parse_args( $args_assoc, [
			'model' => 'dall-e-3',
		] );
		$openai = OpenAI\Client::get_instance();
		$images = $openai->create_image_generation(
			prompt: $args[0],
			model: $args_assoc['model'],
			response_format: 'b64_json',
		);

		foreach ( $images as $number => $image ) {
			file_put_contents( trailingslashit( realpath( $args[1] ) ) . $number . '.png', base64_decode( $image->b64_json ) );
		}

		WP_CLI::success( sprintf( 'Generated %d image(s)', count( $images ) ) );
	}
}
