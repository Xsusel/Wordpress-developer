<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

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
		add_action( 'admin_init', array( $this, 'process_generate_now' ) );
		add_action( 'wp_dashboard_setup', array( $this, 'add_dashboard_widgets' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
		add_action( 'wp_ajax_dgr_lookup_nip', array( $this, 'ajax_lookup_nip' ) );
	}

	public function enqueue_admin_assets( $hook ) {
		wp_enqueue_style( 'dgr-admin-css', plugins_url( '../assets/css/dgr-admin.css', __FILE__ ), array(), '1.3.0' );

		$screen = get_current_screen();
		$load_js = false;

		if ( $screen ) {
			// Investment or Unit edit screen
			if ( in_array( $screen->post_type, array( 'dgr_investment', 'dgr_unit' ), true ) && in_array( $screen->base, array( 'post', 'post-new' ), true ) ) {
				$load_js = true;
			}
			// Plugin settings page
			if ( 'toplevel_page_wp-deweloper-gov-reporter' === $screen->id ) {
				$load_js = true;
			}
		}

		if ( $load_js ) {
			wp_enqueue_script( 'dgr-admin-js', plugins_url( '../assets/js/dgr-admin.js', __FILE__ ), array( 'jquery' ), '1.3.0', true );
			wp_localize_script( 'dgr-admin-js', 'dgr_admin', array(
				'ajaxurl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'dgr_nip_lookup' ),
				'i18n'    => array(
					'lookup'      => __( 'Pobierz dane z GUS', 'wp-deweloper-gov-reporter' ),
					'searching'   => __( 'Szukam...', 'wp-deweloper-gov-reporter' ),
					'not_found'   => __( 'Nie znaleziono firmy o podanym NIP.', 'wp-deweloper-gov-reporter' ),
					'invalid_nip' => __( 'NIP musi mieć 10 cyfr.', 'wp-deweloper-gov-reporter' ),
					'error'       => __( 'Błąd połączenia z serwerem.', 'wp-deweloper-gov-reporter' ),
					'dep_parking' => __( 'Miejsce postojowe', 'wp-deweloper-gov-reporter' ),
					'dep_storage' => __( 'Komórka lokatorska', 'wp-deweloper-gov-reporter' ),
					'dep_garage'  => __( 'Garaż', 'wp-deweloper-gov-reporter' ),
					'dep_bike'    => __( 'Rowerownia', 'wp-deweloper-gov-reporter' ),
					'dep_other'   => __( 'Inne', 'wp-deweloper-gov-reporter' ),
					'dep_remove'  => __( 'Usuń', 'wp-deweloper-gov-reporter' ),
				),
			) );
		}
	}

	public function add_dashboard_widgets() {
		wp_add_dashboard_widget(
			'dgr_dashboard_overview',
			__( 'Statystyki Dewelopera (DGR)', 'wp-deweloper-gov-reporter' ),
			array( $this, 'render_dashboard_widget' )
		);
	}

	public function render_dashboard_widget() {
		global $wpdb;

		// Use a direct query to avoid loading all posts into memory
		$counts = $wpdb->get_results(
			"SELECT pm.meta_value AS status, COUNT(*) AS cnt
			 FROM {$wpdb->postmeta} pm
			 INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
			 WHERE pm.meta_key = '_dgr_unit_status'
			   AND p.post_type = 'dgr_unit'
			   AND p.post_status = 'publish'
			 GROUP BY pm.meta_value"
		);

		$total = 0;
		$available = 0;
		$sold = 0;
		$reserved = 0;

		foreach ( $counts as $row ) {
			$total += (int) $row->cnt;
			if ( in_array( $row->status, array( 'available', 'offer' ), true ) ) {
				$available += (int) $row->cnt;
			} elseif ( in_array( $row->status, array( 'sold', 'transferred' ), true ) ) {
				$sold += (int) $row->cnt;
			} elseif ( in_array( $row->status, array( 'reserved', 'reservation_agreement', 'developer_agreement' ), true ) ) {
				$reserved += (int) $row->cnt;
			}
		}

		// Get total value of available units
		$total_value = $wpdb->get_var(
			"SELECT SUM(CAST(pm_price.meta_value AS DECIMAL(12,2)))
			 FROM {$wpdb->postmeta} pm_price
			 INNER JOIN {$wpdb->posts} p ON p.ID = pm_price.post_id
			 INNER JOIN {$wpdb->postmeta} pm_status ON pm_status.post_id = p.ID AND pm_status.meta_key = '_dgr_unit_status'
			 WHERE pm_price.meta_key = '_dgr_unit_price_total'
			   AND p.post_type = 'dgr_unit'
			   AND p.post_status = 'publish'
			   AND pm_status.meta_value IN ('available', 'offer')"
		);

		// Report status
		$file_status = DGR_API_Connector::get_file_status();

		echo '<div class="main">';
		echo '<p><strong>' . esc_html__( 'Wszystkie lokale:', 'wp-deweloper-gov-reporter' ) . '</strong> ' . esc_html( $total ) . '</p>';
		echo '<p><span style="color: green;">&#9679;</span> <strong>' . esc_html__( 'Dostępne:', 'wp-deweloper-gov-reporter' ) . '</strong> ' . esc_html( $available ) . '</p>';
		echo '<p><span style="color: orange;">&#9679;</span> <strong>' . esc_html__( 'Zarezerwowane:', 'wp-deweloper-gov-reporter' ) . '</strong> ' . esc_html( $reserved ) . '</p>';
		echo '<p><span style="color: red;">&#9679;</span> <strong>' . esc_html__( 'Sprzedane:', 'wp-deweloper-gov-reporter' ) . '</strong> ' . esc_html( $sold ) . '</p>';
		echo '<hr>';
		echo '<p><strong>' . esc_html__( 'Wartość dostępnych lokali:', 'wp-deweloper-gov-reporter' ) . '</strong><br> ' . esc_html( number_format( (float) $total_value, 2, ',', ' ' ) ) . ' PLN</p>';

		if ( ! empty( $file_status['last_success'] ) ) {
			echo '<hr><p><strong>' . esc_html__( 'Ostatni raport XML:', 'wp-deweloper-gov-reporter' ) . '</strong> ' . esc_html( $file_status['last_success'] ) . '</p>';
		}
		echo '</div>';
	}

	public function maybe_flush_rewrite_rules() {
		if ( ! get_option( 'dgr_rewrite_rules_flushed_v2' ) ) {
			flush_rewrite_rules();
			update_option( 'dgr_rewrite_rules_flushed_v2', true );
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
			__( 'Ustawienia', 'wp-deweloper-gov-reporter' ),
			__( 'Ustawienia', 'wp-deweloper-gov-reporter' ),
			'manage_options',
			'wp-deweloper-gov-reporter',
			array( $this, 'display_plugin_setup_page' )
		);
	}

	public function register_settings() {
		register_setting( 'dgr_options_group', 'dgr_developer_nip', array(
			'sanitize_callback' => 'sanitize_text_field',
		) );
		register_setting( 'dgr_options_group', 'dgr_developer_name', array(
			'sanitize_callback' => 'sanitize_text_field',
		) );
		register_setting( 'dgr_options_group', 'dgr_admin_email_notifications', array(
			'sanitize_callback' => 'sanitize_text_field',
		) );

		add_settings_section(
			'dgr_general_section',
			__( 'Dane Dewelopera', 'wp-deweloper-gov-reporter' ),
			null,
			'wp-deweloper-gov-reporter'
		);

		add_settings_field(
			'dgr_developer_name',
			__( 'Nazwa Firmy', 'wp-deweloper-gov-reporter' ),
			array( $this, 'render_field_developer_name' ),
			'wp-deweloper-gov-reporter',
			'dgr_general_section'
		);

		add_settings_field(
			'dgr_developer_nip',
			__( 'NIP Dewelopera', 'wp-deweloper-gov-reporter' ),
			array( $this, 'render_field_developer_nip' ),
			'wp-deweloper-gov-reporter',
			'dgr_general_section'
		);

		add_settings_field(
			'dgr_admin_email_notifications',
			__( 'Powiadomienia email', 'wp-deweloper-gov-reporter' ),
			array( $this, 'render_field_email_notifications' ),
			'wp-deweloper-gov-reporter',
			'dgr_general_section'
		);
	}

	public function render_field_developer_name() {
		$value = get_option( 'dgr_developer_name' );
		echo '<input type="text" name="dgr_developer_name" value="' . esc_attr( $value ) . '" class="regular-text">';
		echo '<p class="description">' . esc_html__( 'Nazwa firmy deweloperskiej (do raportu XML).', 'wp-deweloper-gov-reporter' ) . '</p>';
	}

	public function render_field_developer_nip() {
		$value = get_option( 'dgr_developer_nip' );
		echo '<input type="text" name="dgr_developer_nip" value="' . esc_attr( $value ) . '" class="regular-text" pattern="[0-9]{10}" title="NIP: 10 cyfr">';
		echo '<p class="description">' . esc_html__( 'NIP firmy (10 cyfr, bez myślników).', 'wp-deweloper-gov-reporter' ) . '</p>';
	}

	public function render_field_email_notifications() {
		$value = get_option( 'dgr_admin_email_notifications', 'yes' );
		echo '<select name="dgr_admin_email_notifications">';
		echo '<option value="yes" ' . selected( $value, 'yes', false ) . '>' . esc_html__( 'Włączone', 'wp-deweloper-gov-reporter' ) . '</option>';
		echo '<option value="no" ' . selected( $value, 'no', false ) . '>' . esc_html__( 'Wyłączone', 'wp-deweloper-gov-reporter' ) . '</option>';
		echo '</select>';
		echo '<p class="description">' . esc_html__( 'Powiadomienia email przy błędach generowania raportu.', 'wp-deweloper-gov-reporter' ) . '</p>';
	}

	public function display_plugin_setup_page() {
		$file_status = DGR_API_Connector::get_file_status();
		$file_urls = DGR_API_Connector::get_file_urls();
		?>
		<div class="wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

			<!-- Status Panel -->
			<div class="dgr-status-panel">
				<h2><?php esc_html_e( 'Status Raportu XML dla dane.gov.pl', 'wp-deweloper-gov-reporter' ); ?></h2>

				<div class="dgr-status-grid">
					<div class="dgr-status-card <?php echo $file_status['xml_exists'] ? 'dgr-status-ok' : 'dgr-status-error'; ?>">
						<strong><?php esc_html_e( 'Plik XML:', 'wp-deweloper-gov-reporter' ); ?></strong>
						<?php if ( $file_status['xml_exists'] ) : ?>
							<span class="dgr-badge-ok"><?php esc_html_e( 'Wygenerowany', 'wp-deweloper-gov-reporter' ); ?></span>
							<br><small><?php echo esc_html( sprintf( __( 'Rozmiar: %s KB | Ostatnia zmiana: %s', 'wp-deweloper-gov-reporter' ), number_format( $file_status['xml_size'] / 1024, 1 ), $file_status['xml_modified'] ) ); ?></small>
						<?php else : ?>
							<span class="dgr-badge-error"><?php esc_html_e( 'Brak pliku', 'wp-deweloper-gov-reporter' ); ?></span>
							<br><small><?php esc_html_e( 'Kliknij "Generuj raport teraz" poniżej.', 'wp-deweloper-gov-reporter' ); ?></small>
						<?php endif; ?>
					</div>

					<div class="dgr-status-card <?php echo $file_status['md5_exists'] ? 'dgr-status-ok' : 'dgr-status-error'; ?>">
						<strong><?php esc_html_e( 'Plik MD5:', 'wp-deweloper-gov-reporter' ); ?></strong>
						<?php echo $file_status['md5_exists'] ? '<span class="dgr-badge-ok">OK</span>' : '<span class="dgr-badge-error">' . esc_html__( 'Brak', 'wp-deweloper-gov-reporter' ) . '</span>'; ?>
					</div>
				</div>

				<?php if ( ! empty( $file_urls['xml'] ) ) : ?>
					<div class="dgr-urls-box">
						<h3><?php esc_html_e( 'Adresy URL do rejestracji w dane.gov.pl', 'wp-deweloper-gov-reporter' ); ?></h3>
						<p class="description"><?php esc_html_e( 'Wyślij te adresy mailem na kontakt@dane.gov.pl wraz z danymi firmy (nazwa, NIP, KRS, osoba kontaktowa).', 'wp-deweloper-gov-reporter' ); ?></p>
						<table class="widefat" style="max-width: 700px;">
							<tr>
								<th><?php esc_html_e( 'Plik XML:', 'wp-deweloper-gov-reporter' ); ?></th>
								<td><code><?php echo esc_html( $file_urls['xml'] ); ?></code></td>
							</tr>
							<tr>
								<th><?php esc_html_e( 'Plik MD5:', 'wp-deweloper-gov-reporter' ); ?></th>
								<td><code><?php echo esc_html( $file_urls['md5'] ); ?></code></td>
							</tr>
						</table>
					</div>
				<?php endif; ?>

				<!-- Generate Now Button -->
				<form method="post" action="" style="margin-top: 15px;">
					<?php wp_nonce_field( 'dgr_generate_now_action', 'dgr_generate_now_nonce' ); ?>
					<input type="hidden" name="dgr_generate_now" value="1">
					<?php submit_button( __( 'Generuj raport teraz', 'wp-deweloper-gov-reporter' ), 'primary', 'submit', false ); ?>
					<p class="description"><?php esc_html_e( 'Ręcznie wygeneruj plik XML. CRON generuje automatycznie codziennie o 01:00.', 'wp-deweloper-gov-reporter' ); ?></p>
				</form>

				<?php if ( isset( $_GET['dgr_generated'] ) ) : ?>
					<div class="notice notice-success inline" style="margin-top: 10px;">
						<p><?php esc_html_e( 'Raport XML został wygenerowany pomyślnie!', 'wp-deweloper-gov-reporter' ); ?></p>
					</div>
				<?php endif; ?>
				<?php if ( isset( $_GET['dgr_generate_error'] ) ) : ?>
					<div class="notice notice-error inline" style="margin-top: 10px;">
						<p><?php esc_html_e( 'Wystąpił błąd podczas generowania raportu. Sprawdź logi poniżej.', 'wp-deweloper-gov-reporter' ); ?></p>
					</div>
				<?php endif; ?>
			</div>

			<hr>

			<!-- How it works -->
			<div class="dgr-info-box">
				<h2><?php esc_html_e( 'Jak działa raportowanie do dane.gov.pl?', 'wp-deweloper-gov-reporter' ); ?></h2>
				<ol>
					<li><?php esc_html_e( 'Wtyczka generuje plik XML z cenami wszystkich lokali (codziennie o 01:00 lub ręcznie).', 'wp-deweloper-gov-reporter' ); ?></li>
					<li><?php esc_html_e( 'Plik XML i jego suma kontrolna MD5 są dostępne pod stałymi adresami URL na Twoim serwerze.', 'wp-deweloper-gov-reporter' ); ?></li>
					<li><?php esc_html_e( 'Rejestrujesz te adresy URL w dane.gov.pl (mail na kontakt@dane.gov.pl).', 'wp-deweloper-gov-reporter' ); ?></li>
					<li><?php esc_html_e( 'System dane.gov.pl automatycznie pobiera dane z Twojego serwera raz dziennie.', 'wp-deweloper-gov-reporter' ); ?></li>
				</ol>
			</div>

			<hr>

			<!-- Settings Form -->
			<h2><?php esc_html_e( 'Ustawienia', 'wp-deweloper-gov-reporter' ); ?></h2>
			<form action="options.php" method="post">
				<?php
				settings_fields( 'dgr_options_group' );
				do_settings_sections( 'wp-deweloper-gov-reporter' );
				submit_button();
				?>
			</form>

			<hr>

			<!-- CSV Export -->
			<h2><?php esc_html_e( 'Eksport Danych', 'wp-deweloper-gov-reporter' ); ?></h2>
			<form method="post" action="">
				<?php wp_nonce_field( 'dgr_export_csv_action', 'dgr_export_csv_nonce' ); ?>
				<input type="hidden" name="dgr_export_csv" value="1">
				<p>
					<?php submit_button( __( 'Eksportuj wszystkie lokale do CSV', 'wp-deweloper-gov-reporter' ), 'secondary', 'submit', false ); ?>
				</p>
			</form>

			<hr>

			<!-- Logs -->
			<h2><?php esc_html_e( 'Ostatnie Logi', 'wp-deweloper-gov-reporter' ); ?></h2>
			<textarea readonly class="widefat" rows="10"><?php echo esc_textarea( get_option( 'dgr_api_log', '' ) ); ?></textarea>
		</div>
		<?php
	}

	/**
	 * Handle manual "Generate Now" button.
	 */
	public function process_generate_now() {
		if ( ! isset( $_POST['dgr_generate_now'] ) ) {
			return;
		}

		if ( ! isset( $_POST['dgr_generate_now_nonce'] ) || ! wp_verify_nonce( $_POST['dgr_generate_now_nonce'], 'dgr_generate_now_action' ) ) {
			return;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$connector = new DGR_API_Connector();
		$connector->generate_and_send_report();

		$file_status = DGR_API_Connector::get_file_status();
		$redirect_arg = $file_status['xml_exists'] ? 'dgr_generated' : 'dgr_generate_error';

		wp_safe_redirect( add_query_arg( $redirect_arg, '1', admin_url( 'admin.php?page=wp-deweloper-gov-reporter' ) ) );
		exit;
	}

	/**
	 * Handle CSV export.
	 */
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

		if ( ob_get_level() ) {
			ob_end_clean();
		}

		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=lokale-export-' . wp_date( 'Y-m-d' ) . '.csv' );

		$output = fopen( 'php://output', 'w' );

		// BOM for Excel UTF-8 compatibility
		fputs( $output, "\xEF\xBB\xBF" );

		fputcsv( $output, array( 'ID', 'Inwestycja', 'Numer Lokalu', 'Powierzchnia', 'Pokoje', 'Piętro', 'Status', 'Cena Całkowita', 'Cena m2' ) );

		// Paginated export to avoid memory exhaustion
		$page = 1;
		$per_page = 100;

		do {
			$query = new WP_Query( array(
				'post_type'      => 'dgr_unit',
				'posts_per_page' => $per_page,
				'paged'          => $page,
				'post_status'    => 'any',
			) );

			foreach ( $query->posts as $unit ) {
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

			$max_pages = $query->max_num_pages;
			wp_reset_postdata();
			$page++;
		} while ( $page <= $max_pages );

		fclose( $output );
		exit;
	}

	/**
	 * AJAX handler: Lookup company data by NIP using Biała Lista VAT API (Ministry of Finance).
	 * Free, no API key required. Returns company name, address, KRS, REGON, VAT status.
	 */
	public function ajax_lookup_nip() {
		check_ajax_referer( 'dgr_nip_lookup', 'nonce' );

		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( array( 'message' => __( 'Brak uprawnień.', 'wp-deweloper-gov-reporter' ) ) );
		}

		$nip = isset( $_POST['nip'] ) ? preg_replace( '/[^0-9]/', '', sanitize_text_field( $_POST['nip'] ) ) : '';

		if ( strlen( $nip ) !== 10 ) {
			wp_send_json_error( array( 'message' => __( 'NIP musi mieć 10 cyfr.', 'wp-deweloper-gov-reporter' ) ) );
		}

		// Validate NIP checksum (Polish NIP validation)
		if ( ! $this->validate_nip_checksum( $nip ) ) {
			wp_send_json_error( array( 'message' => __( 'Nieprawidłowa suma kontrolna NIP.', 'wp-deweloper-gov-reporter' ) ) );
		}

		// Check transient cache first (cache for 24h to avoid hammering the API)
		$cache_key = 'dgr_nip_' . $nip;
		$cached = get_transient( $cache_key );
		if ( false !== $cached ) {
			wp_send_json_success( $cached );
		}

		// Call Biała Lista VAT API (Ministry of Finance) - free, no auth required
		$date = wp_date( 'Y-m-d' );
		$api_url = sprintf( 'https://wl-api.mf.gov.pl/api/search/nip/%s?date=%s', $nip, $date );

		$response = wp_remote_get( $api_url, array(
			'timeout' => 15,
			'headers' => array(
				'Accept' => 'application/json',
			),
		) );

		if ( is_wp_error( $response ) ) {
			wp_send_json_error( array( 'message' => __( 'Błąd połączenia z API Ministerstwa Finansów: ', 'wp-deweloper-gov-reporter' ) . $response->get_error_message() ) );
		}

		$http_code = wp_remote_retrieve_response_code( $response );
		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( 200 !== $http_code || empty( $body['result']['subject'] ) ) {
			wp_send_json_error( array( 'message' => __( 'Nie znaleziono firmy o podanym NIP w rejestrze VAT.', 'wp-deweloper-gov-reporter' ) ) );
		}

		$subject = $body['result']['subject'];

		$result = array(
			'name'       => isset( $subject['name'] ) ? $subject['name'] : '',
			'nip'        => isset( $subject['nip'] ) ? $subject['nip'] : $nip,
			'regon'      => isset( $subject['regon'] ) ? $subject['regon'] : '',
			'krs'        => isset( $subject['krs'] ) ? $subject['krs'] : '',
			'address'    => isset( $subject['residenceAddress'] ) ? $subject['residenceAddress'] : ( isset( $subject['workingAddress'] ) ? $subject['workingAddress'] : '' ),
			'status_vat' => isset( $subject['statusVat'] ) ? $subject['statusVat'] : '',
		);

		// Cache for 24 hours
		set_transient( $cache_key, $result, DAY_IN_SECONDS );

		wp_send_json_success( $result );
	}

	/**
	 * Validate Polish NIP checksum.
	 * Weights: 6, 5, 7, 2, 3, 4, 5, 6, 7
	 */
	private function validate_nip_checksum( $nip ) {
		$weights = array( 6, 5, 7, 2, 3, 4, 5, 6, 7 );
		$sum = 0;

		for ( $i = 0; $i < 9; $i++ ) {
			$sum += intval( $nip[ $i ] ) * $weights[ $i ];
		}

		$check = $sum % 11;

		return $check === intval( $nip[9] );
	}
}
