import jQuery from 'jquery';

export class FieldSettings {
	constructor( ...settings ) {
		this.$container = null;
		this.settings = settings?.reduce( ( settings, obj ) => {
			settings[ obj.name ] = obj;

			return settings;
		}, {} );

		jQuery( this.init.bind( this ) );
		jQuery( document ).on(
			'gform_load_field_settings',
			( _, field, form ) => {
				this.loadFieldSettings( field, form );
			}
		);
	}

	isPrepopEnabled() {
		return GetSelectedField()[ 'allowsPrepopulate' ];
	}

	init() {
		this.$container = jQuery( '#poplink_container' );

		// Show or hide poplink field settings when "Allow prepopulate" setting changes
		jQuery( '#field_prepopulate' ).on(
			'click',
			( { target: { checked } } ) => {
				if ( checked ) this.$container.slideDown();
				else this.$container.slideUp();
			}
		);
	}

	loadFieldSettings( field, form ) {
		// Bail early if this form doesn't have population link functionality enabled.
		if ( ! form?.poplink?.enabled ) return;

		// Load all field settings values into their inputs.
		for ( const setting of Object.values( this.settings ) ) setting.load();

		// If all pre-poulated fields are locked at the form level, disable the prepop_lock input and
		//    display it as set.
		if ( form.poplink.lockall === '1' ) {
			this.settings.prepop_lock.disable();
			this.settings.prepop_lock.$input.prop( 'checked', true );
		}

		// Show or hide the sub-settings container dependent on prepopulation being enabled for this field.
		if ( this.isPrepopEnabled() ) this.$container.show();
		else this.$container.hide();
	}
}
