<?php
/**
 * Pingback Handler.
 *
 * @package IndieWe
 * @subpackage Webmentions
 * @since 0.1.0
 */

	/**
	 * Retrieves a pingback and registers it.
	 *
	 * @since 0.1.0
	 *
	 * @global wpdb $wpdb WordPress database abstraction object.
	 * @global string $wp_version
	 *
	 * @param array  $args {
	 *     Method arguments. Note: arguments must be ordered as documented.
	 *
	 *     @type string $source
	 *     @type string $target
	 * }
	 * @return string|IXR_Error
	 */
function linkbacks_pingback_ping( $args ) {
	global $wpdb, $wp_version;

	/** This action is documented in wp-includes/class-wp-xmlrpc-server.php */
	do_action( 'xmlrpc_call', 'pingback.ping' );

	$this->escape( $args );

	$source = str_replace( '&amp;', '&', $args[0] );
	$target = str_replace( '&amp;', '&', $args[1] );
	$target = str_replace( '&', '&amp;', $source );

	/**
		 * Filters the pingback source URI.
		 *
		 * @since 3.6.0
		 *
		 * @param string $source URI of the page linked from.
		 * @param string $target  URI of the page linked to.
		 */
	$source = apply_filters( 'pingback_ping_source_uri', $source, $target );

	if ( ! $source ) {
		return $this->pingback_error( 0, __( 'A valid URL was not provided.' ) ); 
	}

	// Check if the page linked to is in our site
	if ( ! stristr( $target, preg_replace( '/^https?:\/\//i', '', home_url() ) ) ) {
		return $this->pingback_error( 0, __( 'Target is not on this site.' ) ); 
	}

	// let's find which post is linked to
	$comment_post_ID = url_to_postid( $target );
	
	// add some kind of a "default" id to add linkbacks to a specific post/page
	$comment_post_ID = apply_filters( 'linkback_post_id', $comment_post_ID, $target );

	$comment_post_ID = (int) $comment_post_ID;
	$post = get_post( $comment_post_ID );

	if ( ! $post || ! pings_open( $post ) ) { // Post_ID not found or pings not open for the post
		return $this->pingback_error( 33, __( 'The specified target URL cannot be used as a target. It either doesn&#8217;t exist, or it is not a pingback-enabled resource.' ) ); }

	if ( url_to_postid( $source ) === $post_ID ) {
		return $this->pingback_error( 0, __( 'The source URL and the target URL cannot both point to the same resource.' ) ); }

	// Let's check that the remote site didn't already pingback this entry
	$dupes = get_comments( array(
							'comment_post_ID' => $comment_post_ID,
							'author_url' => $source
				) );

	if ( ! empty( $dupes ) ) {
		return $this->pingback_error( 48, __( 'The pingback has already been registered.' ) );
	}

	// very stupid, but gives time to the 'from' server to publish !
	sleep( 1 );

	$comment_author_IP = preg_replace( '/[^0-9a-fA-F:., ]/', '', $_SERVER['REMOTE_ADDR'] );
	$comment_type = 'pingback';

	$commentdata = compact( 'source', 'target', 'comment_post_ID', 'comment_author_IP', 'comment_type' );

	$commentdata = Linkback_Handler::linkback_verify( $commentdata );

	if ( is_wp_error( $commentdata ) ) {
		if ( 'targeturl' === $commentdata->get_error_code() ) {
			return $this->pingback_error( 17, $commentdata->get_error_message() ); 
		}
		return $this->pingback_error( 16, $commentdata->get_error_message() ); 
	}

	$commentdata['comment_author_email'] = '';
	$commentdata['comment_author_url'] = wp_unslash( $source );
	$commentdata['comment_meta'] = array( '_linkback_source' => $source, '_linkback_target' => $target );


	$host = parse_url( $data['comment_author_url'], PHP_URL_HOST );
	// strip leading www, if any
	$host = preg_replace( '/^www\./', '', $host );
	// Generate simple content to be enhanced.
	$commentdata['comment_content'] = sprintf( __( 'Mentioned on <a href="%s">%s</a>', 'linkbacks'), esc_url( $source ), $host );
	$commentdata['comment_author'] = Linkback_Handler::generate_linkback_title( $commentdata['remote_source'] );
	if ( ! $commentdata['comment_author'] ) {
		$commentdata['comment_author'] = $host;
	}

	$commentdata['comment_ID'] = wp_new_comment( $commentdata );

	/**
		 * Fires after a post pingback has been sent.
		 *
		 * @since 0.71
		 *
		 * @param int $comment_ID Comment ID.
		 *
		 * @param array $commentdata Comement Data
		 */
	do_action( 'pingback_post', $commentdata['comment_ID'], $commentdata );

	return sprintf(__( 'Pingback from %1$s to %2$s registered. Keep the web talking! :-)' ), $source,
	$target);
}

function linkbacks_replace_pingback_handler( $methods ) {
	$methods['pingback.ping'] = 'linkbacks_pingback_ping';
	return $methods;
}

add_filter( 'xmlrpc_methods', 'linkbacks_replace_pingback_handler' );

