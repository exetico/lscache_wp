<?php
/**
 * The admin-panel specific functionality of the plugin.
 *
 *
 * @since      1.0.0
 * @package    LiteSpeed
 * @subpackage LiteSpeed/admin
 * @author     LiteSpeed Technologies <info@litespeedtech.com>
 */
namespace LiteSpeed;

defined( 'WPINC' ) || exit;

class Admin_Display extends Base {
	protected static $_instance;

	const NOTICE_BLUE = 'notice notice-info';
	const NOTICE_GREEN = 'notice notice-success';
	const NOTICE_RED = 'notice notice-error';
	const NOTICE_YELLOW = 'notice notice-warning';
	const DB_MSG = 'messages';

	const PURGEBY_CAT = '0';
	const PURGEBY_PID = '1';
	const PURGEBY_TAG = '2';
	const PURGEBY_URL = '3';

	const PURGEBYOPT_SELECT = 'purgeby';
	const PURGEBYOPT_LIST = 'purgebylist';

	const DB_DISMISS_MSG = 'dismiss';
	const RULECONFLICT_ON = 'ExpiresDefault_1';
	const RULECONFLICT_DISMISSED = 'ExpiresDefault_0';

	protected $__cfg;
	protected $messages = array();
	protected $default_settings = array();
	protected $_is_network_admin = false;
	protected $_is_multisite = false;

	/**
	 * Initialize the class and set its properties.
	 *
	 * @since    1.0.7
	 * @access   protected
	 */
	protected function __construct() {
		// load assets
		if( ! empty( $_GET[ 'page' ] ) && ( strpos( $_GET[ 'page' ], 'litespeed-' ) === 0 || $_GET[ 'page' ] == 'litespeed' ) ) {
			add_action( 'admin_enqueue_scripts', array( $this, 'load_assets' ) );
		}

		// main css
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_style' ) );
		// Main js
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );

		$this->_is_network_admin = is_network_admin();
		$this->_is_multisite = is_multisite();

		// Quick access menu
		if ( is_multisite() && $this->_is_network_admin ) {
			$manage = 'manage_network_options';
		}
		else {
			$manage = 'manage_options';
		}
		if ( current_user_can( $manage ) ) {
			if ( ! defined( 'LITESPEED_DISABLE_ALL' ) ) {
				add_action( 'wp_before_admin_bar_render', array( GUI::get_instance(), 'backend_shortcut' ) );
			}

			// `admin_notices` is after `admin_enqueue_scripts`
			// @see wp-admin/admin-header.php
			add_action( $this->_is_network_admin ? 'network_admin_notices' : 'admin_notices', array( $this, 'display_messages' ) );
		}

		/**
		 * In case this is called outside the admin page
		 * @see  https://codex.wordpress.org/Function_Reference/is_plugin_active_for_network
		 * @since  2.0
		 */
		if ( ! function_exists( 'is_plugin_active_for_network' ) ) {
			require_once( ABSPATH . '/wp-admin/includes/plugin.php' );
		}

		// add menus ( Also check for mu-plugins)
		if ( $this->_is_network_admin && ( is_plugin_active_for_network( LSCWP_BASENAME ) || defined( 'LSCWP_MU_PLUGIN' ) ) ) {
			add_action( 'network_admin_menu', array( $this, 'register_admin_menu' ) );
		}
		else {
			add_action( 'admin_menu', array( $this, 'register_admin_menu' ) );
		}

