<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

class DGR_Unit_Details_Widget extends \Elementor\Widget_Base {

	public function get_name() {
		return 'dgr_unit_details';
	}

	public function get_title() {
		return esc_html__( 'Szczegóły Lokalu (DGR)', 'wp-deweloper-gov-reporter' );
	}

	public function get_icon() {
		return 'eicon-info-box';
	}

	public function get_categories() {
		return array( 'general' );
	}

	public function get_keywords() {
		return array( 'details', 'unit', 'lokal', 'deweloper', 'gov' );
	}

	protected function render() {
		echo do_shortcode( '[dgr_unit_details]' );
	}
}
