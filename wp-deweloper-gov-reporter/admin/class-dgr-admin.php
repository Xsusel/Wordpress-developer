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
		add_action( 'admin_init', array( $this, 'process_export_csv' ) );
		add_action( 'wp_dashboard_setup', array( $this, 'add_dashboard_widgets' ) );
	}

	public function add_dashboard_widgets() {
		wp_add_dashboard_widget(
			'dgr_dashboard_overview',
			__( 'Statystyki Dewelopera (DGR)', 'wp-deweloper-gov-reporter' ),
			array( $this, 'render_dashboard_widget' )
		);
	}

	public function render_dashboard_widget() {
		$posts = get_posts( array(
			'post_type'      => 'dgr_unit',
			'posts_per_page' => -1,
			'post_status'    => 'any',
		) );

		$total = count( $posts );
		$available = 0;
		$sold = 0;
		$reserved = 0;
		$total_value_available = 0;

		foreach ( $posts as $post ) {
			$status = get_post_meta( $post->ID, '_dgr_unit_status', true );
			$price  = (float) get_post_meta( $post->ID, '_dgr_unit_price_total', true );

			if ( 'available' === $status || 'offer' === $status ) {
				$available++;
				$total_value_available += $price;
			} elseif ( 'sold' === $status || 'transferred' === $status ) {
				$sold++;
			} elseif ( 'reserved' === $status || 'reservation_agreement' === $status || 'developer_agreement' === $status ) {
				$reserved++;
			}
		}

		echo '<div class="main">';
		echo '<p><strong>' . __( 'Wszystkie lokale:', 'wp-deweloper-gov-reporter' ) . '</strong> ' . $total . '</p>';
		echo '<p><span style="color: green;">●</span> <strong>' . __( 'Dostępne:', 'wp-deweloper-gov-reporter' ) . '</strong> ' . $available . '</p>';
		echo '<p><span style="color: orange;">●</span> <strong>' . __( 'Zarezerwowane:', 'wp-deweloper-gov-reporter' ) . '</strong> ' . $reserved . '</p>';
		echo '<p><span style="color: red;">●</span> <strong>' . __( 'Sprzedane:', 'wp-deweloper-gov-reporter' ) . '</strong> ' . $sold . '</p>';
		echo '<hr>';
		echo '<p><strong>' . __( 'Wartość dostępnych lokali:', 'wp-deweloper-gov-reporter' ) . '</strong><br> ' . number_format( $total_value_available, 2, ',', ' ' ) . ' PLN</p>';
		echo '</div>';
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
			<h2>Eksport Danych</h2>
			<form method="post" action="">
				<?php wp_nonce_field( 'dgr_export_csv_action', 'dgr_export_csv_nonce' ); ?>
				<input type="hidden" name="dgr_export_csv" value="1">
				<p>
					<?php submit_button( 'Eksportuj wszystkie lokale do CSV', 'secondary', 'submit', false ); ?>
				</p>
			</form>
			<hr>
			<h2>Ostatnie Logi</h2>
			<textarea readonly class="widefat" rows="10"><?php echo esc_textarea( get_option( 'dgr_api_log' ) ); ?></textarea>
		</div>
		<?php
	}

	public function process_export_csv() {
		if ( ! isset( $_POST['dgr_export_csv'] ) ) {
			return;
		}

		if ( ! isset( $_POST['dgr_export_csv_nonce'] ) || ! wp_verify_nonce( $_POST['dgr_export_csv_nonce'], 'dgr_export_csv_action' ) ) {
			return;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// Clean buffer
		if ( ob_get_level() ) {
			ob_end_clean();
		}

		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=lokale-export-' . date( 'Y-m-d' ) . '.csv' );

		$output = fopen( 'php://output', 'w' );

		// Add BOM for Excel UTF-8 compatibility
		fputs( $output, "\xEF\xBB\xBF" );

		// Headers
		fputcsv( $output, array( 'ID', 'Inwestycja', 'Numer Lokalu', 'Powierzchnia', 'Pokoje', 'Piętro', 'Status', 'Cena Całkowita', 'Cena m2' ) );

		// Data
		$units = get_posts( array(
			'post_type'      => 'dgr_unit',
			'posts_per_page' => -1,
			'post_status'    => 'any',
		) );

		foreach ( $units as $unit ) {
			$meta = get_post_meta( $unit->ID );
			$parent_id = isset( $meta['_dgr_unit_parent_investment'][0] ) ? $meta['_dgr_unit_parent_investment'][0] : 0;
			$investment_name = $parent_id ? get_the_title( $parent_id ) : '';

			fputcsv( $output, array(
				$unit->ID,
				$investment_name,
				isset( $meta['_dgr_unit_id'][0] ) ? $meta['_dgr_unit_id'][0] : '',
				isset( $meta['_dgr_unit_area'][0] ) ? $meta['_dgr_unit_area'][0] : '',
				isset( $meta['_dgr_unit_rooms'][0] ) ? $meta['_dgr_unit_rooms'][0] : '',
				isset( $meta['_dgr_unit_floor'][0] ) ? $meta['_dgr_unit_floor'][0] : '',
				isset( $meta['_dgr_unit_status'][0] ) ? $meta['_dgr_unit_status'][0] : '',
				isset( $meta['_dgr_unit_price_total'][0] ) ? $meta['_dgr_unit_price_total'][0] : '',
				isset( $meta['_dgr_unit_price_m2'][0] ) ? $meta['_dgr_unit_price_m2'][0] : '',
			) );
		}

		fclose( $output );
		exit;
	}
}
