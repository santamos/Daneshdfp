<?php
/**
 * Public-facing functionality placeholder.
 *
 * @package Danesh\OnlineExam\Public
 */

namespace Danesh\OnlineExam\Public;

use function trailingslashit;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class PublicModule {
    /**
     * Register public-facing assets.
     */
    public function register_assets(): void {
        $asset_dir   = trailingslashit( DANESH_EXAM_PLUGIN_DIR ) . 'public/assets/';
        $asset_url   = trailingslashit( DANESH_EXAM_PLUGIN_URL ) . 'public/assets/';
        $version_js  = file_exists( $asset_dir . 'danesh-exam.js' ) ? (string) filemtime( $asset_dir . 'danesh-exam.js' ) : DANESH_EXAM_VERSION;
        $version_css = file_exists( $asset_dir . 'danesh-exam.css' ) ? (string) filemtime( $asset_dir . 'danesh-exam.css' ) : DANESH_EXAM_VERSION;

        wp_register_script(
            'danesh-exam-frontend',
            $asset_url . 'danesh-exam.js',
            array(),
            $version_js,
            true
        );

        wp_register_style(
            'danesh-exam-frontend',
            $asset_url . 'danesh-exam.css',
            array(),
            $version_css
        );
    }
}
