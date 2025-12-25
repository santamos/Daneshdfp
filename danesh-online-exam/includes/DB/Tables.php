<?php
/**
 * Database table helpers.
 *
 * @package Danesh\OnlineExam\DB
 */

namespace Danesh\OnlineExam\DB;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Tables {
    /**
     * Get the exams table name.
     */
    public static function exams(): string {
        global $wpdb;

        return $wpdb->prefix . 'danesh_exams';
    }

    /**
     * Get the choices table name.
     */
    public static function choices(): string {
        global $wpdb;

        return $wpdb->prefix . 'danesh_choices';
    }

    /**
     * Get the attempt answers table name.
     */
    public static function attempt_answers(): string {
        global $wpdb;

        return $wpdb->prefix . 'danesh_attempt_answers';
    }

    /**
     * Get the questions table name.
     */
    public static function questions(): string {
        global $wpdb;

        return $wpdb->prefix . 'danesh_questions';
    }

    /**
     * Get the attempts table name.
     */
    public static function attempts(): string {
        global $wpdb;

        return $wpdb->prefix . 'danesh_attempts';
    }
}
