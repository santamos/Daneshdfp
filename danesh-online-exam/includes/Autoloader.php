<?php
/**
 * Autoloader for the plugin.
 *
 * @package Danesh\OnlineExam
 */

namespace Danesh\OnlineExam;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Simple PSR-4-like autoloader.
 */
class Autoloader {
    /**
     * Prefix for the namespace.
     *
     * @var string
     */
    private static string $prefix = 'Danesh\\OnlineExam\\';

    /**
     * Base directory for the namespace prefix.
     *
     * @var string
     */
    private static string $base_dir;

    /**
     * Registers the autoloader with SPL.
     *
     * @param string $base_dir Base directory for namespace.
     *
     * @return void
     */
    public static function register( string $base_dir ): void {
        self::$base_dir = rtrim( $base_dir, '/\\' ) . '/';
        spl_autoload_register( array( __CLASS__, 'autoload' ) );
    }

    /**
     * Loads the class file if it exists.
     *
     * @param string $class Class name.
     *
     * @return void
     */
    private static function autoload( string $class ): void {
        if ( 0 !== strpos( $class, self::$prefix ) ) {
            return;
        }

        $relative_class = substr( $class, strlen( self::$prefix ) );
        $file           = self::$base_dir . str_replace( '\\', '/', $relative_class ) . '.php';

        if ( file_exists( $file ) ) {
            require $file;
        }
    }
}
