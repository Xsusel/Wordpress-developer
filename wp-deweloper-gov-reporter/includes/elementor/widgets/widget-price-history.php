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

	protected function register_controls() {
		$this->start_controls_section(
			'content_section',
			[
				'label' => esc_html__( 'Ustawienia', 'wp-deweloper-gov-reporter' ),
				'tab' => \Elementor\Controls_Manager::TAB_CONTENT,
			]
		);

		$this->add_control(
			'view_type',
			[
				'label' => esc_html__( 'Widok', 'wp-deweloper-gov-reporter' ),
				'type' => \Elementor\Controls_Manager::SELECT,
				'default' => 'both',
				'options' => [
					'table' => esc_html__( 'Tylko Tabela', 'wp-deweloper-gov-reporter' ),
					'chart' => esc_html__( 'Tylko Wykres', 'wp-deweloper-gov-reporter' ),
					'both'  => esc_html__( 'Tabela i Wykres', 'wp-deweloper-gov-reporter' ),
				],
			]
		);

		$this->end_controls_section();
	}

	protected function render() {
		$settings = $this->get_settings_for_display();
		echo do_shortcode( '[dgr_price_history view="' . esc_attr( $settings['view_type'] ) . '"]' );
	}
}
