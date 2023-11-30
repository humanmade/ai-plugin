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

function insert_callback( WP_REST_Request $request ) : WP_REST_Response|WP_Error {

	$openai = OpenAI\Client::get_instance();
	$messages = [];

	$system_prompt = 'You are an assistant that writes WordPress gutenberg code and says nothing else. You only reply in Gutenberg HTML format including HTML comments for WordPress blocks. All responses must be valid Gutenberg HTML code. Any formatting should always use HTML when I ask you to link things, make them bold, etc. Don\'t make any remarks about what you\'re doing, just give me the Gutenberg code.';

	if ( $request['content'] ) {
		$system_prompt .= ' You are writing a page that has the content: ' . $request['content'];
	}

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
		stream_response( $stream );
		exit;
	}
	try {
		$response = $openai->chat(
			messages: $messages,
			temperature: 0,
		);
	} catch ( Exception $e ) {
		return rest_ensure_response( new WP_Error( 'openai-api-error', $e->getMessage(), [ 'code' => 400 ] ) );
	}

	return rest_ensure_response( $response->choices[0]->message );
}

function alt_text_callback( WP_REST_Request $request ) : WP_REST_Response|WP_Error {
	$attachment = get_post( $request['attachment_id'] );

	$description = get_the_content( null, false, $attachment );
	$caption = get_the_excerpt( $attachment );
	$filename = pathinfo( get_attached_file( $attachment->ID ), PATHINFO_FILENAME );
	$title = get_the_title( $attachment );
	$azure = Azure_Vision\Client::get_instance();

	$image_data = file_get_contents( get_attached_file( $attachment->ID ) );

	try {
		$caption_response = $azure->analyze(
			features: 'caption',
			image_data: $image_data,
		);
		$azure_caption = $caption_response->captionResult->text;
	} catch ( Exception $e ) {

	}

	try {
		$celebrities = AWS_Rekognition\get_celebrities_from_image_data( $image_data );
	} catch ( Exception $e ) {
		$celebrities = [];
	}

	$celebrities = implode( ', ', $celebrities );

	$openai = OpenAI\Client::get_instance();
	$messages = [
		new Message(
			role: 'system',
			content: 'You are an assistant that writes alt text for images. You only reply in plain text. All responses must be valid alt text. Don\'t make any remarks about what you\'re doing, just give me the alt text. The alt text should be no longer than 140 characters and should be a direct description based off the image data provided.',
		),
		new Message(
			role: 'user',
			content: <<<"END"
				Title: \"{$title}\"
				Long Description: \"{$description}\"
				Caption: \"{$caption}\"
				Filename: \"{$filename}\"
				Azure Caption: \"{$azure_caption}\"
				Celebrity Faces Detected: \"{$celebrities}\"
			END
		),
	];

	try {
		$chat = $openai->chat(
			messages: $messages,
			model: 'gpt-4',
		);
	} catch ( Exception $e ) {
		return rest_ensure_response( new WP_Error( 'openai-api-error', $e->getMessage(), [ 'code' => 400 ] ) );
	}

	$alt_text = trim( $chat->choices[0]->message->content, '"' );
	$response = [
		'alt_text' => $alt_text,
		'fields' => [
			'title' => $title,
			'description' => $description,
			'caption' => $caption,
			'filename' => $filename,
			'azure_caption' => $azure_caption,
			'celebrities' => $celebrities,
		],
	];

	return rest_ensure_response( $response );
}

function chart_callback( WP_REST_Request $request ) : WP_REST_Response|WP_Error {
	$data = $request['data'];
	$prompt = $request['prompt'];

	$openai = OpenAI\Client::get_instance();
	$messages = [
		new Message(
			role: 'system',
			content: 'You are an assistant that creates charts in response to user provided data or prompts. You should parse the data and call the create_chart function with a generated title and most useful interpretation of the data. Create a chart that is most useful to illustrate the trends and concepts that are shown in the data',
		),
	];

	foreach ( $request['messages'] as $message ) {
		if ( isset( $message['function_call'] ) ) {
			$function_call = new Function_Call(
				name: $message['function_call']['name'],
				arguments: json_decode( $message['function_call']['arguments'] ),
			);
		} else {
			$function_call = null;
		}
		$messages[] = new Message(
			role: $message['role'],
			content: $message['content'],
			function_call: $function_call ?? null,
		);
	}
	try {

	} catch ( Exception $e ) {
		return rest_ensure_response( new WP_Error( 'openai-api-error', $e->getMessage(), [ 'code' => 400 ] ) );
	}

	$messages = send_messages( $openai, $messages );
	$message = $messages[ count( $messages ) - 1 ];
	$args = $message->function_call->arguments[0];

	return rest_ensure_response( $args );
}

