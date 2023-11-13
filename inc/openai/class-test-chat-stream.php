<?php

namespace AI\OpenAI;

use IteratorAggregate;
use Generator;

class Test_Chat_Stream implements IteratorAggregate {
	public function __construct(
		private readonly Chat $chat,
	) {

	}

	public function getIterator(): Generator {
		$content = $this->chat->choices[0]->message->content;

		foreach ( str_split( $content, 8 ) as $delta ) {
			$chat = clone( $this->chat );
			usleep(10000);
			$chat->choices[0]->message->content = $delta;
			yield $chat;
		}
	}
}
