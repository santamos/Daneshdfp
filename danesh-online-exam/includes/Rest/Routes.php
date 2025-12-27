<?php
/**
 * REST API routes.
 *
 * @package Danesh\OnlineExam\Rest
 */

namespace Danesh\OnlineExam\Rest;

use Danesh\OnlineExam\Services\AttemptService;
use Danesh\OnlineExam\Services\ExamService;
use WP_Error;
use WP_REST_Request;
use WP_REST_Server;
use function absint;
use function sanitize_text_field;
use function sanitize_textarea_field;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Routes {
    private ExamService $service;

    private AttemptService $attempt_service;

    public function __construct( ?ExamService $service = null, ?AttemptService $attempt_service = null ) {
        $this->service          = $service ?: new ExamService();
        $this->attempt_service  = $attempt_service ?: new AttemptService();
    }

    /**
     * Register REST routes.
     */
    public function register_routes(): void {
        register_rest_route(
            'danesh/v1',
            '/exams',
            array(
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => array( $this, 'create_exam' ),
                'permission_callback' => array( $this, 'check_manage_permissions' ),
                'args'                => $this->get_exam_args(),
            )
        );

        register_rest_route(
            'danesh/v1',
            '/exams/(?P<id>\\d+)',
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( $this, 'get_exam' ),
                'permission_callback' => array( $this, 'check_read_permissions' ),
                'args'                => array(
                    'id' => array(
                        'type'              => 'integer',
                        'sanitize_callback' => 'absint',
                        'required'          => true,
                        'validate_callback' => array( $this, 'validate_non_negative_int' ),
                    ),
                ),
            )
        );

        register_rest_route(
            'danesh/v1',
            '/exams/(?P<id>\\d+)/questions',
            array(
                array(
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => array( $this, 'list_questions' ),
                    'permission_callback' => array( $this, 'check_read_permissions' ),
                    'args'                => array(
                        'id' => array(
                            'type'              => 'integer',
                            'sanitize_callback' => 'absint',
                            'required'          => true,
                        ),
                    ),
                ),
                array(
                    'methods'             => WP_REST_Server::CREATABLE,
                    'callback'            => array( $this, 'create_question' ),
                    'permission_callback' => array( $this, 'check_manage_permissions' ),
                    'args'                => $this->get_question_args(),
                ),
            )
        );

        register_rest_route(
            'danesh/v1',
            '/questions/(?P<id>\\d+)/choices',
            array(
                array(
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => array( $this, 'list_choices' ),
                    'permission_callback' => array( $this, 'check_read_permissions' ),
                    'args'                => array(
                        'id' => array(
                            'type'              => 'integer',
                            'sanitize_callback' => 'absint',
                            'required'          => true,
                        ),
                        'context' => array(
                            'type'              => 'string',
                            'sanitize_callback' => 'sanitize_text_field',
                            'enum'              => array( 'view', 'edit' ),
                            'default'           => 'view',
                        ),
                    ),
                ),
                array(
                    'methods'             => WP_REST_Server::CREATABLE,
                    'callback'            => array( $this, 'create_choice' ),
                    'permission_callback' => array( $this, 'check_manage_permissions' ),
                    'args'                => $this->get_choice_args(),
                ),
            )
        );

        register_rest_route(
            'danesh/v1',
            '/exams/(?P<id>\\d+)/attempts',
            array(
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => array( $this, 'start_attempt' ),
                'permission_callback' => array( $this, 'check_student_permissions' ),
                'args'                => array(
                    'id' => array(
                        'type'              => 'integer',
                        'sanitize_callback' => 'absint',
                        'required'          => true,
                        'validate_callback' => array( $this, 'validate_non_negative_int' ),
                    ),
                ),
            )
        );

        register_rest_route(
            'danesh/v1',
            '/attempts/(?P<id>\\d+)/answers',
            array(
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => array( $this, 'save_answer' ),
                'permission_callback' => array( $this, 'check_student_permissions' ),
                'args'                => $this->get_answer_args(),
            )
        );

        register_rest_route(
            'danesh/v1',
            '/attempts/(?P<id>\\d+)/submit',
            array(
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => array( $this, 'submit_attempt' ),
                'permission_callback' => array( $this, 'check_student_permissions' ),
                'args'                => array(
                    'id' => array(
                        'type'              => 'integer',
                        'sanitize_callback' => 'absint',
                        'required'          => true,
                        'validate_callback' => array( $this, 'validate_non_negative_int' ),
                    ),
                ),
            )
        );

        register_rest_route(
            'danesh/v1',
            '/attempts/(?P<id>\\d+)/report',
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( $this, 'get_attempt_report' ),
                'permission_callback' => array( $this, 'check_student_permissions' ),
                'args'                => array(
                    'id' => array(
                        'type'              => 'integer',
                        'sanitize_callback' => 'absint',
                        'required'          => true,
                        'validate_callback' => array( $this, 'validate_non_negative_int' ),
                    ),
                ),
            )
        );
    }

    /**
     * Create an exam.
     */
    public function create_exam( WP_REST_Request $request ) {
        $payload  = array(
            'title'            => $request->get_param( 'title' ),
            'duration_seconds' => $request->get_param( 'duration_seconds' ),
            'status'           => $request->get_param( 'status' ),
        );
        $response = $this->service->create_exam( $payload );

        return $this->prepare_response( $response, 201 );
    }

    /**
     * Get an exam.
     */
    public function get_exam( WP_REST_Request $request ) {
        $exam_id  = (int) $request->get_param( 'id' );
        $response = $this->service->get_exam( $exam_id );

        return $this->prepare_response( $response );
    }

    /**
     * List exam questions.
     */
    public function list_questions( WP_REST_Request $request ) {
        $exam_id  = (int) $request->get_param( 'id' );
        $response = $this->service->list_questions( $exam_id );

        return $this->prepare_response( $response );
    }

    /**
     * Create question for an exam.
     */
    public function create_question( WP_REST_Request $request ) {
        $exam_id  = (int) $request->get_param( 'id' );
        $payload  = array(
            'prompt'     => $request->get_param( 'prompt' ),
            'points'     => $request->get_param( 'points' ),
            'sort_order' => $request->get_param( 'sort_order' ),
            'type'       => $request->get_param( 'type' ),
        );
        $response = $this->service->create_question( $exam_id, $payload );

        return $this->prepare_response( $response, 201 );
    }

    /**
     * List choices for a question.
     */
    public function list_choices( WP_REST_Request $request ) {
        $question_id = (int) $request->get_param( 'id' );
        $context     = $request->get_param( 'context' ) ?: 'view';

        if ( 'edit' === $context ) {
            $permission = $this->check_manage_permissions();

            if ( is_wp_error( $permission ) ) {
                return $permission;
            }
        }

        $response = $this->service->list_choices( $question_id );

        return $this->prepare_response( $response );
    }

    /**
     * Create a choice for a question.
     */
    public function create_choice( WP_REST_Request $request ) {
        $question_id = (int) $request->get_param( 'id' );
        $payload     = array(
            'choice_text' => $request->get_param( 'choice_text' ),
            'is_correct'  => $request->get_param( 'is_correct' ),
            'sort_order'  => $request->get_param( 'sort_order' ),
        );
        $response    = $this->service->create_choice( $question_id, $payload );

        return $this->prepare_response( $response, 201 );
    }

    /**
     * Start an attempt.
     */
    public function start_attempt( WP_REST_Request $request ) {
        $exam_id  = (int) $request->get_param( 'id' );
        $response = $this->attempt_service->start_attempt( $exam_id );

        $status = 201;

        if ( ! is_wp_error( $response ) && ! empty( $response['resumed'] ) ) {
            $status = 200;
        }

        return $this->prepare_response( $response, $status );
    }

    /**
     * Save an answer for an attempt.
     */
    public function save_answer( WP_REST_Request $request ) {
        $attempt_id  = (int) $request->get_param( 'id' );
        $payload     = $request->get_json_params();

        if ( empty( $payload ) ) {
            $payload = array(
                'question_id' => $request->get_param( 'question_id' ),
                'choice_id'   => $request->get_param( 'choice_id' ),
                'answers'     => $request->get_param( 'answers' ),
            );
        }

        $response = $this->attempt_service->save_answers( $attempt_id, is_array( $payload ) ? $payload : array() );

        return $this->prepare_response( $response );
    }

    /**
     * Submit an attempt.
     */
    public function submit_attempt( WP_REST_Request $request ) {
        $attempt_id = (int) $request->get_param( 'id' );
        $response   = $this->attempt_service->submit_attempt( $attempt_id );

        return $this->prepare_response( $response );
    }

    /**
     * Get attempt report.
     */
    public function get_attempt_report( WP_REST_Request $request ) {
        $attempt_id = (int) $request->get_param( 'id' );
        $response   = $this->attempt_service->get_attempt_report( $attempt_id );

        return $this->prepare_response( $response );
    }

    /**
     * Permission check for exam management.
     */
    public function check_manage_permissions() {
        if ( current_user_can( 'danesh_manage_exams' ) || current_user_can( 'manage_options' ) ) {
            return true;
        }

        return new WP_Error( 'rest_forbidden', __( 'You are not allowed to manage exams.', 'danesh-online-exam' ), array( 'status' => 403 ) );
    }

    /**
     * Permission check for viewing exam resources.
     */
    public function check_read_permissions() {
        if ( is_user_logged_in() ) {
            return true;
        }

        return new WP_Error( 'rest_forbidden', __( 'Authentication required to view exams.', 'danesh-online-exam' ), array( 'status' => 403 ) );
    }

    /**
     * Permission check for students and managers.
     */
    public function check_student_permissions() {
        if ( is_user_logged_in() ) {
            return true;
        }

        return new WP_Error( 'rest_forbidden', __( 'Authentication required.', 'danesh-online-exam' ), array( 'status' => 403 ) );
    }

    /**
     * Prepare response or pass errors through.
     *
     * @param mixed $response Response from service.
     * @param int   $status   HTTP status for success.
     */
    private function prepare_response( $response, int $status = 200 ) {
        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $rest_response = rest_ensure_response( $response );
        $rest_response->set_status( $status );

        return $rest_response;
    }

    /**
     * Schema for exam creation.
     */
    private function get_exam_args(): array {
        return array(
            'title'            => array(
                'type'              => 'string',
                'required'          => true,
                'sanitize_callback' => 'sanitize_text_field',
            ),
            'duration_seconds' => array(
                'type'              => 'integer',
                'required'          => false,
                'sanitize_callback' => 'absint',
                'validate_callback' => array( $this, 'validate_non_negative_int' ),
                'default'           => 0,
            ),
            'status'           => array(
                'type'              => 'string',
                'required'          => false,
                'sanitize_callback' => 'sanitize_text_field',
                'enum'              => array( 'draft', 'published' ),
                'default'           => 'draft',
            ),
        );
    }

    /**
     * Schema for question creation.
     */
    private function get_question_args(): array {
        return array(
            'id' => array(
                'type'              => 'integer',
                'sanitize_callback' => 'absint',
                'required'          => true,
            ),
            'prompt' => array(
                'type'              => 'string',
                'required'          => true,
                'sanitize_callback' => 'sanitize_textarea_field',
            ),
            'points' => array(
                'type'              => 'number',
                'required'          => false,
                'sanitize_callback' => array( $this, 'sanitize_float' ),
                'validate_callback' => array( $this, 'validate_non_negative_number' ),
                'default'           => 1,
            ),
            'sort_order' => array(
                'type'              => 'integer',
                'required'          => false,
                'sanitize_callback' => 'absint',
                'validate_callback' => array( $this, 'validate_non_negative_int' ),
                'default'           => 0,
            ),
            'type' => array(
                'type'              => 'string',
                'required'          => false,
                'sanitize_callback' => 'sanitize_text_field',
                'default'           => 'mcq',
            ),
        );
    }

    /**
     * Schema for choice creation.
     */
    private function get_choice_args(): array {
        return array(
            'id' => array(
                'type'              => 'integer',
                'sanitize_callback' => 'absint',
                'required'          => true,
            ),
            'choice_text' => array(
                'type'              => 'string',
                'required'          => true,
                'sanitize_callback' => 'sanitize_text_field',
            ),
            'is_correct' => array(
                'type'              => 'boolean',
                'required'          => false,
                'sanitize_callback' => array( $this, 'sanitize_boolean' ),
                'default'           => false,
            ),
            'sort_order' => array(
                'type'              => 'integer',
                'required'          => false,
                'sanitize_callback' => 'absint',
                'validate_callback' => array( $this, 'validate_non_negative_int' ),
                'default'           => 0,
            ),
        );
    }

    /**
     * Schema for saving an answer.
     */
    private function get_answer_args(): array {
        return array(
            'id' => array(
                'type'              => 'integer',
                'sanitize_callback' => 'absint',
                'required'          => true,
                'validate_callback' => array( $this, 'validate_non_negative_int' ),
            ),
            'question_id' => array(
                'type'              => 'integer',
                'sanitize_callback' => 'absint',
                'required'          => false,
                'validate_callback' => array( $this, 'validate_non_negative_int' ),
            ),
            'choice_id' => array(
                'type'              => 'integer',
                'sanitize_callback' => 'absint',
                'required'          => false,
                'validate_callback' => array( $this, 'validate_non_negative_int' ),
            ),
            'answers' => array(
                'type'  => 'array',
                'required' => false,
                'items' => array(
                    'type'       => 'object',
                    'properties' => array(
                        'question_id' => array(
                            'type'              => 'integer',
                            'sanitize_callback' => 'absint',
                            'required'          => true,
                            'validate_callback' => array( $this, 'validate_non_negative_int' ),
                        ),
                        'choice_id'   => array(
                            'type'              => 'integer',
                            'sanitize_callback' => 'absint',
                            'required'          => true,
                            'validate_callback' => array( $this, 'validate_non_negative_int' ),
                        ),
                    ),
                ),
            ),
        );
    }

    /**
     * Sanitize boolean fields.
     *
     * @param mixed $value Raw value.
     */
    public function sanitize_boolean( $value ): bool {
        return (bool) filter_var( $value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE );
    }

    /**
     * Sanitize float values.
     *
     * @param mixed $value Raw value.
     */
    public function sanitize_float( $value ): float {
        return (float) $value;
    }

    /**
     * Validate non-negative integer fields.
     *
     * @param mixed $value Value to validate.
     */
    public function validate_non_negative_int( $value ): bool {
        return is_numeric( $value ) && ( (int) $value ) >= 0;
    }

    /**
     * Validate non-negative numeric fields.
     *
     * @param mixed $value Value to validate.
     */
    public function validate_non_negative_number( $value ): bool {
        return is_numeric( $value ) && (float) $value >= 0;
    }
}
