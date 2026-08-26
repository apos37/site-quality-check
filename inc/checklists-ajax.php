<?php
/**
 * CHECKLISTS AJAX
 *
 * Handles AJAX requests for checklist/section/item CRUD, drag-drop reorder,
 * inline editing, and status toggling.
 */

namespace PluginRx\SiteQualityCheck;

if ( ! defined( 'ABSPATH' ) ) exit;


class ChecklistsAjax {

    /**
     * Nonce action for all checklist AJAX requests.
     */
    public const NONCE_ACTION = 'sqcheck_checklists_nonce';


    /**
     * @var ChecklistsAjax|null Singleton instance
     */
    private static ?ChecklistsAjax $instance = null;


    /**
     * Get instance
     *
     * @return self
     */
    public static function instance() : self {
        return self::$instance ??= new self();
    } // End instance()


    /**
     * Constructor
     */
    private function __construct() {
        add_action( 'wp_ajax_sqcheck_set_item_status', [ $this, 'set_item_status' ] );
        add_action( 'wp_ajax_sqcheck_save_item_label', [ $this, 'save_item_label' ] );
        add_action( 'wp_ajax_sqcheck_add_item', [ $this, 'add_item' ] );
        add_action( 'wp_ajax_sqcheck_delete_item', [ $this, 'delete_item' ] );
        add_action( 'wp_ajax_sqcheck_move_item', [ $this, 'move_item' ] );
        add_action( 'wp_ajax_sqcheck_add_section', [ $this, 'add_section' ] );
        add_action( 'wp_ajax_sqcheck_save_section', [ $this, 'save_section' ] );
        add_action( 'wp_ajax_sqcheck_delete_section', [ $this, 'delete_section' ] );
        add_action( 'wp_ajax_sqcheck_reorder_sections', [ $this, 'reorder_sections' ] );
        add_action( 'wp_ajax_sqcheck_add_checklist', [ $this, 'add_checklist' ] );
        add_action( 'wp_ajax_sqcheck_save_checklist', [ $this, 'save_checklist' ] );
        add_action( 'wp_ajax_sqcheck_delete_checklist', [ $this, 'delete_checklist' ] );
        add_action( 'wp_ajax_sqcheck_reorder_checklists', [ $this, 'reorder_checklists' ] );
    } // End __construct()


    /**
     * Verify nonce and access, die with JSON error on failure.
     * Always called as the first line of every handler below, before any
     * $_POST value is read.
     *
     * @return void
     */
    private function guard() : void {
        check_ajax_referer( self::NONCE_ACTION, 'nonce' );

        if ( ! Access::can_access() ) {
            wp_send_json_error( [ 'message' => __( 'You do not have permission to do this.', 'site-quality-check' ) ], 403 );
        }
    } // End guard()


    /**
     * Toggle/set an item's status (complete, incomplete, snoozed, omitted).
     *
     * @return void
     */
    public function set_item_status() : void {
        $this->guard();

        // phpcs:disable WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- verified via guard() above.
        $checklist_id = (int) wp_unslash( $_POST[ 'checklist_id' ] ?? 0 );
        $item_id = sanitize_text_field( wp_unslash( $_POST[ 'item_id' ] ?? '' ) );
        $status = sanitize_text_field( wp_unslash( $_POST[ 'status' ] ?? '' ) );
        // phpcs:enable

        if ( ! in_array( $status, [ 'complete', 'incomplete', 'snoozed' ], true ) ) {
            wp_send_json_error( [ 'message' => __( 'Invalid status.', 'site-quality-check' ) ] );
        }

        $saved = Checklists::set_item_status( $checklist_id, $item_id, $status );

        if ( ! $saved ) {
            wp_send_json_error( [ 'message' => __( 'Item not found.', 'site-quality-check' ) ] );
        }

        $sections = Checklists::get_sections( $checklist_id );
        $location = Checklists::find_item( $sections, $item_id );
        $snoozed_until = $location ? Helpers::format_date( $location[ 'item' ][ 'snoozed_until' ] ?? null ) : '';

        wp_send_json_success( [
            'stats'         => Checklists::get_completion_stats( $checklist_id ),
            'snoozed_until' => $snoozed_until,
        ] );
    } // End set_item_status()


