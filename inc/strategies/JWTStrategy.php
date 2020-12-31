<?php
namespace BosconianDynamics\GFPopLink\strategies;

use BosconianDynamics\GFPopLink\IPopulationStrategy;

/**
 * Undocumented class
 */
class JWTStrategy implements IPopulationStrategy {
	/**
	 * {@inheritDoc}
	 *
	 * @param GF_Form|array $form The form object for which to construct the strategy.
	 * @param array         $settings The GF Poplink AddOn settings for this form.
	 */
	public function __construct( $form, $settings ) {

	}

	/**
	 * Decodes data from a token string consisting of the last two parts of a JWT token.
	 *
	 * @param string $token JWT token string.
	 * @return boolean|array
	 */
	public function deserialize( $token ) {
		if( empty( $token ) || -1 === strpos( $token, '.' ) )
			return false;

		$token            = rawurldecode( $token );
		[$payload, $hash] = explode( '.', $token );

		// If the payload or the hash is missing, the token's invalid.
		if( empty( $payload ) || empty( $hash ) )
			return false;

		$hash_check = \wp_hash( $payload, 'nonce' ); // TODO: plugin/config-level salt override.

		// Has the payload and/or hash been modified since the token was issued?
		if( $hash_check !== $hash )
			return false;

		$payload = base64_decode( $payload ); //phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode

		if( ! $payload )
			return false;

		$payload = json_decode( $payload, true );

		if( ! $payload )
			return false;

		// Does the payload have an expiration date - if so, has it expired?
		if( ! empty( $payload['exp'] ) && $payload['exp'] > time() )
			return false;

		// Token validated - return the payload as legitimate data.
		return $payload;
	}

	/**
	 * Encode data into a token consisting of the last two parts of a JWT.
	 *
	 * @param array $data The data to encode.
	 * @return string
	 */
	public function serialize( $data ) {
		$payload['iat'] = time();

		if( $data )
			$payload['dat'] = $data;

		if( isset( $expiration ) )
			$payload['exp'] = $expiration;

		$payload = base64_encode( \wp_json_encode( $payload ) ); //phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
		$hash    = \wp_hash( $payload, 'nonce' );

		return rawurlencode( $payload . '.' . $hash );
	}
}
