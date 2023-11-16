<?php

namespace AI\Semantic_Search;

use Ai\OpenAI;
use ElasticPress\Indexables;
use Exception;
use WP_Error;
use WP_Post;

const KEY_OPTION = 'ai-semanticsearch-key';

const EMBEDDING_FIELD = 'ai_embedding';
const EMBEDDING_MODEL = 'text-embedding-ada-002';
const EMBEDDING_DIMS = 1536;

function bootstrap() : void {
	add_filter( 'ep_post_mapping', add_embedding_field_mapping( ... ) );
	add_filter( 'ep_post_sync_args_post_prepare_meta', add_embedding( ... ), 10, 2 );

	if ( defined( 'WP_CLI' ) ) {
		\WP_CLI::add_command( 'ai semantic-search', CLI_Command::class );
	}
}

function add_embedding_field_mapping( $mapping ) {
	// Enable kNN indexes.
	$mapping['settings']['index.knn'] = true;

	// Add our altis_embedding field for the embedding data.
	$mapping['mappings']['properties'][ EMBEDDING_FIELD ] = [
		'type' => 'knn_vector',
		'dimension' => EMBEDDING_DIMS,
	];
	return $mapping;
}

function is_enabled_for_post( WP_Post $post ) {
	if ( $post->post_type !== 'post' && $post->post_type !== 'page' ) {
		return false;
	}

	if ( $post->post_status !== 'publish' ) {
		return false;
	}

	return true;
}

/**
 * Add our embedding field to post data.
 *
 * @param array $data Data for the post.
 * @param int $id ID for the post being indexed.
 * @return array Altered data.
 */
function add_embedding( array $data, int $id ) : array {
	$post = get_post( $id );
	if ( ! is_enabled_for_post( $post ) ) {
		return $data;
	}

	try {
		$embedding = get_post_embedding( $post );
	} catch ( Exception $e ) {
		trigger_error(
			sprintf(
				'Error embedding for post %d: %s',
				$id,
				$e->getMessage()
			),
			E_USER_WARNING
		);
		return $data;
	}

	$data[ EMBEDDING_FIELD ] = $embedding->embedding;
	return $data;
}

/**
 * Get the text to be used for the embedding generation.
 *
 * @param WP_Post $post Post to get embedding for.
 */
function get_post_input( WP_Post $post ) {
	// Convert post to a string.
	$content = wp_strip_all_tags( $post->post_content );
	$string = sprintf( "%s\n\n%s", $post->post_title, $content );

	return $string;
}

/**
 * Get the embedding for a post.
 *
 * Fetches the embedding
 *
 * @throws Exception
 * @param WP_Post $post Post to get embedding for.
 */
function get_post_embedding( WP_Post $post ) {
	return get_embedding( get_post_input( $post ) );
}

/**
 * Get the embedding for an arbitrary string.
 *
 * @param string $string Arbitrary string to get embedding for.
 */
function get_embedding( string $string ) : OpenAI\Embedding {
	$openai = OpenAI\Client::get_instance();
	$embeddings = $openai->get_embeddings( $string );
	return $embeddings[0];
}

function perform_raw_search( string $query, int $num = 3 ) {
	// First, generate an embedding for the query.
	$query_embed = get_embedding( $query );

	// Then, perform a kNN search using our mapping.
	/** @var \ElasticPress\Indexable\Post\Post */
	$indexable = Indexables::factory()->get( 'post' );
	$es_query = [
		'from' => 0,
		'size' => $num,
		'query' => [
			'knn' => [
				EMBEDDING_FIELD => [
					'vector' => $query_embed->embedding,
					'k' => $num,
				],
			],
		],
	];
	$res = $indexable->query_es( $es_query, [] );
	if ( $res === false ) {
		return new WP_Error(
			'altis.semanticsearch.search.es_error',
			'Unable to query Elasticsearch'
		);
	}
	return $res['documents'];
}
