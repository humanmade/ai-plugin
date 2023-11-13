<?php

namespace AI\OpenAI;

use DateTimeImmutable;
use IteratorAggregate;
use Generator;
use OpenAI\Responses\StreamResponse;
use OpenAI\Responses\Chat\CreateStreamedResponseChoice;

class Chat_Stream implements IteratorAggregate {
	public function __construct(
		private readonly StreamResponse $response,
	) {

	}

	public function getIterator(): Generator {
		foreach ( $this->response as $item ) {
			if ( empty( $item->choices ) ) {
				continue;
			}
			if ( $item->choices[0]->finishReason === "stop" ) {
				break;
			}

			$chat = new Chat(
				created: DateTimeImmutable::createFromFormat( 'U', $item->created ),
				choices: array_map( function( CreateStreamedResponseChoice $choice ) : Chat_Choice {
					return new Chat_Choice(
						message: new Message(
							content: $choice->delta->content ?: "",
							role: $choice->delta->role ?: "assistant",
						),
						index: $choice->index,
					);
				}, $item->choices ),
				usage: new Usage( 0, 0, 0 ),
				model: $item->model,
			);
			yield $chat;
		}
	}
}
