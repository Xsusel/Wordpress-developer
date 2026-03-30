<?php

use Brain\Monkey;
use Brain\Monkey\Functions;

require_once dirname( __DIR__ ) . '/wp-deweloper-gov-reporter/includes/class-dgr-price-history.php';

class PriceHistoryTest extends DGR_TestCase {

    private DGR_Price_History $price_history;

    protected function setUp(): void {
        parent::setUp();
        $this->price_history = new DGR_Price_History();
    }

    public function test_init_registers_save_post_hook(): void {
        Functions\expect( 'add_action' )
            ->once()
            ->with( 'save_post_dgr_unit', \Mockery::type( 'array' ), 5 );

        $this->price_history->init();
        $this->assertTrue( true );
    }

    public function test_check_returns_early_without_nonce(): void {
        $_POST = [];
        $this->price_history->check_for_price_changes( 1 );
        $this->assertTrue( true );
    }

    public function test_check_returns_early_without_price_data(): void {
        $_POST = [ 'dgr_unit_nonce' => 'abc123' ];
        Functions\when( 'wp_verify_nonce' )->justReturn( true );

        $this->price_history->check_for_price_changes( 1 );
        $this->assertTrue( true );
    }

    public function test_check_updates_history_when_price_changes(): void {
        $_POST = [
            'dgr_unit_nonce'       => 'abc123',
            'dgr_unit_price_total' => '600000',
            'dgr_unit_price_m2'    => '12000',
        ];

        Functions\when( 'wp_verify_nonce' )->justReturn( true );
        Functions\when( 'sanitize_text_field' )->returnArg();
        Functions\when( 'current_time' )->justReturn( '2026-03-30' );

        Functions\when( 'get_post_meta' )->alias( function ( $post_id, $key, $single = false ) {
            return match ( $key ) {
                '_dgr_unit_price_total' => '500000',
                '_dgr_unit_price_m2'    => '10000',
                '_dgr_price_history'    => [],
                default => '',
            };
        } );

        $saved_history = null;
        Functions\when( 'update_post_meta' )->alias( function ( $id, $key, $val ) use ( &$saved_history ) {
            $saved_history = $val;
        } );

        $this->price_history->check_for_price_changes( 1 );

        $this->assertNotNull( $saved_history );
        $this->assertCount( 1, $saved_history );
        $this->assertEquals( '2026-03-30', $saved_history[0]['date'] );
        $this->assertEquals( '600000', $saved_history[0]['price_total'] );
        $this->assertEquals( '12000', $saved_history[0]['price_m2'] );
    }

    public function test_check_does_not_update_when_prices_unchanged(): void {
        $_POST = [
            'dgr_unit_nonce'       => 'abc123',
            'dgr_unit_price_total' => '500000',
            'dgr_unit_price_m2'    => '10000',
        ];

        Functions\when( 'wp_verify_nonce' )->justReturn( true );
        Functions\when( 'sanitize_text_field' )->returnArg();

        Functions\when( 'get_post_meta' )->alias( function ( $post_id, $key, $single = false ) {
            return match ( $key ) {
                '_dgr_unit_price_total' => '500000',
                '_dgr_unit_price_m2'    => '10000',
                default => '',
            };
        } );

        $update_called = false;
        Functions\when( 'update_post_meta' )->alias( function () use ( &$update_called ) {
            $update_called = true;
        } );

        $this->price_history->check_for_price_changes( 1 );
        $this->assertFalse( $update_called );
    }

    public function test_check_updates_existing_entry_same_day(): void {
        $_POST = [
            'dgr_unit_nonce'       => 'abc123',
            'dgr_unit_price_total' => '620000',
            'dgr_unit_price_m2'    => '12400',
        ];

        Functions\when( 'wp_verify_nonce' )->justReturn( true );
        Functions\when( 'sanitize_text_field' )->returnArg();
        Functions\when( 'current_time' )->justReturn( '2026-03-30' );

        $existing_history = [
            [ 'date' => '2026-03-29', 'price_total' => '590000', 'price_m2' => '11800' ],
            [ 'date' => '2026-03-30', 'price_total' => '600000', 'price_m2' => '12000' ],
        ];

        Functions\when( 'get_post_meta' )->alias( function ( $post_id, $key, $single = false ) use ( $existing_history ) {
            return match ( $key ) {
                '_dgr_unit_price_total' => '600000',
                '_dgr_unit_price_m2'    => '12000',
                '_dgr_price_history'    => $existing_history,
                default => '',
            };
        } );

        $saved_history = null;
        Functions\when( 'update_post_meta' )->alias( function ( $id, $key, $val ) use ( &$saved_history ) {
            $saved_history = $val;
        } );

        $this->price_history->check_for_price_changes( 1 );

        $this->assertNotNull( $saved_history );
        $this->assertCount( 2, $saved_history );
        $this->assertEquals( '620000', $saved_history[1]['price_total'] );
        $this->assertEquals( '12400', $saved_history[1]['price_m2'] );
    }

    // ─── get_lowest_price_30_days() ──────────────────────────────────

    public function test_get_lowest_price_returns_false_on_empty_history(): void {
        Functions\when( 'get_post_meta' )->justReturn( '' );

        $result = DGR_Price_History::get_lowest_price_30_days( 1 );
        $this->assertFalse( $result );
    }

    public function test_get_lowest_price_returns_minimum_within_30_days(): void {
        $history = [
            [ 'date' => date( 'Y-m-d', strtotime( '-5 days' ) ), 'price_total' => 500000, 'price_m2' => 10000 ],
            [ 'date' => date( 'Y-m-d', strtotime( '-10 days' ) ), 'price_total' => 480000, 'price_m2' => 9600 ],
            [ 'date' => date( 'Y-m-d', strtotime( '-20 days' ) ), 'price_total' => 520000, 'price_m2' => 10400 ],
        ];

        Functions\when( 'get_post_meta' )->justReturn( $history );
        Functions\when( 'current_time' )->justReturn( time() );

        $result = DGR_Price_History::get_lowest_price_30_days( 1 );
        $this->assertEquals( 480000.0, $result );
    }

    public function test_get_lowest_price_ignores_entries_older_than_30_days(): void {
        $history = [
            [ 'date' => date( 'Y-m-d', strtotime( '-60 days' ) ), 'price_total' => 100000, 'price_m2' => 2000 ],
            [ 'date' => date( 'Y-m-d', strtotime( '-5 days' ) ), 'price_total' => 500000, 'price_m2' => 10000 ],
        ];

        Functions\when( 'get_post_meta' )->justReturn( $history );
        Functions\when( 'current_time' )->justReturn( time() );

        $result = DGR_Price_History::get_lowest_price_30_days( 1 );
        $this->assertEquals( 500000.0, $result );
    }

    public function test_get_lowest_price_fallback_to_current_price(): void {
        $history = [
            [ 'date' => date( 'Y-m-d', strtotime( '-60 days' ) ), 'price_total' => 100000, 'price_m2' => 2000 ],
        ];

        Functions\when( 'get_post_meta' )->alias( function ( $post_id, $key, $single = false ) use ( $history ) {
            return match ( $key ) {
                '_dgr_price_history'    => $history,
                '_dgr_unit_price_total' => '550000',
                default => '',
            };
        } );

        Functions\when( 'current_time' )->justReturn( time() );

        $result = DGR_Price_History::get_lowest_price_30_days( 1 );
        $this->assertEquals( 550000.0, $result );
    }
}
