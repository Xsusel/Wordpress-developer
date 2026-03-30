<?php

use Brain\Monkey;
use Brain\Monkey\Functions;

require_once dirname( __DIR__ ) . '/wp-deweloper-gov-reporter/includes/class-dgr-api-connector.php';

class ApiConnectorTest extends DGR_TestCase {

    private DGR_API_Connector $connector;

    protected function setUp(): void {
        parent::setUp();
        $this->connector = new DGR_API_Connector();
    }

    // ─── generate_daily_report() ─────────────────────────────────────

    public function test_generate_daily_report_returns_empty_when_no_units(): void {
        Functions\when( 'get_posts' )->justReturn( [] );

        $report = $this->connector->generate_daily_report();
        $this->assertEmpty( $report );
    }

    public function test_generate_daily_report_formats_unit_data_correctly(): void {
        $mock_unit     = new \stdClass();
        $mock_unit->ID = 10;

        Functions\when( 'get_posts' )->justReturn( [ $mock_unit ] );

        $serialized_history = serialize( [
            [ 'date' => '2026-03-28', 'price_m2' => 9800, 'price_total' => 544000 ],
            [ 'date' => '2026-03-30', 'price_m2' => 10000, 'price_total' => 555000 ],
        ] );

        Functions\when( 'get_post_meta' )->alias( function () use ( $serialized_history ) {
            $args = func_get_args();
            if ( count( $args ) === 1 && $args[0] === 10 ) {
                return [
                    '_dgr_unit_parent_investment' => [ '5' ],
                    '_dgr_unit_id'               => [ 'A-101' ],
                    '_dgr_unit_area'             => [ '55.5' ],
                    '_dgr_unit_rooms'            => [ '3' ],
                    '_dgr_unit_floor'            => [ '2' ],
                    '_dgr_unit_price_m2'         => [ '10000' ],
                    '_dgr_unit_price_total'      => [ '555000' ],
                    '_dgr_unit_status'           => [ 'available' ],
                    '_dgr_unit_dependencies'     => [ '[{"typ":"miejsce_postojowe","cena":45000}]' ],
                    '_dgr_price_history'         => [ $serialized_history ],
                ];
            }
            if ( $args[0] == 5 && isset( $args[1] ) && $args[1] === '_dgr_investment_id' ) {
                return 'INW-2026-001';
            }
            return '';
        } );

        Functions\when( 'maybe_unserialize' )->alias( function ( $val ) {
            $result = @unserialize( $val );
            return $result !== false ? $result : $val;
        } );

        $report = $this->connector->generate_daily_report();

        $this->assertCount( 1, $report );

        $unit = $report[0];
        $this->assertEquals( 'INW-2026-001', $unit['inwestycja_id'] );
        $this->assertEquals( 'A-101', $unit['lokal_id'] );
        $this->assertEquals( 55.5, $unit['powierzchnia_m2'] );
        $this->assertEquals( 3, $unit['pokoje'] );
        $this->assertEquals( 2, $unit['kondygnacja'] );
        $this->assertEquals( 10000.0, $unit['cena_m2'] );
        $this->assertEquals( 555000.0, $unit['cena_calkowita'] );
        $this->assertEquals( 'available', $unit['status'] );
        $this->assertIsArray( $unit['przynaleznosci'] );
        $this->assertEquals( 'miejsce_postojowe', $unit['przynaleznosci'][0]['typ'] );
        $this->assertCount( 2, $unit['historia_cen'] );
        $this->assertEquals( '2026-03-28', $unit['historia_cen'][0]['data'] );
    }

    public function test_generate_daily_report_handles_missing_meta_gracefully(): void {
        $mock_unit     = new \stdClass();
        $mock_unit->ID = 20;

        Functions\when( 'get_posts' )->justReturn( [ $mock_unit ] );

        Functions\when( 'get_post_meta' )->alias( function () {
            $args = func_get_args();
            if ( count( $args ) === 1 ) {
                return [];
            }
            return '';
        } );

        Functions\when( 'maybe_unserialize' )->justReturn( [] );

        $report = $this->connector->generate_daily_report();

        $this->assertCount( 1, $report );
        $unit = $report[0];
        $this->assertEquals( '', $unit['inwestycja_id'] );
        $this->assertEquals( '', $unit['lokal_id'] );
        $this->assertEquals( 0, $unit['powierzchnia_m2'] );
        $this->assertEquals( 0, $unit['pokoje'] );
        $this->assertEquals( 'available', $unit['status'] );
        $this->assertEmpty( $unit['przynaleznosci'] );
    }

