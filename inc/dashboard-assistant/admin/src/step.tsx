import React from 'react';

import MessageComponent from './message';
import Loading from '../../../src/loading';
import type { OpenAI } from 'openai';

export default function StepComponent( { step }: { step: OpenAI.Beta.Threads.Runs.Steps.RunStep } ) {
	if ( step.type !== 'tool_calls' ) {
		return null;
	}
	return (
		<div className="flex mb-4 justify-between">
			<div>
				{(step.stepDetails as OpenAI.Beta.Threads.Runs.Steps.ToolCallsStepDetails ).toolCalls?.map( toolCall => {
					switch ( toolCall.type ) {
						case 'function':
							return <FunctionComponent function_={ toolCall.function } loading={ step.status === 'in_progress' } key={ toolCall.id } />
						case 'code_interpreter':
							return <CodeInterpreter toolCall={ toolCall } key={ toolCall.id } />
						default:
							return null;
					}
 				} )}
			</div>
			{ step.status === 'in_progress' &&
				<Loading />
			}
		</div>
	)
}

function FunctionComponent( { function_, loading }: { function_: OpenAI.Beta.Threads.Runs.Steps.FunctionToolCall.Function, loading: boolean } ) {
	const [ expandedArguyments, setExpandedArguments ] = React.useState( false );
	return <div className="flex flex-col font-mono my-2">
		<div>
			<span>{ loading ? 'Calling function ' : 'Called function ' }</span>
			<span className="font-bold">
				{ function_?.name }
				(
				<span className="font-normal">
					{ function_.arguments.length > 60 && ! expandedArguyments ?
						<span>
							{ function_.arguments.substr( 0, 60 ) }
							<button onClick={ () => setExpandedArguments( true ) } className="">&hellip;</button>
						</span>
						:
						<span>{ function_.arguments }</span>
					}
				</span>
				)
			</span>
		</div>
		{ function_.output &&
			<div className="flex space-x-2">
				<span className="text-gray-400 dashicons dashicons-editor-break -scale-x-100"></span>
				<div className="h-5 overflow-ellipsis overflow-hidden max-w-4xl whitespace-nowrap" >{ function_.output }</div>
			</div>
		}
	</div>
}

function CodeInterpreter( { toolCall: toolCall }: { toolCall: OpenAI.Beta.Threads.Runs.Steps.CodeInterpreterToolCall } ) {
	const [ expandedArguments, setExpandedArguments ] = React.useState( false );
	return <div className="flex flex-col font-mono my-2">
		<div>
			<span>{ toolCall.type }</span>
		</div>
		<div className="flex space-x-2">
			<span className="text-gray-400 dashicons dashicons-editor-break -scale-x-100"></span>
			<div className="overflow-ellipsis overflow-hidden max-w-4xl whitespace-pre" >{ toolCall.codeInterpreter.input }</div>
		</div>
	</div>
}