    /**
     * Save an inline-edited item label.
     *
     * @return void
     */
    public function save_item_label() : void {
        $this->guard();

        // phpcs:disable WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- verified via guard() above.
        $checklist_id = (int) wp_unslash( $_POST[ 'checklist_id' ] ?? 0 );
        $item_id = sanitize_text_field( wp_unslash( $_POST[ 'item_id' ] ?? '' ) );
        $label = sanitize_text_field( wp_unslash( $_POST[ 'label' ] ?? '' ) );
        // phpcs:enable

        $sections = Checklists::get_sections( $checklist_id );
        $location = Checklists::find_item( $sections, $item_id );

        if ( ! $location ) {
            wp_send_json_error( [ 'message' => __( 'Item not found.', 'site-quality-check' ) ] );
        }

        $sections[ $location[ 'section_index' ] ][ 'items' ][ $location[ 'item_index' ] ][ 'label' ] = $label;

        Checklists::save_sections( $checklist_id, $sections );
        Checklists::touch( $checklist_id );

        wp_send_json_success();
    } // End save_item_label()


    /**
     * Add a new item to a section.
     *
     * @return void
     */
    public function add_item() : void {
        $this->guard();

        // phpcs:disable WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- verified via guard() above.
        $checklist_id = (int) wp_unslash( $_POST[ 'checklist_id' ] ?? 0 );
        $section_id = sanitize_text_field( wp_unslash( $_POST[ 'section_id' ] ?? '' ) );
        $label = sanitize_text_field( wp_unslash( $_POST[ 'label' ] ?? '' ) );
        // phpcs:enable

        $sections = Checklists::get_sections( $checklist_id );
        $target_index = null;

        foreach ( $sections as $index => $section ) {
            if ( ( $section[ 'id' ] ?? '' ) === $section_id ) {
                $target_index = $index;
                break;
            }
        }

        if ( null === $target_index ) {
            wp_send_json_error( [ 'message' => __( 'Section not found.', 'site-quality-check' ) ] );
        }

        $new_item = [
            'id'             => Helpers::generate_id( 'item' ),
            'label'          => $label,
            'order'          => count( $sections[ $target_index ][ 'items' ] ?? [] ),
            'status'         => 'incomplete',
            'last_completed' => null,
            'snoozed_until'  => null,
        ];

        $sections[ $target_index ][ 'items' ][] = $new_item;

        Checklists::save_sections( $checklist_id, $sections );
        Checklists::touch( $checklist_id );

        wp_send_json_success( [ 'item' => $new_item ] );
    } // End add_item()


    /**
     * Delete an item from a checklist.
     *
     * @return void
     */
    public function delete_item() : void {
        $this->guard();

        // phpcs:disable WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- verified via guard() above.
        $checklist_id = (int) wp_unslash( $_POST[ 'checklist_id' ] ?? 0 );
        $item_id = sanitize_text_field( wp_unslash( $_POST[ 'item_id' ] ?? '' ) );
        // phpcs:enable

        $sections = Checklists::get_sections( $checklist_id );
        $location = Checklists::find_item( $sections, $item_id );

        if ( ! $location ) {
            wp_send_json_error( [ 'message' => __( 'Item not found.', 'site-quality-check' ) ] );
        }

        array_splice( $sections[ $location[ 'section_index' ] ][ 'items' ], $location[ 'item_index' ], 1 );

        Checklists::save_sections( $checklist_id, $sections );
        Checklists::touch( $checklist_id );

        wp_send_json_success();
    } // End delete_item()


