<?php
/**
 * Global Functions
 *
 * @package IndieWeb
 * @subpackage Webmentions
 * @since 0.1.0
 */

/**
 * Return Webmention Endpoint
 *
 * @return string the Webmention endpoint
 */
function get_webmention_endpoint() {
	return apply_filters( 'webmention_endpoint', get_rest_url( null, '/webmention/endpoint' ) );
}

/**
	 * A wrapper for Linkback_Sender::send_linkback
	  *
		 * @param string $source source url
		  * @param string $target target url
			 *
			  * @return array of results including HTTP headers
				 */
function send_linkback( $source, $target ) {
		return Linkback_Sender::send_linkback( $source, $target );
}

// Backward compatibility
if ( ! function_exists( 'send_webmention' ) ) {
	function send_webmention( $source, $target ) {
		return send_linkback( $source, $target );
	}
}

if ( ! function_exists( 'wp_get_meta_tags' ) ) :
	/**
	 * Parse meta tags from source content
	 * Based on the Press This Meta Parsing Code
	 *
	* @param string $source_content Source Content
							 	 *
								 	 * @return array meta tags
									 	 */
	function wp_get_meta_tags( $source_content ) {
		$meta_tags = array();

		if ( ! $source_content ) {
			return $meta_tags;
		}

		if ( preg_match_all( '/<meta [^>]+>/', $source_content, $matches ) ) {
			$items = $matches[0];
			foreach ( $items as $value ) {
				if ( preg_match( '/(property|name)="([^"]+)"[^>]+content="([^"]+)"/', $value, $matches ) ) {
					$meta_name  = $matches[2];
					$meta_value = $matches[3];
					// Sanity check. $key is usually things like 'title', 'description', 'keywords', etc.
					if ( strlen( $meta_name ) > 100 ) {
						continue;
					}
					$meta_tags[ $meta_name ] = $meta_value;
				}
			}
		}
		return $meta_tags;
	}
endif;

function wp_extract_urls_link( $content ) {
	preg_match_all( "/<a[^>]+href=.(https?:\/\/[^'\"]+)/i", $content, $post_links );
	$post_links = array_unique( array_map( 'html_entity_decode', $post_links[1] ) );
	return array_values( $post_links );
}

function wp_extract_urls_embed( $content ) {
	// Find all URLs on their own line.
	preg_match_all( '|^(\s*)(https?://[^\s<>"]+)(\s*)$|im', $content, $line_links );
	$line_links = array_unique( array_map( 'html_entity_decode', $line_links[1] ) );

	// Find all URLs in their own paragraph.
	preg_match_all( '|^(\s*)(https?://[^\s<>"]+)(\s*)$|im', $content, $para_links );
	$para_links = array_unique( array_map( 'html_entity_decode', $para_links[1] ) );

	return array_merge( array_values( $line_links ), array_values( $para_links ) );
}

/**
 * compare an url with a list of urls
 *
 * @param string $needle the target url
 * @param array $haystack a list of urls
 * @param boolean $schemelesse define if the target url should be checked with http:// and https://
 *
 * @return boolean
 */
function compare_urls( $needle, $haystack, $schemeless = true ) {
	if ( true === $schemeless ) {
		// remove url-scheme
		$schemeless_target = preg_replace( '/^https?:\/\//i', '', $needle );
		// add both urls to the needle
		$needle = array( 'http://' . $schemeless_target, 'https://' . $schemeless_target );
	} else {
		// make $needle an array
		$needle = array( $needle );
	}
	// compare both arrays
	return array_intersect( $needle, $haystack );
}
