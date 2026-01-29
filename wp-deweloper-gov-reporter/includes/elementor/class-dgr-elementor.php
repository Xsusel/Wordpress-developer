<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

class DGR_Elementor_Manager {

	public function init() {
		// Check if Elementor is installed and active
		if ( ! did_action( 'elementor/loaded' ) ) {
			return;
		}

		add_action( 'elementor/widgets/register', array( $this, 'register_widgets' ) );
	}

	public function register_widgets( $widgets_manager ) {
		require_once plugin_dir_path( __FILE__ ) . 'widgets/widget-price-history.php';
		require_once plugin_dir_path( __FILE__ ) . 'widgets/widget-unit-details.php';

		$widgets_manager->register( new \DGR_Price_History_Widget() );
		$widgets_manager->register( new \DGR_Unit_Details_Widget() );
	}
}
