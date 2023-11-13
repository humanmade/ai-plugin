<?php

namespace AI\OpenAI;

use DateTimeImmutable;

class Edit {
	public function __construct(
		public DateTimeImmutable $created,
		/**
		 * @var Choice[]
		 */
		public array $choices,
		public Usage $usage,
	) {}

	public static function from_data( $json ) : static {
		return new static(
			DateTimeImmutable::createFromFormat( 'U', $json->created ),
			array_map( [ __NAMESPACE__ . '\\Choice', 'from_data' ], $json->choices ),
			Usage::from_data( $json->usage )
		);
	}
}

class Choice {
	public function __construct(
		public string $text,
		public int $index,
	) {}

	public static function from_data( $json ) : static {
		return new static(
			$json->text,
			$json->index,
		);
	}
}
