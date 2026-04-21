<?php
/**
 * Plugin Name: WP Deweloper Gov Reporter
 * Description: Generuje codzienne raporty XML z cenami mieszkań zgodnie z wymogami ustawy deweloperskiej (od 11.07.2025). Pliki XML i MD5 serwowane na stałych URL do pobierania przez dane.gov.pl. Obsługuje Elementora.
 * Version: 2.1.0
 * Author: Jakub Wcisło
 * Text Domain: wp-deweloper-gov-reporter
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The main plugin class.
 */
class WP_Deweloper_Gov_Reporter {

	protected $plugin_name;
	protected $version;
	protected static $instance = null;

	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		$this->plugin_name = 'wp-deweloper-gov-reporter';
		$this->version     = '2.1.0';

		$this->load_dependencies();
		$this->set_locale();
		$this->define_admin_hooks();
		$this->define_public_hooks();
	}

	private function load_dependencies() {
		require_once plugin_dir_path( __FILE__ ) . 'includes/class-dgr-post-types.php';
		require_once plugin_dir_path( __FILE__ ) . 'includes/class-dgr-metaboxes.php';
		require_once plugin_dir_path( __FILE__ ) . 'includes/class-dgr-price-history.php';
		require_once plugin_dir_path( __FILE__ ) . 'includes/class-dgr-api-connector.php';
		require_once plugin_dir_path( __FILE__ ) . 'includes/elementor/class-dgr-elementor.php';

		if ( is_admin() ) {
			require_once plugin_dir_path( __FILE__ ) . 'admin/class-dgr-admin.php';
			require_once plugin_dir_path( __FILE__ ) . 'includes/class-dgr-admin-columns.php';
		}
		require_once plugin_dir_path( __FILE__ ) . 'public/class-dgr-public.php';
	}

	private function set_locale() {
		load_plugin_textdomain( 'wp-deweloper-gov-reporter', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
	}

	private function define_admin_hooks() {
		$post_types = new DGR_Post_Types();
		$post_types->init();

		$metaboxes = new DGR_Metaboxes();
		$metaboxes->init();

		$price_history = new DGR_Price_History();
		$price_history->init();

		if ( is_admin() ) {
			$plugin_admin = new DGR_Admin( $this->get_plugin_name(), $this->get_version() );
			$plugin_admin->init();

			$admin_columns = new DGR_Admin_Columns();
			$admin_columns->init();

			// Admin notice if last report is older than 25 hours
			add_action( 'admin_notices', array( $this, 'check_report_freshness' ) );
		}

		add_action( 'dgr_daily_report_event', array( $this, 'run_daily_report' ) );
	}

	private function define_public_hooks() {
		$plugin_public = new DGR_Public( $this->get_plugin_name(), $this->get_version() );
		$plugin_public->init();

		add_action( 'plugins_loaded', function() {
			$elementor_manager = new DGR_Elementor_Manager();
			$elementor_manager->init();
		});
	}

	public function run_daily_report() {
		$api_connector = new DGR_API_Connector();
		$api_connector->generate_and_send_report();
	}

	/**
	 * Show admin notice if report hasn't been generated in over 25 hours.
	 */
	public function check_report_freshness() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$last_report = get_option( 'dgr_last_successful_report', '' );
		if ( empty( $last_report ) ) {
			echo '<div class="notice notice-warning"><p>';
			echo '<strong>WP Deweloper Gov Reporter:</strong> ';
			esc_html_e( 'Raport XML nie został jeszcze wygenerowany. Przejdź do Deweloper Gov > Ustawienia i kliknij "Generuj raport teraz".', 'wp-deweloper-gov-reporter' );
			echo '</p></div>';
			return;
		}

		$last_time = strtotime( $last_report );
		$hours_ago = ( time() - $last_time ) / 3600;

		if ( $hours_ago > 25 ) {
			echo '<div class="notice notice-error"><p>';
			echo '<strong>WP Deweloper Gov Reporter:</strong> ';
			echo esc_html( sprintf(
				__( 'Ostatni raport XML został wygenerowany %s temu. Raport powinien być aktualizowany codziennie. Sprawdź ustawienia CRON.', 'wp-deweloper-gov-reporter' ),
				human_time_diff( $last_time )
			) );
			echo '</p></div>';
		}
	}

	public function get_plugin_name() {
		return $this->plugin_name;
	}

	public function get_version() {
		return $this->version;
	}
}

/**
 * Begins execution of the plugin.
 */
function run_wp_deweloper_gov_reporter() {
	$plugin = WP_Deweloper_Gov_Reporter::get_instance();
}
add_action( 'plugins_loaded', 'run_wp_deweloper_gov_reporter', 5 );

// Activation hook
register_activation_hook( __FILE__, 'activate_wp_deweloper_gov_reporter' );

function activate_wp_deweloper_gov_reporter() {
	// Schedule daily cron at 01:00 local time
	if ( ! wp_next_scheduled( 'dgr_daily_report_event' ) ) {
		// Calculate next 01:00 in WP timezone
		$timezone = wp_timezone();
		$now = new DateTime( 'now', $timezone );
		$target = new DateTime( 'today 01:00', $timezone );
		if ( $now > $target ) {
			$target->modify( '+1 day' );
		}
		wp_schedule_event( $target->getTimestamp(), 'daily', 'dgr_daily_report_event' );
	}

	// Flush rewrite rules after CPTs are registered
	// Schedule a flag so it runs on next admin_init
	update_option( 'dgr_rewrite_rules_flushed_v2', false );
}

// Deactivation hook
register_deactivation_hook( __FILE__, 'deactivate_wp_deweloper_gov_reporter' );

function deactivate_wp_deweloper_gov_reporter() {
	wp_clear_scheduled_hook( 'dgr_daily_report_event' );
	delete_option( 'dgr_rewrite_rules_flushed_v2' );
}
