import jQuery from 'jquery';

jQuery(() => {
  const config = window.poplink_prepop;
  const $button = jQuery('#poplink_poptoken_button');
  const form = $button[0].form;
  const $modal = jQuery('<div><p>Encoding token...</p><input type="text" readonly value="https://google.com/?s=test" /></div>');
  const $msg = $modal.children('p');
  const $link = $modal.children('input');

  $modal.dialog({
    autoOpen: false,
    minWidth: 0.4 * window.innerWidth,
    modal: true,
    buttons: [
      {
        text: 'Ok',
        click: () => { $modal.dialog('close'); }
      },
      {
        text: config.strings.copy_button,
        click: () => { $link[0].select(); document.execCommand('copy'); }
      }
    ]
  });

  $button.on(
    'click',
    () => {
      const data = new FormData( form );

      data.delete( 'gform_ajax' ); // Remove the Gravity Forms AJAX submission querystring to prevent GF intercepting the request.
      data.append( 'action', 'poplink_serialize_formdata' );
      data.append( '_ajax_nonce', config.nonce );
      data.append( 'form_id', 1 );

      jQuery.ajax(
        config.ajax_url,
        {
          data,
          processData: false,
          contentType: false,
          cache: false,
          type: 'POST',
          success: ( res ) => {
            const { success, data } = res;
            const { message, param, token } = data;

            $msg.html( message );

            if( !success ) {
              $link.hide();
              return;
            }

            const { protocol, host, pathname } = window.location;
            
            $link.val( `${protocol}//${host}${pathname}?${param}=${token}` );
            $modal.dialog( 'open' );
          }
        }
      );
    }
  )
});
