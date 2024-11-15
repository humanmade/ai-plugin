<?php

namespace AI\Dashboard_Assistant\Functions;

use AI\OpenAI;
use AI\OpenAI\Assistant;
use AI\OpenAI\Function_;
use Exception;
use WP_Post;
use WP_User;

function bootstrap() : void {
	add_action( 'dashboard_assistant_init', register_functions( ... ) );
}

function register_functions( Assistant $assistant ) {
	$assistant->register_function( Function_::from_callable( get_posts( ... ) ) );
	$assistant->register_function( Function_::from_callable( get_current_user( ... ) ) );
	$assistant->register_function( Function_::from_callable( update_user( ... ) ) );
	$assistant->register_function( Function_::from_callable( create_post( ... ) ) );
	$assistant->register_function( Function_::from_callable( create_attachment( ... ) ) );
	$assistant->register_function( Function_::from_callable( update_post( ... ) ) );
	$assistant->register_function( Function_::from_callable( generate_images( ... ) ) );
	$assistant->register_function( Function_::from_callable( get_enabled_plugins( ... ) ) );
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
 * @return array
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

/**
 * Get directions on a map from one place to another.
 *
 * @param string $from The location you are coming from.
 * @param string $to The location you are going to
 * @return string
 */
function get_directions( string $from, string $to ) : string {
	return '<iframe
	width="600"
	height="450"
	style="border:0"
	loading="lazy"
	allowfullscreen
	referrerpolicy="no-referrer-when-downgrade"
	src="https://www.google.com/maps/embed/v1/place?key=AIzaSyAin2XjjvdC6vGX9wzkkhZx5WUOsRG4ISU&q=Space+Needle,Seattle+WA">
  </iframe>';
}

/**
 * Get the plugins that are enabled on the site.
 *
 * @return array
 */
function get_enabled_plugins() : array {
	require_once ABSPATH . 'wp-admin/includes/plugin.php';
	$plugins = array_merge( get_plugins(), get_mu_plugins() );
	$response = [];
	foreach ( $plugins as $plugin ) {
		$response[] = [
			'name' => $plugin['Name'],
			'uri' => $plugin['PluginURI'],
			'version' => $plugin['Version'],
			'author' => $plugin['Author'],
			'description' => $plugin['Description'],
		];
	}
	return $response;
}
