import { serialize } from '@wordpress/blocks';
import { select } from '@wordpress/data';
import { useState, useRef } from '@wordpress/element';
import { PluginSidebar, PluginSidebarMoreMenuItem } from '@wordpress/edit-post';

import Icon from './icon';
import Loading from './loading';
import { chat, streamResponse } from './utils';

export default function Chat() {
	const [ messages, setMessages ] = useState( [ {
		role: 'assistant',
		content: 'Hello, how can I help you?',
	} ] );

	const [ loading, setLoading ] = useState( false );
	const [ loadingAbortController, setLoadingAbortController ] = useState<AbortController | null>( null );
	const messageWindow = useRef<HTMLDivElement>( null );

	async function cancelLoading() {
		if ( loadingAbortController ) {
			try {
				loadingAbortController.abort();
			} catch ( e ) {
				// no op.
			}
		}
	}

	async function onSubmit( e: React.FormEvent<HTMLFormElement> ) {

		const input = e.currentTarget.querySelector( 'input' );
		const value = input?.value;

		if ( ! value ) {
			return;
		}
		e.preventDefault();
		setLoading( true );
		const newMessages = [ ...messages ];
		newMessages.push( {
			content: value,
			role: 'user',
		} );

		if ( input ) {
			input.value = '';
		}

		setMessages( newMessages );
		const loadingAbortController = new AbortController();
		setLoadingAbortController( loadingAbortController );

		messageWindow.current?.scrollTo( 0, messageWindow.current?.scrollHeight );

		const editorStore = select( 'core/editor' );
		// @ts-ignore - method names not found.
		const title = editorStore.getEditedPostAttribute( 'title' );
		// @ts-ignore
		const type = editorStore.getEditedPostAttribute( 'type' );
		// @ts-ignore
		const content = editorStore.getBlocks().map( block => {
			const serializedBlock = serialize( block );
			return serializedBlock;
		} ).join( '\n' );

		try {
			const response = await chat( newMessages, { title, type, content }, loadingAbortController.signal );
			const message = {
				role: 'assistant',
				content: ''
			};

			for await ( const text of streamResponse( response ) ) {
				message.content += text;
				setMessages( [ ...newMessages, message ] );
				messageWindow.current?.scrollTo( 0, messageWindow.current?.scrollHeight );
			}
		} catch ( e ) {
			// no op
		} finally {
			setLoading( false );
		}
	}

	return (
		<>
			<PluginSidebarMoreMenuItem target="altis-ai-chat">
				Chat
			</PluginSidebarMoreMenuItem>
			<PluginSidebar
				className="ai-chat"
				icon={ <Icon /> }
				name="altis-ai-chat"
				title="AI Chat"
			>
				<div className="tailwind">
					<div className="flex flex-col" style={ { height: 'calc(100vh - 140px)' } }>
						<div ref={ messageWindow } className="py-4 flex-1 flex flex-col overflow-auto">
							{ messages.map( ( message, i ) => (
								<div
									key={ i }
									className={ `p-2 mb-2 text-sm max-w-[80%] mx-2 rounded-md ${ message.role === 'user' ? 'bg-blue-100 rounded-tr-none self-end' : 'bg-slate-200 rounded-tl-none self-start whitespace-pre-wrap' }` }
								>
									{ message.content }
								</div>
							) ) }
							{ loading && (
								<div className="self-start mx-2">
									<Loading onClick={ cancelLoading } />
								</div>
							) }
						</div>
						<form className="flex flex-row" onSubmit={ onSubmit } >
							<input
								className="flex-1 rounded-lg border-1 border-solid border-gray-200 p-2 mt-4 mx-2 focus:outline-none"
								disabled={ loading }
								placeholder="Ask me anything"
								type="text"
							/>
						</form>
					</div>
				</div>
			</PluginSidebar>
		</>
	);
}
