<?php
/**
 * PHPUnit bootstrap file for WP Deweloper Gov Reporter tests.
 *
 * Uses Brain\Monkey to mock WordPress functions without a full WP installation.
 */

require_once dirname( __DIR__ ) . '/vendor/autoload.php';

// Load Brain\Monkey
use Brain\Monkey;

// Define WordPress constants that plugin code expects
if ( ! defined( 'ABSPATH' ) ) {
    define( 'ABSPATH', '/tmp/wordpress/' );
}

/**
 * Base test case with Brain\Monkey setup/teardown.
 */
abstract class DGR_TestCase extends \PHPUnit\Framework\TestCase {

    protected function setUp(): void {
        parent::setUp();
        Monkey\setUp();
    }

    protected function tearDown(): void {
        Monkey\tearDown();
        parent::tearDown();
    }
}
