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

	}

	protected function render() {
		$settings = $this->get_settings_for_display();

		// Construct shortcode with attributes
		$shortcode = '[dgr_unit_details';

		$shortcode .= ' show_investment="' . $settings['show_investment'] . '"';
		$shortcode .= ' show_unit_id="' . $settings['show_unit_id'] . '"';
		$shortcode .= ' show_area="' . $settings['show_area'] . '"';
		$shortcode .= ' show_rooms="' . $settings['show_rooms'] . '"';
		$shortcode .= ' show_floor="' . $settings['show_floor'] . '"';
		$shortcode .= ' show_status="' . $settings['show_status'] . '"';
		$shortcode .= ' show_price_total="' . $settings['show_price_total'] . '"';
		$shortcode .= ' show_price_m2="' . $settings['show_price_m2'] . '"';
		$shortcode .= ' show_omnibus="' . $settings['show_omnibus'] . '"';

		$shortcode .= ']';

		echo do_shortcode( $shortcode );
	}
}
