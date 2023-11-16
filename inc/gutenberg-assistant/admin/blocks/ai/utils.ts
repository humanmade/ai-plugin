import { parse, createBlock, rawHandler, Block } from '@wordpress/blocks';
import apiFetch from '@wordpress/api-fetch';
import { Message } from './types';

export async function apiFetchRaw(input: RequestInfo | URL, init: RequestInit) : Promise<Response> {
	init.headers = {
		...init.headers,
		['X-WP-Nonce']: window.AIBlock.nonce
	};
	const response = await fetch(input, init);
	return response;
}

export async function generateBlocks(
	requestMessages: Message[],
	post: any,
	availableBlocks: string[],
	signal?: AbortSignal | null
) {
	const response = await apiFetchRaw( `${ window.AIBlock.root }ai/v1/insert`, {
		headers: {
			'Content-Type': 'application/json',
			Accept: 'text/event-stream',
		},
		body: JSON.stringify( {
			messages: requestMessages,
			stream: true,
			post,
			available_blocks: availableBlocks,
		} ),
		method: 'POST',
		signal,
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
	const parser = new ResponseParser( response );
	return parser;
}

export async function generateSummary(
	requestMessages: Message[],
	post: any,
	signal?: AbortSignal | null
) {
	const response = await apiFetch<{
		role: string,
		content: string,
	}>( {
		path: '/?rest_route=/ai/v1/summarize',
		headers: {
			'Content-Type': 'application/json',
			Accept: 'text/event-stream',
		},
		data: {
			messages: requestMessages,
			post,
		},
		method: 'POST',
		signal,
	} );

	return response;
}

export async function chat(
	messages: Message[],
	post: any,
	signal?: AbortSignal | null
) {
	const response = await apiFetchRaw( `${ window.AIBlock.root }ai/v1/chat`, {
		headers: {
			'Content-Type': 'application/json',
			Accept: 'text/event-stream',
		},
		body: JSON.stringify({
			messages,
			stream: true,
			post,
		} ),
		method: 'POST',
		signal,
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

export async function *streamResponse(response: Response) {
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
			if ( line.startsWith( 'data:' ) ) {
				// Extract the JSON data from the line.
				const data = JSON.parse( line.slice( 5 ) );
				data._message_type = type;
				yield data;
			} else if ( line === '' && index === lines.length - 1 ) {
				// If the last line is empty, reset the chunk.
			}
		}

		if ( done ) {
			break;
		}
	}
}

export function fixBlocks( blocks: Block[] ) {
	let fixedBlocks: Block[] = [];

	for ( let block of blocks ) {
		if ( block.innerBlocks ) {
			block.innerBlocks = fixBlocks( block.innerBlocks );
		}
		if ( block.name === 'core/freeform' ) {
			let convertedBlocks = rawHandler( { HTML: block.attributes.content });
			if ( convertedBlocks.length === 1 && convertedBlocks[0].name === 'core/freeform' ) {
				// no-op.
			} else {
				convertedBlocks = fixBlocks( convertedBlocks );
			}

			fixedBlocks = fixedBlocks.concat( convertedBlocks );
			continue;
		}
		if (!block.isValid) {
			block = createBlock( block.name, block.attributes, block.innerBlocks );
		}

		fixedBlocks.push( block );
	}
	return fixedBlocks;
}

export class ResponseParser {
	buffer = '';
	data = '';
	startDelimiter = '<!-- wp:';
	endDelimiterRegex = /<!-- \/wp:([a-z0-9-]+\/?[a-z0-9-]+) -->/i;
	startDelimiterRegex = /<!-- wp:(([a-z0-9-]+\/)?[a-z0-9-]+)(.*) -->/i;
	currentBlock: Block | null = null;
	currentContent = '';
	response: Response | null = null;
	allowedPartialBlocks = [
		'core/paragraph',
		'core/list',
		'core/table',
	];
	constructor( response: Response | null ) {
		this.response = response;
	}

	async *parse() {
		if ( ! this.response ) {
			return;
		}

		const reader = this.response.body!.getReader();
		let buffer: string = '';

		while ( true ) {
			const { value, done } = await reader.read();

			// Convert the chunk to a string.
			const chunk = new TextDecoder( 'utf-8' ).decode( value );

			// Split the chunk by line.
			buffer += chunk;
			const lines = buffer.split( '\n' );
			buffer = lines.pop() || '';

			for ( let index = 0; index < lines.length; index++ ) {
				const line = lines[ index ];
				if ( line.startsWith( 'data:' ) ) {
					// Extract the JSON data from the line.
					const data = JSON.parse( line.slice( 5 ) );
					this.buffer += data.content;
					this.data += data.content;
				} else if (line === '' && index === lines.length - 1) {
					// If the last line is empty, reset the chunk.
				}
			}

			while ( true ) {
				const block = this.extractNextBlock();

				if ( this.currentBlock && block ) {
					yield block;
					break;
				}

				if ( ! block ) {
					break;
				}

				yield block;
			}

			if ( done ) {
				break;
			}
		}

		const remainingContent = this.buffer.trim();

		if ( remainingContent ) {
			yield createBlock( 'core/freeform', { content: remainingContent } );
		}
	}

	extractNextBlock() {
		const startMatch = this.startDelimiterRegex.exec( this.buffer );
		if ( startMatch === null ) {
			return null;
		}
		const start = startMatch.index;

		let end = start;
		let nesting = 0;
		let foundCompleted = false;
		const blockName = ! startMatch[2] ? 'core/' + startMatch[1] : startMatch[1];
		if ( ! this.currentBlock ) {
			try {
				this.currentBlock = createBlock( blockName );
			} catch ( e ) {
				console.log( blockName )
				return null;
			}
		}

		// eslint-disable-next-line no-constant-condition
		while ( true ) {
			const nextStart = this.buffer.indexOf( this.startDelimiter, end + this.startDelimiter.length );
			const nextEndMatch = this.endDelimiterRegex.exec( this.buffer.slice( end + this.startDelimiter.length ) );
			const nextEnd = nextEndMatch ? end + nextEndMatch.index + nextEndMatch[0].length : -1

			if ( nextEnd === -1 ) {
				break;
			}

			if ( nextStart > -1 && nextStart < nextEnd ) {
				nesting++;
				end = nextStart;
			} else if ( nextEnd > -1 ) {
					if ( nesting === 0 ) {
						foundCompleted = true;
						end = nextEnd;
						break;
					}

					nesting--;
					end = nextEnd;
				}
		}

		if ( ! foundCompleted ) {
			// update the current block
			if ( this.currentBlock ) {
				this.currentBlock.partialContent = this.buffer.substring( startMatch.index + startMatch[0].length );
				this.currentContent = this.buffer.substring( startMatch.index );
				return this.currentBlock;
			}
			return null;
		}

		this.currentBlock = null;

		const blockString = this.buffer.substring( start, end + this.startDelimiter.length );
		this.buffer = this.buffer.substring( end + this.startDelimiter.length );
		const blocks = parse( blockString )[0];
		return blocks;
	}
}
