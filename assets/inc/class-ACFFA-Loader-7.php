<?php

/**
 * =======================================
 * Advanced Custom Fields Font Awesome Loader 7
 * Used with FontAwesome 7.x icon set
 * =======================================
 * 
 * 
 * @author Matt Keys <https://profiles.wordpress.org/mattkeys>
 */

if (! defined('ABSPATH')) {
	exit;
}

class ACFFA_Loader_7 {
	private $kit_token					= false;
	private $latest_version_timestamp	= false;
	private $options					= false;

	public function __construct() {
		$acffa_settings					= get_option('acffa_settings', []);
		$this->kit_token				= isset($acffa_settings['acffa_kit']) ? sanitize_text_field($acffa_settings['acffa_kit']) : false;
		$this->latest_version_timestamp	= get_option('ACFFA_latest_version_timestamp', time());
		$this->options					= $acffa_settings;

		if ($ACFFA_fa_kit_token = apply_filters('ACFFA_fa_kit_token', false)) {
			$this->kit_token = $ACFFA_fa_kit_token;
		}

		add_filter('ACFFA_get_fa_url', [$this, 'get_fa_url'], 5, 1);
		add_filter('ACFFA_fontawesome_kit_token', [$this, 'get_token'], 5, 0);
		add_filter('ACFFA_icon_prefix_label', [$this, 'get_prefix_label'], 5, 2);
		add_filter('ACFFA_get_latest_version', [$this, 'get_latest_version'], 5, 2);
		add_filter('ACFFA_fontawesome_access_token', [$this, 'get_access_token'], 5, 2);
		add_filter('ACFFA_family_style_string_to_array', [$this, 'family_style_string_to_array'], 10, 2);
		add_filter('ACFFA_standardize_custom_icon_set_family_style', [$this, 'standardize_custom_icon_set_family_style'], 10, 1);
		add_filter('ACFFA_standardize_icon_set_family_style', [$this, 'standardize_icon_set_family_style'], 10, 1);
		add_filter('ACFFA_default_family_by_style', [$this, 'get_default_family_by_style'], 10, 2);
		add_filter('script_loader_tag', [$this, 'fa_kit_script_attributes'], 10, 3);
		add_filter('script_loader_tag', [$this, 'js_api_script_attributes'], 10, 3);
		add_action('wp_ajax_acffa_fa_query', [$this, 'fa_query_request']);
	}

	public function fa_query_request() {
		check_ajax_referer('acffa_nonce', 'nonce');

		$query = isset($_POST['query']) ? sanitize_text_field(wp_unslash($_POST['query'])) : '';
		$variables = isset($_POST['variables']) ? wp_unslash($_POST['variables']) : [];

		$body = [
			'query'		=> $query,
			'variables'	=> $variables
		];

		$remote_get = wp_remote_post('https://api.fontawesome.com', [
			'headers'	=> [
				'Content-Type'	=> 'application/json',
				'Authorization'	=> 'Bearer ' . apply_filters('ACFFA_fontawesome_access_token', false),
			],
			'timeout'	=> 30,
			'body'		=> json_encode($body)
		]);

		if (! is_wp_error($remote_get)) {
			$response_json = wp_remote_retrieve_body($remote_get);

			if ($response_json) {
				wp_send_json_success(json_decode($response_json));
			}
		}

		wp_send_json_error();
	}

