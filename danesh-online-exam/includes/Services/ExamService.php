<?php
/**
 * Exam services and validation.
 *
 * @package Danesh\OnlineExam\Services
 */

namespace Danesh\OnlineExam\Services;

use Danesh\OnlineExam\Repositories\ChoiceRepository;
use Danesh\OnlineExam\Repositories\ExamRepository;
use Danesh\OnlineExam\Repositories\QuestionRepository;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ExamService {
    private ExamRepository $exams;

    private QuestionRepository $questions;

    private ChoiceRepository $choices;

    public function __construct( ?ExamRepository $exams = null, ?QuestionRepository $questions = null, ?ChoiceRepository $choices = null ) {
        $this->exams     = $exams ?: new ExamRepository();
        $this->questions = $questions ?: new QuestionRepository();
        $this->choices   = $choices ?: new ChoiceRepository();
    }

    /**
     * Create an exam after validation.
     *
     * @param array $payload Exam payload.
     *
     * @return array|WP_Error
     */
    public function create_exam( array $payload ) {
        $validated = $this->validate_exam_payload( $payload );

        if ( is_wp_error( $validated ) ) {
            return $validated;
        }

        $exam_id = $this->exams->create( $validated );

        if ( ! $exam_id ) {
            return new WP_Error( 'exam_create_failed', __( 'Unable to create exam.', 'danesh-online-exam' ), array( 'status' => 500 ) );
        }

        $created = $this->exams->get( $exam_id );

        if ( ! $created ) {
            return new WP_Error( 'exam_fetch_failed', __( 'Unable to load created exam.', 'danesh-online-exam' ), array( 'status' => 500 ) );
        }

        return $this->normalize_exam( $created );
    }

    /**
     * Get exam data.
     *
     * @param int $id Exam ID.
     *
     * @return array|WP_Error
     */
    public function get_exam( int $id ) {
        $exam = $this->exams->get( $id );

        if ( ! $exam ) {
            return new WP_Error( 'exam_not_found', __( 'Exam not found.', 'danesh-online-exam' ), array( 'status' => 404 ) );
        }

        return $this->normalize_exam( $exam );
    }

    /**
     * Create a question for an exam.
     *
     * @param int   $exam_id Exam ID.
     * @param array $payload Question payload.
     *
     * @return array|WP_Error
     */
    public function create_question( int $exam_id, array $payload ) {
        $exam = $this->exams->get( $exam_id );

        if ( ! $exam ) {
            return new WP_Error( 'exam_not_found', __( 'Exam not found.', 'danesh-online-exam' ), array( 'status' => 404 ) );
        }

        $validated = $this->validate_question_payload( $payload );

        if ( is_wp_error( $validated ) ) {
            return $validated;
        }

        $question_id = $this->questions->create( $exam_id, $validated );

        if ( ! $question_id ) {
            return new WP_Error( 'question_create_failed', __( 'Unable to create question.', 'danesh-online-exam' ), array( 'status' => 500 ) );
        }

        $created = $this->questions->get( $question_id );

        if ( ! $created ) {
            return new WP_Error( 'question_fetch_failed', __( 'Unable to load created question.', 'danesh-online-exam' ), array( 'status' => 500 ) );
        }

        return $this->normalize_question( $created );
    }

    /**
     * List questions for an exam.
     *
     * @param int $exam_id Exam ID.
     *
     * @return array|WP_Error
     */
    public function list_questions( int $exam_id ) {
        $exam = $this->exams->get( $exam_id );

        if ( ! $exam ) {
            return new WP_Error( 'exam_not_found', __( 'Exam not found.', 'danesh-online-exam' ), array( 'status' => 404 ) );
        }

        $questions = $this->questions->list_by_exam( $exam_id );

        return array_map( array( $this, 'normalize_question' ), $questions );
    }

    /**
     * Create a choice for a question.
     *
     * @param int   $question_id Question ID.
     * @param array $payload     Choice payload.
     *
     * @return array|WP_Error
     */
    public function create_choice( int $question_id, array $payload ) {
        $question = $this->questions->get( $question_id );

        if ( ! $question ) {
            return new WP_Error( 'question_not_found', __( 'Question not found.', 'danesh-online-exam' ), array( 'status' => 404 ) );
        }

        $validated = $this->validate_choice_payload( $payload );

        if ( is_wp_error( $validated ) ) {
            return $validated;
        }

        $choice_id = $this->choices->create( $question_id, $validated );

        if ( ! $choice_id ) {
            return new WP_Error( 'choice_create_failed', __( 'Unable to create choice.', 'danesh-online-exam' ), array( 'status' => 500 ) );
        }

        $choices = $this->choices->list_by_question( $question_id );
        foreach ( $choices as $choice ) {
            if ( (int) $choice['id'] === (int) $choice_id ) {
                return $this->normalize_choice( $choice );
            }
        }

        return new WP_Error( 'choice_fetch_failed', __( 'Unable to load created choice.', 'danesh-online-exam' ), array( 'status' => 500 ) );
    }

    /**
     * List choices for a question.
     *
     * @param int $question_id Question ID.
     *
     * @return array|WP_Error
     */
    public function list_choices( int $question_id ) {
        $question = $this->questions->get( $question_id );

        if ( ! $question ) {
            return new WP_Error( 'question_not_found', __( 'Question not found.', 'danesh-online-exam' ), array( 'status' => 404 ) );
        }

        $choices = $this->choices->list_by_question( $question_id );

        return array_map( array( $this, 'normalize_choice' ), $choices );
    }

    /**
     * Validate exam payload.
     *
     * @param array $payload Raw payload.
     *
     * @return array|WP_Error
     */
    private function validate_exam_payload( array $payload ) {
        $title = isset( $payload['title'] ) ? sanitize_text_field( $payload['title'] ) : '';

        if ( '' === $title ) {
            return new WP_Error( 'invalid_exam_title', __( 'Exam title is required.', 'danesh-online-exam' ), array( 'status' => 400 ) );
        }

        $duration = isset( $payload['duration_seconds'] ) ? absint( $payload['duration_seconds'] ) : 0;

        if ( $duration < 0 ) {
            return new WP_Error( 'invalid_exam_duration', __( 'Duration must be zero or a positive number.', 'danesh-online-exam' ), array( 'status' => 400 ) );
        }

        $status = isset( $payload['status'] ) ? sanitize_text_field( $payload['status'] ) : 'draft';
        $status = strtolower( $status );
        $allowed_statuses = array( 'draft', 'published' );

        if ( ! in_array( $status, $allowed_statuses, true ) ) {
            return new WP_Error( 'invalid_exam_status', __( 'Status must be draft or published.', 'danesh-online-exam' ), array( 'status' => 400 ) );
        }

        return array(
            'title'            => $title,
            'duration_seconds' => $duration,
            'status'           => $status,
        );
    }

    /**
     * Validate question payload.
     *
     * @param array $payload Raw payload.
     *
     * @return array|WP_Error
     */
    private function validate_question_payload( array $payload ) {
        $type = isset( $payload['type'] ) ? sanitize_text_field( $payload['type'] ) : 'mcq';

        if ( 'mcq' !== $type ) {
            return new WP_Error( 'invalid_question_type', __( 'Only multiple choice questions are supported in v1.', 'danesh-online-exam' ), array( 'status' => 400 ) );
        }

        $prompt = isset( $payload['prompt'] ) ? sanitize_textarea_field( $payload['prompt'] ) : '';

        if ( '' === trim( wp_strip_all_tags( $prompt ) ) ) {
            return new WP_Error( 'invalid_question_prompt', __( 'Question prompt is required.', 'danesh-online-exam' ), array( 'status' => 400 ) );
        }

        $points = isset( $payload['points'] ) ? (float) $payload['points'] : 1.0;

        if ( $points < 0 ) {
            return new WP_Error( 'invalid_question_points', __( 'Points must be zero or a positive value.', 'danesh-online-exam' ), array( 'status' => 400 ) );
        }

        $position = isset( $payload['sort_order'] ) ? absint( $payload['sort_order'] ) : 0;

        return array(
            'type'     => $type,
            'prompt'   => $prompt,
            'points'   => $points,
            'position' => $position,
        );
    }

    /**
     * Validate choice payload.
     *
     * @param array $payload Raw payload.
     *
     * @return array|WP_Error
     */
    private function validate_choice_payload( array $payload ) {
        $choice_text = isset( $payload['choice_text'] ) ? sanitize_text_field( $payload['choice_text'] ) : '';

        if ( '' === $choice_text ) {
            return new WP_Error( 'invalid_choice_text', __( 'Choice text is required.', 'danesh-online-exam' ), array( 'status' => 400 ) );
        }

        $is_correct = false;

        if ( isset( $payload['is_correct'] ) ) {
            $is_correct = (bool) filter_var( $payload['is_correct'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE );
        }

        $position = isset( $payload['sort_order'] ) ? absint( $payload['sort_order'] ) : 0;

        return array(
            'choice_text' => $choice_text,
            'is_correct'  => $is_correct,
            'position'    => $position,
        );
    }

    /**
     * Normalize exam output.
     */
    private function normalize_exam( array $row ): array {
        return array(
            'id'               => (int) $row['id'],
            'title'            => sanitize_text_field( $row['title'] ),
            'duration_seconds' => isset( $row['duration_seconds'] ) ? (int) $row['duration_seconds'] : 0,
            'status'           => sanitize_text_field( $row['status'] ),
            'created_by'       => isset( $row['created_by'] ) ? (int) $row['created_by'] : 0,
            'created_at'       => $row['created_at'] ?? '',
            'updated_at'       => $row['updated_at'] ?? null,
        );
    }

    /**
     * Normalize question output.
     */
    private function normalize_question( array $row ): array {
        return array(
            'id'         => (int) $row['id'],
            'exam_id'    => (int) $row['exam_id'],
            'type'       => sanitize_text_field( $row['type'] ),
            'prompt'     => sanitize_textarea_field( $row['prompt'] ),
            'points'     => isset( $row['points'] ) ? (float) $row['points'] : 0.0,
            'sort_order' => isset( $row['position'] ) ? (int) $row['position'] : 0,
            'created_at' => $row['created_at'] ?? '',
        );
    }

    /**
     * Normalize choice output.
     */
    private function normalize_choice( array $row ): array {
        return array(
            'id'           => (int) $row['id'],
            'question_id'  => (int) $row['question_id'],
            'choice_text'  => sanitize_text_field( $row['choice_text'] ),
            'is_correct'   => ! empty( $row['is_correct'] ),
            'sort_order'   => isset( $row['position'] ) ? (int) $row['position'] : 0,
        );
    }
}
