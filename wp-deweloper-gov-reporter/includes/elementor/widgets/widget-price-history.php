<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

class DGR_Price_History_Widget extends \Elementor\Widget_Base {

	public function get_name() {
		return 'dgr_price_history';
	}

	public function get_title() {
		return esc_html__( 'Historia Cen (DGR)', 'wp-deweloper-gov-reporter' );
	}

	public function get_icon() {
		return 'eicon-history';
	}

	public function get_categories() {
		return array( 'general' );
	}

	public function get_keywords() {
		return array( 'price', 'history', 'deweloper', 'gov' );
	}

	protected function render() {
		echo do_shortcode( '[dgr_price_history]' );
	}
}