function send_messages( OpenAI\Client $openai, array $messages ) : array {
	$chart_types = [
		'doughnut',
		'line',
		'bar',
		'scatter',
	];

	$chat = $openai->chat(
		messages: $messages,
		model: 'gpt-4',
		function_call: [
			'name' => 'create_chart',
		],
		functions: [
			new Function_(
				name: 'create_chart',
				description: 'Create a chart data visualization from data',
				parameters: [
					'type' => 'object',
					'properties' => [
						'type' => [
							'type' => 'string',
							'description' => 'The type of chart to create.',
							'enum' => $chart_types,
						],
						'data' => [
							'type' => 'object',
							'properties' => [
								'datasets' => [
									'description' => 'The datasets or data series for the chart.',
									'type' => 'array',
									'items' => [
										'type' => 'object',
										'properties' => [
											'data' => [
												'type' => 'array',
												'items' => [
													'oneOf' => [
														[
															'type' => 'number',
														],
														[
															'type' => 'object',
															'properties' => [
																'x' => [
																	'type' => 'number',
																],
																'y' => [
																	'type' => 'number',
																],
															],
														],
													],
												],
											],
											'label' => [
												'type' => 'string',
												'description' => 'The label for the data set, shown in the legend. Required if using multiple data sets.',
											],
											'type' => [
												'type' => 'string',
												'enum' => $chart_types,
												'description' => 'The type of of the dataset if you want it to be different to the chart type.',
											],
										],
										'required' => [ 'data' ],
									],
								],
								'labels' => [
									'type' => 'array',
									'items' => [
										'type' => 'string',
									],
									'description' => 'The labels for the data sets. Has to contain the same amount of elements as the dataset with the most values',
								],
							],
							'required' => [ 'datasets', 'labels' ],
						],
						'title' => [
							'type' => 'string',
							'description' => 'A title / description for the chart.',
						],
						'credits' => [
							'type' => 'string',
							'description' => 'A credit for the chart data.',
						],
					],
					'required' => [ 'type', 'data', 'title' ],
				]
			),
			new Function_(
				name: 'get_weather_data',
				description: 'Get weather data for a location',
				parameters: [
					'type' => 'object',
					'properties' => [
						'location' => [
							'type' => 'string',
							'description' => 'The location to get weather data for.',
						],
						'end_date' => [
							'type' => 'string',
							'description' => 'The time interval end date to get weather data. A day must be specified as an ISO8601 date (e.g. 2022-12-31). Can not be later than ' . date( 'Y-m-d' ),
						],
						'start_date' => [
							'type' => 'string',
							'description' => 'The time interval start data to get weather data. A day must be specified as an ISO8601 date (e.g. 2022-12-31).',
						],
						'hourly' => [
							'type' => 'string',
							'description' => 'Metric for the data in hourly intervals. Only pass hourly if daily is not passed.',
							'enum' => [
								'temperature_2m',
								'relativehumidity_2m',
								'dewpoint_2m',
								'apparent_temperature',
								'pressure_msl',
								'precipitation',
								'rain',
								'snowfall',
								'cloudcover',
								'cloudcover_low',
								'cloudcover_mid',
								'cloudcover_high',
								'shortwave_radiation',
								'direct_normal_irradiance',
								'diffuse_radiation',
								'windspeed_10m',
								'winddirection_10m',
								'windgusts_10m',
								'et0_fao_evapotranspiration',
								'weathercode',
								'vapor_pressure_deficit',
								'soil_temperature_0_to_7cm',
								'soil_moisture_0_to_7cm',
							],
						],
						'daily' => [
							'type' => 'string',
							'description' => 'Metric for the data in daily intervals. Only pass daily if hourly is not passed.',
							'enum' => [
								'weathercode',
								'temperature_2m_max',
								'temperature_2m_min',
								'apparent_temperature_max',
								'apparent_temperature_min',
								'precipitation_sum',
								'rain_sum',
								'snowfall_sum',
								'precipitation_hours',
								'sunrise',
								'sunset',
								'windspeed_10m_max',
								'windgusts_10m_max',
								'winddirection_10m_dominant',
								'shortwave_radiation_sum',
								'et0_fao_evapotranspiration',
							],
						],
					],
					'required' => [ 'location', 'end_date', 'start_date' ],
				],
			),
		]
	);

	$message = $chat->choices[0]->message;
	$messages[] = $message;

	if ( $message->function_call && $message->function_call->name === 'get_weather_data' ) {
		$message = $message->function_call->respond( function ( object $args ) {
			$location_url = add_query_arg( 'name', $args->location, 'https://geocoding-api.open-meteo.com/v1/search?count=1' );
			$location = json_decode( wp_remote_retrieve_body( wp_remote_get( $location_url ) ) );
			$endpoint_args = [
				'latitude' => $location->results[0]->latitude,
				'longitude' => $location->results[0]->longitude,
				'end_date' => $args->end_date,
				'start_date' => $args->start_date,
			];

			if ( ! empty( $args->hourly ) ) {
				$endpoint_args['hourly'] = $args->hourly;
			} else {
				$endpoint_args['daily'] = $args->daily;
			}

			$url = add_query_arg( $endpoint_args, 'https://archive-api.open-meteo.com/v1/archive' );
			$data = json_decode( wp_remote_retrieve_body( wp_remote_get( $url ) ) );
			return $data;
		} );
		$messages[] = $message;
		$messages = send_messages( $openai, $messages );
	}
	error_log(print_r($messages, true));
	return $messages;
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

function stream_response( Traversable $stream ) : void {
	ini_set( 'output_buffering', 'off' ); // @codingStandardsIgnoreLine
	ini_set( 'zlib.output_compression', false ); // @codingStandardsIgnoreLine
	header( 'X-Accel-Buffering: no' );

	$id = 1;
	foreach ( $stream as $chat ) {
		printf( "id: %d\n", $id ); // phpcs:ignore
		echo "event: chat\n"; // phpcs:ignore
		echo 'data: ' . wp_json_encode( $chat->choices[0]->message ) . "\n\n";
		flush();
		wp_ob_end_flush_all();
		$id++;
	}
}

function start_stream() {
	ini_set( 'output_buffering', 'off' ); // @codingStandardsIgnoreLine
	ini_set( 'zlib.output_compression', false ); // @codingStandardsIgnoreLine
	header( 'X-Accel-Buffering: no' );
}

/**
 *
 * @param Thread_Message[] $stream
 * @return void
 */
function stream_thread_messages( $stream, OpenAI\Client $client ) : void {
	foreach ( $stream as $message ) {
		printf( "id: %s\n", $message->id ); // phpcs:ignore
		echo "event: message\n"; // phpcs:ignore
		echo 'data: ' . wp_json_encode( $message ) . "\n\n";
		flush();
		wp_ob_end_flush_all();
	}
}

/**
 *
 * @param Thread_Run_Step[] $stream
 * @return void
 */
function stream_thread_run_steps( $stream, OpenAI\Client $client ) : void {
	foreach ( $stream as $step ) {
		printf( "id: %s\n", $step->id ); // phpcs:ignore
		echo "event: step\n"; // phpcs:ignore
		echo 'data: ' . wp_json_encode( $step ) . "\n\n";
		flush();
		wp_ob_end_flush_all();
		// Check for message completed steps and get the message.
		if ( $step->step_details->type === 'message_creation' && $step->status === 'completed' ) {
			$message = $client->get_thread_message( $step->thread_id, $step->step_details->message_creation->message_id );
			stream_thread_messages( [ $message ], $client );
		}
	}
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
	$thread_id = get_user_meta( 1, 'ai_my_assistant_thread_id', true );

	$thread = new Thread( id: $thread_id );
	$message = $openai->create_thread_message( new Thread_New_Message(
		role: 'user',
		thread_id: $thread->id,
		content: $request['content'],
	) );

	if ( $request['stream'] ) {
		start_stream();
		stream_thread_messages( [ $message ], $openai );
		stream_thread_run_steps( $thread->run( $assistant_id, $openai ), $openai );
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
