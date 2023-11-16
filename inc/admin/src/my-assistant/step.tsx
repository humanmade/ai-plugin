import React from 'react';

import { Step } from './types';
import MessageComponent from './message';
import Loading from '../../blocks/ai/loading';

export default function StepComponent( { step }: { step: Step } ) {
	if ( step.step_details.type === 'message_creation' ) {
		return (
			<MessageComponent message={ {
				role: 'assistant',
				content: [],
				id: step.step_details.message_creation?.message_id!,
			} } />
		)
	}
	return (
		<div className="flex mb-4 justify-between">
			<div>
				{step.step_details.tool_calls?.map( toolCall => (
					<FunctionComponent function_={ toolCall.function } loading={ step.status === 'in_progress' } key={ toolCall.id } />
				) )}
			</div>
			{ step.status === 'in_progress' &&
				<Loading />
			}
		</div>
	)
}

function FunctionComponent( { function_, loading }: { function_: any, loading: boolean } ) {
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
