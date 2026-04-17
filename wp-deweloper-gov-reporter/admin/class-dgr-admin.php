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
		add_action( 'admin_init', array( $this, 'process_export_gov_csv' ) );
		add_action( 'admin_init', array( $this, 'process_generate_now' ) );
		add_action( 'wp_dashboard_setup', array( $this, 'add_dashboard_widgets' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
		add_action( 'wp_ajax_dgr_lookup_nip', array( $this, 'ajax_lookup_nip' ) );
	}

	public function enqueue_admin_assets( $hook ) {
		wp_enqueue_style( 'dgr-admin-css', plugins_url( '../assets/css/dgr-admin.css', __FILE__ ), array(), '1.2.0' );

		// Load NIP lookup JS only on relevant pages (investment edit, settings)
		$screen = get_current_screen();
		$load_nip_js = false;

		if ( $screen ) {
			// Investment edit screen
			if ( 'dgr_investment' === $screen->post_type && in_array( $screen->base, array( 'post', 'post-new' ), true ) ) {
				$load_nip_js = true;
			}
			// Plugin settings page
			if ( 'toplevel_page_wp-deweloper-gov-reporter' === $screen->id ) {
				$load_nip_js = true;
			}
		}

		if ( $load_nip_js ) {
			wp_enqueue_script( 'dgr-admin-js', plugins_url( '../assets/js/dgr-admin.js', __FILE__ ), array( 'jquery' ), '1.2.0', true );
			wp_localize_script( 'dgr-admin-js', 'dgr_admin', array(
				'ajaxurl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'dgr_nip_lookup' ),
				'i18n'    => array(
					'lookup'      => __( 'Pobierz dane z GUS', 'wp-deweloper-gov-reporter' ),
					'searching'   => __( 'Szukam...', 'wp-deweloper-gov-reporter' ),
					'not_found'   => __( 'Nie znaleziono firmy o podanym NIP.', 'wp-deweloper-gov-reporter' ),
					'invalid_nip' => __( 'NIP musi mieć 10 cyfr.', 'wp-deweloper-gov-reporter' ),
					'error'       => __( 'Błąd połączenia z serwerem.', 'wp-deweloper-gov-reporter' ),
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
		$text_options = array(
			'dgr_developer_name', 'dgr_developer_nip', 'dgr_developer_legal_form',
			'dgr_developer_regon', 'dgr_developer_krs', 'dgr_developer_email',
			'dgr_developer_phone', 'dgr_admin_email_notifications',
		);
		foreach ( $text_options as $opt ) {
			register_setting( 'dgr_options_group', $opt, array( 'sanitize_callback' => 'sanitize_text_field' ) );
		}

		add_settings_section(
			'dgr_general_section',
			__( 'Dane Dewelopera (wymagane przez ustawę)', 'wp-deweloper-gov-reporter' ),
			null,
			'wp-deweloper-gov-reporter'
		);

		$fields = array(
			'dgr_developer_name'            => array( __( 'Nazwa dewelopera', 'wp-deweloper-gov-reporter' ), 'render_field_developer_name' ),
			'dgr_developer_legal_form'      => array( __( 'Forma prawna', 'wp-deweloper-gov-reporter' ), 'render_field_developer_legal_form' ),
			'dgr_developer_nip'             => array( __( 'NIP', 'wp-deweloper-gov-reporter' ), 'render_field_developer_nip' ),
			'dgr_developer_regon'           => array( __( 'REGON', 'wp-deweloper-gov-reporter' ), 'render_field_developer_regon' ),
			'dgr_developer_krs'             => array( __( 'KRS / CEIDG', 'wp-deweloper-gov-reporter' ), 'render_field_developer_krs' ),
			'dgr_developer_email'           => array( __( 'Email kontaktowy', 'wp-deweloper-gov-reporter' ), 'render_field_developer_email' ),
			'dgr_developer_phone'           => array( __( 'Telefon kontaktowy', 'wp-deweloper-gov-reporter' ), 'render_field_developer_phone' ),
			'dgr_admin_email_notifications' => array( __( 'Powiadomienia email', 'wp-deweloper-gov-reporter' ), 'render_field_email_notifications' ),
		);
		foreach ( $fields as $id => $def ) {
			add_settings_field( $id, $def[0], array( $this, $def[1] ), 'wp-deweloper-gov-reporter', 'dgr_general_section' );
		}
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

	public function render_field_developer_legal_form() {
		$value = get_option( 'dgr_developer_legal_form', '' );
		$forms = array(
			''             => __( '— wybierz —', 'wp-deweloper-gov-reporter' ),
			'sp_z_oo'      => 'Spółka z o.o.',
			'sa'           => 'Spółka akcyjna',
			'sp_j'         => 'Spółka jawna',
			'sp_k'         => 'Spółka komandytowa',
			'sp_ka'        => 'Spółka komandytowo-akcyjna',
			'sp_p'         => 'Spółka partnerska',
			'dzial_gosp'   => 'Działalność gospodarcza',
			'inna'         => 'Inna',
		);
		echo '<select name="dgr_developer_legal_form">';
		foreach ( $forms as $k => $label ) {
			printf( '<option value="%s" %s>%s</option>', esc_attr( $k ), selected( $value, $k, false ), esc_html( $label ) );
		}
		echo '</select>';
	}

	public function render_field_developer_regon() {
		$value = get_option( 'dgr_developer_regon' );
		echo '<input type="text" name="dgr_developer_regon" value="' . esc_attr( $value ) . '" class="regular-text" pattern="[0-9]{9}|[0-9]{14}" title="REGON: 9 lub 14 cyfr">';
		echo '<p class="description">' . esc_html__( 'REGON (9 lub 14 cyfr).', 'wp-deweloper-gov-reporter' ) . '</p>';
	}

	public function render_field_developer_krs() {
		$value = get_option( 'dgr_developer_krs' );
		echo '<input type="text" name="dgr_developer_krs" value="' . esc_attr( $value ) . '" class="regular-text">';
		echo '<p class="description">' . esc_html__( 'Numer KRS lub wpis CEIDG.', 'wp-deweloper-gov-reporter' ) . '</p>';
	}

	public function render_field_developer_email() {
		$value = get_option( 'dgr_developer_email' );
		echo '<input type="email" name="dgr_developer_email" value="' . esc_attr( $value ) . '" class="regular-text">';
	}

	public function render_field_developer_phone() {
		$value = get_option( 'dgr_developer_phone' );
		echo '<input type="tel" name="dgr_developer_phone" value="' . esc_attr( $value ) . '" class="regular-text">';
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
				<div class="notice notice-warning inline" style="margin-top:10px;">
					<p><strong><?php esc_html_e( 'Ważne:', 'wp-deweloper-gov-reporter' ); ?></strong>
					<?php esc_html_e( 'Struktura raportu (XML + CSV) jest zgodna z Rozporządzeniem MRiT z 20.06.2024 (Dz.U. 2024 poz. 933) co do zakresu danych i układu kolumn. Oficjalny harvester dane.gov.pl może używać innych dokładnych nazw elementów/XSD — przed rejestracją porównaj wygenerowany plik z aktualną specyfikacją techniczną na dane.gov.pl.', 'wp-deweloper-gov-reporter' ); ?>
					</p>
				</div>
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
			<form method="post" action="" style="margin-bottom:10px;">
				<?php wp_nonce_field( 'dgr_export_csv_action', 'dgr_export_csv_nonce' ); ?>
				<input type="hidden" name="dgr_export_csv" value="1">
				<p>
					<?php submit_button( __( 'Eksport wewnętrzny (CSV)', 'wp-deweloper-gov-reporter' ), 'secondary', 'submit', false ); ?>
					<span class="description" style="margin-left:8px;"><?php esc_html_e( 'Pełne dane lokali w jednym pliku – użytek wewnętrzny.', 'wp-deweloper-gov-reporter' ); ?></span>
				</p>
			</form>
			<form method="post" action="">
				<?php wp_nonce_field( 'dgr_export_gov_csv_action', 'dgr_export_gov_csv_nonce' ); ?>
				<input type="hidden" name="dgr_export_gov_csv" value="1">
				<p>
					<?php submit_button( __( 'Eksport CSV dla dane.gov.pl (ustawa deweloperska)', 'wp-deweloper-gov-reporter' ), 'primary', 'submit', false ); ?>
					<span class="description" style="margin-left:8px;"><?php esc_html_e( 'Format zgodny ze strukturą rozporządzenia MRiT — zweryfikuj nagłówki z aktualnym XSD/CSV dane.gov.pl przed wysyłką.', 'wp-deweloper-gov-reporter' ); ?></span>
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

		fputcsv( $output, array( 'ID', 'Inwestycja', 'Numer Lokalu', 'Powierzchnia', 'Pokoje', 'Piętro', 'Status', 'Cena Całkowita', 'Cena m2', 'Ilość Balkonów', 'Powierzchnia Balkonów', 'Nr Garażu', 'Cena Brutto Garażu', 'Nr Komórki', 'Cena Brutto Komórki' ) );

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
					isset( $meta['_dgr_unit_balconies_count'][0] ) ? $meta['_dgr_unit_balconies_count'][0] : '',
					isset( $meta['_dgr_unit_balconies_area'][0] ) ? $meta['_dgr_unit_balconies_area'][0] : '',
					isset( $meta['_dgr_unit_garage_number'][0] ) ? $meta['_dgr_unit_garage_number'][0] : '',
					isset( $meta['_dgr_unit_garage_price_brutto'][0] ) ? $meta['_dgr_unit_garage_price_brutto'][0] : '',
					isset( $meta['_dgr_unit_storage_number'][0] ) ? $meta['_dgr_unit_storage_number'][0] : '',
					isset( $meta['_dgr_unit_storage_price_brutto'][0] ) ? $meta['_dgr_unit_storage_price_brutto'][0] : '',
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
	 * CSV export for dane.gov.pl (Rozporządzenie MRiT z 20.06.2024, Dz.U. 2024 poz. 933).
	 * Column structure follows the regulation. Rows include per-unit data
	 * plus one row per accessory (miejsce postojowe / garaż / komórka lokatorska).
	 */
	public function process_export_gov_csv() {
		if ( ! isset( $_POST['dgr_export_gov_csv'] ) ) return;
		if ( ! isset( $_POST['dgr_export_gov_csv_nonce'] ) || ! wp_verify_nonce( $_POST['dgr_export_gov_csv_nonce'], 'dgr_export_gov_csv_action' ) ) return;
		if ( ! current_user_can( 'manage_options' ) ) return;

		if ( ob_get_level() ) ob_end_clean();

		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=ceny-mieszkan-' . wp_date( 'Y-m-d' ) . '.csv' );

		$out = fopen( 'php://output', 'w' );
		fputs( $out, "\xEF\xBB\xBF" );

		$headers = array(
			'nazwa_dewelopera', 'forma_prawna', 'nip', 'regon', 'krs',
			'email', 'telefon',
			'wojewodztwo', 'powiat', 'gmina', 'miejscowosc', 'ulica', 'nr_nieruchomosci', 'kod_pocztowy',
			'rodzaj_lokalu', 'numer_lokalu',
			'cena_m2_poczatkowa', 'data_rozpoczecia_sprzedazy', 'cena_m2_aktualna', 'data_aktualizacji_ceny_m2',
			'cena_calkowita_poczatkowa', 'cena_calkowita_aktualna', 'data_aktualizacji_ceny_calkowitej',
			'cena_sprzedazy', 'data_sprzedazy',
			'rodzaj_przynaleznosci', 'oznaczenie_przynaleznosci', 'cena_przynaleznosci',
		);
		fputcsv( $out, $headers, ';' );

		$dev = array(
			'name'       => get_option( 'dgr_developer_name', '' ),
			'legal_form' => $this->legal_form_label( get_option( 'dgr_developer_legal_form', '' ) ),
			'nip'        => get_option( 'dgr_developer_nip', '' ),
			'regon'      => get_option( 'dgr_developer_regon', '' ),
			'krs'        => get_option( 'dgr_developer_krs', '' ),
			'email'      => get_option( 'dgr_developer_email', '' ),
			'phone'      => get_option( 'dgr_developer_phone', '' ),
		);

		$page = 1; $per_page = 100;
		do {
			$query = new WP_Query( array(
				'post_type'      => 'dgr_unit',
				'posts_per_page' => $per_page,
				'paged'          => $page,
				'post_status'    => 'publish',
			) );

			foreach ( $query->posts as $unit ) {
				$m         = get_post_meta( $unit->ID );
				$parent_id = isset( $m['_dgr_unit_parent_investment'][0] ) ? intval( $m['_dgr_unit_parent_investment'][0] ) : 0;
				$inv       = $parent_id ? get_post_meta( $parent_id ) : array();

				$price_total_now = isset( $m['_dgr_unit_price_total'][0] ) ? $m['_dgr_unit_price_total'][0] : '';
				$price_m2_now    = isset( $m['_dgr_unit_price_m2'][0] ) ? $m['_dgr_unit_price_m2'][0] : '';
				$price_total_ini = isset( $m['_dgr_unit_price_total_initial'][0] ) ? $m['_dgr_unit_price_total_initial'][0] : $price_total_now;
				$price_m2_ini    = isset( $m['_dgr_unit_price_m2_initial'][0] ) ? $m['_dgr_unit_price_m2_initial'][0] : $price_m2_now;
				$status          = isset( $m['_dgr_unit_status'][0] ) ? $m['_dgr_unit_status'][0] : '';
				$price_sale      = isset( $m['_dgr_unit_price_sale_brutto'][0] ) ? $m['_dgr_unit_price_sale_brutto'][0] : '';
				$date_sale       = isset( $m['_dgr_unit_sale_date'][0] ) ? $m['_dgr_unit_sale_date'][0] : '';
				$date_start      = isset( $m['_dgr_unit_sale_start_date'][0] ) ? $m['_dgr_unit_sale_start_date'][0] : '';
				$date_updated    = get_post_modified_time( 'Y-m-d', true, $unit );

				$base_row = array(
					$dev['name'], $dev['legal_form'], $dev['nip'], $dev['regon'], $dev['krs'],
					$dev['email'], $dev['phone'],
					isset( $inv['_dgr_investment_voivodeship'][0] ) ? $inv['_dgr_investment_voivodeship'][0] : '',
					isset( $inv['_dgr_investment_county'][0] ) ? $inv['_dgr_investment_county'][0] : '',
					isset( $inv['_dgr_investment_commune'][0] ) ? $inv['_dgr_investment_commune'][0] : '',
					isset( $inv['_dgr_investment_city'][0] ) ? $inv['_dgr_investment_city'][0] : '',
					isset( $inv['_dgr_investment_street'][0] ) ? $inv['_dgr_investment_street'][0] : '',
					isset( $inv['_dgr_investment_building_number'][0] ) ? $inv['_dgr_investment_building_number'][0] : '',
					isset( $inv['_dgr_investment_postal_code'][0] ) ? $inv['_dgr_investment_postal_code'][0] : '',
					$this->unit_type_label( isset( $m['_dgr_unit_type'][0] ) ? $m['_dgr_unit_type'][0] : 'lokal_mieszkalny' ),
					isset( $m['_dgr_unit_id'][0] ) ? $m['_dgr_unit_id'][0] : '',
					$price_m2_ini, $date_start, $price_m2_now, $date_updated,
					$price_total_ini, $price_total_now, $date_updated,
					$price_sale, $date_sale,
				);

				$accessories = $this->collect_accessories_for_unit( $m );
				if ( empty( $accessories ) ) {
					fputcsv( $out, array_merge( $base_row, array( '', '', '' ) ), ';' );
				} else {
					foreach ( $accessories as $acc ) {
						fputcsv( $out, array_merge( $base_row, array( $acc['rodzaj'], $acc['oznaczenie'], $acc['cena'] ) ), ';' );
					}
				}
			}

			$max_pages = $query->max_num_pages;
			wp_reset_postdata();
			$page++;
		} while ( $page <= $max_pages );

		fclose( $out );
		exit;
	}

	private function legal_form_label( $key ) {
		$map = array(
			'sp_z_oo' => 'Spółka z o.o.', 'sa' => 'Spółka akcyjna',
			'sp_j' => 'Spółka jawna', 'sp_k' => 'Spółka komandytowa',
			'sp_ka' => 'Spółka komandytowo-akcyjna', 'sp_p' => 'Spółka partnerska',
			'dzial_gosp' => 'Działalność gospodarcza', 'inna' => 'Inna',
		);
		return isset( $map[ $key ] ) ? $map[ $key ] : '';
	}

	private function unit_type_label( $key ) {
		return 'dom_jednorodzinny' === $key ? 'dom jednorodzinny' : 'lokal mieszkalny';
	}

	private function collect_accessories_for_unit( $meta ) {
		$items = array();

		if ( ! empty( $meta['_dgr_unit_garage_number'][0] ) || ! empty( $meta['_dgr_unit_garage_price_brutto'][0] ) ) {
			$items[] = array(
				'rodzaj'     => 'garaż',
				'oznaczenie' => isset( $meta['_dgr_unit_garage_number'][0] ) ? $meta['_dgr_unit_garage_number'][0] : '',
				'cena'       => isset( $meta['_dgr_unit_garage_price_brutto'][0] ) ? $meta['_dgr_unit_garage_price_brutto'][0] : '',
			);
		}

		if ( ! empty( $meta['_dgr_unit_storage_number'][0] ) || ! empty( $meta['_dgr_unit_storage_price_brutto'][0] ) ) {
			$items[] = array(
				'rodzaj'     => 'komórka lokatorska',
				'oznaczenie' => isset( $meta['_dgr_unit_storage_number'][0] ) ? $meta['_dgr_unit_storage_number'][0] : '',
				'cena'       => isset( $meta['_dgr_unit_storage_price_brutto'][0] ) ? $meta['_dgr_unit_storage_price_brutto'][0] : '',
			);
		}

		// Legacy JSON dependencies (miejsce_postojowe etc.)
		$deps_raw = isset( $meta['_dgr_unit_dependencies'][0] ) ? $meta['_dgr_unit_dependencies'][0] : '';
		if ( $deps_raw ) {
			$decoded = json_decode( $deps_raw, true );
			if ( is_array( $decoded ) ) {
				foreach ( $decoded as $d ) {
					if ( isset( $d['typ'] ) && isset( $d['cena'] ) ) {
						$items[] = array(
							'rodzaj'     => str_replace( '_', ' ', (string) $d['typ'] ),
							'oznaczenie' => isset( $d['oznaczenie'] ) ? (string) $d['oznaczenie'] : '',
							'cena'       => floatval( $d['cena'] ),
						);
					}
				}
			}
		}

		return $items;
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
