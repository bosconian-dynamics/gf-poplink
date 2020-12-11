<?php echo $args['param']; ?>=<?php echo $args['token']; ?>

<?php var_dump( $args['poplink']->decode_jwt( $args['token'] ) ); ?>