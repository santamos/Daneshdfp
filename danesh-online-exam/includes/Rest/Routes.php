<?php
/**
 * REST API routes.
 *
 * @package Danesh\OnlineExam\Rest
 */

namespace Danesh\OnlineExam\Rest;

use Danesh\OnlineExam\Services\ExamService;
use WP_Error;
use WP_REST_Request;
use WP_REST_Server;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Routes {
    private ExamService $service;

    public function __construct( ?ExamService $service = null ) {
        $this->service = $service ?: new ExamService();
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
                'permission_callback' => array( $this, 'check_permissions' ),
                'args'                => $this->get_exam_args(),
            )
        );

        register_rest_route(
            'danesh/v1',
            '/exams/(?P<id>\\d+)',
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( $this, 'get_exam' ),
                'permission_callback' => array( $this, 'check_permissions' ),
                'args'                => array(
                    'id' => array(
                        'type'              => 'integer',
                        'sanitize_callback' => 'absint',
                        'required'          => true,
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
                    'permission_callback' => array( $this, 'check_permissions' ),
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
                    'permission_callback' => array( $this, 'check_permissions' ),
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
                    'permission_callback' => array( $this, 'check_permissions' ),
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
                    'callback'            => array( $this, 'create_choice' ),
                    'permission_callback' => array( $this, 'check_permissions' ),
                    'args'                => $this->get_choice_args(),
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
        $response    = $this->service->list_choices( $question_id );

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
     * Permission check for exam management.
     */
    public function check_permissions() {
        if ( current_user_can( 'danesh_manage_exams' ) || current_user_can( 'manage_options' ) ) {
            return true;
        }

        return new WP_Error( 'rest_forbidden', __( 'You are not allowed to manage exams.', 'danesh-online-exam' ), array( 'status' => 403 ) );
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
                'sanitize_callback' => 'wp_kses_post',
            ),
            'points' => array(
                'type'              => 'number',
                'required'          => false,
                'sanitize_callback' => array( $this, 'sanitize_float' ),
                'default'           => 1,
            ),
            'sort_order' => array(
                'type'              => 'integer',
                'required'          => false,
                'sanitize_callback' => 'absint',
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
                'default'           => 0,
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
}
