<?php
/**
 * Linkback Enhancement using Indieweb/php-comments.
 *
 * @package IndieWe
 * @subpackage Webmentions
 * @since 0.1.0
 */

if ( ! class_exists( 'Mf2\Parser' ) ) {
	require_once( dirname( dirname( __FILE__ ) ) . '/includes/libs/Parser.php' );
}

require_once( dirname( dirname( __FILE__ ) ) . '/includes/libs/cassis.php' );
require_once( dirname( dirname( __FILE__ ) ) . '/includes/libs/comments.php' );

if ( ! function_exists( 'Mf2\spaceSeparatedAttributeXpathSelector' ) ) {
	require_once( dirname( dirname( __FILE__ ) ) . '/includes/libs/functions.php' );
}
if ( ! class_exists( 'mf2_cleaner' ) ) {
	require_once( dirname( dirname( __FILE__ ) ) . '/includes/libs/class-mf2-cleaner.php' );
}

function indieweb_preprocess_linkback( $commentdata ) {
	$parsed = Mf2\parse( $commentdata['remote_source_original'], $commentdata['source'] );
	if( mf2_cleaner::isMicroformatCollection( $parsed ) ) {
		$entries = mf2_cleaner::findMicroformatsByType( $parsed, 'h-entry' );
		if ( $entries ) {
			$entry = $entries[0];
			$mf2comment = IndieWeb\comments\parse($entry, $commentdata['target']);
			if ( ! array_key_exists( 'comment_meta', $commentdata ) ) {
				$commentdata['comment_meta'] = array();
			}
			if ( ! empty( $mf2comment['text'] ) ) {
				$commentdata['comment_content'] = $mf2comment['text'];
			}
			if ( ! empty( $mf2comment['published'] ) ) {
				$date = new DateTime( $mf2comment['published'] );
				$date->setTimezone( new DateTimeZone( 'UTC' ) );
				$commentdata['comment_date_gmt'] = $date->format( 'Y-m-d H:i:s' );
				$date->setTimezone( new DateTimeZone( get_option('timezone_string') ) );
				$commentdata['comment_date'] = $date->format( 'Y-m-d H:i:s' );
			}
			if ( ! empty( $mf2comment['author'] ) ) {
				if ( ! empty( $mf2comment['author']['name'] ) ) {
					$commentdata['comment_author'] = $mf2comment['author']['name'];
				}
				if ( ! empty( $mf2comment['author']['photo'] ) ) {
					$commentdata['comment_meta']['_linkback_avatar'] = $mf2comment['author']['photo'];
				}
			}
			if ( ! empty( $mf2comment['type'] ) ) {
				$commentdata['comment_meta']['_linkback_type'] = $mf2comment['type'];
			}
			if ( ! empty( $mf2comment['url'] ) ) {
				$commentdata['comment_meta']['_linkback_url'] = $mf2comment['url'];
			}
		}
	}
	return $commentdata;
}

