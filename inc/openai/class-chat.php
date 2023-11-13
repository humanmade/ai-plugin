<?php

namespace AI\OpenAI;

use DateTimeImmutable;

class Chat {
	public function __construct(
		public DateTimeImmutable $created,
		public string $model,
		public Usage $usage,
		/**
		 * @var Chat_Choice[]
		 */
		public array $choices,
	) {}

	public static function from_data( $json ) : static {
		return new static(
			created: DateTimeImmutable::createFromFormat( 'U', $json->created ),
			choices: array_map( [ __NAMESPACE__ . '\\Chat_Choice', 'from_data' ], $json->choices ),
			usage: Usage::from_data( $json->usage ),
			model: $json->model,
		);
	}
}

class Chat_Choice {
	public function __construct(
		public Message $message,
		public int $index,
	) {}

	public static function from_data( $json ) : static {
		return new static(
			Message::from_data( $json->message ),
			$json->index,
		);
	}
}
