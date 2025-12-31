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
     * @param int|null $choice_id   Choice ID.
     *
     * @return bool
     */
    public function upsert_answer( int $attempt_id, int $question_id, ?int $choice_id ): bool {
        global $wpdb;

        $table       = Tables::attempt_answers();
        $answered_at = current_time( 'mysql' );

        if ( is_null( $choice_id ) ) {
            $query = $wpdb->prepare(
                "REPLACE INTO {$table} (attempt_id, question_id, choice_id, is_correct, answered_at) VALUES (%d, %d, NULL, %d, %s)",
                absint( $attempt_id ),
                absint( $question_id ),
                0,
                $answered_at
            );
        } else {
            $query = $wpdb->prepare(
                "REPLACE INTO {$table} (attempt_id, question_id, choice_id, is_correct, answered_at) VALUES (%d, %d, %d, %d, %s)",
                absint( $attempt_id ),
                absint( $question_id ),
                absint( $choice_id ),
                0,
                $answered_at
            );
        }

        $replaced = $wpdb->query( $query );

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

    /**
     * List the latest answer selection per question for an attempt.
     *
     * @param int $attempt_id Attempt ID.
     *
     * @return array
     */
    public function list_answer_selections( int $attempt_id ): array {
        global $wpdb;

        $table = Tables::attempt_answers();
        $query = $wpdb->prepare(
            "SELECT question_id, choice_id FROM {$table} WHERE attempt_id = %d ORDER BY answered_at DESC, id DESC",
            $attempt_id
        );

        $rows        = $wpdb->get_results( $query, ARRAY_A );
        $selections  = array();

        foreach ( $rows as $row ) {
            $question_id = (int) $row['question_id'];

            if ( isset( $selections[ $question_id ] ) ) {
                continue;
            }

            $selections[ $question_id ] = array(
                'question_id' => $question_id,
                'choice_id'   => isset( $row['choice_id'] ) ? (int) $row['choice_id'] : null,
            );
        }

        return array_values( $selections );
    }

    /**
     * Count answered questions for an attempt.
     *
     * @param int $attempt_id Attempt ID.
     *
     * @return int
     */
    public function count_answered( int $attempt_id ): int {
        global $wpdb;

        $table = Tables::attempt_answers();
        $query = $wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} WHERE attempt_id = %d AND choice_id IS NOT NULL",
            $attempt_id
        );

        return (int) $wpdb->get_var( $query );
    }
}
