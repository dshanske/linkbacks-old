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
							'sanitize_callback' => 'esc_url_raw',
							'validate_callback' => 'wp_http_validate_url',
						),
						'target' => array(
							'required' => 'true',
							'sanitize_callback' => 'esc_url_raw',
							'validate_callback' => 'wp_http_validate_url',
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
		// If someone tries to poll the webmention endpoint return a webmention form.
		if ( ! headers_sent() ) {
			// status_header( 400 );
			$server->send_header( 'Content-Type', 'text/html; charset=' . get_option( 'blog_charset' ) );
		}
		$template = apply_filters( 'webmention_endpoint_form', plugin_dir_path( __FILE__ ) . '../templates/webmention-endpoint-form.php' );
		load_template( $template );
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
			return new WP_Error( 'source_missing' , __( 'Source is Missing' 'linkbacks'), array( 'status' => 400 ) );
		}
		if ( ! isset( $params['target'] ) ) {
			return new WP_Error( 'target_missing', __( 'Target is Missing', 'linkbacks'), array( 'status' => 400 ) );
		}

		$source = $params['source'];
		$target = $params['target'];

		if ( ! stristr( $target, preg_replace( '/^https?:\/\//i', '', home_url() ) ) ) {
			return new WP_Error( 'target_domain', __( 'Target is Not on this Domain', 'linkbacks' ), array( 'status' => 400 ) );
		}
		if ( WP_DEBUG ) {
			error_log( 'Webmention Received: ' . $source . ' => ' . $params['target'] );
		}
		$comment_post_ID = url_to_postid( $target );

		// add some kind of a "default" id to add linkbacks to a specific post/page
		$comment_post_ID = apply_filters( 'linkback_post_id', $comment_post_ID, $target );

		if ( url_to_postid( $source ) === $comment_post_ID ) {
			return new WP_Error( 'source_equals_target', __( 'Target and Source cannot direct to the same
						resource', 'linkbacks' ), array( 'status' => 400 ) );
		}

		// check if post id exists
		if ( ! $comment_post_ID ) {
			return new WP_Error( 'target_not_valid', __( 'Target is Not a Valid Post', 'linkbacks' ), array( 'status' => 400 ) );
		}

		// check if pings are allowed
		if ( ! pings_open( $comment_post_ID ) ) {
			return new WP_Error( 'pings_closed', __( 'Pings are Disabled for this Post', 'linkbacks' ), array( 'status' => 400 ) );
		}

		$post = get_post( $comment_post_ID );

		if ( ! $post ) {
			return new WP_Error( 'target_not_valid', __( 'Target is Not a Valid Post', 'linkbacks' ), array( 'status' => 400 ) );
		}

		// In the event of async processing this needs to be stored here as it might not be available
		// later.
		$comment_author_IP = preg_replace( '/[^0-9a-fA-F:., ]/', '',$_SERVER['REMOTE_ADDR'] );
		$comment_agent = isset( $_SERVER['HTTP_USER_AGENT'] ) ? $_SERVER['HTTP_USER_AGENT']: '';
		$comment_date = current_time( 'mysql' );
		$comment_date_gmt = current_time( 'mysql', 1 );

		$comment_type = 'webmention';

		$commentdata = compact( 'comment_type', 'comment_agent', 'comment_date', 'comment_date_gmt', 'comment_post_ID', 'comment_author_IP',
		'source', 'target' );

		// be sure to return an error message or response to the end of your request handler
		return apply_filters( 'webmention_request', $commentdata );
	}

	public static function basic_asynchronous_handler( $data ) {
		// Schedule the Processing to Be Completed sometime in the next 3 minutes
		wp_schedule_single_event( time() + wp_rand( 0, 120 ), 'async_process_webmention', array( $data ) );
		return new WP_REST_Response( $data, 202 );
	}

	public static function synchronous_handler( $data ) {
		$data = self::process_webmention( $data );
		if ( is_wp_error( $data ) ) {
			return $data;
		}
		// Return select data
		$return = array(
				'title' => $data['comment_author'],
				'comment_ID' => $data['comment_ID'],
				'source' => $data['source'],
				'target' => $data['target'],
				);
		return new WP_REST_Response( $return, 200 );
	}

	public static function process_webmention( $data ) {
		if ( ! $data ) {
			error_log( 'Webmention Data Failed' );
			return $data;
		}
		$data = Linkback_Handler::linkback_verify( $data );

		if ( is_wp_error( $data ) ) {
			// Allows for Error Logging or Handling
			do_action( 'webmention_receive_error', $data );
			return $data;
		}

		// Set Comment Author URL to Source
		$data['comment_author_url'] = $data['source'];

		// add empty fields
		$data['comment_parent'] = $data['comment_author_email'] = '';

		// add comment meta
		$data['comment_meta'] = array(
				'_linkback_source' => $data['source'],
				'_linkback_target' => $data['target'],
				'_linkback_type' => 'mention',// Set Default Type
		);

		// Generate Extra Linkback Data from Meta Tags
		$data = Linkback_Handler::generate_linkback_data( $data );

		// Check for Duplicates
		$data = Linkback_Handler::check_dupes( $data );

		// disable flood control
		remove_filter( 'check_comment_flood', 'check_comment_flood_db', 10, 3 );

		// update or save webmention
		if ( empty( $data['comment_ID'] ) ) {
			// save comment
			$data['comment_ID'] = wp_new_comment( $data );
			do_action( 'webmention_post', $data['comment_ID'], $data );
		} else {
			// Temporary placeholder for webmention updates until Core supports an edit comment filter
			$data = apply_filters( 'webmention_update', $data );
			// save comment
			wp_update_comment( $data );
		}
		// re-add flood control
		add_filter( 'check_comment_flood', 'check_comment_flood_db', 10, 3 );

		// Return the comment data
		return $data;
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
		return true;
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

}
