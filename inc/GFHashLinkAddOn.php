<?php
/**
 * Undocumented file
 *
 * @package BosconianDynamics/GFHashLink
 */

namespace BosconianDynamics\GFHashLink;

\GFForms::include_addon_framework();

/**
 * GFHashLinkAddOn
 */
class GFHashLinkAddOn extends \GFAddOn {
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
  protected $_slug = 'hashlink';

  /**
   * {@inheritDoc}
   *
   * @var string
   */
  protected $_path = 'gf-hashlink/gf-hashlink.php';

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
  protected $_title = 'Gravity Forms HashLink AddOn';

  /**
   * {@inheritDoc}
   *
   * @var string
   */
  protected $_short_title = 'Hash Links';

  /**
   * Singleton addon instance.
   *
   * @var GFHashLinkAddOn|null
   */
  private static $_instance = null;

  /**
   * Construct and/or retrieve singleton class instance.
   *
   * @return GFHashLinkAddOn
   */
  public static function get_instance() {
    if( self::$_instance === null )
      self::$_instance = new self();

    return self::$_instance;
  }

  /**
   * Undocumented function
   *
   * @param [type] $post
   * @param [type] $data
   * @param [type] $exp
   * @return void
   */
  public function encode_jwt( $post, $data, $exp = null ) {
    $payload['iat'] = time();
    $payload['dat'] = $data;

    if( isset( $exp ) )
      $payload['exp'] = $exp;

    $payload = base64_encode( \wp_json_encode( $payload ) ); //phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
    $hash    = \wp_hash( $payload, 'nonce' );

    return rawurlencode( $payload . '.' . $hash );
  }

  public function decode_jwt( $token ) {
    if( empty( $token ) || -1 === strpos( $token, '.' ) )
      return false;

    $token            = rawurldecode( $token );
    [$hash, $payload] = explode( '.', $token );

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

    add_action( 'gform_pre_submission', [ __CLASS__, 'populate_fields' ] );
  }

  public function populate_fields( $form ) {
    
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

  public function form_settings_fields( $form ) {
    return [
      // Main section - enable/disable, strategy selection.
      [
        'title'  => esc_html__( 'HashLink Settings', 'gf-hashlink' ),
        'fields' => [
          [
            'label'   => esc_html__( 'Enable token population', 'gf-hashlink' ),
            'type'    => 'checkbox',
            'tooltip' => esc_html__( 'Allow this form to be populated from tokens passed in through the page request.', 'gf-hashlink' ),
            'name'    => 'enabled',
            'choices' => [
              [
                'label' => esc_html__( 'Enabled', 'gf-hashlink' ),
                'name'  => 'enabled',
              ],
            ],
          ],
          [
            'label'   => esc_html__( 'Token strategy', 'gf-hashlink' ),
            'type'    => 'select',
            'tooltip' => esc_html__( 'Which type of token to use.', 'gf-hashlink' ),
            'name'    => 'strategy',
            'choices' => [
              [
                'label' => esc_html__( 'JSON Web Token', 'gf-hashlink' ),
                'value' => 'jwt',
              ],
              [
                'label' => esc_html__( 'Encrypted Data Token', 'gf-hashlink' ),
                'value' => 'encrypted',
              ],
              [
                'label' => esc_html__( 'Database Token', 'gf-hashlink' ),
                'value' => 'database',
              ],
            ],
          ],
          [
            'label'         => esc_html__( 'Querystring parameter', 'gf-hashlink' ),
            'type'          => 'text',
            'tooltip'       => esc_html__( 'The key used in the querystring to identify a population token', 'gf-hashlink' ),
            'name'          => 'param',
            'after_input'   => sprintf(
              __( '<br />Avoid the identifiers listed <a href="%s">here</a> to prevent unexpected behaviors', 'gf-hashlink' ),
              'https://codex.wordpress.org/Reserved_Terms'
            ),
            'default_value' => 'poptok',
          ],
        ],
      ],
    ];
  }
}
