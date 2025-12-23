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
                'id' => 0,
            ),
            $atts,
            'danesh_exam'
        );

        $exam_id = absint( $atts['id'] );

        if ( 0 === $exam_id ) {
            return esc_html__( 'No exam specified.', 'danesh-online-exam' );
        }

        $heading = esc_html__( 'Danesh Online Exam', 'danesh-online-exam' );
        $message = sprintf(
            /* translators: %s: Exam ID. */
            esc_html__( 'Exam placeholder for ID %s.', 'danesh-online-exam' ),
            esc_html( (string) $exam_id )
        );

        ob_start();
        ?>
        <div class="danesh-exam" data-exam-id="<?php echo esc_attr( (string) $exam_id ); ?>">
            <h3><?php echo esc_html( $heading ); ?></h3>
            <p><?php echo esc_html( $message ); ?></p>
        </div>
        <?php

        return (string) ob_get_clean();
    }
}
