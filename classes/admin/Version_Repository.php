<?php
namespace DMR\admin;

use DateTime;
use WP_Error;
use Exception;
use WP_REST_Response;
use DLM_Download_Version;

/**
 * Interaction with the version repository of the download plugin.
 *
 * @package Download_Monitor_REST_API
 */
class Version_Repository {

	/**
	 * Download plugin repository for version.
	 *
	 * @var Object
	 */
	private $version_repository;

	/**
	 * Download plugin manager for transients.
	 *
	 * @var Object
	 */
	private $transient_manager;

	/**
	 * DMR Rest API ref.
	 *
	 * @var DMR_Rest_API
	 */
	private $mda_api;

	/**
	 * Constructor.
	 *
	 * @param DMR_Rest_API $api DMR Rest API ref.
	 */
	public function __construct( $api ) {
		$this->mda_api            = $api;
		$this->version_repository = download_monitor()->service( 'version_repository' );
		$this->transient_manager  = download_monitor()->service( 'transient_manager' );
	}

	/**
	 * Registers the download endpoints.
	 *
	 * @return  Array  Endpoints settings.
	 */
	public function get_endpoints() {
		return array(

			// Fetch all versions from a download id.
			'/versions' => array(
				array(
					'methods'  => 'GET',
					'callback' => array( $this, 'get_versions' ),
					'args'     => array(
						'download_id' => array(
							'required'    => true,
							'type'        => 'integer',
							'description' => 'The download\'s ID',
						),
					),

				),
			),

			// Fetch, update and delete a version.
			'/version/(?P<version_id>\d+)' => array(
				array(
					'methods'  => 'GET',
					'callback' => array( $this, 'get_version' ),
					'args'     => array(
						'version_id' => array(
							'required'    => true,
							'type'        => 'integer',
							'description' => 'The version\'s ID',
						),
					),
				),

				array(
					'methods'  => 'DELETE',
					'callback' => array( $this, 'delete_version' ),
					'args'     => array(
						'version_id' => array(
							'required'    => true,
							'type'        => 'integer',
							'description' => 'The version\'s ID',
						),
					),
				),

				array(
					'methods'  => 'PATCH',
					'callback' => array( $this, 'update_version' ),
					'args'     => array(
						'version_id' => array(
							'required'    => true,
							'type'        => 'integer',
							'description' => 'The version\'s ID',
						),
						'version' => array(
							'required'    => true,
							'type'        => 'string',
							'description' => 'The version\'s number',
						),
						'url' => array(
							'required'    => true,
							'type'        => 'string',
							'description' => 'The version\'s URL',
						),
					),
				),
			),

			// Create a new version.
			'/version' => array(
				array(
					'methods'  => 'POST',
					'callback' => array( $this, 'store_version' ),
					'args'     => array(
						'download_id' => array(
							'required'    => true,
							'type'        => 'integer',
							'description' => 'The download\'s ID',
						),
						'version' => array(
							'required'    => true,
							'type'        => 'string',
							'description' => 'The version\'s number',
						),
						'url' => array(
							'required'    => true,
							'type'        => 'string',
							'description' => 'The version\'s URL',
						),
					),
				),
			),

		);
	}

	/**
	 * Fetch all version
	 *
	 * @param  WP_REST_Request $req  Request object.
	 *
	 * @return  WP_REST_Response|WP_Error  Json response
	 */
	public function get_versions( $req ) {
		$params = $req->get_params();

		$args = array( 'post_parent' => $params['download_id'] );

		$total_versions = $this->version_repository->num_rows( $args );
		$fetch_versions = $this->version_repository->retrieve( $args );

		if ( count( $fetch_versions ) <= 0 ) {
			return new WP_Error( 'download_not_found', 'Download does not exist.', array( 'status' => 404 ) );
		}

		// Let's get the versions.
		$version_items = array_map(
			function ( $version ) {
				return array(
					'download_id' => $version->get_download_id(),
					'version_id'  => $version->get_id(),
					'date'        => $version->get_date()->format( 'c' ),
					'version'     => $version->get_version(),
					'url'         => $version->get_url(),
					'filename'    => $version->get_filename(),
					'filetype'    => $version->get_filetype(),
					'downloads'   => $version->get_download_count(),
				);
			},
			$fetch_versions
		);

		return new WP_REST_Response(
			array(
				'count'    => $total_versions,
				'versions' => $version_items,
			)
		);
	}

