<?php
/**
 * Undocumented file
 *
 * @package BosconianDynamics/GFPopLink
 */

namespace BosconianDynamics\GFPopLink;

use \BosconianDynamics\GFPopLink\IPopulationStrategy;
use \BosconianDynamics\GFPopLink\PrepopForm;
use \BosconianDynamics\GFPopLink\strategies\JWTStrategy;

\GFForms::include_addon_framework();

/**
 * GFPopLinkAddOn
 */
class GFPopLinkAddOn extends \GFAddOn {
	const CAPABILITIES = [
		'gravityforms_edit_forms',
		'gravityforms_create_form',
	];
	const FORM_PARAM   = 'gf_prepop';

	/**
	 * Cache for field data decoded from incoming tokens.
	 *
	 * @var array $data
	 */
	protected $data = [];

	/**
	 * Mapping of form IDs to IPopulationStrategy objects.
	 *
	 * @var array $strategies
	 */
	protected $strategies = [];

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

	/**
	 * Undocumented function
	 */
	public function __construct() {
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
	 * {@inheritDoc}
	 *
	 * @return void
	 */
	public function init() {
		parent::init();

		// TODO: redistribute some of these hooks to other initialization methods, e.g. admin_init().

		add_action( 'gform_pre_submission', [ $this, 'populate_fields' ] );
		add_action( 'gform_field_advanced_settings', [ $this, 'field_settings' ] );
		add_action( 'gform_editor_js', [ $this, 'register_field_settings_js' ] ); // TODO: would be nice to find a way to handle this with a static asset.
		add_action( 'gform_enqueue_scripts', [ $this, 'enqueue_form_scripts' ] );
		add_action( 'wp_ajax_poplink_serialize_formdata', [ $this, 'ajax_serialize_formdata' ] );

		// add_filter( 'gform_pre_form_settings_save', [ $this, 'save_form_settings_fields' ] );
		// TODO: add_filter( 'gform_tooltips', [ $this, 'register_tooltips' ] );.
		add_filter( 'gform_field_value', [ $this, 'filter_field_value' ], 100, 2 );
		add_filter( 'gform_field_content', [ $this, 'disable_prepopulated_inputs' ], 10, 2 );
		add_filter( 'gform_form_actions', [ $this, 'form_action_links' ], 10, 2 );
		add_filter( 'gform_toolbar_menu', [ $this, 'form_action_links' ], 10, 2 );
		add_filter( 'gform_submit_button', [ $this, 'form_footer_buttons' ], 10, 2 );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @return void
	 */
	public function init_admin() {
		parent::init_admin();

		// Handle routing for the dedicated prepopulation form.
		if( $this->is_prepop() ) {
			if( ! \is_user_logged_in() )
			\auth_redirect();

			if( ! \GFAPI::current_user_can_any( self::CAPABILITIES ) )
			\wp_die( esc_html__( 'You don\'t have adequate permission to prepopulate forms.', 'gf-poplink' ) );

			$form = \GFAPI::get_form( \rgget( self::FORM_PARAM ) );

			if( ! $this->is_form_poplink_enabled( $form ) )
			\wp_die( esc_html__( 'Population tokens are not enabled for this form.', 'gf-poplink' ) );

			$settings = $this->get_form_settings( $form );

			$prepop = new PrepopForm(
				$form,
				$settings,
				$this->get_strategy( $form ),
				$this
			);

			$prepop->load();
		}
	}

	/**
	 * Asynchronous request handler to serialize a form's inputs into a token.
	 *
	 * @return void
	 */
	public function ajax_serialize_formdata() {
		if( \rgempty( 'form_id' ) )
			\wp_send_json_error( null, 400 );

		if( ! \GFAPI::current_user_can_any( self::CAPABILITIES ) ) {
			\wp_send_json_error(
				[
					'message' => __( 'You don\'t have adequate permission to prepopulate forms.', 'gf-poplink' ),
				],
				403
			);
		}

		$form_id = \rgpost( 'form_id' );
		$form    = \GFAPI::get_form( $form_id );

		if( ! $this->is_form_poplink_enabled( $form ) ) {
			\wp_send_json_error(
				[
					'message' => __( 'This form does not have Population Links enabled', 'gf-poplink' ),
				]
			);
		}

		$token = $this->get_strategy( $form )->serialize(
			[
				'form_id' => $form_id,
				'fields'  => $this->get_form_data( $form, false ),
			]
		);

		// TODO: add details about any fields thrown out of the token due to not permitting prepopulation.
		\wp_send_json_success(
			[
				'message' => __( 'Population token encoded successfully!', 'gf-poplink' ),
				'token'   => $token,
				'param'   => $this->get_form_settings( $form )['param'],
			]
		);
	}

	/**
	 * Filters the Gravity Forms Submit button in order to add a prepopulation button if the form and
	 * current user's capabilities qualify.
	 *
	 * @param string   $submit_button HTML string representing the submit button for the form.
	 * @param \GF_Form $form GF_Form instance or ID.
	 * @return string
	 */
	public function form_footer_buttons( $submit_button, $form ) {
		if( ! \GFAPI::current_user_can_any( self::CAPABILITIES ) || ! $this->is_form_poplink_enabled( $form ) )
		return $submit_button;

		$prepop_button = '<input type="button" id="poplink_poptoken_button" class="gform_button button" value="' . __( 'Generate Population Link', 'gf-poplink' ) . '">';

		return $submit_button . $prepop_button;
	}

	/**
	 * Retrieves the population strategy instance configured for a form.
	 *
	 * @param \GF_Form|integer|string $form The form for which to retrieve a strategy.
	 * @return IPopulationStrategy|null The respective strategy instance.
	 * @throws \Error Throws on invalid strategy IDs set in a form's settings.
	 */
	public function get_strategy( $form ) {
		if( is_numeric( $form ) )
		$form = \GFAPI::get_form( $form );

		$form_id = $form['id'];

		if( ! isset( $this->strategies[ $form_id ] ) ) {
			$settings = $this->get_form_settings( $form );

			switch( $settings['strategy'] ) {
				case 'jwt':
					$this->strategies[ $form_id ] = new JWTStrategy( $form, $settings );
					break;

				default:
					throw new \Error( 'Unkown population strategy "' . $settings['strategy'] . '"' );
			}
		}

		return $this->strategies[ $form_id ];
	}

	/**
	 * Checks if the current request is for the dedicated prepopulation form.
	 *
	 * @return boolean
	 */
	public function is_prepop() {
		return \is_admin() && ! \rgempty( self::FORM_PARAM, $_GET ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	}

	/**
	 * Filter to add links to the dedicated prepopulation form to action lists in Gravity Forms
	 * screens.
	 *
	 * @param array          $links The original array of action link objects.
	 * @param integer|string $form_id The ID of the current form.
	 * @return array The modified action links array.
	 */
	public function form_action_links( $links, $form_id ) {
		if( ! $this->is_form_poplink_enabled( $form_id ) )
		return $links;

		$links['poplink_prepop'] = [
			'label'        => __( 'Prepopulate', 'gf-poplink' ),
			'aria-label'   => __( 'Pre-populate fields and generate a population link', 'gf-poplink' ),
			'url'          => '?' . self::FORM_PARAM . '=' . $form_id,
			'target'       => '_blank',
			'icon'         => '<i class="fa fa-file-text-o fa-lg"></i>',
			'capabilities' => self::CAPABILITIES,
			'menu_class'   => 'gf_poplink_prepop',
			'priority'     => 650,
		];

		return $links;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param \GF_Form|integer|string $form The form for which to retrieve AddOn settings.
	 * @return array
	 */
	public function get_form_settings( $form ) {
		if( is_numeric( $form ) )
		$form = \GFAPI::get_form( $form );

		return parent::get_form_settings( $form );
	}

	/**
	 * On form submission, overwrite any submitted values for locked fields with those set by token.
	 *
	 * @param \GF_Form $form The form currently being submitted.
	 * @return void
	 */
	public function populate_fields( $form ) {
		$_POST = array_merge(
			$_POST, // phpcs:ignore WordPress.Security.NonceVerification.Missing
			$this->get_form_data( $form, true )
		);
	}

	/**
	 * Get form input values, optionally with token prepopulation.
	 *
	 * @param \GF_Form|integer|string $form The form for which to retrieve form submission data.
	 * @param boolean                 $prepopulate Whether or not to populate submission data with pre-populated data.
	 * @param string                  $compound_delimiter Delimiter used in keys for compound fields in the request data.
	 * @return array
	 */
	public function get_form_data( $form, $prepopulate = false, $compound_delimiter = '_' ) {
		if( is_numeric( $form ) )
		$form = \GFAPI::get_form( $form );

		$data    = [];
		$form_id = $form['id'];

		if( ! $this->is_form_poplink_enabled( $form ) )
		return $data;

		foreach( $form['fields'] as $field ) {
			if( $field['allowsPrepopulate'] != 1 )
			continue;

			$input_name = 'input_' . $field['id'];

			if( $prepopulate ) {
				$data[ $input_name ] = $this->get_token_field_value( $form_id, $field['id'] );

				if( $this->is_field_locked( $field ) )
				continue;
			}

			// TODO: there are other compound field types that need to be handled. There's gotta be
			// an API function for this nonsense!
			if( $field['type'] === 'checkbox' ) {
				$choice_number       = 0;
				$data[ $input_name ] = [];
				$choice_count        = count( $field['choices'] );

				for( $i = 0; $i < $choice_count; $i++ ) {
					if( $choice_number % 10 === 0 )
					$choice_number++;

					$choice_name           = $input_name . $compound_delimiter . ( $choice_number++ );
					$data[ $input_name ][] = \rgpost( $choice_name );
				}

				continue;
			}

			if( ! \rgempty( $input_name ) )
			$data[ $input_name ] = \rgpost( $input_name );
		}

		return $data;
	}

	/**
	 * Conditionally enqueue scripts for specific forms. Right now, simply responsible for enqueuing
	 * the script to handle prepopulation functionality.
	 *
	 * TODO: frontend script - if needed - should be moved here, since Gravity Forms enqueue
	 *   conditionals don't have an option to specify specific forms.
	 *
	 * @param \GF_Form $form The form for which to enqueue scripts.
	 * @return void
	 */
	public function enqueue_form_scripts( $form ) {
		if( $this->is_form_poplink_enabled( $form ) && \GFAPI::current_user_can_any( self::CAPABILITIES ) ) {
			if( $this->is_prepop() )
			return;

			$asset_info    = include $this->get_base_path() . '/build/prepop.asset.php';
			$script_handle = 'gf-poplink_prepop';

			\wp_enqueue_script(
				$script_handle,
				$this->get_base_url() . '/build/prepop.js',
				array_merge(
					$asset_info['dependencies'],
					[ 'jquery-ui-core', 'jquery-ui-dialog' ] // TODO: migrate these to webpack externs or @wordpress/dependency-extraction-webpack-plugin... or just implement a modal without jquery.
				),
				$asset_info['version'],
				true
			);
			\wp_localize_script(
				$script_handle,
				'poplink_prepop',
				[
					'ajax_url' => \admin_url( 'admin-ajax.php' ),
					'nonce'    => \wp_create_nonce( 'poplink_prepop' ),
					'strings'  => [
						'copy_button' => __( 'Copy Link to Clipboard', 'gf-poplink' ),
					],
				]
			);

			\wp_enqueue_style( 'wp-jquery-ui-dialog' );
		}
	}

	/**
	 * {@inheritDoc}
	 *
	 * @return array An array of script configuration arrays for this addon's assets.
	 */
	public function scripts() {
		$form_admin = include $this->get_base_path() . '/build/form-admin.asset.php';
		// $frontend   = include $this->get_base_path() . '/build/frontend.asset.php';

		return array_merge(
			parent::scripts(),
			[
				[
					'handle'  => 'gf-poplink_form-admin',
					'src'     => $this->get_base_url() . '/build/form-admin.js',
					'version' => $form_admin['version'],
					'deps'    => $form_admin['dependencies'],
					'enqueue' => [
						[ 'admin_page' => 'form_editor' ],
					],
				],
				/**
				  [
					'handle'  => 'gf-poplink_frontend',
					'src'     => $this->get_base_url() . '/build/frontend.js',
					'version' => $frontend['version'],
					'deps'    => $frontend['dependencies']
					]
				*/
			]
		);
	}

	/**
	 * Filters form input elements to disable them if they've been prepopulated by a token and are
	 * configured to do so.
	 *
	 * @param string $content HTML string representing a field.
	 * @param array  $field Gravity Forms Field data.
	 * @return string
	 */
	public function disable_prepopulated_inputs( $content, $field ) {
		if( $this->is_form_editor() )
			return $content;

		if( ! $this->is_form_poplink_enabled( $field['formId'] ) )
			return $content;

		if( $this->is_field_locked( $field ) ) {
			$content = str_replace(
				[
					'<input ',
					'<textarea ',
					'<select ',
				],
				[
					'<input disabled="disabled" ',
					'<textarea disabled="disabled" ',
					'<select disabled="disabled" ',
				],
				$content
			);
		}

		return $content;
	}

	/**
	 * Checks if this addon's functionality is enabled for the referenced form.
	 *
	 * @param \GF_Form|integer|string $form The form which to check if Poplinks is enabled for.
	 * @return boolean
	 */
	public function is_form_poplink_enabled( $form ) {
		$settings = $this->get_form_settings( $form );

		return $settings && isset( $settings['enabled'] ) && $settings['enabled'] === '1';
	}

	/**
	 * Checks if a field is configured to lock/disable when it's been pre-populated by a token.
	 *
	 * @param array $field Gravity Forms Field data.
	 * @return boolean
	 */
	public function is_field_lockable( $field ) {
		if( ! $this->is_form_poplink_enabled( $field['formId'] ) )
			return false;

		$settings = $this->get_form_settings( $field['formId'] );

		return $field['poplink_prepop_lock'] || isset( $settings['lockall'] ) && $settings['lockall'] === '1';
	}

	/**
	 * Checks if a field is both configured to lock/disable and is locked/disabled by being
	 * prepopulated by a token.
	 *
	 * @param array $field Gravity Forms Field data.
	 * @return boolean
	 */
	public function is_field_locked( $field ) {
		return $this->is_field_lockable( $field ) && $this->is_field_prepopulated( $field );
	}

	/**
	 * Checks if a field has a prepopulated value in the token data cache
	 *
	 * @param array $field Gravity Forms Field data.
	 * @return boolean
	 */
	public function is_field_prepopulated( $field ) {
		return $this->get_token_field_value( $field['formId'], $field['id'] ) !== null;
	}

	/**
	 * Loads the specified template file from the templates/ directory.
	 *
	 * @param string  $name The path to the file relative to the templates/ directory.
	 * @param array   $args Additional data to pass into the template in the $args variable.
	 * @param boolean $return Whether to return the interpreted template as a string, or to print it.
	 * @param boolean $permit_override Attempt to load overriding an template from the active theme.
	 * @param boolean $require_once Use require_once instead of require.
	 * @return string|void
	 */
	public function load_template( $name, $args = [], $return = false, $permit_override = false, $require_once = false ) {
		$template = $this->get_base_path() . '/inc/templates/' . $name;

		if( $permit_override ) {
			$located = \locate_template(
				[
					'gravityforms/' . $name,
					'gf-poplink/' . $name,
					$name,
				]
			);

				if( $located )
					$template = $located;
		}

		$args['poplink'] = $this;

		if( $return )
			ob_start();

		\load_template( $template, $require_once, $args );

		if( $return )
			return ob_get_clean();
	}

	/**
	 * An action to print out field settings.
	 *
	 * @param integer $position An integer identifying the location within a form's settings markup which is currently being composed.
	 * @return void
	 */
	public function field_settings( $position ) {
		if( $position !== 500 )
		return;

		$this->load_template( 'field-settings.php' );
	}

	/**
	 * Undocumented function
	 */
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
	 * @param integer $form_id The ID number of the form which to retrieve cached data for.
	 * @param integer $field_id The ID number of the field which to retrieve cached data for.
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
					$data = $this->get_strategy( $form_id )->deserialize( $token );

					$this->data[ $form_id ] = $data['dat']['fields'];
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
	 * @param mixed     $value The unaltered value for a field.
	 * @param \GF_Field $field Data and settings for the field being rendered.
	 * @return mixed
	 */
	public function filter_field_value( $value, $field ) {
		// TODO: this is getting hit up even when composing an AJAX response...
		if( $field['allowsPrepopulate'] != 1 )
			return $value;

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
	 * @param \GF_Form $form The form for which to render settings.
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
							/**
							[
								'label' => esc_html__( 'Encrypted Data Token', 'gf-poplink' ),
								'value' => 'encrypted',
							],
							[
								'label' => esc_html__( 'Database Token', 'gf-poplink' ),
								'value' => 'database',
							],
							*/
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
