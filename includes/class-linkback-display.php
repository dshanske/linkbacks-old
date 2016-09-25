<?php
/**
 * Linkback Display.
 *
 * @package IndieWeb
 * @subpackage Webmentions
 * @since 0.1.0
 */

/**
 * Linkback Display.
 *
 * Handles Display of Linkbacks in Cooperation with Custom Comment Walker.
 *
 * @since 0.1.0
 */

final class Linkback_Display {
	/**
	 * Author URL in a linkback is the linkback source.
	 * Replace this in displays with a parsed author url if available.
	 * Establishes a defined meta key for this.
	 *
	 * @param string     $url the author url.
	 * @param int        $id comment ID.
	 * @param WP_Comment $comment Comment Object.
	 * @return string the replaced/parsed author url or the original comment link
	 */
	public static function get_comment_author_url($url, $id, $comment) {
		$author_url = get_comment_meta( $comment->comment_ID, '_linkback_author_url', true );
		if ( ! $author_url ) {
			// If nothing is found fallback on Semantic Linkbacks Data.
			$author_url = get_comment_meta( $comment->comment_ID, 'semantic_linkbacks_author_url', true );
		}
		return $author_url ? $author_url : $url;
	}

	/**
	 * Replaces the comment link with one to the canonical URL
	 *
	 * Establishes a defined meta key for this
	 *
	 *
	 * @param string $link the link url
	 * @param obj $comment the comment object
	 * @param array $args a list of arguments to generate the final link tag
	 * @return string the linkback source or the original comment link
	 */
	public static function get_comment_link( $link, $comment, $args ) {
		$url = get_comment_meta( $comment->comment_ID, '_linkbacks_url', true );
		if ( ! $url ) {
			// If no URL look for it where Semantic Linkbacks stores it.
			$url = get_comment_meta( $comment->comment_ID, 'semantic_linkbacks_canonical', true );
		}
		if ( is_singular() && $url ) {
			return $url;
		}
		return $link;
	}

	/**
	 * Returns an array of linkback texts to their translated and pretty display versions
	 *
	 * @return array The array of translated display texts.
	 */
	public static function get_linkback_type_text() {
		$strings = array( 'mention'		=> __( '%1$s mentioned %2$s', 'linkbacks' ),
											'reply'			=> __( '%1$s replied to %2$s', 'linkbacks' ),
											'repost'		=> __( '%1$s reposted %2$s', 'linkbacks' ),
											'like'			=> __( '%1$s liked %2$s',		'linkbacks' ),
											'tag'				=> __( '%1$s tagged %2$s',		'linkbacks' ),
											'bookmark'	=> __( '%1$s bookmarked %2$s', 'linkbacks' )
				);
		return $strings;
	}

	/**
	 * Generate full comment text if displayed as comment.
	 *
	 * @param string $text the comment text
	 * @param WP_Comment $comment the comment object
	 * @param array $args a list of arguments
	 * @return string the filtered comment text
	 */
	public static function comment_text( $text, $comment = null, $args = array() ) {
		if ( ! is_object( $comment ) ) {
			$comment = get_comment( $comment );
		}
		if ( ! $comment ) {
			return $text;
		}
		if ( '' == $comment->comment_type ) {
			return $text;
		}
		return self::linkback_text( $comment );
	}

	/**
	 * Generate single line description for mention.
	 *
	 * @param WP_Comment $comment the comment object
	 * @return string the comment text
	 */
	public static function linkback_text( $comment = null ) {
		if ( ! is_object( $comment ) ) {
			$comment = get_comment( $comment );
		}
		if ( ! $comment ) {
			return false;
		}
		echo 'Test';
	}


	/**
	 * Show avatars also on webmentions and pingbacks
	 *
	 * @param array $types list of avatar enabled comment types
	 *
	 * @return array show avatars also on webmentions and pingbacks
	 */
	public static function get_avatar_comment_types( $types ) {
		$types[] = 'pingback';
		$types[] = 'webmention';
		return $types;
	}


	/**
	 * Replaces the default avatar with the WebMention uf2 photo
	 *
	 * @param array             $args Arguments passed to get_avatar_data(), after processing.
	 * @param int|string|object $id_or_email A user ID, email address, or comment object
	 * @return array $args
	 */
	public static function pre_get_avatar_data($args, $id_or_email) {
		if ( ! isset( $args['class'] ) ) {
			$args['class'] = array( 'u-photo' );
		} else {
			$args['class'][] = 'u-photo';
		}
		if ( ! is_object( $id_or_email ) || ! isset( $id_or_email->comment_type ) ) {
			return $args;
		}
		// check if comment has an avatar
		$avatar = get_comment_meta( $id_or_email->comment_ID, '_linkback_avatar', true );
		// If there is no avatar check where Semantic Linkbacks stores its avatar
		if ( ! $avatar ) {
			$avatar = get_comment_meta( $id_or_email->comment_ID, 'semantic_linkbacks_avatar', true );
		}
		if ( $avatar ) {
			$args['url'] = $avatar;
		}

		return $args;
	}

	public static function get_linkback_url( $comment = null ) {
		// get URL canonical url...
		$url = get_comment_meta( $comment->comment_ID, '_linkback_url', true );
		// ...or fall back to source
		if ( ! $url ) {
			$url = get_comment_meta( $comment->comment_ID, '_linkback_source', true );
		}

		// get URL canonical url...
		$url = get_comment_meta( $comment->comment_ID, 'semantic_linkbacks_canonical', true );
		// ...or fall back to source
		if ( ! $url ) {
			$url = get_comment_meta( $comment->comment_ID, 'semantic_linkbacks_source', true );
		}
		// or fallback to author url
		if ( ! $url ) {
			$url = $comment->comment_author_url;
		}
		return $url;
	}


	/**
	 * Returns an array of linkback type slugs to their translated and pretty display versions
	 *
	 * @return array The array of translated linkback type names.
	 */
	public static function get_linkback_type_strings() {
		$strings = array(
				'mention'		=> __( 'Mention',	'linkbacks' ),
				'reply'			=> __( 'Reply',		'linkbacks' ),
				'repost'		=> __( 'Repost',	'linkbacks' ),
				'like'			=> __( 'Like',		'linkbacks' ),
				'tag'			=> __( 'Tag',		'linkbacks' ),
				'tagged' => __( 'Tagged', 'linkbacks' ), 
				'bookmark'		=> __( 'Bookmark', 'linkbacks' ),
				'rsvp'		=> __( 'RSVP', 'linkbacks' ) );
		return $strings;
	}

	public static function comment_args( $args ){
		$args['walker']= new Walker_Comment_Linkback();
		unset($args['callback']);
		unset($args['end-callback']);
		$args['short_ping'] = true;
		return $args;
	}

}

