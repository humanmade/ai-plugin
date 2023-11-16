<?php

namespace AI\OpenAI;

use Exception;
use IteratorAggregate;

class Test_Client implements Client {
	public static function get_instance() : static {
		return new static();
	}

	public function edit(
		string $input,
		string $instruction,
		string $model = "text-davinci-edit-001",
		int $n = 1,
		float $temperature = null,
		float $top_p = null,
	) : Edit {
		throw new Exception( 'Not implemented.' );
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
		foreach ( $messages as $message ) {
			if ( $message->role === 'user' ) {
				$last_message = $message;
			}
		}
		$fixture_path = __DIR__ . '/fixtures/' . sanitize_key( $last_message->content ) . '.json';
		if ( file_exists( $fixture_path ) ) {
			return Chat::from_data( json_decode( file_get_contents( $fixture_path ) ) );
		}

		throw new Exception( 'No fixture found for that content.' );
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
		$chat = $this->chat( ...func_get_args() );
		return new Test_Chat_Stream( $chat );
	}

	public function create_assisstant(
		string $model,
		string $name = null,
		string $description = null,
		string $instructions = null,
		array $tools = [],
		array $file_ids = [],
	) : Assistant {

	}

	public function get_assistant(
		string $id,
	) : Assistant {

	}

	public function create_thread(
		array $messages,
	) : Thread {

	}

	public function create_thread_message(
		Thread_New_Message $message
	) : Thread_Message {

	}

	public function get_thread_messages(
		string $thread_id,
		int $limit = 20,
		string $order = 'desc',
		string $after = null,
		string $before = null,
	) : array {

	}

	/**
	 * @return Thread_Message
	 */
	public function get_thread_message(
		string $thread_id,
		string $message_id,
	) : Thread_Message {

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

	}

	public function list_thread_runs(
		string $thread_id,
	) : array {

	}

	public function list_thread_run_steps(
		string $thread_id,
		string $run_id,
		int $limit = 20,
		string $order = 'desc',
		string $after = null,
		string $before = null,
	) : array {

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

	}

	public function get_file_contents(
		string $file_id,
	) : array {

	}

	public function get_embeddings(
		string $input,
		string $model = 'text-embedding-ada-002',
		string $encoding_format = 'float',
	) : array {

	}
}
