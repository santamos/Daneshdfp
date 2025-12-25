<?php
/**
 * Database schema management.
 *
 * @package Danesh\OnlineExam\DB
 */

namespace Danesh\OnlineExam\DB;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Schema {

    /**
     * Current database version.
     */
    public const DB_VERSION = '1.2.0';

    /**
     * Install or update database schema.
     */
    public static function install(): void {
        global $wpdb;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $charset_collate = $wpdb->get_charset_collate();

        $sql = array(
            self::get_exams_schema( $charset_collate ),
            self::get_questions_schema( $charset_collate ),
            self::get_choices_schema( $charset_collate ),
            self::get_attempts_schema( $charset_collate ),
            self::get_attempt_answers_schema( $charset_collate ),
        );

        foreach ( $sql as $statement ) {
            dbDelta( $statement );
        }

        update_option( 'danesh_exam_db_version', self::DB_VERSION );
    }

    /**
     * Exams table schema.
     *
     * @param string $charset_collate Charset collate string.
     */
    private static function get_exams_schema( string $charset_collate ): string {
        $table = Tables::exams();

        return "CREATE TABLE {$table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            title varchar(255) NOT NULL,
            duration_seconds int(10) unsigned NOT NULL DEFAULT 0,
            status varchar(20) NOT NULL DEFAULT 'draft',
            created_by bigint(20) unsigned NOT NULL DEFAULT 0,
            created_at datetime NOT NULL,
            updated_at datetime DEFAULT NULL,
            PRIMARY KEY  (id),
            KEY created_by (created_by),
            KEY status (status)
        ) {$charset_collate};";
    }

    /**
     * Questions table schema.
     *
     * @param string $charset_collate Charset collate string.
     */
    private static function get_questions_schema( string $charset_collate ): string {
        $table = Tables::questions();

        return "CREATE TABLE {$table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            exam_id bigint(20) unsigned NOT NULL,
            type varchar(20) NOT NULL DEFAULT 'mcq',
            prompt longtext NOT NULL,
            points decimal(8,2) NOT NULL DEFAULT 1.00,
            position int(10) unsigned NOT NULL DEFAULT 0,
            created_at datetime NOT NULL,
            PRIMARY KEY  (id),
            KEY exam_id (exam_id),
            KEY exam_pos (exam_id, position)
        ) {$charset_collate};";
    }

    /**
     * Choices table schema.
     *
     * @param string $charset_collate Charset collate string.
     */
    private static function get_choices_schema( string $charset_collate ): string {
        $table = Tables::choices();

        return "CREATE TABLE {$table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            question_id bigint(20) unsigned NOT NULL,
            choice_text longtext NOT NULL,
            is_correct tinyint(1) NOT NULL DEFAULT 0,
            position int(10) unsigned NOT NULL DEFAULT 0,
            PRIMARY KEY  (id),
            KEY question_id (question_id),
            KEY q_pos (question_id, position)
        ) {$charset_collate};";
    }

    /**
     * Attempts table schema.
     *
     * @param string $charset_collate Charset collate string.
     */
    private static function get_attempts_schema( string $charset_collate ): string {
        $table = Tables::attempts();

        return "CREATE TABLE {$table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            exam_id bigint(20) unsigned NOT NULL,
            user_id bigint(20) unsigned NOT NULL,
            started_at datetime NOT NULL,
            expires_at datetime NULL,
            finished_at datetime NULL,
            status varchar(20) NOT NULL DEFAULT 'in_progress',
            score decimal(10,2) NULL,
            total_points decimal(10,2) NULL,
            PRIMARY KEY  (id),
            KEY exam_user (exam_id, user_id),
            KEY user_status (user_id, status)
        ) {$charset_collate};";
    }

    /**
     * Attempt answers table schema.
     *
     * @param string $charset_collate Charset collate string.
     */
    private static function get_attempt_answers_schema( string $charset_collate ): string {
        $table = Tables::attempt_answers();

        return "CREATE TABLE {$table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            attempt_id bigint(20) unsigned NOT NULL,
            question_id bigint(20) unsigned NOT NULL,
            choice_id bigint(20) unsigned NULL,
            is_correct tinyint(1) NULL,
            answered_at datetime NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY attempt_question (attempt_id, question_id),
            KEY attempt_id (attempt_id)
        ) {$charset_collate};";
    }
}