    // ─── send_report() ───────────────────────────────────────────────

    public function test_send_report_logs_error_when_no_api_url(): void {
        Functions\when( 'get_option' )->alias( function ( $key, $default = false ) {
            return match ( $key ) {
                'dgr_api_endpoint' => '',
                'dgr_api_key'     => 'test-key',
                'dgr_api_log'     => '',
                default           => $default,
            };
        } );

        Functions\when( 'current_time' )->justReturn( '2026-03-30 12:00:00' );

        $logged = null;
        Functions\when( 'update_option' )->alias( function ( $key, $val ) use ( &$logged ) {
            $logged = $val;
        } );

        $this->connector->send_report( [ 'test' => 'data' ] );
        $this->assertStringContainsString( 'API URL not configured', $logged );
    }

    public function test_send_report_sends_json_with_bearer_auth(): void {
        Functions\when( 'get_option' )->alias( function ( $key, $default = false ) {
            return match ( $key ) {
                'dgr_api_endpoint' => 'https://api.dane.gov.pl/v1/reports',
                'dgr_api_key'     => 'secret-token-123',
                'dgr_api_log'     => '',
                default           => $default,
            };
        } );

        $test_data    = [ [ 'lokal_id' => 'A-101' ] ];
        $captured_args = null;

        Functions\when( 'wp_remote_post' )->alias( function ( $url, $args ) use ( &$captured_args ) {
            $captured_args = [ 'url' => $url, 'args' => $args ];
            return [ 'response' => [ 'code' => 200 ] ];
        } );

        Functions\when( 'is_wp_error' )->justReturn( false );
        Functions\when( 'wp_remote_retrieve_response_code' )->justReturn( 200 );
        Functions\when( 'wp_remote_retrieve_body' )->justReturn( '{"status":"ok"}' );
        Functions\when( 'current_time' )->justReturn( '2026-03-30 12:00:00' );
        Functions\when( 'update_option' )->justReturn( true );

        $this->connector->send_report( $test_data );

        $this->assertNotNull( $captured_args );
        $this->assertEquals( 'https://api.dane.gov.pl/v1/reports', $captured_args['url'] );
        $this->assertEquals( 'application/json', $captured_args['args']['headers']['Content-Type'] );
        $this->assertEquals( 'Bearer secret-token-123', $captured_args['args']['headers']['Authorization'] );
        $this->assertEquals( json_encode( $test_data ), $captured_args['args']['body'] );
        $this->assertEquals( 45, $captured_args['args']['timeout'] );
    }

    public function test_send_report_logs_wp_error(): void {
        Functions\when( 'get_option' )->alias( function ( $key, $default = false ) {
            return match ( $key ) {
                'dgr_api_endpoint' => 'https://api.dane.gov.pl/v1/reports',
                'dgr_api_key'     => 'key',
                'dgr_api_log'     => '',
                default           => $default,
            };
        } );

        $wp_error = \Mockery::mock( 'WP_Error' );
        $wp_error->shouldReceive( 'get_error_message' )
            ->andReturn( 'Connection timed out' );

        Functions\when( 'wp_remote_post' )->justReturn( $wp_error );
        Functions\when( 'is_wp_error' )->justReturn( true );
        Functions\when( 'current_time' )->justReturn( '2026-03-30 12:00:00' );

        $logged = null;
        Functions\when( 'update_option' )->alias( function ( $key, $val ) use ( &$logged ) {
            $logged = $val;
        } );

        $this->connector->send_report( [ 'data' ] );
        $this->assertStringContainsString( 'Connection timed out', $logged );
    }

    // ─── generate_and_send_report() ──────────────────────────────────

