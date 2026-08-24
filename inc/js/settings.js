/**
 * SITE QUALITY CHECK — SETTINGS
 *
 * AJAX save with Ctrl+S support, live preview of menu title, page title,
 * menu icon, and logo, matching Admin Help Docs' settings behavior.
 */

( function ( $ ) {
    'use strict';

    var $saveButton;
    var originalText;
    var savingInterval;

    var isDirty = false;

    $( document ).ready( function () {
        $saveButton = $( '#sqc-subheader .sqc-button' );
        originalText = $saveButton.text();

        $( document ).on( 'input change', '.sqc-settings-grid [name]', function () {
            markDirty();
        } );

        // Menu Title
        $( document ).on( 'input', '#sqc_menu_title', function () {
            var target = $( 'li#toplevel_page_site-quality-check .wp-menu-name' );
            target.text( $( this ).val() || '...' );
        } );

        // Page Title
        $( document ).on( 'input', '#sqc_page_title', function () {
            var target = $( '#sqc-header h1' );
            target.text( $( this ).val() || '...' );
        } );

        // Menu Icon
        $( document ).on( 'change', '#sqc_menu_icon', function () {
            var icon = $( this ).val();
            var target = $( 'li#toplevel_page_site-quality-check .wp-menu-image' );
            target.attr( 'class', 'wp-menu-image dashicons-before dashicons-' + icon );
        } );

        // Logo
        $( document ).on( 'input', '#sqc_logo', function () {
            var url = $( this ).val();
            var target = $( '#sqc-header .logo' );
            url ? target.attr( 'src', url ).show() : target.hide();
        } );

        // Upload Settings
        $( document ).on( 'change', '#sqc-upload-settings', function ( e ) {
            var file = e.target.files[ 0 ];

            if ( ! file ) {
                return;
            }

            var reader = new FileReader();

            reader.onload = function ( event ) {
                try {
                    var data = JSON.parse( event.target.result );
                    applyUploadedSettings( data.settings || {} );

                    $( '#sqc-upload-filename' ).text( file.name ).show();
                    markDirty();
                } catch ( err ) {
                    window.alert( 'Invalid JSON file. Please check the file and try again.' );
                    $( '#sqc-upload-settings' ).val( '' );
                }
            };

            reader.readAsText( file );
        } );

        $saveButton.on( 'click', function ( e ) {
            e.preventDefault();
            saveSettings();
        } );

        $( document ).on( 'keydown', function ( e ) {
            if ( ( e.ctrlKey || e.metaKey ) && e.key.toLowerCase() === 's' ) {
                e.preventDefault();
                saveSettings();
            }
        } );
    } );

    
    /**
     * Map of exported settings keys to their corresponding field IDs.
     */
    var SETTINGS_FIELD_MAP = {
        menu_title: 'sqc_menu_title',
        page_title: 'sqc_page_title',
        menu_icon: 'sqc_menu_icon',
        logo: 'sqc_logo',
        contact_page_id: 'sqc_contact_page_id',
        contact_form_id: 'sqc_contact_form_id'
    };


    /**
     * Populate settings fields from an uploaded export's "settings" object.
     * Does not save — the user must still click Save to persist changes.
     *
     * @param {Object} settings
     */
    function applyUploadedSettings( settings ) {
        Object.keys( SETTINGS_FIELD_MAP ).forEach( function ( key ) {
            if ( ! settings.hasOwnProperty( key ) ) {
                return;
            }

            var $field = $( '#' + SETTINGS_FIELD_MAP[ key ] );

            if ( ! $field.length ) {
                return;
            }

            $field.val( settings[ key ] ).trigger( 'change' ).trigger( 'input' );
        } );

        if ( settings.hasOwnProperty( 'stale_thresholds' ) ) {
            $( '[name="sqc_stale_thresholds[warning]"]' ).val( settings.stale_thresholds.warning );
            $( '[name="sqc_stale_thresholds[danger]"]' ).val( settings.stale_thresholds.danger );
            $( '[name="sqc_stale_thresholds[critical]"]' ).val( settings.stale_thresholds.critical );
        }

        if ( settings.hasOwnProperty( 'stale_post_types' ) ) {
            $( '[name="sqc_stale_post_types[]"]' ).prop( 'checked', false );
            settings.stale_post_types.forEach( function ( type ) {
                $( '[name="sqc_stale_post_types[]"][value="' + type + '"]' ).prop( 'checked', true );
            } );
        }

        if ( settings.hasOwnProperty( 'enabled_quick_actions' ) ) {
            $( '[name="sqc_enabled_quick_actions[]"]' ).prop( 'checked', false );
            settings.enabled_quick_actions.forEach( function ( action ) {
                $( '[name="sqc_enabled_quick_actions[]"][value="' + action + '"]' ).prop( 'checked', true );
            } );
        }

        if ( settings.hasOwnProperty( 'clear_data_on_uninstall' ) ) {
            $( '[name="sqc_clear_data_on_uninstall"]' ).prop( 'checked', !! settings.clear_data_on_uninstall );
        }
    } // End applyUploadedSettings()


    /**
     * Show the save reminder and mark state as unsaved.
     */
    function markDirty() {
        if ( isDirty ) {
            return;
        }

        isDirty = true;
        $( '#sqc-save-reminder' ).fadeIn( 200 );
    } // End markDirty()


    /**
     * Hide the save reminder after a successful save.
     */
    function clearDirty() {
        isDirty = false;
    } // End clearDirty()


    /**
     * Collect every named field within the settings grid into a flat object.
     *
     * @return Object
     */
    function gatherSettings() {
        var data = {};

        $( '.sqc-settings-grid [name]' ).each( function () {
            var $field = $( this );
            var name = $field.attr( 'name' ).replace( /\[\]$/, '' );
            var val;

            if ( $field.attr( 'type' ) === 'checkbox' ) {
                if ( $field.attr( 'name' ).endsWith( '[]' ) ) {
                    val = $( '[name="' + $field.attr( 'name' ) + '"]:checked' ).map( function () {
                        return this.value;
                    } ).get();
                } else {
                    val = $field.is( ':checked' ) ? 1 : 0;
                }
            } else {
                val = $field.val();
            }

            if ( name.indexOf( '[' ) !== -1 ) {
                var base = name.substring( 0, name.indexOf( '[' ) );
                var sub = name.substring( name.indexOf( '[' ) + 1, name.indexOf( ']' ) );
                data[ base ] = data[ base ] || {};
                data[ base ][ sub ] = val;
            } else if ( data.hasOwnProperty( name ) ) {
                data[ name ] = [].concat( data[ name ], val );
            } else {
                data[ name ] = val;
            }
        } );

        return data;
    } // End gatherSettings()


    /**
     * Save all settings via AJAX.
     */
    function saveSettings() {
        if ( $saveButton.prop( 'disabled' ) ) {
            return;
        }

        var settings = gatherSettings();
        var originalTitle = startSavingTitle();

        $( '#sqc-save-reminder' ).hide();
        isDirty = false;

        showSaving();

        $.ajax( {
            url: ajaxurl,
            method: 'POST',
            dataType: 'json',
            data: {
                action: 'sqc_save_settings',
                nonce: sqcSettings.nonce,
                settings: settings
            },
            success: function ( response ) {
                stopSavingTitle( originalTitle );

                if ( response.success ) {
                    showResult( sqcSettings.savedText );
                    clearDirty();
                } else {
                    showResult( response.data || sqcSettings.errorText, false );
                }
            },
            error: function () {
                stopSavingTitle( originalTitle );
                showResult( sqcSettings.errorText, false );
            }
        } );
    } // End saveSettings()


    /**
     * Animate the browser tab title while saving.
     *
     * @return {string} originalTitle
     */
    function startSavingTitle() {
        var originalTitle = document.title;
        var dots = 0;

        document.title = sqcSettings.savingText;

        savingInterval = setInterval( function () {
            dots = ( dots + 1 ) % 4;
            document.title = sqcSettings.savingText + '.'.repeat( dots );
        }, 500 );

        return originalTitle;
    } // End startSavingTitle()


    /**
     * Restore the browser tab title.
     *
     * @param {string} originalTitle
     */
    function stopSavingTitle( originalTitle ) {
        clearInterval( savingInterval );
        document.title = originalTitle;
    } // End stopSavingTitle()


    /**
     * Show the spinning save state.
     */
    function showSaving() {
        $saveButton.addClass( 'sqc-button-saving' ).prop( 'disabled', true ).html( '<span class="dashicons dashicons-update spin"></span> ' + sqcSettings.savingText + '...' );
        $( '#sqc-save-status' ).remove();
    } // End showSaving()


    /**
     * Show the result message next to the save button.
     *
     * @param {string} message
     * @param {boolean} [success]
     */
    function showResult( message, success ) {
        success = ( undefined === success ) ? true : success;

        $saveButton.removeClass( 'sqc-button-saving' ).prop( 'disabled', false ).text( originalText );

        var $status = $( '<span id="sqc-save-status"></span>' ).text( message ).css( {
            marginLeft: '10px',
            color: success ? 'green' : 'red',
            fontWeight: 'bold'
        } );

        $saveButton.after( $status );

        setTimeout( function () {
            $status.fadeOut( 400, function () { $( this ).remove(); } );
        }, 4000 );
    } // End showResult()

} )( jQuery );