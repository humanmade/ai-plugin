<?php

namespace AI\DreamStudio;

use AI\OpenAI;
use WP_CLI;

class CLI_Command {
	/**
	 * Inpaint an image with a mask and prompt
	 *
	 * This will generate the embeddings. If you want to get the embeddings for a post that has already been generated, use `get-saved-embeddings`.
	 *
	 * @subcommand inpaint
	 *
	 * ## OPTIONS
	 *
	 * <image>
	 * : Path to the image
	 *
	 * <mask>
	 * : Path to the mask image
	 *
	 * <prompt>
	 * : The prompt
	 *
	 * <output>
	 * : The file path to output the new image
	 *
	 */
	public function inpaint( $args ) {
		$client = Client::get_instance();
		$images = $client->inpainting( file_get_contents( $args[0] ), file_get_contents( $args[1] ), $args[2] );
		foreach ( $images as $image ) {
			$image = base64_decode( $image['base64'] );
			file_put_contents( $args[3], $image );
		}
	}
}
