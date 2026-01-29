<?php

class DGR_Admin {

	private $plugin_name;
	private $version;

	public function __construct( $plugin_name, $version ) {
		$this->plugin_name = $plugin_name;
		$this->version     = $version;
	}

	public function init() {
		add_action( 'admin_menu', array( $this, 'add_plugin_admin_menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_init', array( $this, 'maybe_flush_rewrite_rules' ) );
	}

	public function maybe_flush_rewrite_rules() {
		if ( ! get_option( 'dgr_rewrite_rules_flushed_v1' ) ) {
			flush_rewrite_rules();
			update_option( 'dgr_rewrite_rules_flushed_v1', true );
		}
	}

	public function add_plugin_admin_menu() {
		add_menu_page(
			'Deweloper Gov Reporter',
			'Deweloper Gov',
			'manage_options',
			'wp-deweloper-gov-reporter',
			array( $this, 'display_plugin_setup_page' ),
			'dashicons-building',
			30
		);

		add_submenu_page(
			'wp-deweloper-gov-reporter',
			'Ustawienia',
			'Ustawienia',
			'manage_options',
			'wp-deweloper-gov-reporter',
			array( $this, 'display_plugin_setup_page' )
		);
	}

	public function register_settings() {
		register_setting( 'dgr_options_group', 'dgr_api_endpoint' );
		register_setting( 'dgr_options_group', 'dgr_api_key' );
		register_setting( 'dgr_options_group', 'dgr_developer_nip' );

		add_settings_section(
			'dgr_general_section',
			'Ustawienia API dane.gov.pl',
			null,
			'wp-deweloper-gov-reporter'
		);

		add_settings_field(
			'dgr_api_endpoint',
			'Adres API (Endpoint)',
			array( $this, 'render_field_api_endpoint' ),
			'wp-deweloper-gov-reporter',
			'dgr_general_section'
		);

		add_settings_field(
			'dgr_api_key',
			'Klucz API',
			array( $this, 'render_field_api_key' ),
			'wp-deweloper-gov-reporter',
			'dgr_general_section'
		);

		add_settings_field(
			'dgr_developer_nip',
			'NIP Dewelopera',
			array( $this, 'render_field_developer_nip' ),
			'wp-deweloper-gov-reporter',
			'dgr_general_section'
		);
	}

	public function render_field_api_endpoint() {
		$value = get_option( 'dgr_api_endpoint' );
		echo '<input type="text" name="dgr_api_endpoint" value="' . esc_attr( $value ) . '" class="regular-text code">';
		echo '<p class="description">Np. https://api.dane.gov.pl/...</p>';
	}

	public function render_field_api_key() {
		$value = get_option( 'dgr_api_key' );
		echo '<input type="password" name="dgr_api_key" value="' . esc_attr( $value ) . '" class="regular-text">';
	}

	public function render_field_developer_nip() {
		$value = get_option( 'dgr_developer_nip' );
		echo '<input type="text" name="dgr_developer_nip" value="' . esc_attr( $value ) . '" class="regular-text">';
	}

	public function display_plugin_setup_page() {
		?>
		<div class="wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
			<form action="options.php" method="post">
				<?php
				settings_fields( 'dgr_options_group' );
				do_settings_sections( 'wp-deweloper-gov-reporter' );
				submit_button();
				?>
			</form>
			<hr>
			<h2>Ostatnie Logi</h2>
			<textarea readonly class="widefat" rows="10"><?php echo esc_textarea( get_option( 'dgr_api_log' ) ); ?></textarea>
		</div>
		<?php
	}
}
