<?php

use Brain\Monkey;
use Brain\Monkey\Functions;

require_once dirname( __DIR__ ) . '/wp-deweloper-gov-reporter/includes/class-dgr-metaboxes.php';

class MetaboxesTest extends DGR_TestCase {

    private DGR_Metaboxes $metaboxes;

    protected function setUp(): void {
        parent::setUp();
        $this->metaboxes = new DGR_Metaboxes();
    }

    public function test_init_registers_hooks(): void {
        Functions\expect( 'add_action' )
            ->with( 'add_meta_boxes', \Mockery::type( 'array' ) )
            ->once();

        Functions\expect( 'add_action' )
            ->with( 'save_post', \Mockery::type( 'array' ) )
            ->once();

        $this->metaboxes->init();
        $this->assertTrue( true );
    }

    public function test_save_investment_meta(): void {
        $_POST = [
            'dgr_investment_nonce'           => 'valid',
            'dgr_investment_id'              => 'INW-001',
            'dgr_investment_nip'             => '1234567890',
            'dgr_investment_voivodeship'     => 'mazowieckie',
            'dgr_investment_county'          => 'warszawski',
            'dgr_investment_commune'         => 'Warszawa',
            'dgr_investment_city'            => 'Warszawa',
            'dgr_investment_street'          => 'Testowa',
            'dgr_investment_building_number' => '1',
            'dgr_investment_postal_code'     => '00-001',
        ];

        Functions\when( 'wp_verify_nonce' )->justReturn( true );
        Functions\when( 'current_user_can' )->justReturn( true );
        Functions\when( 'sanitize_text_field' )->returnArg();

        $saved = [];
        Functions\when( 'update_post_meta' )->alias( function ( $id, $key, $val ) use ( &$saved ) {
            $saved[ $key ] = $val;
        } );

        $this->metaboxes->save_meta_boxes( 100 );

        $this->assertEquals( 'INW-001', $saved['_dgr_investment_id'] );
        $this->assertEquals( '1234567890', $saved['_dgr_investment_nip'] );
        $this->assertEquals( 'mazowieckie', $saved['_dgr_investment_voivodeship'] );
        $this->assertEquals( 'Warszawa', $saved['_dgr_investment_city'] );
        $this->assertEquals( '00-001', $saved['_dgr_investment_postal_code'] );
    }

    public function test_save_investment_blocked_without_permission(): void {
        $_POST = [
            'dgr_investment_nonce'       => 'valid',
            'dgr_investment_voivodeship' => 'mazowieckie',
        ];

        Functions\when( 'wp_verify_nonce' )->justReturn( true );
        Functions\when( 'current_user_can' )->justReturn( false );

        Functions\expect( 'update_post_meta' )->never();

        $this->metaboxes->save_meta_boxes( 100 );
        $this->assertTrue( true );
    }

    public function test_save_unit_meta_all_fields(): void {
        $_POST = [
            'dgr_unit_nonce'             => 'valid',
            'dgr_unit_parent_investment' => '5',
            'dgr_unit_id'               => 'A-101',
            'dgr_unit_price_total'      => '555000',
            'dgr_unit_price_m2'         => '10000',
            'dgr_unit_area'             => '55.5',
            'dgr_unit_rooms'            => '3',
            'dgr_unit_floor'            => '2',
            'dgr_unit_status'           => 'available',
            'dgr_unit_dependencies'     => '[{"typ":"garaż","cena":50000}]',
        ];

        Functions\when( 'wp_verify_nonce' )->justReturn( true );
        Functions\when( 'current_user_can' )->justReturn( true );
        Functions\when( 'sanitize_text_field' )->returnArg();
        Functions\when( 'sanitize_textarea_field' )->returnArg();

        $saved = [];
        Functions\when( 'update_post_meta' )->alias( function ( $id, $key, $val ) use ( &$saved ) {
            $saved[ $key ] = $val;
        } );

        $this->metaboxes->save_meta_boxes( 200 );

        $this->assertCount( 9, $saved );
        $this->assertEquals( '5', $saved['_dgr_unit_parent_investment'] );
        $this->assertEquals( 'A-101', $saved['_dgr_unit_id'] );
        $this->assertEquals( '555000', $saved['_dgr_unit_price_total'] );
        $this->assertEquals( 'available', $saved['_dgr_unit_status'] );
        $this->assertStringContainsString( 'garaż', $saved['_dgr_unit_dependencies'] );
    }

    public function test_save_rejects_invalid_nonce(): void {
        $_POST = [ 'dgr_investment_nonce' => 'invalid' ];

        Functions\when( 'wp_verify_nonce' )->justReturn( false );
        Functions\expect( 'update_post_meta' )->never();

        $this->metaboxes->save_meta_boxes( 100 );
        $this->assertTrue( true );
    }

    public function test_save_unit_with_empty_dependencies(): void {
        $_POST = [
            'dgr_unit_nonce'         => 'valid',
            'dgr_unit_dependencies'  => '[]',
        ];

        Functions\when( 'wp_verify_nonce' )->justReturn( true );
        Functions\when( 'current_user_can' )->justReturn( true );
        Functions\when( 'sanitize_textarea_field' )->returnArg();

        $saved = [];
        Functions\when( 'update_post_meta' )->alias( function ( $id, $key, $val ) use ( &$saved ) {
            $saved[ $key ] = $val;
        } );

        $this->metaboxes->save_meta_boxes( 200 );

        $this->assertEquals( '[]', $saved['_dgr_unit_dependencies'] );
    }
}
