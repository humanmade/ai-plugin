<?php

namespace AI;

use WP_CLI;
use WP_Post;

function bootstrap() : void {
	ini_set( 'display_errors', 'on' );
	if ( defined( 'WP_CLI' ) && WP_CLI ) {
		require_once __DIR__ . '/class-cli-command.php';
		WP_CLI::add_command( 'ai', __NAMESPACE__ . '\\CLI_Command' );
	}
	Admin\bootstrap();
	REST_API\bootstrap();

	$assistant_id = get_option( 'ai_my_assistant_id' );
	if ( $assistant_id ) {
		$assistant = OpenAI\HTTP_Client::get_instance()->get_assistant( get_option( 'ai_my_assistant_id' ) );

		$assistant->register_function( OpenAI\Function_::from_callable( get_posts( ... ) ) );
		$assistant->register_function( OpenAI\Function_::from_callable( get_current_user( ... ) ) );
		$assistant->register_function( OpenAI\Function_::from_callable( create_post( ... ) ) );
		$assistant->register_function( OpenAI\Function_::from_callable( update_post( ... ) ) );

		OpenAI\Assistant::register( $assistant );
	}
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
function create_post( string $post_type = 'post', string $post_title, string $post_content = '', string $post_status = 'draft' ) : array {
	$post_id = wp_insert_post( [
		'post_type' => $post_type,
		'post_content' => $post_content,
		'post_status' => $post_status,
		'post_title' => $post_title,
	], true );

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
function update_post( int $post_id, string $post_title = null, string $post_content = null, string $post_status = null ) : array {
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
