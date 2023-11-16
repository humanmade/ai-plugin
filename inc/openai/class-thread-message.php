<?php

namespace AI\OpenAI;

use Exception;
use JsonSerializable;

class Thread_Message implements JsonSerializable {
	/**
	 * @param "assistant"|"user"|"system"|"function" $role
	 * @param ?Thread_Message_Content[] $content
	 */
	public function __construct(
		public string $id,
		public string $role,
		public string $thread_id,
		public ?array $content = null,
		public ?string $run_id = null,
		public ?array $file_ids = null,
		public ?string $assistant_id = null,
	) {}

	public static function from_data( $json ) : static {
		return new static(
			id: $json->id,
			role: $json->role,
			content: $json->content ?? null,
			thread_id: $json->thread_id,
			run_id: $json->run_id ?? null,
			file_ids: $json->files_ids ?? null,
			assistant_id: $json->assistant_id ?? null,
		);
	}

	public function jsonSerialize() : array {
		$data = [
			'role' => $this->role,
			'content' => $this->content,
			'id' => $this->id,
		];

		return $data;
	}
}

class Thread_Message_Content {
	public function __construct(
		public string $type,
		public ?Thread_Message_Content_Text $text,
		public ?Thread_Message_Content_File $image_file,

	) {}

	public static function from_data( $json ) : static {
		return new static(
			type: $json->type,
			text: $json->text ? Thread_Message_Content_Text::from_data( $json->text ) : null,
			image_file: $json->image_file ? Thread_Message_Content_File::from_data( $json->image_file ) : null,
		);
	}
}

class Thread_Message_Content_Text {
	public function __construct(
		public string $value,
		public ?array $annotations,
	) {}

	public static function from_data( $json ) : static {
		if ( count( $json->annotations ) > 0 ) {
			throw new Exception( "Annotations not implemented." );
		}
		return new static(
			value: $json->value,
			annotations: $json->annotations ? array_map( Thread_Message_Content_Text_Annotation::from_data(...), $json->annotations ) : null,
		);
	}
}

class Thread_Message_Content_Text_Annotation {
	public function __construct(
		public string $type,
		public string $text,
		public int $start_index,
		public int $end_index,
		public ?Thread_Message_Content_Text_Annotation_File_Path $file_path,
	) {}

	public static function from_data( $json ) : static {
		return new static(
			type: $json->type,
			text: $json->text,
			start_index: $json->start_index,
			end_index: $json->end_index,
			file_path: $json->file_path ? Thread_Message_Content_Text_Annotation_File_Path::from_data( $json->file_path ) : null,
		);
	}
}

class Thread_Message_Content_Text_Annotation_File_Path {
	public function __construct(
		public string $file_id,
	) {}

	public static function from_data( $json ) : static {
		return new static(
			file_id: $json->file_id,
		);
	}
}

class Thread_Message_Content_File {
	public function __construct(
		public string $file_id,
	) {}

	public static function from_data( $json ) : static {
		return new static(
			file_id: $json->file_id,
		);
	}
}
