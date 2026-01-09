<?php

/*
Plugin Name: Advanced Custom Fields: Font Awesome
Plugin URI: https://wordpress.org/plugins/advanced-custom-fields-font-awesome/
Description: Adds a new 'Font Awesome Icon' field to the popular Advanced Custom Fields plugin.
Version: 5.0.1
Author: Justin Kruit, Matt Keys
Author URI: http://justinkruit.com/
Text Domain: acf-font-awesome
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html
*/

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'ACFFA_VERSION' ) ) {
	define( 'ACFFA_VERSION', '5.0.0' );
}

if ( ! defined( 'ACFFA_PUBLIC_PATH' ) ) {
    $stylesheet_dir = trim( get_stylesheet_directory(), '/' );
    $stylesheet_dir = wp_normalize_path($stylesheet_dir);

    $file = wp_normalize_path( __FILE__ );

    if ( stristr( $file, $stylesheet_dir ) ) {
        define( 'ACFFA_THEME_INSTALLATION', true );

        if ( defined( 'MY_ACFFA_URL' ) ) {
            $public_path	= MY_ACFFA_URL;
        } else {
            $basename_dir	= trim( plugin_basename( __DIR__ ), '/' );
            $theme_path		= str_replace( $stylesheet_dir, '', $basename_dir );
            $public_path	= get_stylesheet_directory_uri() . trailingslashit( $theme_path );
        }
    } else {
        define( 'ACFFA_THEME_INSTALLATION', false );
        $public_path = plugin_dir_url( __FILE__ );
    }

    define( 'ACFFA_PUBLIC_PATH', $public_path );
}

if ( ! defined( 'ACFFA_DIRECTORY' ) ) {
    if ( defined( 'MY_ACFFA_PATH' ) ) {
        define( 'ACFFA_DIRECTORY', MY_ACFFA_PATH );
    } else {
        define( 'ACFFA_DIRECTORY', dirname( __FILE__ ) );
    }
}

if ( ! defined( 'ACFFA_BASENAME' ) ) {
	define( 'ACFFA_BASENAME', plugin_basename( __FILE__ ) );
}

function ACFFA_load_textdomain() {
	load_plugin_textdomain( 'acf-font-awesome', false, plugin_basename( dirname( __FILE__ ) ) . '/lang' );
}
add_action( 'init', 'ACFFA_load_textdomain', 10 );