	/**
	 * Fetch a single version
	 *
	 * @param  WP_REST_Request $req  Request object.
	 *
	 * @return  WP_REST_Response|WP_Error  Json response
	 */
	public function get_version( $req ) {
		$params = $req->get_params();

		try {
			$version = $this->version_repository->retrieve_single( $params['version_id'] );
		} catch ( Exception $e ) {
			return new WP_Error( 'version_not_found', 'Download does not exist.', array( 'status' => 404 ) );
		}

		return new WP_REST_Response(
			array(
				'download_id' => $version->get_download_id(),
				'version_id'  => $version->get_id(),
				'date'        => $version->get_date()->format( 'c' ),
				'version'     => $version->get_version(),
				'url'         => $version->get_url(),
				'filename'    => $version->get_filename(),
				'filetype'    => $version->get_filetype(),
				'downloads'   => $version->get_download_count(),
			)
		);
	}

	/**
	 * Delete a single version
	 *
	 * @param  WP_REST_Request $req  Request object.
	 *
	 * @return  WP_REST_Response|WP_Error  Json response
	 */
	public function delete_version( $req ) {
		$params = $req->get_params();

		$version = get_post( $params['version_id'] );

		if ( ! $version || 'dlm_download_version' !== $version->post_type ) {
			return new WP_Error( 'version_not_found', 'Version does not exist.', array( 'status' => 404 ) );
		}

		$this->transient_manager->clear_versions_transient( $version->post_parent );
		wp_delete_post( $version->ID, true );

		return new WP_REST_Response( null, 204 );
	}

	/**
	 * Create a new version
	 *
	 * @param  WP_REST_Request $req  Request object.
	 *
	 * @return  WP_REST_Response|WP_Error  Json response
	 */
	public function store_version( $req ) {
		$params = $req->get_params();

		if ( ! empty( $params['download_id'] ) ) {
			$download_id = intval( $params['download_id'] );

			$version = new DLM_Download_Version();
			$version->set_download_id( $download_id );
			$version->set_version( $params['version'] );
			$version->set_mirrors( array( $params['url'] ) );
			$version->set_author( $this->mda_api->author_id );
			$version->set_date( new DateTime( current_time( 'mysql' ) ) );

			try {
				$this->version_repository->persist( $version );
				$this->transient_manager->clear_versions_transient( $download_id );

				// Get latest data.
				$version = $this->version_repository->retrieve_single( $version->get_id() );
			} catch ( Exception $e ) {
				return new WP_Error( 'version_error', 'Unable to create a version item.', array( 'status' => 400 ) );
			}

			return new WP_REST_Response(
				array(
					'download_id' => $version->get_download_id(),
					'version_id'  => $version->get_id(),
					'date'        => $version->get_date()->format( 'c' ),
					'version'     => $version->get_version(),
					'url'         => $version->get_url(),
					'filename'    => $version->get_filename(),
					'filetype'    => $version->get_filetype(),
					'downloads'   => $version->get_download_count(),
				)
			);

		} else {
			return new WP_Error( 'rest_invalid_param', esc_html__( 'You must provide a download_id.', 'dmr' ), array( 'status' => 400 ) );
		}
	}

	/**
	 * Update a version
	 *
	 * @param  WP_REST_Request $req  Request object.
	 *
	 * @return  WP_REST_Response|WP_Error  Json response
	 */
	public function update_version( $req ) {
		$params = $req->get_params();

		if ( ! empty( $params['version_id'] ) ) {
			// Let's check if version exists first.
			try {
				$version = $this->version_repository->retrieve_single( $params['version_id'] );
			} catch ( Exception $e ) {
				return new WP_Error( 'version_not_found', 'Version does not exist.', array( 'status' => 404 ) );
			}

			$version->set_version( $params['version'] );
			$version->set_mirrors( array( $params['url'] ) );
			$version->set_date( new DateTime( current_time( 'mysql' ) ) );

			try {
				$this->version_repository->persist( $version );
				$this->transient_manager->clear_versions_transient( $version->get_download_id() );

				// Get latest data.
				$version = $this->version_repository->retrieve_single( $version->get_id() );
			} catch ( Exception $e ) {
				return new WP_Error( 'version_error', 'Unable to update the version item.', array( 'status' => 400 ) );
			}

			$version_data = array(
				'download_id' => $version->get_download_id(),
				'version_id'  => $version->get_id(),
				'date'        => $version->get_date()->format( 'c' ),
				'version'     => $version->get_version(),
				'url'         => $version->get_url(),
				'filename'    => $version->get_filename(),
				'filetype'    => $version->get_filetype(),
				'downloads'   => $version->get_download_count(),
			);

			return new WP_REST_Response( $version_data );
		} else {
			return new WP_Error( 'rest_invalid_param', esc_html__( 'You must provide a download_id.', 'dmr' ), array( 'status' => 400 ) );
		}
	}
}
