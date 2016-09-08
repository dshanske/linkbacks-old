<?php
/**
 * Webmention Controller class, used to provide a webmention endpoint.
 *
 * @package IndieWeb
 * @subpackage Webmentions
 * @since 0.1.0
 */

/**
 * Webmention endpoint controller.
 *
 * Handles the Receiving of Webmentions.
 *
 * @since 0.1.0
*/
final class Webmention_Controller {
	/**
	 * Register the Routes.
	 */
	public static function register_routes() {
		register_rest_route( 'webmention', '/endpoint', array(
			array(
					'methods' => WP_REST_Server::CREATABLE,
					'callback' => array( 'Webmention_Controller', 'post' ),
					'args' => array(
						'source' => array(
							'required' => 'true',
							'sanitize_callback' => 'esc_url',
						),
						'target' => array(
							'required' => 'true',
							'sanitize_callback' => 'esc_url',
						),
					),
				),
			array(
				'methods' => WP_REST_Server::READABLE,
				'callback' => array( 'Webmention_Controller', 'get' ),
				),
			)
		);
	}

	/**
 * Hooks into the REST API output to output alternatives to JSON.
 *
 * This is only done for the webmention endpoint.
 *
 * @access private
 * @since 0.1.0
 *
 * @param bool                      $served  Whether the request has already been served.
 * @param WP_HTTP_ResponseInterface $result  Result to send to the client. Usually a WP_REST_Response.
 * @param WP_REST_Request           $request Request used to generate the response.
 * @param WP_REST_Server            $server  Server instance.
 * @return true
 */
	public static function serve_request( $served, $result, $request, $server ) {
		if ( '/webmention/endpoint' !== $request->get_route() ) {
			return $served;
		}
		if ( 'GET' !== $request->get_method() ) {
			return $served;
		}

		if ( 'GET' === $request->get_method() ) {
			if ( ! headers_sent() ) {
				//	status_header( 400 );
				$server->send_header( 'Content-Type', 'text/html; charset=' . get_option( 'blog_charset' ) );
			}
			get_header();
			self::webmention_form();
			get_footer();
		}
		if ( is_wp_error( $result ) ) {
			return true;
		}
		if ( ! $result ) {
			status_header( 501 );
			return get_status_header_desc( 501 );
		}
		$error = $result->as_error();
		if ( $error ) {
			$error_data = $error->get_error_data( );
			if ( isset( $error_data['status'] ) ) {
				status_header( $error_data['status'] );
			}
			echo $error->get_error_message();
		} else {
			echo $result->get_data();
		}
		return true;
	}


	/**
	 * Post Callback for the webmention endpoint.
	 *
	 * Returns the response.
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 * @return WP_Error|WP_REST_Response
	 */
	public static function post( $request ) {
		$params = array_filter( $request->get_params() );
		if ( ! isset( $params['source'] ) ) {
			return new WP_Error( 'source' , 'Source is Missing', array( 'status' => 400 ) );
		}
		if ( ! isset( $params['target'] ) ) {
			return new WP_Error( 'target', 'Target is Missing', array( 'status' => 400 ) );
		}
		if ( ! stristr( $params['target'], preg_replace( '/^https?:\/\//i', '', home_url() ) ) ) {
			return new WP_Error( 'target', 'Target is Not on this Domain', array( 'status' => 400 ) );
		}
		if ( WP_DEBUG ) {
			error_log( 'Webmention Received: ' . $params['source'] . ' => ' . $params['target'] );
		}
		$comment_post_ID = url_to_postid( $params['target'] );

		// add some kind of a "default" id to add all
		// webmentions to a specific post/page
		$comment_post_ID = apply_filters( 'webmention_post_id', $comment_post_ID, $params['target'] );

		// check if post id exists
		if ( ! $comment_post_ID ) {
			return new WP_Error( 'targetnotvalid', 'Target is Not a Valid Post', array( 'status' => 400 ) );
		}

		// check if pings are allowed
		if ( ! pings_open( $comment_post_ID ) ) {
			return new WP_Error( 'pingsclosed', 'Pings are Disabled for this Post', array( 'status' => 400 ) );
		}

		$post = get_post( $comment_post_ID );

		if ( ! $post ) {
			return new WP_Error( 'targetnotvalid', 'Target is Not a Valid Post', array( 'status' => 400 ) );
		}

		// Store Copies of Source and Target in Comment Meta
		$comment_meta = array();

		$comment_author_IP = preg_replace( '/[^0-9a-fA-F:., ]/', '',$_SERVER['REMOTE_ADDR'] ); 

		$comment_author_url = $comment_meta['_webmention_source'] = esc_url_raw( $params['source'] );
		$target = $comment_meta['_webmention_target'] = esc_url_raw( $params['target'] );

		$commentdata = compact( 'comment_post_ID', 'comment_author_IP', 'comment_author_url',
		'comment_meta', 'target' );

		// be sure to return an error message or response to the end of your request handler
		return apply_filters( 'webmention_request', $commentdata );
	}

