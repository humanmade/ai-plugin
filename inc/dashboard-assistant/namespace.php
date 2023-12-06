<?php

namespace AI\Dashboard_Assistant;

use AI\OpenAI;
use Exception;
use WP_CLI;
use WP_Post;
use WP_User;

function bootstrap() : void {
	Admin\bootstrap();
	REST_API\bootstrap();

	if ( defined( 'WP_CLI' ) && WP_CLI ) {
		require_once __DIR__ . '/class-cli-command.php';
		WP_CLI::add_command( 'ai dashboard-assistant', __NAMESPACE__ . '\\CLI_Command' );
	}

	$assistant_id = get_option( 'ai_my_assistant_id' );

	if ( ! $assistant_id ) {
		$assistant = create_assisant();
	} else {
		$assistant = OpenAI\Client::get_instance()->get_assistant( get_option( 'ai_my_assistant_id' ) );
	}

	$assistant->register_function( OpenAI\Function_::from_callable( get_posts( ... ) ) );
	$assistant->register_function( OpenAI\Function_::from_callable( get_current_user( ... ) ) );
	$assistant->register_function( OpenAI\Function_::from_callable( update_user( ... ) ) );
	$assistant->register_function( OpenAI\Function_::from_callable( create_post( ... ) ) );
	$assistant->register_function( OpenAI\Function_::from_callable( create_attachment( ... ) ) );
	$assistant->register_function( OpenAI\Function_::from_callable( update_post( ... ) ) );
	$assistant->register_function( OpenAI\Function_::from_callable( generate_images( ... ) ) );
	$assistant->register_function( OpenAI\Function_::from_callable( get_weather( ... ) ) );

	OpenAI\Assistant::register( $assistant );
}

function create_assisant() : OpenAI\Assistant {
	$assistant = OpenAI\Client::get_instance()->create_assistant(
		model: 'gpt-4-1106-preview',
		name: 'WordPress Assistant',
		instructions: 'You are an assistant for the WordPress CMS admin interface. Users interactive with you to discuss content, publishing actions and site updates. You should perform actions asked by the user and respond to requests for information by using the available functions to get content. You should use code_interpreter to run functions and code that are not provided by user functions.',
	);

	update_option( 'ai_my_assistant_id', $assistant->id );

	return $assistant;
}

/**
 * Get the currently logged in user's details
 *
 * @return array|string
 */
function get_current_user() {
	if ( ! is_user_logged_in() ) {
		return 'not logged in';
	}
	$user = wp_get_current_user();
	return [
		'id' => $user->ID,
		'name' => $user->display_name,
	];
}

/**
 * Get the currently logged in user's details
 *
 * @param int $avatar_attachment_id The attachment ID of the avatar to set to the user.
 * @return array
 */
function update_user( int $user_id, string $name = null, int $avatar_attachment_id = null ) {
	if ( $avatar_attachment_id ) {
		global $simple_local_avatars;
		if ( $simple_local_avatars instanceof \Simple_Local_Avatars ) {
			$simple_local_avatars->assign_new_user_avatar( $avatar_attachment_id, $user_id );
		}
	}
	$user = new WP_User( $user_id );
	return [
		'id' => $user->ID,
		'name' => $user->display_name,
	];
}

/**
 * Get the posts on the WordPress site.
 *
 * @param string $search a search string for posts, can be natural language as this will be converted to embedding and searched using a vector database
 * @return array
 */
function get_posts( string $search = '', string $post_type = 'post', string $post_status = 'any' ) : array {
	$posts = \get_posts( [
		'post_status' => $post_status,
		'post_type' => $post_type,
		'posts_per_page' => 10,
		's' => $search,
	] );
	return array_map( prepare_post( ... ), $posts );
}

/**
 * Create a new post object. This can be used to create posts of all type like pages etc.
 *
 * @param string $post_content the post content as HTML. This can contain Gutenberg / Block Editor blocks markup.
 * @return array
 */
function create_post( string $post_type = 'post', string $post_title, string $post_content = '', string $post_status = 'draft', int $featured_image_attachment_id = null ) : array {
	$post_id = wp_insert_post( [
		'post_type' => $post_type,
		'post_content' => $post_content,
		'post_status' => $post_status,
		'post_title' => $post_title,
	], true );

	if ( $featured_image_attachment_id ) {
		set_post_thumbnail( $post_id, $featured_image_attachment_id );
	}

	if ( is_wp_error( $post_id ) ) {
		return [
			'error' => $post_id->get_error_message(),
		];
	}

	$post = get_post( $post_id );
	return prepare_post( $post );
}

/**
 * Update a post object. This can be used to create posts of all type like pages etc.
 *
 * @param string $post_content the post content as HTML. This can contain Gutenberg / Block Editor blocks markup.
 * @return array
 */
function update_post( int $post_id, string $post_title = null, string $post_content = null, string $post_status = null, int $featured_image_attachment_id = null  ) : array {
	$update = [
		'ID' => $post_id,
	];

	if ( $post_title ) {
		$update['post_title'] = $post_title;
	}

	if ( $post_content ) {
		$update['post_content'] = $post_content;
	}

	if ( $post_status ) {
		$update['post_status'] = $post_status;
	}

	$post_id = wp_update_post( $update, true );

	if ( $featured_image_attachment_id ) {
		set_post_thumbnail( $post_id, $featured_image_attachment_id );
	}

	if ( is_wp_error( $post_id ) ) {
		return [
			'error' => $post_id->get_error_message(),
		];
	}

	$post = get_post( $post_id );
	return prepare_post( $post );
}

function prepare_post( WP_Post $post ) : array {
	return [
		'id' => $post->ID,
		'title' => $post->post_title,
		'status' => $post->post_status,
		'date' => $post->post_date_gmt,
		'url' => get_permalink( $post ),
		'edit_link' => get_edit_post_link( $post ),
		'type' => $post->post_type,
		'content' => $post->post_content,
	];
}

/**
 * Generate images using AI for a given prompt.
 *
 * @return void
 */
function generate_images( string $prompt ) {
	try {
		$openai = OpenAI\Client::get_instance();
		$images = $openai->create_image_generation(
			prompt: $prompt,
			model: 'dall-e-3',
		);
	} catch ( Exception $e ) {
		return [
			'status' => 'error',
			'message' => $e->getMessage(),
		];
	}

	return $images;
}

/**
 * Get the weather today
 *
 */
function get_weather() {
	return '-15c';
}

/**
 * Save a media file (image, pdf etc) as an attachment in WordPress to be used / referenced elsewhere.
 */
function create_attachment( string $media_url ) {
	require_once ABSPATH . 'wp-admin/includes/media.php';
	require_once ABSPATH . 'wp-admin/includes/image.php';
	require_once ABSPATH . 'wp-admin/includes/file.php';
	$attachment_id = media_sideload_image( $media_url, 0, '', 'id' );
	if ( is_wp_error( $attachment_id ) ) {
		return [
			'error' => $attachment_id->get_error_message(),
		];
	}

	$post = get_post( $attachment_id );
	return [
		'url' => wp_get_attachment_url( $attachment_id ),
		'type' => $post->post_mime_type,
		'attachment_id' => $attachment_id,
	];
}
