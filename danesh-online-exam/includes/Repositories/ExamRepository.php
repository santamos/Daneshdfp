<?php
/**
 * Exam repository.
 *
 * @package Danesh\OnlineExam\Repositories
 */

namespace Danesh\OnlineExam\Repositories;

use Danesh\OnlineExam\DB\Tables;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ExamRepository {
    /**
     * Create a new exam.
     *
     * @param array $data Exam data.
     *
     * @return int
     */
    public function create( array $data ): int {
        global $wpdb;

        $table       = Tables::exams();
        $created_by  = get_current_user_id();
        $created_at  = current_time( 'mysql' );
        $insert_data = array(
            'title'            => isset( $data['title'] ) ? sanitize_text_field( $data['title'] ) : '',
            'duration_seconds' => isset( $data['duration_seconds'] ) ? absint( $data['duration_seconds'] ) : 0,
            'status'           => isset( $data['status'] ) ? sanitize_text_field( $data['status'] ) : 'draft',
            'created_by'       => $created_by ? absint( $created_by ) : 0,
            'created_at'       => $created_at,
        );

        $formats = array( '%s', '%d', '%s', '%d', '%s' );

        $inserted = $wpdb->insert( $table, $insert_data, $formats );

        if ( false === $inserted ) {
            return 0;
        }

        return (int) $wpdb->insert_id;
    }

    /**
     * Get an exam by id.
     *
     * @param int $id Exam ID.
     *
     * @return array|null
     */
    public function get( int $id ): ?array {
        global $wpdb;

        $table = Tables::exams();
        $query = $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id );
        $row   = $wpdb->get_row( $query, ARRAY_A );

        return $row ?: null;
    }

    /**
     * List exams with optional pagination.
     *
     * @param array $args Query args.
     *
     * @return array
     */
    public function list( array $args = array() ): array {
        global $wpdb;

        $table  = Tables::exams();
        $limit  = isset( $args['number'] ) ? absint( $args['number'] ) : 20;
        $offset = isset( $args['offset'] ) ? absint( $args['offset'] ) : 0;

        if ( 0 === $limit ) {
            $sql = "SELECT * FROM {$table} ORDER BY created_at DESC";
            return $wpdb->get_results( $sql, ARRAY_A );
        }

        $query = $wpdb->prepare(
            "SELECT * FROM {$table} ORDER BY created_at DESC LIMIT %d OFFSET %d",
            $limit,
            $offset
        );

        return $wpdb->get_results( $query, ARRAY_A );
    }

    /**
     * Update an exam.
     *
     * @param int   $id   Exam ID.
     * @param array $data Fields to update.
     */
    public function update( int $id, array $data ): bool {
        global $wpdb;

        $table     = Tables::exams();
        $whitelist = array( 'title', 'duration_seconds', 'status' );
        $fields    = array();

        foreach ( $whitelist as $key ) {
            if ( array_key_exists( $key, $data ) ) {
                switch ( $key ) {
                    case 'duration_seconds':
                        $fields[ $key ] = absint( $data[ $key ] );
                        break;
                    case 'status':
                        $fields[ $key ] = sanitize_text_field( $data[ $key ] );
                        break;
                    default:
                        $fields[ $key ] = sanitize_text_field( $data[ $key ] );
                        break;
                }
            }
        }

        if ( empty( $fields ) ) {
            return false;
        }

        $fields['updated_at'] = current_time( 'mysql' );

        $formats = array();
        foreach ( array_keys( $fields ) as $field_key ) {
            switch ( $field_key ) {
                case 'duration_seconds':
                    $formats[] = '%d';
                    break;
                case 'updated_at':
                    $formats[] = '%s';
                    break;
                default:
                    $formats[] = '%s';
                    break;
            }
        }

        $updated = $wpdb->update( $table, $fields, array( 'id' => $id ), $formats, array( '%d' ) );

        return false !== $updated;
    }

    /**
     * Delete an exam.
     *
     * @param int $id Exam ID.
     */
    public function delete( int $id ): bool {
        global $wpdb;

        $table = Tables::exams();

        $deleted = $wpdb->delete( $table, array( 'id' => $id ), array( '%d' ) );

        return false !== $deleted;
    }
}