    /**
     * Move an item to a (possibly different) section and set its position there.
     *
     * @return void
     */
    public function move_item() : void {
        $this->guard();

        // phpcs:disable WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- verified via guard() above.
        $checklist_id = (int) wp_unslash( $_POST[ 'checklist_id' ] ?? 0 );
        $item_id = sanitize_text_field( wp_unslash( $_POST[ 'item_id' ] ?? '' ) );
        $new_section_id = sanitize_text_field( wp_unslash( $_POST[ 'new_section_id' ] ?? '' ) );
        $item_ids = array_map( 'sanitize_text_field', wp_unslash( (array) ( $_POST[ 'item_ids' ] ?? [] ) ) );
        // phpcs:enable

        $sections = Checklists::get_sections( $checklist_id );
        $location = Checklists::find_item( $sections, $item_id );

        if ( ! $location ) {
            wp_send_json_error( [ 'message' => __( 'Item not found.', 'site-quality-check' ) ] );
        }

        $item = $sections[ $location[ 'section_index' ] ][ 'items' ][ $location[ 'item_index' ] ];

        array_splice( $sections[ $location[ 'section_index' ] ][ 'items' ], $location[ 'item_index' ], 1 );

        foreach ( $sections as $index => $section ) {
            if ( ( $section[ 'id' ] ?? '' ) === $new_section_id ) {
                $items_by_id = [];

                foreach ( $section[ 'items' ] as $existing ) {
                    $items_by_id[ $existing[ 'id' ] ] = $existing;
                }

                $items_by_id[ $item[ 'id' ] ] = $item;

                $reordered = [];
                foreach ( $item_ids as $order => $id ) {
                    if ( isset( $items_by_id[ $id ] ) ) {
                        $items_by_id[ $id ][ 'order' ] = $order;
                        $reordered[] = $items_by_id[ $id ];
                    }
                }

                $sections[ $index ][ 'items' ] = $reordered;
                break;
            }
        }

        Checklists::save_sections( $checklist_id, $sections );
        Checklists::touch( $checklist_id );

        wp_send_json_success();
    } // End move_item()


    /**
     * Add a new section to a checklist.
     *
     * @return void
     */
    public function add_section() : void {
        $this->guard();

        // phpcs:disable WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- verified via guard() above.
        $checklist_id = (int) wp_unslash( $_POST[ 'checklist_id' ] ?? 0 );
        $label = sanitize_text_field( wp_unslash( $_POST[ 'label' ] ?? '' ) );
        $recurrence = sanitize_text_field( wp_unslash( $_POST[ 'recurrence' ] ?? 'daily' ) );
        // phpcs:enable

        if ( ! array_key_exists( $recurrence, Checklists::RECURRENCE_INTERVALS ) ) {
            $recurrence = 'daily';
        }

        $sections = Checklists::get_sections( $checklist_id );

        $new_section = [
            'id'         => Helpers::generate_id( 'section' ),
            'label'      => $label,
            'recurrence' => $recurrence,
            'order'      => count( $sections ),
            'items'      => [],
        ];

        $sections[] = $new_section;

        $recurrence_order = array_keys( Checklists::RECURRENCE_INTERVALS );

        usort( $sections, function ( $a, $b ) use ( $recurrence_order ) {
            return array_search( $a[ 'recurrence' ], $recurrence_order, true ) <=> array_search( $b[ 'recurrence' ], $recurrence_order, true );
        } );

        Checklists::save_sections( $checklist_id, $sections );
        Checklists::touch( $checklist_id );

        $new_section[ 'recurrence_order' ] = array_search( $recurrence, $recurrence_order, true );

        wp_send_json_success( [ 'section' => $new_section ] );
    } // End add_section()


    /**
     * Save section label/recurrence edits.
     *
     * @return void
     */
    public function save_section() : void {
        $this->guard();

        // phpcs:disable WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- verified via guard() above.
        $checklist_id = (int) wp_unslash( $_POST[ 'checklist_id' ] ?? 0 );
        $section_id = sanitize_text_field( wp_unslash( $_POST[ 'section_id' ] ?? '' ) );
        $label = sanitize_text_field( wp_unslash( $_POST[ 'label' ] ?? '' ) );
        $recurrence = sanitize_text_field( wp_unslash( $_POST[ 'recurrence' ] ?? 'daily' ) );
        // phpcs:enable

        if ( ! array_key_exists( $recurrence, Checklists::RECURRENCE_INTERVALS ) ) {
            $recurrence = 'daily';
        }

        $sections = Checklists::get_sections( $checklist_id );

        foreach ( $sections as $index => $section ) {
            if ( ( $section[ 'id' ] ?? '' ) === $section_id ) {
                $sections[ $index ][ 'label' ] = $label;
                $sections[ $index ][ 'recurrence' ] = $recurrence;
                break;
            }
        }

        Checklists::save_sections( $checklist_id, $sections );
        Checklists::touch( $checklist_id );

        wp_send_json_success();
    } // End save_section()


