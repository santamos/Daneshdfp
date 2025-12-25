<?php
/**
 * Choice repository.
 *
 * @package Danesh\OnlineExam\Repositories
 */

namespace Danesh\OnlineExam\Repositories;

use Danesh\OnlineExam\DB\Tables;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ChoiceRepository {
    /**
     * Create a new choice for a question.
     *
     * @param int   $question_id Question ID.
     * @param array $data        Choice data.
     */
    public function create( int $question_id, array $data ): int {
        global $wpdb;

        $table       = Tables::choices();
        $insert_data = array(
            'question_id' => $question_id,
            'choice_text' => $data['choice_text'] ?? '',
            'is_correct'  => ! empty( $data['is_correct'] ) ? 1 : 0,
            'position'    => isset( $data['position'] ) ? absint( $data['position'] ) : 0,
        );

        $formats = array( '%d', '%s', '%d', '%d' );

        $inserted = $wpdb->insert( $table, $insert_data, $formats );

        if ( false === $inserted ) {
            return 0;
        }

        return (int) $wpdb->insert_id;
    }

    /**
     * List choices for a question.
     *
     * @param int $question_id Question ID.
     */
    public function list_by_question( int $question_id ): array {
        global $wpdb;

        $table = Tables::choices();
        $query = $wpdb->prepare(
            "SELECT * FROM {$table} WHERE question_id = %d ORDER BY position ASC, id ASC",
            $question_id
        );

        return $wpdb->get_results( $query, ARRAY_A );
    }

    /**
     * Delete a choice.
     *
     * @param int $id Choice ID.
     */
    public function delete( int $id ): bool {
        global $wpdb;

        $table   = Tables::choices();
        $deleted = $wpdb->delete( $table, array( 'id' => $id ), array( '%d' ) );

        return false !== $deleted;
    }
}
