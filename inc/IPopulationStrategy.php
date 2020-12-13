<?php
namespace BosconianDynamics\GFPopLink;

interface IPopulationStrategy {
  function __construct( $form, $settings );

  public function deserialize( $token );
  
  public function serialize( $data );
}
