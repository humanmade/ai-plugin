<?php

namespace AI\OpenAI;

class Thread_Run_Step {
	public function __construct(
		public string $id,
		/**
		 * @var "in_progress"|"cancelled"|"failed"|"completed"|"expired"
		 */
		public string $status,
		/**
		 * @var "message_creation"|"tool_calls"
		 */
		public string $type,
		public string $thread_id,
		public Thread_Run_Step_Details $step_details,

	) {}

	public static function from_data( $json ) : static {
		return new static(
			id: $json->id,
			status: $json->status,
			type: $json->type,
			thread_id: $json->thread_id,
			step_details: Thread_Run_Step_Details::from_data( $json->step_details ),
		);
	}

	/**
	 * Check if the run needs to be waited on.
	 *
	 * @return boolean
	 */
	public function should_wait() : bool {
		return in_array( $this->status, [ 'in_progress' ], true );
	}
}

class Thread_Run_Step_Details {
	/**
	 * @param Thread_Run_Required_Action_Submit_Tool_Call[] $tool_calls
	 * @param 'message_creation'|'tool_calls' $type
	 */
	public function __construct(
		public string $type,
		public ?Thread_Run_Step_Message_Creation $message_creation,
		public ?array $tool_calls,
	) {}

	public static function from_data( $json ) : static {
		return new static(
			type: $json->type,
			tool_calls: isset( $json->tool_calls ) ? array_map( Thread_Run_Required_Action_Submit_Tool_Call::from_data( ... ), $json->tool_calls ) : null,
			message_creation: isset( $json->message_creation ) ? Thread_Run_Step_Message_Creation::from_data( $json->message_creation ) : null,
		);
	}
}

class Thread_Run_Step_Message_Creation {
	/**
	 * @param Thread_Run_Required_Action_Submit_Tool_Call[] $tool_calls
	 */
	public function __construct(
		public string $message_id,
	) {}

	public static function from_data( $json ) : static {
		return new static(
			message_id: $json->message_id,
		);
	}
}
