<?php
/**
 * Global Functions
 *
 * @package IndieWeb
 * @subpackage Webmentions
 * @since 0.1.0
 */

/**
 * Return Webmention Endpoint
 *
 * @return string the Webmention endpoint
 */
function get_webmention_endpoint() {
	return Webmention_Controller::get_webmention_endpoint();
}
