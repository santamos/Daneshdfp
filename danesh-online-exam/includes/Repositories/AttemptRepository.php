<?php
/**
 * Attempt repository.
 *
 * @package Danesh\OnlineExam\Repositories
 */

namespace Danesh\OnlineExam\Repositories;

use Danesh\OnlineExam\DB\Tables;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class AttemptRepository {
    /**
     * Create a new attempt.
     *
     * @param int         $exam_id    Exam ID.
     * @param int         $user_id    User ID.
     * @param string      $started_at Start time.
     * @param string|null $expires_at Expiration time.
     * @param string      $status     Attempt status.
     *
     * @return int
     */
    public function create_attempt( int $exam_id, int $user_id, string $started_at, ?string $expires_at, string $status ): int {
        global $wpdb;

        $table       = Tables::attempts();
        $insert_data = array(
            'exam_id'    => absint( $exam_id ),
            'user_id'    => absint( $user_id ),
            'started_at' => $started_at,
            'expires_at' => $expires_at,
            'status'     => sanitize_text_field( $status ),
        );

        $formats = array( '%d', '%d', '%s', '%s', '%s' );

        $inserted = $wpdb->insert( $table, $insert_data, $formats );

        if ( false === $inserted ) {
            return 0;
        }

        return (int) $wpdb->insert_id;
    }

    /**
     * Get an attempt by ID.
     *
     * @param int $id Attempt ID.
     *
     * @return array|null
     */
    public function get_attempt( int $id ): ?array {
        global $wpdb;

        $table = Tables::attempts();
        $query = $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id );
        $row   = $wpdb->get_row( $query, ARRAY_A );

        return $row ?: null;
    }

    /**
     * Mark an attempt as submitted.
     *
     * @param int    $id        Attempt ID.
     * @param string $ended_at  Submission time.
     * @param float  $score     Earned score.
     * @param float  $max_score Maximum possible score.
     *
     * @return bool
     */
    public function set_submitted( int $id, string $ended_at, float $score, float $max_score ): bool {
        global $wpdb;

        $table   = Tables::attempts();
        $updated = $wpdb->update(
            $table,
            array(
                'finished_at'  => $ended_at,
                'status'       => 'submitted',
                'score'        => $score,
                'total_points' => $max_score,
            ),
            array( 'id' => $id ),
            array( '%s', '%s', '%f', '%f' ),
            array( '%d' )
        );

        return false !== $updated;
    }

    /**
     * List attempts for a user and exam.
     *
     * @param int $exam_id Exam ID.
     * @param int $user_id User ID.
     *
     * @return array
     */
    public function list_attempts_by_user( int $exam_id, int $user_id ): array {
        global $wpdb;

        $table = Tables::attempts();
        $query = $wpdb->prepare(
            "SELECT * FROM {$table} WHERE exam_id = %d AND user_id = %d ORDER BY id DESC",
            $exam_id,
            $user_id
        );

        return $wpdb->get_results( $query, ARRAY_A );
    }

    /**
     * List attempts for an exam (optionally filtered by user).
     *
     * @param int      $exam_id Exam ID.
     * @param int|null $user_id User ID to filter.
     *
     * @return array
     */
    public function list_by_exam( int $exam_id, ?int $user_id = null ): array {
        global $wpdb;

        $table = Tables::attempts();

        if ( $user_id ) {
            $query = $wpdb->prepare(
                "SELECT * FROM {$table} WHERE exam_id = %d AND user_id = %d ORDER BY started_at DESC, id DESC",
                $exam_id,
                $user_id
            );
        } else {
            $query = $wpdb->prepare(
                "SELECT * FROM {$table} WHERE exam_id = %d ORDER BY started_at DESC, id DESC",
                $exam_id
            );
        }

        return $wpdb->get_results( $query, ARRAY_A );
    }

    /**
     * Get the latest in-progress attempt for a user and exam.
     *
     * @param int $exam_id Exam ID.
     * @param int $user_id User ID.
     *
     * @return array|null
     */
    public function find_active_attempt( int $exam_id, int $user_id ): ?array {
        global $wpdb;

        $table = Tables::attempts();
        $query = $wpdb->prepare(
            "SELECT * FROM {$table} WHERE exam_id = %d AND user_id = %d AND status = %s ORDER BY id DESC LIMIT 1",
            $exam_id,
            $user_id,
            'in_progress'
        );

        $row = $wpdb->get_row( $query, ARRAY_A );

        return $row ?: null;
    }

    /**
     * Get the latest active attempt for a user and exam.
     *
     * @param int $exam_id Exam ID.
     * @param int $user_id User ID.
     *
     * @return array|null
     */
    public function find_active_by_exam_and_user( int $exam_id, int $user_id ): ?array {
        return $this->find_active_attempt( $exam_id, $user_id );
    }

    /**
     * Get the latest submitted attempt for a user and exam.
     *
     * @param int $exam_id Exam ID.
     * @param int $user_id User ID.
     *
     * @return array|null
     */
    public function find_submitted_attempt( int $exam_id, int $user_id ): ?array {
        global $wpdb;

        $table = Tables::attempts();
        $query = $wpdb->prepare(
            "SELECT * FROM {$table} WHERE exam_id = %d AND user_id = %d AND status = %s ORDER BY finished_at DESC, id DESC LIMIT 1",
            $exam_id,
            $user_id,
            'submitted'
        );

        $row = $wpdb->get_row( $query, ARRAY_A );

        return $row ?: null;
    }

    /**
     * Mark an attempt as expired.
     *
     * @param int    $attempt_id Attempt ID.
     * @param string $finished_at Finished at timestamp.
     *
     * @return bool
     */
    public function mark_expired( int $attempt_id, string $finished_at ): bool {
        global $wpdb;

        $table   = Tables::attempts();
        $updated = $wpdb->update(
            $table,
            array(
                'status'      => 'expired',
                'finished_at' => $finished_at,
            ),
            array( 'id' => $attempt_id ),
            array( '%s', '%s' ),
            array( '%d' )
        );

        return false !== $updated;
    }
}
