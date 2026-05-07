<?php
/**
 * =======================================
 * Advanced Custom Fields Font Awesome Admin
 * =======================================
 * 
 * 
 * @author Matt Keys <https://profiles.wordpress.org/mattkeys>
 */

class ACFFA_Admin
{
	private $version;

	public function init()
	{
		$this->version = 'v' . ACFFA_MAJOR_VERSION;

		add_action( 'admin_notices', [ $this, 'show_upgrade_notice' ] );
		add_action( 'admin_notices', [ $this, 'maybe_notify_cdn_error' ] );
		add_filter( 'plugin_action_links', [ $this, 'add_settings_link' ], 10, 2 );
		add_action( 'admin_menu', [ $this, 'add_settings_page' ], 100 );
		add_action( 'admin_init', [ $this, 'register_settings' ] );
		add_filter( 'pre_update_option_acffa_settings', [ $this, 'intercept_icon_set_save' ], 10, 2 );
		add_filter( 'pre_update_option_acffa_settings', [ $this, 'maybe_refresh_icons' ], 20, 2 );
		add_filter( 'pre_update_option_acffa_settings', [ $this, 'revoke_access_token' ], 20, 2 );
		add_filter( 'pre_update_option_acffa_settings', [ $this, 'clear_search_config_cache' ], 20, 2 );
		add_filter( 'pre_update_option_acffa_settings', [ $this, 'check_kits_settings' ], 25, 2 );
		add_action( 'update_option_acffa_settings', [ $this, 'get_latest_version' ], 10, 3 );
		add_action( 'admin_init', [ $this, 'check_kits_api_key_filter'], 10 );
		add_action( 'wp_ajax_ACFFA_delete_icon_set', [ $this, 'ajax_remove_icon_set' ] );
		add_filter( 'ACFFA_show_fontawesome_pro_blurbs', [ $this, 'hide_fontawesome_pro_blurbs' ], 5, 1 );

		if ( version_compare( ACFFA_MAJOR_VERSION, 6, '>=' ) ) {
			add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_acf_select2' ] );
			add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_scripts_v6' ] );
		} else {
			add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_scripts_v5' ] );
		}
	}

	public function show_upgrade_notice()
	{
		$acffa_settings = get_option( 'acffa_settings' );
		if ( ! isset( $acffa_settings['show_upgrade_notice'] ) ) {
			return;
		}
		?>
		<div class="notice notice-info is-dismissible">
			<p><?php echo wp_kses(sprintf( __( 'Visit the new ACF <a href="%s">FontAwesome Settings</a> page to change FontAwesome icon version, or to create custom icon sets.', 'acf-font-awesome' ), admin_url( '/edit.php?post_type=acf-field-group&page=fontawesome-settings' ) ), array( 'a' => array( 'href' => array() ) ) ); ?></p>
		</div>
		<?php
		unset( $acffa_settings['show_upgrade_notice'] );
		update_option( 'acffa_settings', $acffa_settings, false );
	}

	public function maybe_notify_cdn_error()
	{
		if ( ! get_option( 'ACFFA_cdn_error' ) ) {
			return;
		}

		delete_option( 'ACFFA_cdn_error' );
		$curl_info = curl_version();
		?>
		<div class="notice notice-error is-dismissible">
			<p><?php esc_html_e( 'The plugin "Advanced Custom Fields: Font Awesome" has detected an error while retrieving the latest FontAwesome icons. This may be due to temporary CDN downtime. However if problems persist, please contact your hosting provider to ensure cURL is installed and up to date. Detected cURL version: ', 'acf-font-awesome' ) . $curl_info['version']; ?></p>
		</div>
		<?php
	}

	public function enqueue_acf_select2( $hook )
	{
		$acf = sanitize_title( __( 'ACF', 'acf' ) );
		$custom_fields = sanitize_title( __( 'Custom Fields', 'acf' ) );
		if ( $custom_fields . '_page_fontawesome-settings' != $hook &&
			 $acf . '_page_fontawesome-settings' != $hook ) {
			return;
		}

		// globals
		global $wp_scripts, $wp_styles;

		// vars
		$min     = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';
		$major   = acf_get_setting( 'select2_version' );
		$version = '';
		$script  = '';
		$style   = '';

		// attempt to find 3rd party Select2 version
		// - avoid including v3 CSS when v4 JS is already enququed
		if ( isset( $wp_scripts->registered['select2'] ) ) {

			$major = (int) $wp_scripts->registered['select2']->ver;

		}

		// v4
		if ( $major == 4 ) {

			$version = '4.0.13';
			$script  = acf_get_url( "assets/inc/select2/4/select2.full{$min}.js" );
			$style   = acf_get_url( "assets/inc/select2/4/select2{$min}.css" );

			// v3
		} else {

			$version = '3.5.2';
			$script  = acf_get_url( "assets/inc/select2/3/select2{$min}.js" );
			$style   = acf_get_url( 'assets/inc/select2/3/select2.css' );

		}

		// enqueue
		wp_enqueue_script( 'select2', $script, [ 'jquery' ], $version );
		wp_enqueue_style( 'select2', $style, '', $version );

		// localize
		acf_localize_data(
			[
				'select2L10n' => [
					'matches_1'            => _x( 'One result is available, press enter to select it.', 'Select2 JS matches_1', 'acf' ),
					'matches_n'            => _x( '%d results are available, use up and down arrow keys to navigate.', 'Select2 JS matches_n', 'acf' ),
					'matches_0'            => _x( 'No matches found', 'Select2 JS matches_0', 'acf' ),
					'input_too_short_1'    => _x( 'Please enter 1 or more characters', 'Select2 JS input_too_short_1', 'acf' ),
					'input_too_short_n'    => _x( 'Please enter %d or more characters', 'Select2 JS input_too_short_n', 'acf' ),
					'input_too_long_1'     => _x( 'Please delete 1 character', 'Select2 JS input_too_long_1', 'acf' ),
					'input_too_long_n'     => _x( 'Please delete %d characters', 'Select2 JS input_too_long_n', 'acf' ),
					'selection_too_long_1' => _x( 'You can only select 1 item', 'Select2 JS selection_too_long_1', 'acf' ),
					'selection_too_long_n' => _x( 'You can only select %d items', 'Select2 JS selection_too_long_n', 'acf' ),
					'load_more'            => _x( 'Loading more results&hellip;', 'Select2 JS load_more', 'acf' ),
					'searching'            => _x( 'Searching&hellip;', 'Select2 JS searching', 'acf' ),
					'load_fail'            => _x( 'Loading failed', 'Select2 JS load_fail', 'acf' ),
				],
			]
		);
	}

