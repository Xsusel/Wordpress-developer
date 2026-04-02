<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class DGR_Post_Types {

	public function init() {
		add_action( 'init', array( $this, 'register_post_types' ) );
	}

	public function register_post_types() {
		// Inwestycja (Investment)
		$labels_investment = array(
			'name'                  => _x( 'Inwestycje', 'Post Type General Name', 'wp-deweloper-gov-reporter' ),
			'singular_name'         => _x( 'Inwestycja', 'Post Type Singular Name', 'wp-deweloper-gov-reporter' ),
			'menu_name'             => __( 'Inwestycje', 'wp-deweloper-gov-reporter' ),
			'all_items'             => __( 'Wszystkie Inwestycje', 'wp-deweloper-gov-reporter' ),
			'add_new_item'          => __( 'Dodaj nową Inwestycję', 'wp-deweloper-gov-reporter' ),
			'add_new'               => __( 'Dodaj nową', 'wp-deweloper-gov-reporter' ),
			'new_item'              => __( 'Nowa Inwestycja', 'wp-deweloper-gov-reporter' ),
			'edit_item'             => __( 'Edytuj Inwestycję', 'wp-deweloper-gov-reporter' ),
			'update_item'           => __( 'Zaktualizuj Inwestycję', 'wp-deweloper-gov-reporter' ),
			'view_item'             => __( 'Zobacz Inwestycję', 'wp-deweloper-gov-reporter' ),
			'search_items'          => __( 'Szukaj Inwestycji', 'wp-deweloper-gov-reporter' ),
		);
		$args_investment = array(
			'label'                 => __( 'Inwestycja', 'wp-deweloper-gov-reporter' ),
			'description'           => __( 'Inwestycje deweloperskie', 'wp-deweloper-gov-reporter' ),
			'labels'                => $labels_investment,
			'supports'              => array( 'title', 'editor', 'thumbnail' ),
			'hierarchical'          => false,
			'public'                => true,
			'show_ui'               => true,
			'show_in_menu'          => 'wp-deweloper-gov-reporter',
			'menu_position'         => 5,
			'show_in_admin_bar'     => true,
			'show_in_nav_menus'     => true,
			'can_export'            => true,
			'has_archive'           => true,
			'exclude_from_search'   => false,
			'publicly_queryable'    => true,
			'capability_type'       => 'post',
			'show_in_rest'          => true,
			'rewrite'               => array( 'slug' => 'inwestycja' ),
		);
		register_post_type( 'dgr_investment', $args_investment );

		// Lokal (Unit)
		$labels_unit = array(
			'name'                  => _x( 'Lokale', 'Post Type General Name', 'wp-deweloper-gov-reporter' ),
			'singular_name'         => _x( 'Lokal', 'Post Type Singular Name', 'wp-deweloper-gov-reporter' ),
			'menu_name'             => __( 'Lokale', 'wp-deweloper-gov-reporter' ),
			'all_items'             => __( 'Wszystkie Lokale', 'wp-deweloper-gov-reporter' ),
			'add_new_item'          => __( 'Dodaj nowy Lokal', 'wp-deweloper-gov-reporter' ),
			'add_new'               => __( 'Dodaj nowy', 'wp-deweloper-gov-reporter' ),
			'new_item'              => __( 'Nowy Lokal', 'wp-deweloper-gov-reporter' ),
			'edit_item'             => __( 'Edytuj Lokal', 'wp-deweloper-gov-reporter' ),
			'update_item'           => __( 'Zaktualizuj Lokal', 'wp-deweloper-gov-reporter' ),
			'view_item'             => __( 'Zobacz Lokal', 'wp-deweloper-gov-reporter' ),
			'search_items'          => __( 'Szukaj Lokali', 'wp-deweloper-gov-reporter' ),
		);
		$args_unit = array(
			'label'                 => __( 'Lokal', 'wp-deweloper-gov-reporter' ),
			'description'           => __( 'Lokale mieszkalne i usługowe', 'wp-deweloper-gov-reporter' ),
			'labels'                => $labels_unit,
			'supports'              => array( 'title', 'editor' ),
			'hierarchical'          => false,
			'public'                => true,
			'show_ui'               => true,
			'show_in_menu'          => 'wp-deweloper-gov-reporter',
			'menu_position'         => 6,
			'show_in_admin_bar'     => true,
			'show_in_nav_menus'     => true,
			'can_export'            => true,
			'has_archive'           => true,
			'exclude_from_search'   => false,
			'publicly_queryable'    => true,
			'capability_type'       => 'post',
			'show_in_rest'          => true,
			'rewrite'               => array( 'slug' => 'lokal' ),
		);
		register_post_type( 'dgr_unit', $args_unit );
	}
}
