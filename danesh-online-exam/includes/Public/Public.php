<?php
/**
 * Public-facing functionality placeholder.
 *
 * @package Danesh\OnlineExam\Public
 */

namespace Danesh\OnlineExam\Public;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class PublicModule {
    /**
     * Placeholder for enqueuing public assets.
     */
    public function enqueue_assets(): void {
        // Intentionally left as a placeholder for future public assets.
    }
}

// Alias to maintain intended class name while avoiding reserved keyword conflicts.
class_alias( __NAMESPACE__ . '\\PublicModule', __NAMESPACE__ . '\\Public' );
