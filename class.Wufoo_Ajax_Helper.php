<?php

/**
 * Contains a class to define the Wufoo Ajax Plugin
 *
 * PHP Version 5.5+
 *
 * @category Wufoo Ajax_Helper
 * @package  Plugin
 * @author   Beau Watson <beau@beauwatson.com>
 * @license  Copyright 2015 Beau Watson. All rights reserved.
 * @link     http://docwatson.net
 */

// see https://github.com/wufoo/Wufoo-PHP-API-Wrapper
include 'vendor/WufooApiWrapper.php';

/**
 * A class to define the Wufoo Ajax Plugin
 *
 * @category Wufoo Ajax
 * @package  Plugin
 * @author   Beau Watson <beau@beauwatson.com>
 * @license  Copyright 2015 Beau Watson. All rights reserved.
 * @link     http://docwatson.net
 */
class Wufoo_Ajax_Helper {
	private $_api_key;
	private $_wufoo_id;
	private $_hashes;
	private $_hash_labels;
	private $_version = '1.1.0';


	/**
	 * Constructor function. Registers action and filter hooks.
	 *
	 * @access public
	 */
	public function __construct() {
		//set up properties
		$this->_api_key     = get_option( 'wa_wufoo_api_key' );
		$this->_wufoo_id    = get_option( 'wa_wufoo_id' );
		$this->_hashes      = unserialize( get_option( 'wa_wufoo_hashes' ) );
		$this->_hash_labels = unserialize( get_option( 'wa_wufoo_hash_labels' ) );

		/* Register action hooks. */
		add_action( 'wp_enqueue_scripts', array( $this, 'action_wp_enqueue_scripts' ), 1000 );
		add_action( 'wp_ajax_wufoo_post', array( $this, 'action_wp_ajax_wufoo_post' ) );
		add_action( 'wp_ajax_nopriv_wufoo_post', array( $this, 'action_wp_ajax_wufoo_post' ) );
		add_action( 'pre_get_posts', array( $this, 'hide_file_uploads_from_media' ), 15, 1 );
		add_action( 'wufoo_ajax_helper_remove_uploaded_files', array( $this, 'remove_uploaded_files_from_media' ) );

		//enable admin functions only if the user is an administrator
		if ( current_user_can( 'install_plugins' ) ) {
			add_action( 'admin_enqueue_scripts', array( $this, 'action_admin_enqueue_scripts' ) );
			add_action( 'admin_menu', array( $this, 'action_admin_menu' ) );
		}
	}

    /**
     * Function to enqueue administration area scripts.
     *
     * @access public
     * @return void
     */
    public function action_admin_enqueue_scripts() {

      /* Add JavaScript. */
      wp_enqueue_script( 'wa_script_admin', plugins_url( 'js/wa_script_admin.js',  __FILE__ ), false, $this->_version );
    }

	/**
	 * Function to hook menus into the WordPress admin panel.
	 *
	 * @access public
	 * @return void
	 */
	public function action_admin_menu() {
		add_submenu_page(
			'options-general.php',
			__( 'Wufoo Ajax Settings' ),
			__( 'Wufoo Ajax' ),
			'activate_plugins',
			'wufoo-ajax-settings',
			array( $this, 'admin_panel' )
		);
	}

	/**
	 * Function to hook Wufoo Ajax Settings Page into Wordpress Admin Panel
	 *
	 * @access public
	 * @return void
	 */
	public function admin_panel() {
		if ( ! current_user_can( 'install_plugins' ) ) {
			die( 'You do not have the correct permissions to use this page.' );
		}

		/* Update options if necessary. */
		if ( count( $_POST ) > 0 ) {
			/* Update global options. */
			update_option( 'wa_wufoo_api_key', $_POST['wa_wufoo_api_key'] );
			update_option( 'wa_wufoo_id', $_POST['wa_wufoo_id'] );
			update_option( 'wa_wufoo_hashes', serialize( array_filter( $_POST["wa_wufoo_hash"] ) ) );
			update_option( 'wa_wufoo_hash_labels', serialize( array_filter( $_POST["wa_wufoo_hash_label"] ) ) );
		}

		//set variables for the admin panel
		$wa_wufoo_api_key = $this->_api_key;
		$wa_wufoo_id      = $this->_wufoo_id;
		$wa_wufoo_hashes  = $this->_hashes;
		$wa_wufoo_labels  = $this->_hash_labels;

		include 'views/admin-panel.php';
	}

