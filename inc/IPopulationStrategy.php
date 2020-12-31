<?php
namespace BosconianDynamics\GFPopLink;

interface IPopulationStrategy {
	/**
	 * Undocumented function
	 *
	 * @param GF_Form|array $form The form object for which to construct the strategy.
	 * @param array         $settings The GF Poplink AddOn settings for this form.
	 */
	public function __construct( $form, $settings );

	/**
	 * Decodes a token into form data.
	 *
	 * @param string $token The token to decode.
	 * @return array|boolean
	 */
	public function deserialize( $token );

	/**
	 * Encodes form data into a token string.
	 *
	 * @param array $data Form data.
	 * @return string
	 */
	public function serialize( $data );
}
