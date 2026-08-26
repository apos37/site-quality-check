/**
 * SITE QUALITY CHECK — CHECKLISTS
 *
 * Sidebar switching, edit mode toggling, drag-drop reordering (items,
 * sections, and sidebar checklists), inline label editing, and item
 * status actions (complete/snooze/omit).
 */

( function ( $ ) {
    'use strict';

    var editMode = {};

    $( document ).ready( function () {
        initSidebarSwitching();
        initEditToggle();
        initDeleteChecklist();
        initItemToggle();
        initItemActions();
        initInlineEdit();
        initDragDrop();
        initAddButtons();
        initSearch();
        initPreloadDefaults();
    } );


    /**
     * Switch the visible checklist panel when a sidebar item is clicked.
     */
    function initSidebarSwitching() {
        $( '#sqc-checklist-list' ).on( 'click', '.sqc-sidebar-item a', function ( e ) {
            e.preventDefault();
            switchToChecklist( $( this ).data( 'checklist-id' ) );
        } );
    } // End initSidebarSwitching()


    /**
     * Toggle edit mode for a checklist panel, revealing add-item/add-section/delete controls and drag handles.
     */
    function initEditToggle() {
        $( document ).on( 'click', '.sqc-edit-checklist-toggle', function () {
            var checklistId = $( this ).data( 'checklist-id' );
            var panel = $( '.sqc-checklist-panel[data-checklist-id="' + checklistId + '"]' );
            var isEditing = ! editMode[ checklistId ];
            var button = $( this );

            if ( ! isEditing ) {
                closeOpenEditors( panel, function () {
                    finishEditToggle( panel, checklistId, isEditing, button );
                } );
            } else {
                finishEditToggle( panel, checklistId, isEditing, button );
            }
        } );
    } // End initEditToggle()


    /**
     * Apply the actual edit-mode visibility changes, called only after any
     * open editors have finished saving/closing.
     *
     * @param {jQuery} panel
     * @param {string|number} checklistId
     * @param {boolean} isEditing
     * @param {jQuery} button
     */
    function finishEditToggle( panel, checklistId, isEditing, button ) {
        editMode[ checklistId ] = isEditing;

        panel.toggleClass( 'sqc-editing', isEditing );
        panel.find( '.sqc-show-add-section, .sqc-show-add-item, .sqc-checklist-danger-zone, .sqc-section-edit-controls, .sqc-section-drag-handle, .sqc-item-actions-edit' ).toggle( isEditing );
        panel.find( '.sqc-item-actions-persistent' ).toggle( ! isEditing );
        panel.find( '.sqc-item-toggle' ).toggle( ! isEditing );

        panel.find( '.sqc-item-toggle' ).each( function () {
            var isSnoozed = $( this ).closest( '.sqc-item' ).hasClass( 'sqc-item-snoozed' );
            $( this ).prop( 'disabled', isEditing || isSnoozed );
        } );

        button.text( isEditing ? sqcChecklists.i18n.done : sqcChecklists.i18n.edit );
    } // End finishEditToggle()


    /**
     * Force-close any open inline editors, saving changed text, then call back
     * once everything in flight has settled.
     *
     * @param {jQuery} panel
     * @param {Function} callback
     */
    function closeOpenEditors( panel, callback ) {
        var pending = [];

        panel.find( '.sqc-item-label-input' ).each( function () {
            pending.push( waitForBlurSave( $( this ) ) );
        } );

        panel.find( '.sqc-checklist-title-input' ).each( function () {
            pending.push( waitForBlurSave( $( this ) ) );
        } );

        panel.find( '.sqc-edit-section-row:visible' ).each( function () {
            var row = $( this );
            pending.push( waitForAjaxClick( row.find( '.sqc-edit-section-confirm' ) ) );
        } );

        panel.find( '.sqc-add-item-row:visible' ).each( function () {
            $( this ).find( '.sqc-add-item-cancel' ).trigger( 'click' );
        } );

        panel.find( '.sqc-add-section-row:visible' ).each( function () {
            $( this ).find( '.sqc-add-section-cancel' ).trigger( 'click' );
        } );

        if ( 0 === pending.length ) {
            callback();
            return;
        }

        $.when.apply( $, pending ).done( callback );
    } // End closeOpenEditors()


    /**
     * Trigger blur on an item-label input and resolve once its save (if any) completes.
     *
     * @param {jQuery} input
     * @return {jQuery.Deferred}
     */
    function waitForBlurSave( input ) {
        var deferred = $.Deferred();

        input.one( 'sqc:saved', function () {
            deferred.resolve();
        } );

        input.trigger( 'blur' );

        // If no AJAX save was needed (text unchanged), resolve immediately on next tick.
        setTimeout( function () {
            if ( 'pending' === deferred.state() ) {
                deferred.resolve();
            }
        }, 0 );

        return deferred.promise();
    } // End waitForBlurSave()


    /**
     * Click a confirm button and resolve once its AJAX call completes.
     *
     * @param {jQuery} button
     * @return {jQuery.Deferred}
     */
    function waitForAjaxClick( button ) {
        var deferred = $.Deferred();

        button.one( 'sqc:saved', function () {
            deferred.resolve();
        } );

        button.trigger( 'click' );

        return deferred.promise();
    } // End waitForAjaxClick()


    /**
     * Delete the current checklist entirely.
     */
    function initDeleteChecklist() {
        $( document ).on( 'click', '.sqc-delete-checklist', function () {
            if ( ! window.confirm( sqcChecklists.i18n.deleteConfirm ) ) {
                return;
            }

            var checklistId = $( this ).data( 'checklist-id' );

            ajaxRequest( 'sqc_delete_checklist', {
                checklist_id: checklistId
            }, function () {
                window.location.href = window.location.pathname + '?page=' + new URLSearchParams( window.location.search ).get( 'page' );
            } );
        } );
    } // End initDeleteChecklist()


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

            updateSidebarPercentOptimistically( checklistId );

            ajaxRequest( 'sqc_set_item_status', {
                checklist_id: checklistId,
                item_id: itemId,
                status: status
            }, function ( response ) {
                if ( response.data && response.data.stats && null !== response.data.stats.percent ) {
                    $( '.sqc-sidebar-item[data-checklist-id="' + checklistId + '"] .sqc-sidebar-item-percent' ).text( response.data.stats.percent + '%' );
                }
            } );
        } );

        $( document ).on( 'click', '.sqc-item-label', function () {
            var item = $( this ).closest( '.sqc-item' );
            var checklistId = item.data( 'checklist-id' );

            if ( editMode[ checklistId ] ) {
                return;
            }

            var checkbox = item.find( '.sqc-item-toggle' );

            if ( checkbox.prop( 'disabled' ) ) {
                return;
            }

            checkbox.prop( 'checked', ! checkbox.prop( 'checked' ) ).trigger( 'change' );
        } );
    } // End initItemToggle()


    /**
     * Immediately update the sidebar percent badge based on visible checkbox states,
     * ahead of the AJAX response, for a snappier feel.
     *
     * @param {string|number} checklistId
     */
    function updateSidebarPercentOptimistically( checklistId ) {
        var panel = $( '.sqc-checklist-panel[data-checklist-id="' + checklistId + '"]' );
        var relevant = panel.find( '.sqc-item' ).not( '.sqc-item-snoozed' );
        var total = relevant.length;

        if ( 0 === total ) {
            return;
        }

        var complete = relevant.filter( '.sqc-item-complete' ).length;
        var percent = Math.round( ( complete / total ) * 100 );

        $( '.sqc-sidebar-item[data-checklist-id="' + checklistId + '"] .sqc-sidebar-item-percent' ).text( percent + '%' );
    } // End updateSidebarPercentOptimistically()


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
            }, function ( response ) {
                item.removeClass( 'sqc-item-incomplete sqc-item-complete' ).addClass( 'sqc-item-snoozed' );
                item.find( '.sqc-item-toggle' ).prop( 'checked', false ).prop( 'disabled', true );
                item.find( '.sqc-snooze-item' ).hide();
                item.find( '.sqc-unsnooze-item' ).show();
                item.find( '.sqc-item-snoozed-badge' ).text( sqcChecklists.i18n.snoozedUntil + ' ' + response.data.snoozed_until ).show();
            } );
        } );

        $( document ).on( 'click', '.sqc-unsnooze-item', function () {
            var item = $( this ).closest( '.sqc-item' );
            var checklistId = item.data( 'checklist-id' );
            var itemId = item.data( 'item-id' );

            ajaxRequest( 'sqc_set_item_status', {
                checklist_id: checklistId,
                item_id: itemId,
                status: 'incomplete'
            }, function () {
                item.removeClass( 'sqc-item-snoozed' ).addClass( 'sqc-item-incomplete' );
                item.find( '.sqc-item-toggle' ).prop( 'disabled', false );
                item.find( '.sqc-unsnooze-item' ).hide();
                item.find( '.sqc-snooze-item' ).show();
                item.find( '.sqc-item-snoozed-badge' ).hide();
            } );
        } );

        $( document ).on( 'click', '.sqc-delete-item', function () {
            if ( ! window.confirm( sqcChecklists.i18n.deleteConfirm ) ) {
                return;
            }

            var item = $( this ).closest( '.sqc-item' );
            var checklistId = item.data( 'checklist-id' );
            var itemId = item.data( 'item-id' );

            ajaxRequest( 'sqc_delete_item', {
                checklist_id: checklistId,
                item_id: itemId
            }, function () {
                item.fadeOut( 200, function () { $( this ).remove(); } );
            } );
        } );

        $( document ).on( 'click', '.sqc-delete-section', function () {
            if ( ! window.confirm( sqcChecklists.i18n.deleteConfirm ) ) {
                return;
            }

            var section = $( this ).closest( '.sqc-section' );
            var checklistId = section.closest( '.sqc-sections' ).data( 'checklist-id' );
            var sectionId = section.data( 'section-id' );

            ajaxRequest( 'sqc_delete_section', {
                checklist_id: checklistId,
                section_id: sectionId
            }, function () {
                section.fadeOut( 200, function () { $( this ).remove(); } );
            } );
        } );
    } // End initItemActions()


    /**
     * Inline label editing: click label while in edit mode to make it editable.
     * Also handles the checklist title itself.
     */
    function initInlineEdit() {
        $( document ).on( 'click', '.sqc-item-label', function () {
            startItemLabelEdit( $( this ) );
        } );

        $( document ).on( 'click', '.sqc-edit-item-label', function () {
            var item = $( this ).closest( '.sqc-item' );
            startItemLabelEdit( item.find( '.sqc-item-label' ) );
        } );

        $( document ).on( 'click', '.sqc-section-label', function () {
            var section = $( this ).closest( '.sqc-section' );
            var checklistId = section.closest( '.sqc-sections' ).data( 'checklist-id' );

            if ( ! editMode[ checklistId ] ) {
                return;
            }

            section.find( '.sqc-show-edit-section' ).trigger( 'click' );
        } );

        $( document ).on( 'click', '.sqc-show-edit-section', function () {
            var section = $( this ).closest( '.sqc-section' );
            section.find( '.sqc-section-header-row' ).hide();
            section.find( '.sqc-edit-section-row' ).show().find( 'input' ).trigger( 'focus' );
        } );

        $( document ).on( 'click', '.sqc-edit-section-confirm', function () {
            var button = $( this );
            var section = $( this ).closest( '.sqc-section' );
            var checklistId = section.closest( '.sqc-sections' ).data( 'checklist-id' );
            var sectionId = section.data( 'section-id' );
            var label = section.find( '.sqc-edit-section-input' ).val().trim();
            var recurrence = section.find( '.sqc-edit-section-recurrence' ).val();

            if ( '' === label ) {
                button.trigger( 'sqc:saved' );
                return;
            }

            ajaxRequest( 'sqc_save_section', {
                checklist_id: checklistId,
                section_id: sectionId,
                label: label,
                recurrence: recurrence
            }, function () {
                section.find( '.sqc-section-label' ).contents().first().replaceWith( label + ' ' );
                section.find( '.sqc-section-recurrence' ).text( '(' + $( '.sqc-edit-section-recurrence option:selected', section ).text() + ')' );
                section.find( '.sqc-edit-section-row' ).hide();
                section.find( '.sqc-section-header-row' ).show();
                button.trigger( 'sqc:saved' );
            } );
        } );

        $( document ).on( 'click', '.sqc-checklist-title', function () {
            var panel = $( this ).closest( '.sqc-checklist-panel' );
            var checklistId = panel.data( 'checklist-id' );

            if ( ! editMode[ checklistId ] ) {
                return;
            }

            var title = $( this );
            var currentText = title.text();

            if ( title.find( 'input' ).length ) {
                return;
            }

            var input = $( '<input type="text" class="sqc-checklist-title-input">' ).val( currentText );

            title.empty().append( input );
            input.trigger( 'focus' ).trigger( 'select' );

            input.on( 'blur keydown', function ( e ) {
                if ( e.type === 'keydown' && e.key !== 'Enter' && e.key !== 'Escape' ) {
                    return;
                }

                var newText = e.key === 'Escape' ? currentText : input.val().trim();

                if ( '' === newText ) {
                    newText = currentText;
                }

                title.text( newText );

                if ( newText !== currentText && e.key !== 'Escape' ) {
                    ajaxRequest( 'sqc_save_checklist', {
                        checklist_id: checklistId,
                        title: newText
                    }, function () {
                        $( '.sqc-sidebar-item[data-checklist-id="' + checklistId + '"] .sqc-sidebar-item-title' ).text( newText );
                        input.trigger( 'sqc:saved' );
                    } );
                } else {
                    input.trigger( 'sqc:saved' );
                }
            } );
        } );
    } // End initInlineEdit()


    /**
     * Turn an item's label into an inline-editable input.
     *
     * @param {jQuery} label
     */
    function startItemLabelEdit( label ) {
        var item = label.closest( '.sqc-item' );
        var checklistId = item.data( 'checklist-id' );

        if ( ! editMode[ checklistId ] ) {
            return;
        }

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
                }, function () {
                    input.trigger( 'sqc:saved' );
                } );
            } else {
                input.trigger( 'sqc:saved' );
            }
        } );
    } // End startItemLabelEdit()


    /**
     * HTML5 drag-drop reordering for items within a section, sections within
     * a checklist, and checklists within the sidebar.
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

            var rect = this.getBoundingClientRect();
            var offset = e.originalEvent.clientY - rect.top;

            if ( offset > rect.height / 2 ) {
                $( this ).after( dragged );
            } else {
                $( this ).before( dragged );
            }
        } );

        $( document ).on( 'dragover', '.sqc-items', function ( e ) {
            if ( ! dragged || ! $( dragged ).hasClass( 'sqc-item' ) ) {
                return;
            }

            e.preventDefault();
            $( this ).addClass( 'sqc-drag-hover' );

            if ( 0 === $( this ).find( '.sqc-item' ).not( dragged ).length ) {
                $( this ).append( dragged );
            }
        } );

        $( document ).on( 'dragleave', '.sqc-items', function () {
            $( this ).removeClass( 'sqc-drag-hover' );
        } );

        $( document ).on( 'drop', '.sqc-items', function ( e ) {
            e.preventDefault();
            $( this ).removeClass( 'sqc-drag-hover' );
        } );

        $( document ).on( 'dragend', '.sqc-item', function ( e ) {
            if ( ! dragged || ! $( dragged ).hasClass( 'sqc-item' ) ) {
                return;
            }

            e.stopPropagation();

            var list = $( dragged ).closest( '.sqc-items' );
            var checklistId = $( dragged ).data( 'checklist-id' );
            var newSectionId = list.data( 'section-id' );
            var itemIds = list.find( '.sqc-item' ).map( function () {
                return $( this ).data( 'item-id' );
            } ).get();

            ajaxRequest( 'sqc_move_item', {
                checklist_id: checklistId,
                item_id: $( dragged ).data( 'item-id' ),
                new_section_id: newSectionId,
                item_ids: itemIds
            } );

            dragged = null;
        } );

        // Sections
        var sectionPlaceholder = $( '<div class="sqc-section-placeholder"></div>' );

        $( document ).on( 'mousedown', '.sqc-section-drag-handle', function () {
            $( this ).closest( '.sqc-section' ).attr( 'draggable', 'true' );
        } );

        $( document ).on( 'mouseup', function () {
            $( '.sqc-section' ).removeAttr( 'draggable' );
        } );

        $( document ).on( 'dragstart', '.sqc-section', function ( e ) {
            var checklistId = $( this ).closest( '.sqc-sections' ).data( 'checklist-id' );

            if ( ! editMode[ checklistId ] ) {
                e.preventDefault();
                return;
            }

            e.stopPropagation();

            dragged = this;
            $( this ).addClass( 'sqc-dragging' );

            sectionPlaceholder.css( 'height', $( this ).outerHeight() + 'px' );
            $( this ).after( sectionPlaceholder );

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
                $( this ).after( sectionPlaceholder );
            } else {
                $( this ).before( sectionPlaceholder );
            }
        } );

        $( document ).on( 'dragend', '.sqc-section', function ( e ) {
            if ( ! dragged || ! $( dragged ).hasClass( 'sqc-section' ) ) {
                return;
            }

            e.stopPropagation();

            $( dragged ).removeClass( 'sqc-dragging' );

            if ( sectionPlaceholder.parent().length ) {
                sectionPlaceholder.replaceWith( dragged );
            }

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

            $( '.sqc-section' ).removeAttr( 'draggable' );
        } );

        // Sidebar checklists
        $( document ).on( 'dragstart', '.sqc-sidebar-item', function ( e ) {
            dragged = this;
            e.originalEvent.dataTransfer.effectAllowed = 'move';
        } );

        $( document ).on( 'dragover', '.sqc-sidebar-item', function ( e ) {
            if ( ! dragged || ! $( dragged ).hasClass( 'sqc-sidebar-item' ) || dragged === this ) {
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

        $( document ).on( 'dragend', '.sqc-sidebar-item', function () {
            if ( ! dragged || ! $( dragged ).hasClass( 'sqc-sidebar-item' ) ) {
                return;
            }

            var checklistIds = $( '#sqc-checklist-list .sqc-sidebar-item' ).map( function () {
                return $( this ).data( 'checklist-id' );
            } ).get();

            ajaxRequest( 'sqc_reorder_checklists', {
                checklist_ids: checklistIds
            } );

            dragged = null;
        } );

        $( document ).on( 'drop', '.sqc-item, .sqc-section, .sqc-sidebar-item', function ( e ) {
            e.preventDefault();
        } );
    } // End initDragDrop()


    /**
     * Add item / add section / add checklist: reveal inline inputs, confirm buttons submit via AJAX.
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
            }, function ( response ) {
                var item = response.data.item;
                var li = buildItemHtml( item, checklistId );

                row.closest( '.sqc-section' ).find( '.sqc-items' ).append( li );

                input.val( '' );
                row.hide();
                row.next( '.sqc-show-add-item' ).show();
            } );
        } );

        $( document ).on( 'click', '.sqc-add-item-cancel', function () {
            var row = $( this ).closest( '.sqc-add-item-row' );
            row.find( '.sqc-add-item-input' ).val( '' );
            row.hide();
            row.next( '.sqc-show-add-item' ).show();
        } );

        $( document ).on( 'click', '#sqc-add-checklist-cancel', function () {
            $( '.sqc-add-checklist-input' ).val( '' );
            $( '#sqc-add-checklist-row' ).hide();
            $( '#sqc-add-checklist' ).show();
        } );

        $( document ).on( 'click', '.sqc-add-section-cancel', function () {
            var row = $( this ).closest( '.sqc-add-section-row' );
            row.find( '.sqc-add-section-input' ).val( '' );
            row.hide();
            row.next( '.sqc-show-add-section' ).show();
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
            var $recurrenceSelect = row.find( '.sqc-add-section-recurrence' );

            if ( '' === label ) {
                return;
            }

            ajaxRequest( 'sqc_add_section', {
                checklist_id: checklistId,
                label: label,
                recurrence: recurrence
            }, function ( response ) {
                var section = buildSectionHtml( response.data.section, checklistId, $recurrenceSelect );
                var sectionsWrap = $( '.sqc-sections[data-checklist-id="' + checklistId + '"]' );

                var inserted = false;
                sectionsWrap.find( '.sqc-section' ).each( function () {
                    if ( $( this ).data( 'recurrence-order' ) > response.data.section.recurrence_order ) {
                        $( this ).before( section );
                        inserted = true;
                        return false;
                    }
                } );

                if ( ! inserted ) {
                    sectionsWrap.append( section );
                }

                row.find( '.sqc-add-section-input' ).val( '' );
                row.hide();
                row.next( '.sqc-show-add-section' ).show();
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
     * Build a new checklist item's HTML, matching render_item()'s PHP markup.
     *
     * @param {Object} item
     * @param {string|number} checklistId
     * @return {jQuery}
     */
    function buildItemHtml( item, checklistId ) {
        var li = $( '<li class="sqc-item sqc-item-incomplete" draggable="true"></li>' )
            .attr( 'data-item-id', item.id )
            .attr( 'data-checklist-id', checklistId );

        li.append( '<span class="sqc-drag-handle" title="Drag to reorder" style="display:inline-block;">::</span>' );
        li.append( '<input type="checkbox" class="sqc-item-toggle" disabled>' );
        li.append( $( '<span class="sqc-item-label"></span>' ).text( item.label ) );
        li.append( '<span class="sqc-item-snoozed-badge" style="display:none;"></span>' );
        li.append(
            '<span class="sqc-item-actions-persistent" style="display:none;">' +
                '<button type="button" class="button-link sqc-snooze-item" title="Remind me later">⏰</button>' +
                '<button type="button" class="button-link sqc-unsnooze-item" title="Unsnooze" style="display:none;">Unsnooze</button>' +
            '</span>'
        );
        li.append(
            '<span class="sqc-item-actions-edit">' +
                '<button type="button" class="button-link sqc-edit-item-label" title="Edit">✎</button>' +
                '<button type="button" class="button-link sqc-delete-item" title="Delete">✕</button>' +
            '</span>'
        );

        return li;
    } // End buildItemHtml()


    /**
     * Build a new section's HTML, matching render_section()'s PHP markup.
     *
     * @param {Object} section
     * @param {string|number} checklistId
     * @return {jQuery}
     */
    function buildSectionHtml( section, checklistId, $recurrenceSelect ) {
        var recurrenceOptions = $recurrenceSelect.find( 'option' ).map( function () {
            return '<option value="' + $( this ).val() + '"' + ( $( this ).val() === section.recurrence ? ' selected' : '' ) + '>' + $( this ).text() + '</option>';
        } ).get().join( '' );

        var recurrenceLabel = $recurrenceSelect.find( 'option[value="' + section.recurrence + '"]' ).text();

        wrap.append(
            '<div class="sqc-section-header-row">' +
                '<span class="sqc-section-drag-handle" title="Drag to reorder">⠿</span>' +
                '<h3 class="sqc-section-label sqc-section-header">' + escapeHtml( section.label ) + ' <span class="sqc-section-recurrence">(' + recurrenceLabel + ')</span></h3>' +
                '<span class="sqc-section-edit-controls">' +
                    '<button type="button" class="button-link sqc-show-edit-section" title="Rename section">✎</button>' +
                    '<button type="button" class="button-link sqc-delete-section" title="Delete section">✕</button>' +
                '</span>' +
            '</div>'
        );

        wrap.append(
            '<div class="sqc-edit-section-row" style="display:none;">' +
                '<input type="text" class="sqc-edit-section-input" value="' + escapeHtml( section.label ) + '">' +
                '<select class="sqc-edit-section-recurrence">' + recurrenceOptions + '</select>' +
                '<button type="button" class="sqc-button sqc-edit-section-confirm">Save</button>' +
            '</div>'
        );

        wrap.append( '<ul class="sqc-items" data-section-id="' + section.id + '"></ul>' );

        wrap.append(
            '<div class="sqc-add-item-row" data-checklist-id="' + checklistId + '" data-section-id="' + section.id + '">' +
                '<input type="text" class="sqc-add-item-input" placeholder="New item…">' +
                '<button type="button" class="sqc-button sqc-add-item-confirm">Add</button>' +
                '<button type="button" class="sqc-button button-secondary sqc-add-item-cancel">Cancel</button>' +
            '</div>'
        );
        wrap.find( '.sqc-add-item-row' ).hide();

        wrap.append( '<button type="button" class="sqc-button sqc-show-add-item" data-checklist-id="' + checklistId + '" data-section-id="' + section.id + '">+ Add Item</button>' );

        return wrap;
    } // End buildSectionHtml()


    /**
     * Minimal HTML-escaping for text inserted via string concatenation.
     *
     * @param {string} text
     * @return {string}
     */
    function escapeHtml( text ) {
        return $( '<div>' ).text( text ).html();
    } // End escapeHtml()


    /**
     * Client-side search: filter sidebar items by checklist title or item label,
     * highlighting matches within the active checklist's items.
     */
    function initSearch() {
        $( '#sqc-checklist-search' ).on( 'input', function () {
            var query = $( this ).val().trim().toLowerCase();
            var firstMatchId = null;

            $( '.sqc-item-label mark' ).each( function () {
                $( this ).replaceWith( $( this ).text() );
            } );

            if ( '' === query ) {
                $( '.sqc-sidebar-item' ).show();
                return;
            }

            $( '.sqc-sidebar-item' ).each( function () {
                var sidebarItem = $( this );
                var checklistId = sidebarItem.data( 'checklist-id' );
                var title = sidebarItem.find( '.sqc-sidebar-item-title' ).text().toLowerCase();
                var panel = $( '.sqc-checklist-panel[data-checklist-id="' + checklistId + '"]' );
                var itemMatches = false;

                panel.find( '.sqc-item-label' ).each( function () {
                    var labelEl = $( this );
                    var text = labelEl.text();

                    if ( text.toLowerCase().indexOf( query ) !== -1 ) {
                        itemMatches = true;

                        var regex = new RegExp( '(' + query.replace( /[.*+?^${}()|[\]\\]/g, '\\$&' ) + ')', 'ig' );
                        labelEl.html( text.replace( regex, '<mark>$1</mark>' ) );
                    }
                } );

                var titleMatches = title.indexOf( query ) !== -1;
                var matches = titleMatches || itemMatches;

                sidebarItem.toggle( matches );

                if ( matches && null === firstMatchId ) {
                    firstMatchId = checklistId;
                }
            } );

            if ( null !== firstMatchId && ! $( '.sqc-sidebar-item[data-checklist-id="' + firstMatchId + '"]' ).hasClass( 'active' ) ) {
                switchToChecklist( firstMatchId );
            }
        } );
    } // End initSearch()


    /**
     * Switch the active checklist panel/sidebar item without a page reload.
     *
     * @param {string|number} checklistId
     */
    function switchToChecklist( checklistId ) {
        $( '.sqc-sidebar-item' ).removeClass( 'active' );
        $( '.sqc-sidebar-item[data-checklist-id="' + checklistId + '"]' ).addClass( 'active' );

        $( '.sqc-checklist-panel' ).hide();
        $( '.sqc-checklist-panel[data-checklist-id="' + checklistId + '"]' ).show();

        if ( history.pushState ) {
            var url = new URL( window.location.href );
            url.searchParams.set( 'checklist', checklistId );
            history.pushState( null, '', url );
        }
    } // End switchToChecklist()


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


    /**
     * Preload default checklists when none exist.
     */
    function initPreloadDefaults() {
        $( document ).on( 'click', '#sqc-preload-defaults', function () {
            ajaxRequest( 'sqc_preload_defaults', {}, function () {
                window.location.reload();
            } );
        } );
    } // End initPreloadDefaults()

} )( jQuery );