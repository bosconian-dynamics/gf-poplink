<?php
/**
 * Undocumented file
 *
 * @package BosconianDynamics/GFPopLink
 */

namespace BosconianDynamics\GFPopLink;

\GFForms::include_addon_framework();

/**
 * GFPopLinkAddOn
 */
class GFPopLinkAddOn extends \GFAddOn {
  /**
   * Cache for field data decoded from incoming tokens.
   *
   * @var string $_version
   */
  protected $data;

  /**
   * {@inheritDoc}
   *
   * @var string $_version
   */
  protected $_version = '0.1';

  /**
   * {@inheritDoc} (TODO: test for lower version compatibility)
   *
   * @var string
   */
  protected $_min_gravityforms_version = '2.4';

  /**
   * {@inheritDoc}
   *
   * @var string
   */
  protected $_slug = 'poplink';

  /**
   * {@inheritDoc}
   *
   * @var string
   */
  protected $_path = 'gf-poplink/gf-poplink.php';

  /**
   * {@inheritDoc}
   *
   * @var string
   */
  protected $_full_path = __FILE__;

  /**
   * {@inheritDoc}
   *
   * @var string
   */
  protected $_title = 'Gravity Forms Population Link AddOn';

  /**
   * {@inheritDoc}
   *
   * @var string
   */
  protected $_short_title = 'Population Link';

  /**
   * Singleton addon instance.
   *
   * @var GFPopLinkAddOn|null
   */
  private static $_instance = null;

  /**
   * Construct and/or retrieve singleton class instance.
   *
   * @return GFPopLinkAddOn
   */
  public static function get_instance() {
    if( self::$_instance === null )
      self::$_instance = new self();

    return self::$_instance;
  }

  /**
   * Undocumented function
   *
   * @param \WP_Post|number $post
   * @param array|null      $data Data to sign and encode into the JWT.
   * @param integer|null    $expiration Timestamp at which the JWT should expire.
   * @return string
   */
  public function encode_jwt( $data = null, $expiration = null ) {
    $payload['iat'] = time();

    if( $data )
      $payload['dat'] = $data;

    if( isset( $expiration ) )
      $payload['exp'] = $expiration;

    $payload = base64_encode( \wp_json_encode( $payload ) ); //phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
    $hash    = \wp_hash( $payload, 'nonce' );

    return rawurlencode( $payload . '.' . $hash );
  }

