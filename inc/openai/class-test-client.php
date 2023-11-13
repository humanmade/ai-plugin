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
}