	public function enqueue_scripts_v6( $hook )
	{
		$acf = sanitize_title( __( 'ACF', 'acf' ) );
		$custom_fields = sanitize_title( __( 'Custom Fields', 'acf' ) );
		if ( $custom_fields . '_page_fontawesome-settings' != $hook &&
			 $acf . '_page_fontawesome-settings' != $hook ) {
			return;
		}

		$options = get_option( 'acffa_settings' );

		$fa_url = apply_filters( 'ACFFA_get_fa_url', '' );
		if ( stristr( $fa_url, 'https://kit.fontawesome.com/' ) ) {
			wp_enqueue_script( 'acffa_font-awesome-kit', $fa_url );
		} else {
			wp_enqueue_style( 'acffa_font-awesome', $fa_url );
		}

		wp_enqueue_style( 'acffa-settings', ACFFA_PUBLIC_PATH . 'assets/css/settings.css', [], ACFFA_VERSION );
		wp_enqueue_script( 'acffa-settings', ACFFA_PUBLIC_PATH . 'assets/js/settings-v6.js', [ 'select2', 'wp-util' ], ACFFA_VERSION, true );
		wp_localize_script( 'acffa-settings', 'ACFFA', [
			'save_settings'			=> __( 'Save Settings', 'acf-font-awesome' ),
			'save_refresh_settings'	=> __( 'Save Settings & Refresh Icon Cache', 'acf-font-awesome' ),
			'search_string'			=> __( 'Add New Icon', 'acf-font-awesome' ),
			'confirm_delete'		=> __( 'Are you sure you want to delete this icon set?', 'acf-font-awesome' ),
			'remove_icon'			=> __( 'Remove this icon from this set?', 'acf-font-awesome' ),
			'delete_fail'			=> __( 'There was an error while trying to delete the icon set, please refresh the page and try again.', 'acf-font-awesome' ),
			'acffa_major_version'	=> isset( $options['acffa_major_version'] ) ? $options['acffa_major_version'] : '',
			'acffa_kit'				=> isset( $options['acffa_kit'] ) ? $options['acffa_kit'] : '',
			'acf_nonce'				=> wp_create_nonce( 'acf_nonce' ),
			'kits'					=> get_option( 'ACFFA_kits', [] ),
			'api_key_status'		=> get_option( 'ACFFA_last_api_call_status', 'na' )
		] );

		add_action( 'admin_footer', [ $this, 'js_templates' ] );
	}

	public function enqueue_scripts_v5( $hook )
	{
		$acf = sanitize_title( __( 'ACF', 'acf' ) );
		$custom_fields = sanitize_title( __( 'Custom Fields', 'acf' ) );
		if ( $custom_fields . '_page_fontawesome-settings' != $hook &&
			 $acf . '_page_fontawesome-settings' != $hook ) {
			return;
		}

		wp_enqueue_style( 'acffa-settings', ACFFA_PUBLIC_PATH . 'assets/css/settings.css', [], ACFFA_VERSION );

		wp_register_style( 'font-awesome', apply_filters( 'ACFFA_get_fa_url', '' ) );
		wp_enqueue_style( 'multi-select-css', ACFFA_PUBLIC_PATH . 'assets/inc/multi-select/multi-select.css', [ 'font-awesome' ] );

		wp_register_script( 'quicksearch-js', ACFFA_PUBLIC_PATH . 'assets/inc/quicksearch/jquery.quicksearch.js', [ 'jquery' ], '1.0.0', true );
		wp_register_script( 'multi-select-js', ACFFA_PUBLIC_PATH . 'assets/inc/multi-select/jquery.multi-select.js', [ 'jquery' ], '0.9.12', true );
		wp_enqueue_script( 'acffa-settings', ACFFA_PUBLIC_PATH . 'assets/js/settings-v5.js', [ 'multi-select-js', 'quicksearch-js' ], '1.0.0', true );
		wp_localize_script( 'acffa-settings', 'ACFFA', [
			'save_settings'			=> __( 'Save Settings', 'acf-font-awesome' ),
			'save_refresh_settings'	=> __( 'Save Settings & Refresh Icon Cache', 'acf-font-awesome' ),
			'search_string'			=> __( 'Search List', 'acf-font-awesome' ),
			'confirm_delete'		=> __( 'Are you sure you want to delete this icon set?', 'acf-font-awesome' ),
			'delete_fail'			=> __( 'There was an error while trying to delete the icon set, please refresh the page and try again.', 'acf-font-awesome' )
		] );
	}

	public function add_settings_link( $links, $file )
	{
		if ( $file != ACFFA_BASENAME ) {
			return $links;
		}

		array_unshift( $links, '<a href="' . esc_url( admin_url( '/edit.php?post_type=acf-field-group&page=fontawesome-settings' ) ) . '">' . esc_html__( 'Settings', 'acf-font-awesome' ) . '</a>' );

		if ( apply_filters( 'ACFFA_show_fontawesome_pro_blurbs', true ) ) {
			$links[] = '<a target="_blank" style="color:#20c997;" href="https://fontawesome.com/referral?a=f4be3e1256">' . __( 'Get Font Awesome Pro!', 'acf-font-awesome' ) . '</a>';
		}

		return $links;
	}

