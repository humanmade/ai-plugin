import { switchToBlockType } from '@wordpress/blocks';
import { BlockControls } from '@wordpress/block-editor';
import {
	MenuGroup,
	MenuItem,
	ToolbarDropdownMenu
} from '@wordpress/components';
import { useDispatch, useSelect } from '@wordpress/data';

import Icon from './icon';

function ToolbarButton() {
	const selectedBlocks = useSelect( ( select ) => {
		const { getSelectedBlockClientIds, getBlock } = select( 'core/block-editor' );
		return getSelectedBlockClientIds().map( getBlock );
	} );
	const {
		replaceBlocks,
		updateBlockAttributes,
	} = useDispatch( 'core/block-editor' );

	const onSubmitPrompt = async ( prompt ) => {
		if ( ! selectedBlocks.length === 0 ) {
			return;
		}
		if ( selectedBlocks.length === 1 && selectedBlocks[0]?.name === 'ai/ai' ) {
			const newAttributes = {
				...selectedBlocks[0].attributes,
				_prompt: prompt,
			};
			updateBlockAttributes( selectedBlocks[0].clientId, newAttributes );
		} else {
			const wrapped = switchToBlockType( selectedBlocks, 'ai/ai' );
			const aiBlock = wrapped[0];
			aiBlock.attributes._prompt = prompt;
			replaceBlocks( selectedBlocks.map( block => block.clientId ), [ aiBlock ] );
		}
	}

	return (
		<>
			<BlockControls
				group="other"
			>
				<ToolbarDropdownMenu
					icon={ Icon }
					label="Magicify"
				>
					{ () => (
						<>
							<MenuGroup>
								<MenuItem onClick={ () => onSubmitPrompt('Make this longer') }>
									Make Longer
								</MenuItem>
								<MenuItem onClick={ () => onSubmitPrompt('Make this shorter') }>
									Make Shorter
								</MenuItem>
								<MenuItem onClick={ () => onSubmitPrompt('Fix all the spelling mistakes.') }>
									Fix Spelling
								</MenuItem>
								<MenuItem onClick={ () => onSubmitPrompt('Summarize this.') }>
									Summarize
								</MenuItem>
							</MenuGroup>
							<MenuGroup>
								{ /* <span className="py-2 mt-4 px-2 text-xs uppercase text-gray-400 font-semibold">Change Tone</span> */ }
								<MenuItem onClick={ () => onSubmitPrompt('Change the tone to be more professional.') }>
									Professional
								</MenuItem>
								<MenuItem onClick={ () => onSubmitPrompt('Change the tone to be more informal.') }>
									Informal
								</MenuItem>
								<MenuItem onClick={ () => onSubmitPrompt('Change the tone to be more kind and nice.') }>
									Kind
								</MenuItem>
							</MenuGroup>
						</>
					) }
				</ToolbarDropdownMenu>
			</BlockControls>
		</>
	);
};

export default function ToolbarButtonWrap( BlockEdit ) {
	return props => {

		// Get the parent blocks using the getBlock function. We don't want to show the toolbar button if the parent is an ai/ai block.
		// eslint-disable-next-line react-hooks/rules-of-hooks
		const blockParents = useSelect( ( select ) => {
			const { getBlockParents, getBlock } = select('core/block-editor');
			return getBlockParents( props.clientId ).map( getBlock );
		}, [ props.clientId ] );
		// eslint-disable-next-line react-hooks/rules-of-hooks
		const innerBlocks = useSelect( select => {
			return select('core/block-editor').getBlocksByClientId(props.clientId)?.[0]?.innerBlocks;
		}, [ props.clientId ] );

		const isAiBlockWithNoInnerBlocks = props.name === 'ai/ai' && innerBlocks.length === 0;
		if ( props.name === 'core/image' || blockParents.filter( block => block.name === 'ai/ai' ).length > 0 || isAiBlockWithNoInnerBlocks ) {
			return (
				<BlockEdit
					key="edit"
					{ ...props }
				/>
			);
		}

		return (
			<>
				<ToolbarButton
					{ ...props }
				/>
				<BlockEdit
					key="edit"
					{ ...props }
				/>
			</>
		);
	};
};
