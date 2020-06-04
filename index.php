<?php
/**
 * Wufoo AJAX API Helper
 *
 * @author Beau Watson <beau@beauwatson.com>
 * @author Dmytro Kovalov <chop.suet@gmail.com>
 * @author Createx Studio <contact@createx.studio>
 *
 * @link http://docwatson.net
 *
 * @wordpress-plugin
 * Plugin Name: Wufoo AJAX API Helper
 * Description: A plugin that leverages Wufoo's API Wrapper so you can submit to your forms over ajax
 * Version: 1.1.0
 * Author: Dmytro Kovalov, Beau Watson
 * Author URI: https://github.com/dmytro-kovalov/wufoo-ajax-helper
 *
 */

require_once 'class.Wufoo_Ajax_Helper.php';

/**
 * On plugin activation
 *
 * Schedule event to remove files uploaded during form submission from Media Library.
 */
add_action( 'activate_' . plugin_basename( __FILE__ ), function() {
	wp_clear_scheduled_hook( 'wufoo_ajax_helper_remove_uploaded_files' );
	wp_schedule_event( time(), 'weekly', 'wufoo_ajax_helper_remove_uploaded_files' );
} );

/**
 * Run plugin
 */
add_action( 'plugins_loaded', function () {
	$GLOBALS['wufoo_ajax_plugin'] = new Wufoo_Ajax_Helper();
} );
