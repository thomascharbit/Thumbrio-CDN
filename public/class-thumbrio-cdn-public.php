<?php

/**
 * The public-facing functionality of the plugin.
 *
 * @link       http://freshflesh.fr
 * @since      1.0.0
 *
 * @package    Thumbrio_Cdn
 * @subpackage Thumbrio_Cdn/public
 */



/**
 * The public-facing functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the admin-specific stylesheet and JavaScript.
 *
 * @package    Thumbrio_Cdn
 * @subpackage Thumbrio_Cdn/public
 * @author     Thomas Charbit <thomas.charbit@gmail.com>
 */
class Thumbrio_Cdn_Public {

	const THUMBRIO_BASE_URL_HTTP  = 'http://api.thumbr.io/';
	const THUMBRIO_BASE_URL_HTTPS = 'https://api.thumbr.io/';

	/**
	 * The ID of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $plugin_name    The ID of this plugin.
	 */
	private $plugin_name;

	/**
	 * The version of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $version    The current version of this plugin.
	 */
	private $version;

	/**
	 * Initialize the class and set its properties.
	 *
	 * @since    1.0.0
	 * @param      string    $plugin_name       The name of the plugin.
	 * @param      string    $version    The version of this plugin.
	 */
	public function __construct( $plugin_name, $version ) {

		$this->plugin_name = $plugin_name;
		$this->version = $version;

	}

	/**
	 * Description TODO
	 *
	 * @since    1.0.0
	 */
	public function image_downsize( $downsize, $id, $size ) {

		if ( !getenv('THUMBRIO_API_KEY') || !getenv('THUMBRIO_SECRET_KEY'))  return false;

		$img_url = $this->get_image_source( $id, $size );

		$img_size = $this->get_image_size( $size );
		
		// Can't find image size :(
		if ( $img_size === false ) return false;


		extract( $img_size );

		$dimensions = "{$width}x{$height}";

		// Add smart crop flag if needed
		if ( $crop ) $dimensions .= 'c';

		$query_args = ( is_array($size) && isset($size[3]) ) ? http_build_query( $size[3] ) : null;

		$new_url = $this->get_thumbrio_url( $img_url, $dimensions, $query_args, getenv('THUMBRIO_CNAME') );
		
		return array( $new_url, $width, $height, true );

	}

	protected function get_image_size( $size ) {
		global $_wp_additional_image_sizes;

		$max_width = $max_height = 0;

		if ( is_array($size) ) {
			$max_width = intval($size[0]);
			$max_height = intval($size[1]);
			$crop = isset($size[2]) ? (bool) $size[2] : true;
		}
		elseif ( $size == 'thumb' || $size == 'thumbnail' ) {
			$max_width = intval(get_option('thumbnail_size_w'));
			$max_height = intval(get_option('thumbnail_size_h'));
			$crop = (bool) get_option('thumbnail_crop');

			// last chance thumbnail size defaults
			if ( !$max_width && !$max_height ) {
				$max_width = 128;
				$max_height = 96;
				$crop = true;
			}
		}
		elseif ( $size == 'medium' ) {
			$max_width = intval(get_option('medium_size_w'));
			$max_height = intval(get_option('medium_size_h'));
			$crop = false;
		}
		elseif ( $size == 'large' ) {
			$max_width = intval(get_option('large_size_w'));
			$max_height = intval(get_option('large_size_h'));
			$crop = false;
		}
		elseif ( isset( $_wp_additional_image_sizes ) && count( $_wp_additional_image_sizes ) && in_array( $size, array_keys( $_wp_additional_image_sizes ) ) ) {
			$max_width = intval( $_wp_additional_image_sizes[$size]['width'] );
			$max_height = intval( $_wp_additional_image_sizes[$size]['height'] );
			$crop = (bool) $_wp_additional_image_sizes[$size]['crop'];
		}

		if ( !$max_width && !$max_height ) {
			// We couldnt find a width and height 
			return false;
		}

		return array(
			'width'  => $max_width,
			'height' => $max_height,
			'crop'   => $crop
		);

	}


	protected function get_image_source( $id, $size ) {

		$img_url = wp_get_attachment_url($id);
		$img_url_basename = wp_basename($img_url);

		if ( $intermediate = $this->image_get_intermediate_size($id, $size) ) {
			$img_url = str_replace($img_url_basename, $intermediate['file'], $img_url);
		}

		return $img_url;
	}