  public function decode_jwt( $token ) {
    if( empty( $token ) || -1 === strpos( $token, '.' ) )
      return false;

    $token            = rawurldecode( $token );
    [$payload, $hash] = explode( '.', $token );

    // If the payload or the hash is missing, the token's invalid.
    if( empty( $payload ) || empty( $hash ) )
      return false;

    $hash_check = \wp_hash( $payload, 'nonce' );

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
   * {@inheritDoc}
   *
   * @return void
   */
  public function init() {
    parent::init();

    add_action( 'gform_pre_submission', [ $this, 'populate_fields' ] );
    add_filter( 'gform_field_value', [ $this, 'filter_field_value' ], 10, 2 );
    add_filter( 'gform_field_content', [ $this, 'disable_locked_inputs' ], 10, 2 );
  }

  public function get_form_settings( $form ) {
    if( is_int( $form ) )
      $form = \GFAPI::get_form( $form );

    return $form[ $this->_slug ];
  }

  /**
   * On form submission, overwrite any submitted values with those set by token.
   *
   * @param \GF_Form $form
   * @return void
   */
  public function populate_fields( $form ) {
    $settings = $this->get_form_settings( $form );
    $form_id  = $form['id'];

    if( ! $settings || ! $settings['enabled'] || $settings['enabled'] === '0' )
      return;

    foreach( $form['fields'] as $field ) {
      $token_value = $this->get_token_field_value( $form_id, $field['id'] );

      if( $token_value !== null )
        $_POST[ 'input_' . $field['id'] ] = $token_value;
    }
  }

  public function disable_locked_inputs( $content, $field ) {
    $settings = $this->get_form_settings( $field['formId'] );

    if( ! $settings || ! isset( $settings['enabled'] ) || $settings['enabled'] === '0' )
      return $content;

    if( isset( $settings['lockall'] ) && $settings['lockall'] === '0' )
      return $content;

    return str_replace( '<input ', '<input disabled="disabled" ', $content );
  }

  /**
   * Look up a form field value from the token data cache. Lazily initializes cache
   * data per-form by attempting to decode any tokens indicated by the form's settings.
   *
   * @param integer $form_id
   * @param integer $field_id
   * @return mixed|null
   */
  public function get_token_field_value( $form_id, $field_id ) {
    if( ! isset( $this->data[ $form_id ] ) ) {
      $form     = \GFAPI::get_form( $form_id );
      $settings = $this->get_form_settings( $form_id );

      if( $settings && $settings['enabled'] === '1' ) {
        $token = filter_input(
          \rgpost( $settings['param'] ) ? INPUT_POST : INPUT_GET, // phpcs:ignore WordPress.Security.NonceVerification.Missing
          $settings['param'],
          FILTER_SANITIZE_ENCODED
        );

        if( $settings['strategy'] === 'jwt' )
          $token = $this->decode_jwt( $token );
        else
          throw new \Error( 'Unknown token strategy "' . $settings['strategy'] . '"' );

        $this->data[ $form_id ] = $token['dat'];
      }
      else {
        $this->data[ $form_id ] = false;
      }
    }

    if( $this->data[ $form_id ] === false )
      return null;

    if( ! isset( $this->data[ $form_id ][ 'input_' . $field_id ] ) )
      return null;

    return $this->data[ $form_id ][ 'input_' . $field_id ];
  }

  /**
   * Filter field values, replacing them with values from the token data cache if they exist.
   *
   * @param mixed $value
   * @param \GF_Field $field
   * @return mixed
   */
  public function filter_field_value( $value, $field ) {
    $form_id     = $field['formId'];
    $token_value = $this->get_token_field_value( $form_id, $field['id'] );

    if( $token_value === null )
      return $value;

    return $token_value;
  }

  /**
   * {@inheritDoc}
   *
   * @return array
   */
  public function minimum_requirements() {
    return [
      'wordpress' => [
        'version' => '5.2',
      ],
      'php'       => [
        'version' => '7.2',
      ],
    ];
  }

  /**
   * {@inheritDoc}
   *
   * @param \GF_Form $form
   * @return array
   */
  public function form_settings_fields( $form ) {
    return [
      // Main section - enable/disable, strategy selection.
      [
        'title'  => esc_html__( 'Population Links', 'gf-poplink' ),
        'fields' => [
          [
            'label'   => esc_html__( 'Enable token population', 'gf-poplink' ),
            'type'    => 'checkbox',
            'tooltip' => esc_html__( 'Allow this form to be populated from tokens passed in through the page request.', 'gf-poplink' ),
            'name'    => 'enabled',
            'choices' => [
              [
                'label' => esc_html__( 'Enabled', 'gf-poplink' ),
                'name'  => 'enabled',
              ],
            ],
          ],
          [
            'label'   => esc_html__( 'Token strategy', 'gf-poplink' ),
            'type'    => 'select',
            'tooltip' => esc_html__( 'Which type of token to use.', 'gf-poplink' ),
            'name'    => 'strategy',
            'choices' => [
              [
                'label' => esc_html__( 'JSON Web Token', 'gf-poplink' ),
                'value' => 'jwt',
              ],
              /*[
                'label' => esc_html__( 'Encrypted Data Token', 'gf-poplink' ),
                'value' => 'encrypted',
              ],
              [
                'label' => esc_html__( 'Database Token', 'gf-poplink' ),
                'value' => 'database',
              ],*/
            ],
          ],
          [
            'label'         => esc_html__( 'Querystring parameter', 'gf-poplink' ),
            'type'          => 'text',
            'tooltip'       => esc_html__( 'The key used in the querystring to identify a population token', 'gf-poplink' ),
            'name'          => 'param',
            'after_input'   => sprintf(
              __( '<br />Avoid the identifiers listed <a href="%s">here</a> to prevent unexpected behaviors', 'gf-poplink' ),
              'https://codex.wordpress.org/Reserved_Terms'
            ),
            'default_value' => 'poptok',
          ],
          [
            'label'   => esc_html__( 'Disable populated fields', 'gf-poplink' ),
            'type'    => 'checkbox',
            'tooltip' => esc_html__( 'Disable all inputs populated from a token and lock their values', 'gf-poplink' ),
            'name'    => 'lockall',
            'choices' => [
              [
                'label'         => esc_html__( 'Enabled', 'gf-poplink' ),
                'name'          => 'lockall',
                'default_value' => '1',
              ],
            ],
          ],
        ],
      ],
    ];
  }
}