	public function get_access_token($access_token, $new_api_key = false) {
		$api_key = $new_api_key ? $new_api_key : false;

		if ($ACFFA_fa_api_key = apply_filters('ACFFA_fa_api_key', false)) {
			$api_key = $ACFFA_fa_api_key;
		} else if (! $api_key) {
			$api_key = isset($this->options['acffa_api_key']) && ! empty($this->options['acffa_api_key']) ? $this->options['acffa_api_key'] : false;
		}

		if (! $api_key) {
			return;
		}

		if (! $access_token = get_transient('ACFFA_access_token')) {
			$remote_get = wp_remote_post('https://api.fontawesome.com/token', [
				'headers'	=> [
					'Content-Type'	=> 'application/json',
					'Authorization'	=> 'Bearer ' . $api_key,
				],
				'timeout'	=> 30
			]);

			if (! is_wp_error($remote_get)) {
				$response_json = wp_remote_retrieve_body($remote_get);

				if ($response_json) {
					$response = json_decode($response_json);
					if (isset($response->access_token)) {
						$access_token	= $response->access_token;
						$expire_time	= $response->expires_in - 5;
						set_transient('ACFFA_access_token', $access_token, $expire_time);
						update_option('ACFFA_last_api_call_status', 'success');
					} else {
						update_option('ACFFA_last_api_call_status', 'error');
					}
				}
			}
		}

		return $access_token;
	}

	public function get_fa_url() {
		if ($this->kit_token) {
			return 'https://kit.fontawesome.com/' . $this->kit_token . '.js';
		} else {
			$cdn_baseurl	= 'https://use.fontawesome.com/releases/v';
			$latest_version	= apply_filters('ACFFA_get_latest_version', '7.0.0');
			$cdn_filepath	= '/css/all.css';

			return $cdn_baseurl . $latest_version . $cdn_filepath;
		}
	}

	public function get_token() {
		return $this->kit_token;
	}

	public function get_prefix_label($label, $prefix) {
		$label = empty($label) ? 'regular' : $label;

		switch ($prefix) {
			case 'classic_solid':
			case 'solid':
				$label = __('Classic (Solid)', 'acf-font-awesome');
				break;

			case 'classic_brands':
			case 'brands':
				$label = __('Brands', 'acf-font-awesome');
				break;

			case 'classic_light':
			case 'light':
				$label = __('Classic (Light)', 'acf-font-awesome');
				break;

			case 'classic_thin':
			case 'thin':
				$label = __('Classic (Thin)', 'acf-font-awesome');
				break;

			case 'sharp_solid':
				$label = __('Solid (Sharp)', 'acf-font-awesome');
				break;

			case 'sharp_regular':
				$label = __('Regular (Sharp)', 'acf-font-awesome');
				break;

			case 'sharp_light':
				$label = __('Light (Sharp)', 'acf-font-awesome');
				break;

			case 'sharp_thin':
				$label = __('Thin (Sharp)', 'acf-font-awesome');
				break;

			case 'duotone_solid':
			case 'duotone':
				$label = __('Duotone', 'acf-font-awesome');
				break;

			case 'duotone_regular':
				$label = __('Duotone (Regular)', 'acf-font-awesome');
				break;

			case 'duotone_light':
				$label = __('Duotone (Light)', 'acf-font-awesome');
				break;

			case 'duotone_thin':
				$label = __('Duotone (Thin)', 'acf-font-awesome');
				break;

			case 'sharp-duotone_solid':
				$label = __('Sharp Duotone (Solid)', 'acf-font-awesome');
				break;

			case 'sharp-duotone_regular':
				$label = __('Sharp Duotone (Regular)', 'acf-font-awesome');
				break;

			case 'sharp-duotone_light':
				$label = __('Sharp Duotone (Light)', 'acf-font-awesome');
				break;

			case 'sharp-duotone_thin':
				$label = __('Sharp Duotone (Thin)', 'acf-font-awesome');
				break;

			case 'whiteboard_semibold':
				$label = __('Whiteboard (SemiBold)', 'acf-font-awesome');
				break;

			case 'etch_solid':
				$label = __('Etch (Solid)', 'acf-font-awesome');
				break;

			case 'slab_regular':
				$label = __('Slab (Regular)', 'acf-font-awesome');
				break;

			case 'slab-press_regular':
				$label = __('Slab Press (Regular)', 'acf-font-awesome');
				break;

			case 'thumbprint_light':
				$label = __('Thumbprint (Light)', 'acf-font-awesome');
				break;

			case 'jelly_regular':
				$label = __('Jelly (Regular)', 'acf-font-awesome');
				break;

			case 'jelly-duo_regular':
				$label = __('Jelly Duo (Regular)', 'acf-font-awesome');
				break;

			case 'jelly-fill_regular':
				$label = __('Jelly Fill (Regular)', 'acf-font-awesome');
				break;

			case 'chisel_regular':
				$label = __('Chisel (Regular)', 'acf-font-awesome');
				break;

			case 'notdog_solid':
				$label = __('Notdog (Solid)', 'acf-font-awesome');
				break;

			case 'notdog-duo_solid':
				$label = __('Notdog Duo (Solid)', 'acf-font-awesome');
				break;

			case 'utility_semisolid':
				$label = __('Utility (Solid)', 'acf-font-awesome');
				break;

			case 'utility-duo_semisolid':
				$label = __('Utility Duo (Solid)', 'acf-font-awesome');
				break;

			case 'utility-fill_semisolid':
				$label = __('Utility Fill (Solid)', 'acf-font-awesome');
				break;

			case 'kit_custom':
			case 'custom':
			case 'fak':
				$label = __('Uploaded Icons', 'acf-font-awesome');
				break;

			case 'kit-duotone_custom':
				$label = __('Uploaded Duotone Icons', 'acf-font-awesome');
				break;

			case 'classic_regular':
			case 'regular':
			default:
				$label = __('Classic (Regular)', 'acf-font-awesome');
				break;
		}

		return $label;
	}