	public function add_settings_page()
	{
		$capability = apply_filters( 'acf/settings/capability', 'manage_options' );

		add_submenu_page(
			'edit.php?post_type=acf-field-group',
			'FontAwesome for Advanced Custom Fields',
			'FontAwesome Settings',
			$capability,
			'fontawesome-settings',
			[ $this, 'fontawesome_settings' ]
		);
	}

	public function fontawesome_settings()
	{
		$errors = get_settings_errors( 'acffa_messages' );
		if ( isset( $_GET['settings-updated'] ) && ! $errors ) {
			add_settings_error( 'acffa_messages', 'acffa_message', __( 'Settings Saved', 'acf-font-awesome' ), 'updated' );
		}

		settings_errors( 'acffa_messages' );
		?>
		<div class="wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
			<?php
				if ( apply_filters( 'ACFFA_show_fontawesome_pro_blurbs', true ) ) :
					switch( ACFFA_MAJOR_VERSION ) {
						case '6':
            case '7':
							$fortawesome = 'fa-solid fa-font-awesome';
							$carrot_icon = 'fa-solid fa-carrot';
							break;

						case '5':
							$fortawesome = 'fab fa-font-awesome-flag';
							$carrot_icon = 'fas fa-carrot';
							break;

						default:
							$fortawesome = 'fa fa-font-awesome';
							$carrot_icon = 'fa fa-tree';
							break;
					}
					?>
					<div class="get-fontawesome-pro">
						<div class="title-button-wrap">
							<i class="<?php echo esc_html($fortawesome); ?>"></i>
							<h3><?php esc_html_e( 'Get more icons, styles, tools, & tech support. Upgrade to Font Awesome Pro!', 'acf-font-awesome' ); ?></h3>
							<a target="_blank" href="https://fontawesome.com/referral?a=f4be3e1256"><i class="<?php echo esc_attr($carrot_icon); ?>"></i><?php esc_html_e( 'Get More with Pro', 'acf-font-awesome' ); ?></a>
						</div>
						<p><?php esc_html_e( 'A subscription to a Font Awesome Pro Plan gives you access to 7,000+ icons, all 5 icon styles, handy services and tools, software and icon updates, a lifetime license to use Pro icons, and actual human support. Signing up with the button above helps to support development on this plugin.', 'acf-font-awesome' ); ?></p>
					</div>
					<?php
				endif;
			?>
			<form action="options.php" method="post">
				<?php
					settings_fields( 'acffa' );

					do_settings_sections( 'acffa' );

					if ( version_compare( ACFFA_MAJOR_VERSION, 6, '<' ) ) {
						submit_button( __( 'Save Settings & Refresh Icon Cache', 'acf-font-awesome' ) );
					} else {
						submit_button( __( 'Save Settings', 'acf-font-awesome' ) );
					}
				?>
			</form>
		</div>
		<?php
	}

	public function register_settings()
	{
		register_setting(
			'acffa',
			'acffa_settings',
			[
				'sanitize_callback'	=> [ $this, 'sanitize_new_icon_set' ]
			]
		);

		add_settings_section(
			'acffa_section_developers',
			__( 'Settings', 'acf-font-awesome' ),
			[ $this, 'acffa_section_developers_cb' ],
			'acffa'
		);

		add_settings_field(
			'acffa_major_version',
			__( 'FontAwesome Version', 'acf-font-awesome' ),
			[ $this, 'acffa_major_version_cb' ],
			'acffa',
			'acffa_section_developers',
			[
				'label_for'	=> 'acffa_major_version',
				'class'		=> 'acffa_row'
			]
		);

		add_settings_field(
			'acffa_pro_cdn',
			__( 'Enable Pro Icons?', 'acf-font-awesome' ),
			[ $this, 'acffa_pro_cdn_cb' ],
			'acffa',
			'acffa_section_developers',
			[
				'label_for'	=> 'acffa_pro_cdn',
				'class'		=> 'acffa_row pro_icons'
			]
		);

		add_settings_field(
			'acffa_v5_compatibility_mode',
			__( 'Compatibility Mode', 'acf-font-awesome' ),
			[ $this, 'acffa_v5_compatibility_mode_cb' ],
			'acffa',
			'acffa_section_developers',
			[
				'label_for'	=> 'acffa_v5_compatibility_mode',
				'class'		=> 'acffa_row v5_compatibility_mode'
			]
		);

		add_settings_field(
			'acffa_api_key',
			__( 'FontAwesome API Token', 'acf-font-awesome' ),
			[ $this, 'acffa_api_key_cb' ],
			'acffa',
			'acffa_section_developers',
			[
				'label_for'	=> 'acffa_api_key',
				'class'		=> 'acffa_row api_key'
			]
		);

		add_settings_field(
			'acffa_kit',
			__( 'FontAwesome Kit', 'acf-font-awesome' ),
			[ $this, 'acffa_kit_cb' ],
			'acffa',
			'acffa_section_developers',
			[
				'label_for'	=> 'acffa_kit',
				'class'		=> 'acffa_row kit'
			]
		);

		add_settings_field(
			'acffa_kit_has_pro',
			'Has Pro Kit',
			[ $this, 'acffa_kit_has_pro_cb' ],
			'acffa',
			'acffa_section_developers',
			[
				'label_for'	=> 'acffa_kit_has_pro',
				'class'		=> 'acffa_row hidden'
			]
		);

		add_settings_field(
			'acffa_plugin_version',
			'Plugin Version',
			[ $this, 'acffa_plugin_version_cb' ],
			'acffa',
			'acffa_section_developers',
			[
				'label_for'	=> 'acffa_plugin_version',
				'class'		=> 'acffa_row hidden'
			]
		);

		if (version_compare(ACFFA_MAJOR_VERSION, 7, '<')) {
			add_settings_section(
				'acffa_section_icon_set_builder',
				__('Icon Set Builder', 'acf-font-awesome'),
				[$this, 'acffa_section_icon_set_builder_cb'],
				'acffa'
			);

			add_settings_field(
				'acffa_new_icon_set_label',
				__('New Icon Set Label', 'acf-font-awesome'),
				[$this, 'acffa_new_icon_set_label_cb'],
				'acffa',
				'acffa_section_icon_set_builder',
				[
					'label_for'	=> 'acffa_new_icon_set_label',
					'class'		=> 'acffa_row custom-icon-set'
				]
			);

			add_settings_field(
				'acffa_new_icon_set',
				__('New Icon Set', 'acf-font-awesome'),
				[$this, 'acffa_new_icon_set_cb'],
				'acffa',
				'acffa_section_icon_set_builder',
				[
					'label_for'	=> 'acffa_new_icon_set',
					'class'		=> 'acffa_row custom-icon-set'
				]
			);

			add_settings_field(
				'acffa_existing_icon_sets',
				__('Existing Icon Sets', 'acf-font-awesome'),
				[$this, 'acffa_existing_icon_sets_cb'],
				'acffa',
				'acffa_section_icon_set_builder',
				[
					'label_for'	=> 'acffa_existing_icon_sets',
					'class'		=> 'acffa_row custom-icon-set'
				]
			);
		}
	}

