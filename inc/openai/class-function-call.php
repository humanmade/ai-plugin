<?php

namespace AI\OpenAI;

use JsonSerializable;

class Function_Call implements JsonSerializable {
	public function __construct(
		public string $name,
		public array $arguments,
		public ?string $output,
	) {}

	public static function from_data( $json ) : static {
		$args = json_decode( $json->arguments );
		return new static(
			name: $json->name,
			arguments: is_array( $args ) ? $args : [ $args ],
			output: $json->output ?? null,
		);
	}

	public function jsonSerialize() : array {
		return [
			'name' => $this->name,
			'arguments' => json_encode( $this->arguments ),
			'output' => $this->output,
		];
	}

	public function respond( callable $callback ) : Message {
		$data = $callback( $this->arguments[0] );
		return new Message(
			role: 'function',
			name: $this->name,
			content: json_encode( $data ),
		);
	}
}
