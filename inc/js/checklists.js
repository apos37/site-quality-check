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
        $( '#sqcheck-checklist-list' ).on( 'click', '.sqcheck-sidebar-item a', function ( e ) {
            e.preventDefault();
            switchToChecklist( $( this ).data( 'checklist-id' ) );
        } );
    } // End initSidebarSwitching()


    /**
     * Toggle edit mode for a checklist panel, revealing add-item/add-section/delete controls and drag handles.
     */
    function initEditToggle() {
        $( document ).on( 'click', '.sqcheck-edit-checklist-toggle', function () {
            var checklistId = $( this ).data( 'checklist-id' );
            var panel = $( '.sqcheck-checklist-panel[data-checklist-id="' + checklistId + '"]' );
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

        panel.toggleClass( 'sqcheck-editing', isEditing );
        panel.find( '.sqcheck-show-add-section, .sqcheck-show-add-item, .sqcheck-checklist-danger-zone, .sqcheck-section-edit-controls, .sqcheck-section-drag-handle, .sqcheck-item-actions-edit' ).toggle( isEditing );
        panel.find( '.sqcheck-item-actions-persistent' ).toggle( ! isEditing );
        panel.find( '.sqcheck-item-toggle' ).toggle( ! isEditing );

        panel.find( '.sqcheck-item-toggle' ).each( function () {
            var isSnoozed = $( this ).closest( '.sqcheck-item' ).hasClass( 'sqcheck-item-snoozed' );
            $( this ).prop( 'disabled', isEditing || isSnoozed );
        } );

        button.text( isEditing ? sqcheckChecklists.i18n.done : sqcheckChecklists.i18n.edit );
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

        panel.find( '.sqcheck-item-label-input' ).each( function () {
            pending.push( waitForBlurSave( $( this ) ) );
        } );

        panel.find( '.sqcheck-checklist-title-input' ).each( function () {
            pending.push( waitForBlurSave( $( this ) ) );
        } );

        panel.find( '.sqcheck-edit-section-row:visible' ).each( function () {
            var row = $( this );
            pending.push( waitForAjaxClick( row.find( '.sqcheck-edit-section-confirm' ) ) );
        } );

        panel.find( '.sqcheck-add-item-row:visible' ).each( function () {
            $( this ).find( '.sqcheck-add-item-cancel' ).trigger( 'click' );
        } );

        panel.find( '.sqcheck-add-section-row:visible' ).each( function () {
            $( this ).find( '.sqcheck-add-section-cancel' ).trigger( 'click' );
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
        $( document ).on( 'click', '.sqcheck-delete-checklist', function () {
            if ( ! window.confirm( sqcheckChecklists.i18n.deleteConfirm ) ) {
                return;
            }

            var checklistId = $( this ).data( 'checklist-id' );

            ajaxRequest( 'sqcheck_delete_checklist', {
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
        $( document ).on( 'change', '.sqcheck-item-toggle', function () {
            var item = $( this ).closest( '.sqcheck-item' );
            var checklistId = item.data( 'checklist-id' );
            var itemId = item.data( 'item-id' );
            var status = $( this ).is( ':checked' ) ? 'complete' : 'incomplete';

            item.removeClass( 'sqcheck-item-complete sqcheck-item-incomplete' ).addClass( 'sqcheck-item-' + status );

            updateSidebarPercentOptimistically( checklistId );

            ajaxRequest( 'sqcheck_set_item_status', {
                checklist_id: checklistId,
                item_id: itemId,
                status: status
            }, function ( response ) {
                if ( response.data && response.data.stats && null !== response.data.stats.percent ) {
                    $( '.sqcheck-sidebar-item[data-checklist-id="' + checklistId + '"] .sqcheck-sidebar-item-percent' ).text( response.data.stats.percent + '%' );
                }
            } );
        } );

        $( document ).on( 'click', '.sqcheck-item-label', function () {
            var item = $( this ).closest( '.sqcheck-item' );
            var checklistId = item.data( 'checklist-id' );

            if ( editMode[ checklistId ] ) {
                return;
            }

            var checkbox = item.find( '.sqcheck-item-toggle' );

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
        var panel = $( '.sqcheck-checklist-panel[data-checklist-id="' + checklistId + '"]' );
        var relevant = panel.find( '.sqcheck-item' ).not( '.sqcheck-item-snoozed' );
        var total = relevant.length;

        if ( 0 === total ) {
            return;
        }

        var complete = relevant.filter( '.sqcheck-item-complete' ).length;
        var percent = Math.round( ( complete / total ) * 100 );

        $( '.sqcheck-sidebar-item[data-checklist-id="' + checklistId + '"] .sqcheck-sidebar-item-percent' ).text( percent + '%' );
    } // End updateSidebarPercentOptimistically()


    /**
     * Snooze and omit buttons.
     */
    function initItemActions() {
        $( document ).on( 'click', '.sqcheck-snooze-item', function () {
            var item = $( this ).closest( '.sqcheck-item' );
            var checklistId = item.data( 'checklist-id' );
            var itemId = item.data( 'item-id' );

            ajaxRequest( 'sqcheck_set_item_status', {
                checklist_id: checklistId,
                item_id: itemId,
                status: 'snoozed'
            }, function ( response ) {
                item.removeClass( 'sqcheck-item-incomplete sqcheck-item-complete' ).addClass( 'sqcheck-item-snoozed' );
                item.find( '.sqcheck-item-toggle' ).prop( 'checked', false ).prop( 'disabled', true );
                item.find( '.sqcheck-snooze-item' ).hide();
                item.find( '.sqcheck-unsnooze-item' ).show();
                item.find( '.sqcheck-item-snoozed-badge' ).text( sqcheckChecklists.i18n.snoozedUntil + ' ' + response.data.snoozed_until ).show();
            } );
        } );

        $( document ).on( 'click', '.sqcheck-unsnooze-item', function () {
            var item = $( this ).closest( '.sqcheck-item' );
            var checklistId = item.data( 'checklist-id' );
            var itemId = item.data( 'item-id' );

            ajaxRequest( 'sqcheck_set_item_status', {
                checklist_id: checklistId,
                item_id: itemId,
                status: 'incomplete'
            }, function () {
                item.removeClass( 'sqcheck-item-snoozed' ).addClass( 'sqcheck-item-incomplete' );
                item.find( '.sqcheck-item-toggle' ).prop( 'disabled', false );
                item.find( '.sqcheck-unsnooze-item' ).hide();
                item.find( '.sqcheck-snooze-item' ).show();
                item.find( '.sqcheck-item-snoozed-badge' ).hide();
            } );
        } );

        $( document ).on( 'click', '.sqcheck-delete-item', function () {
            if ( ! window.confirm( sqcheckChecklists.i18n.deleteConfirm ) ) {
                return;
            }

            var item = $( this ).closest( '.sqcheck-item' );
            var checklistId = item.data( 'checklist-id' );
            var itemId = item.data( 'item-id' );

            ajaxRequest( 'sqcheck_delete_item', {
                checklist_id: checklistId,
                item_id: itemId
            }, function () {
                item.fadeOut( 200, function () { $( this ).remove(); } );
            } );
        } );

        $( document ).on( 'click', '.sqcheck-delete-section', function () {
            if ( ! window.confirm( sqcheckChecklists.i18n.deleteConfirm ) ) {
                return;
            }

            var section = $( this ).closest( '.sqcheck-section' );
            var checklistId = section.closest( '.sqcheck-sections' ).data( 'checklist-id' );
            var sectionId = section.data( 'section-id' );

            ajaxRequest( 'sqcheck_delete_section', {
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
        $( document ).on( 'click', '.sqcheck-item-label', function () {
            startItemLabelEdit( $( this ) );
        } );

        $( document ).on( 'click', '.sqcheck-edit-item-label', function () {
            var item = $( this ).closest( '.sqcheck-item' );
            startItemLabelEdit( item.find( '.sqcheck-item-label' ) );
        } );

        $( document ).on( 'click', '.sqcheck-section-label', function () {
            var section = $( this ).closest( '.sqcheck-section' );
            var checklistId = section.closest( '.sqcheck-sections' ).data( 'checklist-id' );

            if ( ! editMode[ checklistId ] ) {
                return;
            }

            section.find( '.sqcheck-show-edit-section' ).trigger( 'click' );
        } );

        $( document ).on( 'click', '.sqcheck-show-edit-section', function () {
            var section = $( this ).closest( '.sqcheck-section' );
            section.find( '.sqcheck-section-header-row' ).hide();
            section.find( '.sqcheck-edit-section-row' ).show().find( 'input' ).trigger( 'focus' );
        } );

        $( document ).on( 'click', '.sqcheck-edit-section-confirm', function () {
            var button = $( this );
            var section = $( this ).closest( '.sqcheck-section' );
            var checklistId = section.closest( '.sqcheck-sections' ).data( 'checklist-id' );
            var sectionId = section.data( 'section-id' );
            var label = section.find( '.sqcheck-edit-section-input' ).val().trim();
            var recurrence = section.find( '.sqcheck-edit-section-recurrence' ).val();

            if ( '' === label ) {
                button.trigger( 'sqc:saved' );
                return;
            }

            ajaxRequest( 'sqcheck_save_section', {
                checklist_id: checklistId,
                section_id: sectionId,
                label: label,
                recurrence: recurrence
            }, function () {
                section.find( '.sqcheck-section-label' ).contents().first().replaceWith( label + ' ' );
                section.find( '.sqcheck-section-recurrence' ).text( '(' + $( '.sqcheck-edit-section-recurrence option:selected', section ).text() + ')' );
                section.find( '.sqcheck-edit-section-row' ).hide();
                section.find( '.sqcheck-section-header-row' ).show();
                button.trigger( 'sqc:saved' );
            } );
        } );

        $( document ).on( 'click', '.sqcheck-checklist-title', function () {
            var panel = $( this ).closest( '.sqcheck-checklist-panel' );
            var checklistId = panel.data( 'checklist-id' );

            if ( ! editMode[ checklistId ] ) {
                return;
            }

            var title = $( this );
            var currentText = title.text();

            if ( title.find( 'input' ).length ) {
                return;
            }

            var input = $( '<input type="text" class="sqcheck-checklist-title-input">' ).val( currentText );

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
                    ajaxRequest( 'sqcheck_save_checklist', {
                        checklist_id: checklistId,
                        title: newText
                    }, function () {
                        $( '.sqcheck-sidebar-item[data-checklist-id="' + checklistId + '"] .sqcheck-sidebar-item-title' ).text( newText );
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
        var item = label.closest( '.sqcheck-item' );
        var checklistId = item.data( 'checklist-id' );

        if ( ! editMode[ checklistId ] ) {
            return;
        }

        var currentText = label.text();

        if ( label.find( 'input' ).length ) {
            return;
        }

        var input = $( '<input type="text" class="sqcheck-item-label-input">' ).val( currentText );

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

                ajaxRequest( 'sqcheck_save_item_label', {
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
        $( document ).on( 'dragstart', '.sqcheck-item', function ( e ) {
            var checklistId = $( this ).data( 'checklist-id' );

            if ( ! editMode[ checklistId ] ) {
                e.preventDefault();
                return;
            }

            e.stopPropagation();
            dragged = this;
            e.originalEvent.dataTransfer.effectAllowed = 'move';
        } );

        $( document ).on( 'dragover', '.sqcheck-item', function ( e ) {
            if ( ! dragged || ! $( dragged ).hasClass( 'sqcheck-item' ) || dragged === this ) {
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

        $( document ).on( 'dragover', '.sqcheck-items', function ( e ) {
            if ( ! dragged || ! $( dragged ).hasClass( 'sqcheck-item' ) ) {
                return;
            }

            e.preventDefault();
            $( this ).addClass( 'sqcheck-drag-hover' );

            if ( 0 === $( this ).find( '.sqcheck-item' ).not( dragged ).length ) {
                $( this ).append( dragged );
            }
        } );

        $( document ).on( 'dragleave', '.sqcheck-items', function () {
            $( this ).removeClass( 'sqcheck-drag-hover' );
        } );

        $( document ).on( 'drop', '.sqcheck-items', function ( e ) {
            e.preventDefault();
            $( this ).removeClass( 'sqcheck-drag-hover' );
        } );

        $( document ).on( 'dragend', '.sqcheck-item', function ( e ) {
            if ( ! dragged || ! $( dragged ).hasClass( 'sqcheck-item' ) ) {
                return;
            }

            e.stopPropagation();

            var list = $( dragged ).closest( '.sqcheck-items' );
            var checklistId = $( dragged ).data( 'checklist-id' );
            var newSectionId = list.data( 'section-id' );
            var itemIds = list.find( '.sqcheck-item' ).map( function () {
                return $( this ).data( 'item-id' );
            } ).get();

            ajaxRequest( 'sqcheck_move_item', {
                checklist_id: checklistId,
                item_id: $( dragged ).data( 'item-id' ),
                new_section_id: newSectionId,
                item_ids: itemIds
            } );

            dragged = null;
        } );

        // Sections
        var sectionPlaceholder = $( '<div class="sqcheck-section-placeholder"></div>' );

        $( document ).on( 'mousedown', '.sqcheck-section-drag-handle', function () {
            $( this ).closest( '.sqcheck-section' ).attr( 'draggable', 'true' );
        } );

        $( document ).on( 'mouseup', function () {
            $( '.sqcheck-section' ).removeAttr( 'draggable' );
        } );

        $( document ).on( 'dragstart', '.sqcheck-section', function ( e ) {
            var checklistId = $( this ).closest( '.sqcheck-sections' ).data( 'checklist-id' );

            if ( ! editMode[ checklistId ] ) {
                e.preventDefault();
                return;
            }

            e.stopPropagation();

            dragged = this;
            $( this ).addClass( 'sqcheck-dragging' );

            sectionPlaceholder.css( 'height', $( this ).outerHeight() + 'px' );
            $( this ).after( sectionPlaceholder );

            e.originalEvent.dataTransfer.effectAllowed = 'move';
        } );

        $( document ).on( 'dragover', '.sqcheck-section', function ( e ) {
            if ( ! dragged || ! $( dragged ).hasClass( 'sqcheck-section' ) || dragged === this ) {
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

        $( document ).on( 'dragend', '.sqcheck-section', function ( e ) {
            if ( ! dragged || ! $( dragged ).hasClass( 'sqcheck-section' ) ) {
                return;
            }

            e.stopPropagation();

            $( dragged ).removeClass( 'sqcheck-dragging' );

            if ( sectionPlaceholder.parent().length ) {
                sectionPlaceholder.replaceWith( dragged );
            }

            var wrapper = $( dragged ).closest( '.sqcheck-sections' );
            var checklistId = wrapper.data( 'checklist-id' );
            var sectionIds = wrapper.find( '.sqcheck-section' ).map( function () {
                return $( this ).data( 'section-id' );
            } ).get();

            ajaxRequest( 'sqcheck_reorder_sections', {
                checklist_id: checklistId,
                section_ids: sectionIds
            } );

            dragged = null;

            $( '.sqcheck-section' ).removeAttr( 'draggable' );
        } );

        // Sidebar checklists
        $( document ).on( 'dragstart', '.sqcheck-sidebar-item', function ( e ) {
            dragged = this;
            e.originalEvent.dataTransfer.effectAllowed = 'move';
        } );

        $( document ).on( 'dragover', '.sqcheck-sidebar-item', function ( e ) {
            if ( ! dragged || ! $( dragged ).hasClass( 'sqcheck-sidebar-item' ) || dragged === this ) {
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

        $( document ).on( 'dragend', '.sqcheck-sidebar-item', function () {
            if ( ! dragged || ! $( dragged ).hasClass( 'sqcheck-sidebar-item' ) ) {
                return;
            }

            var checklistIds = $( '#sqcheck-checklist-list .sqcheck-sidebar-item' ).map( function () {
                return $( this ).data( 'checklist-id' );
            } ).get();

            ajaxRequest( 'sqcheck_reorder_checklists', {
                checklist_ids: checklistIds
            } );

            dragged = null;
        } );

        $( document ).on( 'drop', '.sqcheck-item, .sqcheck-section, .sqcheck-sidebar-item', function ( e ) {
            e.preventDefault();
        } );
    } // End initDragDrop()


    /**
     * Add item / add section / add checklist: reveal inline inputs, confirm buttons submit via AJAX.
     */
    function initAddButtons() {
        $( document ).on( 'click', '.sqcheck-show-add-item', function () {
            $( this ).hide().prev( '.sqcheck-add-item-row' ).show().find( 'input' ).trigger( 'focus' );
        } );

        $( document ).on( 'click', '.sqcheck-add-item-confirm', function () {
            var row = $( this ).closest( '.sqcheck-add-item-row' );
            var checklistId = row.data( 'checklist-id' );
            var sectionId = row.data( 'section-id' );
            var input = row.find( '.sqcheck-add-item-input' );
            var label = input.val().trim();

            if ( '' === label ) {
                return;
            }

            ajaxRequest( 'sqcheck_add_item', {
                checklist_id: checklistId,
                section_id: sectionId,
                label: label
            }, function ( response ) {
                var item = response.data.item;
                var li = buildItemHtml( item, checklistId );

                row.closest( '.sqcheck-section' ).find( '.sqcheck-items' ).append( li );

                input.val( '' );
                row.hide();
                row.next( '.sqcheck-show-add-item' ).show();
            } );
        } );

        $( document ).on( 'click', '.sqcheck-add-item-cancel', function () {
            var row = $( this ).closest( '.sqcheck-add-item-row' );
            row.find( '.sqcheck-add-item-input' ).val( '' );
            row.hide();
            row.next( '.sqcheck-show-add-item' ).show();
        } );

        $( document ).on( 'click', '#sqcheck-add-checklist-cancel', function () {
            $( '.sqcheck-add-checklist-input' ).val( '' );
            $( '#sqcheck-add-checklist-row' ).hide();
            $( '#sqcheck-add-checklist' ).show();
        } );

        $( document ).on( 'click', '.sqcheck-add-section-cancel', function () {
            var row = $( this ).closest( '.sqcheck-add-section-row' );
            row.find( '.sqcheck-add-section-input' ).val( '' );
            row.hide();
            row.next( '.sqcheck-show-add-section' ).show();
        } );

        $( document ).on( 'keydown', '.sqcheck-add-item-input', function ( e ) {
            if ( e.key === 'Enter' ) {
                $( this ).siblings( '.sqcheck-add-item-confirm' ).trigger( 'click' );
            }
        } );

        $( document ).on( 'click', '.sqcheck-show-add-section', function () {
            $( this ).hide().prev( '.sqcheck-add-section-row' ).show().find( 'input' ).trigger( 'focus' );
        } );

        $( document ).on( 'click', '.sqcheck-add-section-confirm', function () {
            var row = $( this ).closest( '.sqcheck-add-section-row' );
            var checklistId = row.data( 'checklist-id' );
            var label = row.find( '.sqcheck-add-section-input' ).val().trim();
            var recurrence = row.find( '.sqcheck-add-section-recurrence' ).val();
            var $recurrenceSelect = row.find( '.sqcheck-add-section-recurrence' );

            if ( '' === label ) {
                return;
            }

            ajaxRequest( 'sqcheck_add_section', {
                checklist_id: checklistId,
                label: label,
                recurrence: recurrence
            }, function ( response ) {
                var section = buildSectionHtml( response.data.section, checklistId, $recurrenceSelect );
                var sectionsWrap = $( '.sqcheck-sections[data-checklist-id="' + checklistId + '"]' );

                var inserted = false;
                sectionsWrap.find( '.sqcheck-section' ).each( function () {
                    if ( $( this ).data( 'recurrence-order' ) > response.data.section.recurrence_order ) {
                        $( this ).before( section );
                        inserted = true;
                        return false;
                    }
                } );

                if ( ! inserted ) {
                    sectionsWrap.append( section );
                }

                row.find( '.sqcheck-add-section-input' ).val( '' );
                row.hide();
                row.next( '.sqcheck-show-add-section' ).show();
            } );
        } );

        $( document ).on( 'keydown', '.sqcheck-add-section-input', function ( e ) {
            if ( e.key === 'Enter' ) {
                $( this ).closest( '.sqcheck-add-section-row' ).find( '.sqcheck-add-section-confirm' ).trigger( 'click' );
            }
        } );

        $( '#sqcheck-add-checklist' ).on( 'click', function () {
            $( this ).hide().prev( '#sqcheck-add-checklist-row' ).show().find( 'input' ).trigger( 'focus' );
        } );

        $( document ).on( 'click', '#sqcheck-add-checklist-confirm', function () {
            var title = $( '.sqcheck-add-checklist-input' ).val().trim();

            if ( '' === title ) {
                return;
            }

            ajaxRequest( 'sqcheck_add_checklist', {
                title: title
            }, function () {
                window.location.reload();
            } );
        } );

        $( document ).on( 'keydown', '.sqcheck-add-checklist-input', function ( e ) {
            if ( e.key === 'Enter' ) {
                $( '#sqcheck-add-checklist-confirm' ).trigger( 'click' );
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
        var li = $( '<li class="sqcheck-item sqcheck-item-incomplete" draggable="true"></li>' )
            .attr( 'data-item-id', item.id )
            .attr( 'data-checklist-id', checklistId );

        li.append( '<span class="sqcheck-drag-handle" title="Drag to reorder" style="display:inline-block;">::</span>' );
        li.append( '<input type="checkbox" class="sqcheck-item-toggle" disabled>' );
        li.append( $( '<span class="sqcheck-item-label"></span>' ).text( item.label ) );
        li.append( '<span class="sqcheck-item-snoozed-badge" style="display:none;"></span>' );
        li.append(
            '<span class="sqcheck-item-actions-persistent" style="display:none;">' +
                '<button type="button" class="button-link sqcheck-snooze-item" title="Remind me later">⏰</button>' +
                '<button type="button" class="button-link sqcheck-unsnooze-item" title="Unsnooze" style="display:none;">Unsnooze</button>' +
            '</span>'
        );
        li.append(
            '<span class="sqcheck-item-actions-edit">' +
                '<button type="button" class="button-link sqcheck-edit-item-label" title="Edit">✎</button>' +
                '<button type="button" class="button-link sqcheck-delete-item" title="Delete">✕</button>' +
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
            '<div class="sqcheck-section-header-row">' +
                '<span class="sqcheck-section-drag-handle" title="Drag to reorder">⠿</span>' +
                '<h3 class="sqcheck-section-label sqcheck-section-header">' + escapeHtml( section.label ) + ' <span class="sqcheck-section-recurrence">(' + recurrenceLabel + ')</span></h3>' +
                '<span class="sqcheck-section-edit-controls">' +
                    '<button type="button" class="button-link sqcheck-show-edit-section" title="Rename section">✎</button>' +
                    '<button type="button" class="button-link sqcheck-delete-section" title="Delete section">✕</button>' +
                '</span>' +
            '</div>'
        );

        wrap.append(
            '<div class="sqcheck-edit-section-row" style="display:none;">' +
                '<input type="text" class="sqcheck-edit-section-input" value="' + escapeHtml( section.label ) + '">' +
                '<select class="sqcheck-edit-section-recurrence">' + recurrenceOptions + '</select>' +
                '<button type="button" class="sqcheck-button sqcheck-edit-section-confirm">Save</button>' +
            '</div>'
        );

        wrap.append( '<ul class="sqcheck-items" data-section-id="' + section.id + '"></ul>' );

        wrap.append(
            '<div class="sqcheck-add-item-row" data-checklist-id="' + checklistId + '" data-section-id="' + section.id + '">' +
                '<input type="text" class="sqcheck-add-item-input" placeholder="New item…">' +
                '<button type="button" class="sqcheck-button sqcheck-add-item-confirm">Add</button>' +
                '<button type="button" class="sqcheck-button button-secondary sqcheck-add-item-cancel">Cancel</button>' +
            '</div>'
        );
        wrap.find( '.sqcheck-add-item-row' ).hide();

        wrap.append( '<button type="button" class="sqcheck-button sqcheck-show-add-item" data-checklist-id="' + checklistId + '" data-section-id="' + section.id + '">+ Add Item</button>' );

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
        $( '#sqcheck-checklist-search' ).on( 'input', function () {
            var query = $( this ).val().trim().toLowerCase();
            var firstMatchId = null;

            $( '.sqcheck-item-label mark' ).each( function () {
                $( this ).replaceWith( $( this ).text() );
            } );

            if ( '' === query ) {
                $( '.sqcheck-sidebar-item' ).show();
                return;
            }

            $( '.sqcheck-sidebar-item' ).each( function () {
                var sidebarItem = $( this );
                var checklistId = sidebarItem.data( 'checklist-id' );
                var title = sidebarItem.find( '.sqcheck-sidebar-item-title' ).text().toLowerCase();
                var panel = $( '.sqcheck-checklist-panel[data-checklist-id="' + checklistId + '"]' );
                var itemMatches = false;

                panel.find( '.sqcheck-item-label' ).each( function () {
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

            if ( null !== firstMatchId && ! $( '.sqcheck-sidebar-item[data-checklist-id="' + firstMatchId + '"]' ).hasClass( 'active' ) ) {
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
        $( '.sqcheck-sidebar-item' ).removeClass( 'active' );
        $( '.sqcheck-sidebar-item[data-checklist-id="' + checklistId + '"]' ).addClass( 'active' );

        $( '.sqcheck-checklist-panel' ).hide();
        $( '.sqcheck-checklist-panel[data-checklist-id="' + checklistId + '"]' ).show();

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
        $.post( sqcheckChecklists.ajaxUrl, $.extend( {
            action: action,
            nonce: sqcheckChecklists.nonce
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
        $( document ).on( 'click', '#sqcheck-preload-defaults', function () {
            ajaxRequest( 'sqcheck_preload_defaults', {}, function () {
                window.location.reload();
            } );
        } );
    } // End initPreloadDefaults()

} )( jQuery );