<?php
/**
 * Postmen API Orders Class
 *
 * Handles requests to the /orders endpoint
 *
 * @author      Postmen
 * @category    API
 * @package     Postmen/API
 * @since       1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
} // Exit if accessed directly

class Postmen_API_Orders extends Postmen_API_Resource {

	/** @var string $base the route base */
	protected $base = '/orders';

	/**
	 * Register the routes for this class
	 *
	 * GET /orders
	 * GET /orders/count
	 *
	 * @since 2.1
	 *
	 * @param array $routes
	 *
	 * @return array
	 */
	public function register_routes( $routes ) {
		# GET /orders/ping
		$routes[ $this->base . '/ping' ] = [
			[ [ $this, 'ping' ], Postmen_API_Server::READABLE ],
		];

		# GET /orders/get_orders
		$routes[ $this->base . '/get_orders' ] = [
			[ [ $this, 'get_orders' ], Postmen_API_Server::READABLE ],
		];

		# PUT /orders/update_order
		$routes[ $this->base . '/update_order' ] = [
			[ [ $this, 'update_order' ], Postmen_API_Server::METHOD_PUT | Postmen_API_Server::ACCEPT_DATA ],
		];

		return $routes;
	}

	/**
	 * heath checkendpoint for wordpress url validation
	 *
	 * @since 2.1
	 * @return string
	 */
	public function ping() {
		return 'pong';
	}

	/**
	 * Get orders
	 *
	 * @since 2.1
	 *
	 * @param string $updated_at_min
	 * @param string $updated_at_max
	 * @param string $max_results_number
	 *
	 * @return array
	 */
	public function get_orders( $updated_at_min = null, $updated_at_max = null, $max_results_number = null ) {
		$args        = [
			'updated_at_min' => $updated_at_min,
			'updated_at_max' => $updated_at_max,
			'orderby'        => 'modified',
			'order'          => 'ASC',
			'limit'          => $max_results_number
		];
		$weight_unit = get_option( 'woocommerce_weight_unit' );
//		return $weight_unit;
		$query  = $this->query_orders( $args );
		$orders = [];
		foreach ( $query->posts as $order_id ) {
			if ( ! $this->is_readable( $order_id ) ) {
				continue;
			}
			$orders[] = current( $this->get_order( $order_id, $weight_unit ) );
		}

		//return array('from' => $from, 'to' => $to, 'max' => $max);
		return $orders;
	}

	/**
	 * Update a particular order
	 *
	 * @since 2.1
	 *
	 * @param $data
	 *
	 * @return array
	 */
	public function update_order( $data ) {
		$id                     = $data['id'];
		$status                 = $data['status'];
		$note                   = $data['note'];
		$customer_note          = $data['customer_note'];
		$tracking_slug          = $data['tracking_slug'];
		$tracking_number        = $data['tracking_number'];
		$actions_sync_aftership = isset( $data['actions_sync_aftership'] ) ? $data['actions_sync_aftership'] : false;

		$id = $this->validate_request( $id, 'shop_order', 'read' );

		if ( is_wp_error( $id ) ) {
			return $id;
		}

		$order      = new WC_Order( $id );
		$order_post = get_post( $id );
		$post_id    = $order->post->ID;

		// update order status
		if ( $status ) {
			// Woocommerce is quite good at guessing which status do we mean
			// eventually we can do it manually using following function
			// wc_get_order_statuses();
			$order->update_status( $status );
		}
		// create order note if order note string is not empty
		if ( $note ) {
			$order->add_order_note( $note, $customer_note );
		}

		// create aftership tracking if aftership plugin enabled
		if ( is_plugin_active( 'aftership-woocommerce-tracking/aftership.php' ) ) {
			if ( isset( $tracking_slug ) ) {
				update_post_meta( $post_id, '_aftership_tracking_provider', wc_clean( $tracking_slug ) );
			}

			if ( isset( $tracking_number ) && $actions_sync_aftership == true ) {
				update_post_meta( $post_id, '_aftership_tracking_number',
					wc_clean( is_array( $tracking_number ) ? array_shift( $tracking_number ) : $tracking_number ) );
			}

			if ( function_exists( 'getAfterShipInstance' ) ) {
				// surpress the output from aftership plugin to not cause exception on
				// worker side
				ob_start();
				getAfterShipInstance()->display_tracking_info( $id );
				ob_end_clean();
			}
		}

		return $data;
	}

	private function get_order( $id, $weight_unit ) {
		// ensure order ID is valid & user has permission to read
		$id = $this->validate_request( $id, 'shop_order', 'read' );

		if ( is_wp_error( $id ) ) {
			return $id;
		}

		$order = new WC_Order( $id );

		$order_post = get_post( $id );

		$order_data = [
			'id'                        => $order->get_id(),
			'order_number'              => $order->get_order_number(),
			'created_at'                => $order_post->post_date_gmt,
			'updated_at'                => $order_post->post_modified_gmt,
			'status'                    => $order->status,
			'currency'                  => $order->order_currency,
			'total'                     => wc_format_decimal( $order->get_total(), 2 ),
			'total_line_items_quantity' => $order->get_item_count(),
			'total_shipping'            => wc_format_decimal( $order->get_total_shipping(), 2 ),
			'shipping_methods'          => $order->get_shipping_method(),
			'weight_unit'               => $weight_unit,
			'payment_details'           => [
				'method_id'    => $order->payment_method,
				'method_title' => $order->payment_method_title,
				'paid'         => isset( $order->paid_date ),
			],
			'shipping_address'          => [
				'first_name' => $order->shipping_first_name,
				'last_name'  => $order->shipping_last_name,
				'company'    => $order->shipping_company,
				'address_1'  => $order->shipping_address_1,
				'address_2'  => $order->shipping_address_2,
				'city'       => $order->shipping_city,
				'state'      => $order->shipping_state,
				'postcode'   => $order->shipping_postcode,
				'country'    => $order->shipping_country,
				'email'      => $order->billing_email,
				'phone'      => $order->billing_phone
			],
			'note'                      => $order->customer_note,
			'line_items'                => [],
			'shipping_lines'            => []
		];

		// add line items
		foreach ( $order->get_items() as $item_id => $item ) {
			if ( is_callable( $item, 'get_product' ) ) {
				$product = $item->get_product();
			} else {
				$product = $order->get_product_from_item( $item );
			}
			if ( empty( $product ) ) {
				// if the $product is empty then the following lines will crash
				continue;
			} else {
				$weight = $product->get_weight();
			}
			$product_id = ( isset( $product->variation_id ) ) ? $product->variation_id : $product->get_id();
			// set the response object
			$order_data['line_items'][] = [
				'subtotal'    => wc_format_decimal( $order->get_line_subtotal( $item ), 2 ),
				'total'       => wc_format_decimal( $order->get_line_total( $item ), 2 ),
				'price'       => wc_format_decimal( $order->get_item_total( $item ), 2 ),
				'quantity'    => (int) $item['qty'],
				'name'        => $item['name'],
				'product_id'  => $product_id,
				'sku'         => is_object( $product ) ? $product->get_sku() : null,
				'weight'      => $weight,
				'description' => get_post( $product_id )->post_content ? get_post( $product_id )->post_content : ''
			];
		}

		// add shipping
		foreach ( $order->get_shipping_methods() as $shipping_item_id => $shipping_item ) {
			$order_data['shipping_lines'][] = [
				'id'           => $shipping_item['method_id'],
				'method_title' => $shipping_item['name'],
				'total'        => wc_format_decimal( $shipping_item['cost'], 2 ),
			];
		}

		return [ 'order' => $order_data ];
	}

	/**
	 * Helper method to get order post objects
	 *
	 * @since 2.1
	 *
	 * @param array $args request arguments for filtering query
	 *
	 * @return WP_Query
	 */
	private function query_orders( $args ) {
		$woo_version = postmen_wpbo_get_woo_version_number();

		if ( $woo_version >= 2.2 ) {
			// set base query arguments
			$query_args = [
				'fields'      => 'ids',
				'post_type'   => 'shop_order',
				'post_status' => array_keys( wc_get_order_statuses() )
			];
		} else {
			// set base query arguments
			$query_args = [
				'fields'      => 'ids',
				'post_type'   => 'shop_order',
				'post_status' => 'publish',
			];
		}

		// add status argument
		if ( ! empty( $args['status'] ) ) {

			$statuses = explode( ',', $args['status'] );

			$query_args['tax_query'] = [
				[
					'taxonomy' => 'shop_order_status',
					'field'    => 'slug',
					'terms'    => $statuses,
				],
			];

			unset( $args['status'] );
		}

		$query_args = $this->merge_query_args( $query_args, $args );

		return new WP_Query( $query_args );
	}

}
