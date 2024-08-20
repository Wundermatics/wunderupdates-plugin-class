<?php

// phpcs:disable WordPress.Files.FileName
class WunderUpdates_ACCOUNT_NAME_PLUGIN_SLUG {
	/**
	 * Default WP Updates API URL.
	 */
	const UPDATE_BASE = 'https://api.wunderupdates.com';

	/**
	 * API Cache Time.
	 */
	private $cache_time = HOUR_IN_SECONDS * 12;

	/**
	 * Plugin update properties.
	 *
	 * @var array
	 */
	private $properties = array();

	/**
	 * Transient key.
	 *
	 * @var array
	 */
	private $transient_key;

	/**
	 * Valid release channel keys.
	 *
	 * @var array
	 */
	private $release_channels = array(
		'stable' => 'Stable releases',
		'rc'     => 'Release candidates',
		'beta'   => 'Beta versions',
		'alpha'  => 'Alpha versions',
	);

	/**
	 * Register hooks.
	 *
	 * @param array $properties
	 *
	 * @return void
	 */
	public function register( $properties ) {
		$this->properties = $properties;
		$this->set_defaults();

		$this->transient_key = 'wunderupdates_info_response_' . sanitize_title( $this->properties['slug'] );
		$base_name           = $this->properties['base_name'];

		add_filter( 'plugins_api', array( $this, 'filter_plugin_update_info' ), 20, 3 );
		add_filter( 'site_transient_update_plugins', array( $this, 'filter_plugin_update_transient' ) );
		add_filter( 'plugin_row_meta', array( $this, 'filter_plugin_row_meta' ), 10, 4 );
		add_action( "in_plugin_update_message-$base_name", array( $this, 'action_in_plugin_update_message' ), 10, 2 );

		$md5    = md5( $this->properties['slug'] );
		$action = 'wp_ajax_wunderupdates-verify-license-' . $md5;
		add_action( $action, array( $this, 'ajax_verify_license' ) );

		$action = 'wp_ajax_wunderupdates-save-channel-' . $md5;
		add_action( $action, array( $this, 'ajax_save_channel' ) );

		$this->cache_time = apply_filters( "wunderupdates_cache_time_$base_name", $this->cache_time );
	}

	/**
	 * Filter the plugin update transient add our info to update notifications.
	 *
	 * @handles site_transient_update_plugins
	 *
	 * @param object $transient
	 *
	 * @return object
	 */
	public function filter_plugin_update_transient( $transient ) {
		if ( empty( $transient->checked ) ) {
			return $transient;
		}

		$result = $this->fetch_plugin_info();

		if ( false === $result ) {
			return $transient;
		}

		if ( version_compare( $this->properties['version'], $result->version, '<' ) ) {
			$res                                 = $this->parse_plugin_info( $result );
			$transient->response[ $res->plugin ] = $res;
			$transient->checked[ $res->plugin ]  = $result->version;
		}

		return $transient;
	}

	/**
	 * Filters the plugin update information.
	 *
	 * @param object $res
	 * @param string $action
	 * @param object $args
	 *
	 * @handles plugins_api
	 *
	 * @return object
	 */
	public function filter_plugin_update_info( $res, $action, $args ) {
		// Not our plugin slug.
		if ( $this->properties['slug'] !== $args->slug ) {
			return $res;
		}

		// Not the action we want to handle.
		if ( 'plugin_information' !== $action ) {
			return $res;
		}

		$result = $this->fetch_plugin_info();

		// do nothing if we don't get the correct response from the server
		if ( false === $result ) {
			return $res;
		}

		return $this->parse_plugin_info( $result );
	}

