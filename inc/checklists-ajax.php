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
    public const NONCE_ACTION = 'sqc_checklists_nonce';


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
        add_action( 'wp_ajax_sqc_set_item_status', [ $this, 'set_item_status' ] );
        add_action( 'wp_ajax_sqc_save_item_label', [ $this, 'save_item_label' ] );
        add_action( 'wp_ajax_sqc_add_item', [ $this, 'add_item' ] );
        add_action( 'wp_ajax_sqc_delete_item', [ $this, 'delete_item' ] );
        add_action( 'wp_ajax_sqc_reorder_items', [ $this, 'reorder_items' ] );
        add_action( 'wp_ajax_sqc_add_section', [ $this, 'add_section' ] );
        add_action( 'wp_ajax_sqc_save_section', [ $this, 'save_section' ] );
        add_action( 'wp_ajax_sqc_delete_section', [ $this, 'delete_section' ] );
        add_action( 'wp_ajax_sqc_reorder_sections', [ $this, 'reorder_sections' ] );
        add_action( 'wp_ajax_sqc_add_checklist', [ $this, 'add_checklist' ] );
        add_action( 'wp_ajax_sqc_save_checklist', [ $this, 'save_checklist' ] );
        add_action( 'wp_ajax_sqc_delete_checklist', [ $this, 'delete_checklist' ] );
        add_action( 'wp_ajax_sqc_reorder_checklists', [ $this, 'reorder_checklists' ] );
    } // End __construct()


    /**
     * Verify nonce and checklist access, die with JSON error on failure.
     *
     * @param int $checklist_id
     * @return void
     */
    private function guard( int $checklist_id ) : void {
        check_ajax_referer( self::NONCE_ACTION, 'nonce' );

        $allowed_roles = Checklists::get_access( $checklist_id );

        if ( ! Access::can_access_checklist( $allowed_roles ) ) {
            wp_send_json_error( [ 'message' => __( 'You do not have permission to edit this checklist.', 'site-quality-check' ) ], 403 );
        }
    } // End guard()


    /**
     * Verify nonce and management access (for checklist-level actions: create/delete/reorder tabs).
     *
     * @return void
     */
    private function guard_manage() : void {
        check_ajax_referer( self::NONCE_ACTION, 'nonce' );

        if ( ! Access::can_manage() ) {
            wp_send_json_error( [ 'message' => __( 'You do not have permission to do this.', 'site-quality-check' ) ], 403 );
        }
    } // End guard_manage()


    /**
     * Toggle/set an item's status (complete, incomplete, snoozed, omitted).
     *
     * @return void
     */
    public function set_item_status() : void {
        $checklist_id = (int) ( $_POST[ 'checklist_id' ] ?? 0 );
        $item_id = sanitize_text_field( wp_unslash( $_POST[ 'item_id' ] ?? '' ) );
        $status = sanitize_text_field( wp_unslash( $_POST[ 'status' ] ?? '' ) );

        $this->guard( $checklist_id );

        if ( ! in_array( $status, [ 'complete', 'incomplete', 'snoozed', 'omitted' ], true ) ) {
            wp_send_json_error( [ 'message' => __( 'Invalid status.', 'site-quality-check' ) ] );
        }

        $saved = Checklists::set_item_status( $checklist_id, $item_id, $status );

        if ( ! $saved ) {
            wp_send_json_error( [ 'message' => __( 'Item not found.', 'site-quality-check' ) ] );
        }

        wp_send_json_success( [ 'stats' => Checklists::get_completion_stats( $checklist_id ) ] );
    } // End set_item_status()


    /**
     * Save an inline-edited item label.
     *
     * @return void
     */
    public function save_item_label() : void {
        $checklist_id = (int) ( $_POST[ 'checklist_id' ] ?? 0 );
        $item_id = sanitize_text_field( wp_unslash( $_POST[ 'item_id' ] ?? '' ) );
        $label = sanitize_text_field( wp_unslash( $_POST[ 'label' ] ?? '' ) );

        $this->guard( $checklist_id );

        $sections = Checklists::get_sections( $checklist_id );
        $location = Checklists::find_item( $sections, $item_id );

        if ( ! $location ) {
            wp_send_json_error( [ 'message' => __( 'Item not found.', 'site-quality-check' ) ] );
        }

        $sections[ $location[ 'section_index' ] ][ 'items' ][ $location[ 'item_index' ] ][ 'label' ] = $label;

        Checklists::save_sections( $checklist_id, $sections );

        wp_send_json_success();
    } // End save_item_label()


    /**
     * Add a new item to a section.
     *
     * @return void
     */
    public function add_item() : void {
        $checklist_id = (int) ( $_POST[ 'checklist_id' ] ?? 0 );
        $section_id = sanitize_text_field( wp_unslash( $_POST[ 'section_id' ] ?? '' ) );
        $label = sanitize_text_field( wp_unslash( $_POST[ 'label' ] ?? '' ) );

        $this->guard( $checklist_id );

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

        wp_send_json_success( [ 'item' => $new_item ] );
    } // End add_item()


    /**
     * Delete an item from a checklist.
     *
     * @return void
     */
    public function delete_item() : void {
        $checklist_id = (int) ( $_POST[ 'checklist_id' ] ?? 0 );
        $item_id = sanitize_text_field( wp_unslash( $_POST[ 'item_id' ] ?? '' ) );

        $this->guard( $checklist_id );

        $sections = Checklists::get_sections( $checklist_id );
        $location = Checklists::find_item( $sections, $item_id );

        if ( ! $location ) {
            wp_send_json_error( [ 'message' => __( 'Item not found.', 'site-quality-check' ) ] );
        }

        array_splice( $sections[ $location[ 'section_index' ] ][ 'items' ], $location[ 'item_index' ], 1 );

        Checklists::save_sections( $checklist_id, $sections );

        wp_send_json_success();
    } // End delete_item()


    /**
     * Reorder items within a section (drag-drop).
     *
     * @return void
     */
    public function reorder_items() : void {
        $checklist_id = (int) ( $_POST[ 'checklist_id' ] ?? 0 );
        $section_id = sanitize_text_field( wp_unslash( $_POST[ 'section_id' ] ?? '' ) );
        $item_ids = array_map( 'sanitize_text_field', wp_unslash( (array) ( $_POST[ 'item_ids' ] ?? [] ) ) );

        $this->guard( $checklist_id );

        $sections = Checklists::get_sections( $checklist_id );

        foreach ( $sections as $index => $section ) {
            if ( ( $section[ 'id' ] ?? '' ) !== $section_id ) {
                continue;
            }

            $items_by_id = [];
            foreach ( $section[ 'items' ] ?? [] as $item ) {
                $items_by_id[ $item[ 'id' ] ] = $item;
            }

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

        Checklists::save_sections( $checklist_id, $sections );

        wp_send_json_success();
    } // End reorder_items()


    /**
     * Add a new section to a checklist.
     *
     * @return void
     */
    public function add_section() : void {
        $checklist_id = (int) ( $_POST[ 'checklist_id' ] ?? 0 );
        $label = sanitize_text_field( wp_unslash( $_POST[ 'label' ] ?? '' ) );
        $recurrence = sanitize_text_field( wp_unslash( $_POST[ 'recurrence' ] ?? 'daily' ) );

        $this->guard( $checklist_id );

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

        Checklists::save_sections( $checklist_id, $sections );

        wp_send_json_success( [ 'section' => $new_section ] );
    } // End add_section()


    /**
     * Save section label/recurrence edits.
     *
     * @return void
     */
    public function save_section() : void {
        $checklist_id = (int) ( $_POST[ 'checklist_id' ] ?? 0 );
        $section_id = sanitize_text_field( wp_unslash( $_POST[ 'section_id' ] ?? '' ) );
        $label = sanitize_text_field( wp_unslash( $_POST[ 'label' ] ?? '' ) );
        $recurrence = sanitize_text_field( wp_unslash( $_POST[ 'recurrence' ] ?? 'daily' ) );

        $this->guard( $checklist_id );

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

        wp_send_json_success();
    } // End save_section()


    /**
     * Delete a section (and its items) from a checklist.
     *
     * @return void
     */
    public function delete_section() : void {
        $checklist_id = (int) ( $_POST[ 'checklist_id' ] ?? 0 );
        $section_id = sanitize_text_field( wp_unslash( $_POST[ 'section_id' ] ?? '' ) );

        $this->guard( $checklist_id );

        $sections = Checklists::get_sections( $checklist_id );

        $sections = array_values( array_filter( $sections, function ( $section ) use ( $section_id ) {
            return ( $section[ 'id' ] ?? '' ) !== $section_id;
        } ) );

        Checklists::save_sections( $checklist_id, $sections );

        wp_send_json_success();
    } // End delete_section()


    /**
     * Reorder sections within a checklist (drag-drop).
     *
     * @return void
     */
    public function reorder_sections() : void {
        $checklist_id = (int) ( $_POST[ 'checklist_id' ] ?? 0 );
        $section_ids = array_map( 'sanitize_text_field', wp_unslash( (array) ( $_POST[ 'section_ids' ] ?? [] ) ) );

        $this->guard( $checklist_id );

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

        wp_send_json_success();
    } // End reorder_sections()


    /**
     * Create a new checklist tab.
     *
     * @return void
     */
    public function add_checklist() : void {
        $this->guard_manage();

        $title = sanitize_text_field( wp_unslash( $_POST[ 'title' ] ?? '' ) );

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
     * Save a checklist's title and access roles.
     *
     * @return void
     */
    public function save_checklist() : void {
        $this->guard_manage();

        $checklist_id = (int) ( $_POST[ 'checklist_id' ] ?? 0 );
        $title = sanitize_text_field( wp_unslash( $_POST[ 'title' ] ?? '' ) );
        $roles = array_map( 'sanitize_text_field', wp_unslash( (array) ( $_POST[ 'roles' ] ?? [] ) ) );

        wp_update_post( [
            'ID'         => $checklist_id,
            'post_title' => $title,
        ] );

        Checklists::save_access( $checklist_id, $roles );

        wp_send_json_success();
    } // End save_checklist()


    /**
     * Delete a checklist tab entirely.
     *
     * @return void
     */
    public function delete_checklist() : void {
        $this->guard_manage();

        $checklist_id = (int) ( $_POST[ 'checklist_id' ] ?? 0 );

        wp_delete_post( $checklist_id, true );

        wp_send_json_success();
    } // End delete_checklist()


    /**
     * Reorder checklist tabs (drag-drop).
     *
     * @return void
     */
    public function reorder_checklists() : void {
        $this->guard_manage();

        $checklist_ids = array_map( 'intval', (array) ( $_POST[ 'checklist_ids' ] ?? [] ) );

        foreach ( $checklist_ids as $order => $checklist_id ) {
            wp_update_post( [
                'ID'         => $checklist_id,
                'menu_order' => $order,
            ] );
        }

        wp_send_json_success();
    } // End reorder_checklists()

} // End class ChecklistsAjax

ChecklistsAjax::instance();