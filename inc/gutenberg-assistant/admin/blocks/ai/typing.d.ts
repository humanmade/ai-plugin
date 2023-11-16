declare module '@wordpress/blocks' {
	interface Block {
		attributes: {
			[ k: string ]: any,
		},
		clientId: string,
		innerBlocks: Block[],
		isValid: boolean,
		name: string,
		originalContent: string,
		partialContent?: string,
	}
	interface BlockType {
		name: string,
		icon: any,
		keywords: any[],
		attributes: {
			[ k: string ]: any,
		},
		providesContext: object,
		usesContext: any[],
		selectors: object,
		supports: object,
		styles: any[],
		variations: any[],
		save(): null,

		[ k: string ]: any,
	}

	interface ParseOptions {
		__unstableSkipMigrationLogs?: boolean,
		__unstableSkipAutop?: boolean,
	}

	/**
	 * Utilizes an optimized token-driven parser based on the Gutenberg grammar spec
	 * defined through a parsing expression grammar to take advantage of the regular
	 * cadence provided by block delimiters -- composed syntactically through HTML
	 * comments -- which, given a general HTML document as an input, returns a block
	 * list array representation.
	 *
	 * This is a recursive-descent parser that scans linearly once through the input
	 * document. Instead of directly recursing it utilizes a trampoline mechanism to
	 * prevent stack overflow. This initial pass is mainly interested in separating
	 * and isolating the blocks serialized in the document and manifestly not in the
	 * content within the blocks.
	 */
	function parse( content: string, options?: ParseOptions ) : Block[];

	interface SerializeOptions {
		isInnerBlocks?: boolean,
	}

	/**
	 * Takes a block or set of blocks and returns the serialized post content.
	 */
	function serialize( blocks: Block | Block[], options?: SerializeOptions ): string;

	/**
	 * Returns all registered blocks.
	 */
	function getBlockTypes(): BlockType[];

	/**
	 * Returns a block object given its type and attributes.
	 */
	function createBlock( name: String, attributes?: Block['attributes'], innerBlocks?: Block[] | null ): Block;

	/**
	 * Converts an HTML string to known blocks.
	 */
	function rawHandler( options: { HTML: string; } ): Block[];
}

interface Window {
	AIBlock: {
		nonce: string,
		root: string,
	}
}
