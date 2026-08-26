<?php
/**
 * STALE CONTENT LIST TABLE
 *
 * WP_List_Table implementation for the Stale Content page: pagination,
 * sortable columns, search, and bulk Omit action.
 */

namespace PluginRx\SiteQualityCheck;

if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! class_exists( '\WP_List_Table' ) ) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}


class StaleContentListTable extends \WP_List_Table {

    /**
     * All stale content items, unpaginated, already filtered by search if applicable.
     *
     * @var array
     */
    private array $all_items = [];


    /**
     * Whether we're viewing the omitted list rather than active stale content.
     *
     * @var bool
     */
    private bool $showing_omitted = false;


    /**
     * Constructor
     */
    public function __construct() {
        parent::__construct( [
            'singular' => 'stale_content_item',
            'plural'   => 'stale_content_items',
            'ajax'     => false,
        ] );
    } // End __construct()


    /**
     * Define the columns.
     *
     * @return array
     */
    public function get_columns() : array {
        return [
            'cb'               => '<input type="checkbox" />',
            'title'            => __( 'Title', 'site-quality-check' ),
            'type'             => __( 'Type', 'site-quality-check' ),
            'last_modified'    => __( 'Last Modified', 'site-quality-check' ),
            'last_modified_by' => __( 'Last Modified By', 'site-quality-check' ),
            'status'           => __( 'Status', 'site-quality-check' ),
        ];
    } // End get_columns()


    /**
     * Define sortable columns.
     *
     * @return array
     */
    public function get_sortable_columns() : array {
        return [
            'title'         => [ 'title', false ],
            'type'          => [ 'type', false ],
            'last_modified' => [ 'last_modified', true ],
        ];
    } // End get_sortable_columns()


    /**
     * Define bulk actions.
     *
     * @return array
     */
    public function get_bulk_actions() : array {
        return $this->showing_omitted
            ? [ 'unomit' => __( 'Un-omit', 'site-quality-check' ) ]
            : [ 'omit' => __( 'Omit', 'site-quality-check' ) ];
    } // End get_bulk_actions()


    /**
     * Checkbox column.
     *
     * @param array $item
     * @return string
     */
    public function column_cb( $item ) : string {
        return sprintf( '<input type="checkbox" name="post_ids[]" value="%d">', esc_attr( $item[ 'post' ]->ID ) );
    } // End column_cb()


    /**
     * Title column with row actions.
     *
     * @param array $item
     * @return string
     */
    public function column_title( $item ) : string {
        $post = $item[ 'post' ];

        $edit_link = get_edit_post_link( $post->ID );
        $view_link = get_permalink( $post );

        $title = '<a href="' . esc_url( $edit_link ) . '" target="_blank" rel="noopener noreferrer">' . esc_html( get_the_title( $post ) ) . '</a>';

        $actions = [
            'edit' => '<a href="' . esc_url( $edit_link ) . '">' . esc_html__( 'Edit', 'site-quality-check' ) . '</a>',
            'view' => '<a href="' . esc_url( $view_link ) . '">' . esc_html__( 'View', 'site-quality-check' ) . '</a>',
        ];

        if ( $this->showing_omitted ) {
            $actions[ 'unomit' ] = '<a href="#" class="sqc-unomit-post" data-post-id="' . esc_attr( $post->ID ) . '">' . esc_html__( 'Un-omit', 'site-quality-check' ) . '</a>';
        } else {
            $actions[ 'omit' ] = '<a href="#" class="sqc-omit-post" data-post-id="' . esc_attr( $post->ID ) . '">' . esc_html__( 'Omit', 'site-quality-check' ) . '</a>';
        }

        return $title . $this->row_actions( $actions );
    } // End column_title()


    /**
     * Default column renderer.
     *
     * @param array $item
     * @param string $column_name
     * @return string
     */
    public function column_default( $item, $column_name ) : string {
        $post = $item[ 'post' ];

        switch ( $column_name ) {
            case 'type':
                return esc_html( get_post_type_object( $post->post_type )->labels->singular_name );

            case 'last_modified':
                if ( $this->showing_omitted ) {
                    return esc_html( get_the_modified_date( '', $post ) );
                }

                return esc_html( get_the_modified_date( '', $post ) ) . ' (' . esc_html( $item[ 'days_stale' ] ) . ' ' . esc_html__( 'days', 'site-quality-check' ) . ')';

            case 'last_modified_by':
                $editor_id = (int) get_post_meta( $post->ID, '_edit_last', true );
                $editor_id = $editor_id ?: (int) $post->post_author;
                $editor = get_userdata( $editor_id );

                return $editor ? esc_html( $editor->display_name ) : esc_html__( 'Unknown', 'site-quality-check' );

            case 'status':
                return '<span class="sqc-badge sqc-badge-' . esc_attr( $item[ 'tier' ] ) . '">' . esc_html( ucfirst( $item[ 'tier' ] ) ) . '</span>';

            default:
                return '';
        }
    } // End column_default()


    /**
     * Process the bulk Omit action.
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

        $post_ids = array_map( 'absint', (array) ( $_REQUEST[ 'post_ids' ] ?? [] ) );

        foreach ( $post_ids as $post_id ) {
            if ( 'omit' === $action ) {
                StaleContent::omit_post( $post_id );
            } else {
                StaleContent::unomit_post( $post_id );
            }
        }
    } // End process_bulk_action()


    /**
     * Prepare items: fetch, filter by search, sort, and paginate.
     *
     * @return void
     */
    public function prepare_items() : void {
        $this->showing_omitted = isset( $_REQUEST[ 'sqc_view' ] ) && 'omitted' === $_REQUEST[ 'sqc_view' ]; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only, no state change.

        $this->process_bulk_action();

        $columns = $this->showing_omitted
            ? array_diff_key( $this->get_columns(), [ 'status' => true ] )
            : $this->get_columns();

        $hidden = [];
        $sortable = $this->get_sortable_columns();

        $this->_column_headers = [ $columns, $hidden, $sortable ];

        $orderby = sanitize_key( wp_unslash( $_REQUEST[ 'orderby' ] ?? 'last_modified' ) );
        $order = ( 'asc' === strtolower( sanitize_text_field( wp_unslash( $_REQUEST[ 'order' ] ?? 'desc' ) ) ) ) ? 'asc' : 'desc';

        if ( $this->showing_omitted ) {
            $posts = StaleContent::get_omitted_posts();
            $items = array_map( function ( $post ) {
                return [ 'post' => $post, 'days_stale' => 0, 'tier' => '' ];
            }, $posts );
        } else {
            $items = StaleContent::get_stale_content( 'desc' === $order );
        }

        $search = sanitize_text_field( wp_unslash( $_REQUEST[ 's' ] ?? '' ) );

        if ( '' !== $search ) {
            $items = array_values( array_filter( $items, function ( $item ) use ( $search ) {
                return false !== stripos( get_the_title( $item[ 'post' ] ), $search );
            } ) );
        }

        if ( 'title' === $orderby || 'type' === $orderby ) {
            usort( $items, function ( $a, $b ) use ( $orderby, $order ) {
                $a_val = 'title' === $orderby ? get_the_title( $a[ 'post' ] ) : $a[ 'post' ]->post_type;
                $b_val = 'title' === $orderby ? get_the_title( $b[ 'post' ] ) : $b[ 'post' ]->post_type;

                $cmp = strcasecmp( $a_val, $b_val );

                return 'asc' === $order ? $cmp : -$cmp;
            } );
        }

        $this->all_items = $items;

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

} // End class StaleContentListTable