<?php
/**
 * Plugin Name: WP Deweloper Gov Reporter
 * Description: Automates daily reporting of apartment prices to dane.gov.pl and displays price history, complying with July 2025 regulations. Supports Elementor.
 * Version: 1.0.0
 * Author: Jules
 * Text Domain: wp-deweloper-gov-reporter
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The main plugin class.
 */
class WP_Deweloper_Gov_Reporter {

	/**
	 * The unique identifier of this plugin.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      string    $plugin_name    The string used to uniquely identify this plugin.
	 */
	protected $plugin_name;

	/**
	 * The current version of the plugin.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      string    $version    The current version of the plugin.
	 */
	protected $version;

	/**
	 * The single instance of the class.
	 */
	protected static $instance = null;

	/**
	 * Instance accessor.
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		$this->plugin_name = 'wp-deweloper-gov-reporter';
		$this->version     = '1.0.0';

		$this->load_dependencies();
		$this->set_locale();
		$this->define_admin_hooks();
		$this->define_public_hooks();
	}

	/**
	 * Load the required dependencies for this plugin.
	 */
	private function load_dependencies() {
		require_once plugin_dir_path( __FILE__ ) . 'includes/class-dgr-post-types.php';
		require_once plugin_dir_path( __FILE__ ) . 'includes/class-dgr-metaboxes.php';
		require_once plugin_dir_path( __FILE__ ) . 'includes/class-dgr-price-history.php';
		require_once plugin_dir_path( __FILE__ ) . 'includes/class-dgr-api-connector.php';
		require_once plugin_dir_path( __FILE__ ) . 'includes/elementor/class-dgr-elementor.php';

		if ( is_admin() ) {
			require_once plugin_dir_path( __FILE__ ) . 'admin/class-dgr-admin.php';
		}
		require_once plugin_dir_path( __FILE__ ) . 'public/class-dgr-public.php';
	}

	/**
	 * Define the locale for this plugin for internationalization.
	 */
	private function set_locale() {
		load_plugin_textdomain( 'wp-deweloper-gov-reporter', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
	}

	/**
	 * Register all of the hooks related to the admin area functionality.
	 */
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
		}

		add_action( 'dgr_daily_report_event', array( $this, 'run_daily_report' ) );
	}

	/**
	 * Register all of the hooks related to the public-facing functionality.
	 */
	private function define_public_hooks() {
		$plugin_public = new DGR_Public( $this->get_plugin_name(), $this->get_version() );
		$plugin_public->init();

		// Initialize Elementor integration on plugins_loaded to ensure Elementor is active
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
	 * Get the plugin name.
	 */
	public function get_plugin_name() {
		return $this->plugin_name;
	}

	/**
	 * Get the version number.
	 */
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
run_wp_deweloper_gov_reporter();

// Activation hook
register_activation_hook( __FILE__, 'activate_wp_deweloper_gov_reporter' );

function activate_wp_deweloper_gov_reporter() {
	// Schedule cron, flush rewrite rules
	if ( ! wp_next_scheduled( 'dgr_daily_report_event' ) ) {
		wp_schedule_event( time(), 'daily', 'dgr_daily_report_event' );
	}
}

// Deactivation hook
register_deactivation_hook( __FILE__, 'deactivate_wp_deweloper_gov_reporter' );

function deactivate_wp_deweloper_gov_reporter() {
	wp_clear_scheduled_hook( 'dgr_daily_report_event' );
}
