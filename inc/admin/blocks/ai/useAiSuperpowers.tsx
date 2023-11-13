/// <reference path="typing.d.ts" />
import { parse, serialize, getBlockTypes, Block } from '@wordpress/blocks';
import { useDispatch, useSelect, select } from '@wordpress/data';
import { useState } from '@wordpress/element';

import { Message } from './types';
import { fixBlocks, generateBlocks } from './utils';

export default function useAiSuperpowers( blockId: string ) {
	const innerBlocks: Block[] = useSelect( select => {
		return select('core/block-editor').getBlocksByClientId<Block[]>( blockId )?.[0]?.innerBlocks;
	}, [] );

	const {
		replaceInnerBlocks,
		replaceBlocks,
		removeBlock,
		insertBlocks,
		updateBlockAttributes,
		__unstableMarkNextChangeAsNotPersistent,
	} = useDispatch( 'core/block-editor' );

	// We use a store for the messages history as useState() doesn't seem
	// to persistent very well. The component is unmounted / mounted when
	// things like updateBlock are called.
	const messages = useSelect<Message[]>( ( select ) => {
		return select( 'ai/ai-store' ).getMessages( blockId );
	}, [] );
	const { setMessages } = useDispatch( 'ai/ai-store' );

	const [ loading, setLoading ] = useState( false );
	const [ loadingAbortController, setLoadingAbortController ] = useState<AbortController | null>(null);
	const [ error, setError ] = useState<string | null>( null );

	async function cancelLoading() {
		if (loadingAbortController) {
			try {
				loadingAbortController.abort();
			} catch ( e ) {
				// no op.
			}
		}
	}
	async function submitPrompt( prompt: string ) {
		setError( null );
		setLoading( true );
		const loadingAbortController = new AbortController();
		setLoadingAbortController(loadingAbortController);

		// If there are inner blocks, we likely want to remove the last message on the messages array else there'll
		// be a duplication of blocks. This is because when the assistant responds we add it to the end of the messages
		// array, but when we submit a prompt we want to provide the new content.
		if ( messages[ messages.length - 1 ]?.role === 'assistant' ) {
			messages.pop();
		}

		const requestMessages = [ ...messages ];

		if ( innerBlocks?.length > 0 ) {
			requestMessages.push({
				role: 'assistant',
				content: serialize(innerBlocks),
			});
		}
		const message = {
			role: 'user',
			content: prompt,
		};

		messages.push(message);
		requestMessages.push(message);
		setMessages( blockId, messages );

		const editorStore = select('core/editor');
		const postTitle = editorStore.getEditedPostAttribute('title');
		const postType = editorStore.getEditedPostAttribute('type');
		const postContent = editorStore.getBlocks<Block[]>().map( block  => {
			if ( block.clientId === blockId ) {
				return `{{selected_block_placeholder}}`;
			}
			const serializedBlock = serialize(block);
			return serializedBlock;
		} ).join('\n');

		try {

			let streamedBlocks: Block[] = [];
			const parser = await generateBlocks(
				requestMessages,
				{
					title: postTitle,
					type: postType,
					content: postContent,
				},
				getBlockTypes().map( block => block.name ),
				loadingAbortController.signal
			);

			let updatingPartial = false;
			let updatingBlock: Block | null = null;

			for await ( const block of parser.parse() ) {
				if ( typeof block.partialContent !== "undefined" ) {
					const CONTENT_BLOCKS = [
						'core/paragraph',
						'core/heading',
						'core/verse',
						'core/preformatted',
					];
					const STREAMABLE_BLOCKS = [
						'core/list',
						'core/table',
						'core/quote',
					];
					if ( CONTENT_BLOCKS.indexOf( block.name ) > -1 ) {
						if ( updatingPartial === false ) {
							if ( streamedBlocks.length === 0 ) {
								replaceInnerBlocks( blockId, [], false );
							}
							streamedBlocks = streamedBlocks.concat( block );
							insertBlocks( block, streamedBlocks.length - 1, blockId, false );
							updatingPartial = true;
							updatingBlock = block;
						}
						__unstableMarkNextChangeAsNotPersistent();
						updateBlockAttributes( block.clientId, { content: block.partialContent.trim().replace(/<\/?p>/, '') } );
					} else if ( STREAMABLE_BLOCKS.indexOf( block.name ) > -1 ) {
						if ( updatingPartial === false ) {
							if ( streamedBlocks.length === 0 ) {
								replaceInnerBlocks( blockId, [], false );
							}
							streamedBlocks = streamedBlocks.concat( block );
							insertBlocks( block, streamedBlocks.length - 1, blockId, false );
							updatingPartial = true;
							updatingBlock = block;
						}
						let newBlocks = fixBlocks( parse( parser.currentContent, {
							__unstableSkipMigrationLogs: true,
						} ) );
						// Parsing the blocks can be quite iffy, it can return multiple blocks even though we're processing
						// one parent block. We try to select the most appropriate to replace based off looking for a block
						// matching the name of the block we're updating.
						newBlocks = newBlocks.sort( ( a, b ) => a.name === updatingBlock?.name ? -1 : 1 );
						__unstableMarkNextChangeAsNotPersistent();
						await replaceBlocks( updatingBlock?.clientId, newBlocks[0] );
						updatingBlock = newBlocks[0];
					}
					continue;
				} else {
					const blockToInsert = fixBlocks( [ block ] );
					if ( updatingBlock ) {
						removeBlock( updatingBlock.clientId, false );
					}
					updatingPartial = false;
					updatingBlock = null;
					if ( streamedBlocks.length === 0 ) {
						replaceInnerBlocks( blockId, [], false);
					}
					streamedBlocks = streamedBlocks.concat( blockToInsert);
					insertBlocks( blockToInsert, streamedBlocks.length - 1, blockId, false );
				}
			}
			// Add the message to the messages array so it's part of the history.
			messages.push( {
				role: 'assistant',
				content: parser.data,
			} );
			// Strip anything from the start of the data that isn't a block. GPT has a tendency to say "Sure, here it is" etc.
			let data = parser.data
			if ( data.indexOf( '<' ) > -1 ) {
				data = data.substring( data.indexOf( '<' ) );
			}
			let blockToInsert = parse( data, {
				__unstableSkipMigrationLogs: true,
			} );

			blockToInsert = fixBlocks(blockToInsert);
			replaceInnerBlocks( blockId, blockToInsert, false );

		} catch ( e ) {
			if ( ( e as Error ).message ) {
				setError( ( e as Error ).message );
			}
			throw e;
		} finally {
			setLoading(false);
		}
	}

	return {
		error,
		loading,
		messages,
		cancelLoading,
		submitPrompt,
	};
}