	public function sanitize_new_icon_set( $data )
	{
		if ( isset( $data['acffa_new_icon_set_label'] ) || ! empty( $data['acffa_new_icon_set_label'] ) ) {
			$data['acffa_new_icon_set_label'] = sanitize_text_field( $data['acffa_new_icon_set_label'] );
		} else {
			$data['acffa_new_icon_set_label'] = false;
		}

		if ( isset( $data['acffa_new_icon_set'] ) || ! empty( $data['acffa_new_icon_set'] ) ) {
			$data['acffa_new_icon_set'] = array_map(
				'sanitize_text_field',
				wp_unslash( $data['acffa_new_icon_set'] )
			);
		} else {
			$data['acffa_new_icon_set'] = false;
		}

		if ( $data['acffa_new_icon_set_label'] && ! $data['acffa_new_icon_set'] ) {
			add_settings_error( 'acffa_messages', 'missing_label', __( 'Please select at least one icon when adding a new custom icon set.', 'acf-font-awesome' ), 'error' );
		} else if ( $data['acffa_new_icon_set'] && ! $data['acffa_new_icon_set_label'] ) {
			add_settings_error( 'acffa_messages', 'missing_icons', __( 'Label is required when adding a new custom icon set.', 'acf-font-awesome' ), 'error' );
		}

		return $data;
	}

	public function acffa_section_developers_cb( $args ) {}

	public function acffa_major_version_cb( $args )
	{
		$options = get_option( 'acffa_settings' );
		$attributes = defined( 'ACFFA_OVERRIDE_MAJOR_VERSION' ) ? 'disabled' : false;
		?>
		<p>
			<?php esc_html_e( 'IMPORTANT: This plugin has undergone major changes between FontAwesome versions. Switching to a new version may require you to reselect some/all icons that you have previously selected using this plugin. Switching to v6 has introduced a new "Compatibility Mode" that aims to make this migration easier.', 'acf-font-awesome' ); ?>
		</p>
		<br>
		<select <?php echo esc_attr( $attributes ); ?> id="<?php echo esc_attr( $args['label_for'] ); ?>" name="acffa_settings[<?php echo esc_attr( $args['label_for'] ); ?>]">
			<option value="4" <?php echo isset( $options[ $args[ 'label_for'] ] ) ? ( selected( $options[ $args[ 'label_for'] ], 4, false ) ) : ( '' ); ?>>
			<?php esc_html_e( '4.x', 'acf-font-awesome' ); ?>
			</option>
			<option value="5" <?php echo isset( $options[ $args[ 'label_for'] ] ) ? ( selected( $options[ $args[ 'label_for'] ], 5, false ) ) : ( '' ); ?>>
			<?php esc_html_e( '5.x', 'acf-font-awesome' ); ?>
			</option>
			<option value="6" <?php echo isset( $options[ $args[ 'label_for'] ] ) ? ( selected( $options[ $args[ 'label_for'] ], 6, false ) ) : ( '' ); ?>>
			<?php esc_html_e( '6.x', 'acf-font-awesome' ); ?>
			</option>
      <option value="7" <?php echo isset( $options[ $args[ 'label_for'] ] ) ? ( selected( $options[ $args[ 'label_for'] ], 7, false ) ) : ( '' ); ?>>
      <?php esc_html_e( '7.x', 'acf-font-awesome' ); ?>
      </option>
		</select>
		<?php
		if ( defined( 'ACFFA_OVERRIDE_MAJOR_VERSION' ) ) :
			?>
			<p>
				<em><?php esc_html_e( 'The FontAwesome version is manually set with the "ACFFA_override_major_version" filter, and cannot be modified from this screen. Please remove or update the filter to make changes.', 'acf-font-awesome' ); ?></em>
			</p>
			<?php
		endif;
	}

