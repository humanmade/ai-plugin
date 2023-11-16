<?php

namespace AI\OpenAI;

use Exception;
use JsonSerializable;
use phpDocumentor\Reflection\DocBlock\Tags\Param;
use phpDocumentor\Reflection\DocBlockFactory;
use ReflectionFunction;
use stdClass;

class Function_ implements JsonSerializable {

	public function __construct(
		public string $name,
		public ?string $description = null,
		public $parameters,
		public $callback = null,
	) {}

	public function jsonSerialize() : array {
		$params = [ ...$this->parameters ];
		$params['properties'] = (object) $params['properties'];
		$data = [
			'name' => $this->name,
			'description' => $this->description,
			'parameters' => $params,
		];
		return $data;
	}

	public static function from_data( $json ) : static {
		return new static(
			name: $json->name,
			description: $json->description,
			parameters: $json->parameters,
		);
	}

	public static function from_callable( callable $function ) : Function_ {
		$reflection = new ReflectionFunction( $function );
		$params = $reflection->getParameters();

		$doc_block = DocBlockFactory::createInstance();
		$doc_block = $doc_block->create( $reflection->getDocComment() );

		$name = $reflection->getName();
		$name = str_replace('\\', '_', $name);
		$name = strtolower($name);

		$function = new Function_(
			name: $name,
			description: $doc_block->getSummary(),
			parameters: null,
			callback: $function,
		);

		$param_docs = $doc_block->getTagsWithTypeByName( 'param' );

		$parameters = [];
		$required = [];

		foreach ( $params as $param ) {
			$param_schema = [
				'type' => $param->getType() ? $param->getType()->getName() : null,
			];

			if ( ! $param->isOptional() ) {
				$required[] = $param->getName();
			}

			if ( $param->isDefaultValueAvailable() ) {
				$param_schema['default'] = $param->getDefaultValue();
			}

			$param_doc = reset( array_filter( $param_docs, function ( Param $param_doc ) use ( $param ) : bool {
				return $param_doc->getVariableName() === $param->getName();
			} ) );

			if ( $param_doc ) {
				$param_schema['description'] = (string) $param_doc->getDescription();
				$param_schema['type'] = (string) $param_doc->getType();
			}

			if ( ! $param_schema['type'] ) {
				throw new Exception( sprintf( 'Param %s has no type', $param->getName() ) );
			}

			$type_map = [
				'int' => 'integer',
				'string' => 'string',
				'float' => 'number',
				'bool' => 'boolean',
			];

			if ( isset( $type_map[ $param_schema['type'] ] ) ) {
				$param_schema['type'] = $type_map[ $param_schema['type'] ];
			} else {
				throw new Exception( sprintf( 'Param type %s not supported', $param->getName() ) );
			}
			$parameters[ $param->getName() ] = $param_schema;
		}

		$function->parameters = [
			'type' => 'object',
			'properties' => $parameters,
			'required' => $required,
		];
		return $function;
	}
}

