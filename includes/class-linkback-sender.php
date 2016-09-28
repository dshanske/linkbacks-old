<?php
/**
 * Linkback Sender Class.
 *
 * Handles sending of Webmentions and Fallback to Pingbacks.
 *
 * @since 0.0.1
 */
final class Linkback_Sender {

	/**
	 * Send Linkback
	 *
	 * @param string $source source url
	 * @param string $target target url
	 * @param int $post_ID the post_ID (optional)
	 *
	 * @return array|boolean  Results including HTTP headers or false if no endpoint discovered.
	 */
	public static function send_linkback( $source, $target, $post_ID = null ) {
		global $wp_version;

		// stop selfpings on the same URL.
		if ( $source === $target ) {
			return false;
		}

		$parsed_target = wp_parse_url( $target );

		if ( ! isset( $parsed_target['host'] ) ) { // Not an URL. This should never happen.
			return false;
		}

		// is this a self mention on the same domain
		$selfping = ( parse_url( $source, PHP_URL_HOST ) === $parsed_target['host'] ) ? true : false  ;
		/**
		 * Filters whether self pings are allowed.
		 *
		 * Self Pings are allowed by default but this allows for an override of that.
		 *
		 * @since 0.0.1
		 *
		 * @param boolean  $denyselfping Whether or Not a Self Ping is denied
		 * @param boolean  $selfping Whether or Not The Source is on the Same Domain as the Target
		 */
		if ( apply_filters( 'same_domain_ping', false, $selfping ) ) {
			return false;
		}

		// do not search for a server on our own uploads
		$uploads_dir = wp_upload_dir();
		if ( 0 === strpos( $target, $uploads_dir['baseurl'] ) ) {
			return false;
		}

		$user_agent = apply_filters( 'http_headers_useragent', 'WordPress/' . $wp_version . '; ' . get_bloginfo( 'url' ) );
		$args = array(
				'timeout' => 100,
				'limit_response_size' => 1048576,
				'redirection' => 2,
				'user-agent' => $user_agent,
				);

		$response = wp_safe_remote_head( $target, $args );
		if ( is_wp_error( $response ) ) {
			return false;
		}

		// Link is to a media file so no point in proceeding.
		if ( preg_match( '#(image|audio|video|model)/#is', wp_remote_retrieve_header( $response, 'content-type' ) ) ) {
			return false;
		}

		// discover the endpoints
		$r = self::discover_endpoint( $target );

		// there is a webmention endpoint send a webmention
		if ( isset( $r['webmention'] ) ) {
			$body = array(
					'source' => $source,
					'target' => $target,
			);
			$body = apply_filters( 'webmention_send_vars', $body, $post_ID );
			$args['body'] = build_query( $body );
			$response = wp_remote_post( $r['webmention'], $args );
		} else if ( isset( $r['pingback'] ) ) {
			include_once( ABSPATH . WPINC . '/class-IXR.php' );
			include_once( ABSPATH . WPINC . '/class-wp-http-ixr-client.php' );
			// using a timeout of 3 seconds should be enough to cover slow servers
			$client = new WP_HTTP_IXR_Client( $r['pingback'] );
			$client->timeout = 3;
			// when set to true, this outputs debug messages by itself
			$client->debug = false;
			$client->query( 'pingback.ping', $source, $target );
		}

		// use the response to do something useful
		do_action( 'linkback_send', $response, $source, $target, $post_ID );

		return $response;
	}

	/**
	 * Send linkbacks for a particular post
	 *
	 * Will try webmentions and fallback to pingbacks if there is no webmention endpoint.
	 * Meant to be a replacement for the pingback function.
	 *
	 * You can hook this function directly into the `publish_post` action:
	 *
	 * <code>
	 *	 add_action('publish_post', array('Webmention_Sender', 'send_webmentions'));
	 * </code>
	 *
	 * @param int $post_ID the post_ID
	 */
	public static function send_linkbacks($post_ID) {
		// get source url
		$source = get_permalink( $post_ID );

		// get post
		$post = get_post( $post_ID );

		// get pung
		$pung = get_pung( $post_ID );

		// initialize links array
		$links = array();

		// Find all external links and embeds in the source
		$links = array_merge( wp_extract_urls_link( $post->post_content ), wp_extract_urls_embed( $post->post_content ) );
		// Alternatively you can extract all URLs in text
		// $links = wp_extract_urls( $post->post_content );

		// filter links
		$post_links = apply_filters( 'linkback_links', $links, $post_ID );
		// Legacy from Other Plugin for Now
		$post_links = apply_filters( 'webmention_links', $post_links, $post_ID );
		$post_links = array_unique( $post_links );

		/**
		 * Fires just before pinging back links found in a post.
		 *
		 * @since WordPress 2.0.0
		 *
		 * @param array &$post_links An array of post links to be checked, passed by reference.
		 * @param array &$pung       Whether a link has already been pinged, passed by reference.
		 * @param int   $post_ID     The post ID.
		*/
		do_action_ref_array( 'pre_ping', array( &$post_links, &$pung, $post_ID ) );

		foreach ( $post_links as $target ) {
			// send linkback
			$response = self::send_linkback( $source, $target, $post_ID );

			if ( ! is_wp_error( $response ) &&
				wp_remote_retrieve_response_code( $response ) < 400 ) {

				// if not already added to punged urls
				if ( ! in_array( $target, $pung ) ) {
					// tell the pingback function not to ping these links again
					add_ping( $post_ID, $target );
				}
			}

			// rescedule if server responds with a http error 5xx
			if ( wp_remote_retrieve_response_code( $response ) >= 500 ) {
				self::reschedule( $post_ID );
			}
		}
	}

