<?php
namespace BosconianDynamics\GFPopLink;

interface IPopulationStrategy {
  function __construct( $form, $settings );
  public function serialize( $data );
  public function deserialize( $token );
}