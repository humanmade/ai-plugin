import { createBlock, cloneBlock } from '@wordpress/blocks';
import { useDispatch, useSelect, select } from '@wordpress/data';
import { useEffect } from '@wordpress/element';
import { useKeyboardShortcut } from '@wordpress/compose';

import Excerpt from './excerpt';
import YoastPlugin from './yoast';
import Chat from './chat';

export default function AIPlugin () {
	const { replaceBlock, replaceBlocks } = useDispatch( 'core/block-editor' );
	const { selectedBlock } = useSelect( select => {
		const { getSelectedBlockClientId, getBlock } = select( 'core/block-editor' );
		const clientId = getSelectedBlockClientId();
		return {
			selectedBlock: getBlock( clientId ),
		};
	}, [] );

	useEffect( () => {
		if ( ! selectedBlock ) {
			return;
		}
		if ( selectedBlock.name === 'core/paragraph' && selectedBlock.attributes.content === '~' ) {
			const newBlock = createBlock( 'ai/ai', {} );
			replaceBlock( selectedBlock.clientId, newBlock );
		}
	}, [ selectedBlock, replaceBlock ] );

	useKeyboardShortcut( '~', () => {
		const { getBlock, getSelectedBlockClientIds } = select( 'core/block-editor' );
		const blocks = getSelectedBlockClientIds();
		const selectedBlocks = blocks.map( getBlock ).map( b => cloneBlock( b ) );
		const newBlock = createBlock( 'ai/ai', {}, selectedBlocks );
		replaceBlocks( blocks, newBlock );
	} );

	return (
		<>
			<Excerpt />
			<YoastPlugin />
			<Chat />
		</>
	);
}
