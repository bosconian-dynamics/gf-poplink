import { FieldSettings } from './admin/FieldSettings';
import { FieldSetting } from './admin/FieldSetting';

new FieldSettings(
	/*new FieldSetting(
    'enable',
    {
      class: 'poplink_field_settings',
      default: true
    }
  ),*/
	new FieldSetting( 'prepop_lock' ) //,
	//new FieldSetting( 'hide' ),
	//new FieldSetting( 'encrypt' )
);
