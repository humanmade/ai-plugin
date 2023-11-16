<?php

namespace AI\OpenAI;

use Exception;
use IteratorAggregate;
use OpenAI;

class HTTP_Client implements Client {
	protected static $instance;
	protected $api_key;
	protected $base_url = 'https://api.openai.com/v1';
	protected $azure_api_version;

	public function __construct( string $api_key, string $base_url = null, string $azure_api_version = '2023-03-15-preview' ) {
		$this->api_key = $api_key;
		$this->azure_api_version = $azure_api_version;

		if ( $base_url ) {
			$this->base_url = $base_url;
		}
	}

	public static function get_instance() : static {
		if ( isset( static::$instance ) ) {
			return static::$instance;
		}

		$use_azure = get_option( 'microsoft_azure_openai_endpoint' );

		if ( false && $use_azure ) {
			$azure_api_key = get_option( 'microsoft_azure_openai_api_key' );
			$azure_api_version = get_option( 'microsoft_azure_openai_api_version' );
			$azure_api_endpoint = get_option( 'microsoft_azure_openai_endpoint' );
			if ( ! $azure_api_key ) {
				throw new Exception( 'Azure API Key not set' );
			}

			if ( ! $azure_api_endpoint ) {
				throw new Exception( 'Azure API Endpoint not set' );
			}

			if ( ! $azure_api_version ) {
				throw new Exception( 'Azure API Version not set' );
			}

			static::$instance = new static( $azure_api_key, $azure_api_endpoint, $azure_api_version );
		} else {
			$openai_api_key = get_option( 'openai_api_key' );
			if ( ! $openai_api_key ) {
				throw new Exception( 'OpenAI API Key not set' );
			}

			static::$instance = new static( $openai_api_key );
		}

		return static::$instance;
	}

	public function edit(
		string $input,
		string $instruction,
		string $model = "text-davinci-edit-001",
		int $n = 1,
		float $temperature = null,
		float $top_p = null,
	) : Edit {
		$response = $this->request(
			'/edits',
			'POST',
			[
			'model' => $model,
			'input' => $input,
			'instruction' => $instruction,
			'n' => $n,
			'temperature' => $temperature,
			'top_p' => $top_p,
			]
		);

		return Edit::from_data(json_decode(wp_remote_retrieve_body($response)));
	}

	public function chat(
		array $messages,
		string $model = "gpt-3.5-turbo",
		int $n = 1,
		array $functions = null,
		array $function_call = null,
		float $temperature = null,
		float $top_p = null,
		string $stop = null,
		int $max_tokens = null,
		float $presence_penalty = null,
		float $frequency_penalty = null,
		array $logit_bias = null,
		string $user = null
	) : Chat {
		$response = $this->request(
			'/chat/completions',
			'POST',
			array_filter(
				[
				'messages' => $messages,
				'model' => $model,
				'n' => $n,
				'temperature' => $temperature,
				'top_p' => $top_p,
				'stop' => $stop,
				'max_tokens' => $max_tokens,
				'presence_penalty' => $presence_penalty,
				'frequency_penalty' => $frequency_penalty,
				'logit_bias' => $logit_bias,
				'user' => $user,
				'functions' => $functions,
				'function_call' => $function_call,
				]
			)
		);
		return Chat::from_data( json_decode( wp_remote_retrieve_body( $response ) ) );
	}

	public function chat_streamed(
		array $messages,
		string $model = "gpt-3.5-turbo",
		int $n = 1,
		float $temperature = null,
		float $top_p = null,
		string $stop = null,
		int $max_tokens = null,
		float $presence_penalty = null,
		float $frequency_penalty = null,
		array $logit_bias = null,
		string $user = null
	) : IteratorAggregate {
		$client = $this->get_openai_client();
		$stream = $client->chat()->createStreamed(
			array_filter(
				[
				'messages' => $messages,
				'model' => $model,
				'n' => $n,
				'temperature' => $temperature,
				'top_p' => $top_p,
				'stop' => $stop,
				'max_tokens' => $max_tokens,
				'presence_penalty' => $presence_penalty,
				'frequency_penalty' => $frequency_penalty,
				'logit_bias' => $logit_bias,
				'user' => $user
				]
			)
		);
		return new Chat_Stream( $stream );
	}

