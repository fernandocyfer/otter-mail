<?php
/**
 * Lightweight assertions for Tokens (run: wp eval-file tests/test-tokens.php).
 *
 * @package OtterMail
 */

if ( ! defined( 'ABSPATH' ) ) {
	fwrite( STDERR, "Load WordPress first.\n" );
	return;
}

use OtterMail\Support\Tokens;

$token = Tokens::generate();
$hash  = Tokens::hash( $token );
if ( $hash !== hash( 'sha256', $token ) ) {
	fwrite( STDERR, "hash mismatch\n" );
	exit( 1 );
}

$cart_token = Tokens::cart_token( 42 );
if ( 42 !== Tokens::cart_id_from_token( $cart_token ) ) {
	fwrite( STDERR, "cart token failed\n" );
	exit( 1 );
}
if ( 0 !== Tokens::cart_id_from_token( '42' ) ) {
	fwrite( STDERR, "bare id must be rejected\n" );
	exit( 1 );
}

$email = 'qa@example.com';
$hmac  = Tokens::unsubscribe_hmac( $email, 'marketing' );
if ( ! Tokens::unsubscribe_valid( $email, $hmac, 'marketing' ) ) {
	fwrite( STDERR, "unsubscribe hmac failed\n" );
	exit( 1 );
}

echo "Tokens OK\n";
