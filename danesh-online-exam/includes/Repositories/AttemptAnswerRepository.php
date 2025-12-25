<?php
/**
 * Attempt answer repository.
 *
 * @package Danesh\OnlineExam\Repositories
 */

namespace Danesh\OnlineExam\Repositories;

use Danesh\OnlineExam\DB\Tables;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class AttemptAnswerRepository {
    /**
     * Insert or update an attempt answer.
     *
     * @param int      $attempt_id  Attempt ID.
     * @param int      $question_id Question ID.
     * @param int      $choice_id   Choice ID.
     * @param bool|int $is_correct  Whether the answer is correct.
     *
     * @return bool
     */
    public function upsert_answer( int $attempt_id, int $question_id, int $choice_id, $is_correct = null ): bool {
        global $wpdb;

        $table = Tables::attempt_answers();
        $data  = array(
            'attempt_id'  => absint( $attempt_id ),
            'question_id' => absint( $question_id ),
            'choice_id'   => absint( $choice_id ),
            'is_correct'  => null === $is_correct ? null : ( $is_correct ? 1 : 0 ),
            'answered_at' => current_time( 'mysql' ),
        );

        $formats = array( '%d', '%d', '%d', '%d', '%s' );

        $replaced = $wpdb->replace( $table, $data, $formats );

        return false !== $replaced;
    }

    /**
     * List answers for an attempt.
     *
     * @param int $attempt_id Attempt ID.
     *
     * @return array
     */
    public function list_answers( int $attempt_id ): array {
        global $wpdb;

        $table = Tables::attempt_answers();
        $query = $wpdb->prepare( "SELECT * FROM {$table} WHERE attempt_id = %d", $attempt_id );

        return $wpdb->get_results( $query, ARRAY_A );
    }
}
