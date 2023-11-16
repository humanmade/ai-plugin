<?php

namespace AI\OpenAI;

class Assistant {

	protected static array $assistants;

	public static function get_by_id( string $id ) : static {
		return static::$assistants[ $id ];
	}

	public static function register( self $assistant ) {
		static::$assistants[ $assistant->id ] = $assistant;
	}

	/**
	 * @param Assistant_Tool[] $tools
	 * @param Function_[] $registered_functions
	 */
	public function __construct(
		public string $id,
		public ?string $name,
		public ?string $description,
		public ?string $instructions,
		public array $tools,
		public array $file_ids,
		protected array $registered_functions = [],
	) {}

	public static function from_data( $json ) : static {
		return new static(
			id: $json->id,
			name: $json->name,
			description: $json->description,
			instructions: $json->instructions,
			tools: $json->tools,
			file_ids: $json->file_ids,
		);
	}

	public function register_function( Function_ $function ) {
		$this->registered_functions[ $function->name ] = $function;
	}

	public function get_registered_tools() : array {
		$tools = [ ...$this->tools ];
		foreach ( $this->registered_functions as $function ) {
			$tools[] = new Assistant_Tool(
				type: 'function',
				function: $function,
			);
		}
		return $tools;
	}

	public function call_registered_function( Function_Call $function_call ) : Message {
		$function = $this->registered_functions[ $function_call->name ];

		if ( ! $function ) {
			return new Message(
				role: 'function',
				name: $this->name,
				content: "An exception occured. Could not find function",
			);
		}

		// It's not clear if a function can be called with multiple sets of arguments,
		// or handle the response.
		foreach ( $function_call->arguments as $arguments ) {
			$args = [];
			foreach ( (array) $arguments as $argument => $value ) {
				if ( ! isset( $function->parameters['properties'][ $argument ] ) ) {
					\WP_CLI::warning( sprintf( 'Function call looks to have hullucinated argument %s', $argument ) );
					continue;
				}
				$args[ $argument ] = $value;
			}
			var_dump( $args );
			$data = call_user_func_array( $function->callback, $args );
		}

		return new Message(
			role: 'function',
			name: $this->name,
			content: json_encode( $data ),
		);
	}
}

class Assistant_Tool {
	public function __construct(
		public string $type,
		public ?Function_ $function,
	) {}

	public static function from_data( $json ) : static {
		return new static(
			type: $json->type,
			function: isset( $json->function ) ? Function_::from_data( $json->function ) : null,
		);
	}
}
