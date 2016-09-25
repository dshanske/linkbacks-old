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
	 * Add Last Modified Meta to Webmentions Set With Timezone Offset
	 */
	public static function last_modified( $comment_id, $commentdata ) {
		if ( 'webmention' === get_comment_type( $comment_id ) ) {
			$date = new DateTime( null, new DateTimeZone( get_option( 'timezone_string' ) ) );
			update_comment_meta( $comment_id, '_linkback_modified', $date->format( DATE_W3C ) );
		}
	}

	public static function generate_linkback_data( $data ) {
		$meta_tags = wp_get_meta_tags( $data['remote_source_original'] );
		$host = wp_parse_url( $data['comment_author_url'] );
		// strip leading www, if any
		$host = preg_replace( '/^www\./', '', $host['host'] );

		// use OGP title if available
		if ( array_key_exists( 'author', $meta_tags ) ) {
			$data['comment_author'] = $meta['author'];
		} elseif ( array_key_exists( 'og:title', $meta_tags ) ) {
			// Use Open Graph Title if set
			$data['comment_author'] = $meta_tags['og:title'];
		} elseif ( preg_match( '/<title>(.+)<\/title>/i', $data['remote_source_original'], $match ) ) { // use title
			$data['comment_author'] = trim( $match[1] );
		} else {
			$data['comment_author'] = $host;
		}

		if ( array_key_exists( 'article:published_time', $meta_tags ) ) {
			$date = new DateTime( $meta_tags['article:published_time'] );
			$date->setTimezone( new DateTimeZone( 'UTC' ) );
			$data['comment_date_gmt'] = $date->format( 'Y-m-d H:i:s' );
			$date->setTimezone( new DateTimeZone( get_option( 'timezone_string' ) ) );
			$data['comment_date'] = $date->format( 'Y-m-d H:i:s' );
		}

		// Generate simple content.
		if ( array_key_exists( 'og:description', $meta_tags ) ) {
			$data['comment_content'] = $meta_tags['og:description'];
		} else {
			$data['comment_content'] = sprintf( __( 'Mentioned on <a href="%s">%s</a>', 'linkbacks' ), esc_url( $data['comment_author_url'] ), $host );
		}

		return $data;
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
	 * @param array                  $data {
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
		if ( ! is_array( $data ) || empty( $data ) ) {
			return new WP_Error( 'invaliddata', 'Invalid Data Passed', array( 'status' => 500 ) );
		}
		$user_agent = apply_filters( 'http_headers_useragent', 'WordPress/' . $wp_version . '; ' . get_bloginfo( 'url' ) );
		$args = array(
						'timeout' => 10,
						'limit_response_size' => 153600,
						'redirection' => 5,
						'user-agent' => "$user_agent; verifying " . $data['comment_type'] .  'linkback from ' . $data['comment_author_IP'],
						);
		$response = wp_safe_remote_head( $data['source'], $args );
		  // check if source is accessible
		if ( is_wp_error( $response ) ) {
			  return new WP_Error( 'sourceurl', 'Source URL not found', array( 'status' => 400 ) );
		}

		// A valid response code from the other server would not be considered an error.
		$response_code = wp_remote_retrieve_response_code( $response );
		// not an (x)html, sgml, or xml page, no use going further
		if ( preg_match( '#(image|audio|video|model)/#is', wp_remote_retrieve_header( $response, 'content-type' ) ) ) {
			return new WP_Error( 'content-type', 'Content Type is Media', array( 'status' => 400 ) );
		}

		switch ( $response_code ) {
			case 200:
				$response = wp_safe_remote_get( $data['source'], $args );
				break;
			case 410:
				return new WP_Error( 'deleted', 'Page has Been Deleted', array( 'status' => 400, 'data' => $data ) );
			case 452:
				return new WP_Error( 'removed', 'Page Removed for Legal Reasons', array( 'status' => 400, 'data' => $data ) );
			default:
				return new WP_Error( 'sourceurl', wp_remote_retrieve_response_message( $response ), array( 'status' => 400 ) );
		}

		$remote_source_original = wp_remote_retrieve_body( $response );

		/**
		 * Filters the linkback remote source.
		 *
		 * @since 2.5.0
		 *
		 * @param string $remote_source_original Raw Response source for the page linked from.
		 * @param string $target  URL of the page linked to.
		 */
		$remote_source_original = apply_filters( 'pre_remote_source', $remote_source_original, $data['target'] );

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

		return array_merge( $commentdata, $data );
	}


	// This is more to lay out the data structure than anything else.
	public static function register_meta() {
		$args = array(
				'sanitize_callback' => 'esc_url_raw',
				'type' => 'string',
				'description' => 'Source for Linkbacks',
				'single' => true,
				'show_in_rest' => true,
				);
		// This is also stored in the comment_author_url field
		register_meta( 'comment', '_linkback_source', $args );

		$args = array(
				'sanitize_callback' => 'esc_url_raw',
				'type' => 'string',
				'description' => 'Target for Linkbacks',
				'single' => true,
				'show_in_rest' => true,
				);
		// In the event the link is actually to a shortlink or other related target is stored
		register_meta( 'comment', '_linkback_target', $args );

		$args = array(
				'sanitize_callback' => 'esc_url_raw',
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
				'description' => 'Avatar for the Linkback',
				'single' => true,
				'show_in_rest' => true,
				);
		// A gravatar requires an email address which may or may not be parseable from a source page.
		// This allows information to retrieve an avatar to be stored.
		// FIXME: Could be attachment ID, URL, data-url
		register_meta( 'comment', '_linkback_avatar', $args );

		$args = array(
				'type' => 'string',
				'description' => 'Last Modified timestamp with offset for Webmentions',
				'single' => true,
				'show_in_rest' => true,
				);
		// This is stored rather than in WordPress standard - two variables having a local and a gmt
		// offset in ISO8601 format with timezone offset included. Parsing can override this with a
		// timestamp supplied by the remote site as needed.
		register_meta( 'comment', '_linkback_modified', $args );

		$args = array(
				'type' => 'string',
				'description' => 'Type of Linkback',
				'single' => true,
				'show_in_rest' => true,
				);
		// Type of Linkback - mention, reply, RSVP, like, etc
		register_meta( 'comment', '_linkback_type', $args );

		$args = array(
				'type' => 'string',
				'description' => 'Canonical URL for Linkback',
				'single' => true,
				'show_in_rest' => true,
				);
		// In the event the parsing declares a different canonical URL
		register_meta( 'comment', '_linkback_url', $args );
	}

	/**
		 * Save Meta - to Match the core functionality in wp_insert_comment.
	 * To be Removed if This Functionality Hits Core.
		 *
		 * @param array $commentdata The new comment data
		 * @param array $comment The old comment data
		 */
	public static function update_meta($comment_ID, $commentdata ) {
		// If metadata is provided, store it.
		if ( isset( $commentdata['comment_meta'] ) && is_array( $commentdata['comment_meta'] ) ) {
			foreach ( $commentdata['comment_meta'] as $meta_key => $meta_value ) {
				update_comment_meta( $comment_ID, $meta_key, $meta_value, true );
			}
		}
	}
}

