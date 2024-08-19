<?php

$license_popup_title = $this->properties['license_popup_strings']['title'];
$license_popup_id    = 'wunderupdates-license-' . md5($this->properties['slug']);
$license_ajax_func   = 'wunderupdates-verify-license-' . md5($this->properties['slug']);

$channel_popup_title = $this->properties['channel_popup_strings']['title'];
$channel_popup_id    = 'wunderupdates-channel-' . md5($this->properties['slug']);
$channel_ajax_func   = 'wunderupdates-save-channel-' . md5($this->properties['slug']);
$channel             = $this->properties['channel'];
?>

<style>
    .spin {
        animation: l11 1s infinite linear;
    }

    @keyframes l11{ 
        100%{transform: rotate(1turn)}
    }
</style>

<script>
    var old_tb_position, old_tb_remove;

    function wunderupdates_tb_position() {
        var isIE6 = typeof document.body.style.maxHeight === "undefined";
        jQuery("#TB_window").css({marginLeft: '-' + parseInt((TB_WIDTH / 2),10) + 'px', width: TB_WIDTH + 'px'});

        if ( ! isIE6 ) { // take away IE6
            jQuery("#TB_window").css({marginTop: '-' + parseInt((TB_HEIGHT / 2),10) + 'px'});
        }
    }

    function wunderupdates_tb_remove() {
        window.tb_position = old_tb_position;
        window.tb_remove   = old_tb_remove;

        window.tb_remove();
    }

    function showLicensePopup() {
        old_tb_position = window.tb_position;
        old_tb_remove   = window.tb_remove;

        window.tb_position = wunderupdates_tb_position;
        window.tb_remove   = wunderupdates_tb_remove;

        let title = '<?php echo esc_html($license_popup_title); ?>';
        let id    = '<?php echo esc_html($license_popup_id); ?>';

        jQuery( '#<?php echo esc_attr($license_popup_id); ?>-license' ).val( '' );
        jQuery( '.<?php echo esc_attr($license_popup_id); ?>-success' ).hide();
        jQuery( '.<?php echo esc_attr($license_popup_id); ?>-fail' ).hide();

        tb_show(title, '?TB_inline&width=350&height=160&inlineId=' + id);
    }

    function showChannelPopup() {
        old_tb_position = window.tb_position;
        old_tb_remove   = window.tb_remove;

        window.tb_position = wunderupdates_tb_position;
        window.tb_remove   = wunderupdates_tb_remove;

        let title = '<?php echo esc_html($channel_popup_title); ?>';
        let id    = '<?php echo esc_html($channel_popup_id); ?>';

        jQuery( '#<?php echo esc_attr($channel_popup_id); ?>-channel' ).val( '<?php echo esc_attr($channel); ?>' );
        jQuery( '.<?php echo esc_attr($channel_popup_id); ?>-success' ).hide();
        jQuery( '.<?php echo esc_attr($channel_popup_id); ?>-fail' ).hide();

        tb_show(title, '?TB_inline&width=350&height=160&inlineId=' + id);
    }    

    function checkLicense() {
        let elementId = '#<?php echo esc_attr($license_popup_id); ?>' + '-license';
        let license   = jQuery( elementId ).val();
        let args      = {
            license: license,
        };

        jQuery( '.<?php echo esc_attr($license_popup_id); ?>-success' ).hide();
        jQuery( '.<?php echo esc_attr($license_popup_id); ?>-fail' ).hide();
        jQuery( '.<?php echo esc_attr($license_popup_id); ?>-spinner' ).show();

        wp.ajax.post( '<?php echo esc_attr($license_ajax_func); ?>', args )
            .done( function( response ) {
                console.log( response );
                if ( response.valid ) {
                    jQuery( '.<?php echo esc_attr($license_popup_id); ?>-success' ).show();
                } else {
                    jQuery( '.<?php echo esc_attr($license_popup_id); ?>-fail' ).show();
                }
            } )
            .fail( function( error ) {
                console.error( error );
                jQuery( '.<?php echo esc_attr($license_popup_id); ?>-fail' ).show();
            } )
            .always( function() {
                jQuery( '.<?php echo esc_attr($license_popup_id); ?>-spinner' ).hide();
            } );
    }

    function saveChannel() {
        let elementId = '#<?php echo esc_attr($channel_popup_id); ?>' + '-channel';
        let channel   = jQuery( elementId ).val();
        let args      = {
            channel: channel,
        };

        jQuery( '.<?php echo esc_attr($channel_popup_id); ?>-success' ).hide();
        jQuery( '.<?php echo esc_attr($channel_popup_id); ?>-fail' ).hide();
        jQuery( '.<?php echo esc_attr($channel_popup_id); ?>-spinner' ).show();

        wp.ajax.post( '<?php echo esc_attr($channel_ajax_func); ?>', args )
            .done( function( response ) {
                jQuery( '.<?php echo esc_attr($channel_popup_id); ?>-success' ).show();
            } )
            .fail( function( error ) {
                jQuery( '.<?php echo esc_attr($channel_popup_id); ?>-fail' ).show();
            } )
            .always( function() {
                jQuery( '.<?php echo esc_attr($channel_popup_id); ?>-spinner' ).hide();
            } );

    }
</script>

<div id="<?php echo esc_html($license_popup_id); ?>" style="display:none;width:159px">
    <p>
        <?php echo esc_html($this->properties['license_popup_strings']['description']); ?>
    </p>
    <p>
        License key: <input id="<?php echo esc_attr($license_popup_id); ?>-license" type="text">
        <button onClick="checkLicense()">Save</button>
    </p>
    <p class="<?php echo esc_html($license_popup_id); ?>-spinner" style="display:none">
        <span class="dashicons dashicons-image-rotate spin"></span>
    </p>    
    <p class="<?php echo esc_html($license_popup_id); ?>-success" style="display:none">
        <span class="dashicons dashicons-yes-alt" style="color: green;"></span>
        <?php echo esc_html($this->properties['license_popup_strings']['validation_success']); ?>
    </p>
    <p class="<?php echo esc_html($license_popup_id); ?>-fail" style="display:none">
        <span class="dashicons dashicons-no-alt" style="color: red;"></span>
        <?php echo esc_html($this->properties['license_popup_strings']['validation_fail']); ?>
    </p>
</div>

<div id="<?php echo esc_html($channel_popup_id); ?>" style="display:none;width:159px">
    <p>
        Channel:
        <select id="<?php echo esc_attr($channel_popup_id); ?>-channel">
            <?php foreach ( $this->release_channels as $key => $value ) : ?>
                <option value="<?php echo esc_attr($key); ?>"><?php echo esc_html($value); ?></option>
            <?php endforeach; ?>
        </select>
        <button onClick="saveChannel()">Save</button>
    </p>
    <p class="<?php echo esc_html($channel_popup_id); ?>-spinner" style="display:none">
        <span class="dashicons dashicons-image-rotate spin"  style="color: green;"></span>
    </p>    
    <p class="<?php echo esc_html($channel_popup_id); ?>-success" style="display:none">
        <span class="dashicons dashicons-yes-alt"  style="color: red;"></span>
        Update channel saved.

    </p>
    <p class="<?php echo esc_html($channel_popup_id); ?>-fail" style="display:none">
        <span class="dashicons dashicons-no-alt"></span>
        Failed to save update channel.
    </p>
</div>
