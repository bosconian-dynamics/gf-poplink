<?php

namespace BosconianDynamics\GFHashLink;

interface IFormLinkStrategy {
  public function get_link( $post, $data, $expiration = null );

  public function validate( $token );
}