	public function get_latest_version($version, $recheck = false) {
		if ($recheck || ! $version = get_option('ACFFA_latest_version')) {
			$remote_get = wp_remote_post('https://api.fontawesome.com', [
				'headers'	=> [
					'Content-Type'	=> 'application/json'
				],
				'timeout'	=> 30,
				'body'			=> '{
					"query" : "query { release(version:\"7.x\") { version } }"
				}'
			]);

			if (! is_wp_error($remote_get)) {
				$response_json = wp_remote_retrieve_body($remote_get);

				if ($response_json) {
					$response = json_decode($response_json);

					if (isset($response->data->release->version)) {
						$version = $response->data->release->version;
						update_option('ACFFA_latest_version_timestamp', time());
						update_option('ACFFA_latest_version', $version);
					}
				}
			}
		}

		return $version;
	}

	public function fa_kit_script_attributes($tag, $handle, $src) {
		if ('acffa_font-awesome-kit' !== $handle) {
			return $tag;
		}

		if (stristr($src, 'https://kit.fontawesome.com/')) {
			$tag = str_replace('<script', '<script crossorigin="anonymous"', $tag);
		}

		return $tag;
	}

	public function js_api_script_attributes($tag, $handle, $src) {
		if ('acffa_fontawesome-js-api' !== $handle) {
			return $tag;
		}

		$tag = str_replace('<script', '<script data-auto-replace-svg="false" data-auto-a11y="false" data-auto-add-css="false" data-observe-mutations="false"', $tag);

		return $tag;
	}

	public function family_style_string_to_array($icon_details, $family_style) {
		switch ($family_style) {
			case 'classic_solid':
				$icon_details = [
					'family'	=> 'classic',
					'style'		=> 'solid',
					'prefix'	=> 'fas'
				];
				break;

			case 'classic_regular':
				$icon_details = [
					'family'	=> 'classic',
					'style'		=> 'regular',
					'prefix'	=> 'far'
				];
				break;

			case 'classic_light':
				$icon_details = [
					'family'	=> 'classic',
					'style'		=> 'light',
					'prefix'	=> 'fal'
				];
				break;

			case 'classic_thin':
				$icon_details = [
					'family'	=> 'classic',
					'style'		=> 'thin',
					'prefix'	=> 'fat'
				];
				break;

			case 'sharp_solid':
				$icon_details = [
					'family'	=> 'sharp',
					'style'		=> 'solid',
					'prefix'	=> 'fass'
				];
				break;

			case 'sharp_regular':
				$icon_details = [
					'family'	=> 'sharp',
					'style'		=> 'regular',
					'prefix'	=> 'fasr'
				];
				break;

			case 'sharp_light':
				$icon_details = [
					'family'	=> 'sharp',
					'style'		=> 'light',
					'prefix'	=> 'fasl'
				];
				break;

			case 'sharp_thin':
				$icon_details = [
					'family'	=> 'sharp',
					'style'		=> 'thin',
					'prefix'	=> 'fast'
				];
				break;

			case 'classic_brands':
				$icon_details = [
					'family'	=> 'classic',
					'style'		=> 'brands',
					'prefix'	=> 'fab'
				];
				break;

			case 'duotone_solid':
				$icon_details = [
					'family'	=> 'duotone',
					'style'		=> 'solid',
					'prefix'	=> 'fad'
				];
				break;

			case 'sharp-duotone_solid':
				$icon_details = [
					'family'	=> 'sharp-duotone',
					'style'		=> 'solid',
					'prefix' 	=> 'fasds'
				];
				break;

			case 'sharp-duotone_regular':
				$icon_details = [
					'family' 	=> 'sharp-duotone',
					'style' 	=> 'regular',
					'prefix' 	=> 'fasdr'
				];
				break;

			case 'sharp-duotone_light':
				$icon_details = [
					'family' 	=> 'sharp-duotone',
					'style' 	=> 'light',
					'prefix' 	=> 'fasdl'
				];
				break;

			case 'sharp-duotone_thin':
				$icon_details = [
					'family' 	=> 'sharp-duotone',
					'style' 	=> 'thin',
					'prefix' 	=> 'fasdt'
				];
				break;

			case 'kit_custom':
				$icon_details = [
					'family'	=> 'kit',
					'style'		=> 'custom',
					'prefix'	=> 'fak'
				];
				break;

			case 'kit-duotone_custom':
				$icon_details = [
					'family'	=> 'kit-duotone',
					'style'		=> 'custom',
					'prefix'	=> 'fakd'
				];
				break;
		}

		return $icon_details;
	}

	private function maybe_recheck_latest_version() {
		if ((time() - $this->latest_version_timestamp) > HOUR_IN_SECONDS) {
			$latest_version	= apply_filters('ACFFA_get_latest_version', '7.0.0', true);
		}
	}

	public function standardize_custom_icon_set_family_style($custom_icon_set) {
		if (! is_array($custom_icon_set)) {
			return $custom_icon_set;
		}

		if (empty($custom_icon_set)) {
			return $custom_icon_set;
		}

		$replacements = [
			'solid'		=> 'classic_solid',
			'regular'	=> 'classic_regular',
			'light'		=> 'classic_light',
			'thin'		=> 'classic_thin',
			'brands'	=> 'classic_brands',
			'duotone'	=> 'duotone_solid',
			'custom'	=> 'kit_custom'
		];

		foreach ($custom_icon_set as $key => $icons) {
			if (isset($replacements[$key])) {
				$custom_icon_set[$replacements[$key]] = $icons;
				unset($custom_icon_set[$key]);
			}
		}

		return $custom_icon_set;
	}

	public function get_default_family_by_style($default_family, $style) {
		switch ($style) {
			case 'custom':
			case 'fak':
				$default_family = 'kit';
				break;

			case 'duotone':
				$default_family = 'duotone';
				break;

			default:
				$default_family = 'classic';
				break;
		}

		return $default_family;
	}

	public function standardize_icon_set_family_style($icon_set) {
		if (! is_array($icon_set)) {
			return $icon_set;
		}

		if (empty($icon_set)) {
			return $icon_set;
		}

		$replacements = [
			'solid'		=> 'classic_solid',
			'regular'	=> 'classic_regular',
			'light'		=> 'classic_light',
			'thin'		=> 'classic_thin',
			'brands'	=> 'classic_brands',
			'duotone'	=> 'duotone_solid',
			'custom'	=> 'kit_custom',
		];

		foreach ($icon_set as $key => $value) {
			if (isset($replacements[$value])) {
				$icon_set[$key] = $replacements[$value];
			}
		}

		return $icon_set;
	}
}

new ACFFA_Loader_7();
