<?php
namespace DMR;

use DMR\admin\DM_Rest_API;

/**
 * Main class
 *
 * @package Download_Monitor_REST_API
 */
class Download_Monitor_REST_API {

	/**
	 * Minimum PHP Version
	 *
	 * @var string Minimum PHP required to run the plugin.
	 * @since 1.0.0
	 */
	const MINIMUM_PHP_VERSION = '8.0';

	/**
	 * Minimum Download plugin Version
	 *
	 * @var string Minimum Download plugin version required to run the plugin.
	 * @since 1.0.0
	 */
	const MINIMUM_DOWNLOAD_PLUGIN_VERSION = '4.4.13';

	/**
	 * Plugin Name
	 *
	 * @since 1.0.0
	 * @var string The plugin name.
	 */
	private $name;

	/**
	 * Plugin Version
	 *
	 * @since 1.0.0
	 * @var string The plugin version.
	 */
	private $version;

	/**
	 * Base URL of the plugin
	 *
	 * @since 1.0.0
	 * @var String Base URL of the plugin for loading assets.
	 */
	private $base_url;

	/**
	 * Base path of the plugin
	 *
	 * @since 1.0.0
	 * @var String Base Path of the plugin for including files.
	 */
	private $base_path;

	/**
	 * The unique instance of the plugin.
	 *
	 * @var Download_Monitor_REST_API
	 */
	private static $instance;

	/**
	 * Gets the instance of our plugin.
	 *
	 * @return Download_Monitor_REST_API
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		$this->base_url  = plugin_dir_url( __DIR__ );
		$this->base_path = plugin_dir_path( __FILE__ );
		$this->name      = __( 'Download Monitor REST API', 'dmr' );
		$this->version   = DMR_VERSION;

		add_action( 'plugins_loaded', array( $this, 'check_plugin_compatibility' ) );
	}

	/**
	 * Main class initiator
	 */
	protected function init() {
		$this->i18n();
		new DM_Rest_API();
	}

	/**
	 * Load Textdomain
	 * Load plugin localization files.
	 *
	 * @since 1.0.0
	 */
	public function i18n() {
		load_plugin_textdomain( 'dmr' );
	}

	/**
	 * On Plugins Loaded
	 * Performs some compatibility checks before loading the plugin.
	 *
	 * Checks if the Download Monitor is installed and if it meets the plugin's minimum requirement.
	 * Checks if the installed PHP version meets the plugin's minimum requirement.
	 *
	 * @since 1.0.0
	 */
	public function check_plugin_compatibility() {
		global $download_monitor;

		if ( empty( $download_monitor ) ) {
			add_action( 'admin_notices', array( $this, 'admin_notice_missing_main_plugin' ) );
			deactivate_plugins( DMR_PLUGIN, true );
			return;
		}

		if ( ! version_compare( DLM_VERSION, self::MINIMUM_DOWNLOAD_PLUGIN_VERSION, '>=' ) ) {
			add_action( 'admin_notices', array( $this, 'admin_notice_minimum_download_plugin_version' ) );
			deactivate_plugins( DMR_PLUGIN, true );
			return;
		}

		if ( version_compare( PHP_VERSION, self::MINIMUM_PHP_VERSION, '<' ) ) {
			add_action( 'admin_notices', array( $this, 'admin_notice_minimum_php_version' ) );
			deactivate_plugins( DMR_PLUGIN, true );
			return;
		}

		// Everything is ok... Let's go!
		$this->init();
	}

	/**
	 * Admin notice
	 * Warning when the site doesn't have Download Monitor installed or activated.
	 *
	 * @since 1.0.0
	 */
	public function admin_notice_missing_main_plugin() {
		$message = sprintf(
			/* translators: 1: Plugin name 2: Download Monitor */
			esc_html__( '%1$s requires %2$s to be installed and activated.', 'dmr' ),
			'<strong>' . $this->name . '</strong>',
			'<strong>' . esc_html__( 'Download Monitor', 'dmr' ) . '</strong>'
		);

		printf( '<div class="notice notice-error"><p>%1$s</p></div>', $message );
	}

	/**
	 * Admin notice
	 * Warning when the site doesn't have a minimum required Download Monitor version.
	 *
	 * @since 1.0.0
	 */
	public function admin_notice_minimum_download_plugin_version() {
		$message = sprintf(
			/* translators: 1: Plugin name 2: Download Monitor 3: Required Download Monitor version */
			esc_html__( '%1$s requires %2$s version %3$s or greater.', 'dmr' ),
			'<strong>' . $this->name . '</strong>',
			'<strong>' . esc_html__( 'Download Monitor', 'dmr' ) . '</strong>',
			self::MINIMUM_DOWNLOAD_PLUGIN_VERSION
		);

		printf( '<div class="notice notice-error"><p>%1$s</p></div>', $message );
	}

	/**
	 * Admin notice
	 * Warning when the site doesn't have a minimum required PHP version.
	 *
	 * @since 1.0.0
	 */
	public function admin_notice_minimum_php_version() {
		$message = sprintf(
			/* translators: 1: Plugin name 2: PHP 3: Required PHP version */
			esc_html__( '%1$s requires %2$s version %3$s or greater.', 'dmr' ),
			'<strong>' . $this->name . '</strong>',
			'<strong>' . esc_html__( 'PHP', 'dmr' ) . '</strong>',
			self::MINIMUM_PHP_VERSION
		);

		printf( '<div class="notice notice-error"><p>%1$s</p></div>', $message );
	}
}