	public function acffa_v5_compatibility_mode_cb( $args )
	{
		$options = get_option( 'acffa_settings' );
		?>
		<p>
			<?php esc_html_e( 'Attempt to automatically migrate any older FontAwesome icon selections made using this plugin to their FontAwesome v6 equivalents.', 'acf-font-awesome' ); ?><br>
			<em><?php esc_html_e( 'NOTE: This is only able to automatically migrate FontAwesome free icons. Pro icons will need to be manually reselected.', 'acf-font-awesome' ); ?></em>
		</p>
		<br>
		<p>
			<input type="checkbox" value="1" id="<?php echo esc_attr( $args['label_for'] ); ?>" name="acffa_settings[<?php echo esc_attr( $args['label_for'] ); ?>]" <?php echo isset( $options[ $args[ 'label_for'] ] ) ? ( checked( $options[ $args[ 'label_for'] ] ) ) : ( '' ); ?> />
			<label for="<?php echo esc_attr( $args['label_for'] ); ?>"><?php esc_html_e( 'Enable Compatibility Mode ', 'acf-font-awesome' ); ?><em><?php esc_html_e( '(Recommended only for users with existing FontAwesome 4.x/5.x icon selections)', 'acf-font-awesome' ); ?></em></label>
		</p>
		<?php
	}

	public function acffa_pro_cdn_cb( $args )
	{
		$options = get_option( 'acffa_settings' );
		?>
		<p>
			<?php esc_html_e( 'If you have a FontAwesome Pro license, check the box below to enable the pro icons.', 'acf-font-awesome' ); ?><br>
			<em><?php esc_html_e( 'NOTE: You MUST add this domain in your FontAwesome "Pro CDN Domains" in order for this to work!', 'acf-font-awesome' ); ?></em>
		</p>
		<br>
		<p>
			<input type="checkbox" value="1" id="<?php echo esc_attr( $args['label_for'] ); ?>" name="acffa_settings[<?php echo esc_attr( $args['label_for'] ); ?>]" <?php echo isset( $options[ $args[ 'label_for'] ] ) ? ( checked( $options[ $args[ 'label_for'] ] ) ) : ( '' ); ?> />
			<label for="<?php echo esc_attr( $args['label_for'] ); ?>"><?php esc_html_e( 'I have enabled this domain for CDN use. Turn on the pro icons!', 'acf-font-awesome' ); ?></label>
		</p>
		<?php
	}

	public function acffa_api_key_cb( $args )
	{
		if ( $api_key = apply_filters( 'ACFFA_fa_api_key', false ) ) {
			?>
				<p>
					<?php esc_html_e( 'The API key has been set programatically using the "ACFFA_fa_api_key" filter.', 'acf-font-awesome' ); ?><br>
				</p>
			<?php
		} else {
			$options = get_option( 'acffa_settings' );
			?>
			<p>
				<?php printf(
					/* translators: %s is a link to FontAwesome Account page */
					esc_html__( 'You can create an API token from your %s page', 'acf-font-awesome' ),
					'<a target="_blank" href="' . esc_url( 'https://fontawesome.com/account/#api-tokens' ) . '">' . esc_html__( 'FontAwesome Account', 'acf-font-awesome' ) . '</a>'
				); ?><br>
			</p>
			<br>
			<p>
				<input type="text" class="regular-text code" value="<?php echo isset( $options[ $args[ 'label_for'] ] ) ? ( esc_attr( $options[ $args[ 'label_for'] ] ) ) : ''; ?>" id="<?php echo esc_attr( $args['label_for'] ); ?>" name="acffa_settings[<?php echo esc_attr( $args['label_for'] ); ?>]" />
				<br>
				<span class="validation-label"><?php esc_html_e( 'Token Validation:', 'acf-font-awesome' ); ?></span>
				<span class="validation-result">
					<span class="empty"><?php esc_html_e( 'Please add your API token above.', 'acf-font-awesome' ); ?></span>
					<span class="save"><?php esc_html_e( 'Save settings to validate token.', 'acf-font-awesome' ); ?></span>
					<span class="success"><?php esc_html_e( 'Token successfully validated.', 'acf-font-awesome' ); ?></span>
					<span class="error"><?php esc_html_e( 'Could not validate token. Please verify the token has been correctly entered.', 'acf-font-awesome' ); ?></span>
				</span>
			</p>
			<?php
		}
	}

	public function acffa_kit_cb( $args )
	{
		if ( $api_key = apply_filters( 'ACFFA_fa_kit_token', false ) ) {
			?>
				<p>
					<?php esc_html_e( 'The kit token has been set programatically using the "ACFFA_fa_kit_token" filter.', 'acf-font-awesome' ); ?><br>
				</p>
			<?php
		} else {
			$options = get_option( 'acffa_settings' );
			?>
			<p>
				<?php esc_html_e( 'FontAwesome kits are required for using FontAwesome Pro icons. Enter your API token above to select your kit.', 'acf-font-awesome' ); ?><br>
			</p>
			<br>

			<table class="widefat" id="available_kits">
				<thead>
					<tr>
						<td><?php esc_html_e( 'Select', 'acf-font-awesome' ); ?></td>
						<td><?php esc_html_e( 'Kit Name', 'acf-font-awesome' ); ?></td>
						<td><?php esc_html_e( 'Token', 'acf-font-awesome' ); ?></td>
						<td><?php esc_html_e( 'Status', 'acf-font-awesome' ); ?></td>
						<td><?php esc_html_e( 'License', 'acf-font-awesome' ); ?></td>
						<td><?php esc_html_e( 'Technology', 'acf-font-awesome' ); ?></td>
						<td><?php esc_html_e( 'Custom Icon(s)', 'acf-font-awesome' ); ?></td>
						<td><?php esc_html_e( 'Version', 'acf-font-awesome' ); ?></td>
					</tr>
				</thead>
				<tbody>
					<tr class="no_kits_found">
						<td><input type="radio" name="acffa_settings[acffa_kit]" checked value=""></td>
						<td colspan="7">
							<?php _e( 'No Kits Found. <a target="_blank" href="https://fontawesome.com/kits">Create a new kit</a>', 'acf-font-awesome' ); ?>
						</td>
					</tr>
				</tbody>
			</table>
			<p><?php _e( 'Make changes to your kits on <a target="_blank" href="https://fontawesome.com/kits">fontawesome.com/kits</a>', 'acf-font-awesome' ); ?></p>
			<?php
		}
	}

