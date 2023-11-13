<?php

namespace AI\AWS_Rekognition;

use Aws\Rekognition\RekognitionClient;
use Exception;

/**
 * Get the celebrity names that appear in an image.
 *
 * @param string $image_data The raw image bytes
 * @throws Exception
 * @return string[] A list of celebrity names
 */
function get_celebrities_from_image_data( string $image_data ) : array {
	$api_key = get_option( 'aws_rekognition_api_key' );
	$api_secret = get_option( 'aws_rekognition_api_secret' );
	$api_region = get_option( 'aws_rekognition_region');

	if ( ! $api_key ) {
		throw new Exception( 'AWS Rekognition API Key not set' );
	}

	if ( ! $api_secret ) {
		throw new Exception( 'AWS Rekognition API Secret not set' );
	}

	if ( ! $api_region ) {
		throw new Exception( 'AWS Rekognition Region not set' );
	}

	$client = new RekognitionClient([
		'region' => $api_region,
		'version' => 'latest',
		'credentials' => [
			'key' => $api_key,
			'secret' => $api_secret,
		],
	]);

	$result = $client->recognizeCelebrities( [
		'Image' => [
			'Bytes' => $image_data,
		],
	] );

	$celebrities = [];
	if ( isset( $result['CelebrityFaces'] ) ) {
		foreach ( $result['CelebrityFaces'] as $celebrity ) {
			$celebrities[] = $celebrity['Name'];
		}
	}

	return $celebrities;
}
