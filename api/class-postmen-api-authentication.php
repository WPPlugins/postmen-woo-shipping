<?php
/**
 * Postmen API Authentication Class
 *
 * @author      Postmen
 * @category    API
 * @package     Postmen/API
 * @since       1
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
} // Exit if accessed directly


if ( ! function_exists( 'getallheaders' ) ) {
	function getallheaders() {
		$headers = [];
		foreach ( $_SERVER as $name => $value ) {
			if ( substr( $name, 0, 5 ) == 'HTTP_' ) {
				$headers[ str_replace( ' ', '-',
					ucwords( strtolower( str_replace( '_', ' ', substr( $name, 5 ) ) ) ) ) ] = $value;
			}
		}

		return $headers;
	}
}

class Postmen_API_Authentication {

	/**
	 * Setup class
	 *
	 * @since 2.1
	 */
	public function __construct() {
		// to disable authentication, hook into this filter at a later priority and return a valid WP_User
		add_filter( 'postmen_api_check_authentication', [ $this, 'authenticate' ], 0 );
	}

	/**
	 * Authenticate the request. The authentication method varies based on whether the request was made over SSL or not.
	 *
	 * @since 2.1
	 * @return null|WP_Error|WP_User
	 * @internal param WP_User $user
	 *
	 */
	public function authenticate() {

		//TODO allow access to the index by default

		try {
			$user = $this->perform_authentication();
		} catch ( Exception $e ) {
			$user = new WP_Error( 'postmen_api_authentication_error', $e->getMessage(), [ 'status' => $e->getCode() ] );
		}

		return $user;
	}

	private function perform_authentication() {
		//$params = getPostmenInstance()->api->server->params['GET'];

		$headers = getallheaders();
		$headers = json_decode( json_encode( $headers ), true );

		// it dues to different kind of server configuration
		$key  = 'POSTMEN_WP_KEY';
		$key1 = str_replace( ' ', '-', ucwords( strtolower( str_replace( '_', ' ', $key ) ) ) );
		$key2 = 'POSTMEN-WP-KEY';

		// get Postmen wp key
		if ( ! empty( $headers[ $key ] ) ) {
			$api_key = $headers[ $key ];
		} else {
			if ( ! empty( $headers[ $key1 ] ) ) {
				$api_key = $headers[ $key1 ];
			} else {
				if ( ! empty( $headers[ $key2 ] ) ) {
					$api_key = $headers[ $key2 ];
				} else {
					throw new Exception( __( 'Postmen\'s WordPress Key is missing', 'postmen' ), 404 );
				}
			}
		}

		$user = $this->get_user_by_api_key( $api_key );

		return $user;
	}

	/**
	 * Return the user for the given consumer key
	 *
	 * @since 2.1
	 *
	 * @param $api_key
	 *
	 * @return WP_User
	 * @throws Exception
	 * @internal param string $consumer_key
	 *
	 */
	private function get_user_by_api_key( $api_key ) {

		$user_query = new WP_User_Query(
			[
				'meta_key'   => 'postmen_wp_api_key',
				'meta_value' => $api_key,
			]
		);

		$users = $user_query->get_results();

		if ( empty( $users[0] ) ) {
			throw new Exception( __( 'Postmen\'s WordPress API Key is invalid', 'postmen' ), 401 );
		}

		return $users[0];
	}

}
