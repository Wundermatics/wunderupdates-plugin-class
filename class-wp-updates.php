<?php

class WunderUpdates_ACCOUNT_NAME_PLUGIN_SLUG {
	/**
	 * Default WP Updates API URL.
	 */
	const UPDATE_BASE = 'https://api.wunderupdates.com';

	/**
	 * API Cache Time.
	 */
	const CACHE_TIME = HOUR_IN_SECONDS * 12;

	/**
	 * Plugin properties in array format.
	 *
	 * @var array
	 */
	private $properties;


	/**
	 * Update class constructor.
	 *
	 * @param array $properties
	 * 
	 * @return void
	 */
	public function __construct( $properties ) {
		$this->register( $properties );
	}

	/**
	 * Register hooks.
	 *
	 * @param array $properties
	 * 
	 * @return void
	 */
	public function register( $properties ) {
		$parts                   = explode( '/', $properties['full_path'] );
		$properties['base_name'] = join( '/', array_slice( $parts, -2 ) );
		$this->properties        = $properties;

		add_filter( 'plugins_api', array( $this, 'filter_plugin_update_info' ), 20, 3 );
		add_filter( 'site_transient_update_plugins', array( $this, 'filter_plugin_update_transient' ) );
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
	 * Fetches the plugin update object from the server API.
	 *
	 * @return object|false
	 */
	private function fetch_plugin_info() {
		//Fetch cache first
		$transient_name = $this->get_transient_key();
		$response       = get_transient( $transient_name );

		if ( empty( $response ) ) {
			$response = wp_remote_get(
				sprintf(
					'%s/public/%s/plugins/%s',
					self::UPDATE_BASE,
					$this->properties['account_key'],
					$this->properties['slug']
				),
				array(
					'timeout' => 10,
					'headers' => array(
						'Accept' => 'application/json',
					),
				)
			);

			if (
				is_wp_error( $response ) ||
				200 !== wp_remote_retrieve_response_code( $response ) ||
				empty( wp_remote_retrieve_body( $response ) )
			) {
				return false;
			}

			$response = wp_remote_retrieve_body( $response );

			//Cache the response
			set_transient( $transient_name, $response, self::CACHE_TIME );
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
		$res->plugin        = $this->properties['base_name'];
		$res->package       = $response->download_link ?? '';
		$res->tested        = $response->tested_up_to ?? '';

		$res->sections = array(
			'description' => $response->sections->description ?? '',
			'changelog'   => $response->sections->changelog ?? '',
		);

		return $res;
	}

	/**
	 * Return the transient key for the plugin info response.
	 *
	 * @return string
	 */
	private function get_transient_key() {
		return 'wpup_update_info_response_' . sanitize_title( $this->properties['slug'] );
	}
}
