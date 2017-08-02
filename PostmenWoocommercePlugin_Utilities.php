<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
} // Exit if accessed directly


if ( ! function_exists( 'postmen_connector_enable_tracking' ) ) {
	function postmen_connector_enable_tracking( $id, $slug, $tracking_number ) {
		if ( ! is_plugin_active( 'aftership-woocommerce-tracking/aftership.php' ) ) {
			return 'AfterShip Woocommerce Plugin is not activated';
		}

// TODO		$id = $this->validate_request($id, 'shop_order', 'read');

		if ( is_wp_error( $id ) ) {
			return $id;
		}

		$order   = new WC_Order( $id );
		$post_id = $order->post->ID;

		if ( isset( $slug ) ) {
			update_post_meta( $post_id, '_aftership_tracking_provider', wc_clean( $slug ) );
		}

		if ( isset( $tracking_number ) ) {
			update_post_meta( $post_id, '_aftership_tracking_number', wc_clean( $tracking_number ) );
		}

		if ( function_exists( 'getAfterShipInstance' ) ) {
			getAfterShipInstance()->display_tracking_info( $id );

			return 'yeah ' . $id;
		}

		return 'nah ' . $id;
	}
}


if ( ! function_exists( 'postmen_connector_get_order_notes' ) ) {
	function postmen_connector_get_order_notes( $id, $fields = null ) {
		// ensure ID is valid order ID
// TODO		$id = $this->validate_request($id, 'shop_order', 'read');

		if ( is_wp_error( $id ) ) {
			return $id;
		}

		$args = [
			'post_id' => $id,
			'approve' => 'approve',
			'type'    => 'order_note'
		];

		remove_filter( 'comments_clauses', [ 'WC_Comments', 'exclude_order_comments' ], 10, 1 );

		$notes = get_comments( $args );

		add_filter( 'comments_clauses', [ 'WC_Comments', 'exclude_order_comments' ], 10, 1 );

		$order_notes = [];

		foreach ( $notes as $note ) {

			$order_notes[] = [
				'id'            => $note->comment_ID,
				'created_at'    => $note->comment_date_gmt,
				// TODO$this->server->format_datetime($note->comment_date_gmt),
				'note'          => $note->comment_content,
				'customer_note' => get_comment_meta( $note->comment_ID, 'is_customer_note', true ) ? true : false,
			];
		}

//		return array('order_notes' => apply_filters('aftership_api_order_notes_response', $order_notes, $id, $fields, $notes, $this->server));
		return [ 'order_notes' => $order_notes ];
	}
}

if ( ! function_exists( 'postmen_connector_add_order_note' ) ) {
	function postmen_connector_add_order_note( $id, $customer, $note ) {
// TODO		$id = $this->validate_request($id, 'shop_order', 'edit');
		if ( is_wp_error( $id ) ) {
			return $id;
		}
		$order = new WC_Order( $id );
		if ( isset( $note ) ) {
			$order->add_order_note( $note, $customer );
		}

		return $id;
	}
}

if ( ! function_exists( 'postmen_connector_update_order_status' ) ) {
	function postmen_connector_update_order_status( $id, $status ) {
// TODO		$id = $this->validate_request($id, 'shop_order', 'edit');
		if ( is_wp_error( $id ) ) {
			return $id;
		}
		$order = new WC_Order( $id );
		if ( isset( $status ) ) {
			$order->update_status( $status );
		}

		//return $this->get_order($id);
		return $id;
	}
}

if ( ! function_exists( 'postmen_connector_get_orders' ) ) {
	function postmen_connector_get_orders( $fields = null, $filter = [], $status = null, $page = 1 ) {
		if ( ! empty( $status ) ) {
			$filter['status'] = $status;
		}

		$filter['page'] = $page;

		$query = postmen_connector_query_orders( $filter );

		$orders = [];

		foreach ( $query->posts as $order_id ) {
// TODO			if (!$this->is_readable($order_id))
//				continue;
			$orders[] = current( postmen_connector_get_order( $order_id, $fields ) );
		}

		return count( $orders );
	}
}

if ( ! function_exists( 'postmen_connector_get_order' ) ) {
	function postmen_connector_get_order( $id, $fields = null ) {
		// ensure order ID is valid & user has permission to read
// TODO		$id = $this->validate_request($id, 'shop_order', 'read');

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
			],
			'note'                      => $order->customer_note,
			'line_items'                => [],
			'shipping_lines'            => []
		];

		// add line items
		foreach ( $order->get_items() as $item_id => $item ) {
			$product                    = $order->get_product_from_item( $item );
			$order_data['line_items'][] = [
				'subtotal'   => wc_format_decimal( $order->get_line_subtotal( $item ), 2 ),
				'total'      => wc_format_decimal( $order->get_line_total( $item ), 2 ),
				'price'      => wc_format_decimal( $order->get_item_total( $item ), 2 ),
				'quantity'   => (int) $item['qty'],
				'name'       => $item['name'],
				'product_id' => ( isset( $product->variation_id ) ) ? $product->variation_id : $product->get_id(),
				'sku'        => is_object( $product ) ? $product->get_sku() : null
			];
		}

		// add shipping
		foreach ( $order->get_shipping_methods() as $shipping_item_id => $shipping_item ) {
			$order_data['shipping_lines'][] = [
				'id'           => $shipping_item_id,
				'method_id'    => $shipping_item['method_id'],
				'method_title' => $shipping_item['name'],
				'total'        => wc_format_decimal( $shipping_item['cost'], 2 ),
			];
		}

// TODO		return array('order' => apply_filters('aftership_api_order_response', $order_data, $order, $fields, $this->server));
		return [ 'order' => $order_data ];
	}
}


if ( ! function_exists( 'postmen_connector_query_orders' ) ) {
	function postmen_connector_query_orders( $args ) {
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
			$statuses                = explode( ',', $args['status'] );
			$query_args['tax_query'] = [
				[
					'taxonomy' => 'shop_order_status',
					'field'    => 'slug',
					'terms'    => $statuses,
				],
			];
			unset( $args['status'] );
		}
// TODO		$query_args = $this->merge_query_args($query_args, $args);
		$query_args = array_merge( $query_args, $args );

		return new WP_Query( $query_args );
	}
}

if ( ! function_exists( 'postmen_wpbo_get_woo_version_number' ) ) {
	function postmen_wpbo_get_woo_version_number() {
		// If get_plugins() isn't available, require it
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once( ABSPATH . 'wp-admin/includes/plugin.php' );
		}

		// Create the plugins folder and file variables
		$plugin_folder = get_plugins( '/' . 'woocommerce' );
		$plugin_file   = 'woocommerce.php';

		// If the plugin version number is set, return it
		if ( isset( $plugin_folder[ $plugin_file ]['Version'] ) ) {
			return $plugin_folder[ $plugin_file ]['Version'];

		} else {
			// Otherwise return null
			return null;
		}
	}
}