	protected function get_openai_client() : OpenAI\Client {
		$base_uri = str_replace( 'https://', '', $this->base_url );

		if ( strpos( $this->base_url, 'azure.com' ) ) {
			$client = OpenAI::factory()
				->withApiKey( $this->api_key )
				->withBaseUri( $base_uri )
				->withQueryParam( 'api-version', $this->azure_api_version )
				->withHttpHeader( 'api-key', $this->api_key )
				->make();
		} else {
			$client = OpenAI::client( $this->api_key );
		}
		return $client;
	}

	public function request( string $endpoint, string $method, array $data = [] ) {

		$url = "{$this->base_url}$endpoint";
		$args = [
			'method' => $method,
			'headers' => [
				'Content-Type' => 'application/json',
				'OpenAI-Beta' => 'assistants=v1',
			],
			'timeout' => 60, // phpcs:ignore WordPressVIPMinimum.Performance.RemoteRequestTimeout.timeout_timeout -- Intentionally longer as OpenAI can take a while to respond.
		];

		if ( strpos( $this->base_url, 'azure.com' ) ) {
			$args['headers']['api-key'] = $this->api_key;
			$url = "$url?api-version=$this->azure_api_version";
		} else {
			$args['headers']['Authorization'] = "Bearer $this->api_key";
		}

		if ( $method === 'POST' ) {
			$args['body'] = wp_json_encode( $data );
		} else {
			$url = add_query_arg( $data, $url );
		}

		$response = wp_remote_request( $url, $args );

		if ( is_wp_error( $response ) ) {
			throw new Exception( esc_html( $response->get_error_message() ) );
		}

		if ( wp_remote_retrieve_response_code( $response ) > 299 ) {
			$data = json_decode(wp_remote_retrieve_body($response));
			throw new Exception( esc_html( is_array( $data->error->message ) ? implode( "\n", $data->error->message ) : $data->error->message ) );
		}

		return $response;
	}

	public function create_assisstant(
		string $model,
		string $name = null,
		string $description = null,
		string $instructions = null,
		array $tools = [],
		array $file_ids = [],
	) : Assistant {
		$response = $this->request( '/assistants', 'POST', [
			'model' => $model,
			'name' => $name,
			'description' => $description,
			'instructions' => $instructions,
			'tools' => $tools,
			'file_ids' => $file_ids,

		] );
		return Assistant::from_data( json_decode( wp_remote_retrieve_body( $response ) ) );
	}

	public function update_assisstant(
		string $id,
		string $model,
		string $name = null,
		string $description = null,
		string $instructions = null,
		array $tools = [],
		array $file_ids = [],
	) : Assistant {
		$response = $this->request( sprintf( '/assistants/%s', $id ), 'POST', [
			'model' => $model,
			'name' => $name,
			'description' => $description,
			'instructions' => $instructions,
			'tools' => $tools,
			'file_ids' => $file_ids,
		] );
		return Assistant::from_data( json_decode( wp_remote_retrieve_body( $response ) ) );
	}

	public function get_assistant(
		string $id,
	) : Assistant {
		$response = $this->request( sprintf( '/assistants/%s', $id ), 'GET' );
		return Assistant::from_data( json_decode( wp_remote_retrieve_body( $response ) ) );
	}

	public function create_thread(
		array $messages,
	) : Thread {
		$response = $this->request( '/threads', 'POST' );
		return Thread::from_data( json_decode( wp_remote_retrieve_body( $response ) ) );
	}

	public function create_thread_message(
		Thread_New_Message $message
	) : Thread_Message {
		$response = $this->request( sprintf( '/threads/%s/messages', $message->thread_id ), 'POST', $message->jsonSerialize() );
		return Thread_Message::from_data( json_decode( wp_remote_retrieve_body( $response ) ) );
	}

