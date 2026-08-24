/**
 * SITE QUALITY CHECK — CHECKLISTS
 *
 * Handles tab switching, edit mode toggling, drag-drop reordering,
 * inline label editing, and item status actions (complete/snooze/omit).
 */

( function ( $ ) {
    'use strict';

    var editMode = {};

    $( document ).ready( function () {
        initTabs();
        initEditToggle();
        initItemToggle();
        initItemActions();
        initInlineEdit();
        initDragDrop();
        initAddButtons();
    } );


    /**
     * Tab switching without full page reload.
     */
    function initTabs() {
        $( '#sqc-checklist-tabs' ).on( 'click', 'a.nav-tab', function ( e ) {
            e.preventDefault();

            var checklistId = $( this ).data( 'checklist-id' );

            $( '#sqc-checklist-tabs a.nav-tab' ).removeClass( 'nav-tab-active' );
            $( this ).addClass( 'nav-tab-active' );

            $( '.sqc-checklist-panel' ).hide();
            $( '.sqc-checklist-panel[data-checklist-id="' + checklistId + '"]' ).show();

            if ( history.pushState ) {
                var url = new URL( window.location.href );
                url.searchParams.set( 'checklist', checklistId );
                history.pushState( null, '', url );
            }
        } );
    } // End initTabs()


    /**
     * Toggle edit mode for a checklist panel, revealing add-item/add-section buttons and drag handles.
     */
    function initEditToggle() {
        $( document ).on( 'click', '.sqc-edit-checklist-toggle', function () {
            var checklistId = $( this ).data( 'checklist-id' );
            var panel = $( '.sqc-checklist-panel[data-checklist-id="' + checklistId + '"]' );
            var isEditing = ! editMode[ checklistId ];

            editMode[ checklistId ] = isEditing;

            panel.toggleClass( 'sqc-editing', isEditing );
            panel.find( '.sqc-add-section, .sqc-add-item' ).toggle( isEditing );

            $( this ).text( isEditing ? sqcChecklists.i18n.done : sqcChecklists.i18n.edit );
        } );
    } // End initEditToggle()


    /**
     * Checkbox toggle: complete / incomplete.
     */
    function initItemToggle() {
        $( document ).on( 'change', '.sqc-item-toggle', function () {
            var item = $( this ).closest( '.sqc-item' );
            var checklistId = item.data( 'checklist-id' );
            var itemId = item.data( 'item-id' );
            var status = $( this ).is( ':checked' ) ? 'complete' : 'incomplete';

            item.removeClass( 'sqc-item-complete sqc-item-incomplete' ).addClass( 'sqc-item-' + status );

            ajaxRequest( 'sqc_set_item_status', {
                checklist_id: checklistId,
                item_id: itemId,
                status: status
            } );
        } );
    } // End initItemToggle()


    /**
     * Snooze and omit buttons.
     */
    function initItemActions() {
        $( document ).on( 'click', '.sqc-snooze-item', function () {
            var item = $( this ).closest( '.sqc-item' );
            var checklistId = item.data( 'checklist-id' );
            var itemId = item.data( 'item-id' );

            ajaxRequest( 'sqc_set_item_status', {
                checklist_id: checklistId,
                item_id: itemId,
                status: 'snoozed'
            }, function () {
                item.fadeOut( 200, function () { $( this ).remove(); } );
            } );
        } );

        $( document ).on( 'click', '.sqc-omit-item', function () {
            if ( ! window.confirm( sqcChecklists.i18n.deleteConfirm ) ) {
                return;
            }

            var item = $( this ).closest( '.sqc-item' );
            var checklistId = item.data( 'checklist-id' );
            var itemId = item.data( 'item-id' );

            ajaxRequest( 'sqc_set_item_status', {
                checklist_id: checklistId,
                item_id: itemId,
                status: 'omitted'
            }, function () {
                item.fadeOut( 200, function () { $( this ).remove(); } );
            } );
        } );
    } // End initItemActions()


    /**
     * Inline label editing: click label while in edit mode to make it editable.
     */
    function initInlineEdit() {
        $( document ).on( 'click', '.sqc-item-label', function () {
            var item = $( this ).closest( '.sqc-item' );
            var checklistId = item.data( 'checklist-id' );

            if ( ! editMode[ checklistId ] ) {
                return;
            }

            var label = $( this );
            var currentText = label.text();

            if ( label.find( 'input' ).length ) {
                return;
            }

            var input = $( '<input type="text" class="sqc-item-label-input">' ).val( currentText );

            label.empty().append( input );
            input.trigger( 'focus' ).trigger( 'select' );

            input.on( 'blur keydown', function ( e ) {
                if ( e.type === 'keydown' && e.key !== 'Enter' && e.key !== 'Escape' ) {
                    return;
                }

                var newText = e.key === 'Escape' ? currentText : input.val().trim();

                if ( '' === newText ) {
                    newText = currentText;
                }

                label.text( newText );

                if ( newText !== currentText && e.key !== 'Escape' ) {
                    var itemId = item.data( 'item-id' );

                    ajaxRequest( 'sqc_save_item_label', {
                        checklist_id: checklistId,
                        item_id: itemId,
                        label: newText
                    } );
                }
            } );
        } );
    } // End initInlineEdit()


        /**
     * HTML5 drag-drop reordering for items within a section,
     * sections within a checklist, and checklist tabs themselves.
     */
    function initDragDrop() {
        var dragged = null;

        // Items
        $( document ).on( 'dragstart', '.sqc-item', function ( e ) {
            var checklistId = $( this ).data( 'checklist-id' );

            if ( ! editMode[ checklistId ] ) {
                e.preventDefault();
                return;
            }

            e.stopPropagation();
            dragged = this;
            e.originalEvent.dataTransfer.effectAllowed = 'move';
        } );

        $( document ).on( 'dragover', '.sqc-item', function ( e ) {
            if ( ! dragged || ! $( dragged ).hasClass( 'sqc-item' ) || dragged === this ) {
                return;
            }

            e.preventDefault();
            e.stopPropagation();

            var list = $( this ).closest( '.sqc-items' );

            if ( ! $( dragged ).closest( '.sqc-items' ).is( list ) ) {
                return;
            }

            var rect = this.getBoundingClientRect();
            var offset = e.originalEvent.clientY - rect.top;

            if ( offset > rect.height / 2 ) {
                $( this ).after( dragged );
            } else {
                $( this ).before( dragged );
            }
        } );

        $( document ).on( 'dragend', '.sqc-item', function ( e ) {
            if ( ! dragged || ! $( dragged ).hasClass( 'sqc-item' ) ) {
                return;
            }

            e.stopPropagation();

            var list = $( dragged ).closest( '.sqc-items' );
            var checklistId = $( dragged ).data( 'checklist-id' );
            var sectionId = list.data( 'section-id' );
            var itemIds = list.find( '.sqc-item' ).map( function () {
                return $( this ).data( 'item-id' );
            } ).get();

            ajaxRequest( 'sqc_reorder_items', {
                checklist_id: checklistId,
                section_id: sectionId,
                item_ids: itemIds
            } );

            dragged = null;
        } );

        // Sections
        $( document ).on( 'dragstart', '.sqc-section-header', function ( e ) {
            var section = $( this ).closest( '.sqc-section' );
            var checklistId = section.closest( '.sqc-sections' ).data( 'checklist-id' );

            if ( ! editMode[ checklistId ] ) {
                e.preventDefault();
                return;
            }

            e.stopPropagation();
            dragged = section.get( 0 );
            e.originalEvent.dataTransfer.effectAllowed = 'move';
        } );

        $( document ).on( 'dragover', '.sqc-section', function ( e ) {
            if ( ! dragged || ! $( dragged ).hasClass( 'sqc-section' ) || dragged === this ) {
                return;
            }

            e.preventDefault();

            var rect = this.getBoundingClientRect();
            var offset = e.originalEvent.clientY - rect.top;

            if ( offset > rect.height / 2 ) {
                $( this ).after( dragged );
            } else {
                $( this ).before( dragged );
            }
        } );

        $( document ).on( 'dragend', '.sqc-section-header', function ( e ) {
            if ( ! dragged || ! $( dragged ).hasClass( 'sqc-section' ) ) {
                return;
            }

            e.stopPropagation();

            var wrapper = $( dragged ).closest( '.sqc-sections' );
            var checklistId = wrapper.data( 'checklist-id' );
            var sectionIds = wrapper.find( '.sqc-section' ).map( function () {
                return $( this ).data( 'section-id' );
            } ).get();

            ajaxRequest( 'sqc_reorder_sections', {
                checklist_id: checklistId,
                section_ids: sectionIds
            } );

            dragged = null;
        } );

        // Checklist tabs
        $( document ).on( 'dragstart', '#sqc-checklist-tabs a.nav-tab', function ( e ) {
            if ( ! sqcChecklists.canManage ) {
                e.preventDefault();
                return;
            }

            dragged = this;
            e.originalEvent.dataTransfer.effectAllowed = 'move';
        } );

        $( document ).on( 'dragover', '#sqc-checklist-tabs a.nav-tab', function ( e ) {
            if ( ! dragged || ! $( dragged ).is( 'a.nav-tab' ) || dragged === this ) {
                return;
            }

            e.preventDefault();

            var rect = this.getBoundingClientRect();
            var offset = e.originalEvent.clientX - rect.left;

            if ( offset > rect.width / 2 ) {
                $( this ).after( dragged );
            } else {
                $( this ).before( dragged );
            }
        } );

        $( document ).on( 'dragend', '#sqc-checklist-tabs a.nav-tab', function () {
            if ( ! dragged || ! $( dragged ).is( 'a.nav-tab' ) ) {
                return;
            }

            var checklistIds = $( '#sqc-checklist-tabs a.nav-tab' ).map( function () {
                return $( this ).data( 'checklist-id' );
            } ).get();

            ajaxRequest( 'sqc_reorder_checklists', {
                checklist_ids: checklistIds
            } );

            dragged = null;
        } );

        $( document ).on( 'drop', '.sqc-item, .sqc-section, #sqc-checklist-tabs a.nav-tab', function ( e ) {
            e.preventDefault();
        } );
    } // End initDragDrop()


    /**
     * Add item / add section / add checklist: reveal inline inputs in edit mode,
     * confirm buttons submit via AJAX.
     */
    function initAddButtons() {
        $( document ).on( 'click', '.sqc-show-add-item', function () {
            $( this ).hide().prev( '.sqc-add-item-row' ).show().find( 'input' ).trigger( 'focus' );
        } );

        $( document ).on( 'click', '.sqc-add-item-confirm', function () {
            var row = $( this ).closest( '.sqc-add-item-row' );
            var checklistId = row.data( 'checklist-id' );
            var sectionId = row.data( 'section-id' );
            var input = row.find( '.sqc-add-item-input' );
            var label = input.val().trim();

            if ( '' === label ) {
                return;
            }

            ajaxRequest( 'sqc_add_item', {
                checklist_id: checklistId,
                section_id: sectionId,
                label: label
            }, function () {
                window.location.reload();
            } );
        } );

        $( document ).on( 'keydown', '.sqc-add-item-input', function ( e ) {
            if ( e.key === 'Enter' ) {
                $( this ).siblings( '.sqc-add-item-confirm' ).trigger( 'click' );
            }
        } );

        $( document ).on( 'click', '.sqc-show-add-section', function () {
            $( this ).hide().prev( '.sqc-add-section-row' ).show().find( 'input' ).trigger( 'focus' );
        } );

        $( document ).on( 'click', '.sqc-add-section-confirm', function () {
            var row = $( this ).closest( '.sqc-add-section-row' );
            var checklistId = row.data( 'checklist-id' );
            var label = row.find( '.sqc-add-section-input' ).val().trim();
            var recurrence = row.find( '.sqc-add-section-recurrence' ).val();

            if ( '' === label ) {
                return;
            }

            ajaxRequest( 'sqc_add_section', {
                checklist_id: checklistId,
                label: label,
                recurrence: recurrence
            }, function () {
                window.location.reload();
            } );
        } );

        $( document ).on( 'keydown', '.sqc-add-section-input', function ( e ) {
            if ( e.key === 'Enter' ) {
                $( this ).closest( '.sqc-add-section-row' ).find( '.sqc-add-section-confirm' ).trigger( 'click' );
            }
        } );

        $( '#sqc-add-checklist' ).on( 'click', function () {
            $( this ).hide().prev( '#sqc-add-checklist-row' ).show().find( 'input' ).trigger( 'focus' );
        } );

        $( document ).on( 'click', '#sqc-add-checklist-confirm', function () {
            var title = $( '.sqc-add-checklist-input' ).val().trim();

            if ( '' === title ) {
                return;
            }

            ajaxRequest( 'sqc_add_checklist', {
                title: title
            }, function () {
                window.location.reload();
            } );
        } );

        $( document ).on( 'keydown', '.sqc-add-checklist-input', function ( e ) {
            if ( e.key === 'Enter' ) {
                $( '#sqc-add-checklist-confirm' ).trigger( 'click' );
            }
        } );
    } // End initAddButtons()


    /**
     * Shared AJAX request wrapper.
     *
     * @param {string} action
     * @param {Object} data
     * @param {Function} [onSuccess]
     */
    function ajaxRequest( action, data, onSuccess ) {
        $.post( sqcChecklists.ajaxUrl, $.extend( {
            action: action,
            nonce: sqcChecklists.nonce
        }, data ) )
            .done( function ( response ) {
                if ( ! response.success ) {
                    window.alert( response.data && response.data.message ? response.data.message : 'Error' );
                    return;
                }

                if ( typeof onSuccess === 'function' ) {
                    onSuccess( response );
                }
            } )
            .fail( function () {
                window.alert( 'Request failed.' );
            } );
    } // End ajaxRequest()

} )( jQuery );