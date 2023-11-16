<?php

namespace AI\OpenAI;

class Image {
	public function __construct(
		public ?string $url,
		public ?string $b64_json,
		public ?string $revised_prompt,

	) {}

	public static function from_data( $json ) : static {
		return new static(
			url: $json->url ?? null,
			b64_json: $json->b64_json ?? null,
			revised_prompt: $json->revised_prompt ?? null,
		);
	}
}
