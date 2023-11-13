<?php

namespace AI\OpenAI;

use JsonSerializable;

class Function_ implements JsonSerializable {
	public function __construct(
		public string $name,
		public ?string $description = null,
		public array $parameters = [],
	) {}

	public function jsonSerialize() : array {
		$data = [
			'name' => $this->name,
			'description' => $this->description,
			'parameters' => $this->parameters,
		];
		return $data;
	}
}