	public static function synchronous_handler( $data ) {
		global $wp_version;
		$user_agent = apply_filters( 'http_headers_useragent', 'WordPress/' . $wp_version . '; ' . get_bloginfo( 'url' ) );
		$args = array(
				'timeout' => 100,
				'limit_response_size' => 1048576,
				'redirection' => 20,
				'user-agent' => "$user_agent; verifying Webmention from " . $data['comment_author_IP'],
		);
		$response = wp_safe_remote_get( $data['comment_author_url'], $args );
		// check if source is accessible
		if ( is_wp_error( $response ) ) {
			return new WP_Error( 'sourceurl', 'Source URL not found', array( 'status' => 400 ) );
		}
		$remote_source_original = wp_safe_remote_retrieve_body( $response );
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

		$comment_type = 'webmention';

		// add empty fields
		$comment_parent = $comment_author_email = '';

		$host = parse_url( $data['comment_author_url'], PHP_URL_HOST );
		// strip leading www, if any
		$host = preg_replace( '/^www\./', '', $host );
		// Generate simple content to be enhanced.
		$comment_content = sprintf( __( 'Mentioned on <a href="%s">%s</a>', 'linkbacks' ), esc_url( $data['comment_author_url'] ), $host );

		$meta_tags = wp_get_meta_tags( $remote_source_original );
		// use meta-author
		if ( array_key_exists( 'author', $meta_tags ) ) {
			$data['comment_author'] = $meta_tags['author'];
		} elseif ( array_key_exists( 'og:title', $meta_tags ) ) {
			// Use Open Graph Title if set
			$data['comment_author'] = $meta_tags['og:title'];
		} elseif ( preg_match( '/<title>(.+)<\/title>/i', $remote_source_original, $match ) ) { // use title
			$data['comment_author'] = trim( $match[1] );
		} else {
			$data['comment_author'] = $host;
		}

		$commentdata = compact( 'comment_author', 'comment_author_email',
			'comment_content', 'comment_parent', 'remote_source',
		'remote_source_original', 'comment_type' );
		$commentdata = array_merge( $commentdata, $data );

		$commentdata = apply_filters( 'webmention_comment_data', $commentdata );

		// disable flood control
		remove_filter( 'check_comment_flood', 'check_comment_flood_db', 10, 3 );

		// update or save webmention
		if ( empty( $commentdata['comment_ID'] ) ) {
			// save comment
			$comment_ID = wp_new_comment( $commentdata );
		} else {
			// save comment
			wp_update_comment( $commentdata );
			$comment_ID = $comment->comment_ID;
		}
		// re-add flood control
		add_filter( 'check_comment_flood', 'check_comment_flood_db', 10, 3 );

		// render a simple and customizable text output
		return rest_ensure_response( $comment_ID );
	}

	/**
 * Post Callback for the webmention endpoint.
 *
 * Returns the response.
 *
 * @param WP_REST_Request $request Full data about the request.
 * @return WP_Error|WP_REST_Response
 */
	public static function get( $request ) {
		return '';
	}

	/**
	 * Extend the "filter by comment type" of in the comments section
	 * of the admin interface with "webmention"
	 *
	 * @param array $types the different comment types
	 *
	 * @return array the filtert comment types
	 */
	public static function comment_types_dropdown( $types ) {
		$types['webmention'] = __( 'Webmentions', 'linkbacks' );
		return $types;
	}

	/**
	 * The Webmention autodicovery meta-tags
	 */
	public static function html_header() {
		// Only add link if pings are open.
		if ( pings_open() ) {
			echo '<link rel="webmention" href="' . get_webmention_endpoint() . '" />' . "\n";
		}
	}

	/**
	 * The Webmention autodicovery http-header
	 */
	public static function http_header() {
		header( 'Link: <' . get_webmention_endpoint() . '>; rel="webmention"', false );
	}

	/**
 * Generates a webmention form
 */
	public static function webmention_form() {
		?> 
		<br />
		<form id="webmention-form" action="<?php echo get_webmention_endpoint(); ?>" method="post">
		<p>
			<label for="webmention-source"><?php _e( 'Source URL:', 'webmention' ); ?></label>
				<input id="webmention-source" size="15" type="url" name="source" placeholder="Where Did You Link to?" />
		</p>
		<p>
			<label for="webmention-target"><?php _e( 'Target URL:', 'webmention' ); ?></label>
			<input id="webmention-target" size="15" type="url" name="target" placeholder="What Did You Link to?" />
			<br /><br/>
			<input id="webmention-submit" type="submit" name="submit" value="Send" />
		</p>
		</form>
		<p><?php _e( 'Webmention is a way for you to tell me "Hey, I have written a response to your post."', 'webmention' ); ?> </p>
		<p><?php _e( 'Learn more about webmentions at <a href="http://webmention.net">webmention.net</a>', 'webmention' ); ?> </p>
		<?php
	}


}
