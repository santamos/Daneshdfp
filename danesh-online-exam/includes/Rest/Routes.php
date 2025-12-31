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
use WP_REST_Response;
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
            '/exams/(?P<exam_id>\\d+)/attempts',
            array(
                array(
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => array( $this, 'get_exam_attempts' ),
                    'permission_callback' => 'is_user_logged_in',
                    'args'                => array(
                        'exam_id' => array(
                            'type'              => 'integer',
                            'sanitize_callback' => 'absint',
                            'required'          => true,
                            'validate_callback' => array( $this, 'validate_non_negative_int' ),
                        ),
                        'user_id' => array(
                            'type'              => 'integer',
                            'sanitize_callback' => 'absint',
                            'required'          => false,
                            'validate_callback' => array( $this, 'validate_non_negative_int' ),
                        ),
                    ),
                ),
                array(
                    'methods'             => WP_REST_Server::CREATABLE,
                    'callback'            => array( $this, 'start_attempt' ),
                    'permission_callback' => array( $this, 'check_student_permissions' ),
                    'args'                => array(
                        'exam_id' => array(
                            'type'              => 'integer',
                            'sanitize_callback' => 'absint',
                            'required'          => true,
                            'validate_callback' => array( $this, 'validate_non_negative_int' ),
                        ),
                    ),
                ),
            )
        );

        register_rest_route(
            'danesh/v1',
            '/exams/(?P<exam_id>\\d+)/attempts/active',
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( $this, 'get_active_exam_attempt' ),
                'permission_callback' => 'is_user_logged_in',
                'args'                => array(
                    'exam_id' => array(
                        'type'              => 'integer',
                        'sanitize_callback' => 'absint',
                        'required'          => true,
                        'validate_callback' => array( $this, 'validate_non_negative_int' ),
                    ),
                    'user_id' => array(
                        'type'              => 'integer',
                        'sanitize_callback' => 'absint',
                        'required'          => false,
                        'validate_callback' => array( $this, 'validate_non_negative_int' ),
                    ),
                ),
            )
        );

        register_rest_route(
            'danesh/v1',
            '/exams/(?P<exam_id>\\d+)/attempts/eligibility',
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( $this, 'get_attempt_eligibility' ),
                'permission_callback' => 'is_user_logged_in',
                'args'                => array(
                    'exam_id' => array(
                        'type'              => 'integer',
                        'sanitize_callback' => 'absint',
                        'required'          => true,
                        'validate_callback' => array( $this, 'validate_non_negative_int' ),
                    ),
                    'user_id' => array(
                        'type'              => 'integer',
                        'sanitize_callback' => 'absint',
                        'required'          => false,
                        'validate_callback' => array( $this, 'validate_non_negative_int' ),
                    ),
                ),
            )
        );

        register_rest_route(
            'danesh/v1',
            '/attempts/(?P<attempt_id>\\d+)',
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( $this, 'get_attempt' ),
                'permission_callback' => 'is_user_logged_in',
                'args'                => array(
                    'attempt_id' => array(
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
            '/attempts/(?P<attempt_id>\\d+)/paper',
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( $this, 'get_attempt_paper' ),
                'permission_callback' => 'is_user_logged_in',
                'args'                => array(
                    'attempt_id' => array(
                        'type'              => 'integer',
                        'sanitize_callback' => 'absint',
                        'required'          => true,
                        'validate_callback' => array( $this, 'validate_non_negative_int' ),
                    ),
                    'context' => array(
                        'type'              => 'string',
                        'sanitize_callback' => 'sanitize_text_field',
                        'enum'              => array( 'view', 'edit' ),
                        'default'           => 'view',
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

        register_rest_route(
            'danesh/v1',
            '/me',
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( $this, 'get_me' ),
                'permission_callback' => array( $this, 'check_student_permissions' ),
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

        return $this->prepare_response( $response, 201, $request );
    }

    /**
     * Get an exam.
     */
    public function get_exam( WP_REST_Request $request ) {
        $exam_id  = (int) $request->get_param( 'id' );
        $response = $this->service->get_exam( $exam_id );

        return $this->prepare_response( $response, 200, $request );
    }

    /**
     * List exam questions.
     */
    public function list_questions( WP_REST_Request $request ) {
        $exam_id  = (int) $request->get_param( 'id' );
        $response = $this->service->list_questions( $exam_id );

        return $this->prepare_response( $response, 200, $request );
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

        return $this->prepare_response( $response, 201, $request );
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
                return $this->prepare_response( $permission, 403, $request );
            }
        }

        $response = $this->service->list_choices( $question_id );

        return $this->prepare_response( $response, 200, $request );
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

        return $this->prepare_response( $response, 201, $request );
    }

    /**
     * Start an attempt.
     */
    public function start_attempt( WP_REST_Request $request ) {
        $exam_id  = (int) ( $request->get_param( 'exam_id' ) ?? $request->get_param( 'id' ) );
        $response = $this->attempt_service->start_attempt( $exam_id );

        $status = 201;

        if ( ! is_wp_error( $response ) && ! empty( $response['resumed'] ) ) {
            $status = 200;
        }

        return $this->prepare_response( $response, $status, $request );
    }

    /**
     * List attempts for an exam.
     */
    public function get_exam_attempts( WP_REST_Request $request ) {
        $exam_id    = (int) ( $request->get_param( 'exam_id' ) ?? $request->get_param( 'id' ) );
        $can_manage = current_user_can( 'danesh_manage_exams' ) || current_user_can( 'manage_options' );
        $user_id    = null;

        if ( $can_manage ) {
            $user_param = $request->get_param( 'user_id' );

            if ( null !== $user_param ) {
                $user_id = absint( $user_param );
            }
        } else {
            $user_id = get_current_user_id();
        }

        $response = $this->attempt_service->list_exam_attempts( $exam_id, $user_id );
        return $this->prepare_response( $response, 200, $request );
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

        if ( is_array( $payload ) && isset( $payload['answers'] ) && is_array( $payload['answers'] ) ) {
            foreach ( $payload['answers'] as &$answer ) {
                if ( is_array( $answer ) && ! isset( $answer['choice_id'] ) && isset( $answer['selected_choice_id'] ) ) {
                    $answer['choice_id'] = $answer['selected_choice_id'];
                }
            }
            unset( $answer );
        }

        $response = $this->attempt_service->save_answers( $attempt_id, is_array( $payload ) ? $payload : array() );

        return $this->prepare_response( $response, 200, $request );
    }

    /**
     * Get attempt status and answers.
     */
    public function get_attempt( WP_REST_Request $request ) {
        $attempt_id = (int) $request->get_param( 'attempt_id' );
        $response   = $this->attempt_service->get_attempt( $attempt_id );

        return $this->prepare_response( $response, 200, $request );
    }

    /**
     * Get attempt paper.
     */
    public function get_attempt_paper( WP_REST_Request $request ) {
        $attempt_id = (int) $request->get_param( 'attempt_id' );
        $response   = $this->attempt_service->get_attempt_paper( $attempt_id, $request );

        // prepare_response خودش WP_Error را همانطور برمی‌گرداند
        return $this->prepare_response( $response, 200, $request );
    }

    /**
     * Get the active attempt for the current (or specified) user.
     */
    public function get_active_exam_attempt( WP_REST_Request $request ) {
        $exam_id = (int) $request->get_param( 'exam_id' );
        $user_id = $request->get_param( 'user_id' );

        if ( null !== $user_id ) {
            $user_id = absint( $user_id );
        }

        $response      = $this->attempt_service->get_active_attempt( $exam_id, $user_id );
        return $this->prepare_response( $response, 200, $request );
    }

    /**
     * Get attempt eligibility / resume state.
     */
    public function get_attempt_eligibility( WP_REST_Request $request ) {
        $exam_id = (int) $request->get_param( 'exam_id' );
        $user_id = $request->get_param( 'user_id' );

        if ( null !== $user_id ) {
            $user_id = absint( $user_id );
        }

        $response      = $this->attempt_service->get_attempt_eligibility( $exam_id, $user_id );
        return $this->prepare_response( $response, 200, $request );
    }

    /**
     * Submit an attempt.
     */
    public function submit_attempt( WP_REST_Request $request ) {
        $attempt_id = (int) $request->get_param( 'id' );
        $response   = $this->attempt_service->submit_attempt( $attempt_id );

        return $this->prepare_response( $response, 200, $request );
    }

    /**
     * Get attempt report.
     */
    public function get_attempt_report( WP_REST_Request $request ) {
        $attempt_id = (int) $request->get_param( 'id' );
        $response   = $this->attempt_service->get_attempt_report( $attempt_id );

        return $this->prepare_response( $response, 200, $request );
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
     * Get current user info.
     */
    public function get_me( WP_REST_Request $request ) {
        $user_id = get_current_user_id();

        if ( ! $user_id ) {
            return $this->prepare_response( new WP_Error( 'not_logged_in', 'You must be logged in.', array( 'status' => 401 ) ), 401, $request );
        }

        $user = wp_get_current_user();

        return $this->prepare_response(
            array(
                'user_id'           => (int) $user_id,
                'roles'             => array_values( $user->roles ),
                'can_manage_exams'  => current_user_can( 'danesh_manage_exams' ) || current_user_can( 'manage_options' ),
            ),
            200,
            $request
        );
    }

    /**
     * Prepare response with consistent structure and headers.
     *
     * @param mixed $response Response from service.
     * @param int   $status   HTTP status for success.
     */
    private function prepare_response( $response, int $status = 200, ?WP_REST_Request $request = null ): WP_REST_Response {
        $is_error = is_wp_error( $response );

        if ( $is_error ) {
            $error_data   = $response->get_error_data();
            $error_status = 400;

            if ( is_array( $error_data ) && isset( $error_data['status'] ) && is_int( $error_data['status'] ) ) {
                $error_status = (int) $error_data['status'];
            }

            $rest_response = rest_convert_error_to_response( $response );
            $rest_response->set_status( $error_status );
        } else {
            $rest_response = rest_ensure_response( $response );
            $rest_response->set_status( $status );
        }

        if ( $request instanceof WP_REST_Request && $this->wants_envelope( $request ) ) {
            $rest_response = $this->envelope_response( $rest_response, $is_error );
        }

        return $this->apply_no_cache_headers( $rest_response );
    }

    /**
     * Check whether the request opted into the response envelope.
     */
    private function wants_envelope( WP_REST_Request $request ): bool {
        $param = $request->get_param( 'danesh_envelope' );

        if ( null !== $param ) {
            if ( is_array( $param ) ) {
                $param_values = array_values( $param );
                $param        = end( $param_values );
            }

            return $this->is_truthy_envelope_value( $param );
        }

        $header = $request->get_header( 'X-Danesh-Envelope' );

        if ( null === $header ) {
            return false;
        }

        if ( is_array( $header ) ) {
            $header_values = array_values( $header );
            $header        = reset( $header_values );
        }

        return $this->is_truthy_envelope_value( $header );
    }

    /**
     * Determine whether a value opts into the response envelope.
     *
     * @param mixed $value Value to inspect.
     */
    private function is_truthy_envelope_value( $value ): bool {
        if ( is_string( $value ) ) {
            $value = strtolower( trim( $value ) );
        }

        return in_array( $value, array( 1, true, '1', 'true', 'yes', 'on' ), true );
    }

    /**
     * Wrap the response in an envelope structure without altering status or headers.
     */
    private function envelope_response( WP_REST_Response $rest_response, bool $is_error ): WP_REST_Response {
        $status  = $rest_response->get_status();
        $data    = $rest_response->get_data();
        $payload = array(
            'success' => ! $is_error,
            'meta'    => array( 'status' => $status ),
        );

        if ( $is_error ) {
            $payload['error'] = array(
                'code'    => ( is_array( $data ) && isset( $data['code'] ) ) ? $data['code'] : 'unknown_error',
                'message' => ( is_array( $data ) && isset( $data['message'] ) ) ? $data['message'] : '',
                'data'    => ( is_array( $data ) && array_key_exists( 'data', $data ) ) ? $data['data'] : null,
            );
        } else {
            $payload['data'] = $data;
        }

        $enveloped_response = new WP_REST_Response( $payload, $status );
        $enveloped_response->set_links( $rest_response->get_links() );

        foreach ( $rest_response->get_headers() as $header => $value ) {
            $enveloped_response->header( $header, $value );
        }

        return $enveloped_response;
    }

    /**
     * Apply no-cache headers to a REST response.
     *
     * @param \WP_REST_Response $rest_response REST response.
     */
    private function apply_no_cache_headers( WP_REST_Response $rest_response ): WP_REST_Response {
        foreach ( wp_get_nocache_headers() as $header => $value ) {
            $rest_response->header( $header, $value );
        }

        $rest_response->header( 'Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0' );
        $rest_response->header( 'Pragma', 'no-cache' );
        $rest_response->header( 'Expires', '0' );
        $rest_response->header( 'Vary', 'Authorization, Cookie' );

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
                    'required'   => array( 'question_id' ),
                    'properties' => array(
                        'question_id' => array(
                            'type'              => 'integer',
                            'sanitize_callback' => 'absint',
                            'required'          => true,
                            'validate_callback' => array( $this, 'validate_non_negative_int' ),
                        ),
                        'choice_id'   => array(
                            'type'              => array( 'integer', 'null' ),
                            'sanitize_callback' => array( $this, 'sanitize_nullable_absint' ),
                            'required'          => false,
                            'validate_callback' => array( $this, 'validate_nullable_non_negative_int' ),
                        ),
                        'selected_choice_id'   => array(
                            'type'              => array( 'integer', 'null' ),
                            'sanitize_callback' => array( $this, 'sanitize_nullable_absint' ),
                            'required'          => false,
                            'validate_callback' => array( $this, 'validate_nullable_non_negative_int' ),
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
     * Sanitize nullable integer fields.
     *
     * @param mixed $value Raw value.
     */
    public function sanitize_nullable_absint( $value ) {
        if ( is_null( $value ) ) {
            return null;
        }

        return absint( $value );
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
     * Validate nullable non-negative integer fields.
     *
     * @param mixed $value Value to validate.
     */
    public function validate_nullable_non_negative_int( $value ): bool {
        if ( is_null( $value ) ) {
            return true;
        }

        return $this->validate_non_negative_int( $value );
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
