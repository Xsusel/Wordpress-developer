<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

class DGR_Unit_List_Widget extends \Elementor\Widget_Base {

	public function get_name() {
		return 'dgr_unit_list';
	}

	public function get_title() {
		return esc_html__( 'Lista Lokali (DGR)', 'wp-deweloper-gov-reporter' );
	}

	public function get_icon() {
		return 'eicon-post-list';
	}

	public function get_categories() {
		return array( 'general' );
	}

	public function get_keywords() {
		return array( 'list', 'unit', 'lokal', 'deweloper', 'gov' );
	}

	protected function register_controls() {
		$this->start_controls_section(
			'content_section',
			[
				'label' => esc_html__( 'Ustawienia', 'wp-deweloper-gov-reporter' ),
				'tab' => \Elementor\Controls_Manager::TAB_CONTENT,
			]
		);

		// Get investments for dropdown
		$investments = get_posts( array(
			'post_type'      => 'dgr_investment',
			'posts_per_page' => -1,
			'post_status'    => 'publish',
		) );

		$options = array(
			'' => esc_html__( 'Wszystkie', 'wp-deweloper-gov-reporter' ),
		);
		foreach ( $investments as $inv ) {
			$options[ $inv->ID ] = $inv->post_title;
		}

		$this->add_control(
			'investment_id',
			[
				'label' => esc_html__( 'Inwestycja', 'wp-deweloper-gov-reporter' ),
				'type' => \Elementor\Controls_Manager::SELECT,
				'default' => '',
				'options' => $options,
			]
		);

		$this->end_controls_section();
	}

	protected function render() {
		$settings = $this->get_settings_for_display();
		$shortcode = '[dgr_unit_list';
		if ( ! empty( $settings['investment_id'] ) ) {
			$shortcode .= ' investment_id="' . $settings['investment_id'] . '"';
		}
		$shortcode .= ']';
		echo do_shortcode( $shortcode );
	}
}