	public function acffa_kit_has_pro_cb( $args )
	{
		$options = get_option( 'acffa_settings' );
		?>
		<input type="hidden" value="<?php echo isset( $options[ $args[ 'label_for'] ] ) ? ( esc_attr( $options[ $args[ 'label_for'] ] ) ) : ''; ?>" id="<?php echo esc_attr( $args['label_for'] ); ?>" name="acffa_settings[<?php echo esc_attr( $args['label_for'] ); ?>]" />
		<?php
	}

	public function acffa_plugin_version_cb( $args )
	{
		?>
		<input type="hidden" value="<?php echo esc_html(ACFFA_VERSION); ?>" id="<?php echo esc_attr( $args['label_for'] ); ?>" name="acffa_settings[<?php echo esc_attr( $args['label_for'] ); ?>]" />
		<?php
	}

	public function acffa_section_icon_set_builder_cb( $args )
	{
		?>
		<p id="<?php echo esc_attr( $args['id'] ); ?>">
			<?php esc_html_e( 'Use the icon set builder to create custom collections of FontAwesome icons to be used in your ACF FontAwesome fields', 'acf-font-awesome' ); ?><br>
			<em><?php esc_html_e( 'If you\'ve made changes the the FontAwesome version you are loading above, please refresh this page to see those changes reflected in the list below.', 'acf-font-awesome' ); ?></em>
		</p>
		<p class="icon-builder-complete-changes-notice">
	 		<strong><?php esc_html_e( 'You must save your changes to the major version before using the icon set builder.', 'acf-font-awesome' ); ?></strong>
		</p>
		<?php
	}

	public function acffa_new_icon_set_label_cb( $args )
	{
		?>
		<input type="text" id="<?php echo esc_attr( $args['label_for'] ); ?>" name="acffa_settings[<?php echo esc_attr( $args['label_for'] ); ?>]" placeholder="<?php esc_html_e( 'Custom Icon Set Name', 'acf-font-awesome' ); ?>">
		<p>
			<em><?php esc_html_e( 'NOTE: Providing a label that is already in use will overwrite the existing custom icon set.', 'acf-font-awesome' ); ?></em>
		</p>
		<?php
	}

	public function acffa_new_icon_set_cb( $args )
	{
		if ( version_compare( ACFFA_MAJOR_VERSION, 6, '>=' ) ) {
			?>
			<div class="selected-icons"></div>
			<select multiple id="<?php echo esc_attr( $args['label_for'] ); ?>" name="acffa_settings[<?php echo esc_attr( $args['label_for'] ); ?>][]"></select>
			<select id="icon_chooser"></select>
			<?php
		} else {
			$options = get_option( 'acffa_settings' );
			?>
			<select multiple="multiple" id="<?php echo esc_attr( $args['label_for'] ); ?>" name="acffa_settings[<?php echo esc_attr( $args['label_for'] ); ?>][]">
				<?php
					$fa_icons = apply_filters( 'ACFFA_get_icons', array() );
					if ( $fa_icons ) {
						if ( version_compare( ACFFA_MAJOR_VERSION, 5, '=' ) ) {
							foreach ( $fa_icons['list'] as $prefix => $icons ) {
								$optgroup_label = apply_filters( 'ACFFA_icon_prefix_label', 'Regular', $prefix );
								echo '<optgroup label="' . esc_attr( $optgroup_label ) . '">';

								foreach( $icons as $k => $v ) {
									$value = str_replace( array( 'fas ', 'far ', 'fab ', 'fal ', 'fad ', 'fa-' ), '', $k );
									?>
									<option value="<?php echo esc_attr( $k ); ?>"><?php echo esc_html( $value ); ?></option>
									<?php
								}

								echo '</optgroup>';
							}
						} else {
							foreach ( $fa_icons['list'] as $k => $v ) {
								$value = str_replace( array( 'fa-' ), '', $k );
								?>
								<option value="<?php echo esc_attr( $k ); ?>"><?php echo esc_html( $value ); ?></option>
								<?php
							}
						}
					} else {
						?>
						<option value=""><?php esc_html_e( 'No Icons Found', 'acf-font-awesome' ); ?></option>
						<?php
					}
				?>
			</select>
			<?php
		}
	}

