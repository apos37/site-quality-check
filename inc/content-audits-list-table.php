<?php
/**
 * CONTENT AUDITS LIST TABLE
 *
 * Single reusable WP_List_Table for all four audit types. The audit type
 * and a details-column renderer are passed in at construction.
 */

namespace PluginRx\SiteQualityCheck;

if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! class_exists( '\WP_List_Table' ) ) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}


class ContentAuditsListTable extends \WP_List_Table {

    /**
     * @var string
     */
    private string $audit_type;


    /**
     * @var callable Renders the "Details" column body for a decoded details array.
     */
    private $details_renderer;


    /**
     * @var bool
     */
    private bool $showing_omitted;


    /**
     * Constructor
     *
     * @param string $audit_type
     * @param callable $details_renderer function( array $details ) : string
     */
    public function __construct( string $audit_type, callable $details_renderer ) {
        $this->audit_type = $audit_type;
        $this->details_renderer = $details_renderer;
        $this->showing_omitted = isset( $_REQUEST[ 'sqcheck_view' ] ) && 'omitted' === $_REQUEST[ 'sqcheck_view' ]; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only, no state change.

        parent::__construct( [
            'singular' => 'audit_result',
            'plural'   => 'audit_results',
            'ajax'     => false,
        ] );
    } // End __construct()


    /**
     * @return array
     */
    public function get_columns() : array {
        return [
            'cb'      => '<input type="checkbox" />',
            'title'   => __( 'Title', 'site-quality-check' ),
            'type'    => __( 'Post Type', 'site-quality-check' ),
            'details' => __( 'Details', 'site-quality-check' ),
            'found'   => __( 'Found', 'site-quality-check' ),
        ];
    } // End get_columns()


    /**
     * @return array
     */
    public function get_bulk_actions() : array {
        return $this->showing_omitted
            ? [ 'unomit' => __( 'Un-omit', 'site-quality-check' ) ]
            : [ 'omit' => __( 'Omit', 'site-quality-check' ) ];
    } // End get_bulk_actions()


    /**
     * @param array $item
     * @return string
     */
    public function column_cb( $item ) : string {
        return sprintf( '<input type="checkbox" name="result_ids[]" value="%d">', esc_attr( $item[ 'id' ] ) );
    } // End column_cb()


    /**
     * @param array $item
     * @return string
     */
    public function column_title( $item ) : string {
        $post_id = (int) $item[ 'post_id' ];
        $edit_link = get_edit_post_link( $post_id );
        $view_link = get_permalink( $post_id );

        $title = '<a href="' . esc_url( $edit_link ) . '" target="_blank" rel="noopener noreferrer">' . esc_html( get_the_title( $post_id ) ) . '</a>';

        $actions = [
            'edit' => '<a href="' . esc_url( $edit_link ) . '">' . esc_html__( 'Edit', 'site-quality-check' ) . '</a>',
            'view' => '<a href="' . esc_url( $view_link ) . '">' . esc_html__( 'View', 'site-quality-check' ) . '</a>',
        ];

        if ( $this->showing_omitted ) {
            $actions[ 'unomit' ] = '<a href="#" class="sqcheck-unomit-result" data-id="' . esc_attr( $item[ 'id' ] ) . '">' . esc_html__( 'Un-omit', 'site-quality-check' ) . '</a>';
        } else {
            $actions[ 'omit' ] = '<a href="#" class="sqcheck-omit-result" data-id="' . esc_attr( $item[ 'id' ] ) . '">' . esc_html__( 'Omit', 'site-quality-check' ) . '</a>';
        }

        return $title . $this->row_actions( $actions );
    } // End column_title()


    /**
     * @param array $item
     * @param string $column_name
     * @return string
     */
    public function column_default( $item, $column_name ) : string {
        $post_id = (int) $item[ 'post_id' ];

        switch ( $column_name ) {
            case 'type':
                $post_type = get_post_type( $post_id );
                $obj = $post_type ? get_post_type_object( $post_type ) : null;
                return $obj ? esc_html( $obj->labels->singular_name ) : '';

            case 'details':
                $details = json_decode( $item[ 'details' ], true ) ?: [];
                return call_user_func( $this->details_renderer, $details );

            case 'found':
                return esc_html( wp_date( get_option( 'date_format' ), strtotime( $item[ 'found_at' ] ) ) );

            default:
                return '';
        }
    } // End column_default()


    /**
     * Process bulk omit/un-omit.
     *
     * @return void
     */
    private function process_bulk_action() : void {
        $action = $this->current_action();

        if ( ! in_array( $action, [ 'omit', 'unomit' ], true ) ) {
            return;
        }

        check_admin_referer( 'bulk-' . $this->_args[ 'plural' ] );

        if ( ! Access::can_access() ) {
            return;
        }

        global $wpdb;
        $ids = array_map( 'absint', (array) ( $_REQUEST[ 'result_ids' ] ?? [] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- verified via check_admin_referer above.

        if ( empty( $ids ) ) {
            return;
        }

        $table = $wpdb->prefix . 'sqcheck_audit_results';
        $placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );

        $wpdb->query( $wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            "UPDATE {$table} SET omitted = %d WHERE id IN ({$placeholders})", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- $table is hardcoded via $wpdb->prefix, not user input.
            array_merge( [ 'omit' === $action ? 1 : 0 ], $ids )
        ) );
    } // End process_bulk_action()


    /**
     * @return void
     */
    public function prepare_items() : void {
        $this->process_bulk_action();

        $columns = $this->get_columns();
        $this->_column_headers = [ $columns, [], [] ];

        $items = Audits::get_results( $this->audit_type, $this->showing_omitted );

        $per_page = 20;
        $current_page = $this->get_pagenum();
        $total_items = count( $items );

        $this->set_pagination_args( [
            'total_items' => $total_items,
            'per_page'    => $per_page,
            'total_pages' => (int) ceil( $total_items / $per_page ),
        ] );

        $this->items = array_slice( $items, ( $current_page - 1 ) * $per_page, $per_page );
    } // End prepare_items()

} // End class ContentAuditsListTable