	/**
	 * Reschedule Webmentions on HTTP code 500
	 *
	 * @param int $post_ID the post id
	 */
	public static function reschedule( $post_ID ) {
		$tries = get_post_meta( $post_ID, '_pingme_tries', true );

		// check "tries" and set to 0 if null
		if ( ! $tries ) {
			$tries = 0;
		}

		// raise "tries" counter
		$tries = $tries + 1;

		// rescedule only three times
		if ( $tries <= 3 ) {
			// save new tries value
			update_post_meta( $post_ID, '_pingme_tries', $tries );

			// and rescedule
			add_post_meta( $post_ID, '_pingme', '1', true );

			wp_schedule_single_event( time() + ( $tries * 900 ), 'do_pings' );
		} else {
			delete_post_meta( $post_ID, '_pingme_tries' );
		}
	}

	/**
	 * Do all pings
	 */
	public static function do_all_pings() {
		global $wpdb;
		$post_types = get_post_types( array( 'publicly_queryable' => true ) );
		$mentions = get_posts( array(
			'meta_key' => '_pingme',
			'suppress_filters' => false,
			'post_type' => $post_types,
			'fields' => 'ids',
			'posts_per_page' => -1,
		) );
		if ( empty( $mentions ) ) {
			return;
		}

		foreach ( $mentions as $mention ) {
			delete_post_meta( $mention , '_pingme' );
			// send them Webmentions
			self::send_linkbacks( $mention );
		}

		$enclosures = get_posts( array(
			'post_type' => $post_types,
			'suppress_filters' => false,
			'posts_per_page' => -1,
			'meta_value' => '_encloseme',
			'fields' => 'ids',
		) );
		foreach ( $enclosures as $enclosure ) {
			delete_post_meta( $enclosure, '_encloseme' );
			do_enclose( $enclosure->post_content, $enclosure->ID );
		}

		// Do Trackbacks
		$trackbacks = $wpdb->get_col( "SELECT ID FROM $wpdb->posts WHERE to_ping <> '' AND post_status = 'publish'" );
		if ( is_array( $trackbacks ) ) {
			foreach ( $trackbacks as $trackback ) {
				do_trackbacks( $trackback ); }
		}

			//Do Update Services/Generic Pings
			generic_ping();
	}



	/**
	 * Finds a webmention or pingback URI based on the given URL
	 *
	 * Checks the HTML for the rel link and headers. It does
	 * a check for the webmention headers first and returns that, if available.
	 *
	 * @param string $url URL to ping
	 *
	 * @return $args bool|array False on failure, array containing URI on success {
	 * 	@type string $pingback Pingback Endpoint URL.
	 *	@type string $webmention Webmention Endpoint URL.
	 */
	public static function discover_endpoint( $url ) {
		global $wp_version;
		$user_agent = apply_filters( 'http_headers_useragent', 'WordPress/' . $wp_version . '; ' . get_bloginfo( 'url' ) );
		$args = array(
				'timeout' => 100,
				'limit_response_size' => 1048576,
				'redirection' => 2,
				'user-agent' => $user_agent,
		);
		$return = array();
		$response = wp_safe_remote_head( $url, $args );
		if ( is_wp_error( $response ) ) {
			return false;
		}

		// Link is to a media file so no point in proceeding.
		if ( preg_match( '#(image|audio|video|model)/#is', wp_remote_retrieve_header( $response, 'content-type' ) ) ) {
			return false;
		}

		// check link header if there is one for a webmention return immediately
		if ( $links = wp_remote_retrieve_header( $response, 'link' ) ) {
			if ( is_array( $links ) ) {
				foreach ( $links as $link ) {
					if ( preg_match( '/<(.[^>]+)>;\s+rel\s?=\s?[\"\']?(http:\/\/)?webmention(\.org)?\/?[\"\']?/i', $link, $result ) ) {
						$return = array( 'webmention' => WP_Http::make_absolute_url( $result[1], $url ) );
					}
				}
			} else {
				if ( preg_match( '/<(.[^>]+)>;\s+rel\s?=\s?[\"\']?(http:\/\/)?webmention(\.org)?\/?[\"\']?/i', $links, $result ) ) {
					$return = array( 'webmention' => WP_Http::make_absolute_url( $result[1], $url ) );
				}
			}
		}

		if ( wp_remote_retrieve_header( $response, 'x-pingback' ) ) {
			$return['pingback'] = wp_remote_retrieve_header( $response, 'x-pingback' );
		}

		// now do a GET since we're going to look in the html headers
		$response = wp_safe_remote_get( $url, $args );

		if ( is_wp_error( $response ) ) {
			return false;
		}

		$contents = wp_remote_retrieve_body( $response );

		// unicode to HTML entities
		$contents = mb_convert_encoding( $contents, 'HTML-ENTITIES', mb_detect_encoding( $contents ) );

		libxml_use_internal_errors( true );

		$doc = new DOMDocument();
		$doc->loadHTML( $contents );

		$xpath = new DOMXPath( $doc );

		// check <link> and <a> elements
		// checks only body>a-links
		foreach ( $xpath->query( '(//link|//a)[contains(concat(" ", @rel, " "), " webmention ") or contains(@rel, "webmention.org")]/@href' ) as $result ) {
			$return['webmention'] = WP_Http::make_absolute_url( $result->value, $url );
		}
		if ( ! empty( $return ) ) {
			return $return;
		}
		foreach ( $xpath->query( '(//link|//a)[contains(concat(" ", @rel, " "), " pingback ")]/@href' ) as $result ) {
			return array( 'pingback' => WP_Http::make_absolute_url( $result->value, $url ) );
		}
		return false;
	}

}
