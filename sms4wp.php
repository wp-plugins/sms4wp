<?php
/**
Plugin Name: SMS for WordPress (sms4wp)
Plugin URI: https://sms4wp.com
Description: Send SMS messages from WordPress!
Version: 0.8.4
Author: sms4wp.com
Author URI: https://sms4wp.com/
*/
if ( !defined( 'ABSPATH' ) ) exit;

require_once( 'defines.php' );

require_once( SMS4WP_INC_CORE_PATH . '/sms4wp.init.php' );

if( is_admin() ) {
    //-- registers a plugin function to be run when the sms4wp is activated. --//
    register_activation_hook( __FILE__, 'sms4wp_install' );
}

?>
