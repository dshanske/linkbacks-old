<?php
/**
 * Sets up default filters and hooks for the plugin.
 *
 * @package IndieWeb
 * @subpackage Webmentions
 * @since 0.1.0
 */

require_once( dirname( dirname( __FILE__ ) ) . '/includes/class-linkback-handler.php' );
require_once( dirname( dirname( __FILE__ ) ) . '/includes/class-webmention-controller.php' );
require_once( dirname( dirname( __FILE__ ) ) . '/includes/class-linkback-sender.php' );
require_once( dirname( dirname( __FILE__ ) ) . '/includes/pingback-handler.php' );



// Configure the REST API route.
add_action( 'rest_api_init', array( 'Webmention_Controller', 'register_routes' ) );

// Filter the response to allow plaintext
add_filter( 'rest_pre_serve_request', array( 'Webmention_Controller', 'serve_request' ), 9, 4 );

// Add Support for Registering Meta
add_action( 'plugins_loaded', array( 'Linkback_Handler', 'register_meta' ) );

// a pseudo hook so you can run a do_action('send_linkback')
// instead of calling Linkback_Sender::send_linkback
add_action( 'send_linkback', array( 'Linkback_Sender', 'send_linkback' ), 10, 2 );

// run webmentions before the other pinging stuff
// add_action( 'do_pings', array( 'Webmention_Sender', 'do_webmentions' ), 5, 1 );
// add_action( 'publish_post', array( 'Webmention_Sender', 'publish_post_hook' ) );

// Sync Webmention Handler
add_action( 'webmention_request', array( 'Webmention_Controller', 'synchronous_handler' ) );

// Basic Async Webmention Handler
//add_action( 'webmention_request', array( 'Webmention_Controller', 'basic_asynchronous_handler' ) );
//add_action( 'async_process_webmention', array( 'Webmention_Controller', 'process_webmention', ) );


// Add webmention to comment dropdown
add_action( 'admin_comment_types_dropdown', array( 'Webmention_Controller', 'comment_types_dropdown' ) );

//
add_filter( 'get_comment_author_url', array( 'Linkback_Handler', 'get_comment_author_url' ), 99, 3 );

// endpoint discovery
add_action( 'wp_head', array( 'Webmention_Controller', 'html_header' ), 99 );
add_action( 'send_headers', array( 'Webmention_Controller', 'http_header' ) );

// replace do_all_pings
remove_action( 'do_pings', 'do_all_pings', 10, 1 );
add_action( 'do_pings', array( 'Linkback_Sender', 'do_all_pings' ), 10, 1 );
