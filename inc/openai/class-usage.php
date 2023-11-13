<?php

namespace AI\OpenAI;

class Usage {
	public function __construct(
		public int $prompt_tokens,
		public int $completion_tokens,
		public int $total_tokens,
	) {}

	public static function from_data( $json ) : static {
		return new static(
			$json->prompt_tokens,
			$json->completion_tokens,
			$json->total_tokens,
		);
	}
}
