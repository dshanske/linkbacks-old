<?php
/**
 * Sets up default filters and hooks for the plugin.
 *
 * @package IndieWeb
 * @subpackage Webmentions
 * @since 0.1.0
 */

require_once( dirname( dirname( __FILE__ ) ) . '/includes/class-webmention-controller.php' );
require_once( dirname( dirname( __FILE__ ) ) . '/includes/class-webmention-sender.php' );

// Configure the REST API route.
add_action( 'rest_api_init', array( 'Webmention_Controller', 'register_routes' ) );

// Filter the response to allow plaintext
add_filter( 'rest_pre_serve_request', array( 'Webmention_Controller', 'serve_request' ), 9, 4 );

// a pseudo hook so you can run a do_action('send_webmention')
// instead of calling Webmention_Sender::send_webmention
add_action( 'send_webmention', array( 'Webmention_Sender', 'send_webmention' ), 10, 2 );
 
// run webmentions before the other pinging stuff
add_action( 'do_pings', array( 'Webmention_Sender', 'do_webmentions' ), 5, 1 );
add_action( 'publish_post', array( 'Webmention_Sender', 'publish_post_hook' ) );



// endpoint discovery
add_action( 'wp_head', array( 'Webmention_Controller', 'html_header' ), 99 );
add_action( 'send_headers', array( 'Webmention_Controller', 'http_header' ) );

