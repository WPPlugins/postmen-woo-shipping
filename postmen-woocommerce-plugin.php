<?php
/*
   Plugin Name: Postmen Woocommerce Shipping
   Plugin URI: http://wordpress.org/extend/plugins/postmen-woocommerce-shipping/
   Version: 1.1.3
   Author: <a href="https://www.postmen.com/">Postmen</a>
   Description: Easiest way to integrate with multiple shipping carriers for online retailers and marketplaces of any size.
   Text Domain: postmen-woocommerce-shipping
   License: GPLv3
  */

/*
    "WordPress Plugin Template" Copyright (C) 2016 Michael Simpson  (email : michael.d.simpson@gmail.com)

    This following part of this file is part of WordPress Plugin Template for WordPress.

    WordPress Plugin Template is free software: you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation, either version 3 of the License, or
    (at your option) any later version.

    WordPress Plugin Template is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with Contact Form to Database Extension.
    If not, see http://www.gnu.org/licenses/gpl-3.0.html
*/

$PostmenWoocommercePlugin_minimalRequiredPhpVersion = '5.4';

/**
 * Check the PHP version and give a useful error message if the user's version is less than the required version
 * @return boolean true if version check passed. If false, triggers an error which WP will handle, by displaying
 * an error message on the Admin page
 */
function PostmenWoocommercePlugin_noticePhpVersionWrong() {
	global $PostmenWoocommercePlugin_minimalRequiredPhpVersion;
	echo '<div class="updated fade">' .
	     __( 'Error: plugin "Postmen Woocommerce Plugin" requires a newer version of PHP to be running.',
		     'postmen-woocommerce-plugin' ) .
	     '<br/>' . __( 'Minimal version of PHP required: ',
			'postmen-woocommerce-plugin' ) . '<strong>' . $PostmenWoocommercePlugin_minimalRequiredPhpVersion . '</strong>' .
	     '<br/>' . __( 'Your server\'s PHP version: ',
			'postmen-woocommerce-plugin' ) . '<strong>' . phpversion() . '</strong>' .
	     '</div>';
}


function PostmenWoocommercePlugin_PhpVersionCheck() {
	global $PostmenWoocommercePlugin_minimalRequiredPhpVersion;
	if ( version_compare( phpversion(), $PostmenWoocommercePlugin_minimalRequiredPhpVersion ) < 0 ) {
		add_action( 'admin_notices', 'PostmenWoocommercePlugin_noticePhpVersionWrong' );

		return false;
	}

	return true;
}


/**
 * Initialize internationalization (i18n) for this plugin.
 * References:
 *      http://codex.wordpress.org/I18n_for_WordPress_Developers
 *      http://www.wdmac.com/how-to-create-a-po-language-translation#more-631
 * @return void
 */
function PostmenWoocommercePlugin_i18n_init() {
	$pluginDir = dirname( plugin_basename( __FILE__ ) );
	load_plugin_textdomain( 'postmen-woocommerce-plugin', false, $pluginDir . '/languages/' );
}


//////////////////////////////////
// Run initialization
/////////////////////////////////

// Initialize i18n
add_action( 'plugins_loadedi', 'PostmenWoocommercePlugin_i18n_init' );

// Run the version check.
// If it is successful, continue with initialization for this plugin
if ( PostmenWoocommercePlugin_PhpVersionCheck() ) {
	// Only load and run the init function if we know PHP version can parse it
	include_once( 'postmen-woocommerce-plugin_init.php' );
	PostmenWoocommercePlugin_init( __FILE__ );
}
