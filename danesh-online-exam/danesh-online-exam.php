<?php
/**
 * Plugin Name:       Danesh Online Exam
 * Plugin URI:        https://example.com
 * Description:       Scaffold for building and running online quizzes and exams.
 * Version:           1.0.0
 * Author:            Danesh
 * Author URI:        https://example.com
 * Text Domain:       danesh-online-exam
 * Domain Path:       /languages
 * Requires at least: 6.2
 * Requires PHP:      8.0
 */

use Danesh\OnlineExam\Activator;
use Danesh\OnlineExam\Autoloader;
use Danesh\OnlineExam\Deactivator;
use Danesh\OnlineExam\Plugin;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'DANESH_EXAM_VERSION', '1.0.0' );
define( 'DANESH_EXAM_PLUGIN_FILE', __FILE__ );
define( 'DANESH_EXAM_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
define( 'DANESH_EXAM_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'DANESH_EXAM_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

require_once DANESH_EXAM_PLUGIN_DIR . 'includes/Autoloader.php';

Autoloader::register( DANESH_EXAM_PLUGIN_DIR . 'includes/' );

register_activation_hook( __FILE__, array( Activator::class, 'activate' ) );
register_deactivation_hook( __FILE__, array( Deactivator::class, 'deactivate' ) );

/**
 * Load plugin text domain.
 *
 * @return void
 */
function danesh_exam_load_textdomain(): void {
    load_plugin_textdomain( 'danesh-online-exam', false, dirname( DANESH_EXAM_PLUGIN_BASENAME ) . '/languages/' );
}
add_action( 'plugins_loaded', 'danesh_exam_load_textdomain' );

/**
 * Begins execution of the plugin.
 *
 * @return void
 */
function danesh_exam_run(): void {
    $plugin = new Plugin();
    $plugin->run();
}
add_action( 'plugins_loaded', 'danesh_exam_run' );