	/**
	 * Returns a list of messages for a given thread.
	 *
	 * @param string $thread_id
	 * @param integer $limit
	 * @param string $order
	 * @param string|null $after
	 * @param string|null $before
	 * @return Thread_Message[]
	 */
	public function get_thread_messages(
		string $thread_id,
		int $limit = 20,
		string $order = 'desc',
		string $after = null,
		string $before = null,
	) : array {
		$response = $this->request( sprintf( '/threads/%s/messages', $thread_id ), 'GET', [
			'limit' => $limit,
			'order' => $order,
			'after' => $after,
			'before' => $before,
		] );
		$messages = json_decode( wp_remote_retrieve_body( $response ) )->data;
		$messages = array_map( Thread_Message::from_data(...), $messages );
		return $messages;
	}

		/**
	 * @return Thread_Message
	 */
	public function get_thread_message(
		string $thread_id,
		string $message_id,
	) : Thread_Message {
		$response = $this->request( sprintf( '/threads/%s/messages/%s', $thread_id, $message_id ), 'GET' );
		$message = json_decode( wp_remote_retrieve_body( $response ) );
		$message = Thread_Message::from_data( $message );
		return $message;
	}

	/**
	 * Returns a list of messages for a given thread.
	 *
	 * @param string $thread_id
	 */
	public function run_thread(
		string $thread_id,
		string $assistant_id,
		?string $model = null,
		?string $instructions = null,
		?array $tools = null,
	) : Thread_Run {
		$response = $this->request( sprintf( '/threads/%s/runs', $thread_id ), 'POST', [
			'assistant_id' => $assistant_id,
			'model' => $model,
			'instructions' => $instructions,
			'tools' => $tools,
		] );
		$run = Thread_Run::from_data( json_decode( wp_remote_retrieve_body( $response ) ) );
		return $run;
	}

		/**
	 * Retrieve a run
	 *
	 * @param string $run_id
	 */
	public function get_thread_run(
		string $thread_id,
		string $run_id,
	) : Thread_Run {
		$response = $this->request( sprintf( '/threads/%s/runs/%s', $thread_id, $run_id ), 'GET' );
		$run = Thread_Run::from_data( json_decode( wp_remote_retrieve_body( $response ) ) );
		return $run;
	}

	public function list_thread_runs(
		string $thread_id,
	) : array {
		$response = $this->request( sprintf( '/threads/%s/runs', $thread_id ), 'GET' );

		$runs = array_map( Thread_Run::from_data( ... ), json_decode( wp_remote_retrieve_body( $response ) )->data );
		return $runs;
	}

	/**
	 * @return Thread_Run_Step[]
	 */
	public function list_thread_run_steps(
		string $thread_id,
		string $run_id,
		int $limit = 20,
		string $order = 'desc',
		string $after = null,
		string $before = null,
	) : array {
		$response = $this->request( sprintf( '/threads/%s/runs/%s/steps', $thread_id, $run_id ), 'GET', [
			'limit' => $limit,
			'order' => $order,
			'after' => $after,
			'before' => $before,
		] );
		$runs = array_map( Thread_Run_Step::from_data( ... ), json_decode( wp_remote_retrieve_body( $response ) )->data );
		return $runs;
	}

		/**
	 * @param Thread_Run_Tool_Output[] $tools_output
	 *
	 */
	public function submit_tool_outputs(
		string $thread_id,
		string $run_id,
		array $tool_outputs
	) : Thread_Run {
		$response = $this->request( sprintf( '/threads/%s/runs/%s/submit_tool_outputs', $thread_id, $run_id ), 'POST', [
			'tool_outputs' => $tool_outputs,
		] );
		$run = Thread_Run::from_data( json_decode( wp_remote_retrieve_body( $response ) ) );
		return $run;
	}

	public function get_file_contents(
		string $file_id,
	) : array {
		$response = $this->request( sprintf( '/files/%s/content', $file_id ), 'GET' );
		return $response;
	}

	public function get_embeddings(
		string $input,
		string $model = 'text-embedding-ada-002',
		string $encoding_format = 'float',
	) : array {
		$response = $this->request( '/embeddings', 'POST', [
			'input' => $input,
			'model' => $model,
			'encoding_format' => $encoding_format,
		] );
		return array_map( Embedding::from_data( ... ), json_decode( wp_remote_retrieve_body( $response ) )->data );
	}
}
