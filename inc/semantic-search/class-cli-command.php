<?php

namespace AI\Semantic_Search;

use AI\OpenAI;
use WP_CLI;

class CLI_Command {
	/**
	 * Get embeddings for a given post. Will display the post text and the embeddings.
	 *
	 * This will generate the embeddings. If you want to get the embeddings for a post that has already been generated, use `get-saved-embeddings`.
	 *
	 * @subcommand get-post-embeddings
	 *
	 * ## OPTIONS
	 *
	 * <post-id>
	 * : The ID of the post
	 *
	 */
	public function get_post_embedding( $args ) {
		$openai = OpenAI\Client::get_instance();
		$post = get_post( $args[0] );
		$input = get_post_input( $post );
		$embedding = get_post_embedding( $post );

		WP_CLI::line( 'Text used for embedding:' );
		echo $input . "\n\n";

		WP_CLI::line( 'Embedding:' );
		print_r( $embedding );
	}
}
