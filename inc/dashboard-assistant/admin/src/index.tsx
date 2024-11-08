import { createRoot } from 'react-dom';
import React, { useEffect } from 'react';
import MessageComponent from './message';
import StepComponent from './step';
import Loading from '../../../src/loading';
import type * as openai from 'openai'

type SSeEvent = openai.OpenAI.Beta.AssistantStreamEvent
type Event = openai.OpenAI.Beta.Threads.Message | openai.OpenAI.Beta.Threads.Runs.Steps.RunStep;

function processSseEvents( messages: Event[], event: SSeEvent ) : Event[] {
	switch ( event.event ) {
		case 'thread.message.completed':
		case 'thread.message.created':
			// If the message already exists, splice in the new one
			const index = messages.findIndex( message => message.id === event.data.id );
			if ( index > -1 ) {
				messages.splice( index, 1, event.data );
				return [...messages];
			}
			if ( ! event.data.object ) {
				event.data.object = 'thread.message';
			}
			return [
				...messages,
				event.data,
			]

		case 'thread.message.delta':
			const message = messages.find( message => message.id === event.data.id ) as openai.OpenAI.Beta.Threads.Message;
			if ( ! message ) {
				return messages;
			}
			for ( const content of event.data.delta.content || [] ) {
				switch ( content.type ) {
					case 'text':
						if ( message.content.length === 0 ) {
							message.content.push({
								type: 'text',
								text: {
									value: '',
									annotations: [],
								},
							});
						}
						if ( message.content[0].type === 'text' ) {
							message.content[0].text.value += content.text?.value || '';
						}
						break;
				}
			}
			// Return a new messages array with the updated message. Maintain the original order.
			return messages.map( message => message.id === event.data.id ? message : messages[messages.findIndex( m => m.id === message.id )] );

		case 'thread.run.step.created':
			if ( event.data.type !== 'tool_calls' ) {
				return messages;
			}
			return [
				...messages,
				event.data,
			]
		case 'thread.run.step.delta': {
			const step = messages.find( step => step.id === event.data.id ) as openai.OpenAI.Beta.Threads.Runs.Steps.RunStep;
			if ( step.type !== 'tool_calls' ) {
				return messages;
			}
			if ( ! step ) {
				return messages;
			}
			let details = event.data.delta.stepDetails as openai.OpenAI.Beta.Threads.Runs.ToolCallDeltaObject;
			for ( const toolCall of details.toolCalls ) {
				let existingToolCall = step.stepDetails.toolCalls.find( toolCall => toolCall.id === toolCall.id );
				if ( ! existingToolCall ) {
					existingToolCall = toolCall;
					step.stepDetails.toolCalls.push( toolCall );
				}
				switch ( toolCall.type ) {
					case 'function':
						existingToolCall.function.arguments += toolCall.function?.arguments || '';
						existingToolCall.function.output += toolCall.function?.output || '';
						existingToolCall.function.name = toolCall.function?.name;
						break;
					case 'code_interpreter':
						existingToolCall.codeInterpreter.input += toolCall.codeInterpreter?.input;
						existingToolCall.codeInterpreter.output += toolCall.codeInterpreter?.output;
						break;
				}

			}
			return messages.map( step => step.id === event.data.id ? step : messages[messages.findIndex( m => m.id === step.id )] );
		}

		case 'thread.run.step.completed':
		case 'thread.run.step.in_progress': {
			const step = messages.find( step => step.id === event.data.id ) as openai.OpenAI.Beta.Threads.Runs.Steps.RunStep;
			if ( ! step ) {
				return messages;
			}
			if ( step.type !== 'tool_calls' ) {
				return messages;
			}
			step.status = event.data.status;
			step.stepDetails = event.data.stepDetails as openai.OpenAI.Beta.Threads.Runs.Steps.ToolCallsStepDetails;
			return messages.map( step => step.id === event.data.id ? step : messages[messages.findIndex( m => m.id === step.id )] );
		}
	}



	return messages;
}

