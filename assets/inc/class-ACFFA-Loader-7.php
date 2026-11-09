<?php

/**
 * =======================================
 * Advanced Custom Fields Font Awesome Loader 7
 * Used with FontAwesome 7.x icon set
 * =======================================
 * 
 * 
 * @author Justin Kruit <https://profiles.wordpress.org/justinkruit>
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

		if (! current_user_can('edit_posts')) {
			wp_send_json_error(['message' => __('You do not have permission to perform this action.', 'acf-font-awesome')]);
		}

		$query = isset($_POST['query']) ? sanitize_text_field(wp_unslash($_POST['query'])) : '';
		$variables = isset($_POST['variables']) ? json_decode(wp_unslash($_POST['variables']), true) : [];
		$variables = is_array($variables) ? $variables : [];

		$request = $this->acffa_build_upstream_request($query, $variables);

		if (! $request) {
			wp_send_json_error(['message' => __('This query requests fields that are not permitted.', 'acf-font-awesome')]);
		}

		$operation_name	= array_search($query, $this->acffa_upstream_query_templates(), true);
		$cache_key		= $this->acffa_query_cache_key($operation_name, $request);

		if ($cache_key) {
			$cached_response = get_transient($cache_key);

			if (is_string($cached_response) && $cached_response !== '') {
				wp_send_json_success(json_decode($cached_response));
			}
		}

		$remote_get = wp_remote_post('https://api.fontawesome.com', [
			'headers'	=> [
				'Content-Type'	=> 'application/json',
				'Authorization'	=> 'Bearer ' . apply_filters('ACFFA_fontawesome_access_token', false),
			],
			'timeout'	=> 15,
			'body'		=> json_encode($request)
		]);

		if (! is_wp_error($remote_get) && 200 === (int) wp_remote_retrieve_response_code($remote_get)) {
			$response_json	= wp_remote_retrieve_body($remote_get);
			$response		= $response_json ? json_decode($response_json) : null;

			if ($response) {
				if (empty($response->errors)) {
					if ('KitRevision' === $operation_name) {
						$this->acffa_store_kit_revision($response);
					} else if ($cache_key) {
						set_transient($cache_key, $response_json, $this->acffa_query_cache_ttl($operation_name));
					}
				}

				wp_send_json_success($response);
			}
		}

		wp_send_json_error();
	}

	/**
	 * Server-side cache key for upstream responses that are safe to share
	 * between requests and users. Kit data is keyed on the kit's current
	 * revision (as reported by the never-cached KitRevision probe, which the
	 * icon chooser always runs first), so a changed kit is never served
	 * stale data. Returns false for responses that must not be cached.
	 */
	private function acffa_query_cache_key($operation_name, $request) {
		switch ($operation_name) {
			case 'Search':
				// Search results are fixed for a given Font Awesome version.
				return 'ACFFA_q_' . md5(wp_json_encode($request));

			case 'KitMetadata':
			case 'ShowcaseIcons':
				$revision = get_option('ACFFA_kit_revision');

				if (! is_array($revision) || ($revision['kit'] ?? '') !== $this->kit_token || empty($revision['revision'])) {
					return false;
				}

				return 'ACFFA_q_' . md5(wp_json_encode($request) . '|' . $revision['revision']);
		}

		return false;
	}

	private function acffa_query_cache_ttl($operation_name) {
		return 'Search' === $operation_name ? WEEK_IN_SECONDS : DAY_IN_SECONDS;
	}

	private function acffa_store_kit_revision($response) {
		$kit = $response->data->me->kit ?? null;

		if (! $kit || empty($this->kit_token)) {
			return;
		}

		$revision = [
			'kit'		=> $this->kit_token,
			'revision'	=> (string) ($kit->kitRevision ?? '') . '|' . (string) ($kit->showcaseCacheKey ?? ''),
		];

		if (get_option('ACFFA_kit_revision') !== $revision) {
			update_option('ACFFA_kit_revision', $revision, false);
		}
	}

	/**
	 * The bundled fa-icon-chooser web component (version pinned in
	 * assets/js/fa-icon-chooser.esm.js) only ever issues a small, fixed set of
	 * GraphQL documents. Rather than trying to validate arbitrary
	 * client-supplied GraphQL text, recognize which of these fixed operations
	 * was requested and send our own canonical copy of it--never the
	 * client's original text--with arguments bound to trusted values.
	 * Anything that doesn't match one of these documents exactly is rejected.
	 *
	 * Captured from @fortawesome/fa-icon-chooser 0.11.0 (the version this
	 * plugin loads).
	 */
	private function acffa_upstream_query_templates() {
		return [
			// Kit metadata.
			'KitMetadata'   => 'query KitMetadata($token: String!) { me { kit(token: $token) { kitRevision showcaseCacheKey familyStylesPaginated(page: 1, pageSize: 50) { familyStyles { familyStyle { family style prefix } } } version technologySelected licenseSelected name permits { embedProSvg { prefix family } } release { version } iconUploads { name unicode version width height pathData } } } }',

			// Lightweight kit-identity probe run before KitMetadata/SearchKit/ShowcaseIcons to check cache freshness.
			'KitRevision'   => 'query KitRevision($token: String!) { me { kit(token: $token) { kitRevision showcaseCacheKey } } }',

			// Icon search when no kit token is configured (free/CDN version mode).
			'Search'        => 'query Search($version: String!, $query: String!) { search(version: $version, query: $query, first: 100) { id label familyStylesByLicense { free { family style } pro { family style } } } }',

			// Icon search in kit mode, replaces Search when a kit token is configured.
			'SearchKit'     => 'query SearchKit($token: String!, $query: String!, $searchMode: KitSearchMode!, $page: Int!, $pageSize: Int!) { me { kit(token: $token) { searchKit(query: $query, searchMode: $searchMode, page: $page, pageSize: $pageSize) { page pageSize totalIconCount totalPageCount icons { __typename ... on IconWithVariants { name label unicodeHex variants { name unicodeHex familyStyle { family style prefix } } } ... on IconUpload { name unicodeHex width height pathData } } } } } }',

			// Opening icon showcase for one family-style, in kit mode.
			'ShowcaseIcons' => 'query ShowcaseIcons($token: String!, $selector: FamilyStyleSelector!) { me { kit(token: $token) { showcaseIcons(selector: $selector, page: 1, pageSize: 80, limitPerFamilyStyle: 80) { page pageSize totalIconVariantCount totalPageCount iconVariants { name unicodeHex familyStyle { family style prefix } } } } } }',
		];
	}

	private function acffa_build_upstream_request($query, $variables) {
		if (! is_string($query) || trim($query) === '') {
			return false;
		}

		$operation_name = array_search($query, $this->acffa_upstream_query_templates(), true);

		if ($operation_name === false) {
			return false;
		}

		switch ($operation_name) {
			case 'KitMetadata':
			case 'KitRevision':
				if (empty($this->kit_token)) {
					return false;
				}

				return [
					'query'		=> $query,
					'variables'	=> ['token' => $this->kit_token],
				];

			case 'Search':
				return $this->acffa_build_search_request($query, $variables);

			case 'SearchKit':
				return $this->acffa_build_search_kit_request($query, $variables);

			case 'ShowcaseIcons':
				return $this->acffa_build_showcase_icons_request($query, $variables);
		}

		return false;
	}

	private function acffa_build_search_request($query, $variables) {
		if (! isset($variables['query']) || ! is_string($variables['query'])) {
			return false;
		}

		if (! isset($variables['version']) || ! is_string($variables['version']) || ! preg_match('/^\d+\.\d+\.\d+(?:-[0-9A-Za-z.]+)?$/', $variables['version'])) {
			return false;
		}

		return [
			'query'		=> $query,
			'variables'	=> [
				'version'	=> $variables['version'],
				'query'		=> substr(sanitize_text_field($variables['query']), 0, 100),
			],
		];
	}

	private function acffa_build_search_kit_request($query, $variables) {
		if (empty($this->kit_token)) {
			return false;
		}

		if (! isset($variables['query']) || ! is_string($variables['query'])) {
			return false;
		}

		if (! isset($variables['searchMode']) || ! in_array($variables['searchMode'], ['OFFICIAL', 'CUSTOM'], true)) {
			return false;
		}

		$page		= $variables['page'] ?? null;
		$page_size	= $variables['pageSize'] ?? null;

		if (! is_int($page) || $page < 1 || $page > 5) {
			return false;
		}

		if (! is_int($page_size) || $page_size < 1 || $page_size > 50) {
			return false;
		}

		return [
			'query'		=> $query,
			'variables'	=> [
				'token'			=> $this->kit_token,
				'query'			=> substr(sanitize_text_field($variables['query']), 0, 100),
				'searchMode'	=> $variables['searchMode'],
				'page'			=> $page,
				'pageSize'		=> $page_size,
			],
		];
	}

	private function acffa_build_showcase_icons_request($query, $variables) {
		if (empty($this->kit_token)) {
			return false;
		}

		$prefix = isset($variables['selector']) && is_array($variables['selector']) ? ($variables['selector']['prefix'] ?? null) : null;

		if (! is_string($prefix) || ! preg_match('/^[a-z]{2,10}$/', $prefix)) {
			return false;
		}

		return [
			'query'		=> $query,
			'variables'	=> [
				'token'		=> $this->kit_token,
				'selector'	=> ['prefix' => $prefix],
			],
		];
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
			'fak'		  => 'kit_custom'
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
