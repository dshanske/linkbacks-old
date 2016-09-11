<?php
/**
 * Plugin Name: Linkbacks
 * Plugin URI:  https://github.com/dshanske/linkbacks
 * Description: Improvements of Linkbacks
 * Version:     0.1.0
 * Author:      David Shanske
 * Author URI:  https://david.shanske.com
 * License:     GPLv2+
 *
 * @package Indieweb
 */

/**
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License, version 2 or, at
 * your discretion, any later version, as published by the Free
 * Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
 */

/**
 * Init our plugin.
 *
 */
function linkbacks_init() {
	require_once( dirname( __FILE__ ) . '/includes/functions.php' );
	require_once( dirname( __FILE__ ) . '/includes/default-filters.php' );
}


add_action( 'plugins_loaded', 'linkbacks_init' );

