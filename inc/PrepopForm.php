<?php
namespace BosconianDynamics\GFPopLink;

/**
 * Undocumented class
 */
class PrepopForm {
	const TEMPLATES = [
		'form.php',
		'page.php',
		'singular.php',
		'index.php',
	];

	/**
	 * AddOn instance.
	 *
	 * @var GFPopLinkAddOn $addon
	 */
	protected $addon;

	/**
	 * Gravity Forms form object/data representing the form to render.
	 *
	 * @var \GF_Form|array $form
	 */
	protected $form;

	/**
	 * Form ID.
	 *
	 * @var integer $id
	 */
	protected $id;

	/**
	 * Addon settings for this form.
	 *
	 * @var array $settings
	 */
	protected $settings;

	/**
	 * This form's population strategy.
	 *
	 * @var IPopulationStrategy $strategy
	 */
	protected $strategy;

	/**
	 * The fabricated WP_Post object to inject into the main query.
	 *
	 * @var \WP_Post $virtual_post
	 */
	protected $virtual_post;

	/**
	 * Undocumented function
	 *
	 * @param \GF_Form|array      $form Gravity Forms form object/data representing the form to render.
	 * @param array               $settings Addon settings for this form.
	 * @param IPopulationStrategy $strategy This form's population strategy.
	 * @param GFPopLinkAddOn      $addon AddOn instance.
	 */
	public function __construct( $form, $settings, $strategy, $addon ) {
		$this->addon    = $addon;
		$this->form     = $form;
		$this->id       = $form['id'];
		$this->settings = $settings;
		$this->strategy = $strategy;
	}

	/**
	 * Check if this request is a form submission.
	 *
	 * @return boolean
	 */
	public function is_submission() {
		return \rgpost( 'gform_submit' ) == '1';
	}

	/**
	 * Simulates a WordPress loading sequence to display a fabricated WP_Post object in an overridden
	 * WP_Query main query.
	 *
	 * @return void
	 */
	public function load() {
		global $wp;

		// TODO: this hack allows the admin bar to check the dashboard screen even though we're not
		// really on a dashboard page. Virtual pages should probably either be moved out of wp-admin
		// or loaded on an admin screen like Gravity Forms does with preview.php.
		require_once \ABSPATH . 'wp-admin/includes/class-wp-screen.php';
		require_once \ABSPATH . 'wp-admin/includes/screen.php';
		\set_current_screen( 'front' );

		\do_action( 'parse_request', $wp );

		if( $this->is_submission() ) {
			// Handle prepopulation submission.
			$data = $this->addon->get_form_data( $this->form, false );

			/**
			 * The token if form data was serialized successfully, or a WP_Error object describing the failure state.
			 *
			 * @var string|\WP_Error $token
			 */
			$token = $this->strategy->serialize(
				[
					'form_id' => $this->id,
					'fields'  => $data,
				]
			);

			if( \is_wp_error( $token ) ) {
				$this->setup_post(
					__( 'Error', 'gf-poplink' ),
					sprintf(
						'<p>' . \esc_html__( 'An error occured during token generation: "%s"', 'gf-poplink' ) . '</p>',
						$token->get_error_message()
					)
				);
			}
			else {
				$this->setup_post(
					sprintf(
						__( '"%s" Population Token', 'gf-poplink' ),
						filter_var( $this->form['title'], FILTER_SANITIZE_STRING )
					),
					sprintf(
						'<p>' . \esc_html__( 'Success! The following parameters can be appended to any URL where the "%1$s" form is accessible in order to populate it with the entered data.<pre>%2$s=%3$s</pre>', 'gf-poplink' ) . '</p>',
						filter_var( $this->form['title'], FILTER_SANITIZE_STRING ),
						$this->settings['param'],
						$token
					)
				);
			}
		}
		else {
			// Display prepopulation form/page.
			$this->setup_post(
				sprintf(
					__( 'Prepopulate "%s"', 'gf-poplink' ),
					filter_var( $this->form['title'], FILTER_SANITIZE_STRING )
				),
				// TODO: should probably actually functionally render the form to markup.
				'[gravityform id="' . $this->id . '" title="false"]'
			);
		}

		\wp_enqueue_script( 'jquery' );

		$this->setup_query();

		\do_action( 'wp', $wp );
		\do_action( 'template_redirect' );

		$this->load_template();

		exit();
	}

	/**
	 * Loads the actual template file used to display the virtual post containing the form.
	 * Provides a mechanism for themes to override template selection with form-{id}.php and
	 * form.php templates, falling back to the standard Page hierarchy if no custom templates
	 * are found.
	 *
	 * @param array $args Arguments to pass to the template file.
	 * @return void
	 */
	public function load_template( $args = [] ) {
		$template = \locate_template( array_merge( [ 'form-' . $this->id . '.php' ], self::TEMPLATES ) );

		// TODO: this doesn't seem reliably safe - generic conditionals could easily misdirect template selection
		// $template = \apply_filters( 'template_include', $template );.

		\load_template(
			$template,
			true,
			array_merge(
				[
					'form'    => $this->form,
					'form_id' => $this->id,
				],
				$args
			)
		);
	}

	/**
	 * Fabricates a WP_Post object with the specified title and content.
	 *
	 * @param string $title The name of the post.
	 * @param string $content HTML content.
	 * @return void
	 */
	public function setup_post( $title, $content ) {
		$this->virtual_post = new \WP_Post(
			(object) [
				'ID'             => 0,
				'post_title'     => $title,
				'post_name'      => sanitize_title( $title ),
				'post_content'   => $content,
				'post_excerpt'   => '',
				'post_parent'    => 0,
				'menu_order'     => 0,
				'post_type'      => 'page',
				'post_status'    => 'publish',
				'comment_status' => 'closed',
				'ping_status'    => 'closed',
				'comment_count'  => 0,
				'post_password'  => '',
				'to_ping'        => '',
				'pinged'         => '',
				'guid'           => add_query_arg( 'gf_prepop', $this->id, admin_url() ),
				'post_date'      => current_time( 'mysql' ),
				'post_date_gmt'  => current_time( 'mysql', 1 ),
				'post_author'    => $this->form['useCurrentUserAsAuthor']
				? get_current_user_id()
				: $this->form['post_author'],
				'filter'         => 'raw',
			]
		);
	}

	/**
	 * Sets up the main query to display the fabricated WP_Post.
	 *
	 * @return void
	 */
	public function setup_query() {
		global $wp_query;

		$wp_query->init();
		$wp_query->is_page       = true;
		$wp_query->is_singular   = true;
		$wp_query->is_home       = false;
		$wp_query->found_posts   = 1;
		$wp_query->post_count    = 1;
		$wp_query->max_num_pages = 1;
		$posts                   = apply_filters( 'the_posts', [ $this->virtual_post ], $wp_query );
		$post                    = $posts[0];
		$GLOBALS['post']         = $post; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		$wp_query->posts         = $posts;
		$wp_query->post          = $post;
	}
}
