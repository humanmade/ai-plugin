<?php

namespace AI\OpenAI;

use JsonSerializable;

class Message implements JsonSerializable {
	public function __construct(
		/**
		 * @var "assistant"|"user"|"system"|"function"
		 */
		public string $role,
		public ?string $content = null,
		public ?Function_Call $function_call = null,
		public ?string $name = null,
	) {}

	public static function from_data( $json ) : static {
		return new static(
			role: $json->role,
			content: $json->content ?? null,
			function_call: ! empty( $json->function_call ) ? Function_Call::from_data( $json->function_call ) : null,
		);
	}

	public function jsonSerialize() : array {
		$data = [
			'role' => $this->role,
			'content' => $this->content,
		];

		if ( $this->role === 'function' ) {
			$data['name'] = $this->name;
		}

		if ( $this->function_call ) {
			$data['function_call'] = $this->function_call;
		}

		return $data;
	}
}

