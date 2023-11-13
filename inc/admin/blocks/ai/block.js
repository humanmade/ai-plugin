import { registerBlockType, createBlock } from '@wordpress/blocks';
import { addFilter } from '@wordpress/hooks';
import { InnerBlocks } from '@wordpress/block-editor';
import { registerPlugin } from '@wordpress/plugins';
import { registerStore } from '@wordpress/data';

import AIPlugin from './plugin';
import Edit from './edit';
import Icon from './icon';
import ToolbarButton from './toolbar-button';
import useAiSummary from './useAiSummary';
import useAiSuperpowers from './useAiSuperpowers';

// Expose for use in other plugins.
window.AltisAi = {
	useAiSummary,
	useAiSuperpowers,
};

registerPlugin('ai', {
	render: AIPlugin,
} );

addFilter( 'editor.BlockEdit', 'ai/ai', ToolbarButton );

registerBlockType('ai/ai', {
	title: 'AI Generation',
	transforms: {
		from: [
			{
				type: 'block',
				blocks: ['*'],
				// This is how core/group works.. due to the fact that the usual transformed doesn't
				// get access to the blocks, just attributes.
				__experimentalConvert(blocks) {
					const alignments = ['wide', 'full'];

					// Determine the widest setting of all the blocks to be grouped
					const widestAlignment = blocks.reduce((accumulator, block) => {
						const { align } = block.attributes;
						return alignments.indexOf(align) > alignments.indexOf(accumulator) ? align : accumulator;
					}, undefined);

					// Clone the Blocks to be Grouped
					// Failing to create new block references causes the original blocks
					// to be replaced in the switchToBlockType call thereby meaning they
					// are removed both from their original location and within the
					// new group block.
					const groupInnerBlocks = blocks.map((block) => {
						return createBlock(block.name, block.attributes, block.innerBlocks);
					});

					return createBlock(
						'ai/ai',
						{
							align: widestAlignment,
							layout: { type: 'constrained' },
						},
						groupInnerBlocks
					);
				},
				isMultiBlock: true,
				isMatch: ( attributes, blocks ) => {
					// Remove any blocks that we can't transform to.
					blocks = blocks.filter( block => {
						if ( [ 'ai/ai' ].indexOf( block.name ) > -1 ) {
							return false;
						}
						return true;
					} );
					return blocks.length > 0;
				},
			},
		],
	},
	icon: Icon,
	edit: Edit,
	save () {
		return (
			<InnerBlocks.Content />
		);
	},
});

const DEFAULT_STATE = {

};

const actions = {
	setMessages( clientId, messages ) {
		return {
			type: 'UPDATE_MESSAGES',
			clientId,
			messages,
		};
	},
};

const reducer = ( state = DEFAULT_STATE, action ) => {
	switch ( action.type ) {
		case 'UPDATE_MESSAGES':
			return {
				...state,
				[ action.clientId ]: {
					...state[ action.clientId ],
					messages: action.messages,
				},
			};
		default:
			return state;
	}
};

const selectors = {
	getMessages( state, clientId ) {
		return state[ clientId ]?.messages || [];
	},
};

registerStore( 'ai/ai-store', {
	reducer,
	actions,
	selectors,
});