	/**
	 * Action hook to process forms from AJAX request
	 *
	 * @access public
	 * @return void
	 */
	public function action_wp_ajax_wufoo_post() {
		// look up the index of the hash of the form we're processing
		$index = $this->get_hash_by_label( $_POST['form_type'] );

		// Prepare fields for Wufoo Submission
		$fields = [];
		foreach ( $_POST as $key => $value ) {
			if ( $key === 'action' || $key === 'form_type' ) {
				continue;
			}

			$fields[] = new WufooSubmitField( $key, $value );
		}
		unset( $key, $value );

		// Do not forget about _FILES!
		if ( ! empty( $_FILES ) ) {
			if ( ! function_exists( 'media_handle_upload' ) ) {
				require_once( ABSPATH . 'wp-admin/includes/image.php' );
				require_once( ABSPATH . 'wp-admin/includes/file.php' );
				require_once( ABSPATH . 'wp-admin/includes/media.php' );
			}

			foreach ( $_FILES as $key => $FILE ) {
				$attachment_id = media_handle_upload( $key, 0 );
				if ( is_wp_error( $attachment_id ) ) {
					continue;
				}

				// Special mark to hide uploaded files from Media Library.
				// These files will be removed via scheduled events.
				update_post_meta( $attachment_id, '_wufoo_file', 1 );

				$fields[] = new WufooSubmitField( $key, [
					'path' => get_attached_file( $attachment_id ),
					'mime' => $FILE['type'],
					'name' => $FILE['name'],
				], true );
			}
			unset( $key, $FILE );
		}


		//set the hash
		$hash = $this->_hashes[ $index ];
		//create an API wrapper object
		$api = new WufooApiWrapper( $this->_api_key, $this->_wufoo_id );
		//make the API call
		$response = $api->entryPost( $hash, $fields );

		//record the response in JSON
		header( 'Content-Type: application/json' );
		echo json_encode( $response );
		wp_die();
	}

	/**
	 * Action hook to add frontend javascript
	 *
	 * @access public
	 * @return void
	 */
	public function action_wp_enqueue_scripts() {
		wp_enqueue_script( 'wufoo-ajax-script', plugins_url( 'js/wa_script.js', __FILE__ ), [ 'jquery' ], $this->_version, true );
		wp_localize_script( 'wufoo-ajax-script', 'wufooAjax', [
			'ajaxurl' => esc_url( admin_url( 'admin-ajax.php' ) ),
			'strings' => apply_filters( 'wufoo_ajax_helper_js_strings', [
				'submitSuccess' => esc_html__( 'Form submitted successfully.', 'wufoo_ajax_helper' ),
			] ),
		] );
	}

	/**
	 * Function to easily return an index of the hash associated with a label
	 *
	 * @param string $label
	 *
	 * @return int
	 */
	public function get_hash_by_label( $label ) {
		$index = array_search( $label, $this->_hash_labels );

		return $index;
	}

	/**
	 * Hide user uploads from Media Library
	 *
	 * @param WP_Query $q Query object.
	 */
	public function hide_file_uploads_from_media( $q ) {
		if ( is_admin()
		     && ( isset( $q->query_vars['post_type'] ) && $q->query_vars['post_type'] === 'attachment' )
		) {
			$q->set( 'meta_query', [
				[
					'key'     => '_wufoo_file',
					'compare' => 'NOT EXISTS',
				]
			] );
		}
	}

	/**
	 * Delete uploaded files from Media Library
	 *
	 * Runs one time a week via WP-Cron.
	 */
	public function remove_uploaded_files_from_media() {
		$files = get_posts( [
			'post_type'              => 'attachment',
			'post_status'            => 'any',
			'fields'                 => 'ids',
			'posts_per_page'         => 500,
			'cache_results'          => false,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
			'meta_query'             => [
				[
					'key'     => '_wufoo_file',
					'value'   => 1,
					'compare' => '=',
					'type'    => 'NUMERIC',
				]
			]
		] );

		if ( empty( $files ) ) {
			return;
		}

		foreach ( $files as $file ) {
			wp_delete_attachment( $file, true );
		}
	}
}
