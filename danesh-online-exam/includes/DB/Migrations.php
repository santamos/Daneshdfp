<?php
/**
 * Database migrations and versioning.
 *
 * @package Danesh\OnlineExam\DB
 */

namespace Danesh\OnlineExam\DB;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Migrations {
    /**
     * Run database migrations when needed.
     */
    public function maybe_upgrade(): void {
        if ( ! $this->can_upgrade() ) {
            return;
        }

        $stored_version = (string) get_option( 'danesh_exam_db_version', '0' );

        if ( version_compare( $stored_version, Schema::DB_VERSION, '<' ) ) {
            Schema::install();
        }
    }

    /**
     * Whether the current request can trigger an upgrade.
     */
    private function can_upgrade(): bool {
        if ( ! is_admin() ) {
            return false;
        }

        return current_user_can( 'manage_options' ) || current_user_can( 'danesh_manage_exams' );
    }
}
