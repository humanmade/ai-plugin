export interface Message {
	id: string,
	content: [{
		type: 'text',
		text: {
			value: string,
			annotations: {
				type: string,
				text: string,
			}[]
		}
	} | {
		type: 'image_file',
		image_file: {
			file_id: string,
		}
	}],
	role: 'assistant' | 'user',
	_message_type?: 'message',
}

export interface Step {
	id: string,
	status: string,
	step_details: {
		type: 'message_creation' | 'tool_calls',
		message_creation?: {
			message_id: string,
		},
		tool_calls?: {
			id: string,
			type: 'function' | 'code_interpreter'
			function?: {
				name: string,
				arguments: string,
				output?: string,
			}
		}[]
	}
	_message_type?: 'step',
}

export type Event = Message | Step;
