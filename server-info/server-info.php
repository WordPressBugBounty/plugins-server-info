<?php
/**
 * Plugin Name: Server Info - System Health & Diagnostics Suite
 * Plugin URI: https://wordpress.org/plugins/server-info/
 * Description: The ultimate dashboard to monitor server configuration, database health, caching performance, and critical WordPress diagnostics in real-time.
 * Version: 1.1.0
 * Requires at least: 5.5
 * Requires PHP: 7.3
 * Author: Usman Ali Qureshi
 * Author URI: https://usmanaliqureshi.com/
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: server-info
 * Domain Path: /languages/
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'server_info_fs' ) ) {
	/**
	 * Freemius SDK helper.
	 *
	 * @return Freemius
	 */
	function server_info_fs() {
		global $server_info_fs;

		if ( ! isset( $server_info_fs ) ) {
			require_once __DIR__ . '/vendor/freemius/start.php';

			$server_info_fs = fs_dynamic_init( array(
				'id'               => '2860',
				'slug'             => 'server-info',
				'type'             => 'plugin',
				'public_key'       => 'pk_6e7a210fbe9898524cf4df3c6d6fb',
				'is_premium'       => false,
				'has_addons'       => false,
				'has_paid_plans'   => true,
				'is_org_compliant' => true,
				'menu'             => array(
					'slug'    => 'server_info_display',
					'account' => false,
					'contact' => false,
					'support' => false,
					'pricing' => false,
					'parent'  => array(
						'slug' => 'options-general.php',
					),
				),
			) );
		}

		return $server_info_fs;
	}

	server_info_fs();
	server_info_fs()->add_filter( 'is_pricing_page_visible', '__return_false' );
	do_action( 'server_info_fs_loaded' );
}

// Set the plugin version.
define( 'SERVER_INFO_PLUGIN_VERSION', '1.1.0' );

// Set the plugin file.
define( 'SERVER_INFO_PLUGIN_FILE', __FILE__ );

