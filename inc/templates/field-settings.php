      <li class="field_setting poplink_field_settings">
        <label class="section_label">
          <?php esc_html_e( 'Population Links', 'gf-poplink' ); ?>
          <?php gform_tooltip( 'form_field_poplink' ); ?>
        </label>

        <input type="checkbox" id="field_poplink_enable" />
        <label for="field_poplink_enable" class="inline">
          <?php esc_html_e( 'Allow field to be populated by request tokens', 'gf-poplink' ); ?>
          <?php gform_tooltip( 'poplink_enable' ); ?>
        </label>
        <br />

        <div id="poplink_container" style="display:none; padding-top:10px;">
          <ul>
            <li class="field_setting poplink_prepop_lock_field_setting">
              <input type="checkbox" id="field_poplink_prepop_lock" />
              <label for="field_poplink_prepop_lock" class="inline">
                <?php esc_html_e( 'Disable field when pre-populated', 'gf-poplink' ) ?>
                <?php gform_tooltip( 'poplink_prepop_lock' ) ?>
              </label>
            </li>

            <!--<li class="field_setting poplink_hide_field_setting">
              <input type="checkbox" id="field_poplink_hide" />
              <label for="field_poplink_hide" class="inline">
                <?php esc_html_e( 'Hide this field when pre-populated', 'gf-poplink' ) ?>
                <?php gform_tooltip( 'poplink_hide' ) ?>
              </label>
            </li>

            <li class="field_setting poplink_encrypt_field_setting">
              <input type="checkbox" id="field_poplink_encrypt" />
              <label for="field_poplink_encrypt" class="inline">
                <?php esc_html_e( 'Encrypt this field\'s value in population tokens', 'gf-poplink' ) ?>
                <?php gform_tooltip( 'poplink_encrypt' ) ?>
              </label>
            </li>-->
          </ul>
        </div>
      </li>