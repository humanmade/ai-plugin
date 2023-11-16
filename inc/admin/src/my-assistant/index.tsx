import { createRoot } from 'react-dom';
import React, { useEffect } from 'react';
import { streamResponse } from '../../blocks/ai/utils';
import MessageComponent from './message';
import StepComponent from './step';
import { SseEvent } from './types';
import Loading from '../../blocks/ai/loading';


function addNewMessage( message: SseEvent, events: SseEvent[] ) : SseEvent[] {
	let newEvents = [...events];
	switch ( message._message_type ) {
		case 'message': {
			// Update the message if it already exists.
			const messageIndex = newEvents.findIndex( event => event.id === message.id );
			if ( messageIndex > -1 ) {
				newEvents[ messageIndex ] = message;
			} else {
				newEvents = newEvents.concat( message );
			}

			// If there's a step for this message, remove the step.
			const stepIndex = newEvents.findIndex( step => step._message_type === 'step' && step.step_details.message_creation?.message_id === message.id );
			console.log( stepIndex )
			if ( stepIndex > -1 ) {
				newEvents.splice( stepIndex, 1 );
			}
			break;
		}
		case 'step': {
			// Update the step if it already exists.
			const stepIndex = newEvents.findIndex( step => step.id === message.id );
			if ( stepIndex > -1 ) {
				newEvents[ stepIndex ] = message;
			} else {
				newEvents = newEvents.concat( message );
			}
			break;
		}
	}

	return newEvents;
}

function MyAssistant() {

	const [ events, setEvents ] = React.useState<SseEvent[]>( [] );
	const [ sendingMessages, setSendingMessages ] = React.useState<Message[]>( [] );
	const [ waiting, setWaiting ] = React.useState( false );

	async function streamEvents( response: Response ) {
		let newEvents = [ ...events ];
		for await ( const message of streamResponse( response ) ) {
			newEvents = addNewMessage( message, newEvents );
			setEvents( newEvents );
		}
	}

	useEffect( () => {
		async function fetchData() {
			setWaiting( true );
			await streamEvents( await fetchMessages() );
			setWaiting( false );
		}
		fetchData()
	}, [] );

	async function onSubmit( e: React.FormEvent<HTMLFormElement> ) {
		e.preventDefault();
		const input = e.target.querySelector( 'input' );
		let prompt = input.value;
		setWaiting( true );
		input.value = '';
		await streamEvents( await sendMessage( prompt ) );
		setWaiting( false );
	}

	return <div className="flex flex-col py-6 flex-grow max-w-5xl mx-auto">
		<h1 className="px-6">Your AI Assistant</h1>
		<div className="flex-grow overflow-x-auto px-12 mb-6 flex flex-col-reverse ">
			<div className="animate-pulse">
				{ sendingMessages.map( message => (
					<MessageComponent key={ message.id } message={ message } />
				))}
			</div>
			{ [...events].reverse().map( event => (
				event._message_type === 'step' ?
					<StepComponent key={ event.id } step={ event } />
				:
					<MessageComponent key={ event.id } message={ event as Message } />
			) ) }
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

// if ( module.hot ) {
// 	module.hot.accept('./my-assistant.tsx', () => {
// 		console.log( 'updated' );
// 		const MyAssistant = require( './my-assistant.tsx' ).default;
// 		const root = createRoot( wrapper );
// 		root.render( <MyAssistant /> );
// 	} );

// }




async function fetchMessages() {
	const response = await apiFetchRaw( '/?rest_route=/ai/v1/my-assistant&stream=true', {
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
	const response = await apiFetchRaw( '/?rest_route=/ai/v1/my-assistant', {
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
		// ['X-WP-Nonce']: window.AIBlock.nonce
	};
	const response = await fetch(input, init);
	return response;
}
