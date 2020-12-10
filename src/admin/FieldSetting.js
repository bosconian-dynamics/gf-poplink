import jQuery from 'jquery';

export class FieldSetting {
  constructor( name, options = {} ) {
    this.name = name;
    this.property = options.property || 'poplink_' + this.name;
    this.class = options.class || this.property + '_field_setting';
    this.id = options.id || 'field_' + this.property;
    this.default = options.default;

    this.save = this.save.bind( this );
    this.init = this.init.bind( this );

    jQuery( this.init );  
  }

  init() {
    this.$li = jQuery(`.${this.class}`);
    this.$input = jQuery(`#${this.id}`);

    this.$input.on( 'blur', this.save );
  }

  disable() {
    this.$input.prop( 'disabled', true );
  }

  enable() {
    this.$input.prop( 'disabled', false );
  }

  get value() {
    const value = GetSelectedField()[ this.property ];

    return value === undefined || value === ''
      ? this.default
      : value;
  }

  set value( value ) {
    if( value === undefined )
      value = this.default;

    if( (!value || value === 'on') && this.$input.is(':checkbox') )
      value = this.$input.is(':checked');
    
    if( this.value === value )
      return;
    
    SetFieldProperty( this.property, value );
  }

  load() {
    if( this.$input.is(':checkbox') ) {
      if( typeof this.value === 'boolean' )
        this.$input.prop( 'checked', this.value );
      else if( this.value === this.$input.val() )
        this.$input.prop( 'checked', true );
    }
    else {
      this.$input.val( this.value );
    }
  }

  save() {
    this.value = this.$input.val();
  }
}
