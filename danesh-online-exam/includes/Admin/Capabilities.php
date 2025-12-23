<?php
/**
 * Capability and role registration.
 *
 * @package Danesh\OnlineExam\Admin
 */

namespace Danesh\OnlineExam\Admin;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Capabilities {
    /**
     * Adds roles and capabilities for the plugin.
     */
    public static function add_roles_and_caps(): void {
        $caps = array(
            'danesh_manage_exams',
            'danesh_take_exams',
        );

        add_role(
            'danesh_teacher',
            esc_html__( 'Danesh Teacher', 'danesh-online-exam' ),
            array(
                'read'                => true,
                'danesh_manage_exams' => true,
                'danesh_take_exams'   => true,
            )
        );

        add_role(
            'danesh_student',
            esc_html__( 'Danesh Student', 'danesh-online-exam' ),
            array(
                'read'              => true,
                'danesh_take_exams' => true,
            )
        );

        $administrator = get_role( 'administrator' );
        if ( $administrator ) {
            foreach ( $caps as $cap ) {
                if ( ! $administrator->has_cap( $cap ) ) {
                    $administrator->add_cap( $cap );
                }
            }
        }
    }
}
