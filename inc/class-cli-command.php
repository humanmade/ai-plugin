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
use WP_Error;
use WP_REST_Request;

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

	public function assistant( array $args, array $args_assoc ) {
		ini_set( 'display_errors', 1 );
		wp_set_current_user(1);
		$functions = $this->get_rest_api_functions();

		//echo json_encode($functions, JSON_PRETTY_PRINT);

		// Limit function to 64 length. OpenAI hard limit.
		$functions = array_slice( $functions, 0, 64 );
		// $functions = [ $functions['get_wp_v2_posts'] ];

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

		$handle_response = function ( $chat ) use ( $openai, &$messages, &$functions, &$handle_response ) {
			$message = $chat->choices[0]->message;
			var_dump( $message );
			if ( $message->function_call ) {
				$function = $functions[ $message->function_call->name ] ?? false;
				if ( ! $function ) {
					$result = new WP_Error( 'function-not-found', 'A function by that name was not found.' );
				} else {
					$request = new WP_REST_Request( key( $function['endpoint']['methods'] ), '' );
					$defaults = [];
					foreach ( $function['endpoint']['args'] as $arg => $options ) {
						if ( isset( $options['default'] ) ) {
							$defaults[ $arg ] = $options['default'];
						}
					}

					$request->set_default_params( $defaults );
					$result = [];
					// Change to assoc array.
					$function_calls = json_decode( json_encode( $message->function_call->arguments ), true );
					var_dump( $function_calls );
					foreach ( $function_calls as $params ) {
						$request = clone $request;
						foreach ( $params as $param => $value ) {
							$request->set_param( $param, $value );
						}
						print_r( $data );
						$data = $function['endpoint']['callback']( $request );
						if ( is_wp_error( $data ) ) {
							WP_CLI::warning( 'Function call failed with ' . $data->get_error_message() );
						} else {
							WP_CLI::success( 'Function call succeeded' );
							print_r( $data->get_data() );
						}

						if ( is_wp_error( $data ) ) {
							$result[] = $data;
						} else {
							$result[] = $data->get_data();
						}
					}
				}


				WP_CLI::line( $message->function_call->name );
				var_dump( $result );
				$message = new OpenAI\Message(
					role: "function",
					name: $message->function_call->name,
					content: json_encode( $result ),
				);
				$messages[] = $message;

				$chat = $openai->chat(
					messages: $messages,
					model: $args_assoc['model'] ?? "gpt-3.5-turbo-16k",
					functions: array_values( $functions ),
				);
				$handle_response( $chat );
			} else {
				$messages[] = $message;
				echo $chat->choices[0]->message->content . "\n";
			}

		};

		while ( true ) {
			$message = "what are my posts?";
			$message = "";
			echo "\n> ";
			$prompt = fgets( STDIN );
			$message = new OpenAI\Message(
				role: "user",
				content: $prompt,
			);
			$messages[] = $message;

			$chat = $openai->chat(
				messages: $messages,
				model: $args_assoc['model'] ?? "gpt-3.5-turbo-16k",
				functions: array_values( $functions ),
			);

			$handle_response( $chat );
		}

	}

	public function list_functions() {
		$functions = $this->get_rest_api_functions();
		// $functions = array_slice( $functions, 0, 64 );
		$functions = [ $functions['get_wp_v2_pattern_directory_patterns'] ];
		foreach ( $functions as $function ) {
			unset( $function['endpoint'] );
			print_r( $function );
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

	protected function get_rest_api_functions() {
		$functions = [];
		$routes = rest_get_server()->get_routes();
		foreach ( $routes as $route => $endpoints ) {
			$route_options = rest_get_server()->get_route_options( $route );

			if ( empty( $route_options['schema'] ) ) {
				continue;
			}

			$schema = $route_options['schema']();
			$exclude = [ '/', '/batch/v1' ];
			if ( in_array( $route, $exclude, true ) ) {
				continue;
			}

			if ( strpos( $route, 'revisions' ) ) {
				continue;
			}

			if ( strpos( $route, 'autosaves' ) ) {
				continue;
			}
			if ( strpos( $route, 'application-passwords' ) ) {
				continue;
			}
			if ( strpos( $route, 'templates' ) ) {
				continue;
			}
			if ( strpos( $route, 'template-parts' ) ) {
				continue;
			}
			if ( strpos( $route, 'block' ) ) {
				continue;
			}
			if ( strpos( $route, 'menu' ) ) {
				continue;
			}
			if ( strpos( $route, 'sidebar' ) ) {
				continue;
			}
			if ( strpos( $route, 'widgets' ) ) {
				continue;
			}

			foreach ( $endpoints as $endpoint ) {
				// Skip root namespace routes.
				if ( '/' . ( $endpoint['args']['namespace']['default'] ?? '' ) === $route ) {
					continue 2;
				}
				if ( empty( $endpoint['args'] ) ) {
					continue;
				}

				$name = $this->get_name_for_route_endpoint( $route, $endpoint );
				if ( ! $name ) {
					continue;
				}

				$skip_properties = [ 'context' => true, 'ab_tests' => true ];
				$properties = array_diff_key( $endpoint['args'], $skip_properties );
				$properties = array_map( function ( array $arg ) : array {
					unset( $arg['validate_callback'] );
					unset( $arg['sanitize_callback'] );
					return $arg;
				}, $properties );

				$required = [];
				// More required props to root object, ala json schema should be.
				foreach ( $properties as $property => &$details ) {
					// Make sure the default value matches the type. Else OpenAI will choke on it.
					if ( isset( $details['default'] ) ) {
						switch ( $details['type'] ) {
							case 'array':
								if ( ! is_array( $details['default'] ) ) {
									$details['default'] = [ $details['default'] ];
								}
								break;
						}
					}

					if ( isset( $details['required'] ) ) {
						$required[] = $property;
						unset( $details['required'] );
					}

					if ( $details['type'] === 'int' ) {
						$details['type'] = 'integer';
					}

					// If arrays don't have items set it will choke. Don't add any of those params.
					if ( $details['type'] === 'array' && ! isset( $details['items'] ) ) {
						unset( $properties[ $property ] );
					}

					if ( $details['type'] === 'object' && isset( $details['properties'] ) && $details['properties'] === [] ) {
						$details['properties'] = new stdClass();
					}

					if ( $details['items']['type'] ?? '' === 'int' ) {
						$details['items']['type'] = 'integer';
					}
				}
				$functions[ $name ] = [
					'name' => $name,
					'description' => $name . ' ' . $route . ' ' . implode( ', ', array_keys( $endpoint['methods'] ) ),
					'parameters' => [
						'type' => 'object',
						'properties' => $properties ?: new stdClass(),
						'required' => $required,
					],
					'endpoint' => $endpoint,
				];
			}
		}

		return $functions;
	}

	protected function get_name_for_route_endpoint( string $route, array $endpoint ) : string {
		$verb = array_map( function ( $method ) : string {
			return [
				'POST' => 'create',
				'PUT' => 'update',
				'PATCH' => 'update',
				'GET' => 'get',
				'DELETE' => 'delete',
				'OPTIONS' => '',
			][ $method ];
		}, array_keys( array_filter( $endpoint['methods'] ) ) )[0];
		// we can remove capture blocks as they will already be in args for the endpoint.
		$route = preg_replace( '/\(\?P<([^\)]+)>[^\)]+\)/', 'with_$1', $route );
		// cleanup
		// only allow regex [a-zA-Z0-9_-]{1,64}
		$route = $verb . '_' . trim( str_replace( [ '/', '-', ')' ], '_', $route ), '_' );
		$route = preg_replace( '/[^a-zA-Z0-9_-]/', '', $route );

		return $route;
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