	/**
	 * Check if a provided license is valid via ajax.
	 *
	 * @handles wp_ajax_wunderupdates-verify-license-[md5]
	 */
	public function ajax_verify_license() {
		$nonce = sanitize_text_field( $_POST['nonce'] );
		if ( ! wp_verify_nonce( $nonce, 'wunderupdates' ) ) {
			wp_send_json_error();
		}
		$license_key = trim( sanitize_text_field( $_POST['license'] ) );

		// Remove existing transient.
		delete_transient( $this->transient_key );

		// If the license check succeeded, save the key and return.
		$license_response = $this->fetch_plugin_info( $license_key );
		if ( ( $license_response->license ?? false ) && ( $license_response->license->valid ?? false ) ) {
			update_option( $this->properties['license_option_key'], $license_key );
			wp_send_json_success(
				(object) array(
					'valid' => true,
				)
			);
		}

		// No luck. Delete option and return false.
		delete_option( $this->properties['license_option_key'] );
		wp_send_json_error(
			(object) array(
				'valid' => false,
			)
		);
	}

	/**
	 * Save the selected channel via ajax.
	 *
	 * @handles wp_ajax_wunderupdates-save-channel-[md5]
	 */
	public function ajax_save_channel() {
		$nonce = sanitize_text_field( $_POST['nonce'] );
		if ( ! wp_verify_nonce( $nonce, 'wunderupdates' ) ) {
			wp_send_json_error();
		}
		$channel = sanitize_text_field( $_POST['channel'] );

		if ( ! array_key_exists( $channel, $this->release_channels ) ) {
			wp_send_json_error();
		}

		// Save the channel.
		update_option( $this->properties['channel_option_key'], $channel );

		// Remove existing transient.
		delete_transient( $this->transient_key );

		wp_send_json_success();
	}

	/**
	 * Fetches the plugin update object from the server API.
	 *
	 * @param string|null $license_key
	 *
	 * @return object|false
	 */
	private function fetch_plugin_info( $license_key = null ) {
		//Test cache first.
		$response = get_transient( $this->transient_key );

		if ( empty( $response ) ) {
			$url = sprintf(
				'%s/v1/public/%s/plugins/%s',
				self::UPDATE_BASE,
				$this->properties['account_key'],
				$this->properties['slug']
			);

			if ( 'stable' !== $this->properties['channel'] ) {
				$url .= '/' . $this->properties['channel'];
			}

			if ( $this->properties['licensed'] ?? false ) {
				// If the license key wasn't provided, get it.
				if ( is_null( $license_key ) ) {
					$option_name = $this->properties['license_option_key'];
					$license_key = get_option( $option_name, false );
				}

				// Add the key to the URL.
				$url .= '?key=' . $license_key;
			}

			$response = wp_remote_get(
				$url,
				array(
					'timeout' => 10,
					'headers' => array(
						'Accept' => 'application/json',
					),
				)
			);

			if (
				is_wp_error( $response ) ||
				wp_remote_retrieve_response_code( $response ) !== 200 ||
				empty( wp_remote_retrieve_body( $response ) )
			) {
				return false;
			}

			$response = wp_remote_retrieve_body( $response );

			//Cache the response
			set_transient( $this->transient_key, $response, $this->cache_time );
		}

		return json_decode( $response );
	}

	/**
	 * Parses the product info response.
	 *
	 * @param object $response
	 *
	 * @return stdClass
	 */
	private function parse_plugin_info( $response ) {
		$res                = new stdClass();
		$res->name          = $response->plugin_name ?? '';
		$res->slug          = $this->properties['slug'];
		$res->version       = $response->version ?? '';
		$res->requires      = $response->requires_at_least ?? '';
		$res->requires_php  = $response->requires_php ?? '';
		$res->download_link = $response->download_link ?? '';
		$res->new_version   = $response->version ?? '';
		$res->plugin        = $this->properties['base_name'] ?? 'Unknown';
		$res->package       = $response->download_link ?? '';
		$res->tested        = $response->tested_up_to ?? '';

		$res->sections = array(
			'description' => $response->sections->description ?? '',
			'changelog'   => $response->sections->changelog ?? '',
		);

		return $res;
	}

