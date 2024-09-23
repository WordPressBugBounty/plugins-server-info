<?php
/**
 * Plugin Name: Server Info
 * Plugin URI: https://github.com/usmanaliqureshi/server-info
 * Description: This plugin will show you useful information about the hosting server you are using e.g. PHP version, MySQL version, Server OS, Server Protocol, Server IP and other useful information. You can use the information displayed by this plugin to update any settings which is crucial for your website performance and other aspects.
 * Version: 0.0.1
 * Author: Usman Ali Qureshi
 * Author URI: https://profiles.wordpress.org/usmanaliqureshi
 * License: GPLv2 or later
 * GitHub Plugin URI: https://github.com/alpipego/wp-version-info
 * Text Domain: server-info
 * Domain Path: /languages/
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

// Set the plugin version.
define( 'SERVER_INFO_PLUGIN_VERSION', '0.0.1' );

// Set the plugin file.
define( 'SERVER_INFO_PLUGIN_FILE', __FILE__ );

// Set the absolute path for the plugin.
define( 'SERVER_INFO_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );

// Set the plugin URL root.
define( 'SERVER_INFO_PLUGIN_URL', plugins_url( '/', __FILE__ ) );

/**
 * Class ServerInfo
 */
class Server_Info {

	/**
	 * Singleton instance static property
	 */
	static $instance = false;

	/**
	 * If an instance exists, this returns it. If not, it creates one and returns it.
	 *
	 * @return Server_Info instance
	 */
	public static function getInstance() {
		if ( ! self::$instance ) {
			self::$instance = new self;
		}

		return self::$instance;
	}

	/**
	 * Plugin constructor
	 *
	 * @return void
	 */
	private function __construct() {
		$this->init();
	}

	/**
	 * Plugin initiation.
	 *
	 * A helper function to initiate actions, hooks and other features needed.
	 *
	 * @return void
	 */
	public function init() {
		add_action( 'plugins_loaded', array( $this, 'load_i18n' ) );
		add_action( 'wp_dashboard_setup', array( $this, 'add_dashboard_widgets' ) );
		add_action( 'admin_menu', array( $this, 'add_plugin_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_scripts' ) );
	}

