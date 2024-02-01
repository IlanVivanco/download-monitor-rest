<?php
namespace DMR\admin;

use WP_Error;
use Exception;
use DLM_Download;
use WP_REST_Response;

/**
 * Interaction with the download repository of the download plugin.
 *
 * @package Download_Monitor_REST_API
 */
class Download_Repository {
	/**
	 * Download plugin repository for downloads.
	 *
	 * @var Object
	 */
	private $download_repository;

	/**
	 * Version plugin repository for downloads.
	 *
	 * @var Object
	 */
	private $version_repository;

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
		$this->mda_api             = $api;
		$this->download_repository = download_monitor()->service( 'download_repository' );
		$this->version_repository  = download_monitor()->service( 'version_repository' );
	}

	/**
	 * Registers the download endpoints.
	 *
	 * @return  Array  Endpoints settings.
	 */
	public function get_endpoints() {
		return array(

			// Fetch all downloads.
			'/downloads' => array(
				array(
					'methods'  => 'GET',
					'callback' => array( $this, 'get_downloads' ),
				),
			),

			// Fetch, update and delete a download.
			'/download/(?P<download_id>\d+)' => array(
				array(
					'methods'  => 'GET',
					'callback' => array( $this, 'get_download' ),
					'args'     => array(
						'download_id' => array(
							'required'    => true,
							'type'        => 'integer',
							'description' => 'The download\'s ID',
						),
					),
				),

				array(
					'methods'  => 'DELETE',
					'callback' => array( $this, 'delete_download' ),
					'args'     => array(
						'download_id' => array(
							'required'    => true,
							'type'        => 'integer',
							'description' => 'The download\'s ID',
						),
					),
				),

				array(
					'methods'  => 'PATCH',
					'callback' => array( $this, 'update_download' ),
					'args'     => array(
						'download_id' => array(
							'required'    => true,
							'type'        => 'integer',
							'description' => 'The download\'s ID',
						),
						'title' => array(
							'required'    => false,
							'type'        => 'string',
							'description' => 'The download\'s title',
						),
					),
				),
			),

			// Create a new download.
			'/download' => array(
				array(
					'methods'  => 'POST',
					'callback' => array( $this, 'store_download' ),
					'args'     => array(
						'title' => array(
							'required'    => true,
							'type'        => 'string',
							'description' => 'The download\'s title',
						),
					),
				),
			),

		);
	}

	/**
	 * Fetch all downloads
	 *
	 * @param  WP_REST_Request $req  Request object.
	 *
	 * @return  WP_REST_Response|WP_Error  Json response
	 */
	public function get_downloads( $req ) {
		$total_downloads = $this->download_repository->num_rows();
		$fetch_downloads = $this->download_repository->retrieve(
			array(
				'orderby' => 'id',
				'order'   => 'DESC',
			)
		);

		$download_items = array_map(
			function ( $download ) {
				$download_data = array(
					'download_id' => $download->get_id(),
					'title'       => $download->get_title(),
				);

				// Let's get the versions.
				$args                    = array( 'post_parent' => $download->get_id() );
				$download_versions       = $this->version_repository->retrieve( $args );
				$total_download_versions = $this->version_repository->num_rows( $args );

				if ( count( $download_versions ) > 0 ) {
					$download_data['versions'] = array(
						'count' => $total_download_versions,
						'items' => array_map(
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
							$download_versions
						),
					);
				}

				return $download_data;
			},
			$fetch_downloads
		);

		return new WP_REST_Response(
			array(
				'count' => $total_downloads,
				'items' => $download_items,
			)
		);
	}

	/**
	 * Fetch a single download
	 *
	 * @param  WP_REST_Request $req  Request object.
	 *
	 * @return  WP_REST_Response|WP_Error  Json response
	 */
	public function get_download( $req ) {
		$params = $req->get_params();

		try {
			$download = $this->download_repository->retrieve_single( $params['download_id'] );
		} catch ( Exception $e ) {
			return new WP_Error( 'download_not_found', 'Download does not exist.', array( 'status' => 404 ) );
		}

		$download_data = array(
			'download_id' => $download->get_id(),
			'title'       => $download->get_title(),
		);

		// Let's get the versions.
		$args                    = array( 'post_parent' => $download->get_id() );
		$download_versions       = $this->version_repository->retrieve( $args );
		$total_download_versions = $this->version_repository->num_rows( $args );

		if ( count( $download_versions ) > 0 ) {
			$download_data['versions'] = array(
				'count' => $total_download_versions,
				'items' => array_map(
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
					$download_versions
				),
			);
		}

		return new WP_REST_Response( $download_data );
	}

	/**
	 * Delete a single download
	 *
	 * @param  WP_REST_Request $req  Request object.
	 *
	 * @return  WP_REST_Response|WP_Error  Json response
	 */
	public function delete_download( $req ) {
		$params = $req->get_params();

		$download = get_post( $params['download_id'] );

		if ( ! $download || 'dlm_download' !== $download->post_type ) {
			return new WP_Error( 'download_not_found', 'Download does not exist.', array( 'status' => 404 ) );
		}

		wp_delete_post( $download->ID, true );

		return new WP_REST_Response( null, 204 );
	}

	/**
	 * Create a new download
	 *
	 * @param  WP_REST_Request $req  Request object.
	 *
	 * @return  WP_REST_Response|WP_Error  Json response
	 */
	public function store_download( $req ) {
		$params = $req->get_params();

		if ( ! empty( $params['title'] ) ) {

			$download = new DLM_Download();
			$download->set_title( $params['title'] );
			$download->set_author( $this->mda_api->author_id );
			$download->set_status( 'publish' );

			try {
				$this->download_repository->persist( $download );
				update_post_meta( $download->get_id(), '_redirect_only', 'yes' );
			} catch ( Exception $e ) {
				return new WP_Error( 'download_error', 'Unable to create a download item.', array( 'status' => 400 ) );
			}

			return new WP_REST_Response(
				array(
					'download_id' => $download->get_id(),
					'title'       => $download->get_title(),
				)
			);

		} else {
			return new WP_Error( 'rest_invalid_param', esc_html__( 'You must provide a title.', 'dmr' ), array( 'status' => 400 ) );
		}
	}

	/**
	 * Update a download
	 *
	 * @param  WP_REST_Request $req  Request object.
	 *
	 * @return  WP_REST_Response|WP_Error  Json response
	 */
	public function update_download( $req ) {
		$params = $req->get_params();

		// Let's check if download exists first.
		try {
			$this->download_repository->retrieve_single( $params['download_id'] );
		} catch ( Exception $e ) {
			return new WP_Error( 'download_not_found', 'Download does not exist.', array( 'status' => 404 ) );
		}

		$download = new DLM_Download();
		$download->set_id( $params['download_id'] );
		$download->set_title( $params['title'] );
		$download->set_author( $this->mda_api->author_id );
		$download->set_status( 'publish' );

		try {
			$this->download_repository->persist( $download );
		} catch ( Exception $e ) {
			return new WP_Error( 'download_error', 'Unable to update the download item.', array( 'status' => 400 ) );
		}

		$download_data = array(
			'download_id' => $download->get_id(),
			'title'       => $download->get_title(),
		);

		// Let's get the versions.
		$args                    = array( 'post_parent' => $download->get_id() );
		$download_versions       = $this->version_repository->retrieve( $args );
		$total_download_versions = $this->version_repository->num_rows( $args );

		if ( count( $download_versions ) > 0 ) {
			$download_data['versions'] = array(
				'count' => $total_download_versions,
				'items' => array_map(
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
					$download_versions,
				),
			);
		}

		return new WP_REST_Response( $download_data );
	}
}
