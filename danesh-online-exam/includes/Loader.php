<?php
/**
 * Loader to manage hooks and shortcodes.
 *
 * @package Danesh\OnlineExam
 */

namespace Danesh\OnlineExam;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Loader {
    /**
     * The array of actions registered with WordPress.
     *
     * @var array
     */
    protected array $actions = array();

    /**
     * The array of shortcodes registered with WordPress.
     *
     * @var array
     */
    protected array $shortcodes = array();

    /**
     * Add a new action to the collection to be registered with WordPress.
     *
     * @param string $hook          The name of the WordPress action that is being registered.
     * @param object $component     A reference to the instance of the object on which the action is defined.
     * @param string $callback      The name of the function definition on the $component.
     * @param int    $priority      The priority at which the function should be fired.
     * @param int    $accepted_args The number of arguments that should be passed to the $callback.
     */
    public function add_action( string $hook, object $component, string $callback, int $priority = 10, int $accepted_args = 1 ): void {
        $this->actions[] = compact( 'hook', 'component', 'callback', 'priority', 'accepted_args' );
    }

    /**
     * Add a new shortcode to the collection to be registered with WordPress.
     *
     * @param string $tag       Shortcode tag.
     * @param object $component Component instance.
     * @param string $callback  Callback method.
     */
    public function add_shortcode( string $tag, object $component, string $callback ): void {
        $this->shortcodes[] = compact( 'tag', 'component', 'callback' );
    }

    /**
     * Register the actions and shortcodes with WordPress.
     */
    public function run(): void {
        foreach ( $this->actions as $hook ) {
            add_action( $hook['hook'], array( $hook['component'], $hook['callback'] ), $hook['priority'], $hook['accepted_args'] );
        }

        foreach ( $this->shortcodes as $shortcode ) {
            add_shortcode( $shortcode['tag'], array( $shortcode['component'], $shortcode['callback'] ) );
        }
    }
}