	/**
	 * Load plugin translations.
	 *
	 * Loads the textdomain needed to get translations for the plugin.
	 *
	 * @return void
	 */
	public function load_i18n() {
		load_plugin_textdomain( 'server-info', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
	}

	/**
	 * Adds plugin styles & scripts
	 *
	 * @return void
	 */
	public function admin_scripts() {
		wp_enqueue_style( 'server-info', SERVER_INFO_PLUGIN_URL . '/assets/css/style.css', array(), SERVER_INFO_PLUGIN_VERSION, 'all' );
	}

	/**
	 * Adds the plugin menu
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
	 * Adds the plugin dashboard widget
	 *
	 * @return void
	 */
	public function add_dashboard_widgets() {
		wp_add_dashboard_widget(
			'serverinfo_dashboard_widget',
			esc_html__( 'Server Info', 'server-info' ),
			array( 'server_info', 'display_dashboard_widget' )
		);
	}

	/**
	 * Displays the plugin dashboard widget content
	 *
	 * @return void
	 */
	public static function display_dashboard_widget() {
		?>
        <table class="table striped infohouse_table dashboard_inf_table">
			<?php
			if ( function_exists( 'php_uname' ) ) {
				?>
                <tr>
                    <td><?php esc_html_e( 'Operating System', 'server-info' ); ?>:</td>
                    <td><?php echo php_uname( "s" ); ?></td>
                </tr>
				<?php
			}

			if ( isset( $_SERVER['SERVER_ADDR'] ) ) {
				?>
                <tr>
                    <td><?php esc_html_e( 'Server IP', 'server-info' ); ?>:</td>
                    <td><?php echo esc_html( $_SERVER['SERVER_ADDR'] ); ?></td>
                </tr>
				<?php
			}

			if ( function_exists( 'php_uname' ) ) {
				?>
                <tr>
                    <td><?php esc_html_e( 'Server Hostname', 'server-info' ); ?>:</td>
                    <td><?php echo php_uname( 'n' ); ?></td>
                </tr>
				<?php
			}

			if ( function_exists( 'phpversion' ) ) {
				?>
                <tr>
                    <td><?php esc_html_e( 'PHP Version', 'server-info' ); ?>:</td>
                    <td><?php echo phpversion(); ?></td>
                </tr>
				<?php
			}
			?>
            <tr>
                <td colspan="2" class="view-more-info">
                    <a class="button button-primary" href="<?php echo admin_url( 'options-general.php?page=server_info_display' ); ?>"><?php esc_html_e( 'View More Information', 'server-info' ); ?></a>
                </td>
            </tr>
        </table>
		<?php
	}

	/**
	 * Static function for generating site info.
	 *
	 * @return array
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
			$php64bit = ( PHP_INT_SIZE * 8 === 64 );

			$php_version = sprintf(
				'%s %s',
				$php_version_debug,
				( $php64bit ? esc_html__( '(Supports 64bit values)', 'server-info' ) : esc_html__( '(Does not support 64bit values)', 'server-info' ) )
			);
		} else {
			$php_version = esc_html__( 'Unable to determine PHP version', 'server-info' );
		}

		if ( function_exists( 'php_uname' ) ) {
			$server_architecture = sprintf( '%s %s %s', php_uname( 's' ), php_uname( 'r' ), php_uname( 'm' ) );
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

		if ( isset( $_SERVER['SERVER_ADDR'] ) ) {
			$info['wp-server']['fields']['server_ip'] = array(
				'label' => esc_html__( 'Server IP', 'server-info' ),
				'value' => esc_html( $_SERVER['SERVER_ADDR'] )
			);
		}

		if ( isset( $_SERVER['SERVER_PROTOCOL'] ) ) {
			$info['wp-server']['fields']['server_protocol'] = array(
				'label' => esc_html__( 'Server Protocol', 'server-info' ),
				'value' => esc_html( $_SERVER['SERVER_PROTOCOL'] )
			);
		}

		if ( isset( $_SERVER['SERVER_ADMIN'] ) ) {
			$info['wp-server']['fields']['server_administrator'] = array(
				'label' => esc_html__( 'Server Administrator', 'server-info' ),
				'value' => esc_html( $_SERVER['SERVER_ADMIN'] )
			);
		}

		if ( isset( $_SERVER['SERVER_PORT'] ) ) {
			$info['wp-server']['fields']['server_web_port'] = array(
				'label' => esc_html__( 'Server Web Port', 'server-info' ),
				'value' => esc_html( $_SERVER['SERVER_PORT'] )
			);
		}

		$uptime = exec( "uptime", $system );
		if ( ! empty( $uptime ) ) {
			$info['wp-server']['fields']['system_uptime'] = array(
				'label' => esc_html__( 'System Uptime', 'server-info' ),
				'value' => esc_html( $uptime )
			);
		}

		$info['wp-server']['fields']['httpd_software'] = array(
			'label' => esc_html__( 'Web server', 'server-info' ),
			'value' => ( isset( $_SERVER['SERVER_SOFTWARE'] ) ? $_SERVER['SERVER_SOFTWARE'] : esc_html__( 'Unable to determine what web server software is used', 'server-info' ) )
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

		if ( isset( $_SERVER['GATEWAY_INTERFACE'] ) ) {
			$info['wp-server']['fields']['CGI_version'] = array(
				'label' => esc_html__( 'CGI Version', 'server-info' ),
				'value' => esc_html( $_SERVER['GATEWAY_INTERFACE'] )
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

		$server = $wpdb->get_var( 'SELECT VERSION()' );

		if ( isset( $wpdb->use_mysqli ) && $wpdb->use_mysqli ) {
			$client_version = $wpdb->dbh->client_info;
		} else {
			// phpcs:ignore WordPress.DB.RestrictedFunctions.mysql_mysql_get_client_info,PHPCompatibility.Extensions.RemovedExtensions.mysql_DeprecatedRemoved
			if ( preg_match( '|[0-9]{1,2}\.[0-9]{1,2}\.[0-9]{1,2}|', mysql_get_client_info(), $matches ) ) {
				$client_version = $matches[0];
			} else {
				$client_version = null;
			}
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
					esc_html__( '%1$s (%2$s)', 'server-info' ),
					$active_theme->name,
					$active_theme->stylesheet
				),
			),
		);

		// List all available plugins.
		$plugins          = get_plugins();
		$plugins_active   = array();
		$plugins_inactive = array();

		foreach ( $plugins as $plugin_path => $plugin ) {
			$plugin_author = $plugin['Author'];

			if ( ! empty( $plugin_author ) ) {
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

		return $info;
	}

	/**
	 * Displays the plugin content
	 *
	 * @return void
	 */
	public static function display_server_info() {
		?>
        <div class="wrap server-info">
            <h2 class="infohouse_heading"><?php esc_html_e( 'Server Information', 'server-info' ); ?></h2>
            <hr />
            <p><?php esc_html_e( 'Server Info plugin shows the general information about the hosting server your WordPress site is currently hosted on. You can find this information helpful for many purposes like performance improvements and so on.', 'server-info' ); ?></p><br />
            <div class="infohouse_settings_page">
                <div class="table-responsive">
					<?php
					$info = Self::site_info();
					foreach ( $info as $details ) {
						if ( ! isset( $details['fields'] ) || empty( $details['fields'] ) ) {
							continue;
						}
						?>
                        <table class="table widefat striped infohouse_table">
                            <tbody>
                            <tr>
                                <th colspan="2"><h3 class="server-info-heading"><?php echo esc_html( $details['label'] ); ?></h3></th>
                            </tr>
							<?php
							foreach ( $details['fields'] as $field ) {
								if ( is_array( $field['value'] ) ) {
									$values = '<ul>';
									foreach ( $field['value'] as $name => $value ) {
										$values .= sprintf( '<li>%s<br /><span>%s</span></li>', esc_html( $name ), esc_html( $value ) );
									}
									$values .= '</ul>';
								} else {
									$values = esc_html( $field['value'] );
								}

								printf( '<tr><td>%s</td><td>%s</td></tr>', esc_html( $field['label'] ), $values );
							}
							?>
                            </tbody>
                        </table>
						<?php
					}
					?>
                </div>
            </div>
        </div>
		<?php
	}
}

// Instantiate the ServerInfo class
$Server_Info = Server_Info::getInstance();