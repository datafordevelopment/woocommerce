<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles Refunds and other API requests such as capture.
 * @since 2.7.0
 */
class WC_Gateway_Paypal_API_Handler {

	/** @var string API Username */
	public static $api_username;

	/** @var string API Password */
	public static $api_password;

	/** @var string API Signature */
	public static $api_signature;

	/** @var bool Sandbox */
	public static $sandbox = false;

	/**
	 * Get capture request args.
	 * See https://developer.paypal.com/docs/classic/api/merchant/DoCapture_API_Operation_NVP/.
	 * @param  WC_Order $order
	 * @param  float    $amount
	 * @return array
	 */
	public static function get_capture_request( $order, $amount = null ) {
		$request = array(
			'VERSION'         => '84.0',
			'SIGNATURE'       => self::$api_signature,
			'USER'            => self::$api_username,
			'PWD'             => self::$api_password,
			'METHOD'          => 'DoCapture',
			'AUTHORIZATIONID' => $order->get_transaction_id(),
			'AMT'             => number_format( is_null( $amount ) ? $order->get_total() : $amount, 2, '.', '' ),
			'CURRENCYCODE'    => $order->get_order_currency(),
			'COMPLETETYPE'    => 'Complete',
		);
		return apply_filters( 'woocommerce_paypal_capture_request', $request, $order, $amount );
	}

	/**
	 * Get refund request args.
	 * @param  WC_Order $order
	 * @param  float    $amount
	 * @param  string   $reason
	 * @return array
	 */
	public static function get_refund_request( $order, $amount = null, $reason = '' ) {
		$request = array(
			'VERSION'       => '84.0',
			'SIGNATURE'     => self::$api_signature,
			'USER'          => self::$api_username,
			'PWD'           => self::$api_password,
			'METHOD'        => 'RefundTransaction',
			'TRANSACTIONID' => $order->get_transaction_id(),
			'NOTE'          => html_entity_decode( wc_trim_string( $reason, 255 ), ENT_NOQUOTES, 'UTF-8' ),
			'REFUNDTYPE'    => 'Full',
		);
		if ( ! is_null( $amount ) ) {
			$request['AMT']          = number_format( $amount, 2, '.', '' );
			$request['CURRENCYCODE'] = $order->get_order_currency();
			$request['REFUNDTYPE']   = 'Partial';
		}
		return apply_filters( 'woocommerce_paypal_refund_request', $request, $order, $amount, $reason );
	}

	/**
	 * Capture an authorization.
	 * @param  WC_Order $order
	 * @param  float $amount
	 * @return object Either an object of name value pairs for a success, or a WP_ERROR object.
	 */
	public static function do_capture( $order, $amount = null ) {
		$raw_response = wp_safe_remote_post(
			self::$sandbox ? 'https://api-3t.sandbox.paypal.com/nvp' : 'https://api-3t.paypal.com/nvp',
			array(
				'method'      => 'POST',
				'body'        => self::get_capture_request( $order, $amount ),
				'timeout'     => 70,
				'user-agent'  => 'WooCommerce/' . WC()->version,
				'httpversion' => '1.1',
			)
		);

		WC_Gateway_Paypal::log( 'DoCapture Response: ' . print_r( $raw_response, true ) );

		if ( empty( $raw_response['body'] ) ) {
			return new WP_Error( 'paypal-api', 'Empty Response' );
		} elseif ( is_wp_error( $raw_response ) ) {
			return $raw_response;
		}

		parse_str( $raw_response['body'], $response );

		return (object) $response;
	}

	/**
	 * Refund an order via PayPal.
	 * @param  WC_Order $order
	 * @param  float    $amount
	 * @param  string   $reason
	 * @return object Either an object of name value pairs for a success, or a WP_ERROR object.
	 */
	public static function refund_transaction( $order, $amount = null, $reason = '' ) {
		$raw_response = wp_safe_remote_post(
			self::$sandbox ? 'https://api-3t.sandbox.paypal.com/nvp' : 'https://api-3t.paypal.com/nvp',
			array(
				'method'      => 'POST',
				'body'        => self::get_refund_request( $order, $amount, $reason ),
				'timeout'     => 70,
				'user-agent'  => 'WooCommerce/' . WC()->version,
				'httpversion' => '1.1',
			)
		);

		WC_Gateway_Paypal::log( 'Refund Response: ' . print_r( $raw_response, true ) );

		if ( empty( $raw_response['body'] ) ) {
			return new WP_Error( 'paypal-api', 'Empty Response' );
		} elseif ( is_wp_error( $raw_response ) ) {
			return $raw_response;
		}

		parse_str( $raw_response['body'], $response );

		return (object) $response;
	}
}

/**
 * Here for backwards compatibility.
 * @since 2.7.0
 */
class WC_Gateway_Paypal_Refund extends WC_Gateway_Paypal_API_Handler {
	public static function get_request( $order, $amount = null, $reason = '' ) {
		return self::get_refund_request( $order, $amount, $reason );
	}
	public static function refund_order( $order, $amount = null, $reason = '', $sandbox = false ) {
		if ( $sandbox ) {
			self::$sandbox = $sandbox;
		}
		$result = self::refund_transaction( $order, $amount, $reason );
		if ( is_wp_error( $result ) ) {
			return $result;
		} else {
			return (array) $result;
		}
	}
}
