<?php

namespace AI\Image_Editor;

use Imagick;
use WP_Error;
use WP_Image_Editor_Imagick;

class Image_Editor_Imagick extends WP_Image_Editor_Imagick {
	public function resize( $max_w, $max_h, $crop = false ) {
		$trace = debug_backtrace( false );
		if ( $trace[2]['function'] !== 'wp_ajax_image_editor' ) {
			return parent::resize( $max_w, $max_h, $crop );
		}

		if ( isset( $_REQUEST['upscale'] ) ) {
			$client = \AI\Segmind\Client::get_instance();
			$data = $client->esrgan( $this->image->getImageBlob(), (int) $_REQUEST['upscale'] );
			$image = new Imagick();
			$image->readImageBlob( $data );
			$this->set_quality( 100 );
			$this->image->setImage( $image );
		}

		if ( isset( $_REQUEST['remove_background'] ) ) {
			$client = \AI\Segmind\Client::get_instance();
			$data = $client->background_removal( $this->image->getImageBlob() );
			$image = new Imagick();
			$image->readImageBlob( $data );
			$this->set_quality( 100 );
			$this->image->setImage( $image );
		}

		return $this->update_size();
	}
}
