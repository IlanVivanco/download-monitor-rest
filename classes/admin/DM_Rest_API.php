<?php
namespace DMR\admin;

use WP_Error;
use DMR\admin\Download_Repository;
use DMR\admin\Version_Repository;

/**
 * Handles WP Rest API endpoints
 *
 * @package Download_Monitor_REST_API
 */
class DM_Rest_API {

	/**
	 * DMR Rest API endpoints.
	 *
	 * @var Array
	 */
	private $endpoints;

	/**
	 * DMR Rest API namespace.
	 *
	 * @var String
	 */
	private $namespace;

	/**
	 * Download plugin repository for downloads.
	 *
	 * @var Object
	 */
	private $download_repository;

	/**
	 * Download plugin repository for versions.
	 *
	 * @var Object
	 */
	private $version_repository;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->namespace           = 'dmr/v1';
		$this->download_repository = new Download_Repository( $this );
		$this->version_repository  = new Version_Repository( $this );
		$this->endpoints           = array();

		add_action( 'rest_api_init', array( $this, 'register_endpoints' ) );
		add_filter( 'posts_orderby', array( $this, 'modify_download_version_orderby' ), 10, 2 );
	}

	/**
	 * Prepare the endpoins.
	 */
	public function get_endpoints() {
		$endpoints = array_merge(
			$this->download_repository->get_endpoints(),
			$this->version_repository->get_endpoints()
		);

		/**
		 * Filters the array of available REST API endpoints.
		 */
		$endpoints = apply_filters( 'dmr_endpoints', $endpoints );

		/**
		 * Apply default configuration if not set on the endpoints.
		 */
		foreach ( $endpoints as &$handlers ) {
			foreach ( $handlers as &$handler ) {
				// Basic authentication.
				if ( ! isset( $handler['permission_callback'] ) ) {
					$handler['permission_callback'] = array( $this, 'is_user_allowed' );
				}

				// Arguments sanitization.
				if ( isset( $handler['args'] ) ) {
					foreach ( $handler['args'] as $arg_name => &$arg ) {
						if ( ! isset( $arg['sanitize_callback'] ) ) {
							$arg['sanitize_callback'] = array( $this, 'sanitize_arg' );
						}
					}
				} else {
					$handler['args']['sanitize_callback'] = array( $this, 'sanitize_arg' );
				}
			}
		}

		return $endpoints;
	}

	/**
	 * Modifies the default orderby for download versions.
	 *
	 * @param String   $orderby  Current orderby.
	 * @param WP_Query $query    Current query.
	 *
	 * @return String  Modified orderby.
	 */
	public function modify_download_version_orderby( $orderby, $query ) {
		global $wpdb;

		if ( 'dlm_download_version' === $query->get( 'post_type' ) ) {
			$orderby = "$wpdb->posts.post_date DESC";
		}

		return $orderby;
	}

	/**
	 * Register REST routes
	 */
	public function register_endpoints() {
		$endpoints = $this->get_endpoints();

		foreach ( $endpoints as $endpoint => &$handlers ) {
			register_rest_route( $this->namespace, $endpoint, $handlers );
		}
	}

	/**
	 * Basic authentication handler for the REST API
	 *
	 * @return  Boolean True if the user is authorized, false otherwise.
	 */
	public function is_user_allowed() {
		return current_user_can( 'administrator' );
	}

	/**
	 * Sanitize a request argument based on details registered to the route.
	 *
	 * @param  mixed $value   Value of the 'filter' argument.
	 * @return WP_Error|boolean
	 */
	public function sanitize_arg( $value ) {
		return sanitize_text_field( $value );
	}
}