if ( ! class_exists('acf_plugin_font_awesome') ) :

	class acf_plugin_font_awesome {

		public function init()
		{
			$acffa_major_version = $this->get_major_version();

			if ( is_admin() ) {
				require 'admin/class-ACFFA-Admin.php';
			}

			$this->check_for_updates( $acffa_major_version );

			if ( $acffa_major_version == 4 ) {
				require 'assets/inc/class-ACFFA-Loader-4.php';
			} elseif ( $acffa_major_version == 5 ) {
				require 'assets/inc/class-ACFFA-Loader-5.php';
			} elseif ( $acffa_major_version == 6 ) {
				require 'assets/inc/class-ACFFA-Loader-6.php';
			} else {
				require 'assets/inc/class-ACFFA-Loader-7.php';
            }

			if ( version_compare( $acffa_major_version, 6, '<' ) ) {
				include_once('fields/acf-font-awesome-v5.php');
			} elseif (version_compare( $acffa_major_version, 7, '<' ) ) {
				include_once('fields/acf-font-awesome-v6.php');
			} else {
				include_once('fields/acf-font-awesome-v7.php');
			}

			if ( ! defined( 'DISABLE_NAG_NOTICES' ) || ! DISABLE_NAG_NOTICES ) {
				add_action( 'admin_notices', [ $this, 'theme_install_update_needed' ] );
			}
			add_action( 'ACFFA_theme_install_update_check', [ $this, 'theme_install_update_check' ] );

			if ( ACFFA_THEME_INSTALLATION ) {
				if ( ! wp_next_scheduled ( 'ACFFA_theme_install_update_check' ) ) {
					wp_schedule_event( time(), 'daily', 'ACFFA_theme_install_update_check' );
				}
			}

		}

		private function get_major_version()
		{
			$current_version		= get_option( 'ACFFA_current_version' );
			$acffa_settings			= get_option( 'acffa_settings', [] );
			$default_version		= ( $current_version && empty( $acffa_settings ) ) ? 4 : 7;

			$acffa_major_version	= isset( $acffa_settings['acffa_major_version'] ) ? intval( $acffa_settings['acffa_major_version'] ) : $default_version;
			$override_major_version	= (int) apply_filters( 'ACFFA_override_major_version', false );
			if ( $override_major_version ) {
				$override_major_version = floor( $override_major_version );

				if ( 4 == $override_major_version || 5 == $override_major_version || 6 == $override_major_version || 7 == $override_major_version ) {
					if ( $acffa_major_version !== $override_major_version ) {
						$acffa_settings['acffa_major_version'] = $override_major_version;
						update_option( 'acffa_settings', $acffa_settings, false );

						do_action( 'ACFFA_refresh_latest_icons' );

						$acffa_major_version = $override_major_version;
					}

					if ( ! defined( 'ACFFA_OVERRIDE_MAJOR_VERSION' ) ) {
						define( 'ACFFA_OVERRIDE_MAJOR_VERSION', true );
					}
				}
			}

			if ( ! defined( 'ACFFA_MAJOR_VERSION' ) ) {
				define( 'ACFFA_MAJOR_VERSION', $acffa_major_version );
			}

			if ( ! isset( $acffa_settings['acffa_major_version'] ) ) {
				$acffa_settings['acffa_major_version'] = $acffa_major_version;
				$acffa_settings['acffa_plugin_version'] = ACFFA_VERSION;
				$acffa_settings['show_upgrade_notice'] = true;
				update_option( 'acffa_settings', $acffa_settings, false );
			}

			return $acffa_major_version;
		}

		private function check_for_updates( $acffa_major_version )
		{
			$acffa_settings			= get_option( 'acffa_settings', [] );
			$acffa_internal_version	= isset( $acffa_settings['acffa_plugin_version'] ) ? $acffa_settings['acffa_plugin_version'] : false;

			if ( ! $acffa_internal_version ) {
				return;
			}

			switch ( $acffa_major_version ) {
				case 4:
					if ( version_compare( $acffa_internal_version, '4.0.0', '<' ) ) {
						$acffa_settings['acffa_v5_compatibility_mode'] = 1;
					}
				case 5:
					if ( version_compare( $acffa_internal_version, '3.1.1', '<' ) ) {
						define( 'ACFFA_FORCE_REFRESH', true );
						do_action( 'ACFFA_refresh_latest_icons' );
					}
					if ( version_compare( $acffa_internal_version, '4.0.1', '<' ) ) {
						$acffa_settings['acffa_v5_compatibility_mode'] = 1;
					}
					break;
			}

			if ( version_compare( $acffa_internal_version, '4.0.3', '<' ) ) {
				define( 'ACFFA_FORCE_REFRESH', true );
				do_action( 'ACFFA_refresh_latest_icons' );
			}

			if ( $acffa_internal_version !== ACFFA_VERSION ) {
				$acffa_settings['acffa_plugin_version'] = ACFFA_VERSION;
				update_option( 'acffa_settings', $acffa_settings, false );
				delete_option( 'ACFFA_theme_install_update_needed' );
			}
		}

		public function theme_install_update_check()
		{
			$acf_font_awesome_plugindata = wp_remote_get( 'https://api.wordpress.org/plugins/info/1.0/advanced-custom-fields-font-awesome.json' );

			if ( is_wp_error( $acf_font_awesome_plugindata ) ) {
				return;
			}

			$response	= wp_remote_retrieve_body( $acf_font_awesome_plugindata );
			$plugindata = json_decode( $response );

			if ( ! isset( $plugindata->version ) || empty( $plugindata->version ) ) {
				return;
			}

			if ( version_compare( ACFFA_VERSION, $plugindata->version, '<' ) ) {
				update_option( 'ACFFA_theme_install_update_needed', $plugindata->version );
			} else {
				delete_option( 'ACFFA_theme_install_update_needed' );
			}
		}

		public function theme_install_update_needed()
		{
			if ( ! ACFFA_THEME_INSTALLATION ) {
				return;
			}

			global $pagenow;

			$show_notice = false;
			if ( 'update-core.php' == $pagenow || 'plugins.php' == $pagenow ) {
				$show_notice = true;
			}

			if ( ( isset( $_GET['post_type'] ) && 'acf-field-group' == $_GET['post_type'] ) && ( isset( $_GET['page'] ) && 'fontawesome-settings' == $_GET['page'] ) ) {
				$show_notice = true;
			}

			if ( ! $show_notice ) {
				return;
			}

			if ( ! $latest_version = get_option( 'ACFFA_theme_install_update_needed' ) ) {
				return;
			}

			$active_theme	= wp_get_theme();
			$theme_name		= $active_theme->get('Name') ? '<strong>(' . $active_theme->get('Name') . ')</strong>' : false;
			$theme_author	= $active_theme->get('AuthorURI') ? '<a href="' . $active_theme->get('AuthorURI') . '" target="_blank">' . __( 'theme author', 'acf-font-awesome' ) . '</a>' : __( 'theme author', 'acf-font-awesome' );

			$out_of_date_message = '<p>' . sprintf( __( 'There is a new version of <a href="%s" target="_blank">Advanced Custom Fields: Font Awesome</a> available. Installed Version: <strong>%s</strong>, Latest Version: <strong>%s</strong>', 'acf-font-awesome' ), 'https://wordpress.org/plugins/advanced-custom-fields-font-awesome/', ACFFA_VERSION, $latest_version ) . '<br>';
			$out_of_date_message .= "<br>\n";
			$out_of_date_message .= sprintf( __( 'It looks like this plugin is bundled with your theme: %s and is not able to receive updates. It is recommended that you contact your %s for updates. Alternatively you can install this plugin through the <a href="%s" target="_blank">WordPress Plugin Repository</a> to get the latest version.', 'acf-font-awesome' ), $theme_name, $theme_author, admin_url( 'plugin-install.php?tab=plugin-information&plugin=advanced-custom-fields-font-awesome' ) ) . '</p>';

			$out_of_date_message = apply_filters( 'ACFFA_theme_install_update_message', $out_of_date_message, ACFFA_VERSION, $latest_version );

			if ( $out_of_date_message ) {
				?>
				<div class="update-message notice notice-warning notice-alt is-dismissible">
					<?php echo wp_kses_post( $out_of_date_message ); ?>
				</div>
				<?php
			}
		}
	}

	add_action( 'acf/include_field_types', [ new acf_plugin_font_awesome, 'init' ], 10 );

endif;