	/**
	 * Populate default values for the plugin properties.
	 *
	 * @return void
	 */
	private function set_defaults() {
		$required = array( 'plugin_name', 'slug', 'version', 'full_path' );
		$missing  = array_diff( $required, array_keys( $this->properties ) );
		if ( ! empty( $missing ) ) {
			$this->properties['error']     = 'WunderUpdates is missing required properties: ' . join( ', ', $missing );
			$this->properties['base_name'] = '';

			return;
		}

		$this->properties = array_filter(
			$this->properties,
			function ( $value ) {
				return ! empty( $value );
			}
		);

		$parts                         = explode( '/', $this->properties['full_path'] );
		$this->properties['base_name'] = join( '/', array_slice( $parts, -2 ) );

		// Defaults for license popup strings.
		$license_popup_strings                     = array(
			'title'              => 'License key - ' . $this->properties['plugin_name'],
			'description'        => 'Enter your license key to enable updates',
			'validation_success' => 'License key is valid.',
			'validation_fail'    => 'License key validation failed.',
		);
		$this->properties['license_popup_strings'] = isset( $this->properties['license_popup_strings'] ) ?
			array_merge( $license_popup_strings, $this->properties['license_popup_strings'] ) :
			$license_popup_strings;

		// Defaults for channel popup strings.
		$channel_popup_strings = array(
			'title'              => 'Select update channel - ' . $this->properties['plugin_name'],
			'validation_success' => 'Update channel saved.',
			'validation_fail'    => 'Failed to save update channel.',
		);

		$this->properties['channel_popup_strings'] = isset( $this->properties['channel_popup_strings'] ) ?
			array_merge( $channel_popup_strings, $this->properties['channel_popup_strings'] ) :
			$channel_popup_strings;

		$default = array(
			'base_name'             => '',
			'licensed'              => false,
			'license_option_key'    => 'wunderupdates_license_' . $this->properties['slug'],
			'channel_option_key'    => 'wunderupdates_channel_' . $this->properties['slug'],
			'license_popup'         => false,
			'allow_channels'        => false,
			'channel'               => 'stable',
			'license_popup_strings' => $license_popup_strings,
			'channel_popup_strings' => $channel_popup_strings,
			'update_message'        => 'Enter a valid license key get updates.',
		);

		$this->properties = array_merge( $default, $this->properties );

		$channel                     = get_option( $this->properties['channel_option_key'], 'stable' );
		$this->properties['channel'] = array_key_exists( $channel, $this->release_channels ) ? $channel : 'stable';
	}

	/**
	 * Filter the plugin row meta.
	 *
	 * @param array  $plugin_meta
	 * @param string $plugin_file
	 *
	 * @return array
	 */
	public function filter_plugin_row_meta( $plugin_meta, $plugin_file ) {
		if ( $this->properties['base_name'] !== $plugin_file ) {
			return $plugin_meta;
		}

		if ( isset( $this->properties['error'] ) ) {
			$plugin_meta[] = $this->properties['error'];
			return $plugin_meta;
		}

		if ( false !== $this->properties['licensed'] && $this->properties['license_popup'] ) {
			require_once __DIR__ . '/popup.php';
			$plugin_meta[] = sprintf(
				'<a href="#" onClick="showLicensePopup()">%s</a>',
				__( 'Enter license', 'wp-updates' )
			);
		}

		if ( $this->properties['allow_channels'] ) {
			require_once __DIR__ . '/popup.php';
			$plugin_meta[] = sprintf(
				'%s: <a href="#" onClick="showChannelPopup()" class="change-channel-%s">%s</a>',
				__( 'Channel', 'wp-updates' ),
				$this->properties['slug'],
				$this->release_channels[ $this->properties['channel'] ]
			);
		}

		if ( ! $this->properties['allow_channels'] && 'stable' !== $this->properties['channel'] ) {
			$plugin_meta[] = sprintf(
				'%s: %s',
				__( 'Channel', 'wp-updates' ),
				$this->release_channels[ $this->properties['channel'] ]
			);
		}

		return $plugin_meta;
	}

	/**
	 * Add a custom update message.
	 *
	 * @param array  $plugin_data
	 * @param string $response
	 *
	 * @return void
	 */
	public function action_in_plugin_update_message( $plugin_data, $response ) {
		if ( ! empty( $response->package ) ) {
			return;
		}

		echo esc_html( $this->properties['update_message'] );
	}
}
