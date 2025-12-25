<?php
/**
 * Question repository.
 *
 * @package Danesh\OnlineExam\Repositories
 */

namespace Danesh\OnlineExam\Repositories;

use Danesh\OnlineExam\DB\Tables;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class QuestionRepository {
    /**
     * Create a new question for an exam.
     *
     * @param int   $exam_id Exam ID.
     * @param array $data    Question data.
     */
    public function create( int $exam_id, array $data ): int {
        global $wpdb;

        $table       = Tables::questions();
        $insert_data = array(
            'exam_id'    => absint( $exam_id ),
            'type'       => isset( $data['type'] ) ? sanitize_text_field( $data['type'] ) : 'mcq',
            'prompt'     => isset( $data['prompt'] ) ? sanitize_textarea_field( $data['prompt'] ) : '',
            'points'     => isset( $data['points'] ) ? (float) $data['points'] : 1,
            'position'   => isset( $data['position'] ) ? absint( $data['position'] ) : 0,
            'created_at' => current_time( 'mysql' ),
        );

        $formats = array( '%d', '%s', '%s', '%f', '%d', '%s' );

        $inserted = $wpdb->insert( $table, $insert_data, $formats );

        if ( false === $inserted ) {
            return 0;
        }

        return (int) $wpdb->insert_id;
    }

    /**
     * Get a question by id.
     *
     * @param int $id Question ID.
     */
    public function get( int $id ): ?array {
        global $wpdb;

        $table = Tables::questions();
        $query = $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id );
        $row   = $wpdb->get_row( $query, ARRAY_A );

        return $row ?: null;
    }

    /**
     * List questions for an exam.
     *
     * @param int $exam_id Exam ID.
     */
    public function list_by_exam( int $exam_id ): array {
        global $wpdb;

        $table = Tables::questions();
        $query = $wpdb->prepare(
            "SELECT * FROM {$table} WHERE exam_id = %d ORDER BY position ASC, id ASC",
            $exam_id
        );

        return $wpdb->get_results( $query, ARRAY_A );
    }

    /**
     * Delete a question.
     *
     * @param int $id Question ID.
     */
    public function delete( int $id ): bool {
        global $wpdb;

        $table   = Tables::questions();
        $deleted = $wpdb->delete( $table, array( 'id' => $id ), array( '%d' ) );

        return false !== $deleted;
    }
}
