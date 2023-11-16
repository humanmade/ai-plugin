<?php

namespace AI\OpenAI;

use JsonSerializable;

class Thread_New_Message implements JsonSerializable {
	public function __construct(
		/**
		 * @var "assistant"|"user"|"system"|"function"
		 */
		public string $role,
		public string $thread_id,
		public ?string $content = null,
		public ?string $name = null,
		public ?string $run_id = null,
		public ?array $file_ids = null,
		public ?string $assistant_id = null,
	) {}

	public function jsonSerialize() : array {
		$data = [
			'role' => $this->role,
			'content' => $this->content,
		];

		if ( $this->role === 'function' ) {
			$data['name'] = $this->name;
		}

		return $data;
	}
}

