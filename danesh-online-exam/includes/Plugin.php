<?php
/**
 * Core plugin class.
 *
 * @package Danesh\OnlineExam
 */

namespace Danesh\OnlineExam;

use Danesh\OnlineExam\Admin\Admin;
use Danesh\OnlineExam\Ajax\Ajax;
use Danesh\OnlineExam\DB\Migrations;
use Danesh\OnlineExam\Rest\Routes;
use Danesh\OnlineExam\Public\PublicModule;
use Danesh\OnlineExam\Public\Shortcodes;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Plugin {
    /**
     * Loader instance.
     *
     * @var Loader
     */
    protected Loader $loader;

    /**
     * Initialize the plugin.
     */
    public function __construct() {
        $this->loader = new Loader();

        $this->set_locale();
        $this->define_db_hooks();
        $this->define_admin_hooks();
        $this->define_public_hooks();
        $this->define_ajax_hooks();
        $this->define_rest_hooks();
    }

    /**
     * Load plugin textdomain.
     */
    private function set_locale(): void {
        $this->loader->add_action( 'init', $this, 'load_textdomain' );
    }

    /**
     * Registers database migration hooks.
     */
    private function define_db_hooks(): void {
        $migrations = new Migrations();

        $this->loader->add_action( 'admin_init', $migrations, 'maybe_upgrade' );
    }

    /**
     * Registers admin related hooks.
     */
    private function define_admin_hooks(): void {
        $admin = new Admin();
        // Placeholder for admin hooks.
        // $this->loader->add_action( 'admin_enqueue_scripts', $admin, 'enqueue_styles' );
    }

    /**
     * Registers public hooks and shortcodes.
     */
    private function define_public_hooks(): void {
        $public     = new PublicModule();
        $shortcodes = new Shortcodes();

        $this->loader->add_action( 'wp_enqueue_scripts', $public, 'register_assets', 5 );

        $this->loader->add_shortcode( 'danesh_exam', $shortcodes, 'render_exam' );
    }

    /**
     * Registers ajax related hooks.
     */
    private function define_ajax_hooks(): void {
        $ajax = new Ajax();
        // Placeholder for ajax actions.
        // $this->loader->add_action( 'wp_ajax_danesh_exam_action', $ajax, 'handle_authenticated_request' );
        // $this->loader->add_action( 'wp_ajax_nopriv_danesh_exam_action', $ajax, 'handle_public_request' );
    }

    /**
     * Registers REST API routes.
     */
    private function define_rest_hooks(): void {
        $routes = new Routes();

        $this->loader->add_action( 'rest_api_init', $routes, 'register_routes' );
    }

    /**
     * Execute all hooks with WordPress.
     */
    public function run(): void {
        $this->loader->run();
    }

    /**
     * Load plugin textdomain.
     */
    public function load_textdomain(): void {
        load_plugin_textdomain( 'danesh-online-exam', false, dirname( DANESH_EXAM_PLUGIN_BASENAME ) . '/languages/' );
    }
}
