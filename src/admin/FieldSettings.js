import jQuery from 'jquery';

export class FieldSettings {
  constructor( ...settings ) {
    this.$container = null;
    this.settings = settings?.reduce(
      ( settings, obj ) => {
        settings[ obj.name ] = obj;

        return settings;
      },
      {}
    );

    jQuery( this.init.bind( this ) );
    jQuery( document ).on(
      'gform_load_field_settings',
      ( _, field, form ) => { this.loadFieldSettings( field, form ); }
    );
  }

  init() {
    this.$container = jQuery( '#poplink_container' );

    // TODO: pretty much everything below should be localized into self-contained FieldSetting subclasses
    this.settings.enable.$input.on(
      'click',
      () => {
        this.settings.enable.save();

        if( this.settings.enable.value === false )
          this.$container.hide();
        else
          this.$container.show();
      }
    );
  }

  loadFieldSettings( field, form ) {
    // Bail early if this form doesn't have population link functionality enabled.
    if( !form?.poplink?.enabled )
      return;

    // Load all field settings values into their inputs.
    for( const setting of Object.values( this.settings ) )
      setting.load();
    
    // If all pre-poulated fields are locked at the form level, disable the prepop_lock input and
    //    display it as set.
    if( form.poplink.lockall === '1' ) {
      this.settings.prepop_lock.disable();
      this.settings.prepop_lock.$input.prop( 'checked', true );
    }

    // Show or hide the sub-settings container dependent on poplinks being enabled for this form.
    if( this.settings.enable.value === false )
      this.$container.hide();
    else
      this.$container.show();
  }
}