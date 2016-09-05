<?php
/**
 * Webmention Controller class, used to provide a webmention endpoint.
 *
 * @package IndieWeb
 * @subpackage Webmentions
 * @since 0.1.0
 */

/**
 * Webmentiion endpoint controller.
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
					'methods' => 'POST',
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
				'methods' => 'GET',
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
		if ( 'GET' !== $request->get_method() && 'POST' !== $request->get_method() ) {
			return $served;
		}
		if ( 'POST' === $request->get_method() ) {
			if ( ! headers_sent() ) {
			 	$server->send_header( 'Content-Type', 'text/plain; charset=' . get_option( 'blog_charset' ) );
				$server->send_header( 'Access-Control-Allow-Origin', '*' );
			}
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
			echo 'error';
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
