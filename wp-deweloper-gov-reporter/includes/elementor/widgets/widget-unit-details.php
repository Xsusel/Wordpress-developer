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

	protected function register_controls() {

		$this->start_controls_section(
			'content_section',
			[
				'label' => esc_html__( 'Ustawienia', 'wp-deweloper-gov-reporter' ),
				'tab' => \Elementor\Controls_Manager::TAB_CONTENT,
			]
		);

		$this->add_control(
			'show_investment',
			[
				'label' => esc_html__( 'Pokaż Inwestycję', 'wp-deweloper-gov-reporter' ),
				'type' => \Elementor\Controls_Manager::SWITCHER,
				'label_on' => esc_html__( 'Tak', 'wp-deweloper-gov-reporter' ),
				'label_off' => esc_html__( 'Nie', 'wp-deweloper-gov-reporter' ),
				'return_value' => 'yes',
				'default' => 'yes',
			]
		);

		$this->add_control(
			'show_unit_id',
			[
				'label' => esc_html__( 'Pokaż Numer Lokalu', 'wp-deweloper-gov-reporter' ),
				'type' => \Elementor\Controls_Manager::SWITCHER,
				'label_on' => esc_html__( 'Tak', 'wp-deweloper-gov-reporter' ),
				'label_off' => esc_html__( 'Nie', 'wp-deweloper-gov-reporter' ),
				'return_value' => 'yes',
				'default' => 'yes',
			]
		);

		$this->add_control(
			'show_area',
			[
				'label' => esc_html__( 'Pokaż Powierzchnię', 'wp-deweloper-gov-reporter' ),
				'type' => \Elementor\Controls_Manager::SWITCHER,
				'label_on' => esc_html__( 'Tak', 'wp-deweloper-gov-reporter' ),
				'label_off' => esc_html__( 'Nie', 'wp-deweloper-gov-reporter' ),
				'return_value' => 'yes',
				'default' => 'yes',
			]
		);

		$this->add_control(
			'show_rooms',
			[
				'label' => esc_html__( 'Pokaż Pokoje', 'wp-deweloper-gov-reporter' ),
				'type' => \Elementor\Controls_Manager::SWITCHER,
				'label_on' => esc_html__( 'Tak', 'wp-deweloper-gov-reporter' ),
				'label_off' => esc_html__( 'Nie', 'wp-deweloper-gov-reporter' ),
				'return_value' => 'yes',
				'default' => 'yes',
			]
		);

		$this->add_control(
			'show_floor',
			[
				'label' => esc_html__( 'Pokaż Piętro', 'wp-deweloper-gov-reporter' ),
				'type' => \Elementor\Controls_Manager::SWITCHER,
				'label_on' => esc_html__( 'Tak', 'wp-deweloper-gov-reporter' ),
				'label_off' => esc_html__( 'Nie', 'wp-deweloper-gov-reporter' ),
				'return_value' => 'yes',
				'default' => 'yes',
			]
		);

		$this->add_control(
			'show_status',
			[
				'label' => esc_html__( 'Pokaż Status', 'wp-deweloper-gov-reporter' ),
				'type' => \Elementor\Controls_Manager::SWITCHER,
				'label_on' => esc_html__( 'Tak', 'wp-deweloper-gov-reporter' ),
				'label_off' => esc_html__( 'Nie', 'wp-deweloper-gov-reporter' ),
				'return_value' => 'yes',
				'default' => 'yes',
			]
		);

		$this->add_control(
			'show_price_total',
			[
				'label' => esc_html__( 'Pokaż Cenę Całkowitą', 'wp-deweloper-gov-reporter' ),
				'type' => \Elementor\Controls_Manager::SWITCHER,
				'label_on' => esc_html__( 'Tak', 'wp-deweloper-gov-reporter' ),
				'label_off' => esc_html__( 'Nie', 'wp-deweloper-gov-reporter' ),
				'return_value' => 'yes',
				'default' => 'yes',
			]
		);

		$this->add_control(
			'show_price_m2',
			[
				'label' => esc_html__( 'Pokaż Cenę za m²', 'wp-deweloper-gov-reporter' ),
				'type' => \Elementor\Controls_Manager::SWITCHER,
				'label_on' => esc_html__( 'Tak', 'wp-deweloper-gov-reporter' ),
				'label_off' => esc_html__( 'Nie', 'wp-deweloper-gov-reporter' ),
				'return_value' => 'yes',
				'default' => 'yes',
			]
		);

		$this->add_control(
			'show_omnibus',
			[
				'label' => esc_html__( 'Pokaż Najniższą Cenę (30 dni)', 'wp-deweloper-gov-reporter' ),
				'type' => \Elementor\Controls_Manager::SWITCHER,
				'label_on' => esc_html__( 'Tak', 'wp-deweloper-gov-reporter' ),
				'label_off' => esc_html__( 'Nie', 'wp-deweloper-gov-reporter' ),
				'return_value' => 'yes',
				'default' => 'no',
			]
		);

		$this->end_controls_section();

		$this->start_controls_section(
			'style_section',
			[
				'label' => esc_html__( 'Styl', 'wp-deweloper-gov-reporter' ),
				'tab' => \Elementor\Controls_Manager::TAB_STYLE,
			]
		);

		$this->add_control(
			'list_color',
			[
				'label' => esc_html__( 'Kolor Tekstu', 'wp-deweloper-gov-reporter' ),
				'type' => \Elementor\Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}} .dgr-unit-details ul.dgr-list li' => 'color: {{VALUE}}',
				],
			]
		);

		$this->add_group_control(
			\Elementor\Group_Control_Typography::get_type(),
			[
				'name' => 'list_typography',
				'selector' => '{{WRAPPER}} .dgr-unit-details ul.dgr-list li',
			]
		);

		$this->add_responsive_control(
			'item_spacing',
			[
				'label' => esc_html__( 'Odstęp między elementami', 'wp-deweloper-gov-reporter' ),
				'type' => \Elementor\Controls_Manager::SLIDER,
				'size_units' => [ 'px', 'em' ],
				'range' => [
					'px' => [
						'min' => 0,
						'max' => 50,
					],
				],
				'selectors' => [
					'{{WRAPPER}} .dgr-unit-details ul.dgr-list li' => 'margin-bottom: {{SIZE}}{{UNIT}};',
				],
			]
		);

		$this->end_controls_section();

	}

	protected function render() {
		$settings = $this->get_settings_for_display();

		$fields = array( 'show_investment', 'show_unit_id', 'show_area', 'show_rooms', 'show_floor', 'show_status', 'show_price_total', 'show_price_m2', 'show_omnibus' );
		$shortcode = '[dgr_unit_details';
		foreach ( $fields as $field ) {
			$shortcode .= ' ' . $field . '="' . esc_attr( $settings[ $field ] ) . '"';
		}
		$shortcode .= ']';

		echo do_shortcode( $shortcode );
	}
}