    /**
     * Delete a section (and its items) from a checklist.
     *
     * @return void
     */
    public function delete_section() : void {
        $this->guard();

        // phpcs:disable WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- verified via guard() above.
        $checklist_id = (int) wp_unslash( $_POST[ 'checklist_id' ] ?? 0 );
        $section_id = sanitize_text_field( wp_unslash( $_POST[ 'section_id' ] ?? '' ) );
        // phpcs:enable

        $sections = Checklists::get_sections( $checklist_id );

        $sections = array_values( array_filter( $sections, function ( $section ) use ( $section_id ) {
            return ( $section[ 'id' ] ?? '' ) !== $section_id;
        } ) );

        Checklists::save_sections( $checklist_id, $sections );
        Checklists::touch( $checklist_id );

        wp_send_json_success();
    } // End delete_section()


    /**
     * Reorder sections within a checklist (drag-drop).
     *
     * @return void
     */
    public function reorder_sections() : void {
        $this->guard();

        // phpcs:disable WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- verified via guard() above.
        $checklist_id = (int) wp_unslash( $_POST[ 'checklist_id' ] ?? 0 );
        $section_ids = array_map( 'sanitize_text_field', wp_unslash( (array) ( $_POST[ 'section_ids' ] ?? [] ) ) );
        // phpcs:enable

        $sections = Checklists::get_sections( $checklist_id );
        $sections_by_id = [];

        foreach ( $sections as $section ) {
            $sections_by_id[ $section[ 'id' ] ] = $section;
        }

        $reordered = [];
        foreach ( $section_ids as $order => $id ) {
            if ( isset( $sections_by_id[ $id ] ) ) {
                $sections_by_id[ $id ][ 'order' ] = $order;
                $reordered[] = $sections_by_id[ $id ];
            }
        }

        Checklists::save_sections( $checklist_id, $reordered );
        Checklists::touch( $checklist_id );

        wp_send_json_success();
    } // End reorder_sections()


    /**
     * Create a new checklist tab.
     *
     * @return void
     */
    public function add_checklist() : void {
        $this->guard();

        $title = sanitize_text_field( wp_unslash( $_POST[ 'title' ] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified via guard() above.

        if ( '' === $title ) {
            wp_send_json_error( [ 'message' => __( 'Title is required.', 'site-quality-check' ) ] );
        }

        $existing_count = count( Checklists::get_all() );
        $checklist_id = Checklists::create( $title, [], $existing_count );

        if ( is_wp_error( $checklist_id ) ) {
            wp_send_json_error( [ 'message' => $checklist_id->get_error_message() ] );
        }

        wp_send_json_success( [ 'checklist_id' => $checklist_id ] );
    } // End add_checklist()


    /**
     * Save a checklist's title.
     *
     * @return void
     */
    public function save_checklist() : void {
        $this->guard();

        // phpcs:disable WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- verified via guard() above.
        $checklist_id = (int) wp_unslash( $_POST[ 'checklist_id' ] ?? 0 );
        $title = sanitize_text_field( wp_unslash( $_POST[ 'title' ] ?? '' ) );
        // phpcs:enable

        wp_update_post( [
            'ID'         => $checklist_id,
            'post_title' => $title,
        ] );

        Checklists::touch( $checklist_id );

        wp_send_json_success();
    } // End save_checklist()


    /**
     * Delete a checklist tab entirely.
     *
     * @return void
     */
    public function delete_checklist() : void {
        $this->guard();

        $checklist_id = (int) wp_unslash( $_POST[ 'checklist_id' ] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- verified via guard() above.

        wp_delete_post( $checklist_id, true );

        wp_send_json_success();
    } // End delete_checklist()


    /**
     * Reorder checklist tabs (drag-drop).
     *
     * @return void
     */
    public function reorder_checklists() : void {
        $this->guard();

        $checklist_ids = array_map( 'intval', wp_unslash( (array) ( $_POST[ 'checklist_ids' ] ?? [] ) ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified via guard() above.

        $last_id = 0;

        foreach ( $checklist_ids as $order => $checklist_id ) {
            wp_update_post( [
                'ID'         => $checklist_id,
                'menu_order' => $order,
            ] );

            $last_id = $checklist_id;
        }

        if ( $last_id ) {
            Checklists::touch( $last_id );
        }

        wp_send_json_success();
    } // End reorder_checklists()

} // End class ChecklistsAjax

ChecklistsAjax::instance();