	public function acffa_existing_icon_sets_cb( $args )
	{
		$custom_icon_sets_list = get_option( 'ACFFA_custom_icon_sets_list' );

		if ( isset( $custom_icon_sets_list[ $this->version ] ) && ! empty( $custom_icon_sets_list[ $this->version ] ) ) {
			?>
			<ul class="existing-custom-icon-sets">
			<?php
			foreach ( $custom_icon_sets_list[ $this->version ] as $icon_set_name => $icon_set_label ) {
				$icon_set = get_option( $icon_set_name );
				$icon_set = apply_filters( 'ACFFA_standardize_custom_icon_set_family_style', $icon_set );

				if ( ! $icon_set ) {
					$this->remove_icon_set( $custom_icon_sets_list, $icon_set_name, true );
				}
				?>
				<li class="icon-set" data-set-label="<?php echo esc_html( $icon_set_label ); ?>" data-set-name="<?php echo esc_html( $icon_set_name ); ?>">
					<span><strong><?php echo esc_html( $icon_set_label ); ?></strong> <span class="actions">( <a href="#" class="edit-icon-set"><?php esc_html_e( 'Load For Editing', 'acf-font-awesome' ); ?></a> | <a href="#" class="view-icon-list"><?php esc_html_e( 'Toggle Icon List', 'acf-font-awesome' ); ?></a> | <a href="#" class="delete-icon-set" data-icon-set-name="<?php echo esc_attr( $icon_set_name ); ?>" data-nonce="<?php echo esc_attr( wp_create_nonce( 'acffa_delete_set_' . $icon_set_name ) ); ?>"><?php esc_html_e( 'Delete Icon Set', 'acf-font-awesome' ); ?></a> )</span></span>
					<ul class="icon-list">
						<?php
							if ( version_compare( ACFFA_MAJOR_VERSION, 6, '>=' ) ) {
								foreach ( $icon_set as $family_style => $icons ) {
									?>
									<li>
										<span class="style"><?php echo esc_html( apply_filters( 'ACFFA_icon_prefix_label', 'Regular', $family_style ) ); ?></span>
										<ul>
											<?php
												foreach ( $icons as $id => $icon_json ) {
													$icon_info	= json_decode( $icon_json );
													$family		= isset( $icon_info->family ) ? $icon_info->family : apply_filters( 'ACFFA_default_family_by_style', 'classic', $icon_info->style );
													echo '<li class="icon" data-icon-json="' . esc_attr( htmlentities( $icon_json ) ) . '"><i class="fa-' . esc_attr( $family ) . ' fa-' . esc_attr( $icon_info->style ) . ' fa-' . esc_attr( $icon_info->id ) . ' fa-fw"></i>' . esc_html( $icon_info->label ) . '</li>';
												}
											?>
										</ul>
									</li>
									<?php
								}
							} else if ( version_compare( ACFFA_MAJOR_VERSION, 5, '=' ) ) {
								foreach ( $icon_set as $prefix => $icons ) {
									?>
									<li>
										<?php echo esc_html( apply_filters( 'ACFFA_icon_prefix_label', 'Regular', $prefix ) ); ?>
										<ul>
											<?php
												foreach ( $icons as $class => $label ) {
													echo '<li class="icon" data-icon="' . esc_attr( $class ) . '">' . esc_html( $label ) . '</li>';
												}
											?>
										</ul>
									</li>
									<?php
								}
							} else {
								foreach ( $icon_set as $class => $label ) {
									?>
									<li>
										<?php
											echo '<li class="icon" data-icon="' . esc_attr( $class ) . '">' . esc_html( $label ) . '</li>';
										?>
									</li>
									<?php
								}
							}
						?>
					</ul>
				</li>
				<?php
			}
			?>
			</ul>
			<?php
		} else {
			esc_html_e( 'No existing custom icon set(s) found.', 'acf-font-awesome' );
		}
	}

	public function intercept_icon_set_save( $new_value, $old_value )
	{
		$label = $new_value['acffa_new_icon_set_label'];
		$icons = $new_value['acffa_new_icon_set'];

		unset( $new_value['acffa_new_icon_set_label'] );
		unset( $new_value['acffa_new_icon_set'] );

		if ( $label && $icons ) {
			$this->save_new_icon_set( $label, $icons );
		}

		return $new_value;
	}

	public function maybe_refresh_icons( $new_value, $old_value )
	{
		if ( version_compare( ACFFA_MAJOR_VERSION, 6, '>=' ) ) {
			return $new_value;
		}

		unset( $new_value['acffa_new_icon_set_label'] );
		unset( $new_value['acffa_new_icon_set'] );

		do_action( 'ACFFA_refresh_latest_icons' );

		return $new_value;
	}

	public function revoke_access_token( $new_value, $old_value )
	{
		$old_api_key = isset( $old_value['acffa_api_key'] ) ? $old_value['acffa_api_key'] : false;
		$new_api_key = isset( $new_value['acffa_api_key'] ) ? $new_value['acffa_api_key'] : false;

		if ( $old_api_key != $new_api_key ) {
			delete_transient( 'ACFFA_access_token' );
			delete_transient( 'ACFFA_search_config' );
			update_option( 'ACFFA_last_api_call_status', 'na' );
			update_option( 'ACFFA_kits', [] );
		}

		return $new_value;
	}

	public function clear_search_config_cache( $new_value, $old_value )
	{
		$old_kit_id = isset( $old_value['acffa_kit'] ) ? $old_value['acffa_kit'] : false;
		$new_kit_id = isset( $new_value['acffa_kit'] ) ? $new_value['acffa_kit'] : false;

		if ( $old_kit_id != $new_kit_id ) {
			delete_transient( 'ACFFA_search_config' );
		}

		return $new_value;
	}

	public function check_kits_settings( $new_value, $old_value )
	{
		if ( version_compare( ACFFA_MAJOR_VERSION, 6, '<' ) ) {
			return $new_value;
		}

		if ( $ACFFA_fa_api_key = apply_filters( 'ACFFA_fa_api_key', false ) ) {
			return $new_value;
		}

		if ( ! isset( $new_value['acffa_api_key'] ) || empty( $new_value['acffa_api_key'] ) ) {
			return $new_value;
		}

		$this->get_fontawesome_kits( $new_value['acffa_api_key'] );

		return $new_value;
	}

	public function get_latest_version($old_value, $new_value, $option)
	{
		if ( version_compare( ACFFA_MAJOR_VERSION, 6, '<' ) ) {
			return $new_value;
		}

		
		$old_version = isset( $old_value['acffa_major_version'] ) ? $old_value['acffa_major_version'] : false;
		$new_version = isset( $new_value['acffa_major_version'] ) ? $new_value['acffa_major_version'] : false;

		if ( $old_version != $new_version ) {
			delete_option( 'ACFFA_latest_version' );
		}

		return $new_value;
	}

	public function check_kits_api_key_filter()
	{
		$ACFFA_fa_api_key		= apply_filters( 'ACFFA_fa_api_key', false );
		$ACFFA_fa_api_key_db	= get_option( 'ACFFA_fa_api_key' );

		if ( ! $ACFFA_fa_api_key && ! $ACFFA_fa_api_key_db ) {
			return;
		}

		if ( $ACFFA_fa_api_key != $ACFFA_fa_api_key_db ) {
			delete_transient( 'ACFFA_access_token' );
			delete_transient( 'ACFFA_search_config' );
			update_option( 'ACFFA_last_api_call_status', 'na' );
			update_option( 'ACFFA_kits', [] );

			if ( $ACFFA_fa_api_key ) {
				update_option( 'ACFFA_fa_api_key', $ACFFA_fa_api_key, false );
			} else {
				delete_option( 'ACFFA_fa_api_key' );
			}

			$this->get_fontawesome_kits( $ACFFA_fa_api_key );
		}
	}

