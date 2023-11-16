import { serialize, Block } from '@wordpress/blocks';
import { useState } from '@wordpress/element';
import { select } from '@wordpress/data';

import { Message } from './types';
import { generateSummary } from './utils';

export default function useAiSummary() {
	const [ loading, setLoading ] = useState<boolean>( false );

	const submitPrompt = async ( prompt: string ) => {
		setLoading( true );

		const editorStore = select('core/editor');
		const postTitle = editorStore.getEditedPostAttribute('title');
		const postType = editorStore.getEditedPostAttribute('type');
		const postContent = editorStore.getBlocks<Block[]>().map( block => serialize( block ) ).join( '\n' );

		const messages: Message[] = [
			{
				role: 'user',
				content: prompt,
			}
		];

		try {
			const result = await generateSummary(
				messages,
				{
					title: postTitle,
					type: postType,
					content: postContent,
				}
			);
			setLoading( false );

			return result.content;
		}
		catch ( e ) {
			setLoading( false );
			throw e;
		}
	};

	return {
		loading,
		submitPrompt,
	};
}
