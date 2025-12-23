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
     * Install or update database schema.
     */
    public static function install(): void {
        global $wpdb;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $charset_collate = $wpdb->get_charset_collate();
        $table           = Tables::exams();

        $sql = "CREATE TABLE {$table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            title varchar(255) NOT NULL,
            duration_seconds int unsigned NOT NULL DEFAULT 0,
            created_by bigint(20) unsigned NOT NULL DEFAULT 0,
            status varchar(20) NOT NULL DEFAULT 'draft',
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY created_by (created_by),
            KEY status (status)
        ) {$charset_collate};";

        dbDelta( $sql );

        update_option( 'danesh_exam_db_version', '1.0.0' );
    }
}
