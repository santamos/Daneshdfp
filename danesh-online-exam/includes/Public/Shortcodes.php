<?php
/**
 * Shortcode handlers.
 *
 * @package Danesh\OnlineExam\Public
 */

namespace Danesh\OnlineExam\Public;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Shortcodes {
    /**
     * Render the exam shortcode.
     *
     * @param array  $atts    Shortcode attributes.
     * @param string $content Enclosed content.
     */
    public function render_exam( array $atts = array(), string $content = '' ): string {
        $atts = shortcode_atts(
            array(
                'exam_id' => 0,
                'id'      => 0,
            ),
            $atts,
            'danesh_exam'
        );

        $exam_id = absint( $atts['exam_id'] ?: $atts['id'] );

        if ( 0 === $exam_id ) {
            return esc_html__( 'No exam specified.', 'danesh-online-exam' );
        }

        $rest_url = rest_url( 'danesh/v1/' );
        $nonce    = wp_create_nonce( 'wp_rest' );

        wp_enqueue_script( 'danesh-exam-frontend' );
        wp_enqueue_style( 'danesh-exam-frontend' );

        wp_localize_script(
            'danesh-exam-frontend',
            'DaneshExamConfig',
            array(
                'restUrl' => $rest_url,
                'nonce'   => $nonce,
            )
        );

        ob_start();
        ?>
        <div
            class="danesh-exam"
            data-exam-id="<?php echo esc_attr( (string) $exam_id ); ?>"
            data-rest-url="<?php echo esc_attr( $rest_url ); ?>"
            data-nonce="<?php echo esc_attr( $nonce ); ?>"
        >
            <div class="danesh-exam__loader">
                <?php echo esc_html__( 'Preparing your exam...', 'danesh-online-exam' ); ?>
            </div>
        </div>
        <?php

        return (string) ob_get_clean();
    }
}