// Set the absolute path for the plugin.
define( 'SERVER_INFO_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );

// Set the plugin URL root.
define( 'SERVER_INFO_PLUGIN_URL', plugins_url( '/', __FILE__ ) );

// Set the plugin option key.
define( 'SERVER_INFO_OPTION_NAME', 'server_info_options' );

// Freemius product values for the optional supporter checkout.
define( 'SERVER_INFO_FREEMIUS_PRODUCT_ID', '2860' );
define( 'SERVER_INFO_FREEMIUS_SUPPORTER_PLAN_ID', '61241' );
define( 'SERVER_INFO_FREEMIUS_BACKER_PLAN_ID', '61242' );
define( 'SERVER_INFO_FREEMIUS_SPONSOR_PLAN_ID', '61243' );
define( 'SERVER_INFO_FREEMIUS_AGENCY_SPONSOR_PLAN_ID', '61244' );
define( 'SERVER_INFO_FREEMIUS_PUBLIC_KEY', 'pk_6e7a210fbe9898524cf4df3c6d6fb' );

/**
 * Class Server_Info
 *
 * Main plugin class that handles the collection and display of server information.
 *
 * @package Server_Info
 * @since 0.0.1
 */
class Server_Info {

	/**
	 * Singleton instance static property.
	 *
	 * @var Server_Info|bool
	 * @since 0.0.1
	 */
	static $instance = false;

	/**
	 * Retrieves the singleton instance of the class.
	 *
	 * @since 0.0.1
	 * @access public
	 *
	 * @return Server_Info The singleton instance.
	 */
	public static function getInstance() {
		if ( ! self::$instance ) {
			self::$instance = new self;
		}

		return self::$instance;
	}

	/**
	 * Detect the current environment.
	 *
	 * @return string
	 */
	public static function get_environment_type() {
		$env = 'Production';
		if ( function_exists( 'wp_get_environment_type' ) ) {
			$env = ucfirst( wp_get_environment_type() );
		}
		
		$host = self::get_server_value( 'HTTP_HOST' );
		if ( strpos( $host, '.local' ) !== false || strpos( $host, '.test' ) !== false || strpos( $host, 'localhost' ) !== false ) {
			$env = 'Local';
		} elseif ( strpos( $host, 'staging.' ) !== false || strpos( $host, 'dev.' ) !== false ) {
			$env = 'Staging';
		}
		
		return $env;
	}

	/**
	 * Returns a sanitized value from the web server environment.
	 *
	 * @param string $key     Server variable key.
	 * @param string $default Fallback value.
	 * @return string
	 */
	public static function get_server_value( $key, $default = '' ) {
		if ( ! isset( $_SERVER[ $key ] ) ) {
			return $default;
		}

		$value = sanitize_text_field( wp_unslash( $_SERVER[ $key ] ) );

		return '' !== $value ? $value : $default;
	}

	/**
	 * Plugin constructor.
	 *
	 * @since 0.0.1
	 * @access private
	 */
	private function __construct() {
		$this->init();
	}

	/**
	 * Initializes plugin hooks and actions.
	 *
	 * @since 0.0.1
	 * @access public
	 *
	 * @return void
	 */
	public function init() {
		add_action( 'wp_dashboard_setup', array( $this, 'add_dashboard_widgets' ) );
		add_action( 'admin_menu', array( $this, 'add_plugin_menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_scripts' ) );
		add_action( 'admin_bar_menu', array( $this, 'add_admin_bar_hud' ), 999 );

		// Footer HUD
		add_filter( 'admin_footer_text', array( $this, 'add_admin_footer_text' ), 999 );
		add_filter( 'update_footer', array( $this, 'add_update_footer_text' ), 999 );
	}

	/**
	 * Add Always-On HUD to WordPress Admin Bar.
	 */
	 public function add_admin_bar_hud( $wp_admin_bar ) {
		 if ( ! current_user_can( 'manage_options' ) ) {
			 return;
		 }

		 if ( ! self::is_admin_bar_hud_enabled() ) {
			 return;
		 }

		$env = self::get_environment_type();
		$env_color = '#d63638'; // Red for Production
		if ( 'Local' === $env ) {
			$env_color = '#00a32a'; // Green for Local
		} elseif ( 'Staging' === $env ) {
			$env_color = '#dba617'; // Orange for Staging
		}

		$php_version = substr( phpversion(), 0, 3 );
		
		$memory_usage = memory_get_peak_usage( true );
		$memory_limit_bytes = wp_convert_hr_to_bytes( ini_get( 'memory_limit' ) );

		if ( $memory_limit_bytes > 0 ) {
			$percentage = round( ( $memory_usage / $memory_limit_bytes ) * 100 );
			$memory_percentage = $percentage . '%';
		} else {
			$memory_percentage = size_format( $memory_usage );
		}

		$badge_html = '<span style="display:inline-block; padding:0 6px; border-radius:3px; background-color:' . esc_attr( $env_color ) . '; color:#fff; font-weight:bold; font-size:11px; text-transform:uppercase; margin-right:8px; line-height:1.6;">' . esc_html( $env ) . '</span>';
		$title_html = $badge_html . ' PHP ' . esc_html( $php_version ) . ' | RAM ' . esc_html( $memory_percentage );

		$wp_admin_bar->add_node( array(
			'id'    => 'si_admin_bar_hud',
			'title' => $title_html,
			'href'  => admin_url( 'options-general.php?page=server_info_display' ),
			'meta'  => array(
				'title' => esc_attr__( 'Server Info', 'server-info' ),
			),
		) );

		$wp_admin_bar->add_node( array(
			'id'     => 'si_hud_ip',
			'parent' => 'si_admin_bar_hud',
				'title'  => esc_html__( 'Server IP: ', 'server-info' ) . esc_html( self::get_server_value( 'SERVER_ADDR', '127.0.0.1' ) ),
		) );

		$web_server = self::get_server_value( 'SERVER_SOFTWARE', esc_html__( 'Unknown', 'server-info' ) );
		if ( strlen( $web_server ) > 30 ) {
			$web_server = substr( $web_server, 0, 27 ) . '...';
		}

		$wp_admin_bar->add_node( array(
			'id'     => 'si_hud_web_server',
			'parent' => 'si_admin_bar_hud',
			'title'  => esc_html__( 'Web Server: ', 'server-info' ) . $web_server,
		) );

		$wp_admin_bar->add_node( array(
			'id'     => 'si_hud_os',
			'parent' => 'si_admin_bar_hud',
			'title'  => esc_html__( 'Operating System: ', 'server-info' ) . PHP_OS,
		) );

		global $wp_version, $wpdb;
		$db_version = $wpdb->db_version();

		$wp_admin_bar->add_node( array(
			'id'     => 'si_hud_db',
			'parent' => 'si_admin_bar_hud',
			'title'  => esc_html__( 'Database: ', 'server-info' ) . $db_version,
		) );

		$wp_admin_bar->add_node( array(
			'id'     => 'si_hud_wp',
			'parent' => 'si_admin_bar_hud',
			'title'  => esc_html__( 'WordPress: ', 'server-info' ) . $wp_version,
		) );

		$wp_admin_bar->add_node( array(
			'id'     => 'si_hud_php_limit',
			'parent' => 'si_admin_bar_hud',
			'title'  => esc_html__( 'PHP Memory Limit: ', 'server-info' ) . ini_get( 'memory_limit' ),
		) );

		$wp_memory_limit = defined( 'WP_MEMORY_LIMIT' ) ? WP_MEMORY_LIMIT : '40M';
		$wp_admin_bar->add_node( array(
			'id'     => 'si_hud_wp_limit',
			'parent' => 'si_admin_bar_hud',
			'title'  => esc_html__( 'WP Memory Limit: ', 'server-info' ) . $wp_memory_limit,
		) );
	}

	/**
	 * Add server info to the left side of the admin footer.
	 *
	 * @param string $text The existing footer text.
	 * @return string
	 */
	 public function add_admin_footer_text( $text ) {
		 if ( ! current_user_can( 'manage_options' ) ) {
			 return $text;
		 }

		 if ( ! self::is_footer_info_enabled() ) {
			 return $text;
		 }

		$memory_usage       = memory_get_peak_usage( true );
		$memory_limit_bytes = wp_convert_hr_to_bytes( ini_get( 'memory_limit' ) );
		$memory_summary     = size_format( $memory_usage );

		if ( $memory_limit_bytes > 0 ) {
			$memory_summary = round( ( $memory_usage / $memory_limit_bytes ) * 100 ) . '% RAM';
		}

		$info = sprintf(
			' <span class="si-admin-footer-summary">| %1$s: %2$s | %3$s</span>',
			esc_html__( 'Server Info', 'server-info' ),
			esc_html( self::get_environment_type() ),
			esc_html( $memory_summary )
		);

		return $text . $info;
	}

	/**
	 * Add server info to the right side of the admin footer.
	 *
	 * @param string $text The existing update footer text.
	 * @return string
	 */
	 public function add_update_footer_text( $text ) {
		return $text;
	}



	/**
	 * Enqueues admin styles and scripts and sets dynamic theme variables.
	 *
	 * @since 0.0.1
	 * @access public
	 *
	 * @return void
	 */
	public function admin_scripts( $hook ) {
		if ( 'settings_page_server_info_display' !== $hook && 'index.php' !== $hook ) {
			return;
		}

		$style_path    = SERVER_INFO_PLUGIN_DIR . 'assets/css/style.css';
		$style_version = file_exists( $style_path ) ? (string) filemtime( $style_path ) : SERVER_INFO_PLUGIN_VERSION;

		wp_enqueue_style( 'server-info', SERVER_INFO_PLUGIN_URL . 'assets/css/style.css', array(), $style_version, 'all' );

		wp_add_inline_style( 'server-info', self::get_appearance_css() );

		if ( 'settings_page_server_info_display' === $hook ) {
			$script_path    = SERVER_INFO_PLUGIN_DIR . 'assets/js/admin.js';
			$script_version = file_exists( $script_path ) ? (string) filemtime( $script_path ) : SERVER_INFO_PLUGIN_VERSION;

			wp_enqueue_script( 'server-info-admin', SERVER_INFO_PLUGIN_URL . 'assets/js/admin.js', array(), $script_version, true );
			wp_localize_script(
				'server-info-admin',
				'serverInfoAdmin',
				array(
					'checkoutUrl'      => 'https://checkout.freemius.com/js/v1/',
					'productId'        => SERVER_INFO_FREEMIUS_PRODUCT_ID,
					'publicKey'        => SERVER_INFO_FREEMIUS_PUBLIC_KEY,
					'productName'      => 'Server Info',
					'loadingLabel'     => __( 'Opening secure checkout...', 'server-info' ),
					'unavailableLabel' => __( 'Support checkout is temporarily unavailable. Please try again later.', 'server-info' ),
					'thankYouLabel'    => __( 'Thank you for supporting Server Info.', 'server-info' ),
				)
			);
		}
	}

	/**
	 * Gets the available voluntary supporter plans.
	 *
	 * @return array
	 */
	public static function get_support_plans() {
		return array(
			array(
				'id'          => SERVER_INFO_FREEMIUS_SUPPORTER_PLAN_ID,
				'title'       => esc_html__( 'Supporter', 'server-info' ),
				'amount'      => '$5',
				'description' => esc_html__( 'A small thank-you that helps keep Server Info maintained.', 'server-info' ),
				'featured'    => true,
			),
			array(
				'id'          => SERVER_INFO_FREEMIUS_BACKER_PLAN_ID,
				'title'       => esc_html__( 'Backer', 'server-info' ),
				'amount'      => '$15',
				'description' => esc_html__( 'Fuel ongoing updates, testing, and thoughtful maintenance.', 'server-info' ),
			),
			array(
				'id'          => SERVER_INFO_FREEMIUS_SPONSOR_PLAN_ID,
				'title'       => esc_html__( 'Sponsor', 'server-info' ),
				'amount'      => '$49',
				'description' => esc_html__( 'Help shape new diagnostics and stronger, more reliable releases.', 'server-info' ),
			),
			array(
				'id'          => SERVER_INFO_FREEMIUS_AGENCY_SPONSOR_PLAN_ID,
				'title'       => esc_html__( 'Agency Sponsor', 'server-info' ),
				'amount'      => '$99',
				'description' => esc_html__( 'Sustain Server Info for agencies managing client websites.', 'server-info' ),
			),
		);
	}

	/**
	 * Registers the plugin options page under Settings.
	 *
	 * @since 0.0.1
	 * @access public
	 *
	 * @return void
	 */
	 public function add_plugin_menu() {
		 add_options_page(
			 esc_html__( 'Server Information', 'server-info' ),
			esc_html__( 'Server Info', 'server-info' ),
			'manage_options',
			'server_info_display',
			array( 'Server_Info', 'display_server_info' )
		 );
	 }

	/**
	 * Registers plugin settings.
	 *
	 * @return void
	 */
	public function register_settings() {
		register_setting(
			'server_info_settings',
			SERVER_INFO_OPTION_NAME,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( 'Server_Info', 'sanitize_settings' ),
				'default'           => self::get_default_options(),
			)
		);
	}

	/**
	 * Gets the default plugin options.
	 *
	 * @return array
	 */
	public static function get_default_options() {
		return array(
			'admin_bar_hud'        => 1,
			'footer_info'          => 1,
			'domain_expiry_lookup' => 0,
			'appearance_scheme'    => 'default',
			'custom_bg_color'      => '#f8fafc',
			'custom_text_color'    => '#0f172a',
		);
	}

	/**
	 * Gets merged plugin options.
	 *
	 * @return array
	 */
	public static function get_options() {
		$options = get_option( SERVER_INFO_OPTION_NAME, array() );

		if ( ! is_array( $options ) ) {
			$options = array();
		}

		return wp_parse_args( $options, self::get_default_options() );
	}

	/**
	 * Sanitizes plugin settings.
	 *
	 * @param array $input Raw settings.
	 * @return array
	 */
	public static function sanitize_settings( $input ) {
		$input = is_array( $input ) ? $input : array();
		$scheme = isset( $input['appearance_scheme'] ) ? sanitize_key( $input['appearance_scheme'] ) : 'default';

		if ( ! array_key_exists( $scheme, self::get_appearance_schemes() ) ) {
			$scheme = 'default';
		}

		$custom_bg   = isset( $input['custom_bg_color'] ) ? sanitize_hex_color( $input['custom_bg_color'] ) : '';
		$custom_text = isset( $input['custom_text_color'] ) ? sanitize_hex_color( $input['custom_text_color'] ) : '';

		return array(
			'admin_bar_hud'        => empty( $input['admin_bar_hud'] ) ? 0 : 1,
			'footer_info'          => empty( $input['footer_info'] ) ? 0 : 1,
			'domain_expiry_lookup' => empty( $input['domain_expiry_lookup'] ) ? 0 : 1,
			'appearance_scheme'    => $scheme,
			'custom_bg_color'      => $custom_bg ? $custom_bg : '#f8fafc',
			'custom_text_color'    => $custom_text ? $custom_text : '#0f172a',
		);
	}

	/**
	 * Determines if the admin bar HUD is enabled.
	 *
	 * @return bool
	 */
	public static function is_admin_bar_hud_enabled() {
		$options = self::get_options();

		return ! empty( $options['admin_bar_hud'] );
	}

	/**
	 * Determines if footer diagnostics are enabled.
	 *
	 * @return bool
	 */
	public static function is_footer_info_enabled() {
		$options = self::get_options();

		return ! empty( $options['footer_info'] );
	}

	/**
	 * Determines if domain expiry lookups are enabled.
	 *
	 * @return bool
	 */
	public static function is_domain_expiry_lookup_enabled() {
		$options = self::get_options();

		return ! empty( $options['domain_expiry_lookup'] );
	}

	/**
	 * Gets supported appearance schemes.
	 *
	 * @return array
	 */
	public static function get_appearance_schemes() {
		return array(
			'default'       => array(
				'label' => esc_html__( 'Default', 'server-info' ),
				'vars'  => array(),
			),
			'calm_blue'     => array(
				'label' => esc_html__( 'Calm Blue', 'server-info' ),
				'vars'  => array(
					'--si-bg'            => '#eef6ff',
					'--si-surface'       => '#ffffff',
					'--si-border'        => '#bfdbfe',
					'--si-text-main'     => '#102033',
					'--si-text-muted'    => '#4f6b87',
					'--si-primary'       => '#2563eb',
					'--si-primary-light' => '#dbeafe',
				),
			),
			'fresh_green'   => array(
				'label' => esc_html__( 'Fresh Green', 'server-info' ),
				'vars'  => array(
					'--si-bg'            => '#f0fdf4',
					'--si-surface'       => '#ffffff',
					'--si-border'        => '#bbf7d0',
					'--si-text-main'     => '#13241a',
					'--si-text-muted'    => '#526b5a',
					'--si-primary'       => '#15803d',
					'--si-primary-light' => '#dcfce7',
				),
			),
			'high_contrast' => array(
				'label' => esc_html__( 'High Contrast', 'server-info' ),
				'vars'  => array(
					'--si-bg'            => '#111827',
					'--si-surface'       => '#ffffff',
					'--si-border'        => '#d1d5db',
					'--si-text-main'     => '#030712',
					'--si-text-muted'    => '#374151',
					'--si-primary'       => '#1d4ed8',
					'--si-primary-light' => '#dbeafe',
				),
			),
			'custom'        => array(
				'label' => esc_html__( 'Custom Colors', 'server-info' ),
				'vars'  => array(),
			),
		);
	}

	/**
	 * Builds CSS variables for the selected appearance.
	 *
	 * @return string
	 */
	public static function get_appearance_css() {
		$options = self::get_options();
		$schemes = self::get_appearance_schemes();
		$scheme  = isset( $options['appearance_scheme'] ) ? $options['appearance_scheme'] : 'default';
		$vars    = isset( $schemes[ $scheme ] ) ? $schemes[ $scheme ]['vars'] : array();

		if ( 'custom' === $scheme ) {
			$vars = array(
				'--si-bg'         => $options['custom_bg_color'],
				'--si-text-main'  => $options['custom_text_color'],
				'--si-text-muted' => $options['custom_text_color'],
			);
		}

		if ( empty( $vars ) ) {
			return '';
		}

		$css = '.server-info-wrapper {';
		foreach ( $vars as $name => $value ) {
			$css .= sprintf( '%s:%s;', $name, $value );
		}
		$css .= '}';

		return $css;
	}

	/**
	 * Gets the normalized public-facing site domain.
	 *
	 * @return string
	 */
	public static function get_site_domain() {
		$host = wp_parse_url( home_url(), PHP_URL_HOST );

		if ( empty( $host ) && ! empty( $_SERVER['HTTP_HOST'] ) ) {
			$host = sanitize_text_field( wp_unslash( $_SERVER['HTTP_HOST'] ) );
		}

		$host = strtolower( trim( (string) $host ) );
		$host = preg_replace( '/:\d+$/', '', $host );

		if ( function_exists( 'idn_to_ascii' ) ) {
			$idn_host = idn_to_ascii( $host, 0, defined( 'INTL_IDNA_VARIANT_UTS46' ) ? INTL_IDNA_VARIANT_UTS46 : 0 );
			if ( ! empty( $idn_host ) ) {
				$host = strtolower( $idn_host );
			}
		}

		return $host;
	}

	/**
	 * Gets the likely registered/root domain for expiry lookups.
	 *
	 * @param string $domain Site host or domain.
	 * @return string
	 */
	public static function get_registered_domain( $domain ) {
		$domain = strtolower( trim( (string) $domain, ". \t\n\r\0\x0B" ) );

		if ( ! self::is_public_domain( $domain ) ) {
			return $domain;
		}

		$parts = explode( '.', $domain );
		if ( count( $parts ) <= 2 ) {
			return $domain;
		}

		$known_second_level_suffixes = array(
			'ac.uk',
			'co.uk',
			'gov.uk',
			'ltd.uk',
			'me.uk',
			'net.uk',
			'org.uk',
			'plc.uk',
			'com.au',
			'net.au',
			'org.au',
			'com.br',
			'net.br',
			'org.br',
			'com.cn',
			'net.cn',
			'org.cn',
			'com.mx',
			'co.nz',
			'net.nz',
			'org.nz',
			'ac.nz',
			'co.jp',
			'ne.jp',
			'or.jp',
			'ac.jp',
			'com.pk',
			'net.pk',
			'org.pk',
			'edu.pk',
			'gov.pk',
			'com.tr',
			'net.tr',
			'org.tr',
		);

		$suffix = implode( '.', array_slice( $parts, -2 ) );
		if ( in_array( $suffix, $known_second_level_suffixes, true ) && count( $parts ) >= 3 ) {
			return implode( '.', array_slice( $parts, -3 ) );
		}

		return implode( '.', array_slice( $parts, -2 ) );
	}

	/**
	 * Determines whether the domain is worth probing externally.
	 *
	 * @param string $domain Domain name.
	 * @return bool
	 */
	public static function is_public_domain( $domain ) {
		if ( empty( $domain ) ) {
			return false;
		}

		if ( false === strpos( $domain, '.' ) ) {
			return false;
		}

		if ( preg_match( '/(\.local|\.test|\.localhost|\.invalid|\.example)$/', $domain ) ) {
			return false;
		}

		if ( 'localhost' === $domain || filter_var( $domain, FILTER_VALIDATE_IP ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Gets SSL/TLS certificate metadata for the site's host.
	 *
	 * @param string $domain Domain name.
	 * @return array
	 */
	public static function get_ssl_certificate_info( $domain ) {
		$default = array(
			'available'  => false,
			'status'     => esc_html__( 'Unavailable', 'server-info' ),
			'message'    => esc_html__( 'SSL certificate details could not be read for this site.', 'server-info' ),
			'issuer'     => esc_html__( 'Unavailable', 'server-info' ),
			'domain'     => $domain,
			'expiry'     => esc_html__( 'Unavailable', 'server-info' ),
			'days_left'  => null,
			'status_key' => 'neutral',
		);

		if ( ! self::is_public_domain( $domain ) ) {
			$default['message'] = esc_html__( 'SSL lookup is skipped for local, test, or IP-based domains.', 'server-info' );
			return $default;
		}

		$cache_key = 'si_ssl_cert_' . md5( $domain );
		$cached    = get_transient( $cache_key );
		if ( is_array( $cached ) ) {
			return $cached;
		}

		if ( ! function_exists( 'stream_socket_client' ) || ! function_exists( 'openssl_x509_parse' ) ) {
			$default['message'] = esc_html__( 'The required PHP stream or OpenSSL functions are not available.', 'server-info' );
			set_transient( $cache_key, $default, 6 * HOUR_IN_SECONDS );
			return $default;
		}

		$context = stream_context_create(
			array(
				'ssl' => array(
					'capture_peer_cert' => true,
					'peer_name'         => $domain,
					'verify_peer'       => false,
					'verify_peer_name'  => false,
					'SNI_enabled'       => true,
				),
			)
		);

		$client = @stream_socket_client( 'ssl://' . $domain . ':443', $error_code, $error_message, 4, STREAM_CLIENT_CONNECT, $context );
		if ( ! $client ) {
			$default['message'] = $error_message ? $error_message : esc_html__( 'Connection to port 443 failed.', 'server-info' );
			set_transient( $cache_key, $default, 2 * HOUR_IN_SECONDS );
			return $default;
		}

		$params = stream_context_get_params( $client );
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- This closes a TLS network socket, not a filesystem handle.
			fclose( $client );

		if ( empty( $params['options']['ssl']['peer_certificate'] ) ) {
			set_transient( $cache_key, $default, 2 * HOUR_IN_SECONDS );
			return $default;
		}

		$cert = openssl_x509_parse( $params['options']['ssl']['peer_certificate'] );
		if ( ! is_array( $cert ) ) {
			set_transient( $cache_key, $default, 2 * HOUR_IN_SECONDS );
			return $default;
		}

		$issuer    = self::format_certificate_name( isset( $cert['issuer'] ) ? $cert['issuer'] : array() );
		$expires   = isset( $cert['validTo_time_t'] ) ? (int) $cert['validTo_time_t'] : 0;
		$days_left = $expires > 0 ? (int) floor( ( $expires - time() ) / DAY_IN_SECONDS ) : null;
		$matches   = self::certificate_matches_domain( $cert, $domain );

		$status_key = 'success';
		$status     = esc_html__( 'Valid', 'server-info' );
		$message    = esc_html__( 'Certificate is active for this domain.', 'server-info' );

		if ( null === $days_left ) {
			$status_key = 'neutral';
			$status     = esc_html__( 'Unknown', 'server-info' );
			$message    = esc_html__( 'Certificate expiry date is not available.', 'server-info' );
		} elseif ( $days_left < 0 ) {
			$status_key = 'danger';
			$status     = esc_html__( 'Expired', 'server-info' );
			$message    = esc_html__( 'Certificate has expired.', 'server-info' );
		} elseif ( $days_left <= 30 ) {
			$status_key = 'warning';
			$status     = esc_html__( 'Expiring Soon', 'server-info' );
			$message    = esc_html__( 'Certificate expires within 30 days.', 'server-info' );
		}

		if ( ! $matches ) {
			$status_key = 'danger';
			$status     = esc_html__( 'Domain Mismatch', 'server-info' );
			$message    = esc_html__( 'Certificate does not appear to match the site domain.', 'server-info' );
		}

		$result = array(
			'available'  => true,
			'status'     => $status,
			'message'    => $message,
			'issuer'     => $issuer ? $issuer : esc_html__( 'Unknown issuer', 'server-info' ),
			'domain'     => $domain,
			'expiry'     => $expires > 0 ? date_i18n( get_option( 'date_format' ), $expires ) : esc_html__( 'Unavailable', 'server-info' ),
			'days_left'  => $days_left,
			'status_key' => $status_key,
		);

		set_transient( $cache_key, $result, 12 * HOUR_IN_SECONDS );

		return $result;
	}

	/**
	 * Formats certificate subject/issuer arrays.
	 *
	 * @param array $parts Certificate name parts.
	 * @return string
	 */
	public static function format_certificate_name( $parts ) {
		if ( ! is_array( $parts ) || empty( $parts ) ) {
			return '';
		}

		foreach ( array( 'CN', 'O', 'OU' ) as $key ) {
			if ( ! empty( $parts[ $key ] ) ) {
				return is_array( $parts[ $key ] ) ? implode( ', ', array_map( 'sanitize_text_field', $parts[ $key ] ) ) : sanitize_text_field( $parts[ $key ] );
			}
		}

		return '';
	}

	/**
	 * Checks certificate common name/SAN coverage.
	 *
	 * @param array  $cert   Parsed certificate.
	 * @param string $domain Domain name.
	 * @return bool
	 */
	public static function certificate_matches_domain( $cert, $domain ) {
		$names = array();

		if ( ! empty( $cert['subject']['CN'] ) ) {
			$names[] = $cert['subject']['CN'];
		}

		if ( ! empty( $cert['extensions']['subjectAltName'] ) ) {
			$alt_names = explode( ',', $cert['extensions']['subjectAltName'] );
			foreach ( $alt_names as $alt_name ) {
				$alt_name = trim( $alt_name );
				if ( 0 === stripos( $alt_name, 'DNS:' ) ) {
					$names[] = substr( $alt_name, 4 );
				}
			}
		}

		foreach ( $names as $name ) {
			$name = strtolower( trim( $name ) );
			if ( $name === $domain ) {
				return true;
			}

			if ( 0 === strpos( $name, '*.' ) ) {
				$suffix = substr( $name, 1 );
				if ( substr( $domain, -strlen( $suffix ) ) === $suffix && substr_count( $domain, '.' ) === substr_count( $suffix, '.' ) ) {
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * Gets best-effort WHOIS domain expiry details.
	 *
	 * @param string $domain Domain name.
	 * @return array
	 */
	public static function get_domain_expiry_info( $domain ) {
		$default = array(
			'available'  => false,
			'status'     => esc_html__( 'Unavailable', 'server-info' ),
			'message'    => esc_html__( 'Domain expiry could not be determined.', 'server-info' ),
			'registrar'  => esc_html__( 'Unavailable', 'server-info' ),
			'domain'     => $domain,
			'expiry'     => esc_html__( 'Unavailable', 'server-info' ),
			'days_left'  => null,
			'status_key' => 'neutral',
		);

		if ( ! self::is_domain_expiry_lookup_enabled() ) {
			$default['message'] = esc_html__( 'Domain expiry lookup is disabled in Server Info settings.', 'server-info' );
			return $default;
		}

		if ( ! self::is_public_domain( $domain ) ) {
			$default['message'] = esc_html__( 'Domain expiry lookup is skipped for local, test, or IP-based domains.', 'server-info' );
			return $default;
		}

		$cache_key = 'si_domain_expiry_' . md5( $domain );
		$cached    = get_transient( $cache_key );
		if ( is_array( $cached ) ) {
			return $cached;
		}

		$parsed = self::query_rdap_domain( $domain );

		if ( empty( $parsed['expiry_timestamp'] ) ) {
			$whois_server = self::get_whois_server_for_domain( $domain );
			if ( empty( $whois_server ) ) {
				$default['message'] = esc_html__( 'No RDAP or WHOIS expiry data is available for this domain extension.', 'server-info' );
				set_transient( $cache_key, $default, 12 * HOUR_IN_SECONDS );
				return $default;
			}

			$raw_whois = self::query_whois( $whois_server, $domain );
			if ( empty( $raw_whois ) ) {
				$default['message'] = esc_html__( 'Domain lookup timed out or returned no data.', 'server-info' );
				set_transient( $cache_key, $default, 6 * HOUR_IN_SECONDS );
				return $default;
			}

			$parsed = self::parse_whois_expiry( $raw_whois );
		}

		if ( empty( $parsed['expiry_timestamp'] ) ) {
			$default['message'] = esc_html__( 'Domain lookup did not include a recognizable expiry date.', 'server-info' );
			set_transient( $cache_key, $default, 12 * HOUR_IN_SECONDS );
			return $default;
		}

		$days_left  = (int) floor( ( $parsed['expiry_timestamp'] - time() ) / DAY_IN_SECONDS );
		$status_key = 'success';
		$status     = esc_html__( 'Active', 'server-info' );
		$message    = esc_html__( 'Domain registration appears active.', 'server-info' );

		if ( $days_left < 0 ) {
			$status_key = 'danger';
			$status     = esc_html__( 'Expired', 'server-info' );
			$message    = esc_html__( 'Domain registration appears expired.', 'server-info' );
		} elseif ( $days_left <= 30 ) {
			$status_key = 'warning';
			$status     = esc_html__( 'Expiring Soon', 'server-info' );
			$message    = esc_html__( 'Domain expires within 30 days.', 'server-info' );
		}

		$result = array(
			'available'  => true,
			'status'     => $status,
			'message'    => $message,
			'registrar'  => ! empty( $parsed['registrar'] ) ? $parsed['registrar'] : esc_html__( 'Unknown registrar', 'server-info' ),
			'domain'     => $domain,
			'expiry'     => date_i18n( get_option( 'date_format' ), $parsed['expiry_timestamp'] ),
			'days_left'  => $days_left,
			'status_key' => $status_key,
		);

		set_transient( $cache_key, $result, DAY_IN_SECONDS );

		return $result;
	}

	/**
	 * Queries RDAP for domain registration metadata.
	 *
	 * @param string $domain Domain name.
	 * @return array
	 */
	public static function query_rdap_domain( $domain ) {
		$urls = self::get_rdap_urls_for_domain( $domain );

		foreach ( $urls as $url ) {
			$response = wp_remote_get(
				$url,
				array(
					'timeout'     => 5,
					'redirection' => 3,
					'user-agent'  => 'Server Info/' . SERVER_INFO_PLUGIN_VERSION . '; ' . home_url( '/' ),
				)
			);

			if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
				continue;
			}

			$body = json_decode( wp_remote_retrieve_body( $response ), true );
			if ( ! is_array( $body ) ) {
				continue;
			}

			$parsed = self::parse_rdap_expiry( $body );
			if ( ! empty( $parsed['expiry_timestamp'] ) ) {
				return $parsed;
			}
		}

		return array();
	}

	/**
	 * Gets RDAP endpoint candidates for a domain.
	 *
	 * @param string $domain Domain name.
	 * @return array
	 */
	public static function get_rdap_urls_for_domain( $domain ) {
		$parts = explode( '.', $domain );
		$tld   = end( $parts );

		$direct = array(
			'com' => 'https://rdap.verisign.com/com/v1/domain/',
			'net' => 'https://rdap.verisign.com/net/v1/domain/',
			'org' => 'https://rdap.publicinterestregistry.org/rdap/domain/',
		);

		$urls = array();
		if ( isset( $direct[ $tld ] ) ) {
			$urls[] = $direct[ $tld ] . rawurlencode( $domain );
		}

		$urls[] = 'https://rdap.org/domain/' . rawurlencode( $domain );

		return $urls;
	}

	/**
	 * Parses registrar and expiry values from RDAP data.
	 *
	 * @param array $data RDAP response.
	 * @return array
	 */
	public static function parse_rdap_expiry( $data ) {
		$expiry_timestamp = 0;
		if ( ! empty( $data['events'] ) && is_array( $data['events'] ) ) {
			foreach ( $data['events'] as $event ) {
				if ( empty( $event['eventAction'] ) || empty( $event['eventDate'] ) ) {
					continue;
				}

				if ( in_array( strtolower( $event['eventAction'] ), array( 'expiration', 'expiry' ), true ) ) {
					$timestamp = strtotime( $event['eventDate'] );
					if ( false !== $timestamp ) {
						$expiry_timestamp = $timestamp;
						break;
					}
				}
			}
		}

		$registrar = '';
		if ( ! empty( $data['entities'] ) && is_array( $data['entities'] ) ) {
			foreach ( $data['entities'] as $entity ) {
				$roles = ! empty( $entity['roles'] ) && is_array( $entity['roles'] ) ? array_map( 'strtolower', $entity['roles'] ) : array();
				if ( ! in_array( 'registrar', $roles, true ) ) {
					continue;
				}

				$registrar = self::extract_rdap_vcard_name( $entity );
				if ( $registrar ) {
					break;
				}
			}
		}

		return array(
			'expiry_timestamp' => $expiry_timestamp,
			'registrar'        => $registrar,
		);
	}

	/**
	 * Extracts a display name from an RDAP vCard entity.
	 *
	 * @param array $entity RDAP entity.
	 * @return string
	 */
	public static function extract_rdap_vcard_name( $entity ) {
		if ( empty( $entity['vcardArray'][1] ) || ! is_array( $entity['vcardArray'][1] ) ) {
			return '';
		}

		foreach ( $entity['vcardArray'][1] as $item ) {
			if ( is_array( $item ) && isset( $item[0], $item[3] ) && 'fn' === strtolower( $item[0] ) ) {
				return sanitize_text_field( $item[3] );
			}
		}

		return '';
	}

	/**
	 * Maps common TLDs to WHOIS servers.
	 *
	 * @param string $domain Domain name.
	 * @return string
	 */
	public static function get_whois_server_for_domain( $domain ) {
		$parts = explode( '.', $domain );
		$tld   = end( $parts );

		$servers = array(
			'com' => 'whois.verisign-grs.com',
			'net' => 'whois.verisign-grs.com',
			'org' => 'whois.pir.org',
			'info' => 'whois.afilias.net',
			'biz' => 'whois.biz',
			'us' => 'whois.nic.us',
			'co' => 'whois.nic.co',
			'io' => 'whois.nic.io',
			'me' => 'whois.nic.me',
			'tv' => 'whois.nic.tv',
			'dev' => 'whois.nic.google',
			'app' => 'whois.nic.google',
			'uk' => 'whois.nic.uk',
			'ca' => 'whois.cira.ca',
			'au' => 'whois.auda.org.au',
			'pk' => 'whois.pknic.net.pk',
		);

		return isset( $servers[ $tld ] ) ? $servers[ $tld ] : '';
	}

	/**
	 * Queries a WHOIS server with a short timeout.
	 *
	 * @param string $server WHOIS server.
	 * @param string $domain Domain name.
	 * @return string
	 */
	public static function query_whois( $server, $domain ) {
		if ( ! function_exists( 'fsockopen' ) ) {
			return '';
		}

			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fsockopen -- WHOIS is a TCP protocol and cannot use WP_Filesystem.
			$connection = @fsockopen( $server, 43, $error_code, $error_message, 4 );
		if ( ! $connection ) {
			return '';
		}

		stream_set_timeout( $connection, 4 );
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite -- Writing a WHOIS query to a network socket.
			fwrite( $connection, $domain . "\r\n" );

		$response = '';
		while ( ! feof( $connection ) ) {
			$response .= fgets( $connection, 1024 );
			if ( strlen( $response ) > 20000 ) {
				break;
			}
		}
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- This closes a WHOIS network socket.
			fclose( $connection );

		return $response;
	}

	/**
	 * Parses registrar and expiry values from a WHOIS response.
	 *
	 * @param string $response WHOIS response.
	 * @return array
	 */
	public static function parse_whois_expiry( $response ) {
		$expiry_patterns = array(
			'/Registry Expiry Date:\s*(.+)/i',
			'/Registrar Registration Expiration Date:\s*(.+)/i',
			'/Expiration Date:\s*(.+)/i',
			'/Expiry Date:\s*(.+)/i',
			'/Renewal Date:\s*(.+)/i',
			'/paid-till:\s*(.+)/i',
			'/expires:\s*(.+)/i',
		);

		$registrar_patterns = array(
			'/Registrar:\s*(.+)/i',
			'/Sponsoring Registrar:\s*(.+)/i',
			'/registrar:\s*(.+)/i',
		);

		$expiry_timestamp = 0;
		foreach ( $expiry_patterns as $pattern ) {
			if ( preg_match( $pattern, $response, $matches ) ) {
				$date = trim( $matches[1] );
				$date = preg_replace( '/\s+\(.+\)$/', '', $date );
				$timestamp = strtotime( $date );
				if ( false !== $timestamp ) {
					$expiry_timestamp = $timestamp;
					break;
				}
			}
		}

		$registrar = '';
		foreach ( $registrar_patterns as $pattern ) {
			if ( preg_match( $pattern, $response, $matches ) ) {
				$registrar = sanitize_text_field( trim( $matches[1] ) );
				break;
			}
		}

		return array(
			'expiry_timestamp' => $expiry_timestamp,
			'registrar'        => $registrar,
		);
	}

	/**
	 * Registers the dashboard widget.
	 *
	 * @since 0.0.1
	 * @access public
	 *
	 * @return void
	 */
	public function add_dashboard_widgets() {
		wp_add_dashboard_widget(
			'serverinfo_dashboard_widget',
			esc_html__( 'Server Info', 'server-info' ),
			array( 'Server_Info', 'display_dashboard_widget' )
		);
	}

	/**
	 * Renders the dashboard widget content.
	 *
	 * @since 0.0.1
	 * @access public
	 *
	 * @return void
	 */
	public static function display_dashboard_widget() {
		$info = self::site_info();
		?>
        <div class="si-card" style="box-shadow: none; padding: 0; background: transparent;">
            <ul class="si-list">
				<?php
				$fields_to_show = array( 'operating_system', 'server_ip', 'server_hostname', 'php_version' );
				foreach ( $fields_to_show as $key ) {
					if ( isset( $info['wp-server']['fields'][ $key ] ) ) {
						$field = $info['wp-server']['fields'][ $key ];
						?>
                        <li class="si-list-item">
                            <span class="si-item-label"><?php echo esc_html( $field['label'] ); ?></span>
                            <span class="si-item-value"><?php echo esc_html( $field['value'] ); ?></span>
                        </li>
						<?php
					}
				}
				?>
            </ul>
            <div style="margin-top: 15px;">
                <a class="si-btn" style="width: 100%; text-align: center; display: block;" href="<?php echo esc_url( admin_url( 'options-general.php?page=server_info_display' ) ); ?>"><?php esc_html_e( 'View More Information', 'server-info' ); ?></a>
            </div>
        </div>
		<?php
	}

	/**
	 * Gathers and structures all server and WordPress information.
	 *
	 * @since 0.0.1
	 * @access public
	 *
	 * @return array Multi-dimensional array containing server data.
	 */
	public static function site_info() {
		global $wpdb;

		// Set up the array that holds all information.
		$info = array();

		$info['wp-server'] = array(
			'label'  => esc_html__( 'Hosting Server Information', 'server-info' ),
			'fields' => array(),
		);

		if ( function_exists( 'phpversion' ) ) {
			$php_version_debug = phpversion();
			// Whether PHP supports 64-bit.
			$php64bit = ( 64 === PHP_INT_SIZE * 8 );

			$php_version = $php_version_debug;
		} else {
			$php_version = esc_html__( 'Unable to determine PHP version', 'server-info' );
		}

		if ( function_exists( 'php_uname' ) ) {
			$server_architecture = sprintf( '%s %s %s', php_uname( 's' ), php_uname( 'r' ), php_uname( 'm' ) );
			$os_family = php_uname( 's' );
			
			// Extract specific Linux distribution based on community feedback
			if ( 'Linux' === $os_family ) {
				if ( @is_readable( '/etc/os-release' ) ) {
					$os_release = @parse_ini_file( '/etc/os-release' );
					if ( ! empty( $os_release['PRETTY_NAME'] ) ) {
						$server_architecture .= ' (' . trim( $os_release['PRETTY_NAME'], '"\'' ) . ')';
					}
				} elseif ( @is_readable( '/etc/issue' ) ) {
					$issue = @file_get_contents( '/etc/issue' );
					if ( $issue ) {
						$parts = explode( '\\', $issue );
						if ( ! empty( $parts[0] ) ) {
							$server_architecture .= ' (' . trim( $parts[0] ) . ')';
						}
					}
				}
			}
		} else {
			$server_architecture = 'unknown';
		}
		$info['wp-server']['fields']['operating_system'] = array(
			'label' => esc_html__( 'Operating System', 'server-info' ),
			'value' => ( 'unknown' !== $server_architecture ? $server_architecture : esc_html__( 'Unable to determine server architecture', 'server-info' ) )
		);

		if ( function_exists( 'php_uname' ) ) {
			$info['wp-server']['fields']['server_hostname'] = array(
				'label' => esc_html__( 'Server Hostname', 'server-info' ),
				'value' => php_uname( 'n' )
			);
		}

		$server_ip = self::get_server_value( 'SERVER_ADDR' );
		if ( '' !== $server_ip ) {
			$info['wp-server']['fields']['server_ip'] = array(
				'label' => esc_html__( 'Server IP', 'server-info' ),
				'value' => $server_ip,
			);
		}

		$server_protocol = self::get_server_value( 'SERVER_PROTOCOL' );
		if ( '' !== $server_protocol ) {
			$info['wp-server']['fields']['server_protocol'] = array(
				'label' => esc_html__( 'Server Protocol', 'server-info' ),
				'value' => $server_protocol,
			);
		}

		$server_admin = self::get_server_value( 'SERVER_ADMIN' );
		if ( '' !== $server_admin ) {
			$info['wp-server']['fields']['server_administrator'] = array(
				'label' => esc_html__( 'Server Administrator', 'server-info' ),
				'value' => $server_admin,
			);
		}

		$server_port = self::get_server_value( 'SERVER_PORT' );
		if ( '' !== $server_port ) {
			$info['wp-server']['fields']['server_web_port'] = array(
				'label' => esc_html__( 'Server Web Port', 'server-info' ),
				'value' => $server_port,
			);
		}

		$uptime = '';
		$disable_functions = ini_get( 'disable_functions' );
		if ( function_exists( 'exec' ) && is_callable( 'exec' ) && ( ! is_string( $disable_functions ) || false === stripos( $disable_functions, 'exec' ) ) ) {
			$uptime = @exec( "uptime" );
		}
		if ( ! empty( $uptime ) ) {
			$info['wp-server']['fields']['system_uptime'] = array(
				'label' => esc_html__( 'System Uptime', 'server-info' ),
				'value' => esc_html( $uptime )
			);
		}

		if ( function_exists( 'sys_getloadavg' ) ) {
			$load = sys_getloadavg();
			if ( ! empty( $load ) ) {
				$info['wp-server']['fields']['load_average'] = array(
					'label' => esc_html__( 'Load Average', 'server-info' ),
					'value' => implode( ', ', $load )
				);
			}
		}

		if ( function_exists( 'memory_get_usage' ) ) {
			$info['wp-server']['fields']['memory_usage'] = array(
				'label' => esc_html__( 'PHP Memory Usage', 'server-info' ),
				'value' => number_format( memory_get_usage( true ) / 1048576, 2 ) . ' MB'
			);
		}

		$extensions = array( 'curl', 'mbstring', 'gd', 'imagick', 'zip', 'redis', 'memcached', 'opcache' );
		$active_exts = array();
		foreach ( $extensions as $ext ) {
			if ( extension_loaded( $ext ) ) {
				$active_exts[] = $ext;
			}
		}
		if ( ! empty( $active_exts ) ) {
			$info['wp-server']['fields']['active_extensions'] = array(
				'label' => esc_html__( 'Active PHP Extensions', 'server-info' ),
				'value' => implode( ', ', $active_exts )
			);
		}

		$wp_config_path = ABSPATH . 'wp-config.php';
		if ( file_exists( $wp_config_path ) ) {
			$info['wp-server']['fields']['wp_config_perms'] = array(
				'label' => esc_html__( 'wp-config.php Permissions', 'server-info' ),
				'value' => substr( sprintf( '%o', fileperms( $wp_config_path ) ), -4 )
			);
		}

		$upload_dir = wp_upload_dir();
		$uploads_path = $upload_dir['basedir'];
		$content_path = WP_CONTENT_DIR;

		if ( file_exists( $content_path ) ) {
			$info['wp-server']['fields']['wp_content_perms'] = array(
				'label' => esc_html__( 'wp-content Permissions', 'server-info' ),
				'value' => substr( sprintf( '%o', fileperms( $content_path ) ), -4 ),
			);
		}

		if ( file_exists( $uploads_path ) ) {
			$info['wp-server']['fields']['uploads_perms'] = array(
				'label' => esc_html__( 'Uploads Directory Permissions', 'server-info' ),
				'value' => substr( sprintf( '%o', fileperms( $uploads_path ) ), -4 ),
			);
		}

		$info['wp-server']['fields']['httpd_software'] = array(
			'label' => esc_html__( 'Web server', 'server-info' ),
			'value' => self::get_server_value( 'SERVER_SOFTWARE', esc_html__( 'Unable to determine what web server software is used', 'server-info' ) ),
		);

		$info['wp-server']['fields']['php_version'] = array(
			'label' => esc_html__( 'PHP version', 'server-info' ),
			'value' => $php_version,
		);

		// Some servers disable `ini_set()` and `ini_get()`, we check this before trying to get configuration values.
		if ( function_exists( 'ini_get' ) ) {
			$info['wp-server']['fields']['memory_limit'] = array(
				'label' => esc_html__( 'PHP memory limit', 'server-info' ),
				'value' => ini_get( 'memory_limit' ),
			);
		}

		$info['wp-server']['fields']['server_timezone'] = array(
			'label' => esc_html__( 'Server Timezone', 'server-info' ),
			'value' => date_default_timezone_get(),
		);

		$gateway_interface = self::get_server_value( 'GATEWAY_INTERFACE' );
		if ( '' !== $gateway_interface ) {
			$info['wp-server']['fields']['CGI_version'] = array(
				'label' => esc_html__( 'CGI Version', 'server-info' ),
				'value' => $gateway_interface,
			);
		}

		/**
		 * Database
		 */
		$info['wp-database'] = array(
			'label'  => esc_html__( 'Database', 'server-info' ),
			'fields' => array(),
		);

		// Populate the database fields.
		if ( is_resource( $wpdb->dbh ) ) {
			// Old mysql extension.
			$extension = 'mysql';
		} else if ( is_object( $wpdb->dbh ) ) {
			// mysqli or PDO.
			$extension = get_class( $wpdb->dbh );
		} else {
			// Unknown sql extension.
			$extension = null;
		}

		$server = $wpdb->db_version();

		if ( isset( $wpdb->use_mysqli ) && $wpdb->use_mysqli && isset( $wpdb->dbh->client_info ) ) {
			$client_version = $wpdb->dbh->client_info;
		} else {
			$client_version = null;
		}

		$info['wp-database']['fields']['extension'] = array(
			'label' => esc_html__( 'Extension', 'server-info' ),
			'value' => $extension,
		);

		$info['wp-database']['fields']['server_version'] = array(
			'label' => esc_html__( 'Server version', 'server-info' ),
			'value' => $server,
		);

		$info['wp-database']['fields']['client_version'] = array(
			'label' => esc_html__( 'Client version', 'server-info' ),
			'value' => $client_version,
		);

		$info['wp-database']['fields']['database_user'] = array(
			'label'   => esc_html__( 'Database username', 'server-info' ),
			'value'   => $wpdb->dbuser,
			'private' => true,
		);

		$info['wp-database']['fields']['database_host'] = array(
			'label'   => esc_html__( 'Database host', 'server-info' ),
			'value'   => $wpdb->dbhost,
			'private' => true,
		);

		$info['wp-database']['fields']['database_name'] = array(
			'label'   => esc_html__( 'Database name', 'server-info' ),
			'value'   => $wpdb->dbname,
			'private' => true,
		);

		$db_size_query = $wpdb->prepare(
			'SELECT SUM(data_length + index_length) FROM information_schema.TABLES WHERE table_schema = %s',
			$wpdb->dbname
		);
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared -- The query is prepared immediately above; live diagnostic data should not be cached.
		$db_size = $wpdb->get_var( $db_size_query );
		if ( $db_size ) {
			$info['wp-database']['fields']['database_size'] = array(
				'label' => esc_html__( 'Total Database size', 'server-info' ),
				'value' => number_format( $db_size / 1048576, 2 ) . ' MB',
			);

			// Top 5 largest tables
			$top_tables_query = $wpdb->prepare( "
				SELECT table_name AS name, 
				       round(((data_length + index_length) / 1024 / 1024), 2) AS size_mb 
				FROM information_schema.TABLES 
				WHERE table_schema = %s
				ORDER BY (data_length + index_length) DESC 
				LIMIT 5
			", $wpdb->dbname );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared -- The query is prepared immediately above; live diagnostic data should not be cached.
			$top_tables = $wpdb->get_results( $top_tables_query );

			if ( ! empty( $top_tables ) ) {
				$tables_html = array();
				foreach ( $top_tables as $tbl ) {
					$tables_html[ $tbl->name ] = $tbl->size_mb . ' MB';
				}
				$info['wp-database']['fields']['top_tables'] = array(
					'label' => esc_html__( 'Top 5 Largest Tables', 'server-info' ),
					'value' => $tables_html,
				);
			}
		}

		$info['wp-database']['fields']['database_prefix'] = array(
			'label'   => esc_html__( 'Table prefix', 'server-info' ),
			'value'   => $wpdb->prefix,
			'private' => true,
		);

		$info['wp-database']['fields']['database_charset'] = array(
			'label'   => esc_html__( 'Database charset', 'server-info' ),
			'value'   => $wpdb->charset,
			'private' => true,
		);

		$info['wp-database']['fields']['database_collate'] = array(
			'label'   => esc_html__( 'Database collation', 'server-info' ),
			'value'   => $wpdb->collate,
			'private' => true,
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Live server variables should not be cached by the object cache.
		$max_connections = $wpdb->get_var( "SHOW VARIABLES LIKE 'max_connections'", 1 );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Live server variables should not be cached by the object cache.
		$max_allowed_packet = $wpdb->get_var( "SHOW VARIABLES LIKE 'max_allowed_packet'", 1 );

		if ( $max_connections ) {
			$info['wp-database']['fields']['max_connections'] = array(
				'label' => esc_html__( 'Max Connections', 'server-info' ),
				'value' => $max_connections,
			);
		}

		if ( $max_allowed_packet ) {
			$info['wp-database']['fields']['max_allowed_packet'] = array(
				'label' => esc_html__( 'Max Allowed Packet', 'server-info' ),
				'value' => size_format( $max_allowed_packet ),
			);
		}

		/**
		 * Caching & Performance
		 */
		$info['wp-caching'] = array(
			'label'  => esc_html__( 'Caching & Performance', 'server-info' ),
			'fields' => array(),
		);

		$object_cache_file = WP_CONTENT_DIR . '/object-cache.php';
		$info['wp-caching']['fields']['object_cache'] = array(
			'label' => esc_html__( 'Object Cache Drop-in', 'server-info' ),
			'value' => file_exists( $object_cache_file ) ? esc_html__( 'Active', 'server-info' ) : esc_html__( 'Inactive', 'server-info' ),
		);

		if ( function_exists( 'opcache_get_status' ) ) {
			// Suppress warnings in case OPcache is restricted by config
			$opcache = @opcache_get_status( false );
			if ( is_array( $opcache ) && ! empty( $opcache['opcache_enabled'] ) ) {
				$hit_rate = 0;
				if ( isset( $opcache['opcache_statistics']['opcache_hit_rate'] ) ) {
					$hit_rate = round( $opcache['opcache_statistics']['opcache_hit_rate'], 2 );
				}
				$info['wp-caching']['fields']['opcache_status'] = array(
					'label' => esc_html__( 'OPcache Status', 'server-info' ),
					/* translators: %s: OPcache hit-rate percentage. */
					'value' => sprintf( esc_html__( 'Enabled (Hit Rate: %s%%)', 'server-info' ), $hit_rate ),
				);
			} else {
				$info['wp-caching']['fields']['opcache_status'] = array(
					'label' => esc_html__( 'OPcache Status', 'server-info' ),
					'value' => esc_html__( 'Disabled or Restricted', 'server-info' ),
				);
			}
		}

		/**
		 * WordPress Information
		 */
		$is_multisite    = is_multisite();
		$info['wp-info'] = array(
			'label'  => esc_html__( 'WordPress Information', 'server-info' ),
			'fields' => array(
				'multisite' => array(
					'label' => esc_html__( 'Is this a multisite?', 'server-info' ),
					'value' => $is_multisite ? esc_html__( 'Yes', 'server-info' ) : esc_html__( 'No', 'server-info' ),
				),
			),
		);

		if ( is_multisite() ) {
			$network_query = new WP_Network_Query();
			$network_ids   = $network_query->query(
				array(
					'fields'        => 'ids',
					'number'        => 100,
					'no_found_rows' => false,
				)
			);

			$site_count = 0;
			foreach ( $network_ids as $network_id ) {
				$site_count += get_blog_count( $network_id );
			}

			$info['wp-info']['fields']['user_count'] = array(
				'label' => esc_html__( 'User count', 'server-info' ),
				'value' => get_user_count(),
			);

			$info['wp-info']['fields']['site_count'] = array(
				'label' => esc_html__( 'Site count', 'server-info' ),
				'value' => $site_count,
			);

			$info['wp-info']['fields']['network_count'] = array(
				'label' => esc_html__( 'Network count', 'server-info' ),
				'value' => $network_query->found_networks,
			);
		} else {
			$user_count = count_users();

			$info['wp-info']['fields']['user_count'] = array(
				'label' => esc_html__( 'User count', 'server-info' ),
				'value' => $user_count['total_users'],
			);
		}

		$active_theme              = wp_get_theme();
		$info['wp-info']['fields'] = array(
			'name' => array(
				'label' => esc_html__( 'Active Theme', 'server-info' ),
				'value' => sprintf(
					/* translators: 1: Theme name, 2: Theme stylesheet directory. */
					esc_html__( '%1$s (%2$s)', 'server-info' ),
					$active_theme->name,
					$active_theme->stylesheet
				),
			),
		);

		// List all available plugins.
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		$plugins          = get_plugins();
		$plugins_active   = array();
		$plugins_inactive = array();

		foreach ( $plugins as $plugin_path => $plugin ) {
			$plugin_author = $plugin['Author'];

			if ( ! empty( $plugin_author ) ) {
				/* translators: %s: Plugin author name. */
				$plugin_author = sprintf( esc_html__( 'By %s', 'server-info' ), $plugin_author );
			} else {
				$plugin_author = '';
			}

			if ( is_plugin_active( $plugin_path ) ) {
				$plugins_active[ $plugin['Name'] ] = $plugin_author;
			} else {
				$plugins_inactive[ $plugin['Name'] ] = $plugin_author;
			}
		}

		if ( empty( $plugins_active ) ) {
			$plugins_active = esc_html__( 'None', 'server-info' );
		}
		$info['wp-info']['fields']['plugins_active'] = array(
			'label' => esc_html__( 'Active Plugins', 'server-info' ),
			'value' => $plugins_active,
		);

		if ( empty( $plugins_inactive ) ) {
			$plugins_inactive = esc_html__( 'None', 'server-info' );
		}
		$info['wp-info']['fields']['plugins_inactive'] = array(
			'label' => esc_html__( 'Inactive Plugins', 'server-info' ),
			'value' => $plugins_inactive,
		);

		$info['wp-info']['fields']['WP_MEMORY_LIMIT'] = array(
			'label' => esc_html__( 'WordPress Memory Limit', 'server-info' ),
			'value' => WP_MEMORY_LIMIT,
		);

		$info['wp-info']['fields']['WP_MAX_MEMORY_LIMIT'] = array(
			'label' => esc_html__( 'WordPress Max Memory Limit', 'server-info' ),
			'value' => WP_MAX_MEMORY_LIMIT,
		);

		$info['wp-info']['fields']['WP_DEBUG'] = array(
			'label' => esc_html__( 'WordPress Debugging', 'server-info' ),
			'value' => WP_DEBUG ? esc_html__( 'Enabled', 'server-info' ) : esc_html__( 'Disabled', 'server-info' )
		);

		$cron_status = defined('DISABLE_WP_CRON') && DISABLE_WP_CRON ? esc_html__( 'Disabled via wp-config.php', 'server-info' ) : esc_html__( 'Enabled', 'server-info' );
		$info['wp-info']['fields']['cron_status'] = array(
			'label' => esc_html__( 'WP-Cron Status', 'server-info' ),
			'value' => $cron_status,
		);

		$cron_array = _get_cron_array();
		if ( ! empty( $cron_array ) ) {
			$upcoming_crons = array();
			$count = 0;
			foreach ( $cron_array as $timestamp => $cron_hooks ) {
				foreach ( $cron_hooks as $hook => $keys ) {
					if ( $count >= 3 ) break 2;
					$time_diff = human_time_diff( current_time( 'timestamp' ), $timestamp );
					/* translators: %s: Human-readable time until a cron event runs. */
					$upcoming_crons[ $hook ] = sprintf( esc_html__( 'In %s', 'server-info' ), $time_diff );
					$count++;
				}
			}
			if ( ! empty( $upcoming_crons ) ) {
				$info['wp-info']['fields']['upcoming_crons'] = array(
					'label' => esc_html__( 'Next 3 Cron Events', 'server-info' ),
					'value' => $upcoming_crons,
				);
			}
		}

		return $info;
	}

	/**
	 * Renders the main plugin settings page with tabs.
	 *
	 * @since 0.0.1
	 * @access public
	 *
	 * @return void
	 */
	 public static function display_server_info() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- The tab parameter only selects a read-only admin view and does not change data.
		$active_tab = isset( $_GET['tab'] ) ? sanitize_text_field( wp_unslash( $_GET['tab'] ) ) : 'server';
		$allowed_tabs = array( 'server', 'database', 'wordpress', 'phpinfo', 'caching', 'diagnostics', 'more_plugins', 'settings', 'support' );
		if ( ! in_array( $active_tab, $allowed_tabs, true ) ) {
			$active_tab = 'server';
		}

		$page_titles = array(
			'server'       => array(
				'title'    => esc_html__( 'Overview', 'server-info' ),
				'subtitle' => esc_html__( 'Real-time summary of your server health and configuration.', 'server-info' ),
			),
			'database'     => array(
				'title'    => esc_html__( 'Database', 'server-info' ),
				'subtitle' => esc_html__( 'MySQL and database limits that affect performance and stability.', 'server-info' ),
			),
			'wordpress'    => array(
				'title'    => esc_html__( 'WordPress Core', 'server-info' ),
				'subtitle' => esc_html__( 'Important WordPress configuration, theme, plugin, and cron details.', 'server-info' ),
			),
			'phpinfo'      => array(
				'title'    => esc_html__( 'PHP Information', 'server-info' ),
				'subtitle' => esc_html__( 'Detailed PHP runtime configuration for debugging hosting issues.', 'server-info' ),
			),
			'caching'      => array(
				'title'    => esc_html__( 'Caching', 'server-info' ),
				'subtitle' => esc_html__( 'Caching and optimization signals from your hosting environment.', 'server-info' ),
			),
			'diagnostics'  => array(
				'title'    => esc_html__( 'Diagnostics & Logs', 'server-info' ),
				'subtitle' => esc_html__( 'Health checks and debug-log visibility for administrators.', 'server-info' ),
			),
			'more_plugins' => array(
				'title'    => esc_html__( 'More Plugins', 'server-info' ),
				'subtitle' => esc_html__( 'Other lightweight WordPress tools from the same author.', 'server-info' ),
			),
			'settings'     => array(
				'title'    => esc_html__( 'Settings', 'server-info' ),
				'subtitle' => esc_html__( 'Control where Server Info appears in the WordPress admin.', 'server-info' ),
			),
			'support'      => array(
				'title'    => esc_html__( 'Support Development', 'server-info' ),
				'subtitle' => esc_html__( 'Help keep Server Info maintained, tested, and free for everyone.', 'server-info' ),
			),
		);

		$page_title    = $page_titles[ $active_tab ]['title'];
		$page_subtitle = $page_titles[ $active_tab ]['subtitle'];
		$info = self::site_info();
		
		// Helper vars for top KPIs
		$php_version = phpversion();
		$memory_usage = 'N/A';
		if ( function_exists('memory_get_usage') ) {
			$memory_usage = round(memory_get_usage(true) / 1024 / 1024, 2) . ' MB';
		}
		
		global $wp_version, $wpdb;
		$db_version_raw = (string) $wpdb->db_version();
		$db_type = stripos( $db_version_raw, 'MariaDB' ) !== false ? 'MariaDB' : 'MySQL';
		preg_match( '/[0-9]+(?:\.[0-9]+)*/', $db_version_raw, $matches );
		$db_version = isset( $matches[0] ) ? $matches[0] : esc_html__( 'Unavailable', 'server-info' );
		
		$web_server = self::get_server_value( 'SERVER_SOFTWARE', esc_html__( 'Unknown', 'server-info' ) );
		if (strlen($web_server) > 15) {
			$web_server = substr($web_server, 0, 15) . '...';
		}
		
		// CPU Load
		$cpu_load = 'N/A';
		$cpu_pct = 0;
		if ( function_exists('sys_getloadavg') ) {
			$load = sys_getloadavg();
			if ( is_array($load) ) {
				$cpu_load = round($load[0], 2);
				$cpu_pct = min(100, round(($load[0] / 4) * 100)); // Rough estimate
			}
		}
		
		// Memory Usage (PHP)
		$mem_used_bytes = memory_get_usage(true);
		$mem_limit_str = ini_get('memory_limit');
		$mem_limit_bytes = wp_convert_hr_to_bytes($mem_limit_str);
		if ( $mem_limit_bytes <= 0 ) {
			$mem_limit_bytes = $mem_used_bytes; 
		}
		$mem_pct = min(100, round(($mem_used_bytes / $mem_limit_bytes) * 100));
		
		// Disk Space
		$disk_pct = 0;
		if ( function_exists('disk_total_space') && function_exists('disk_free_space') ) {
			$disk_total = @disk_total_space( ABSPATH );
			$disk_free = @disk_free_space( ABSPATH );
			if ( $disk_total > 0 ) {
				$disk_used = $disk_total - $disk_free;
				$disk_pct = min(100, round(($disk_used / $disk_total) * 100));
			}
		}

		$site_domain        = self::get_site_domain();
		$registered_domain  = self::get_registered_domain( $site_domain );
		$ssl_info           = self::get_ssl_certificate_info( $site_domain );
		$domain_info        = self::get_domain_expiry_info( $registered_domain );

		// Calculate Overall Health Score
		$health_score = 100;
		$health_reasons = array();
		
		// PHP Version check
		$php_v = phpversion();
		$php_status_label = esc_html__( 'Latest', 'server-info' );
		$php_status_class = 'success';
		if ( version_compare( $php_v, '7.4', '<' ) ) {
			$health_score -= 30;
			$health_reasons[] = "Critical: PHP version is very outdated ($php_v).";
			$php_status_label = esc_html__( 'Critical', 'server-info' );
			$php_status_class = 'neutral';
		} elseif ( version_compare( $php_v, '8.3', '<' ) ) {
			$health_score -= 10;
			$health_reasons[] = "Warning: PHP version ($php_v) is below recommended 8.3.";
			$php_status_label = esc_html__( 'Update recommended', 'server-info' );
			$php_status_class = 'neutral';
		}

		// Memory Limit Check
		$mem_limit_int = intval( $mem_limit_str );
		if ( false !== strpos( $mem_limit_str, 'G' ) ) {
			$mem_limit_int *= 1024;
		}
		if ( $mem_limit_int > 0 && $mem_limit_int < 256 ) {
			$health_score -= 10;
			$health_reasons[] = "Warning: Low PHP memory limit ($mem_limit_str).";
		}
		
		// WordPress Core Check
		global $wp_version;
		$core_updates = get_site_transient('update_core');
		$wp_core_status_label = esc_html__( 'Up to date', 'server-info' );
		$wp_core_status_class = 'success';
		if ( isset( $core_updates->updates ) && is_array( $core_updates->updates ) ) {
			foreach ( $core_updates->updates as $update ) {
				if ( $update->response === 'upgrade' ) {
					$health_score -= 10;
					$health_reasons[] = "Warning: WordPress core is outdated.";
					$wp_core_status_label = esc_html__( 'Update available', 'server-info' );
					$wp_core_status_class = 'neutral';
					break;
				}
			}
		}

		// Security Check
		$wp_config_path = ABSPATH . 'wp-config.php';
		if ( file_exists( $wp_config_path ) && wp_is_writable( $wp_config_path ) ) {
			$health_score -= 10;
			$health_reasons[] = "Security: wp-config.php is writable.";
		}
		
		// Resource Checks
		if ( $mem_pct > 90 ) {
			$health_score -= 5;
			$health_reasons[] = "Warning: High memory usage ($mem_pct%).";
		}
		if ( $disk_pct > 90 ) {
			$health_score -= 5;
			$health_reasons[] = "Warning: High disk usage ($disk_pct%).";
		}

		if ( ! empty( $ssl_info['available'] ) && 'danger' === $ssl_info['status_key'] ) {
			$health_score -= 15;
			$health_reasons[] = 'Security: SSL/TLS certificate needs attention.';
		} elseif ( ! empty( $ssl_info['available'] ) && 'warning' === $ssl_info['status_key'] ) {
			$health_score -= 8;
			$health_reasons[] = 'Warning: SSL/TLS certificate expires soon.';
		}

		if ( ! empty( $domain_info['available'] ) && 'danger' === $domain_info['status_key'] ) {
			$health_score -= 15;
			$health_reasons[] = 'Critical: Domain registration appears expired.';
		} elseif ( ! empty( $domain_info['available'] ) && 'warning' === $domain_info['status_key'] ) {
			$health_score -= 8;
			$health_reasons[] = 'Warning: Domain registration expires soon.';
		}
		
		$health_score = max(0, $health_score);
		
		// Determine Status
		if ( $health_score >= 90 ) {
			$health_status = 'Excellent';
			$health_color = '#10b981'; // Green
			$health_bg = '#d1fae5';
			$health_msg = 'Your server is running smoothly.';
		} elseif ( $health_score >= 70 ) {
			$health_status = 'Fair';
			$health_color = '#f59e0b'; // Amber
			$health_bg = '#fef3c7';
			$health_msg = 'Needs attention: ' . (isset($health_reasons[0]) ? $health_reasons[0] : 'Suboptimal settings.');
		} else {
			$health_status = 'Critical';
			$health_color = '#ef4444'; // Red
			$health_bg = '#fee2e2';
			$health_msg = 'Urgent: ' . (isset($health_reasons[0]) ? $health_reasons[0] : 'Multiple severe issues.');
		}
		?>
		<div class="wrap"><h1 style="display:none;"></h1></div>
		<div class="server-info-wrapper">
			
			<div class="si-sidebar">
				<div class="si-brand">
					<div class="si-brand-icon"><span class="dashicons dashicons-networking"></span></div>
					<div class="si-brand-text">
						<h2>Server Info <span class="si-version-tag">v<?php echo esc_html( SERVER_INFO_PLUGIN_VERSION ); ?></span></h2>
						<p class="si-brand-sub">System Health & Diagnostics</p>
					</div>
				</div>

				<div class="si-nav">
					<a href="?page=server_info_display&tab=server" class="si-nav-item <?php echo 'server' === $active_tab ? 'active' : ''; ?>">
						<span class="dashicons dashicons-admin-home"></span> <?php esc_html_e( 'Overview', 'server-info' ); ?>
					</a>
					<a href="?page=server_info_display&tab=database" class="si-nav-item <?php echo 'database' === $active_tab ? 'active' : ''; ?>">
						<span class="dashicons dashicons-database"></span> <?php esc_html_e( 'Database', 'server-info' ); ?>
					</a>
					<a href="?page=server_info_display&tab=wordpress" class="si-nav-item <?php echo 'wordpress' === $active_tab ? 'active' : ''; ?>">
						<span class="dashicons dashicons-wordpress"></span> <?php esc_html_e( 'WordPress Core', 'server-info' ); ?>
					</a>
					<a href="?page=server_info_display&tab=phpinfo" class="si-nav-item <?php echo 'phpinfo' === $active_tab ? 'active' : ''; ?>">
						<span class="dashicons dashicons-editor-code"></span> <?php esc_html_e( 'PHP Information', 'server-info' ); ?>
					</a>
					<a href="?page=server_info_display&tab=caching" class="si-nav-item <?php echo 'caching' === $active_tab ? 'active' : ''; ?>">
						<span class="dashicons dashicons-dashboard"></span> <?php esc_html_e( 'Caching', 'server-info' ); ?>
					</a>
					<a href="?page=server_info_display&tab=diagnostics" class="si-nav-item <?php echo 'diagnostics' === $active_tab ? 'active' : ''; ?>">
						<span class="dashicons dashicons-admin-tools"></span> <?php esc_html_e( 'Diagnostics & Logs', 'server-info' ); ?>
					</a>
					<a href="?page=server_info_display&tab=more_plugins" class="si-nav-item <?php echo 'more_plugins' === $active_tab ? 'active' : ''; ?>">
						<span class="dashicons dashicons-admin-plugins"></span> <?php esc_html_e( 'More Plugins', 'server-info' ); ?>
					</a>
					<a href="?page=server_info_display&tab=settings" class="si-nav-item <?php echo 'settings' === $active_tab ? 'active' : ''; ?>">
						<span class="dashicons dashicons-admin-settings"></span> <?php esc_html_e( 'Settings', 'server-info' ); ?>
					</a>
					<a href="?page=server_info_display&tab=support" class="si-nav-item <?php echo 'support' === $active_tab ? 'active' : ''; ?>">
						<span class="dashicons dashicons-heart"></span> <?php esc_html_e( 'Support', 'server-info' ); ?>
					</a>
				</div>

				<div class="si-health-widget">
					<h3 class="si-health-title">Overall Health</h3>
					<p class="si-health-score" style="color: <?php echo esc_attr($health_color); ?>"><?php echo esc_html($health_score); ?>%</p>
					<p class="si-health-status" style="color: <?php echo esc_attr($health_color); ?>"><?php echo esc_html($health_status); ?></p>
					<div class="si-health-chart">
						<svg viewBox="0 0 100 30" preserveAspectRatio="none">
							<path d="M0,20 C15,20 15,5 30,5 C45,5 45,25 60,25 C75,25 75,10 90,10 C95,10 98,15 100,20 L100,30 L0,30 Z" fill="<?php echo esc_attr($health_bg); ?>" />
							<path d="M0,20 C15,20 15,5 30,5 C45,5 45,25 60,25 C75,25 75,10 90,10 C95,10 98,15 100,20" fill="none" stroke="<?php echo esc_attr($health_color); ?>" stroke-width="2" />
						</svg>
					</div>
					<p class="si-health-desc"><?php echo esc_html($health_msg); ?></p>
					<a href="?page=server_info_display&tab=diagnostics" class="si-btn-outline">View Health Details &rarr;</a>
				</div>

				<div class="si-rating-widget">
					<div class="si-rating-stars">
						<span class="dashicons dashicons-star-filled"></span>
						<span class="dashicons dashicons-star-filled"></span>
						<span class="dashicons dashicons-star-filled"></span>
						<span class="dashicons dashicons-star-filled"></span>
						<span class="dashicons dashicons-star-filled"></span>
					</div>
					<p class="si-rating-title">Love Server Info?</p>
					<p class="si-rating-desc">Please share your experience with the plugin.</p>
					<a href="https://wordpress.org/support/plugin/server-info/reviews/#new-post" target="_blank" rel="noopener noreferrer" class="si-btn-outline si-rating-btn">Leave a Rating &rarr;</a>
				</div>

				<div class="si-support-widget">
					<p class="si-support-title"><?php esc_html_e( 'Support the Author', 'server-info' ); ?></p>
					<p class="si-support-desc"><?php esc_html_e( 'A small contribution helps maintain compatibility, testing, and new diagnostics.', 'server-info' ); ?></p>
					<a href="?page=server_info_display&tab=support" class="si-btn-primary si-support-btn"><?php esc_html_e( 'Support Development', 'server-info' ); ?></a>
				</div>
			</div>

			<div class="si-main">
				<div class="si-topbar">
					<div class="si-page-title">
						<h1><?php echo esc_html( $page_title ); ?></h1>
						<p><?php echo esc_html( $page_subtitle ); ?></p>
					</div>
					<div class="si-actions">
						<a href="<?php echo esc_url( add_query_arg( array( 'page' => 'server_info_display', 'tab' => $active_tab ), admin_url( 'options-general.php' ) ) ); ?>" class="si-btn-secondary">
							<span class="dashicons dashicons-update"></span> <?php esc_html_e( 'Refresh Data', 'server-info' ); ?>
						</a>
					</div>
				</div>

				<?php if ( 'server' === $active_tab ) : ?>
				
				<div class="si-kpi-grid">
					<div class="si-kpi-card <?php echo $health_score >= 80 ? 'status-excellent' : 'status-primary'; ?>" style="--si-success: <?php echo esc_attr($health_color); ?>;">
						<div class="si-kpi-icon" style="background: <?php echo esc_attr($health_color); ?>20; color: <?php echo esc_attr($health_color); ?>;"><span class="dashicons dashicons-chart-line"></span></div>
						<div class="si-kpi-content">
							<div class="si-kpi-label">Server Health</div>
							<div class="si-kpi-value"><?php echo esc_html($health_score); ?>%</div>
							<div class="si-kpi-status" style="color: <?php echo esc_attr($health_color); ?>;"><?php echo esc_html($health_status); ?></div>
						</div>
					</div>
					<div class="si-kpi-card status-primary">
						<div class="si-kpi-icon purple"><span class="dashicons dashicons-editor-code"></span></div>
						<div class="si-kpi-content">
							<div class="si-kpi-label">PHP Version</div>
							<div class="si-kpi-value"><?php echo esc_html($php_version); ?></div>
							<div class="si-kpi-status <?php echo esc_attr( $php_status_class ); ?>"><?php echo esc_html( $php_status_label ); ?></div>
						</div>
					</div>
					<div class="si-kpi-card status-primary">
						<div class="si-kpi-icon blue"><span class="dashicons dashicons-dashboard"></span></div>
						<div class="si-kpi-content">
							<div class="si-kpi-label">Memory Usage</div>
							<div class="si-kpi-value"><?php echo esc_html($memory_usage); ?></div>
							<div class="si-kpi-status neutral">Used / Limit</div>
						</div>
					</div>
					<div class="si-kpi-card status-excellent">
						<div class="si-kpi-icon green"><span class="dashicons dashicons-database"></span></div>
						<div class="si-kpi-content">
							<div class="si-kpi-label">Database</div>
							<div class="si-kpi-value"><?php echo esc_html( $db_type . ' ' . $db_version ); ?></div>
							<div class="si-kpi-status success">Healthy</div>
						</div>
					</div>
					<div class="si-kpi-card status-primary">
						<div class="si-kpi-icon orange"><span class="dashicons dashicons-wordpress"></span></div>
						<div class="si-kpi-content">
							<div class="si-kpi-label">WordPress</div>
							<div class="si-kpi-value"><?php echo esc_html($wp_version); ?></div>
							<div class="si-kpi-status <?php echo esc_attr( $wp_core_status_class ); ?>"><?php echo esc_html( $wp_core_status_label ); ?></div>
						</div>
					</div>
				</div>

				<div class="si-section si-basic-section">
					<div class="si-section-header">
						<h3 class="si-section-title"><?php esc_html_e( 'Basic Information', 'server-info' ); ?></h3>
					</div>
					<div class="si-basic-grid">
						<div class="si-basic-card">
							<div class="si-check-heading">
								<span class="dashicons dashicons-networking"></span>
								<strong><?php esc_html_e( 'Hosting', 'server-info' ); ?></strong>
							</div>
							<ul class="si-mini-list">
								<?php
								$fields = array( 'server_hostname', 'server_ip', 'server_protocol' );
								foreach ( $fields as $key ) {
									if ( isset( $info['wp-server']['fields'][ $key ] ) ) {
										$field = $info['wp-server']['fields'][ $key ];
										?>
										<li>
											<span><?php echo esc_html( $field['label'] ); ?></span>
											<strong><?php echo esc_html( $field['value'] ); ?></strong>
										</li>
										<?php
									}
								}
								?>
							</ul>
						</div>

						<div class="si-basic-card si-check-card <?php echo esc_attr( 'status-' . $ssl_info['status_key'] ); ?>">
							<div class="si-check-heading">
								<span class="dashicons dashicons-lock"></span>
								<strong><?php esc_html_e( 'SSL/TLS Certificate', 'server-info' ); ?></strong>
								<span class="si-status-pill"><?php echo esc_html( $ssl_info['status'] ); ?></span>
							</div>
							<ul class="si-mini-list">
								<li><span><?php esc_html_e( 'Issued by', 'server-info' ); ?></span><strong><?php echo esc_html( $ssl_info['issuer'] ); ?></strong></li>
								<li><span><?php esc_html_e( 'Domain', 'server-info' ); ?></span><strong><?php echo esc_html( $ssl_info['domain'] ); ?></strong></li>
								<li><span><?php esc_html_e( 'Expiry', 'server-info' ); ?></span><strong><?php echo esc_html( $ssl_info['expiry'] ); ?></strong></li>
								<li>
									<span><?php esc_html_e( 'Days left', 'server-info' ); ?></span>
									<strong>
										<?php
										if ( null === $ssl_info['days_left'] ) {
											esc_html_e( 'Unavailable', 'server-info' );
										} elseif ( $ssl_info['days_left'] < 0 ) {
										/* translators: %s: Number of days since the certificate expired. */
										printf( esc_html__( '%s days ago', 'server-info' ), esc_html( absint( $ssl_info['days_left'] ) ) );
									} else {
										/* translators: %s: Number of days until the certificate expires. */
										printf( esc_html__( '%s days', 'server-info' ), esc_html( absint( $ssl_info['days_left'] ) ) );
										}
										?>
									</strong>
								</li>
							</ul>
						</div>

						<div class="si-basic-card si-check-card <?php echo esc_attr( 'status-' . $domain_info['status_key'] ); ?>">
							<div class="si-check-heading">
								<span class="dashicons dashicons-admin-site-alt3"></span>
								<strong><?php esc_html_e( 'Domain Expiry', 'server-info' ); ?></strong>
								<span class="si-status-pill"><?php echo esc_html( $domain_info['status'] ); ?></span>
							</div>
							<ul class="si-mini-list">
								<li><span><?php esc_html_e( 'Registrar', 'server-info' ); ?></span><strong><?php echo esc_html( $domain_info['registrar'] ); ?></strong></li>
								<li><span><?php esc_html_e( 'Domain', 'server-info' ); ?></span><strong><?php echo esc_html( $domain_info['domain'] ); ?></strong></li>
								<li><span><?php esc_html_e( 'Expiry date', 'server-info' ); ?></span><strong><?php echo esc_html( $domain_info['expiry'] ); ?></strong></li>
								<li>
									<span><?php esc_html_e( 'Days left', 'server-info' ); ?></span>
									<strong>
										<?php
										if ( null === $domain_info['days_left'] ) {
											esc_html_e( 'Unavailable', 'server-info' );
										} elseif ( $domain_info['days_left'] < 0 ) {
										/* translators: %s: Number of days since the domain registration expired. */
										printf( esc_html__( '%s days ago', 'server-info' ), esc_html( absint( $domain_info['days_left'] ) ) );
									} else {
										/* translators: %s: Number of days until the domain registration expires. */
										printf( esc_html__( '%s days', 'server-info' ), esc_html( absint( $domain_info['days_left'] ) ) );
										}
										?>
									</strong>
								</li>
							</ul>
						</div>
					</div>
				</div>

				<div class="si-dashboard-grid">

					<div class="si-section">
						<div class="si-section-header">
							<h3 class="si-section-title">System Resources</h3>
						</div>
						<div class="si-resources-circles">
							<div class="si-circle-wrapper">
								<div class="si-circle-chart">
									<svg viewBox="0 0 100 100">
										<circle class="si-circle-bg" cx="50" cy="50" r="40"></circle>
										<circle class="si-circle-progress" cx="50" cy="50" r="40" stroke-dasharray="251" stroke-dashoffset="<?php echo esc_attr(251 - (251 * $cpu_pct / 100)); ?>"></circle>
									</svg>
									<div class="si-circle-val"><?php echo esc_html($cpu_load); ?></div>
									<div class="si-circle-label">Load</div>
								</div>
								<div class="si-circle-title">CPU Load</div>
							</div>
							<div class="si-circle-wrapper">
								<div class="si-circle-chart">
									<svg viewBox="0 0 100 100">
										<circle class="si-circle-bg" cx="50" cy="50" r="40"></circle>
										<circle class="si-circle-progress blue" cx="50" cy="50" r="40" stroke-dasharray="251" stroke-dashoffset="<?php echo esc_attr(251 - (251 * $mem_pct / 100)); ?>"></circle>
									</svg>
									<div class="si-circle-val"><?php echo esc_html($mem_pct); ?>%</div>
									<div class="si-circle-label">Used</div>
								</div>
								<div class="si-circle-title">Memory</div>
							</div>
							<div class="si-circle-wrapper">
								<div class="si-circle-chart">
									<svg viewBox="0 0 100 100">
										<circle class="si-circle-bg" cx="50" cy="50" r="40"></circle>
										<circle class="si-circle-progress" cx="50" cy="50" r="40" stroke-dasharray="251" stroke-dashoffset="<?php echo esc_attr(251 - (251 * $disk_pct / 100)); ?>"></circle>
									</svg>
									<div class="si-circle-val"><?php echo esc_html($disk_pct); ?>%</div>
									<div class="si-circle-label">Used</div>
								</div>
								<div class="si-circle-title">Disk Space</div>
							</div>
						</div>
						<div class="si-memory-bars">
							<div class="si-mem-stat">
								<span class="si-mem-label"><span class="dashicons dashicons-minus"></span> Total Memory</span>
							<span class="si-mem-val"><?php echo esc_html( ini_get( 'memory_limit' ) ); ?></span>
							</div>
							<div class="si-mem-stat">
								<span class="si-mem-label"><span class="dashicons dashicons-minus" style="color:var(--si-primary);"></span> Used Memory</span>
								<span class="si-mem-val"><?php echo esc_html($memory_usage); ?></span>
							</div>
						</div>
						<div class="si-section-footer">
							<a href="?page=server_info_display&tab=diagnostics">View Performance Details &rarr;</a>
						</div>
					</div>

					<div class="si-section">
						<div class="si-section-header">
							<h3 class="si-section-title">PHP Configuration</h3>
						</div>
						<?php
						$max_execution_time = ini_get( 'max_execution_time' );
						$max_input_vars     = ini_get( 'max_input_vars' );
						$upload_max_size    = ini_get( 'upload_max_filesize' );
						$post_max_size      = ini_get( 'post_max_size' );
						$loaded_extensions  = function_exists( 'get_loaded_extensions' ) ? get_loaded_extensions() : array();
						$opcache_status     = esc_html__( 'Unavailable', 'server-info' );

						if ( function_exists( 'opcache_get_status' ) ) {
							$opcache = @opcache_get_status( false );
							$opcache_status = ( is_array( $opcache ) && ! empty( $opcache['opcache_enabled'] ) ) ? esc_html__( 'Enabled', 'server-info' ) : esc_html__( 'Disabled', 'server-info' );
						} elseif ( extension_loaded( 'Zend OPcache' ) || extension_loaded( 'opcache' ) ) {
							$opcache_status = esc_html__( 'Loaded', 'server-info' );
						}

						$php_overview_items = array(
							array(
								'icon'  => 'dashicons-media-code',
								'label' => esc_html__( 'PHP Version', 'server-info' ),
								'value' => $php_version,
								'hint'  => $php_status_label,
							),
							array(
								'icon'  => 'dashicons-dashboard',
								'label' => esc_html__( 'Memory Limit', 'server-info' ),
								'value' => ini_get( 'memory_limit' ),
								'hint'  => esc_html__( 'Per PHP process', 'server-info' ),
							),
							array(
								'icon'  => 'dashicons-clock',
								'label' => esc_html__( 'Max Execution', 'server-info' ),
								/* translators: %s: Maximum PHP execution time in seconds. */
								'value' => '' === $max_execution_time ? esc_html__( 'Unavailable', 'server-info' ) : sprintf( esc_html__( '%s sec', 'server-info' ), $max_execution_time ),
								'hint'  => esc_html__( 'Script timeout', 'server-info' ),
							),
							array(
								'icon'  => 'dashicons-upload',
								'label' => esc_html__( 'Upload Max', 'server-info' ),
								'value' => $upload_max_size ? $upload_max_size : esc_html__( 'Unavailable', 'server-info' ),
								'hint'  => esc_html__( 'File upload limit', 'server-info' ),
							),
							array(
								'icon'  => 'dashicons-forms',
								'label' => esc_html__( 'Post Max Size', 'server-info' ),
								'value' => $post_max_size ? $post_max_size : esc_html__( 'Unavailable', 'server-info' ),
								'hint'  => esc_html__( 'Request body limit', 'server-info' ),
							),
							array(
								'icon'  => 'dashicons-editor-code',
								'label' => esc_html__( 'PHP SAPI', 'server-info' ),
								'value' => function_exists( 'php_sapi_name' ) ? php_sapi_name() : esc_html__( 'Unavailable', 'server-info' ),
								'hint'  => esc_html__( 'Runtime interface', 'server-info' ),
							),
							array(
								'icon'  => 'dashicons-performance',
								'label' => esc_html__( 'OPcache', 'server-info' ),
								'value' => $opcache_status,
								'hint'  => esc_html__( 'Bytecode cache', 'server-info' ),
							),
							array(
								'icon'  => 'dashicons-admin-plugins',
								'label' => esc_html__( 'Extensions', 'server-info' ),
								'value' => count( $loaded_extensions ),
								'hint'  => esc_html__( 'Loaded modules', 'server-info' ),
							),
							array(
								'icon'  => 'dashicons-list-view',
								'label' => esc_html__( 'Max Input Vars', 'server-info' ),
								'value' => $max_input_vars ? $max_input_vars : esc_html__( 'Unavailable', 'server-info' ),
								'hint'  => esc_html__( 'Form fields limit', 'server-info' ),
							),
						);
						?>
						<div class="si-data-grid si-php-overview-grid">
							<?php foreach ( $php_overview_items as $item ) : ?>
								<div class="si-data-item si-php-data-item">
									<span class="si-data-item-label"><span class="dashicons <?php echo esc_attr( $item['icon'] ); ?>"></span> <?php echo esc_html( $item['label'] ); ?></span>
									<span class="si-data-item-val"><?php echo esc_html( $item['value'] ); ?></span>
									<span class="si-data-item-hint"><?php echo esc_html( $item['hint'] ); ?></span>
								</div>
							<?php endforeach; ?>
						</div>
						<div class="si-section-footer" style="margin-top: 30px;">
							<a href="?page=server_info_display&tab=phpinfo">View All PHP Settings &rarr;</a>
						</div>
					</div>
					
					<div class="si-section" style="background:transparent; border:none; box-shadow:none; padding:0;">
						<div class="si-section-header">
							<h3 class="si-section-title">Quick Actions</h3>
						</div>
						<div class="si-actions-grid">
							<a href="?page=server_info_display&tab=diagnostics" class="si-action-card">
								<div class="si-action-icon"><span class="dashicons dashicons-analytics"></span></div>
								<div class="si-action-texts">
									<span class="si-action-title">View Logs</span>
									<span class="si-action-desc">Access error logs</span>
								</div>
							</a>
							<a href="?page=server_info_display&tab=phpinfo" class="si-action-card">
								<div class="si-action-icon"><span class="dashicons dashicons-editor-code"></span></div>
								<div class="si-action-texts">
									<span class="si-action-title">PHP Info</span>
									<span class="si-action-desc">View runtime settings</span>
								</div>
							</a>
						</div>
					</div>
				</div>
				<?php else: 
					// Fallback for other tabs (Database, WP Core, Diagnostics, Plugins)
					if ( 'diagnostics' === $active_tab ) {
						self::display_diagnostics_tab();
					} elseif ( 'phpinfo' === $active_tab ) {
						self::display_phpinfo_tab();
					} elseif ( 'more_plugins' === $active_tab ) {
						self::display_more_plugins_tab();
					} elseif ( 'settings' === $active_tab ) {
						self::display_settings_tab();
					} elseif ( 'support' === $active_tab ) {
						self::display_support_tab();
					} else {
						$group_key = 'wp-server';
						if ( 'database' === $active_tab ) {
							$group_key = 'wp-database';
						} elseif ( 'wordpress' === $active_tab ) {
							$group_key = 'wp-info';
						} elseif ( 'caching' === $active_tab ) {
							$group_key = 'wp-caching';
						}

						if ( isset( $info[ $group_key ] ) && ! empty( $info[ $group_key ]['fields'] ) ) {
							$details = $info[ $group_key ];
							echo '<div class="si-section">';
							echo '<div class="si-section-header"><h3 class="si-section-title">' . esc_html( $details['label'] ) . '</h3></div>';
							echo '<ul class="si-table-list">';
							foreach ( $details['fields'] as $field ) {
								$value_html = '';
								if ( is_array( $field['value'] ) ) {
									$value_html .= '<div class="si-array-val">';
									foreach ( $field['value'] as $name => $val ) {
										if ( empty( $val ) ) {
											$value_html .= sprintf( '<div>%s</div>', esc_html( $name ) );
										} else {
											$value_html .= sprintf( '<div><strong>%s</strong> %s</div>', esc_html( $name ), esc_html( $val ) );
										}
									}
									$value_html .= '</div>';
								} else {
									$value_html = esc_html( $field['value'] );
								}
								?>
								<li class="si-table-row <?php echo is_array( $field['value'] ) ? 'has-array' : ''; ?>">
									<span class="si-table-label"><span class="dashicons dashicons-arrow-right-alt2"></span> <?php echo esc_html( $field['label'] ); ?></span>
								<span class="si-table-val"><?php echo wp_kses_post( $value_html ); ?></span>
								</li>
								<?php
							}
							echo '</ul></div>';
						}
					}
				endif; ?>

			</div>
			<?php self::display_support_floating_checkout(); ?>
		</div>
		<?php
	}

	/**
	 * Displays the more plugins tab content for cross-promotion.
	 *
	 * @since 0.0.1
	 * @access public
	 *
	 * @return void
	 */
	 public static function display_more_plugins_tab() {
		if ( ! function_exists( 'is_plugin_active' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$plugins = array(
			array(
				'name' => esc_html__( 'Advance Canonical URL', 'server-info' ),
				'desc' => esc_html__( 'Easily manage and customize canonical URLs to eliminate duplicate content issues and boost your SEO rankings.', 'server-info' ),
				'icon' => 'dashicons-admin-links',
				'slug' => 'advance-canonical-url',
				'file' => 'advance-canonical-url/functions.php',
			),
			array(
				'name' => esc_html__( 'Metaviewer - Debug Meta Data', 'server-info' ),
				'desc' => esc_html__( 'The ultimate developer tool to instantly view, inspect, and debug post, user, and term meta data directly from the frontend.', 'server-info' ),
				'icon' => 'dashicons-visibility',
				'slug' => 'metaviewer-debug-meta-data',
				'file' => 'metaviewer-debug-meta-data/metaviewer.php',
			),
			array(
				'name' => esc_html__( 'Randomize Password', 'server-info' ),
				'desc' => esc_html__( 'Enhance your security by forcing highly secure, completely randomized passwords for user accounts upon creation or reset.', 'server-info' ),
				'icon' => 'dashicons-lock',
				'slug' => 'randomize-password',
				'file' => 'randomize-password/randomize-password.php',
			),
			array(
				'name' => esc_html__( 'Fusion Pricing Tables', 'server-info' ),
				'desc' => esc_html__( 'Build flexible Elementor pricing tables with 15 ready-made skins, billing toggles, ribbons, and responsive controls.', 'server-info' ),
				'icon' => 'dashicons-editor-table',
				'slug' => 'fusion-pricing-tables',
				'file' => 'fusion-pricing-tables/pricing-grid.php',
			),
		);
		?>
		<div class="si-dashboard-grid si-plugins-grid">
			<?php foreach ( $plugins as $plugin ) : 
				$is_installed = file_exists( WP_PLUGIN_DIR . '/' . $plugin['file'] );
				$is_active    = $is_installed && is_plugin_active( $plugin['file'] );
			?>
			<div class="si-card">
				<div class="si-plugin-card-header">
					<div class="si-plugin-card-icon">
						<span class="dashicons <?php echo esc_attr( $plugin['icon'] ); ?>"></span>
					</div>
					<h3 class="si-plugin-card-title"><?php echo esc_html( $plugin['name'] ); ?></h3>
				</div>
				<p class="si-plugin-card-desc">
					<?php echo esc_html( $plugin['desc'] ); ?>
				</p>
				<div>
					<?php if ( $is_active ) : ?>
						<button type="button" class="si-btn-active" disabled><?php esc_html_e( 'Active', 'server-info' ); ?></button>
					<?php elseif ( $is_installed ) : 
						$activate_url = wp_nonce_url( admin_url( 'plugins.php?action=activate&plugin=' . urlencode( $plugin['file'] ) ), 'activate-plugin_' . $plugin['file'] );
					?>
						<a href="<?php echo esc_url( $activate_url ); ?>" class="si-btn-activate"><?php esc_html_e( 'Activate', 'server-info' ); ?></a>
					<?php else : 
						$install_url = wp_nonce_url( admin_url( 'update.php?action=install-plugin&plugin=' . urlencode( $plugin['slug'] ) ), 'install-plugin_' . $plugin['slug'] );
					?>
						<a href="<?php echo esc_url( $install_url ); ?>" class="si-btn-install"><?php esc_html_e( 'Install Now', 'server-info' ); ?></a>
					<?php endif; ?>
				</div>
			</div>
			<?php endforeach; ?>
		</div>
		<?php
	}

	/**
	 * Displays the plugin settings tab.
	 *
	 * @return void
	 */
	public static function display_settings_tab() {
		$options = self::get_options();
		$schemes = self::get_appearance_schemes();
		?>
		<div class="si-dashboard-grid si-settings-grid">
			<div class="si-section">
				<div class="si-section-header">
					<h3 class="si-section-title"><?php esc_html_e( 'Admin Display Settings', 'server-info' ); ?></h3>
				</div>
				<form method="post" action="options.php" class="si-settings-form">
					<?php settings_fields( 'server_info_settings' ); ?>
					<label class="si-toggle-row" for="server-info-admin-bar-hud">
						<span>
							<strong><?php esc_html_e( 'Admin Bar HUD', 'server-info' ); ?></strong>
							<small><?php esc_html_e( 'Show environment, PHP version, and memory usage in the WordPress admin bar.', 'server-info' ); ?></small>
						</span>
						<span class="si-toggle-control">
							<input type="checkbox" id="server-info-admin-bar-hud" name="<?php echo esc_attr( SERVER_INFO_OPTION_NAME ); ?>[admin_bar_hud]" value="1" <?php checked( ! empty( $options['admin_bar_hud'] ) ); ?> />
							<span class="si-toggle-switch" aria-hidden="true"></span>
						</span>
					</label>
					<label class="si-toggle-row" for="server-info-footer-info">
						<span>
							<strong><?php esc_html_e( 'Admin Footer Diagnostics', 'server-info' ); ?></strong>
							<small><?php esc_html_e( 'Show a compact environment and memory summary in the admin footer.', 'server-info' ); ?></small>
						</span>
						<span class="si-toggle-control">
							<input type="checkbox" id="server-info-footer-info" name="<?php echo esc_attr( SERVER_INFO_OPTION_NAME ); ?>[footer_info]" value="1" <?php checked( ! empty( $options['footer_info'] ) ); ?> />
							<span class="si-toggle-switch" aria-hidden="true"></span>
						</span>
					</label>
					<label class="si-toggle-row" for="server-info-domain-expiry-lookup">
						<span>
							<strong><?php esc_html_e( 'Domain Expiry Lookup', 'server-info' ); ?></strong>
							<small><?php esc_html_e( 'Check public RDAP or WHOIS registration data for the site domain and cache the result to keep Overview fast.', 'server-info' ); ?></small>
						</span>
						<span class="si-toggle-control">
							<input type="checkbox" id="server-info-domain-expiry-lookup" name="<?php echo esc_attr( SERVER_INFO_OPTION_NAME ); ?>[domain_expiry_lookup]" value="1" <?php checked( ! empty( $options['domain_expiry_lookup'] ) ); ?> />
							<span class="si-toggle-switch" aria-hidden="true"></span>
						</span>
					</label>

					<div class="si-settings-subsection">
						<h4><?php esc_html_e( 'Appearance', 'server-info' ); ?></h4>
						<p><?php esc_html_e( 'Choose a layout color preset or set a custom background and text color for the Server Info admin screen.', 'server-info' ); ?></p>
						<div class="si-appearance-options">
							<?php foreach ( $schemes as $scheme_key => $scheme ) : ?>
								<label class="si-appearance-option" for="server-info-appearance-<?php echo esc_attr( $scheme_key ); ?>">
									<input type="radio" id="server-info-appearance-<?php echo esc_attr( $scheme_key ); ?>" name="<?php echo esc_attr( SERVER_INFO_OPTION_NAME ); ?>[appearance_scheme]" value="<?php echo esc_attr( $scheme_key ); ?>" <?php checked( $options['appearance_scheme'], $scheme_key ); ?> />
									<span><?php echo esc_html( $scheme['label'] ); ?></span>
								</label>
							<?php endforeach; ?>
						</div>
						<div class="si-color-controls">
							<label for="server-info-custom-bg-color">
								<span><?php esc_html_e( 'Background', 'server-info' ); ?></span>
								<input type="color" id="server-info-custom-bg-color" name="<?php echo esc_attr( SERVER_INFO_OPTION_NAME ); ?>[custom_bg_color]" value="<?php echo esc_attr( $options['custom_bg_color'] ); ?>" />
							</label>
							<label for="server-info-custom-text-color">
								<span><?php esc_html_e( 'Text', 'server-info' ); ?></span>
								<input type="color" id="server-info-custom-text-color" name="<?php echo esc_attr( SERVER_INFO_OPTION_NAME ); ?>[custom_text_color]" value="<?php echo esc_attr( $options['custom_text_color'] ); ?>" />
							</label>
						</div>
					</div>
					<?php submit_button( esc_html__( 'Save Settings', 'server-info' ), 'primary si-submit', 'submit', false ); ?>
				</form>
			</div>

			<div class="si-section si-settings-note">
				<div class="si-section-header">
					<h3 class="si-section-title"><?php esc_html_e( 'Privacy Note', 'server-info' ); ?></h3>
				</div>
				<p><?php esc_html_e( 'Server Info only displays diagnostics to administrators. Some values can include internal hostnames, IP addresses, file paths, and debug-log lines, so avoid sharing screenshots publicly without reviewing them first.', 'server-info' ); ?></p>
			</div>
		</div>
		<?php
	}

	/**
	 * Displays the support tab.
	 *
	 * @return void
	 */
	public static function display_support_tab() {
		$review_url         = 'https://wordpress.org/support/plugin/server-info/reviews/#new-post';
		$support_forum_url  = 'https://wordpress.org/support/plugin/server-info/';
		?>
		<div class="si-dashboard-grid si-support-grid">
			<div class="si-section si-support-hero">
				<div class="si-support-hero-icon">
					<span class="dashicons dashicons-heart"></span>
				</div>
				<h3><?php esc_html_e( 'Support Server Info', 'server-info' ); ?></h3>
				<p><?php esc_html_e( 'If Server Info helped you debug a hosting issue, prepare a support ticket, or understand a slow site, your support helps keep the plugin maintained and compatible with new WordPress and PHP releases.', 'server-info' ); ?></p>
				<div class="si-support-actions">
					<button type="button" class="si-btn-primary si-support-checkout-button" data-server-info-support-open>
						<span class="dashicons dashicons-heart"></span> <?php esc_html_e( 'Choose Support Amount', 'server-info' ); ?>
					</button>
					<a class="si-btn-secondary" href="<?php echo esc_url( $review_url ); ?>" target="_blank" rel="noopener noreferrer">
						<span class="dashicons dashicons-star-filled"></span> <?php esc_html_e( 'Leave a Review', 'server-info' ); ?>
					</a>
					<a class="si-btn-secondary" href="<?php echo esc_url( $support_forum_url ); ?>" target="_blank" rel="noopener noreferrer">
						<span class="dashicons dashicons-sos"></span> <?php esc_html_e( 'Get Support', 'server-info' ); ?>
					</a>
				</div>
			</div>

			<div class="si-section">
				<div class="si-section-header">
					<h3 class="si-section-title"><?php esc_html_e( 'What Your Support Funds', 'server-info' ); ?></h3>
				</div>
				<ul class="si-support-list">
					<li><span class="dashicons dashicons-yes-alt"></span><?php esc_html_e( 'Compatibility testing for new WordPress and PHP releases.', 'server-info' ); ?></li>
					<li><span class="dashicons dashicons-yes-alt"></span><?php esc_html_e( 'Safer diagnostics, redaction, and export workflows.', 'server-info' ); ?></li>
					<li><span class="dashicons dashicons-yes-alt"></span><?php esc_html_e( 'Better WooCommerce, cron, cache, and database health checks.', 'server-info' ); ?></li>
					<li><span class="dashicons dashicons-yes-alt"></span><?php esc_html_e( 'Faster fixes for hosting-specific edge cases.', 'server-info' ); ?></li>
				</ul>
			</div>
		</div>
		<?php
	}

	/**
	 * Displays the floating supporter checkout launcher.
	 *
	 * @return void
	 */
	public static function display_support_floating_checkout() {
		$plans = self::get_support_plans();
		?>
		<button type="button" class="si-floating-support-button" data-server-info-support-open>
			<span class="dashicons dashicons-heart"></span>
			<span><?php esc_html_e( 'Support', 'server-info' ); ?></span>
		</button>

		<div id="server-info-support-panel" class="si-support-panel" hidden aria-hidden="true">
			<div class="si-support-panel-backdrop" data-server-info-support-close></div>
			<div class="si-support-panel-card" role="dialog" aria-modal="true" aria-labelledby="server-info-support-panel-title">
				<button type="button" class="si-support-panel-close" data-server-info-support-close aria-label="<?php esc_attr_e( 'Close support options', 'server-info' ); ?>">
					<span class="dashicons dashicons-no-alt"></span>
				</button>
				<div class="si-support-panel-header">
					<span class="si-support-panel-icon"><span class="dashicons dashicons-heart"></span></span>
					<div>
						<h3 id="server-info-support-panel-title"><?php esc_html_e( 'Support Server Info', 'server-info' ); ?></h3>
						<p><?php esc_html_e( 'Choose any one-time amount that feels right. The plugin stays free for everyone.', 'server-info' ); ?></p>
					</div>
				</div>
				<div class="si-support-plan-grid">
					<?php foreach ( $plans as $plan ) : ?>
						<button type="button" class="si-support-plan-card <?php echo ! empty( $plan['featured'] ) ? 'is-featured' : ''; ?>" data-server-info-support-plan="<?php echo esc_attr( $plan['id'] ); ?>">
							<span class="si-support-plan-title"><?php echo esc_html( $plan['title'] ); ?></span>
							<span class="si-support-plan-amount"><?php echo esc_html( $plan['amount'] ); ?></span>
							<span class="si-support-plan-desc"><?php echo esc_html( $plan['description'] ); ?></span>
						</button>
					<?php endforeach; ?>
				</div>
				<p id="server-info-support-message" class="si-support-message" aria-live="polite"></p>
			</div>
		</div>
		<?php
	}

	/**
	 * Display the PHP Info tab.
	 *
	 * @return void
	 */
	public static function display_phpinfo_tab() {
		$settings = function_exists( 'ini_get_all' ) ? ini_get_all( null, false ) : array();
		$settings = is_array( $settings ) ? $settings : array();
		ksort( $settings, SORT_NATURAL | SORT_FLAG_CASE );

		$extensions = function_exists( 'get_loaded_extensions' ) ? get_loaded_extensions() : array();
		sort( $extensions, SORT_NATURAL | SORT_FLAG_CASE );

		?>
		<div class="si-section si-phpinfo-wrapper">
			<div class="si-section-header">
				<h3 class="si-section-title"><?php esc_html_e( 'PHP Runtime Summary', 'server-info' ); ?></h3>
			</div>
			<ul class="si-table-list">
				<li class="si-table-row">
					<span class="si-table-label"><?php esc_html_e( 'PHP Version', 'server-info' ); ?></span>
					<span class="si-table-val"><?php echo esc_html( PHP_VERSION ); ?></span>
				</li>
				<li class="si-table-row">
					<span class="si-table-label"><?php esc_html_e( 'Server API', 'server-info' ); ?></span>
					<span class="si-table-val"><?php echo esc_html( PHP_SAPI ); ?></span>
				</li>
				<li class="si-table-row has-array">
					<span class="si-table-label"><?php esc_html_e( 'Loaded Extensions', 'server-info' ); ?></span>
					<span class="si-table-val"><?php echo esc_html( implode( ', ', $extensions ) ); ?></span>
				</li>
			</ul>
		</div>

		<div class="si-section si-phpinfo-wrapper">
			<div class="si-section-header">
				<h3 class="si-section-title"><?php esc_html_e( 'Current PHP Settings', 'server-info' ); ?></h3>
			</div>
			<?php if ( empty( $settings ) ) : ?>
				<p><?php esc_html_e( 'PHP configuration values are unavailable on this server.', 'server-info' ); ?></p>
			<?php else : ?>
				<ul class="si-table-list">
					<?php foreach ( $settings as $name => $value ) : ?>
						<?php
						$is_sensitive = (bool) preg_match( '/pass(word|wd)?|secret|credential|token/i', (string) $name );
						if ( $is_sensitive ) {
							$display_value = esc_html__( 'Hidden', 'server-info' );
						} elseif ( false === $value || '' === $value ) {
							$display_value = esc_html__( 'Not set', 'server-info' );
						} else {
							$display_value = (string) $value;
						}
						?>
						<li class="si-table-row">
							<span class="si-table-label"><code><?php echo esc_html( $name ); ?></code></span>
							<span class="si-table-val"><?php echo esc_html( $display_value ); ?></span>
						</li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Displays the diagnostics and logs tab content.
	 *
	 * @since 0.0.1
	 * @access public
	 *
	 * @return void
	 */
	public static function display_diagnostics_tab() {		$php_version = phpversion();
		$php_status = 'Optimal';
		$php_impact = 'No Impact';
		$php_color = 'var(--si-success)';
		if ( version_compare( $php_version, '7.4', '<' ) ) {
			$php_status = 'Critical';
			$php_impact = '-30% Penalty';
			$php_color = 'var(--si-danger)';
		} elseif ( version_compare( $php_version, '8.3', '<' ) ) {
			$php_status = 'Warning';
			$php_impact = '-10% Penalty';
			$php_color = 'var(--si-warning)';
		}

		$memory_limit = ini_get( 'memory_limit' );
		$memory_limit_int = intval( $memory_limit );
		if ( false !== strpos( $memory_limit, 'G' ) ) {
			$memory_limit_int *= 1024;
		}
		$mem_status = 'Good';
		$mem_impact = 'No Impact';
		$mem_color = 'var(--si-success)';
		if ( $memory_limit_int > 0 && $memory_limit_int < 256 ) {
			$mem_status = 'Low';
			$mem_impact = '-10% Penalty';
			$mem_color = 'var(--si-warning)';
		}

		$wp_config_path = ABSPATH . 'wp-config.php';
		$is_config_writable = file_exists( $wp_config_path ) && wp_is_writable( $wp_config_path );
		$config_status = $is_config_writable ? 'Writable (Insecure)' : 'Secure';
		$config_impact = $is_config_writable ? '-10% Penalty' : 'No Impact';
		$config_color = $is_config_writable ? 'var(--si-danger)' : 'var(--si-success)';
		
		global $wp_version;
		$core_updates = get_site_transient('update_core');
		$wp_status = 'Up to date';
		$wp_impact = 'No Impact';
		$wp_color = 'var(--si-success)';
		if ( isset( $core_updates->updates ) && is_array( $core_updates->updates ) ) {
			foreach ( $core_updates->updates as $update ) {
				if ( $update->response === 'upgrade' ) {
					$wp_status = 'Update Available';
					$wp_impact = '-10% Penalty';
					$wp_color = 'var(--si-warning)';
					break;
				}
			}
		}

		$error_log = ini_get( 'error_log' );
		$log_status = ! empty( $error_log ) ? esc_html( $error_log ) : 'Not configured';
		
		?>
		<div class="si-dashboard-grid" style="grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); margin-bottom: 24px;">
			<div class="si-card">
				<div class="si-plugin-card-header">
					<div class="si-plugin-card-icon" style="color: <?php echo esc_attr($php_color); ?>;">
						<span class="dashicons dashicons-media-code"></span>
					</div>
					<h3 class="si-plugin-card-title"><?php esc_html_e( 'PHP Version', 'server-info' ); ?></h3>
				</div>
				<div style="margin-bottom: 12px;">
					<strong><?php echo esc_html($php_version); ?></strong> - <span style="color: <?php echo esc_attr($php_color); ?>; font-weight: 600;"><?php echo esc_html($php_status); ?></span>
				</div>
				<div style="font-size: 13px; color: var(--si-text-muted); background: var(--si-bg); padding: 8px; border-radius: 6px;">
					<strong>Score Impact:</strong> <?php echo esc_html($php_impact); ?>
				</div>
			</div>
			
			<div class="si-card">
				<div class="si-plugin-card-header">
					<div class="si-plugin-card-icon" style="color: <?php echo esc_attr($mem_color); ?>;">
						<span class="dashicons dashicons-dashboard"></span>
					</div>
					<h3 class="si-plugin-card-title"><?php esc_html_e( 'PHP Memory Limit', 'server-info' ); ?></h3>
				</div>
				<div style="margin-bottom: 12px;">
					<strong><?php echo esc_html($memory_limit); ?></strong> - <span style="color: <?php echo esc_attr($mem_color); ?>; font-weight: 600;"><?php echo esc_html($mem_status); ?></span>
				</div>
				<div style="font-size: 13px; color: var(--si-text-muted); background: var(--si-bg); padding: 8px; border-radius: 6px;">
					<strong>Score Impact:</strong> <?php echo esc_html($mem_impact); ?>
				</div>
			</div>

			<div class="si-card">
				<div class="si-plugin-card-header">
					<div class="si-plugin-card-icon" style="color: <?php echo esc_attr($config_color); ?>;">
						<span class="dashicons dashicons-lock"></span>
					</div>
					<h3 class="si-plugin-card-title"><?php esc_html_e( 'wp-config.php', 'server-info' ); ?></h3>
				</div>
				<div style="margin-bottom: 12px;">
					<span style="color: <?php echo esc_attr($config_color); ?>; font-weight: 600;"><?php echo esc_html($config_status); ?></span>
				</div>
				<div style="font-size: 13px; color: var(--si-text-muted); background: var(--si-bg); padding: 8px; border-radius: 6px;">
					<strong>Score Impact:</strong> <?php echo esc_html($config_impact); ?>
				</div>
			</div>

			<div class="si-card">
				<div class="si-plugin-card-header">
					<div class="si-plugin-card-icon" style="color: <?php echo esc_attr($wp_color); ?>;">
						<span class="dashicons dashicons-wordpress"></span>
					</div>
					<h3 class="si-plugin-card-title"><?php esc_html_e( 'WordPress Core', 'server-info' ); ?></h3>
				</div>
				<div style="margin-bottom: 12px;">
					<strong><?php echo esc_html($wp_version); ?></strong> - <span style="color: <?php echo esc_attr($wp_color); ?>; font-weight: 600;"><?php echo esc_html($wp_status); ?></span>
				</div>
				<div style="font-size: 13px; color: var(--si-text-muted); background: var(--si-bg); padding: 8px; border-radius: 6px;">
					<strong>Score Impact:</strong> <?php echo esc_html($wp_impact); ?>
				</div>
			</div>
			
			<div class="si-card">
				<div class="si-plugin-card-header">
					<div class="si-plugin-card-icon" style="color: var(--si-text-main);">
						<span class="dashicons dashicons-analytics"></span>
					</div>
					<h3 class="si-plugin-card-title"><?php esc_html_e( 'Native PHP Error Log', 'server-info' ); ?></h3>
				</div>
				<div style="margin-bottom: 12px; font-size: 12px; word-break: break-all;">
					<?php echo esc_html($log_status); ?>
				</div>
				<div style="font-size: 13px; color: var(--si-text-muted); background: var(--si-bg); padding: 8px; border-radius: 6px;">
					<strong>Score Impact:</strong> No Impact
				</div>
			</div>
		</div>

			<div class="si-section">
				<div class="si-section-header">
					<h3 class="si-section-title"><?php esc_html_e( 'Server Debug Log', 'server-info' ); ?></h3>
				</div>
				<div class="si-terminal">
					<?php
					$log_file = WP_CONTENT_DIR . '/debug.log';
					require_once ABSPATH . 'wp-admin/includes/file.php';
					WP_Filesystem();
					global $wp_filesystem;
					if ( $wp_filesystem && $wp_filesystem->exists( $log_file ) && $wp_filesystem->is_readable( $log_file ) ) {
						$contents = $wp_filesystem->get_contents( $log_file );
						$lines    = is_string( $contents ) ? preg_split( '/\R/', $contents ) : array();
						if ( is_array( $lines ) && ! empty( $lines ) ) {
							$last_lines = array_slice( $lines, -30 );
							foreach ( $last_lines as $line ) {
								echo esc_html( $line ) . '<br/>';
							}
						} else {
							echo esc_html__( 'Debug log is currently empty.', 'server-info' );
						}
					} else {
						echo esc_html__( 'Debug log not found or not readable. Ensure WP_DEBUG and WP_DEBUG_LOG are enabled in wp-config.php.', 'server-info' );
					}
					?>
				</div>
			</div>
		<?php
	}
}

// Instantiate the Server_Info class
$Server_Info = Server_Info::getInstance();
