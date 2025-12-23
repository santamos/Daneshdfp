<?php
/**
 * Activation handler.
 *
 * @package Danesh\OnlineExam
 */

namespace Danesh\OnlineExam;

use Danesh\OnlineExam\Admin\Capabilities;
use Danesh\OnlineExam\DB\Schema;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Activator {
    /**
     * Run tasks on activation.
     */
    public static function activate(): void {
        Schema::install();
        Capabilities::add_roles_and_caps();
    }
}
