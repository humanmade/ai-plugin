<?php

namespace AI\OpenAI;

class Embedding {
	public function __construct(
		public int $index,
		public array $embedding,
	) {}

	public static function from_data( $json ) : static {
		return new static(
			index: $json->index,
			embedding: $json->embedding,
		);
	}
}
