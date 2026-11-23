<?php
/**
 * Velox AI — the connection to Claude (Anthropic's Messages API).
 *
 * Plain HTTP through WordPress (wp_remote_*): works on every host Velox runs
 * on, needs no Composer libraries and can't clash with other plugins that
 * ship their own HTTP stack. Other providers get a class of their own next to
 * this one; the assistant only talks to them through messages() and test().
 *
 * @package Velox
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Velox_AI_Claude {

	const API     = 'https://api.anthropic.com/v1/';
	const VERSION = '2026-10-21';
	// A declined request is retried on the model Anthropic recommends for it.
	const BETAS = 'server-side-fallback-2026-07-01';

	/**
	 * The models a site can pick, with prices in US dollars per million tokens
	 * (input, output, cache write, cache read) for the usage meter.
	 */
	public static function models() {
		return array(
			'claude-opus-5-5'   => array( 'label' => 'Claude Opus 5.5', 'note' => __( 'Best results. Recommended.', 'velox' ), 'price' => array( 4, 20, 5, 0.2 ) ),
			'claude-sonnet-5-5' => array( 'label' => 'Claude Sonnet 5.5', 'note' => __( 'Faster and about half the price.', 'velox' ), 'price' => array( 2, 10, 2.5, 0.1 ) ),
		);
	}

	public static function model( $id ) {
		$all = self::models();
		return isset( $all[ $id ] ) ? $id : 'claude-opus-5-5';
	}

	/** Where a site owner creates a key. */
	public static function keys_url() {
		return 'https://platform.claude.com/settings/keys';
	}

	private static function headers( $key ) {
		return array(
			'x-api-key'         => $key,
			'anthropic-version' => self::VERSION,
			'content-type'      => 'application/json',
		);
	}

	/**
	 * Is this key good? Reads the model's entry, which costs nothing.
	 *
	 * @return true|WP_Error
	 */
	public static function test( $key, $model = 'claude-opus-5-5' ) {
		$r = wp_remote_get( self::API . 'models/' . rawurlencode( self::model( $model ) ), array( 'headers' => self::headers( $key ), 'timeout' => 20 ) );
		if ( is_wp_error( $r ) ) {
			return new WP_Error( 'velox_ai_net', __( 'Your server could not reach Claude. Your host may block outgoing connections.', 'velox' ) . ' (' . $r->get_error_message() . ')' );
		}
		$code = (int) wp_remote_retrieve_response_code( $r );
		return 200 === $code ? true : self::error( $code, wp_remote_retrieve_body( $r ) );
	}

	/**
	 * One request to the Messages API. $body is the request without model
	 * defaults; the response comes back as an array, unchanged.
	 *
	 * @return array|WP_Error
	 */
	public static function messages( $key, $body, $timeout = 300 ) {
		$r = wp_remote_post( self::API . 'messages', array(
			'headers' => self::headers( $key ) + array( 'anthropic-beta' => self::BETAS ),
			'body'    => wp_json_encode( $body ),
			'timeout' => $timeout,
		) );
		if ( is_wp_error( $r ) ) {
			return new WP_Error( 'velox_ai_net', __( 'No answer from Claude in time. Please try again.', 'velox' ) . ' (' . $r->get_error_message() . ')' );
		}
		$code = (int) wp_remote_retrieve_response_code( $r );
		$raw  = wp_remote_retrieve_body( $r );
		if ( 200 !== $code ) {
			return self::error( $code, $raw );
		}
		$data = json_decode( $raw, true );
		if ( ! is_array( $data ) || ! isset( $data['content'] ) || ! is_array( $data['content'] ) ) {
			return new WP_Error( 'velox_ai_bad', __( 'Claude sent an answer Velox could not read. Please try again.', 'velox' ) );
		}
		return $data;
	}

	/** A message a person can act on, with Anthropic's own text added. */
	private static function error( $code, $raw ) {
		$j   = json_decode( (string) $raw, true );
		$api = is_array( $j ) && isset( $j['error']['message'] ) ? (string) $j['error']['message'] : '';
		switch ( $code ) {
			case 401:
				$msg = __( 'The API key was not accepted. Check it, or create a new one.', 'velox' );
				break;
			case 403:
				$msg = __( 'This API key may not use Claude. Check its permissions in the Claude Console.', 'velox' );
				break;
			case 404:
				$msg = __( 'This model is not available for your account.', 'velox' );
				break;
			case 413:
				$msg = __( 'The conversation is too long. Start a new chat.', 'velox' );
				break;
			case 429:
				$msg = __( 'Too many requests for your Anthropic account right now. Wait a minute and try again.', 'velox' );
				break;
			case 500:
			case 502:
			case 503:
			case 529:
				$msg = __( 'Claude is busy right now. Try again in a moment.', 'velox' );
				break;
			default:
				$msg = __( 'Claude could not handle this request.', 'velox' );
		}
		if ( false !== stripos( $api, 'credit balance' ) ) {
			$msg = __( 'Your Anthropic account has no credit left. Add credit in the Claude Console.', 'velox' );
		}
		return new WP_Error( 'velox_ai_' . $code, $msg . ( '' !== $api ? ' (' . $api . ')' : '' ), array( 'status' => $code ) );
	}

	/** What a response cost, in US dollars (an estimate from the published prices). */
	public static function cost( $model, $usage ) {
		$m = self::models()[ self::model( $model ) ]['price'];
		$u = is_array( $usage ) ? $usage : array();
		return ( (int) ( $u['input_tokens'] ?? 0 ) * $m[0]
			+ (int) ( $u['output_tokens'] ?? 0 ) * $m[1]
			+ (int) ( $u['cache_creation_input_tokens'] ?? 0 ) * $m[2]
			+ (int) ( $u['cache_read_input_tokens'] ?? 0 ) * $m[3] ) / 1000000;
	}
}
