<?php

namespace AI\OpenAI;

use JsonSerializable;

class Thread_Run {
	public function __construct(
		/**
		 * @param Assistant_Tool[] $tools
		 *
		 * @var string
		 */
		public string $id,
		/**
		 * @var "queued"|"in_progress"|"requires_action"|"cancelling"|"cancelled"|"failed"|"completed"|"expired"
		 */
		public string $status,
		public string $thread_id,
		public string $assistant_id,
		public ?Thread_Run_Required_Action $required_action,
		public ?Thread_Run_Error $last_error,
		public string $model,
		public string $instructions,
		public array $tools,
	) {}

	public static function from_data( $json ) : static {
		return new static(
			id: $json->id,
			status: $json->status,
			thread_id: $json->thread_id,
			assistant_id: $json->assistant_id,
			required_action: isset( $json->required_action ) ? Thread_Run_Required_Action::from_data( $json->required_action ) : null,
			last_error: isset( $json->last_error ) ? Thread_Run_Error::from_data( $json->last_error ) : null,
			model: $json->model,
			instructions: $json->instructions,
			tools: array_map( Assistant_Tool::from_data( ... ), $json->tools ),
		);
	}

	/**
	 * Check if the run needs to be waited on.
	 *
	 * @return boolean
	 */
	public function should_wait() : bool {
		return in_array( $this->status, [ 'queued', 'in_progress', 'cancelling' ], true );
	}
}

class Thread_Run_Required_Action {
	public function __construct(
		public string $type,
		public Thread_Run_Required_Action_Submit_Tool_Ouputs $submit_tool_outputs,
	) {}

	public static function from_data( $json ) : static {
		return new static(
			type: $json->type,
			submit_tool_outputs: Thread_Run_Required_Action_Submit_Tool_Ouputs::from_data( $json->submit_tool_outputs ),
		);
	}
}

class Thread_Run_Required_Action_Submit_Tool_Ouputs {
	/**
	 * @param Thread_Run_Required_Action_Submit_Tool_Call[] $tool_calls
	 */
	public function __construct(
		public array $tool_calls,
	) {}

	public static function from_data( $json ) : static {
		return new static(
			tool_calls: array_map( Thread_Run_Required_Action_Submit_Tool_Call::from_data( ... ), $json->tool_calls ),
		);
	}
}

class Thread_Run_Required_Action_Submit_Tool_Call {
	public function __construct(
		public string $id,
		public string $type,
		public ?Function_Call $function,
		public ?Code_Interpreter $code_interpreter,
	) {}

	public static function from_data( $json ) : static {
		return new static(
			id: $json->id,
			type: $json->type,
			function: isset( $json->function ) ? Function_Call::from_data( $json->function ) : null,
			code_interpreter: isset( $json->code_interpreter ) ? Code_Interpreter::from_data( $json->code_interpreter ) : null,
		);
	}
}

class Thread_Run_Tool_Output {
	public function __construct(
		public string $tool_call_id,
		public ?string $output,
	) {}
}

class Thread_Run_Error {
	public function __construct(
		public string $code,
		public string $message,
	) {}

	public static function from_data( $json ) : static {
		return new static(
			code: $json->code,
			message: $json->message,
		);
	}
}

class Code_Interpreter {
	public function __construct(
		public string $input,
		public ?array $outputs,
	) {}

	public static function from_data( $json ) : static {
		return new static(
			input: $json->input,
			outputs: isset( $json->outputs ) ? array_map( Code_Interpreter_Output::from_data( ... ), $json->outputs ) : null,
		);
	}
}

class Code_Interpreter_Output {
	/**
	 * @param 'image'|'logs' $type
	 */
	public function __construct(
		public ?Code_Interpreter_Output_Image $image,
		public string $type,
		public ?string $logs,
	) {}

	public static function from_data( $json ) : static {
		return new static(
			type: $json->type,
			image: isset( $json->image ) ? Code_Interpreter_Output_Image::from_data( $json->image ) : null,
			logs: $json->logs ?? null,
		);
	}
}

class Code_Interpreter_Output_Image {
	public function __construct(
		public string $file_id,
	) {}

	public static function from_data( $json ) : static {
		return new static(
			file_id: $json->file_id,
		);
	}
}
