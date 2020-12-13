<?php
namespace BosconianDynamics\GFPopLink;

class PrepopForm {
  const TEMPLATES = [
    'form.php',
    'page.php',
    'singular.php',
    'index.php'
  ];

  protected $form;
  protected $id;
  protected $settings;
  protected $strategy;
  protected $title;
  protected $virtual_post;

  function __construct( $form, $settings, $strategy ) {
    $this->form     = $form;
    $this->id       = $form['id'];
    $this->settings = $settings;
    $this->strategy = $strategy;
  }

  public function is_submission() {
    return \rgpost( 'gform_submit' ) == '1';
  }

  public function load() {
    global $wp;

    // TODO: this hack allows the admin bar to check the dashboard screen even though we're not
    //   really on a dashboard page. Virtual pages should probably either be moved out of wp-admin
    //   or loaded on an admin screen like Gravity Forms does with preview.php
    require_once( \ABSPATH . 'wp-admin/includes/class-wp-screen.php' );
    require_once( \ABSPATH . 'wp-admin/includes/screen.php' );
    \set_current_screen( 'front' );

    \do_action( 'parse_request', $wp );

    if( $this->is_submission() ) {
      // Handle prepopulation submission
      $data = $this->get_form_data();
      $token = $this->strategy->serialize([
        'form_id' => $this->id,
        'fields'  => $data
      ]);

      if( \is_wp_error( $token ) ) {
        $this->setup_post(
          __( 'Error', 'gf-poplink' ),
          sprintf(
            __( '<p>An error occured during token generation: "%s"</p>' ),
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
            __( '<p>Success! The following parameters can be appended to any URL where the "%s" form is accessible in order to populate it with the entered data.</p><pre>%s=%s</pre>' ),
            filter_var( $this->form['title'], FILTER_SANITIZE_STRING ),
            $this->settings['param'],
            $token
          )
        );
      }
    }
    else {
      // Display prepopulation form/page
      $this->setup_post(
        sprintf(
          __( 'Prepopulate "%s"', 'gf-poplink' ),
          filter_var( $this->form['title'], FILTER_SANITIZE_STRING )
        ),
        // TODO: should probably actually functionally render the form to markup
        '[gravityform id="' . $this->id . '" title="false"]'
      );
    }

    $this->setup_query();

    \do_action( 'wp', $wp );
    \do_action( 'template_redirect' );

    $this->load_template();

    exit();
  }

  public function get_form_data() {
    $data = [];

    foreach( $this->form['fields'] as $field ) {
      if( !$field['allowsPrepopulate'] )
        continue;
      
      if( $field['type'] === 'checkbox' ) {
        $choice_number = 0;

        for( $i = 0; $i < count( $field['choices'] ); $i++ ) {
          // Gravity Forms checkbox input indexes skip multiples of 10, so the choice index cannot
          //   be used directly.
          if( $choice_number > 0 && $choice_number % 10 === 0 )
            $choice_number++;
          
          $input_name = 'input_' . $field['id'] . '.' . $choice_number++;

          if( \rgempty( $input_name, $_POST ) )
            continue;
          
          $data[ $input_name ] = \rgpost( $input_name );
        }

        continue;
      }

      $input_name = 'input_' . $field['id'];
      
      if( \rgempty( $input_name, $_POST ) )
        continue;
      
      $data[ $input_name ] = \rgpost( $input_name );
    }

    return $data;
  }

  public function load_template( $args = [] ) {
    $template = \locate_template( array_merge( [ 'form-' . $this->id . '.php' ], self::TEMPLATES ) );

    // TODO: this doesn't seem reliably safe - generic conditionals could easily misdirect template selection
    // $template = \apply_filters( 'template_include', $template );

    \load_template(
      $template,
      true,
      array_merge(
        [
          'form'    => $this->form,
          'form_id' => $this->id
        ],
        $args
      )
    );
  }

  public function setup_post( $title, $content ) {
    $this->virtual_post = new \WP_Post((object)[
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
      'filter'         => 'raw'
    ]);
  }
  
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
    $GLOBALS['post']         = $post;

    $wp_query->posts         = $posts;
    $wp_query->post          = $post;
  }
}