function MyAssistant() {

	const [ events, setEvents ] = React.useState<Event[]>( [] );
	const [ waiting, setWaiting ] = React.useState( false );

	async function streamEvents( response: Response ) {
		let messages = [...events];
		for await ( const message of streamResponse( response ) ) {
			console.log(message);
			messages = processSseEvents( messages, message )
			console.log(messages);
			setEvents( messages );
		}
	}

	useEffect( () => {
		async function fetchData() {
			setWaiting( true );
			await streamEvents( await fetchSseEvents() );
			setWaiting( false );
		}
		fetchData()
	}, [] );

	async function onSubmit( e: React.FormEvent<HTMLFormElement> ) {
		e.preventDefault();
		const input = (e.target as Element ).querySelector( 'input' )!;
		let prompt = input.value;
		setWaiting( true );
		input.value = '';
		await streamEvents( await sendMessage( prompt ) );
		setWaiting( false );
	}

	async function onClear() {
		await apiFetchRaw( `${ window.dashboardAssistant.api.root }ai/v1/my-assistant`, {
			method: 'DELETE',
		} );
		setEvents( [] );
	}
	console.log(events);
	return <div className="flex flex-col py-6 flex-grow max-w-5xl mx-auto">
		<div className="flex justify-between ">
			<h1 className="px-6">Your AI Assistant</h1>
			<button className="p-0  self-center border-none bg-transparent text-gray-400 hover:text-gray-800" onClick={ onClear }><span className="dashicons dashicons-table-row-delete"></span></button>
		</div>
		<div className="flex-grow overflow-x-auto px-12 mb-6 flex flex-col-reverse">
			{ [...events].reverse().map( event => {
				switch ( event.object ) {
					case 'thread.message':
						return <MessageComponent key={ event.id } message={ event } />
					case 'thread.run.step':
						return <StepComponent key={ event.id } step={ event } />
				}
 			} ) }
		</div>
		<form className="p-6 mx-6 border border-solid border-gray-300 rounded-xl shadow-xl flex flex-col" onSubmit={ onSubmit }>
			<input className="text-md bg-transparent border-none text-gray-600 outline-none" placeholder="Ask me anything about your site..." />
			<div className="flex space-x-2">
				<button disabled={ waiting } type="submit" className="rounded bg-blue-600 disabled:bg-blue-300 font-bold text-white border-none p-2 px-4 mt-2">Send</button>
				{ waiting && <Loading /> }
			</div>
		</form>

	</div>
}

const wrapper = document.getElementById( 'my-assistant-wrapper' );
if ( wrapper ) {
	const root = createRoot( wrapper );
	root.render( <MyAssistant /> );
}

async function fetchSseEvents() {
	const response = await apiFetchRaw( `${ window.dashboardAssistant.api.root }ai/v1/my-assistant?stream=true`, {
		headers: {
			'Content-Type': 'application/json',
			Accept: 'text/event-stream',
		},
	} );

	if ( ! response.ok ) {
		// Manually parse the text so we can handle non-JSON if needed.
		let text = await response.text();
		try {
			const json = JSON.parse( text );
			text = json.message;
		} catch ( e ) {
			// no op.
		}
		throw new Error( text );
	}

	return response;

}

async function sendMessage( prompt: string ) {
	const response = await apiFetchRaw( `${ window.dashboardAssistant.api.root }ai/v1/my-assistant`, {
		headers: {
			'Content-Type': 'application/json',
			Accept: 'text/event-stream',
		},
		body: JSON.stringify( {
			content: prompt,
			stream: true,
		} ),
		method: 'POST',
	} );

	if ( ! response.ok ) {
		// Manually parse the text so we can handle non-JSON if needed.
		let text = await response.text();
		try {
			const json = JSON.parse( text );
			text = json.message;
		} catch ( e ) {
			// no op.
		}
		throw new Error( text );
	}

	return response;
}

export async function apiFetchRaw(input: RequestInfo | URL, init: RequestInit) : Promise<Response> {
	init.headers = {
		...init.headers,
		['X-WP-Nonce']: window.dashboardAssistant.api.nonce
	};
	const response = await fetch(input, init);
	return response;
}


export async function *streamResponse(response: Response) : AsyncGenerator<SSeEvent> {
	const reader = response.body!.getReader();
	let buffer: string = '';

	while ( true ) {
		const { value, done } = await reader.read();

		// Convert the chunk to a string.
		const chunk = new TextDecoder( 'utf-8' ).decode( value );

		// Split the chunk by line.
		buffer += chunk;
		const lines = buffer.split( '\n' );
		buffer = lines.pop() || '';

		let type = "message"
		for ( let index = 0; index < lines.length; index++ ) {
			const line = lines[ index ];
			if ( line.startsWith( 'event:' ) ) {
				type = line.slice( 6 ).trim();
			}
			if ( line.startsWith( 'response:' ) ) {
				// Extract the JSON data from the line.
				const data = JSON.parse( line.slice( 'response:'.length ) );
				data._message_type = type;
				yield {
					event: type,
					data,
				};
			} else if ( line === '' && index === lines.length - 1 ) {
				// If the last line is empty, reset the chunk.
			}
		}

		if ( done ) {
			break;
		}
	}
}