    public function test_generate_and_send_logs_when_no_data(): void {
        Functions\when( 'get_posts' )->justReturn( [] );
        Functions\when( 'current_time' )->justReturn( '2026-03-30 12:00:00' );
        Functions\when( 'get_option' )->justReturn( '' );

        $logged = null;
        Functions\when( 'update_option' )->alias( function ( $key, $val ) use ( &$logged ) {
            $logged = $val;
        } );

        $this->connector->generate_and_send_report();
        $this->assertStringContainsString( 'No data to report', $logged );
    }

    // ─── JSON output structure validation ────────────────────────────

    public function test_report_json_structure_matches_gov_spec(): void {
        $mock_unit     = new \stdClass();
        $mock_unit->ID = 30;

        Functions\when( 'get_posts' )->justReturn( [ $mock_unit ] );

        Functions\when( 'get_post_meta' )->alias( function () {
            $args = func_get_args();
            if ( count( $args ) === 1 && $args[0] === 30 ) {
                return [
                    '_dgr_unit_parent_investment' => [ '5' ],
                    '_dgr_unit_id'               => [ 'B-202' ],
                    '_dgr_unit_area'             => [ '72.3' ],
                    '_dgr_unit_rooms'            => [ '4' ],
                    '_dgr_unit_floor'            => [ '3' ],
                    '_dgr_unit_price_m2'         => [ '11000' ],
                    '_dgr_unit_price_total'      => [ '795300' ],
                    '_dgr_unit_status'           => [ 'reserved' ],
                    '_dgr_unit_dependencies'     => [ '[]' ],
                    '_dgr_price_history'         => [ serialize( [] ) ],
                ];
            }
            if ( $args[0] == 5 && isset( $args[1] ) && $args[1] === '_dgr_investment_id' ) {
                return 'GOV-123';
            }
            return '';
        } );

        Functions\when( 'maybe_unserialize' )->alias( function ( $v ) {
            $result = @unserialize( $v );
            return $result !== false ? $result : $v;
        } );

        $report = $this->connector->generate_daily_report();
        $json   = json_encode( $report );

        $this->assertNotFalse( $json );

        $required_keys = [
            'inwestycja_id', 'lokal_id', 'powierzchnia_m2', 'pokoje',
            'kondygnacja', 'cena_m2', 'cena_calkowita', 'status',
            'przynaleznosci', 'historia_cen',
        ];

        $decoded = json_decode( $json, true );
        foreach ( $required_keys as $key ) {
            $this->assertArrayHasKey( $key, $decoded[0], "Missing required key: $key" );
        }
    }

    // ─── Multiple units report ───────────────────────────────────────

    public function test_generate_report_with_multiple_units(): void {
        $unit1     = new \stdClass();
        $unit1->ID = 10;
        $unit2     = new \stdClass();
        $unit2->ID = 11;

        Functions\when( 'get_posts' )->justReturn( [ $unit1, $unit2 ] );

        Functions\when( 'get_post_meta' )->alias( function () {
            $args = func_get_args();
            if ( count( $args ) === 1 ) {
                return [
                    '_dgr_unit_parent_investment' => [ '5' ],
                    '_dgr_unit_id'               => [ 'U-' . $args[0] ],
                    '_dgr_unit_area'             => [ '50' ],
                    '_dgr_unit_rooms'            => [ '2' ],
                    '_dgr_unit_floor'            => [ '1' ],
                    '_dgr_unit_price_m2'         => [ '10000' ],
                    '_dgr_unit_price_total'      => [ '500000' ],
                    '_dgr_unit_status'           => [ 'available' ],
                    '_dgr_unit_dependencies'     => [ '[]' ],
                    '_dgr_price_history'         => [ serialize( [] ) ],
                ];
            }
            return 'GOV-MULTI';
        } );

        Functions\when( 'maybe_unserialize' )->alias( function ( $v ) {
            $result = @unserialize( $v );
            return $result !== false ? $result : $v;
        } );

        $report = $this->connector->generate_daily_report();
        $this->assertCount( 2, $report );
        $this->assertEquals( 'U-10', $report[0]['lokal_id'] );
        $this->assertEquals( 'U-11', $report[1]['lokal_id'] );
    }
}
