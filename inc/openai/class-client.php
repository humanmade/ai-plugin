<?php

namespace AI\OpenAI;

use IteratorAggregate;

interface Client {
	public static function get_instance() : static;

	public function edit(
		string $input,
		string $instruction,
		string $model = "text-davinci-edit-001",
		int $n = 1,
		float $temperature = null,
		float $top_p = null,
	) : Edit;

	/**
	 * Send a Chat request to OpenAI.
	 *
	 * @param Message[] $messages A list of messages comprising the conversation so far.
	 * @param string $model
	 * @param integer $n
	 * @param Function[] $functions A list of functions the model may generate JSON inputs for.
	 * @param float|null $temperature What sampling temperature to use, between 0 and 2. Higher values like 0.8 will make the output more random, while lower values like 0.2 will make it more focused and deterministic.
	 * @param float|null $top_p An alternative to sampling with temperature, called nucleus sampling, where the model considers the results of the tokens with top_p probability mass. So 0.1 means only the tokens comprising the top 10% probability mass are considered.
	 * @param string|null $stop Up to 4 sequences where the API will stop generating further tokens.
	 * @param integer|null $max_tokens The maximum number of tokens to generate in the chat completion.
	 * @param float|null $presence_penalty Number between -2.0 and 2.0. Positive values penalize new tokens based on whether they appear in the text so far, increasing the model's likelihood to talk about new topics.
	 * @param float|null $frequency_penalty Number between -2.0 and 2.0. Positive values penalize new tokens based on their existing frequency in the text so far, decreasing the model's likelihood to repeat the same line verbatim.
	 * @param array|null $logit_bias Modify the likelihood of specified tokens appearing in the completion.
	 * @param string|null $user A unique identifier representing your end-user, which can help OpenAI to monitor and detect abuse.
	 * @return Chat
	 */
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
	) : Chat;

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
	) : IteratorAggregate;

	public function create_assisstant(
		string $model,
		string $name = null,
		string $description = null,
		string $instructions = null,
		array $tools = [],
		array $file_ids = [],
	) : Assistant;

	public function get_assistant(
		string $id,
	) : Assistant;

	public function create_thread(
		array $messages,
	) : Thread;

	public function create_thread_message(
		Thread_New_Message $message
	) : Thread_Message;

	/**
	 * @return Thread_Message[]
	 */
	public function get_thread_messages(
		string $thread_id,
		int $limit = 20,
		string $order = 'desc',
		string $after = null,
		string $before = null,
	) : array;

	/**
	 * @return Thread_Message
	 */
	public function get_thread_message(
		string $thread_id,
		string $message_id,
	) : Thread_Message;

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
	) : Thread_Run;

	/**
	 * Retrieve a run
	 *
	 * @param string $run_id
	 */
	public function get_thread_run(
		string $thread_id,
		string $run_id,
	) : Thread_Run;

	public function list_thread_runs(
		string $thread_id,
	) : array;

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
	) : array;

	/**
	 * @param Thread_Run_Tool_Output[] $tools_output
	 *
	 */
	public function submit_tool_outputs(
		string $thread_id,
		string $run_id,
		array $tool_outputs
	) : Thread_Run;

	public function get_file_contents(
		string $file_id,
	) : array;

	/**
	 * @return Embedding[]
	 */
	public function get_embeddings(
		string $input,
		string $model = 'text-embedding-ada-002',
		string $encoding_format = 'float',
	) : array;
}
