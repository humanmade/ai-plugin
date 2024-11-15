<?php

namespace AI\OpenAI;

use Exception;
use Generator;
use Iterator;
use OpenAI\Responses\Threads\Runs\Steps\Delta\ThreadRunStepDeltaResponse;
use OpenAI\Responses\Threads\Runs\Steps\Delta\ThreadRunStepResponseToolCallsStepDetails;
use OpenAI\Responses\Threads\Runs\Steps\Delta\ThreadRunStepResponseFunctionToolCall;
use OpenAI\Responses\Threads\Runs\Steps\ThreadRunStepResponse;
use OpenAI\Responses\Threads\Runs\ThreadRunResponseRequiredActionFunctionToolCall;
use OpenAI\Responses\Threads\Runs\ThreadRunStreamResponse;
use OpenAI\Responses\Threads\Runs\ThreadRunResponse;

class Thread {
	public function __construct(
		public string $id,
	) {}

	public static function from_data( $json ) : static {
		return new static(
			id: $json->id,
		);
	}


	/**
	 * Run the thread with a given assistant id.
	 *
	 * @param Client $client
	 * @return ?Generator<int, Thread_Run_Step, mixed, mixed> The generator which should be iterated over. Yields Thread_Run_Step.
	 */
	public function resume( Client $client ) {
		// Check if there is an active run.
		$runs = $client->list_thread_runs( $this->id );
		$runs = array_values( array_filter( $runs, function ( Thread_Run $run ) : bool {
			return $run->should_wait() || $run->status === 'requires_action';
		} ) );

		if ( ! $runs ) {
			return;
		}

		return $this->run_steps( $runs[0], $client );
	}

	/**
	 * Run the thread with a given assistant id.
	 *
	 * @param Client $client
	 * @return Generator<int, ThreadRunStreamResponse, mixed, mixed> The generator which should be iterated over. Yields Thread_Run_Step.
	 */
	public function run( string $assistant_id, Client $client ) : Generator {
		$assistant = Assistant::get_by_id( $assistant_id );
		$run = $client->run_thread_streamed( $this->id, $assistant_id, 'gpt-4o', null, $assistant->get_registered_tools() );
		return $this->handle_streamed_response( $run, $assistant_id, $client );
	}

	public function handle_streamed_response( Iterator $run, string $assistant_id, Client $client  ) : Generator {
		$assistant = Assistant::get_by_id( $assistant_id );
		$openai_client = $client->get_openai_client();
		foreach ( $run as $run_step ) {
			if ( $run_step->response instanceof ThreadRunResponse && $run_step->response->requiredAction ) {
				$tool_outputs = [];
				foreach ( $run_step->response->requiredAction->submitToolOutputs->toolCalls as $tool_call ) {
					if ( $tool_call instanceof ThreadRunResponseRequiredActionFunctionToolCall ) {
						$args = json_decode( $tool_call->function->arguments, true );

						$function = new Function_Call(
							name: $tool_call->function->name,
							arguments: [ $args ],
							output: null,
						);

						$message = $assistant->call_registered_function( $function );

						$tool_outputs[] = [
							'tool_call_id' => $tool_call->id,
							'output' => $message->content,
						];
					}
				}

				$run = $openai_client->threads()->runs()->submitToolOutputsStreamed( $this->id, $run_step->response->id, [ 'tool_outputs' => $tool_outputs ] );
				$iterator = $this->handle_streamed_response( $run->getIterator(), $assistant_id, $client );
				foreach ( $iterator as $run_step ) {
					yield $run_step;
				}
			}
			yield $run_step;
		}
	}
	/**
	 * Get the runs steps as a generator.
	 *
	 * @param Thread_Run $run
	 * @param Client $client
	 * @return Generator<int, Thread_Run_Step>
	 */
	protected function run_steps( Thread_Run $run, Client $client ) : Generator {

		$last_run_step = null;

		while ( true ) {
			$run = $client->get_thread_run( $this->id, $run->id );
			$steps = $client->list_thread_run_steps( $this->id, $run->id, 20, 'asc', $last_run_step );
			$is_completed = true;
			foreach ( $steps as $step ) {
				yield $step;
				if ( ! $step->should_wait() ) {
					$last_run_step = $step->id;
				} else {
					$is_completed = false;
					if ( $step->step_details->type === 'tool_calls' && $run->status === 'requires_action' ) {
						$tool_outputs = [];
						foreach ( $step->step_details->tool_calls as $tool_call ) {
							switch ( $tool_call->type ) {
								case 'function':
									$assistant = Assistant::get_by_id( $run->assistant_id );
									$message = $assistant->call_registered_function( $tool_call->function );
									$tool_outputs[] = new Thread_Run_Tool_Output(
										tool_call_id: $tool_call->id,
										output: $message->content,
									);
									break;
							}
						}

						if ( $tool_outputs ) {
							$client->submit_tool_outputs( $this->id, $run->id, $tool_outputs );
						}
					}
				}
			}
			if ( $is_completed ) {
				if ( ! $run->should_wait() ) {
					// It's possible that the run _just_ completed so we need to do one more cycle of get steps before returning.
					break;
				}
			}
		}
	}

	protected function wait_on_run( Thread_Run $run, Client $client ) : Thread_Run {
		if ( $run->should_wait() ) {
			$run = $client->get_thread_run( $this->id, $run->id );
			return $this->wait_on_run( $run, $client );
		}

		if ( $run->status === 'requires_action' && $run->required_action->type === 'submit_tool_outputs' ) {
			$tool_outputs = [];
			foreach ( $run->required_action->submit_tool_outputs->tool_calls as $tool_call ) {
				switch ( $tool_call->type ) {
					case 'function':
						$assistant = Assistant::get_by_id( $run->assistant_id );
						$message = $assistant->call_registered_function( $tool_call->function );
						$tool_outputs[] = new Thread_Run_Tool_Output(
							tool_call_id: $tool_call->id,
							output: $message->content,
						);
						break;
					default:
						throw new Exception( sprintf( 'No implementation for tool type %s', $tool_call->type ) );
				}
			}

			if ( $tool_outputs ) {
				$run = $client->submit_tool_outputs( $this->id, $run->id, $tool_outputs );
				return $this->wait_on_run( $run, $client );
			}
		}

		return $run;
	}


}

