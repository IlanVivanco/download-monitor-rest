<?php
/**
 * Plugin Name: Download Monitor API
 * Description: Extends the Download Monitor plugin and adds REST API endpoints.
 * Author: Ilán Vivanco
 * Author URI: https://ilanvivanco.com/
 * Version: 1.0.0
 * Text Domain: dmr
 *
 * @package Download_Monitor_REST_API
 */

use DMR\Download_Monitor_REST_API;

defined( 'ABSPATH' ) || die( 'No script kiddies please!' );

define( 'DMR_VERSION', '1.0.0' );
define( 'DMR_PLUGIN', plugin_basename( __FILE__ ) );

require_once plugin_dir_path( __FILE__ ) . 'vendor/autoload.php';
require_once plugin_dir_path( __FILE__ ) . 'classes/Download_Monitor_REST_API.php';

// Fetch plugin instance and store in global
$GLOBALS['download_monitor_rest_api'] = Download_Monitor_REST_API::get_instance();