	protected function image_get_intermediate_size( $post_id, $size = 'thumbnail' ) {
		if ( !is_array( $imagedata = wp_get_attachment_metadata( $post_id ) ) )
			return false;

		// get the best one for a specified set of dimensions
		if ( is_array($size) && !empty($imagedata['sizes']) ) {
			$areas = array();

			foreach ( $imagedata['sizes'] as $_size => $data ) {
				// already cropped to width or height; so use this size
				if ( ( $data['width'] == $size[0] && $data['height'] >= $size[1] ) || ( $data['height'] == $size[1] && $data['width'] >= $size[0] ) ) {
					$file = $data['file'];
					list($width, $height) = image_constrain_size_for_editor( $data['width'], $data['height'], $size );
					return compact( 'file', 'width', 'height' );
				}
				// add to lookup table: area => size
				$areas[$data['width'] * $data['height']] = $_size;
			}
			if ( !$size || !empty($areas) ) {
				// find for the smallest image not smaller than the desired size
				ksort($areas);
				foreach ( $areas as $_size ) {
					$data = $imagedata['sizes'][$_size];
					if ( $data['width'] >= $size[0] && $data['height'] >= $size[1] ) {
						// Skip images with unexpectedly divergent aspect ratios (crops)
						// First, we calculate what size the original image would be if constrained to a box the size of the current image in the loop
						$maybe_cropped = image_resize_dimensions($imagedata['width'], $imagedata['height'], $data['width'], $data['height'], false );
						// If the size doesn't match within one pixel, then it is of a different aspect ratio, so we skip it, unless it's the thumbnail size
						if ( 'thumbnail' != $_size && ( !$maybe_cropped || ( $maybe_cropped[4] != $data['width'] && $maybe_cropped[4] + 1 != $data['width'] ) || ( $maybe_cropped[5] != $data['height'] && $maybe_cropped[5] + 1 != $data['height'] ) ) )
							continue;
						// If we're still here, then we're going to use this size
						$file = $data['file'];
						list($width, $height) = image_constrain_size_for_editor( $data['width'], $data['height'], $size );
						return compact( 'file', 'width', 'height' );
					}
				}
			}
		}

		if ( is_array($size) || empty($size) || empty($imagedata['sizes'][$size]) )
			return false;

		$data = $imagedata['sizes'][$size];
		// include the full filesystem path of the intermediate file
		if ( empty($data['path']) && !empty($data['file']) ) {
			$file_url = wp_get_attachment_url($post_id);
			$data['path'] = path_join( dirname($imagedata['file']), $data['file'] );
			$data['url'] = path_join( dirname($file_url), $data['file'] );
		}
		return $data;
	}

	protected function get_thumbrio_url( $url, $size, $query_arguments = NULL, $base_url = NULL, $thumb_name = NULL ) {

		if ( !$base_url ) {
			if ( substr($url, 0, 7) === 'http://' ) {
				$base_url = self::THUMBRIO_BASE_URL_HTTP;
			}
			else {
				$base_url = self::THUMBRIO_BASE_URL_HTTPS;
			}
		}

		if ( substr($url, 0, 7) === 'http://' ) {
			$url = substr($url, 7);
		}

		if ( !$thumb_name ) {
			$thumb_name = wp_basename( $url );
		}

		$encoded_url        = $this->urlencode($url);
		$encoded_size       = $this->urlencode($size);
		$encoded_thumb_name = $this->urlencode($thumb_name);

		$path = "$encoded_url/$encoded_size/$encoded_thumb_name";

		if ($query_arguments) {
			$path .= "?$query_arguments";
		}

		// We should add the API to the URL when we use the non customized
		// thumbr.io domains
		if ( $base_url == self::THUMBRIO_BASE_URL_HTTP || $base_url == self::THUMBRIO_BASE_URL_HTTPS ) {
			$path = getenv('THUMBRIO_API_KEY') . "/$path";
		}

		// some bots (msnbot-media) "fix" the url changing // by /, so even if
		// it's legal it's troublesome to use // in a URL.
		$path = str_replace('//', '%2F%2F', $path);
		$token = hash_hmac('md5', $base_url . $path, getenv('THUMBRIO_SECRET_KEY'));

		return "$base_url$token/$path";
	}


	/**
	 * Encodes a string following RFC 3986, adding "/" to the safe characters.
	 * Assumes the $str is encoded in UTF-8.
	 */
	protected function urlencode($str) {
	    $length = strlen($str);
	    $encoded = '';
	    for ($i = 0; $i < $length; $i++) {
	        $c = $str[$i];
	        if (($c >= 'a' && $c <= 'z') ||
	            ($c >= 'A' && $c <= 'Z') ||
	            ($c >= '0' && $c <= '9') ||
	            $c == '/' || $c == '-' || $c == '_' || $c == '.')
	            $encoded .= $c;
	        else
	            $encoded .= '%' . strtoupper(bin2hex($c));
	    }
	    return $encoded;
	}


}
