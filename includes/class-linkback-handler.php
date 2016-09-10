<?php
/**
 * Linkback Handler.
 *
 * @package IndieWeb
 * @subpackage Webmentions
 * @since 0.1.0
 */

/**
 * Linkback Handler.
 *
 * Common Linkback Functionality Between Pingback and Webmention.
 *
 * @since 0.1.0
 */

final class Linkback_Handler {
	/**
	 * Author URL in a linkback is the linkback source.
	 * Replace this in displays with a parsed author url if available.
	 * Establishes a defined meta key for this.
	 *
	 * @param string $url the author url.
	 * @param int $id comment ID.
	 * @param WP_Comment $comment Comment Object.
	 * @return string the replaced/parsed author url or the original comment link
	 */
	public static function get_comment_author_url($url, $id, $comment) {
		if ( $author_url = get_comment_meta( $id, '_linkback_author_url', true ) ) {
			return $author_url;
		}
		return $url;
	}

	public static function generate_linkback_title( $remote_source ) {
		$meta_tags = wp_get_meta_tags( $remote_source );
		// use OGP title if available
		if ( array_key_exists( 'og:title', $meta_tags ) ) {
			// Use Open Graph Title if set
			return $meta_tags['og:title'];
		} elseif ( preg_match( '/<title>(.+)<\/title>/i', $remote_source, $match ) ) { // use title
			return trim( $match[1] );
		} else {
			return false;
		}
	}

	/**
	 * Check if a comment already exists
	 *
	 * @param array $commentdata the comment, created for the linkback data
	 *
	 * @return array|null              the dupe or null
	 */
	public static function check_dupes( $commentdata ) {
		$args = array(
				'comment_post_ID' => $commentdata['comment_post_ID'],
				'author_url' => htmlentities( $commentdata['comment_author_url'] ),
				);
		$comments = get_comments( $args );
		// check result
		if ( ! empty( $comments ) ) {
			$comment = $comments[0];
			$commentdata['comment_ID'] = $comment->comment_ID;
			$commentdata['comment_approved'] = $comment->comment_approved;
		}
		// Allows for alternative duplicate detection methods
		return apply_filters( 'linkback_check_dupes', $commentdata );
	}

	/**
 * Verify a linkback and either return an error if not verified or return the array with retrieved
 * data.
 *
 * @param array $data {
 *              @param $comment_type
 *              @param $comment_author_url
 *              @param $comment_author_IP
 *              @param $target
 * }
 *
 * @return array|WP_Error $data Return Error Object or array with added fields {
 *              @param $remote_source
 *              @param $remote_source_original
 *              @param $content_type
 */
	public static function linkback_verify( $data ) {
		global $wp_version;
		$user_agent = apply_filters( 'http_headers_useragent', 'WordPress/' . $wp_version . '; ' . get_bloginfo( 'url' ) );
		$args = array(
						'timeout' => 100,
						'limit_response_size' => 1048576,
						'redirection' => 5,
						'user-agent' => "$user_agent; verifying linkback from " . $data['comment_author_IP'],
						);
		$response = wp_safe_remote_get( $data['comment_author_url'], $args );
		  // check if source is accessible
		if ( is_wp_error( $response ) ) {
			  return new WP_Error( 'sourceurl', 'Source URL not found', array( 'status' => 400 ) );
		}
		$remote_source_original = wp_remote_retrieve_body( $response );
		// check if source really links to target
		if ( ! strpos( htmlspecialchars_decode( $remote_source_original ), str_replace( array(
												'http://www.',
											   'http://',
												'https://www.',
												'https://',
		), '', untrailingslashit( preg_replace( '/#.*/', '', $data['target'] ) ) ) ) ) {
				return new WP_Error( 'targeturl', 'Cannot find target link.', array( 'status' => 400 ) );
		}
		if ( ! function_exists( 'wp_kses_post' ) ) {
				include_once( ABSPATH . 'wp-includes/kses.php' );
		}
		$remote_source = wp_kses_post( $remote_source_original );

		$content_type = wp_remote_retrieve_header( $response, 'Content-Type' );

		$commentdata = compact( 'remote_source', 'remote_source_original', 'content_type' );

		$commentdata = array_merge( $commentdata, $data );
		return $commentdata;
	}



	// This is more to lay out the data structure than anything else.
	public static function register_meta() {
		$args = array(
				'sanitize_callback' => 'esc_url',
				'type' => 'string',
				'description' => 'Source for Linkbacks',
				'single' => true,
				'show_in_rest' => true,
				);
		// This is also stored in the comment_author_url field
		register_meta( 'comment', '_linkback_source', $args );

		$args = array(
				'sanitize_callback' => 'esc_url',
				'type' => 'string',
				'description' => 'Target for Linkbacks',
				'single' => true,
				'show_in_rest' => true,
				);
		// In the event the link is actually to a shortlink or other related target is stored
		register_meta( 'comment', '_linkback_target', $args );

		$args = array(
				'sanitize_callback' => 'esc_url',
				'type' => 'string',
				'description' => 'Author URL for the Linkback',
				'single' => true,
				'show_in_rest' => true,
				);
		// The comment_author_url field in the comment is used for the linkback source so cannot be
		// used for an actual author_url if one is parsed.
		register_meta( 'comment', '_linkback_author_url', $args );

		$args = array(
				'type' => 'string',
				'description' => 'Author Name for the Linkback',
				'single' => true,
				'show_in_rest' => true,
				);
		// The author name in a linkback is used for the Site Title.
		// This represents, if found, an actual author name.
		register_meta( 'comment', '_linkback_author_name' );

		$args = array(
				'type' => 'string',
				'description' => 'Avatar for the Linkback',
				'single' => true,
				'show_in_rest' => true,
				);
		// A gravatar requires an email address which may or may not be parseable from a source page.
		// This allows information to retrieve an avatar to be stored.
		// FIXME: Could be attachment ID, URL, data-url
		register_meta( 'comment', '_linkback_avatar', $args );
	}


}

