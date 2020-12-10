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

  function __construct() {
    // TODO: this is silly. Expose a constant.
    $this->_full_path = dirname( __DIR__ ) . '/gf-poplink.php';
    $this->_path      = basename( dirname( $this->_full_path ) ) . '/' . basename( $this->_full_path );
    
    parent::__construct();
  }

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

    // TODO: redistribute some of these hooks to other initialization methods, e.g. admin_init()

    add_action( 'gform_pre_submission', [ $this, 'populate_fields' ] );
    add_action( 'gform_field_advanced_settings', [ $this, 'field_settings' ], 10, 2 );
    add_action( 'gform_editor_js', [ $this, 'register_field_settings_js' ] );

    add_filter( 'gform_pre_form_settings_save', [ $this, 'save_form_settings_fields' ] );
    // TODO: add_filter( 'gform_tooltips', [ $this, 'register_tooltips' ] ); 
    add_filter( 'gform_field_value', [ $this, 'filter_field_value' ], 10, 2 );
    add_filter( 'gform_field_content', [ $this, 'disable_locked_inputs' ], 10, 2 );
    add_filter( 'gform_form_actions', [ $this, 'form_action_links' ], 10, 2 );
    add_filter( 'gform_toolbar_menu', [ $this, 'form_action_links' ], 10, 2 );

    add_action( 'wp_ajax_gf_poplink_encode_token', [ $this, 'encode_form_data_token' ] );
  }

  public function form_action_links( $links, $form_id ) {
    if( !$this->is_form_poplink_enabled( $form_id ) )
      return $links;
      
    $links['poplink_prepop'] = [
      'label'        => __( 'Prepopulate', 'gf-poplink' ),
      'aria-label'   => __( 'Pre-populate fields and generate a population link', 'gf-poplink' ),
      'url'          => '#',
      'icon'         => '<i class="fa fa-file-text-o fa-lg"></i>',
      'capabilities' => 'gravityforms_edit_forms',
      'menu_class'   => 'gf_poplink_prepop',
      'priority'     => 650,
    ];

    return $links;
  }

  public function encode_form_data_token() {
    // TODO: nonce check
    $form_id  = \rgpost( 'form_id' );
    $form     = \GFAPI::get_form( $form_id );
    $settings = $this->get_form_settings( $form );
    $data     = [
      'form_id' => $form_id,
      'fields'  => [],
    ];

    if( !$this->is_form_poplink_enabled( $form ) ) {
      wp_send_json_error([
        'message' => __( 'Population Links are disabled for this form.', 'gf-poplink' ),
      ]);
    }
    
    foreach( $form['fields'] as $field ) {
      if( $field['poplink_enable'] === false )
        continue;
      
      $input_id  = 'input_' . $field['id'];

      // TODO: improve sanitization
      $value = \rgpost( $input_id );

      if( $value !== null && $value !== '' )
        $data['fields'][ $input_id ] = $value;
    }

    switch( $settings['strategy'] ) {
      case 'jwt':
        $token = $this->encode_jwt( $data ); // TODO: expiration form setting
        break;
      default:
        throw new \Error( 'Unknown token strategy "' . $settings['strategy'] . '"' );
    }

    wp_send_json_success([
      'token' => $token
    ]);
  }

  public function get_form_settings( $form ) {
    if( is_int( $form ) || is_string( $form ) )
      $form = \GFAPI::get_form( $form );

    return parent::get_form_settings( $form );
  }

  /**
   * On form submission, overwrite any submitted values with those set by token.
   *
   * @param \GF_Form $form
   * @return void
   */
  public function populate_fields( $form ) {
    $form_id  = $form['id'];

    if( !$this->is_form_poplink_enabled( $form ) )
      return;

    foreach( $form['fields'] as $field ) {
      if( $field['poplink_enable'] === false )
        continue;
      
      $input_id  = 'input_' . $field['id'];
      $input_val = \rgpost( $input_id );
      
      if( !$this->is_field_locked( $field ) && !empty( $input_val ) )
        continue;

      $token_value = $this->get_token_field_value( $form_id, $field['id'] );

      if( $token_value !== null )
        $_POST[ $input_id ] = $token_value;
    }
  }

  public function scripts() {
    $form_admin = include $this->get_base_path() . '/build/form-admin.asset.php';
    $frontend   = include $this->get_base_path() . '/build/frontend.asset.php';

    return array_merge(
      parent::scripts(),
      [
        [
          'handle'  => 'gf-poplink_form-admin',
          'src'     => $this->get_base_url() . '/build/form-admin.js',
          'version' => $form_admin['version'],
          'deps'    => $form_admin['dependencies'],
          'enqueue' => [
            'admin_page' => 'form_editor',
          ],
        ],
        [
          'handle'  => 'gf-poplink_frontend',
          'src'     => $this->get_base_url() . '/build/frontend.js',
          'version' => $frontend['version'],
          'deps'    => $frontend['dependencies'],
          // TODO: possibly a callback to check poplink settings for the frontend form
          /*'enqueue' => [
            'admin_page' => 'form_editor'
          ]*/
        ],
      ]
    );
  }

  public function disable_locked_inputs( $content, $field ) {
    if( !$this->is_form_poplink_enabled( $field['formId'] ) )
      return $content;

    if( $this->is_field_locked( $field ) )
      return str_replace( '<input ', '<input disabled="disabled" ', $content );
    
    return $content;
  }

  public function is_form_poplink_enabled( $form ) {
    $settings = $this->get_form_settings( $form );

    return $settings && isset( $settings['enabled'] ) && $settings['enabled'] === '1';
  }

  public function is_field_lockable( $field ) {
    if( !$this->is_form_poplink_enabled( $field['formId'] ) )
      return false;

    $settings = $this->get_form_settings( $field['formId'] );
    
    return $field['poplink_prepop_lock'] || isset( $settings['lockall'] ) && $settings['lockall'] === '1';
  }

  public function is_field_locked( $field ) {
    return $this->is_field_lockable( $field ) && $this->is_field_prepopulated( $field );
  }

  public function is_field_prepopulated( $field ) {
    return $this->get_token_field_value( $field['formId'], $field['id'] ) !== null;
  }

  public function load_template( $name, $args = [], $return = false, $require_once = false ) {
    // TODO: theme override support

    if( $return )
      ob_start();
    
    \load_template(
      $this->get_base_path() . '/inc/templates/' . $name,
      $require_once,
      $args
    );

    if( $return )
      return ob_get_clean();
  }

  public function field_settings( $position, $form_id ) {
    if( $position !== -1 )
      return;
    
    $this->load_template( 'field-settings.php' );
  }

  public function register_field_settings_js() {
    // TODO: consider abstracting general field attributes into a JSON file for use in PHP and JS.
    ?>
      <script type="text/javascript">
        var poplinkSettingsClasses = ', .' + [
          'poplink_field_settings',
          'poplink_prepop_lock_field_setting',
          //'poplink_encrypt_field_setting', // TODO
          //'poplink_hide_field_setting'     // TODO
        ].join(', .');

        // TODO: refine this list. This addon probably isn't compatible with every field type by default.
        for( var fieldType of Object.keys( window.fieldSettings ) ) {
          window.fieldSettings[ fieldType ] += poplinkSettingsClasses;
        }
      </script>
    <?php
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
      if( $this->is_form_poplink_enabled( $form_id ) ) {
        $settings = $this->get_form_settings( $form_id );
        $token    = filter_input(
          \rgpost( $settings['param'] ) ? INPUT_POST : INPUT_GET, // phpcs:ignore WordPress.Security.NonceVerification.Missing
          $settings['param'],
          FILTER_SANITIZE_ENCODED
        );

        if( $token ) {
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

  public function save_form_settings_fields( $form ) {
    return $form;
  }
}
