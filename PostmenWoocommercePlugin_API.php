<?php

class PostmenWoocommercePlugin_API {
	/** This is the major version for the REST API and takes
	 * first-order position in endpoint URLs
	 */
	const VERSION = 1;

	/** @var WC_API_Server the REST API server */
	public $server;

	/**
	 * Setup class
	 *
	 * @access public
	 * @since 2.0
	 * @return WC_API
	 */
	public function __construct() {

		// add query vars
		add_filter( 'query_vars', [ $this, 'add_query_vars' ], 0 );

		// register API endpoints
		add_action( 'init', [ $this, 'add_endpoint' ], 0 );

		// handle REST/legacy API request
		add_action( 'parse_request', [ $this, 'handle_api_requests' ], 0 );

// TODO temporary just start it
//		$this->add_endpoint();
	}

	/**
	 * add_query_vars function.
	 *
	 * @access public
	 * @since 2.0
	 *
	 * @param $vars
	 *
	 * @return array
	 */
	public function add_query_vars( $vars ) {
		$vars[] = 'postmen-api';
		$vars[] = 'postmen-api-route';

		return $vars;
	}

	/**
	 * add_endpoint function.
	 *
	 * @access public
	 * @since 2.0
	 * @return void
	 */
	public function add_endpoint() {
// TODO debugging info : throw new Exception('can I add endpoint?');
		// REST API
		add_rewrite_rule( '^postmen-api\/v' . self::VERSION . '/?$', 'index.php?postmen-api-route=/', 'top' );
		add_rewrite_rule( '^postmen-api\/v' . self::VERSION . '(.*)?', 'index.php?postmen-api-route=$matches[1]',
			'top' );

		// legacy API for payment gateway IPNs
		add_rewrite_endpoint( 'postmen-api', EP_ALL );
	}


	/**
	 * API request - Trigger any API requests
	 *
	 * @access public
	 * @since 2.0
	 * @return void
	 */
	public function handle_api_requests() {
		global $wp;

//throw new Exception('can I add endpoint?');
		if ( ! empty( $_GET['postmen-api'] ) ) {
			$wp->query_vars['postmen-api'] = $_GET['postmen-api'];
		}

		if ( ! empty( $_GET['postmen-api-route'] ) ) {
			$wp->query_vars['postmen-api-route'] = $_GET['postmen-api-route'];
		}

		// REST API request
		if ( ! empty( $wp->query_vars['postmen-api-route'] ) ) {

			define( 'POSTMEN_API_REQUEST', true );

			// load required files
			$this->includes();

			$this->server = new Postmen_API_Server( $wp->query_vars['postmen-api-route'] );

			// load API resource classes
			$this->register_resources( $this->server );

			// Fire off the request
			$this->server->serve_request();

			exit;
		}

		// legacy API requests
		if ( ! empty( $wp->query_vars['postmen-api'] ) ) {

			// Buffer, we won't want any output here
			ob_start();

			// Get API trigger
			$api = strtolower( esc_attr( $wp->query_vars['postmen-api'] ) );

			// Load class if exists
			if ( class_exists( $api ) ) {
				$api_class = new $api();
			}

			// Trigger actions
			do_action( 'woocommerce_api_' . $api );

			// Done, clear buffer and exit
			ob_end_clean();
			die( '1' );
		}
	}


	/**
	 * Include required files for REST API request
	 *
	 * @since 2.1
	 */
	private function includes() {

		// API server / response handlers
		include_once( 'api/class-postmen-api-server.php' );
		include_once( 'api/interface-postmen-api-handler.php' );
		include_once( 'api/class-postmen-api-json-handler.php' );

		// authentication
		include_once( 'api/class-postmen-api-authentication.php' );

		$this->authentication = new Postmen_API_Authentication();

		include_once( 'api/class-postmen-api-resource.php' );

		// self api
		include_once( 'api/class-postmen-api-orders.php' );

		// allow plugins to load other response handlers or resource classes
		do_action( 'woocommerce_api_loaded' );
	}

	/**
	 * Register available API resources
	 *
	 * @since 2.1
	 *
	 * @param object $server the REST server
	 */
	public function register_resources( $server ) {

		$api_classes = apply_filters( 'postmen_api_classes',
			[
				'Postmen_API_Orders',
			]
		);

		foreach ( $api_classes as $api_class ) {
			$this->$api_class = new $api_class( $server );
		}
	}

}
