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
 * Enhances Linkback Functionality.
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


}

