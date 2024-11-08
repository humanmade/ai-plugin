import React from 'react';
import Markdown from 'react-markdown';
import Loading from '../../../src/loading';
import type { OpenAI } from 'openai';

const roleNameMap = {
	assistant: 'WordPress Assistant',
	user: 'You',
}

export default function MessageComponent( { message }: { message: OpenAI.Beta.Threads.Message } ) {
	return (
		<div className="flex flex-col my-2">
			<div className="font-bold text-[16px]">{ roleNameMap[ message.role ] }</div>
			{ message.content.map( ( content, i ) => (
				<div key={ i }>
					{ content.type === 'text' && <Text content={ content } /> }
					{ content.type === 'image_file' && <Image content={ content } /> }
				</div>
			) ) }
			{ message.content.length === 0 && <div className="my-4"><Loading /></div> }
		</div>
	)
}

function Text( { content }: { content: OpenAI.Beta.Threads.Message['content'][0] } ) {
	return (
		<div className="text-lg"><Markdown className="markdown">{ content.type === 'text' ? content.text.value : '' }</Markdown></div>
	)
}

function Image( { content }: { content:  OpenAI.Beta.Threads.ImageFileContentBlock} ) {
	// Image data is ?PNG\r\n\u001a\n\u0000\u0000\u0000.....
	// Construct an image SRC from the data in content.image_file.content

	return <img src={ `/?rest_route=/ai/v1/files/${ content.image_file.file_id}` } className="max-w-xl my-4" />
}
