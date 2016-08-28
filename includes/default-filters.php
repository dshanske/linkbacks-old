<?php
/**
 * Sets up default filters and hooks for the plugin.
 *
 * @package IndieWeb
 * @subpackage Webmentions
 * @since 0.1.0
 */

require_once( dirname( dirname( __FILE__ ) ) . '/includes/class-webmention-controller.php' );

// Configure the REST API route.
add_action( 'rest_api_init', array( 'Webmention_Controller', 'register_routes' ) );

// Filter the response to allow plaintext
add_filter( 'rest_pre_serve_request', array( 'Webmention_Controller', 'serve_request' ), 9, 4 );

// endpoint discovery
add_action( 'wp_head', array( 'Webmention_Controller', 'html_header' ), 99 );
add_action( 'send_headers', array( 'Webmention_Controller', 'http_header' ) );
add_filter( 'host_meta', array( 'Webmention_Controller', 'jrd_links' ) );
add_filter( 'webfinger_user_data', array( 'Webmention_Controller', 'jrd_links' ) );
add_filter( 'webfinger_post_data', array( 'Webmention_Controller', 'jrd_links' ) );