		$this->__cfg = Conf::get_instance();
	}

	/**
	 * Load LiteSpeed assets
	 *
	 * @since    1.1.0
	 * @access public
	 * @param  array $hook WP hook
	 */
	public function load_assets($hook) {
		// Admin footer
		add_filter('admin_footer_text', array($this, 'admin_footer_text'), 1);

		if( defined( 'LITESPEED_ON' ) ) {
			// Help tab
			$this->add_help_tabs();

			global $pagenow;
			if ( $pagenow === 'plugins.php' ) {//todo: check if work
				add_action('wp_default_scripts', array($this, 'set_update_text'), 0);
				add_action('wp_default_scripts', array($this, 'unset_update_text'), 20);
			}
		}

		wp_register_script( Core::PLUGIN_NAME . '-lib-vue', LSWCP_PLUGIN_URL . 'assets/js/vue.min.js', array(), Core::VER, false );
		wp_enqueue_script( Core::PLUGIN_NAME . '-lib-vue' );
	}

	/**
	 * Show the title of one line
	 *
	 * @since  3.0
	 * @access public
	 */
	public function title( $id ) {
		echo Lang::title( $id );
	}

	/**
	 * Register the admin menu display.
	 *
	 * @since    1.0.0
	 * @access public
	 */
	public function register_admin_menu() {
		$capability = $this->_is_network_admin ? 'manage_network_options' : 'manage_options';
		if ( current_user_can( $capability ) ) {

			// root menu
			add_menu_page( 'LiteSpeed Cache', 'LiteSpeed Cache', 'manage_options', 'litespeed' );

			// sub menus
			$this->_add_submenu( __( 'Dashboard', 'litespeed-cache' ), 'litespeed', 'show_menu_dash' );

			$this->_add_submenu( __( 'General', 'litespeed-cache' ), 'litespeed-general', 'show_menu_general' );

			$this->_add_submenu( __( 'Cache', 'litespeed-cache' ), 'litespeed-cache', 'show_menu_cache' );

			! $this->_is_network_admin && $this->_add_submenu( __( 'CDN', 'litespeed-cache' ), 'litespeed-cdn', 'show_menu_cdn' );

			$this->_add_submenu( __( 'Image Optimization', 'litespeed-cache' ), 'litespeed-img_optm', 'show_img_optm' );

			! $this->_is_network_admin && $this->_add_submenu( __( 'Page Optimization', 'litespeed-cache' ), 'litespeed-page_optm', 'show_page_optm' );

			$this->_add_submenu( __( 'Database', 'litespeed-cache' ), 'litespeed-db_optm', 'show_db_optm' );

			! $this->_is_network_admin && $this->_add_submenu( __( 'Crawler', 'litespeed-cache' ), 'litespeed-crawler', 'show_crawler' );

			$this->_add_submenu( __( 'Toolbox', 'litespeed-cache' ), 'litespeed-toolbox', 'show_toolbox' );

			// sub menus under options
			add_options_page('LiteSpeed Cache', 'LiteSpeed Cache', $capability, 'litespeed-cache-options', array($this, 'show_menu_cache'));
		}
	}

	/**
	 * Helper function to set up a submenu page.
	 *
	 * @since 1.0.4
	 * @access private
	 * @param string $menu_title The title that appears on the menu.
	 * @param string $menu_slug The slug of the page.
	 * @param string $callback The callback to call if selected.
	 */
	private function _add_submenu( $menu_title, $menu_slug, $callback ) {
		add_submenu_page( 'litespeed', $menu_title, $menu_title, 'manage_options', $menu_slug, array( $this, $callback ) );
	}

	/**
	 * Register the stylesheets for the admin area.
	 *
	 * @since    1.0.14
	 * @access public
	 */
	public function enqueue_style() {
		wp_enqueue_style(Core::PLUGIN_NAME, LSWCP_PLUGIN_URL . 'assets/css/litespeed.css', array(), Core::VER, 'all');
	}

	/**
	 * Register the JavaScript for the admin area.
	 *
	 * @since    1.0.0
	 * @access public
	 */
	public function enqueue_scripts() {
		wp_register_script( Core::PLUGIN_NAME, LSWCP_PLUGIN_URL . 'assets/js/litespeed-cache-admin.js', array(), Core::VER, false );

		$localize_data = array();
		if ( GUI::has_whm_msg() ) {
			$ajax_url_dismiss_whm = Utility::build_url( Core::ACTION_DISMISS, GUI::TYPE_DISMISS_WHM, true );
			$localize_data[ 'ajax_url_dismiss_whm' ] = $ajax_url_dismiss_whm;
		}

		if ( GUI::has_msg_ruleconflict() ) {
			$ajax_url = Utility::build_url( Core::ACTION_DISMISS, GUI::TYPE_DISMISS_EXPIRESDEFAULT, true );
			$localize_data[ 'ajax_url_dismiss_ruleconflict' ] = $ajax_url;
		}

		$promo_tag = GUI::get_instance()->show_promo( true );
		if ( $promo_tag ) {
			$ajax_url_promo = Utility::build_url( Core::ACTION_DISMISS, GUI::TYPE_DISMISS_PROMO, true, null, array( 'promo_tag' => $promo_tag ) );
			$localize_data[ 'ajax_url_promo' ] = $ajax_url_promo;
		}

		// If on Server IP setting page, append getIP link
		global $pagenow;
		if ( $pagenow == 'admin.php' && ! empty( $_GET[ 'page' ] ) && $_GET[ 'page' ] == 'litespeed-general' ) {
			$localize_data[ 'ajax_url_getIP' ] = function_exists( 'get_rest_url' ) ? get_rest_url( null, 'litespeed/v1/tool/check_ip' ) : '/';
			$localize_data[ 'nonce' ] = wp_create_nonce( 'wp_rest' );
		}
		if ( ( $pagenow == 'admin.php' && ! empty( $_GET[ 'page' ] ) && $_GET[ 'page' ] == 'litespeed-cache' )
			|| ( $pagenow == 'options-general.php' && ! empty( $_GET[ 'page' ] ) && $_GET[ 'page' ] == 'litespeed-cache-options' )
		) {
			$localize_data[ 'ajax_url_fetch_esi_nonce' ] = function_exists( 'get_rest_url' ) ? get_rest_url( null, 'litespeed/v1/fetch_esi_nonce' ) : '/';
			$localize_data[ 'nonce' ] = wp_create_nonce( 'wp_rest' );
		}

		if ( $localize_data ) {
			wp_localize_script(Core::PLUGIN_NAME, 'litespeed_data', $localize_data );
		}

		wp_enqueue_script( Core::PLUGIN_NAME );
	}

	/**
	 * Callback that adds LiteSpeed Cache's action links.
	 *
	 * @since 1.0.0
	 * @access public
	 * @param array $links Previously added links from other plugins.
	 * @return array Links array with the litespeed cache one appended.
	 */
	public function add_plugin_links($links) {
		// $links[] = '<a href="' . admin_url('options-general.php?page=litespeed-cache') . '">' . __('Settings', 'litespeed-cache') . '</a>';
		$links[] = '<a href="' . admin_url('admin.php?page=litespeed-cache') . '">' . __('Settings', 'litespeed-cache') . '</a>';

		return $links;
	}

	/**
	 * Add text to recommend updating upon update success.
	 *
	 * @since 1.0.8.1
	 * @access public
	 * @param string $translations
	 * @param string $text
	 * @return string
	 */
	public function add_update_text($translations, $text) {
		if ( $text !== 'Updated!' ) {
			return $translations;
		}

		return $translations . ' ' . __('It is recommended that LiteSpeed Cache be purged after updating a plugin.', 'litespeed-cache');
	}

	/**
	 * Add the filter to update plugin update text.
	 *
	 * @since 1.0.8.1
	 * @access public
	 */
	public function set_update_text() {
		add_filter('gettext', array($this, 'add_update_text'), 10, 2);
	}

	/**
	 * Remove the filter to update plugin update text.
	 *
	 * @since 1.0.8.1
	 * @access public
	 */
	public function unset_update_text() {
		remove_filter('gettext', array($this, 'add_update_text'));
	}

	/**
	 * Change the admin footer text on LiteSpeed Cache admin pages.
	 *
	 * @since  1.0.13
	 * @param  string $footer_text
	 * @return string
	 */
	public function admin_footer_text($footer_text) {
		require_once LSCWP_DIR . 'tpl/inc/admin_footer.php';

		return $footer_text;
	}

	/**
	 * Displays the help tab in the admin pages.
	 *
	 * @since 1.0.0
	 * @access public
	 */
	public function add_help_tabs() {
		require_once LSCWP_DIR . 'tpl/inc/help_tabs.php';
	}

	/**
	 * Builds the html for a single notice.
	 *
	 * @since 1.0.7
	 * @access public
	 * @param string $color The color to use for the notice.
	 * @param string $str The notice message.
	 * @return string The built notice html.
	 */
	public static function build_notice($color, $str) {
		return '<div class="' . $color . ' is-dismissible"><p>'. $str . '</p></div>';
	}

	/**
	 * Display info notice
	 *
	 * @since 1.6.5
	 * @access public
	 */
	public static function info( $msg, $echo = false ) {
		self::add_notice( self::NOTICE_BLUE, $msg, $echo );
	}

	/**
	 * Display note notice
	 *
	 * @since 1.6.5
	 * @access public
	 */
	public static function note( $msg, $echo = false ) {
		self::add_notice( self::NOTICE_YELLOW, $msg, $echo );
	}

	/**
	 * Display success notice
	 *
	 * @since 1.6
	 * @access public
	 */
	public static function succeed( $msg, $echo = false ) {
		self::add_notice( self::NOTICE_GREEN, $msg, $echo );
	}

	/**
	 * Display error notice
	 *
	 * @since 1.6
	 * @access public
	 */
	public static function error( $msg, $echo = false ) {
		self::add_notice( self::NOTICE_RED, $msg, $echo );
	}

	/**
	 * Adds a notice to display on the admin page
	 *
	 * @since 1.0.7
	 * @access public
	 */
	public static function add_notice( $color, $msg, $echo = false ) {
		// Bypass adding for CLI or cron
		if ( defined( 'LITESPEED_CLI' ) || defined( 'DOING_CRON' ) ) {
			// WP CLI will show the info directly
			if ( defined( 'WP_CLI' ) && WP_CLI ) {
				$msg = strip_tags( $msg );
				if ( $color == self::NOTICE_RED ) {
					\WP_CLI::error( $msg, false );
				}
				else {
					\WP_CLI::success( $msg );
				}
			}
			return;
		}

		if ( $echo ) {
			echo self::build_notice( $color, $msg );
			return;
		}

		$messages = self::get_option( self::DB_MSG );

		if ( is_array($msg) ) {
			foreach ($msg as $str) {
				$messages[] = self::build_notice($color, $str);
			}
		}
		else {
			$messages[] = self::build_notice($color, $msg);
		}
		self::update_option( self::DB_MSG, $messages );
	}

	/**
	 * Display notices and errors in dashboard
	 *
	 * @since 1.1.0
	 * @access public
	 */
	public function display_messages() {
		if ( GUI::has_whm_msg() ) {
			$this->show_display_installed();
		}

		Data::get_instance()->check_upgrading_msg();

		// If is in dev version, always check latest update
		Cloud::get_instance()->check_dev_version();

		// One time msg
		$messages = self::get_option( self::DB_MSG );
		if( is_array($messages) ) {
			$messages = array_unique($messages);

			$added_thickbox = false;
			foreach ($messages as $msg) {
				// Added for popup links
				if ( strpos( $msg, 'TB_iframe' ) && ! $added_thickbox ) {
					add_thickbox();
					$added_thickbox = true;
				}
				echo $msg;
			}
		}
		self::delete_option( self::DB_MSG );

		if( empty( $_GET[ 'page' ] ) || strpos( $_GET[ 'page' ], 'litespeed' ) !== 0 ) {
			global $pagenow;
			if ( $pagenow != 'plugins.php' ) { // && $pagenow != 'index.php'
				return;
			}
		}

		// Show disable all warning
		if ( defined( 'LITESPEED_DISABLE_ALL' ) ) {
			Admin_Display::error( Error::msg( 'disabled_all' ), true );
		}

		if ( ! Conf::val( Base::O_NEWS ) ) {
			return;
		}

		// Show promo from cloud
		Cloud::get_instance()->show_promo();

		/**
		 * Check promo msg first
		 * @since 2.9
		 */
		GUI::get_instance()->show_promo();

		// Show version news
		Cloud::get_instance()->news();
	}

	/**
	 * Hooked to the in_widget_form action.
	 * Appends LiteSpeed Cache settings to the widget edit settings screen.
	 * This will append the esi on/off selector and ttl text.
	 *
	 * @since 1.1.0
	 * @access public
	 */
	public function show_widget_edit($widget, $return, $instance) {
		require LSCWP_DIR . 'tpl/esi_widget_edit.php';
	}

	/**
	 * Displays the dashboard page.
	 *
	 * @since 3.0
	 * @access public
	 */
	public function show_menu_dash() {
		require_once LSCWP_DIR . 'tpl/dash/entry.tpl.php';
	}

	/**
	 * Displays the General page.
	 *
	 * @since 3.0
	 * @access public
	 */
	public function show_menu_general() {
		require_once LSCWP_DIR . 'tpl/general/entry.tpl.php';
	}

	/**
	 * Displays the CDN page.
	 *
	 * @since 3.0
	 * @access public
	 */
	public function show_menu_cdn() {
		require_once LSCWP_DIR . 'tpl/cdn/entry.tpl.php';
	}

	/**
	 * Outputs the LiteSpeed Cache settings page.
	 *
	 * @since 1.0.0
	 * @access public
	 */
	public function show_menu_cache() {
		if ( $this->_is_network_admin ) {
			require_once LSCWP_DIR . 'tpl/cache/entry_network.tpl.php';
		}
		else {
			require_once LSCWP_DIR . 'tpl/cache/entry.tpl.php';
		}
	}

	/**
	 * Tools page
	 *
	 * @since 3.0
	 * @access public
	 */
	public function show_toolbox() {
		require_once LSCWP_DIR . 'tpl/toolbox/entry.tpl.php';
	}

	/**
	 * Outputs the crawler operation page.
	 *
	 * @since 1.1.0
	 * @access public
	 */
	public function show_crawler() {
		require_once LSCWP_DIR . 'tpl/crawler/entry.tpl.php';
	}

	/**
	 * Outputs the optimization operation page.
	 *
	 * @since 1.6
	 * @access public
	 */
	public function show_img_optm() {
		require_once LSCWP_DIR . 'tpl/img_optm/entry.tpl.php';
	}

	/**
	 * Page optm page.
	 *
	 * @since 3.0
	 * @access public
	 */
	public function show_page_optm() {
		require_once LSCWP_DIR . 'tpl/page_optm/entry.tpl.php';
	}

	/**
	 * DB optm page.
	 *
	 * @since 3.0
	 * @access public
	 */
	public function show_db_optm() {
		require_once LSCWP_DIR . 'tpl/db_optm/entry.tpl.php';
	}

	/**
	 * Outputs a notice to the admin panel when the plugin is installed
	 * via the WHM plugin.
	 *
	 * @since 1.0.12
	 * @access public
	 */
	public function show_display_installed() {
		require_once LSCWP_DIR . 'tpl/inc/show_display_installed.php';
	}

	/**
	 * Display error cookie msg.
	 *
	 * @since 1.0.12
	 * @access public
	 */
	public static function show_error_cookie() {
		require_once LSCWP_DIR . 'tpl/inc/show_error_cookie.php';
	}

	/**
	 * Display warning if lscache is disabled
	 *
	 * @since 2.1
	 * @access public
	 */
	public function cache_disabled_warning() {
		include LSCWP_DIR . "tpl/inc/check_cache_disabled.php";
	}

	/**
	 * Output litespeed form info
	 *
	 * @since    3.0
	 * @access public
	 */
	public function form_action( $action = false, $type = false, $has_upload = false ) {
		if ( ! $action ) {
			$action = Router::ACTION_SAVE_SETTINGS;
		}

		$has_upload = $has_upload ? 'enctype="multipart/form-data"' : '';

		echo '<form method="post" action="' . wp_unslash( $_SERVER[ 'REQUEST_URI' ] ) . '" class="litespeed-relative" ' . $has_upload . '>';

		echo '<input type="hidden" name="' . Router::ACTION . '" value="' . $action . '" />';
		if ( $type ) {
			echo '<input type="hidden" name="' . Router::TYPE . '" value="' . $type . '" />';
		}
		wp_nonce_field( $action, Router::NONCE );
	}

	/**
	 * Output litespeed form info END
	 *
	 * @since    3.0
	 * @access public
	 */
	public function form_end( $disable_reset = false ) {
		echo "<div class='litespeed-top20'></div>";
		submit_button( __( 'Save Changes', 'litespeed-cache' ), 'primary litespeed-duplicate-float', 'litespeed-submit' );

		echo '</form>';
	}

	/**
	 * Register this setting to save
	 *
	 * @since  3.0
	 * @access public
	 */
	public function enroll( $id ) {
		echo '<input type="hidden" name="' . Admin_Settings::ENROLL . '[]" value="' . $id . '" />';
	}

	/**
	 * Build a textarea
	 *
	 * @since 1.1.0
	 * @access public
	 */
	public function build_textarea( $id, $cols = false, $val = null ) {
		if ( $val === null ) {
			$val = Conf::val( $id, true );

			if ( is_array( $val ) ) {
				$val = implode( "\n", $val );
			}
		}

		if ( ! $cols ) {
			$cols = 80;
		}

		$rows = 5;
		$lines = substr_count( $val, "\n" ) + 2;
		if ( $lines > $rows ) {
			$rows = $lines;
		}
		if ( $rows > 40 ) {
			$rows = 40;
		}

		$this->enroll( $id );

		echo "<textarea name='$id' rows='$rows' cols='$cols'>" . esc_textarea( $val ) . "</textarea>";

		$this->_check_overwritten( $id );
	}

	/**
	 * Build a text input field
	 *
	 * @since 1.1.0
	 * @access public
	 */
	public function build_input( $id, $cls = null, $val = null, $type = 'text', $disabled = false ) {
		if ( $val === null ) {
			$val = Conf::val( $id, true );

			// Mask pswds
			if ( $this->_conf_pswd( $id ) && $val ) {
				$val = str_repeat( '*', strlen( $val ) );
			}
		}

		$label_id = preg_replace( '|\W|', '', $id );

		if ( $type == 'text' ) {
			$cls = "regular-text $cls";
		}

		if ( $disabled ) {
			echo "<input type='$type' class='$cls' value='" . esc_textarea( $val ) ."' id='input_$label_id' disabled /> ";
		}
		else {
			$this->enroll( $id );
			echo "<input type='$type' class='$cls' name='$id' value='" . esc_textarea( $val ) ."' id='input_$label_id' /> ";
		}

		$this->_check_overwritten( $id );
	}

	/**
	 * Build a checkbox html snippet
	 *
	 * @since 1.1.0
	 * @access public
	 * @param  string $id
	 * @param  string $title
	 * @param  bool $checked
	 */
	public function build_checkbox( $id, $title, $checked = null, $value = 1 ) {
		if ( $checked === null && Conf::val( $id, true ) ) {
			$checked = true;
		}
		$checked = $checked ? ' checked ' : '';

		$label_id = preg_replace( '|\W|', '', $id );

		if ( $value !== 1 ) {
			$label_id .= '_' . $value;
		}

		$this->enroll( $id );

		echo "<div class='litespeed-tick'>
			<input type='checkbox' name='$id' id='input_checkbox_$label_id' value='$value' $checked />
			<label for='input_checkbox_$label_id'>$title</label>
		</div>";

		$this->_check_overwritten( $id );
	}

	/**
	 * Build a toggle checkbox html snippet
	 *
	 * @since 1.7
	 */
	public function build_toggle( $id, $checked = null, $title_on = null, $title_off = null ) {
		if ( $checked === null && Conf::val( $id, true ) ) {
			$checked = true;
		}

		if ( $title_on === null ) {
			$title_on = __( 'ON', 'litespeed-cache' );
			$title_off = __( 'OFF', 'litespeed-cache' );
		}

		$cls = $checked ? 'primary' : 'default litespeed-toggleoff';

		$this->enroll( $id );

		echo "<div class='litespeed-toggle litespeed-toggle-btn litespeed-toggle-btn-$cls' data-litespeed-toggle-on='primary' data-litespeed-toggle-off='default'>
				<input name='$id' type='hidden' value='$checked' />
				<div class='litespeed-toggle-group'>
					<label class='litespeed-toggle-btn litespeed-toggle-btn-primary litespeed-toggle-on'>$title_on</label>
					<label class='litespeed-toggle-btn litespeed-toggle-btn-default litespeed-toggle-active litespeed-toggle-off'>$title_off</label>
					<span class='litespeed-toggle-handle litespeed-toggle-btn litespeed-toggle-btn-default'></span>
				</div>
			</div>";

		$this->_check_overwritten( $id );
	}

	/**
	 * Build a switch div html snippet
	 *
	 * @since 1.1.0
	 * @since 1.7 removed param $disable
	 * @access public
	 */
	public function build_switch( $id, $title_list = false ) {
		$this->enroll( $id );

		echo '<div class="litespeed-switch">';

		if ( ! $title_list ) {
			$title_list = array(
				__( 'OFF', 'litespeed-cache' ),
				__( 'ON', 'litespeed-cache' ),
			);
		}

		foreach ( $title_list as $k => $v ) {
			$this->_build_radio( $id, $k, $v );
		}

		echo '</div>';

		$this->_check_overwritten( $id );
	}

	/**
	 * Build a radio input html codes and output
	 *
	 * @since 1.1.0
	 * @access private
	 */
	private function _build_radio( $id, $val, $txt ) {
		$id_attr = 'input_radio_' . preg_replace( '|\W|', '', $id ) . '_' . $val;

		$default = isset( self::$_default_options[ $id ] ) ? self::$_default_options[ $id ] : self::$_default_site_options[ $id ];

		if ( ! is_string( $default ) ) {
			$checked = (int) Conf::val( $id, true ) === (int) $val ? ' checked ' : '';
		}
		else {
			$checked = Conf::val( $id, true ) === $val ? ' checked ' : '';
		}

		echo "<input type='radio' autocomplete='off' name='$id' id='$id_attr' value='$val' $checked /> <label for='$id_attr'>$txt</label>";
	}

	/**
	 * Show overwritten msg if there is a const defined
	 *
	 * @since  3.0
	 */
	protected function _check_overwritten( $id ) {
		$const_val = $this->__cfg->const_overwritten( $id );
		$primary_val = $this->__cfg->primary_overwritten( $id );
		if ( $const_val === null && $primary_val === null ) {
			return;
		}

		$val = $const_val !== null ? $const_val : $primary_val;

		$default = isset( self::$_default_options[ $id ] ) ? self::$_default_options[ $id ] : self::$_default_site_options[ $id ];

		if ( is_bool( $default ) ) {
			$val = $val ? __( 'ON', 'litespeed-cache' ) : __( 'OFF', 'litespeed-cache' );
		}
		else {
			if ( is_array( $default ) ) {
				$val = implode( "\n", $val );
			}
			$val = esc_textarea( $val );
		}

		echo '<div class="litespeed-desc litespeed-warning">⚠️ ';

		if ( $const_val !== null ) {
			echo sprintf( __( 'This setting is overwritten by the PHP constant %s', 'litespeed-cache' ), '<code>' . Base::conf_const( $id ) . '</code>' );
		} else {
			if ( get_current_blog_id() != BLOG_ID_CURRENT_SITE ) {
				echo __( 'This setting is overwritten by the primary site setting', 'litespeed-cache' );
			}
			else {
				echo __( 'This setting is overwritten by the Network setting', 'litespeed-cache' );
			}
		}

		echo ', ' . sprintf( __( 'currently set to %s', 'litespeed-cache' ), "<code>$val</code>" ) . '</div>';
	}

	/**
	 * Display seconds text and readable layout
	 *
	 * @since 3.0
	 * @access public
	 */
	public function readable_seconds() {
		echo __( 'seconds', 'litespeed-cache' );
		echo ' <span data-litespeed-readable=""></span>';
	}

	/**
	 * Display default value
	 *
	 * @since  1.1.1
	 * @access public
	 */
	public function recommended( $id ) {
		if ( ! $this->default_settings ) {
			$this->default_settings = $this->__cfg->load_default_vals();
		}

		$val = $this->default_settings[ $id ];

		if ( $val ) {
			if ( is_array( $val ) ) {
				$rows = 5;
				$cols = 30;
				// Flexible rows/cols
				$lines = count( $val ) + 1;
				$rows = min( max( $lines, $rows ), 40 );
				foreach ( $val as $v ) {
					$cols = max( strlen( $v ), $cols );
				}
				$cols = min( $cols, 150 );

				$val = implode( "\n", $val );
				$val = esc_textarea( $val );
				$val = '<div class="litespeed-desc">' . __( 'Recommended value', 'litespeed-cache' ) . ':</div>' . "<textarea readonly rows='$rows' cols='$cols'>$val</textarea>";
			}
			else {
				$val = esc_textarea( $val );
				$val = "<code>$val</code>";
				$val = __( 'Recommended value', 'litespeed-cache' ) . ': '.$val;
			}
			echo  $val;
		}
	}

	/**
	 * Validate rewrite rules regex syntax
	 *
	 * @since  3.0
	 */
	protected function _validate_syntax( $id ) {
		$val = Conf::val( $id, true );

		if ( ! $val ) {
			return;
		}

		if ( ! is_array( $val ) ) {
			$val = array( $val );
		}

		foreach ( $val as $v ) {
			if ( ! Utility::syntax_checker( $v ) ) {
				echo '<br /><font class="litespeed-warning"> ❌ ' . __( 'Invalid rewrite rule', 'litespeed-cache' ) . ': <code>' . $v . '</code></font>';
			}
		}
	}

	/**
	 * Validate if the htaccess path is valid
	 *
	 * @since  3.0
	 */
	protected function _validate_htaccess_path( $id ) {
		$val = Conf::val( $id, true );
		if ( ! $val ) {
			return;
		}

		if ( substr( $val, -10 ) !== '/.htaccess' ) {
			echo '<br /><font class="litespeed-warning"> ❌ ' . sprintf( __( 'Path must end with %s', 'litespeed-cache' ), '<code>/.htaccess</code>' ) . '</font>';
		}
	}

	/**
	 * Check ttl instead of error when saving
	 *
	 * @since  3.0
	 */
	protected function _validate_ttl( $id, $min = false, $max = false, $allow_zero = false ) {
		$val = Conf::val( $id, true );

		if ( $allow_zero && ! $val ) {
			// return;
		}

		$tip = array();
		if ( $min && $val < $min && ( ! $allow_zero || $val != 0 ) ) {
			$tip[] = __( 'Minimum value', 'litespeed-cache' ) . ': <code>' . $min . '</code>.';
		}
		if ( $max && $val > $max ) {
			$tip[] = __( 'Maximum value', 'litespeed-cache' ) . ': <code>' . $max . '</code>.';
		}

		echo '<br />';

		if ( $tip ) {
			echo '<font class="litespeed-warning"> ❌ ' . implode( ' ', $tip ) . '</font>';
		}

		$range = '';

		if ( $allow_zero ) {
			$range .= __( 'Zero, or', 'litespeed-cache' ) . ' ';
		}

		if ( $min && $max ) {
			$range .= $min . ' - ' . $max;
		}
		elseif ( $min ) {
			$range .= __( 'Larger than', 'litespeed-cache' ) . ' ' . $min;
		}
		elseif ( $max ) {
			$range .= __( 'Smaller than', 'litespeed-cache' ) . ' ' . $max;
		}

		echo __( 'Value range', 'litespeed-cache' ) . ': <code>' . $range . '</code>';
	}

	/**
	 * Check if ip is valid
	 *
	 * @since  3.0
	 */
	protected function _validate_ip( $id ) {
		$val = Conf::val( $id, true );
		if ( ! $val ) {
			return;
		}

		if ( ! is_array( $val ) ) {
			$val = array( $val );
		}

		$tip = array();
		foreach ( $val as $v ) {
			if ( ! $v ) {
				continue;
			}

			if ( ! \WP_Http::is_ip_address( $v ) ) {
				$tip[] = __( 'Invalid IP', 'litespeed-cache' ) . ': <code>' . $v . '</code>.';
			}
		}

		if ( $tip ) {
			echo '<br /><font class="litespeed-warning"> ❌ ' . implode( ' ', $tip ) . '</font>';
		}
	}

	/**
	 * Display API environment variable support
	 *
	 * @since  1.8.3
	 * @access protected
	 */
	protected function _api_env_var() {
		$args = func_get_args();
		$s = '<code>' . implode( '</code>, <code>', $args ) . '</code>';

		echo '<font class="litespeed-success"> '
			. __( 'API', 'litespeed-cache' ) . ': '
			. sprintf( __( 'Server variable(s) %s available to override this setting.', 'litespeed-cache' ), $s );

		Doc::learn_more( 'https://docs.litespeedtech.com/lscache/lscwp/admin/#limiting-the-crawler' );
	}

	/**
	 * Display URI setting example
	 *
	 * @since  2.6.1
	 * @access protected
	 */
	protected function _uri_usage_example() {
		echo __( 'The URLs will be compared to the REQUEST_URI server variable.', 'litespeed-cache' );
		echo ' ' . sprintf( __( 'For example, for %s, %s can be used here.', 'litespeed-cache' ), '<code>/mypath/mypage?aa=bb</code>', '<code>mypage?aa=</code>' );
		echo '<br /><i>';
			echo sprintf( __( 'To match the beginning, add %s to the beginning of the item.', 'litespeed-cache' ), '<code>^</code>' );
			echo ' ' . sprintf( __( 'To do an exact match, add %s to the end of the URL.', 'litespeed-cache' ), '<code>$</code>' );
			echo ' ' . __( 'One per line.', 'litespeed-cache' );
		echo '</i>';
	}

	/**
	 * Return groups string
	 *
	 * @since  2.0
	 * @access public
	 */
	public static function print_plural( $num, $kind = 'group' ) {
		if ( $num > 1 ) {
			switch ( $kind ) {
				case 'group' :
					return sprintf( __( '%s groups', 'litespeed-cache' ), $num );

				case 'image' :
					return sprintf( __( '%s images', 'litespeed-cache' ), $num );

				default:
					return $num;
			}

		}

		switch ( $kind ) {
			case 'group' :
				return sprintf( __( '%s group', 'litespeed-cache' ), $num );

			case 'image' :
				return sprintf( __( '%s image', 'litespeed-cache' ), $num );

			default:
				return $num;
		}
	}

	/**
	 * Return guidance html
	 *
	 * @since  2.0
	 * @access public
	 */
	public static function guidance( $title, $steps, $current_step ) {
		if ( $current_step === 'done' ) {
			$current_step = count( $steps ) + 1;
		}

		$percentage = ' (' . floor( ( $current_step - 1 ) * 100 / count( $steps ) ) . '%)';

		$html = '<div class="litespeed-guide">'
					. '<h2>' . $title . $percentage . '</h2>'
					. '<ol>';
		foreach ( $steps as $k => $v ) {
			$step = $k + 1;
			if ( $current_step > $step ) {
				$html .= '<li class="litespeed-guide-done">';
			}
			else {
				$html .= '<li>';
			}
			$html .= $v . '</li>';
		}

		$html .= '</ol></div>';

		return $html;
	}
}