	private function get_fontawesome_kits( $fa_api_key )
	{
		$access_token = apply_filters( 'ACFFA_fontawesome_access_token', false, $fa_api_key );

		if ( ! $access_token ) {
			return;
		}

		$remote_get = wp_remote_post( 'https://api.fontawesome.com', [
			'headers'	=> [
				'Content-Type'	=> 'application/json',
				'Authorization'	=> 'Bearer ' . $access_token,
			],
			'timeout'	=> 30,
			'body'		=> '{
				"query" : "query { me { kits { name token status licenseSelected technologySelected version iconUploads { name } } } }" 
			}'
		] );

		if ( ! is_wp_error( $remote_get ) ) {
			$response_json = wp_remote_retrieve_body( $remote_get );

			if ( $response_json ) {
				$response = json_decode( $response_json );

				if ( isset( $response->data->me->kits ) && ! empty( $response->data->me->kits ) ) {
					update_option( 'ACFFA_kits', $response->data->me->kits );
				}
			}
		}
	}

	private function save_new_icon_set( $label, $icons )
	{
		$new_icon_set = [];

		$fa_icons = apply_filters( 'ACFFA_get_icons', [] );

		if ( version_compare( ACFFA_MAJOR_VERSION, 6, '>=' ) ) {
			foreach( $icons as $icon ) {
				$icon_details	= json_decode( $icon );
				$family			= isset( $icon_details->family ) ? $icon_details->family : apply_filters( 'ACFFA_default_family_by_style', 'classic', $icon_details->style );
				$family_style	= $family . '_' . $icon_details->style;
				if ( ! isset( $new_icon_set[ $family_style ] ) ) {
					$new_icon_set[ $family_style ] = [];
				}
				$new_icon_set[ $family_style ][ $icon_details->id ] = $icon;
			}
		} else if ( version_compare( ACFFA_MAJOR_VERSION, 5, '=' ) ) {
			foreach( $icons as $icon ) {
				$prefix = substr( $icon, 0, 3 );

				if ( isset( $fa_icons['list'][ $prefix ][ $icon ] ) ) {
					if ( ! isset( $new_icon_set[ $prefix ] ) ) {
						$new_icon_set[ $prefix ] = [];
					}
					$new_icon_set[ $prefix ][ $icon ] = $fa_icons['list'][ $prefix ][ $icon ];
				}
			}
		} else {
			foreach( $icons as $icon ) {
				if ( isset( $fa_icons['list'][ $icon ] ) ) {
					$new_icon_set[ $icon ] = $fa_icons['list'][ $icon ];
				}
			}
		}

		if ( ! empty( $new_icon_set ) ) {
			$option_name = 'ACFFA_custom_icon_list_' . $this->version . '_' . sanitize_html_class( $label );
			update_option( $option_name, $new_icon_set, false );
			$this->update_custom_icon_sets_list( $option_name, $label );
		}
	}

	private function update_custom_icon_sets_list( $option_name, $label )
	{
		$icon_sets_list = get_option( 'ACFFA_custom_icon_sets_list' );

		if ( ! $icon_sets_list ) {
			$icon_sets_list = [];
		}

		if ( ! isset( $icon_sets_list[ $this->version ] ) ) {
			$icon_sets_list[ $this->version ] = [];
		}

		if ( ! isset( $icon_sets_list[ $this->version ][ 'ACFFA_custom_icon_list_' . $option_name ] ) ) {
			$icon_sets_list[ $this->version ][ $option_name ] = $label;
		}

		update_option( 'ACFFA_custom_icon_sets_list', $icon_sets_list, false );
	}

	private function remove_icon_set( $custom_icon_sets_list, $icon_set_name, $list_only = false )
	{
		if ( ! $custom_icon_sets_list ) {
			$custom_icon_sets_list = get_option( 'ACFFA_custom_icon_sets_list' );
		}

		if ( ! isset( $custom_icon_sets_list[ $this->version ][ $icon_set_name ] ) ) {
			return;
		}

		unset( $custom_icon_sets_list[ $this->version ][ $icon_set_name ] );

		update_option( 'ACFFA_custom_icon_sets_list', $custom_icon_sets_list );

		if ( ! $list_only ) {
			delete_option( $icon_set_name );
		}
	}

	public function ajax_remove_icon_set()
	{
		$valid_nonce = check_ajax_referer( 'acffa_delete_set_' . $_POST['icon_set_name'], 'nonce', false );

		if ( ! $valid_nonce ) {
			wp_die( 'fail' );
		}

		$this->remove_icon_set( false, $_POST['icon_set_name'] );

		wp_die( 'success' );
	}

	public function hide_fontawesome_pro_blurbs( $show_blurbs )
	{
		$acffa_settings = get_option( 'acffa_settings' );

		if ( isset( $acffa_settings['acffa_kit_has_pro'] ) && $acffa_settings['acffa_kit_has_pro'] ) {
			$show_blurbs = false;
		}

		if ( version_compare( ACFFA_MAJOR_VERSION, 5, '=' ) && isset( $acffa_settings['acffa_pro_cdn'] ) && $acffa_settings['acffa_pro_cdn'] ) {
			$show_blurbs = false;
		}

		return $show_blurbs;
	}

	public function js_templates()
	{
		include_once ACFFA_DIRECTORY . '/assets/js/templates/tmpl-fa-kit.php';
	}

}

add_action( 'acf/init', [ new ACFFA_Admin, 'init' ], 10 );
