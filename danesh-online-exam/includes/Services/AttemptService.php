<?php
/**
 * Attempt services and validation.
 *
 * @package Danesh\OnlineExam\Services
 */

namespace Danesh\OnlineExam\Services;

use Danesh\OnlineExam\Repositories\AttemptAnswerRepository;
use Danesh\OnlineExam\Repositories\AttemptRepository;
use Danesh\OnlineExam\Repositories\ChoiceRepository;
use Danesh\OnlineExam\Repositories\ExamRepository;
use Danesh\OnlineExam\Repositories\QuestionRepository;
use WP_Error;
use WP_REST_Request;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class AttemptService {
    private AttemptRepository $attempts;

    private AttemptAnswerRepository $answers;

    private ExamRepository $exams;

    private QuestionRepository $questions;

    private ChoiceRepository $choices;

    public function __construct(
        ?AttemptRepository $attempts = null,
        ?AttemptAnswerRepository $answers = null,
        ?ExamRepository $exams = null,
        ?QuestionRepository $questions = null,
        ?ChoiceRepository $choices = null
    ) {
        $this->attempts  = $attempts ?: new AttemptRepository();
        $this->answers   = $answers ?: new AttemptAnswerRepository();
        $this->exams     = $exams ?: new ExamRepository();
        $this->questions = $questions ?: new QuestionRepository();
        $this->choices   = $choices ?: new ChoiceRepository();
    }

    /**
     * Get an attempt status and answers.
     *
     * @param int $attempt_id Attempt ID.
     *
     * @return array|WP_Error
     */
    public function get_attempt( int $attempt_id ) {
        $attempt = $this->attempts->get_attempt( $attempt_id );

        if ( ! $attempt ) {
            return new WP_Error( 'attempt_not_found', __( 'Attempt not found.', 'danesh-online-exam' ), array( 'status' => 404 ) );
        }

        $access = $this->enforce_attempt_access( $attempt );

        if ( is_wp_error( $access ) ) {
            return $access;
        }

        $now = $this->get_current_timestamp();

        if ( 'in_progress' === ( $attempt['status'] ?? '' ) && $this->is_expired( $attempt['expires_at'] ?? null, $now ) ) {
            $finished_at = $this->format_gmt_datetime( $now );
            $this->attempts->mark_expired( $attempt_id, $finished_at );
            $attempt['status']      = 'expired';
            $attempt['finished_at'] = $finished_at;
        }

        $normalized                   = $this->normalize_attempt( $attempt, $now );
        $normalized['answered_count'] = $this->answers->count_answered( $attempt_id );
        $normalized['answers']        = array_map(
            static function ( array $answer ): array {
                $choice_id = $answer['choice_id'] ?? null;

                return array(
                    'question_id' => (int) $answer['question_id'],
                    'choice_id'   => is_null( $choice_id ) ? null : (int) $choice_id,
                );
            },
            $this->answers->list_answer_selections( $attempt_id )
        );

        if ( 'expired' === $normalized['status'] ) {
            $normalized['remaining_seconds'] = 0;
        }

        return $normalized;
    }

    /**
     * Get the paper (questions and choices) for an attempt.
     *
     * @param int              $attempt_id Attempt ID.
     * @param WP_REST_Request  $request    REST request.
     *
     * @return array|WP_Error
     */
    public function get_attempt_paper( int $attempt_id, WP_REST_Request $request ) {
        $attempt = $this->attempts->get_attempt( $attempt_id );

        if ( ! $attempt ) {
            return new WP_Error( 'attempt_not_found', __( 'Attempt not found.', 'danesh-online-exam' ), array( 'status' => 404 ) );
        }

        $access = $this->enforce_attempt_access( $attempt );

        if ( is_wp_error( $access ) ) {
            return $access;
        }

        $now     = $this->get_current_timestamp();
        $status  = $attempt['status'] ?? '';
        $is_edit = $this->is_manage_context() && ( 'edit' === ( $request->get_param( 'context' ) ?? '' ) );

        if ( 'in_progress' === $status && $this->is_expired( $attempt['expires_at'] ?? null, $now ) ) {
            $finished_at = $this->format_gmt_datetime( $now );
            $this->attempts->mark_expired( $attempt_id, $finished_at );

            return new WP_Error( 'attempt_expired', __( 'Attempt expired', 'danesh-online-exam' ), array( 'status' => 403 ) );
        }

        if ( 'submitted' === $status ) {
            return new WP_Error( 'attempt_already_submitted', __( 'Attempt has already been submitted.', 'danesh-online-exam' ), array( 'status' => 400 ) );
        }

        if ( 'expired' === $status ) {
            return new WP_Error( 'attempt_expired', __( 'Attempt expired', 'danesh-online-exam' ), array( 'status' => 403 ) );
        }

        $questions     = $this->questions->list_by_exam( (int) $attempt['exam_id'] );
        $question_ids  = array_map(
            static function ( $question ): int {
                return (int) ( $question['id'] ?? 0 );
            },
            $questions
        );
        $choice_rows   = $this->choices->list_by_question_ids( $question_ids );
        $selections    = $this->answers->list_answer_selections( $attempt_id );
        $choice_map    = array();
        $selection_map = array();

        foreach ( $choice_rows as $choice ) {
            $question_id = (int) ( $choice['question_id'] ?? 0 );

            if ( ! isset( $choice_map[ $question_id ] ) ) {
                $choice_map[ $question_id ] = array();
            }

            $choice_map[ $question_id ][] = $this->normalize_choice_for_paper( $choice, $is_edit );
        }
foreach ( $selections as $selection ) {
    $qid = (int) ( $selection['question_id'] ?? 0 );
    if ( $qid <= 0 ) {
        continue;
    }

   $cid_raw = $selection['choice_id'] ?? ( $selection['selected_choice_id'] ?? null );

    $selection_map[ $qid ] = is_null( $cid_raw ) ? null : (int) $cid_raw;
}


        $remaining_seconds = $this->calculate_remaining_seconds( $attempt['expires_at'] ?? null, $now );

        return array(
            'attempt'   => array(
                'id'                => (int) $attempt['id'],
                'exam_id'           => (int) $attempt['exam_id'],
                'status'            => sanitize_text_field( $attempt['status'] ?? 'in_progress' ),
                'started_at'        => $attempt['started_at'] ?? '',
                'expires_at'        => $attempt['expires_at'] ?? null,
                'remaining_seconds' => $remaining_seconds,
            ),
            'questions' => array_map(
                function ( array $question ) use ( $choice_map, $selection_map ): array {
                    $question_id = (int) $question['id'];

                    return array(
                        'id'                  => $question_id,
                        'prompt'              => sanitize_textarea_field( $question['prompt'] ?? '' ),
                        'type'                => sanitize_text_field( $question['type'] ?? 'mcq' ),
                        'points'              => isset( $question['points'] ) ? (float) $question['points'] : 0.0,
                        'selected_choice_id'  => $selection_map[ $question_id ] ?? null,
                        'choices'             => $choice_map[ $question_id ] ?? array(),
                    );
                },
                $questions
            ),
        );
    }

    /**
     * Start a new attempt for an exam.
     *
     * @param int $exam_id Exam ID.
     *
     * @return array|WP_Error
     */
    public function start_attempt( int $exam_id ) {
        $user_id = get_current_user_id();

        if ( ! $user_id ) {
            return new WP_Error( 'not_logged_in', __( 'You must be logged in to start an attempt.', 'danesh-online-exam' ), array( 'status' => 401 ) );
        }

        $exam = $this->exams->get( $exam_id );

        if ( ! $exam ) {
            return new WP_Error( 'exam_not_found', __( 'Exam not found.', 'danesh-online-exam' ), array( 'status' => 404 ) );
        }

      if ( ! $this->is_manage_context() && 'published' !== $exam['status'] ) {
    return new WP_Error(
        'exam_not_published',
        __( 'The exam is not available for attempts.', 'danesh-online-exam' ),
        array( 'status' => 403 )
    );
}

$now            = $this->get_current_timestamp();
$submitted_attempt = $this->attempts->find_submitted_attempt( $exam_id, $user_id );
$active_attempt = $this->attempts->find_active_attempt( $exam_id, $user_id );
$expires_at     = $active_attempt['expires_at'] ?? null;

if ( $submitted_attempt ) {
    if ( $active_attempt ) {
        $this->attempts->mark_expired( (int) $active_attempt['id'], $this->format_gmt_datetime( $now ) );
    }

    return new WP_Error(
        'danesh_already_submitted',
        __( 'You already submitted this exam.', 'danesh-online-exam' ),
        array(
            'status'     => 409,
            'attempt_id' => (int) $submitted_attempt['id'],
        )
    );
}

if ( $active_attempt && ! $this->is_expired( $expires_at, $now ) ) {
    $attempt            = $this->normalize_attempt( $active_attempt, $now );
    $attempt['resumed'] = true;
    return $attempt;
}

if ( $active_attempt && $this->is_expired( $expires_at, $now ) ) {
    $this->attempts->mark_expired( (int) $active_attempt['id'], $this->format_gmt_datetime( $now ) );
}

$started_at        = $this->format_gmt_datetime( $now );
$expires_at        = $this->calculate_expiry( (int) ( $exam['duration_seconds'] ?? 0 ), $now );
$remaining_seconds = $this->calculate_remaining_seconds( $expires_at, $now );

$attempt_id = $this->attempts->create_attempt( $exam_id, $user_id, $started_at, $expires_at, 'in_progress' );

if ( ! $attempt_id ) {
    return new WP_Error( 'attempt_create_failed', __( 'Unable to start attempt.', 'danesh-online-exam' ), array( 'status' => 500 ) );
}


        $attempt = $this->attempts->get_attempt( $attempt_id );

        if ( ! $attempt ) {
            return new WP_Error( 'attempt_load_failed', __( 'Unable to load attempt data.', 'danesh-online-exam' ), array( 'status' => 500 ) );
        }

        $normalized               = $this->normalize_attempt( $attempt, $now );
        $normalized['resumed']    = false;
        $normalized['remaining_seconds'] = $remaining_seconds;

        return $normalized;
    }

    /**
     * List attempts for an exam.
     *
     * @param int      $exam_id Exam ID.
     * @param int|null $user_id Optional user filter.
     *
     * @return array|WP_Error
     */
    public function list_exam_attempts( int $exam_id, ?int $user_id = null ) {
        $exam = $this->exams->get( $exam_id );

        if ( ! $exam ) {
            return new WP_Error( 'exam_not_found', __( 'Exam not found.', 'danesh-online-exam' ), array( 'status' => 404 ) );
        }

        if ( ! $this->is_manage_context() ) {
            $current_user_id = get_current_user_id();

            if ( ! $current_user_id ) {
                return new WP_Error( 'not_logged_in', __( 'Authentication required.', 'danesh-online-exam' ), array( 'status' => 401 ) );
            }

            $user_id = $current_user_id;
        } elseif ( $user_id && $user_id < 0 ) {
            $user_id = null;
        }

        $now      = $this->get_current_timestamp();
        $attempts = array();

        foreach ( $this->attempts->list_by_exam( $exam_id, $user_id ) as $attempt ) {
            $attempt_id = (int) $attempt['id'];

            if ( 'in_progress' === ( $attempt['status'] ?? '' ) && $this->is_expired( $attempt['expires_at'] ?? null, $now ) ) {
                $finished_at = $this->format_gmt_datetime( $now );
                $this->attempts->mark_expired( $attempt_id, $finished_at );

                $attempt['status']      = 'expired';
                $attempt['finished_at'] = $finished_at;
            }

            $normalized                   = $this->normalize_attempt( $attempt, $now );
            $normalized['answered_count'] = $this->answers->count_answered( $attempt_id );

            if ( 'expired' === $normalized['status'] ) {
                $normalized['remaining_seconds'] = 0;
            }

            $attempts[] = $normalized;
        }

        $response = array(
            'exam_id'  => (int) $exam_id,
            'attempts' => $attempts,
        );

        if ( null !== $user_id ) {
            $response['user_id'] = (int) $user_id;
        }

        return $response;
    }

    /**
     * Get the active attempt for an exam and user.
     *
     * @param int      $exam_id Exam ID.
     * @param int|null $user_id User ID (defaults to current user).
     *
     * @return array|WP_Error
     */
    public function get_active_attempt( int $exam_id, ?int $user_id = null ) {
        $exam = $this->exams->get( $exam_id );

        if ( ! $exam ) {
            return new WP_Error( 'exam_not_found', __( 'Exam not found.', 'danesh-online-exam' ), array( 'status' => 404 ) );
        }

        $current_user_id = get_current_user_id();

        if ( ! $current_user_id ) {
            return new WP_Error( 'not_logged_in', __( 'Authentication required.', 'danesh-online-exam' ), array( 'status' => 401 ) );
        }

        $requested_user_id = $user_id ?? $current_user_id;

        if ( ! $this->is_manage_context() && $requested_user_id !== $current_user_id ) {
            return new WP_Error( 'attempt_forbidden', __( 'You cannot access this attempt.', 'danesh-online-exam' ), array( 'status' => 403 ) );
        }

        $now            = $this->get_current_timestamp();
        $active_attempt = $this->attempts->find_active_by_exam_and_user( $exam_id, $requested_user_id );

        if ( ! $active_attempt ) {
            return new WP_Error( 'no_active_attempt', __( 'No active attempt found.', 'danesh-online-exam' ), array( 'status' => 404 ) );
        }

        if ( $this->is_expired( $active_attempt['expires_at'] ?? null, $now ) ) {
            $this->attempts->mark_expired( (int) $active_attempt['id'], $this->format_gmt_datetime( $now ) );

            return new WP_Error( 'no_active_attempt', __( 'No active attempt found.', 'danesh-online-exam' ), array( 'status' => 404 ) );
        }

        $normalized            = $this->normalize_attempt( $active_attempt, $now );
        $normalized['resumed'] = true;

        return $normalized;
    }

    /**
     * Get eligibility/resume state for an exam attempt.
     *
     * @param int      $exam_id Exam ID.
     * @param int|null $user_id Optional user ID (manage context only).
     *
     * @return array|WP_Error
     */
    public function get_attempt_eligibility( int $exam_id, ?int $user_id = null ) {
        $exam = $this->exams->get( $exam_id );

        if ( ! $exam ) {
            return new WP_Error( 'exam_not_found', __( 'Exam not found.', 'danesh-online-exam' ), array( 'status' => 404 ) );
        }

        $current_user_id = get_current_user_id();

        if ( ! $current_user_id ) {
            return new WP_Error( 'not_logged_in', __( 'Authentication required.', 'danesh-online-exam' ), array( 'status' => 401 ) );
        }

        $manage_context     = $this->is_manage_context();
        $requested_user_id  = $user_id ?? $current_user_id;

        if ( ! $manage_context && 'published' !== $exam['status'] ) {
            return new WP_Error(
                'exam_not_published',
                __( 'The exam is not available for attempts.', 'danesh-online-exam' ),
                array( 'status' => 403 )
            );
        }

        if ( ! $manage_context && $requested_user_id !== $current_user_id ) {
            return new WP_Error( 'attempt_forbidden', __( 'You cannot access this attempt.', 'danesh-online-exam' ), array( 'status' => 403 ) );
        }

        $now            = $this->get_current_timestamp();
        $active_attempt = $this->attempts->find_active_attempt( $exam_id, $requested_user_id );

        if ( $active_attempt && $this->is_expired( $active_attempt['expires_at'] ?? null, $now ) ) {
            $this->attempts->mark_expired( (int) $active_attempt['id'], $this->format_gmt_datetime( $now ) );
            $active_attempt = null;
        }

        $response = array(
            'exam_id'            => (int) $exam_id,
            'user_id'            => (int) $requested_user_id,
            'can_start'          => true,
            'has_active_attempt' => false,
            'active_attempt'     => null,
            'action'             => 'start',
        );

        if ( $active_attempt ) {
            $response['has_active_attempt'] = true;
            $response['active_attempt']     = $this->normalize_attempt( $active_attempt, $now );
            $response['action']             = 'resume';
        }

        return $response;
    }

    /**
     * Save an answer for an attempt.
     *
     * @param int $attempt_id  Attempt ID.
     * @param array $payload     Request payload.
     *
     * @return array|WP_Error
     */
    public function save_answers( int $attempt_id, array $payload ) {
        $attempt = $this->attempts->get_attempt( $attempt_id );

        if ( ! $attempt ) {
            return new WP_Error( 'attempt_not_found', __( 'Attempt not found.', 'danesh-online-exam' ), array( 'status' => 404 ) );
        }

        $access = $this->enforce_attempt_access( $attempt );

        if ( is_wp_error( $access ) ) {
            return $access;
        }

        $now = $this->get_current_timestamp();
        $status = $attempt['status'] ?? '';

        if ( 'submitted' === $status ) {
            return new WP_Error( 'attempt_already_submitted', __( 'Attempt has already been submitted.', 'danesh-online-exam' ), array( 'status' => 400 ) );
        }

        if ( 'expired' === $status ) {
            return new WP_Error( 'attempt_expired', __( 'The attempt has expired.', 'danesh-online-exam' ), array( 'status' => 403 ) );
        }

        if ( 'in_progress' === $status && $this->is_expired( $attempt['expires_at'] ?? null, $now ) ) {
            $finished_at = $this->format_gmt_datetime( $now );
            $this->attempts->mark_expired( $attempt_id, $finished_at );

            return new WP_Error( 'attempt_expired', __( 'The attempt has expired.', 'danesh-online-exam' ), array( 'status' => 403 ) );
        }

        if ( 'in_progress' !== $status ) {
            return new WP_Error( 'attempt_not_in_progress', __( 'Attempt is not in progress.', 'danesh-online-exam' ), array( 'status' => 400 ) );
        }

        $answers = $this->normalize_answers_payload( $payload );

        if ( is_wp_error( $answers ) ) {
            return $answers;
        }

        $answers     = $this->dedupe_answers_by_question( $answers );
        $saved_count = 0;

        foreach ( $answers as $answer ) {
            $question_id = (int) $answer['question_id'];
            $choice_id   = $answer['choice_id'];

            $question = $this->questions->get( $question_id );

            if ( ! $question ) {
                return new WP_Error( 'question_not_found', __( 'Question not found.', 'danesh-online-exam' ), array( 'status' => 404 ) );
            }

            if ( (int) $question['exam_id'] !== (int) $attempt['exam_id'] ) {
                return new WP_Error( 'question_mismatch', __( 'Question does not belong to this exam.', 'danesh-online-exam' ), array( 'status' => 400 ) );
            }

            if ( is_null( $choice_id ) ) {
                $deleted = $this->answers->delete_answer( $attempt_id, $question_id );

                if ( false === $deleted ) {
                    return new WP_Error( 'answer_delete_failed', __( 'Unable to clear answer.', 'danesh-online-exam' ), array( 'status' => 500 ) );
                }
            } else {
                $choice = $this->choices->get( (int) $choice_id );

                if ( ! $choice || (int) $choice['question_id'] !== $question_id ) {
                    return new WP_Error( 'choice_not_found', __( 'Choice does not belong to this question.', 'danesh-online-exam' ), array( 'status' => 400 ) );
                }

                $stored = $this->answers->upsert_answer( $attempt_id, $question_id, $choice_id );

                if ( ! $stored ) {
                    return new WP_Error( 'answer_save_failed', __( 'Unable to save answer.', 'danesh-online-exam' ), array( 'status' => 500 ) );
                }
            }

            $saved_count++;
        }

        $answered_count    = $this->answers->count_answered( $attempt_id );
        $remaining_seconds = $this->calculate_remaining_seconds( $attempt['expires_at'] ?? null, $now );
        $stored_answers    = $this->answers->list_answer_selections( $attempt_id );

        return array(
            'attempt_id'  => (int) $attempt_id,
            'saved_count' => $saved_count,
            'answered_count' => $answered_count,
            'remaining_seconds' => $remaining_seconds,
            'answers'     => array_map(
                static function ( array $answer ): array {
                    $choice_id = $answer['choice_id'] ?? null;

                    return array(
                        'question_id' => (int) $answer['question_id'],
                        'choice_id'   => is_null( $choice_id ) ? null : (int) $choice_id,
                    );
                },
                $stored_answers
            ),
        );
    }

    /**
     * Normalize incoming answers payload into a list.
     *
     * @param array $payload Raw payload.
     *
     * @return array|WP_Error
     */
    private function normalize_answers_payload( array $payload ) {
        if ( ! isset( $payload['answers'] ) || ! is_array( $payload['answers'] ) ) {
            return new WP_Error( 'invalid_answers', __( 'Answers must be an array.', 'danesh-online-exam' ), array( 'status' => 400 ) );
        }

        $normalized = array();

        foreach ( $payload['answers'] as $index => $item ) {
            if ( ! is_array( $item ) || ! array_key_exists( 'question_id', $item ) ) {
                return new WP_Error( 'invalid_answer', __( 'Each answer must include question_id.', 'danesh-online-exam' ), array( 'status' => 400 ) );
            }

            $has_choice_id  = array_key_exists( 'choice_id', $item );
            $has_selected   = array_key_exists( 'selected_choice_id', $item );

            if ( ! $has_choice_id && ! $has_selected ) {
                return new WP_Error( 'rest_invalid_param', __( 'Each answer must include choice_id or selected_choice_id.', 'danesh-online-exam' ), array( 'status' => 400 ) );
            }

            if ( ! is_numeric( $item['question_id'] ) ) {
                return new WP_Error( 'invalid_answer', __( 'Question ID must be numeric.', 'danesh-online-exam' ), array( 'status' => 400 ) );
            }

            $question_id = (int) $item['question_id'];
            $choice_raw  = $has_choice_id ? $item['choice_id'] : $item['selected_choice_id'];

            if ( $question_id <= 0 ) {
                return new WP_Error( 'invalid_answer', __( 'Question ID must be positive.', 'danesh-online-exam' ), array( 'status' => 400 ) );
            }

            if ( ! is_null( $choice_raw ) ) {
                if ( ! is_numeric( $choice_raw ) ) {
                    return new WP_Error( 'invalid_answer', __( 'Choice ID must be numeric or null.', 'danesh-online-exam' ), array( 'status' => 400 ) );
                }

                $choice_id = (int) $choice_raw;

                if ( $choice_id <= 0 ) {
                    return new WP_Error( 'invalid_answer', __( 'Choice ID must be positive when provided.', 'danesh-online-exam' ), array( 'status' => 400 ) );
                }
            } else {
                $choice_id = null;
            }

            $normalized[] = array(
                'question_id' => $question_id,
                'choice_id'   => $choice_id,
            );
        }

        return $normalized;
    }

    /**
     * Deduplicate answers by question ID keeping the last occurrence.
     *
     * @param array $answers Normalized answers.
     *
     * @return array
     */
    private function dedupe_answers_by_question( array $answers ): array {
        $deduped = array();

        foreach ( $answers as $answer ) {
            $deduped[ $answer['question_id'] ] = $answer;
        }

        return array_values( $deduped );
    }

    /**
     * Submit an attempt and calculate the score.
     *
     * @param int $attempt_id Attempt ID.
     *
     * @return array|WP_Error
     */
    public function submit_attempt( int $attempt_id ) {
        $attempt = $this->attempts->get_attempt( $attempt_id );

        if ( ! $attempt ) {
            return new WP_Error( 'attempt_not_found', __( 'Attempt not found.', 'danesh-online-exam' ), array( 'status' => 404 ) );
        }

        $access = $this->enforce_attempt_access( $attempt );

        if ( is_wp_error( $access ) ) {
            return $access;
        }

        if ( 'submitted' === $attempt['status'] ) {
            return new WP_Error( 'attempt_already_submitted', __( 'Attempt has already been submitted.', 'danesh-online-exam' ), array( 'status' => 400 ) );
        }

        if ( 'expired' === $attempt['status'] ) {
            return new WP_Error( 'attempt_expired', __( 'Attempt expired', 'danesh-online-exam' ), array( 'status' => 403 ) );
        }

        if ( 'in_progress' !== $attempt['status'] ) {
            return new WP_Error( 'attempt_not_in_progress', __( 'Attempt is not in progress.', 'danesh-online-exam' ), array( 'status' => 400 ) );
        }

        $now = $this->get_current_timestamp();

        if ( $this->is_expired( $attempt['expires_at'] ?? null, $now ) ) {
            $this->attempts->mark_expired( $attempt_id, $this->format_gmt_datetime( $now ) );

            return new WP_Error( 'attempt_expired', __( 'Attempt expired', 'danesh-online-exam' ), array( 'status' => 403 ) );
        }

        $questions = $this->questions->list_by_exam( (int) $attempt['exam_id'] );
        $answers   = $this->answers->list_answers( $attempt_id );
        $scoring   = $this->calculate_score( $questions, $answers );

        $ended_at = $this->format_gmt_datetime( $now );
        $updated  = $this->attempts->set_submitted( $attempt_id, $ended_at, $scoring['score'], $scoring['max_score'] );

        if ( ! $updated ) {
            return new WP_Error( 'attempt_submit_failed', __( 'Unable to submit attempt.', 'danesh-online-exam' ), array( 'status' => 500 ) );
        }

        return array(
            'attempt_id'   => (int) $attempt_id,
            'score'        => (float) $scoring['score'],
            'max_score'    => (float) $scoring['max_score'],
            'submitted_at' => $ended_at,
            'breakdown'    => $this->filter_breakdown_for_role( $scoring['breakdown'] ),
        );
    }

    /**
     * Get the submitted attempt report.
     *
     * @param int $attempt_id Attempt ID.
     *
     * @return array|WP_Error
     */
    public function get_attempt_report( int $attempt_id ) {
        $attempt = $this->attempts->get_attempt( $attempt_id );

        if ( ! $attempt ) {
            return new WP_Error( 'attempt_not_found', __( 'Attempt not found.', 'danesh-online-exam' ), array( 'status' => 404 ) );
        }

        $access = $this->enforce_attempt_access( $attempt );

        if ( is_wp_error( $access ) ) {
            return $access;
        }

        if ( 'submitted' !== $attempt['status'] ) {
            return new WP_Error( 'attempt_not_submitted', __( 'Attempt has not been submitted yet.', 'danesh-online-exam' ), array( 'status' => 400 ) );
        }

        $questions = $this->questions->list_by_exam( (int) $attempt['exam_id'] );
        $answers   = $this->answers->list_answers( $attempt_id );
        $scoring   = $this->calculate_score( $questions, $answers );

        $score     = isset( $attempt['score'] ) ? (float) $attempt['score'] : $scoring['score'];
        $max_score = isset( $attempt['total_points'] ) ? (float) $attempt['total_points'] : $scoring['max_score'];

        return array(
            'attempt_id'   => (int) $attempt_id,
            'score'        => $score,
            'max_score'    => $max_score,
            'submitted_at' => $attempt['finished_at'] ?? '',
            'breakdown'    => $this->filter_breakdown_for_role( $scoring['breakdown'] ),
        );
    }

    /**
     * Check whether the current user can access the attempt.
     *
     * @param array $attempt Attempt data.
     *
     * @return true|WP_Error
     */
    private function enforce_attempt_access( array $attempt ) {
        $user_id = get_current_user_id();

        if ( ! $user_id ) {
            return new WP_Error( 'not_logged_in', __( 'Authentication required.', 'danesh-online-exam' ), array( 'status' => 401 ) );
        }

        if ( $this->is_manage_context() ) {
            return true;
        }

        if ( (int) $attempt['user_id'] !== (int) $user_id ) {
            return new WP_Error( 'attempt_forbidden', __( 'You cannot access this attempt.', 'danesh-online-exam' ), array( 'status' => 403 ) );
        }

        return true;
    }

    /**
     * Whether current user can manage exams.
     */
    private function is_manage_context(): bool {
        return current_user_can( 'danesh_manage_exams' ) || current_user_can( 'manage_options' );
    }

    /**
     * Get the current UTC timestamp.
     */
    private function get_current_timestamp(): int {
        return time();
    }

    /**
     * Format a GMT datetime string.
     *
     * @param int $timestamp Timestamp.
     */
    private function format_gmt_datetime( int $timestamp ): string {
        return gmdate( 'Y-m-d H:i:s', $timestamp );
    }

    /**
     * Calculate expiry date for an attempt.
     *
     * @param int   $duration_seconds Duration in seconds.
     * @param int   $reference        Reference timestamp.
     *
     * @return string|null
     */
    private function calculate_expiry( int $duration_seconds, int $reference ) {
        if ( $duration_seconds <= 0 ) {
            return null;
        }

        return $this->format_gmt_datetime( $reference + $duration_seconds );
    }

    /**
     * Calculate remaining seconds until expiry.
     *
     * @param string|null $expires_at Expiry timestamp.
     * @param int|null    $reference  Reference timestamp.
     *
     * @return int|null
     */
    private function calculate_remaining_seconds( ?string $expires_at, ?int $reference = null ): ?int {
        if ( empty( $expires_at ) ) {
            return null;
        }

        $expiry_ts = strtotime( $expires_at );

        if ( ! $expiry_ts ) {
            return null;
        }

        $now = $reference ?? $this->get_current_timestamp();

        return max( 0, $expiry_ts - $now );
    }

    /**
     * Calculate remaining seconds until expiry.
     *
     * @param string|null $expires_at Expiry timestamp.
     * @param int|null    $reference  Reference timestamp.
     *
     * @return int|null
     */
   
    /**
     * Whether the attempt has expired.
     *
     * @param string|null $expires_at Expiry timestamp.
     * @param int|null    $reference  Reference timestamp.
     */
    private function is_expired( ?string $expires_at, ?int $reference = null ): bool {
        if ( empty( $expires_at ) ) {
            return false;
        }

        $expiry_ts = strtotime( $expires_at );

        if ( ! $expiry_ts ) {
            return false;
        }

        $now = $reference ?? $this->get_current_timestamp();

        return $expiry_ts <= $now;
    }

    /**
     * Normalize attempt output.
     *
     * @param array $attempt Attempt record.
     */
    private function normalize_attempt( array $attempt, ?int $reference = null ): array {
        $remaining_seconds = $this->calculate_remaining_seconds( $attempt['expires_at'] ?? null, $reference );

        return array(
            'id'                => (int) $attempt['id'],
            'exam_id'           => (int) $attempt['exam_id'],
            'user_id'           => (int) $attempt['user_id'],
            'status'            => sanitize_text_field( $attempt['status'] ?? 'in_progress' ),
            'started_at'        => $attempt['started_at'] ?? '',
            'expires_at'        => $attempt['expires_at'] ?? null,
            'finished_at'       => $attempt['finished_at'] ?? null,
            'remaining_seconds' => $remaining_seconds,
        );
    }

    /**
     * Normalize choice output for attempt paper.
     *
     * @param array $choice Choice record.
     * @param bool  $is_edit Whether correctness can be exposed.
     */
    private function normalize_choice_for_paper( array $choice, bool $is_edit ): array {
        $normalized = array(
            'id'   => (int) $choice['id'],
            'text' => sanitize_text_field( $choice['choice_text'] ?? '' ),
        );

        if ( isset( $choice['position'] ) ) {
            $normalized['sort_order'] = (int) $choice['position'];
        }

        if ( $is_edit ) {
            $normalized['is_correct'] = ! empty( $choice['is_correct'] );
        }

        return $normalized;
    }

    /**
     * Calculate score and breakdown.
     *
     * @param array $questions List of questions.
     * @param array $answers   List of answers.
     */
    private function calculate_score( array $questions, array $answers ): array {
        $answers_by_question = array();

        foreach ( $answers as $answer ) {
            $answers_by_question[ (int) $answer['question_id'] ] = $answer;
        }

        $score      = 0.0;
        $max_score  = 0.0;
        $breakdown  = array();

        foreach ( $questions as $question ) {
            $question_id      = (int) $question['id'];
            $points           = isset( $question['points'] ) ? (float) $question['points'] : 0.0;

            if ( $points <= 0 ) {
                $points = 1.0;
            }

            $max_score       += $points;
            $selected_choice  = $answers_by_question[ $question_id ]['choice_id'] ?? null;
            $choice_id        = $selected_choice ? (int) $selected_choice : null;
            $choice           = $choice_id ? $this->choices->get( $choice_id ) : null;
            $is_correct       = $choice && ! empty( $choice['is_correct'] ) && (int) $choice['question_id'] === $question_id;
            $awarded          = $is_correct ? $points : 0.0;
            $score           += $awarded;

            $breakdown[] = array(
                'question_id'        => $question_id,
                'selected_choice_id' => $choice_id,
                'is_correct'         => $is_correct,
                'points_awarded'     => $awarded,
                'points_possible'    => $points,
            );
        }

        return array(
            'score'     => $score,
            'max_score' => $max_score,
            'breakdown' => $breakdown,
        );
    }

    /**
     * Hide sensitive correctness data for students.
     *
     * @param array $breakdown Score breakdown.
     *
     * @return array
     */
    private function filter_breakdown_for_role( array $breakdown ): array {
        if ( $this->is_manage_context() ) {
            return $breakdown;
        }

        return array_map(
            static function ( array $item ): array {
                unset( $item['is_correct'] );
                return $item;
            },
            $breakdown
        );
    }
}
