<?php
/**
 * CHECKLISTS
 *
 * Registers the site_quality_checklist CPT and handles section/item data
 * stored as JSON in post meta, plus recurrence-based auto-reset via cron.
 */

namespace PluginRx\SiteQualityCheck;

if ( ! defined( 'ABSPATH' ) ) exit;


class Checklists {

    /**
     * CPT slug.
     */
    public const POST_TYPE = 'sqc_checklist';


    /**
     * Recurrence intervals mapped to snooze/reset durations.
     */
    public const RECURRENCE_INTERVALS = [
        'daily'     => DAY_IN_SECONDS,
        'weekly'    => WEEK_IN_SECONDS,
        'monthly'   => MONTH_IN_SECONDS,
        'quarterly' => 3 * MONTH_IN_SECONDS,
        'annually'  => YEAR_IN_SECONDS,
    ];


    /**
     * Default checklist tabs created on activation.
     */
    public const DEFAULT_TABS = [ 'Developer', 'Designer', 'Content Editor' ];


    /**
     * @var Checklists|null Singleton instance
     */
    private static ?Checklists $instance = null;


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
        add_action( 'init', [ $this, 'register_post_type' ] );
        add_action( 'sqc_recurrence_check', [ $this, 'run_recurrence_check' ] );
        add_action( 'sqc_subheader_left', [ $this, 'render_subheader_button' ] );
        add_action( 'sqc_subheader_right', [ $this, 'render_subheader_search' ] );
        add_action( 'wp_ajax_sqc_preload_defaults', [ $this, 'ajax_preload_defaults' ] );

        if ( ! wp_next_scheduled( 'sqc_recurrence_check' ) ) {
            wp_schedule_event( time(), 'hourly', 'sqc_recurrence_check' );
        }
    } // End __construct()


    /**
     * AJAX: preload the default checklists, only if none currently exist.
     *
     * @return void
     */
    public function ajax_preload_defaults() : void {
        check_ajax_referer( ChecklistsAjax::NONCE_ACTION, 'nonce' );

        if ( ! Access::can_access() ) {
            wp_send_json_error( [ 'message' => __( 'You do not have permission to do this.', 'site-quality-check' ) ], 403 );
        }

        if ( ! empty( self::get_all() ) ) {
            wp_send_json_error( [ 'message' => __( 'Checklists already exist.', 'site-quality-check' ) ] );
        }

        DefaultData::instance()->seed_now();

        wp_send_json_success();
    } // End ajax_preload_defaults()


    /**
     * Render the "+ Add New Checklist" control, and "Preload Default Checklists"
     * when no checklists exist, in the subheader.
     *
     * @param string $active_page
     * @return void
     */
    public function render_subheader_button( string $active_page ) : void {
        if ( Menu::MENU_SLUG . '-checklists' !== $active_page ) {
            return;
        }
        ?>
        <span class="sqc-add-checklist-row" id="sqc-add-checklist-row" style="display:none;">
            <input type="text" class="sqc-add-checklist-input" placeholder="<?php esc_attr_e( 'New checklist name…', 'site-quality-check' ); ?>">
            <button type="button" class="sqc-button" id="sqc-add-checklist-confirm"><?php esc_html_e( 'Add', 'site-quality-check' ); ?></button>
            <button type="button" class="sqc-button button-secondary" id="sqc-add-checklist-cancel"><?php esc_html_e( 'Cancel', 'site-quality-check' ); ?></button>
        </span>
        <button type="button" class="sqc-button" id="sqc-add-checklist">+ <?php esc_html_e( 'Add New Checklist', 'site-quality-check' ); ?></button>

        <?php if ( empty( self::get_all() ) ) : ?>
            <button type="button" class="sqc-button button-secondary" id="sqc-preload-defaults"><?php esc_html_e( 'Preload Default Checklists', 'site-quality-check' ); ?></button>
        <?php endif; ?>
        <?php
    } // End render_subheader_button()


    /**
     * Render the checklist search field in the subheader (right side).
     *
     * @param string $active_page
     * @return void
     */
    public function render_subheader_search( string $active_page ) : void {
        if ( Menu::MENU_SLUG . '-checklists' !== $active_page ) {
            return;
        }

        if ( empty( self::get_all() ) ) {
            return;
        }
        ?>
        <input type="search" id="sqc-checklist-search" placeholder="<?php esc_attr_e( 'Search checklists...', 'site-quality-check' ); ?>" class="sqc-search-input">
        <?php
    } // End render_subheader_search()


    /**
     * Register the checklist CPT.
     *
     * @return void
     */
    public function register_post_type() : void {
        register_post_type( self::POST_TYPE, [
            'label'           => __( 'Quality Checklists', 'site-quality-check' ),
            'public'          => false,
            'show_ui'         => false,
            'show_in_menu'    => false,
            'supports'        => [ 'title' ],
            'capability_type' => 'post',
            'map_meta_cap'    => true,
        ] );

        register_post_meta( self::POST_TYPE, 'sqc_sections', [
            'show_in_rest'      => false,
            'single'            => true,
            'type'              => 'string',
            'sanitize_callback' => 'wp_kses_post',
        ] );

        register_post_meta( self::POST_TYPE, 'sqc_last_modified_by', [
            'show_in_rest'      => false,
            'single'            => true,
            'type'              => 'integer',
            'sanitize_callback' => 'absint',
        ] );
    } // End register_post_type()


    /**
     * Get all checklists ordered by menu_order.
     *
     * @return array Array of WP_Post objects.
     */
    public static function get_all() : array {
        return get_posts( [
            'post_type'      => self::POST_TYPE,
            'posts_per_page' => -1,
            'orderby'        => 'menu_order',
            'order'          => 'ASC',
            'post_status'    => 'publish',
        ] );
    } // End get_all()


    /**
     * Get a single checklist's decoded sections array.
     *
     * @param int $checklist_id
     * @return array
     */
    public static function get_sections( int $checklist_id ) : array {
        $raw = get_post_meta( $checklist_id, 'sqc_sections', true );

        if ( empty( $raw ) ) {
            return [];
        }

        $decoded = json_decode( $raw, true );

        return is_array( $decoded ) ? $decoded : [];
    } // End get_sections()


    /**
     * Save a checklist's sections array.
     *
     * @param int $checklist_id
     * @param array $sections
     * @return bool
     */
    public static function save_sections( int $checklist_id, array $sections ) : bool {
        $encoded = wp_json_encode( $sections );

        return (bool) update_post_meta( $checklist_id, 'sqc_sections', $encoded );
    } // End save_sections()


    /**
     * Create a new checklist.
     *
     * @param string $title
     * @param array $sections
     * @param int $menu_order
     * @return int|\WP_Error Post ID on success.
     */
    public static function create( string $title, array $sections = [], int $menu_order = 0 ) {
        $post_id = wp_insert_post( [
            'post_type'   => self::POST_TYPE,
            'post_title'  => $title,
            'post_status' => 'publish',
            'menu_order'  => $menu_order,
        ], true );

        if ( is_wp_error( $post_id ) ) {
            return $post_id;
        }

        self::save_sections( $post_id, $sections );

        return $post_id;
    } // End create()


    /**
     * Find an item within a checklist's sections by item ID.
     * Returns a reference-safe copy plus its location for updating.
     *
     * @param array $sections
     * @param string $item_id
     * @return array|null [ 'section_index' => int, 'item_index' => int, 'item' => array, 'recurrence' => string ]
     */
    public static function find_item( array $sections, string $item_id ) : ?array {
        foreach ( $sections as $section_index => $section ) {
            foreach ( $section[ 'items' ] ?? [] as $item_index => $item ) {
                if ( ( $item[ 'id' ] ?? '' ) === $item_id ) {
                    return [
                        'section_index' => $section_index,
                        'item_index'    => $item_index,
                        'item'          => $item,
                        'recurrence'    => $section[ 'recurrence' ] ?? 'daily',
                    ];
                }
            }
        }

        return null;
    } // End find_item()


    /**
     * Update a single item's status within a checklist and persist it.
     *
     * @param int $checklist_id
     * @param string $item_id
     * @param string $status One of: complete, incomplete, snoozed, omitted.
     * @return bool
     */
    public static function set_item_status( int $checklist_id, string $item_id, string $status ) : bool {
        $sections = self::get_sections( $checklist_id );
        $location = self::find_item( $sections, $item_id );

        if ( ! $location ) {
            return false;
        }

        $section_index = $location[ 'section_index' ];
        $item_index    = $location[ 'item_index' ];
        $recurrence    = $location[ 'recurrence' ];

        $sections[ $section_index ][ 'items' ][ $item_index ][ 'status' ] = $status;

        if ( 'complete' === $status ) {
            $sections[ $section_index ][ 'items' ][ $item_index ][ 'last_completed' ] = time();
            $sections[ $section_index ][ 'items' ][ $item_index ][ 'snoozed_until' ] = null;
        } elseif ( 'snoozed' === $status ) {
            $interval = self::RECURRENCE_INTERVALS[ $recurrence ] ?? DAY_IN_SECONDS;
            $sections[ $section_index ][ 'items' ][ $item_index ][ 'snoozed_until' ] = time() + $interval;
        } else {
            $sections[ $section_index ][ 'items' ][ $item_index ][ 'snoozed_until' ] = null;
        }

        return self::save_sections( $checklist_id, $sections );
    } // End set_item_status()


    /**
     * Cron callback: reset completed items past their recurrence interval,
     * and clear expired snoozes back to incomplete.
     *
     * @return void
     */
    public function run_recurrence_check() : void {
        $checklists = self::get_all();
        $now = time();

        foreach ( $checklists as $checklist ) {
            $sections = self::get_sections( $checklist->ID );
            $changed = false;

            foreach ( $sections as $section_index => $section ) {
                $interval = self::RECURRENCE_INTERVALS[ $section[ 'recurrence' ] ?? 'daily' ] ?? DAY_IN_SECONDS;

                foreach ( $section[ 'items' ] ?? [] as $item_index => $item ) {
                    $status = $item[ 'status' ] ?? 'incomplete';

                    if ( 'complete' === $status && ! empty( $item[ 'last_completed' ] ) ) {
                        if ( ( $now - (int) $item[ 'last_completed' ] ) >= $interval ) {
                            $sections[ $section_index ][ 'items' ][ $item_index ][ 'status' ] = 'incomplete';
                            $changed = true;
                        }
                    }

                    if ( 'snoozed' === $status && ! empty( $item[ 'snoozed_until' ] ) ) {
                        if ( $now >= (int) $item[ 'snoozed_until' ] ) {
                            $sections[ $section_index ][ 'items' ][ $item_index ][ 'status' ] = 'incomplete';
                            $sections[ $section_index ][ 'items' ][ $item_index ][ 'snoozed_until' ] = null;
                            $changed = true;
                        }
                    }
                }
            }

            if ( $changed ) {
                self::save_sections( $checklist->ID, $sections );
            }
        }
    } // End run_recurrence_check()


    /**
     * Calculate completion stats for a checklist, excluding omitted and snoozed items.
     *
     * @param int $checklist_id
     * @return array [ 'complete' => int, 'incomplete' => int, 'percent' => int|null ]
     */
    public static function get_completion_stats( int $checklist_id ) : array {
        $sections = self::get_sections( $checklist_id );
        $complete = 0;
        $incomplete = 0;

        foreach ( $sections as $section ) {
            foreach ( $section[ 'items' ] ?? [] as $item ) {
                $status = $item[ 'status' ] ?? 'incomplete';

                if ( 'complete' === $status ) {
                    $complete++;
                } elseif ( 'incomplete' === $status ) {
                    $incomplete++;
                }
                // snoozed and omitted are excluded entirely
            }
        }

        $total = $complete + $incomplete;

        return [
            'complete'   => $complete,
            'incomplete' => $incomplete,
            'percent'    => $total > 0 ? (int) round( ( $complete / $total ) * 100 ) : null,
        ];
    } // End get_completion_stats()


    /**
     * Render the Checklists admin page: sidebar of checklists + selected checklist's sections/items.
     *
     * @return void
     */
    public static function render_page() : void {
        if ( ! Access::can_access() ) {
            wp_die( esc_html__( 'You do not have permission to view this page.', 'site-quality-check' ) );
        }

        $checklists = self::get_all();

        wp_enqueue_style(
            'sqc-checklists',
            Bootstrap::url() . 'inc/css/checklists.css',
            [ 'sqc-theme' ],
            Bootstrap::script_version()
        );

        wp_enqueue_script(
            'sqc-checklists',
            Bootstrap::url() . 'inc/js/checklists.js',
            [ 'jquery' ],
            Bootstrap::script_version(),
            true
        );

        wp_localize_script( 'sqc-checklists', 'sqcChecklists', [
            'ajaxUrl'   => admin_url( 'admin-ajax.php' ),
            'nonce'     => wp_create_nonce( ChecklistsAjax::NONCE_ACTION ),
            'i18n' => [
                'addItem'       => __( 'Add Item', 'site-quality-check' ),
                'addSection'    => __( 'Add section', 'site-quality-check' ),
                'addChecklist'  => __( 'Add checklist', 'site-quality-check' ),
                'edit'          => __( 'Edit', 'site-quality-check' ),
                'done'          => __( 'Done', 'site-quality-check' ),
                'delete'        => __( 'Delete', 'site-quality-check' ),
                'deleteConfirm' => __( 'Are you sure you want to delete this?', 'site-quality-check' ),
                'snoozedUntil'  => __( 'Snoozed until', 'site-quality-check' ),
            ],
        ] );

        if ( empty( $checklists ) ) {
            ?>
            <div class="wrap sqc-content-wrap sqc-checklists">
                <p><?php esc_html_e( 'No checklists yet. Use "+ Add New Checklist" above to create one.', 'site-quality-check' ); ?></p>
            </div>
            <?php
            return;
        }

        $active_id = isset( $_GET[ 'checklist' ] ) ? (int) $_GET[ 'checklist' ] : (int) get_user_meta( get_current_user_id(), 'sqc_last_checklist', true );
        $active_ids = wp_list_pluck( $checklists, 'ID' );

        if ( ! in_array( $active_id, $active_ids, true ) ) {
            $active_id = $checklists[ 0 ]->ID;
        }

        update_user_meta( get_current_user_id(), 'sqc_last_checklist', $active_id );
        ?>
        <div class="wrap sqc-content-wrap sqc-checklists" id="sqc-checklists-app">
            <div id="sqc-checklist-layout">

                <div id="sqc-checklist-sidebar" class="sqc-box">
                    <ul id="sqc-checklist-list">
                        <?php foreach ( $checklists as $checklist ) : ?>
                            <?php
                            $stats = self::get_completion_stats( $checklist->ID );
                            $is_active = ( $checklist->ID === $active_id );
                            $item_class = $is_active ? 'sqc-sidebar-item active' : 'sqc-sidebar-item';
                            ?>
                            <li class="<?php echo esc_attr( $item_class ); ?>" data-checklist-id="<?php echo esc_attr( $checklist->ID ); ?>" draggable="true">
                                <a href="<?php echo esc_url( add_query_arg( [ 'page' => Menu::MENU_SLUG . '-checklists', 'checklist' => $checklist->ID ], admin_url( 'admin.php' ) ) ); ?>" data-checklist-id="<?php echo esc_attr( $checklist->ID ); ?>">
                                    <span class="sqc-sidebar-item-title"><?php echo esc_html( $checklist->post_title ); ?></span>
                                    <?php if ( null !== $stats[ 'percent' ] ) : ?>
                                        <span class="sqc-sidebar-item-percent"><?php echo esc_html( $stats[ 'percent' ] ); ?>%</span>
                                    <?php endif; ?>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>

                <div id="sqc-checklist-viewer" class="sqc-box">
                    <?php foreach ( $checklists as $checklist ) : ?>
                        <?php self::render_checklist_panel( $checklist, $checklist->ID === $active_id ); ?>
                    <?php endforeach; ?>
                </div>

            </div>
        </div>
        <?php
    } // End render_page()


    /**
     * Render a single checklist's panel: title header, tools, and sections.
     *
     * @param \WP_Post $checklist
     * @param bool $is_active
     * @return void
     */
        private static function render_checklist_panel( \WP_Post $checklist, bool $is_active ) : void {
        $sections = self::get_sections( $checklist->ID );
        $panel_style = $is_active ? '' : ' style="display:none;"';

        $created_by = get_userdata( $checklist->post_author );
        $modified_by_id = (int) get_post_meta( $checklist->ID, 'sqc_last_modified_by', true );
        $modified_by = $modified_by_id ? get_userdata( $modified_by_id ) : null;
        ?>
        <div class="sqc-checklist-panel" data-checklist-id="<?php echo esc_attr( $checklist->ID ); ?>"<?php echo $panel_style; ?>>
            <div class="sqc-checklist-header">
                <h2 class="sqc-checklist-title"><?php echo esc_html( $checklist->post_title ); ?></h2>
                <div class="sqc-checklist-tools">
                    <button type="button" class="sqc-button button-secondary sqc-edit-checklist-toggle" data-checklist-id="<?php echo esc_attr( $checklist->ID ); ?>"><?php esc_html_e( 'Edit', 'site-quality-check' ); ?></button>
                </div>
            </div>

            <p class="sqc-checklist-meta">
                <?php echo esc_html( sprintf(
                    /* translators: 1: date, 2: user display name */
                    __( 'Created %1$s by %2$s', 'site-quality-check' ),
                    Helpers::format_date( strtotime( $checklist->post_date ) ),
                    $created_by ? $created_by->display_name : __( 'Unknown', 'site-quality-check' )
                ) ); ?>

                <?php if ( $modified_by ) : ?>
                    &nbsp;&middot;&nbsp;
                    <?php echo esc_html( sprintf(
                        /* translators: 1: date, 2: user display name */
                        __( 'Last modified %1$s by %2$s', 'site-quality-check' ),
                        Helpers::format_date( strtotime( $checklist->post_modified ) ),
                        $modified_by->display_name
                    ) ); ?>
                <?php endif; ?>
            </p>

            <div class="sqc-sections" data-checklist-id="<?php echo esc_attr( $checklist->ID ); ?>">
                <?php foreach ( $sections as $section ) : ?>
                    <?php self::render_section( $checklist->ID, $section ); ?>
                <?php endforeach; ?>
            </div>

            <div class="sqc-add-section-row" data-checklist-id="<?php echo esc_attr( $checklist->ID ); ?>" style="display:none;">
                <input type="text" class="sqc-add-section-input" placeholder="<?php esc_attr_e( 'New section name…', 'site-quality-check' ); ?>">
                <select class="sqc-add-section-recurrence">
                    <?php foreach ( Checklists::RECURRENCE_INTERVALS as $key => $seconds ) : ?>
                        <option value="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( Helpers::recurrence_label( $key ) ); ?></option>
                    <?php endforeach; ?>
                </select>
                <button type="button" class="sqc-button sqc-add-section-confirm" data-checklist-id="<?php echo esc_attr( $checklist->ID ); ?>"><?php esc_html_e( 'Add', 'site-quality-check' ); ?></button>
                <button type="button" class="sqc-button button-secondary sqc-add-section-cancel"><?php esc_html_e( 'Cancel', 'site-quality-check' ); ?></button>
            </div>
            <button type="button" class="sqc-button button-secondary sqc-show-add-section" data-checklist-id="<?php echo esc_attr( $checklist->ID ); ?>" style="display:none;">+ <?php esc_html_e( 'Add Section', 'site-quality-check' ); ?></button>

            <div class="sqc-checklist-danger-zone" style="display:none;">
                <button type="button" class="sqc-button sqc-button-danger sqc-delete-checklist" data-checklist-id="<?php echo esc_attr( $checklist->ID ); ?>"><?php esc_html_e( 'Delete This Checklist', 'site-quality-check' ); ?></button>
            </div>
        </div>
        <?php
    } // End render_checklist_panel()


    /**
     * Render a single section and its items.
     *
     * @param int $checklist_id
     * @param array $section
     * @return void
     */
    private static function render_section( int $checklist_id, array $section ) : void {
        $recurrence_order = array_search( $section[ 'recurrence' ], array_keys( self::RECURRENCE_INTERVALS ), true );
        ?>
        <div class="sqc-section" data-section-id="<?php echo esc_attr( $section[ 'id' ] ); ?>" data-recurrence-order="<?php echo esc_attr( $recurrence_order ); ?>">
            <div class="sqc-section-header-row">
                <span class="sqc-section-drag-handle" title="<?php esc_attr_e( 'Drag to reorder', 'site-quality-check' ); ?>" style="display:none;">⠿</span>
                <h3 class="sqc-section-label sqc-section-header"><?php echo esc_html( $section[ 'label' ] ); ?> <span class="sqc-section-recurrence">(<?php echo esc_html( Helpers::recurrence_label( $section[ 'recurrence' ] ) ); ?>)</span></h3>
                <span class="sqc-section-edit-controls" style="display:none;">
                    <button type="button" class="button-link sqc-show-edit-section" title="<?php esc_attr_e( 'Rename section', 'site-quality-check' ); ?>">✎</button>
                    <button type="button" class="button-link sqc-delete-section" title="<?php esc_attr_e( 'Delete section', 'site-quality-check' ); ?>">✕</button>
                </span>
            </div>

            <div class="sqc-edit-section-row" style="display:none;">
                <input type="text" class="sqc-edit-section-input" value="<?php echo esc_attr( $section[ 'label' ] ); ?>">
                <select class="sqc-edit-section-recurrence">
                    <?php foreach ( self::RECURRENCE_INTERVALS as $key => $seconds ) : ?>
                        <option value="<?php echo esc_attr( $key ); ?>" <?php selected( $section[ 'recurrence' ], $key ); ?>><?php echo esc_html( Helpers::recurrence_label( $key ) ); ?></option>
                    <?php endforeach; ?>
                </select>
                <button type="button" class="sqc-button sqc-edit-section-confirm"><?php esc_html_e( 'Save', 'site-quality-check' ); ?></button>
            </div>

            <ul class="sqc-items" data-section-id="<?php echo esc_attr( $section[ 'id' ] ); ?>">
                <?php foreach ( $section[ 'items' ] ?? [] as $item ) : ?>
                    <?php self::render_item( $checklist_id, $item ); ?>
                <?php endforeach; ?>
            </ul>

            <div class="sqc-add-item-row" data-checklist-id="<?php echo esc_attr( $checklist_id ); ?>" data-section-id="<?php echo esc_attr( $section[ 'id' ] ); ?>" style="display:none;">
                <input type="text" class="sqc-add-item-input" placeholder="<?php esc_attr_e( 'New item…', 'site-quality-check' ); ?>">
                <button type="button" class="sqc-button sqc-add-item-confirm"><?php esc_html_e( 'Add', 'site-quality-check' ); ?></button>
                <button type="button" class="sqc-button button-secondary sqc-add-item-cancel"><?php esc_html_e( 'Cancel', 'site-quality-check' ); ?></button>
            </div>
            <button type="button" class="sqc-button sqc-show-add-item" data-checklist-id="<?php echo esc_attr( $checklist_id ); ?>" data-section-id="<?php echo esc_attr( $section[ 'id' ] ); ?>" style="display:none;">+ <?php esc_html_e( 'Add Item', 'site-quality-check' ); ?></button>
        </div>
        <?php
    } // End render_section()


    /**
     * Render a single checklist item.
     *
     * @param int $checklist_id
     * @param array $item
     * @return void
     */
    private static function render_item( int $checklist_id, array $item ) : void {
        $status = $item[ 'status' ] ?? 'incomplete';
        $is_snoozed = 'snoozed' === $status;
        ?>
        <li class="sqc-item sqc-item-<?php echo esc_attr( $status ); ?>" data-item-id="<?php echo esc_attr( $item[ 'id' ] ); ?>" data-checklist-id="<?php echo esc_attr( $checklist_id ); ?>" draggable="true">
            <span class="sqc-drag-handle" title="<?php esc_attr_e( 'Drag to reorder', 'site-quality-check' ); ?>">::</span>

            <input type="checkbox" class="sqc-item-toggle" <?php checked( 'complete', $status ); ?> <?php disabled( $is_snoozed ); ?>>

            <span class="sqc-item-label"><?php echo esc_html( $item[ 'label' ] ); ?></span>

            <span class="sqc-item-snoozed-badge" <?php echo $is_snoozed ? '' : 'style="display:none;"'; ?>>
                <?php echo esc_html( sprintf( __( 'Snoozed until %s', 'site-quality-check' ), Helpers::format_date( $item[ 'snoozed_until' ] ?? null ) ) ); ?>
            </span>

            <span class="sqc-item-actions-persistent">
                <button type="button" class="button-link sqc-snooze-item" title="<?php esc_attr_e( 'Remind me later', 'site-quality-check' ); ?>" <?php echo $is_snoozed ? 'style="display:none;"' : ''; ?>>⏰</button>
                <button type="button" class="button-link sqc-unsnooze-item" title="<?php esc_attr_e( 'Unsnooze', 'site-quality-check' ); ?>" <?php echo $is_snoozed ? '' : 'style="display:none;"'; ?>><?php esc_html_e( 'Unsnooze', 'site-quality-check' ); ?></button>
            </span>

            <span class="sqc-item-actions-edit" style="display:none;">
                <button type="button" class="button-link sqc-edit-item-label" title="<?php esc_attr_e( 'Edit', 'site-quality-check' ); ?>">✎</button>
                <button type="button" class="button-link sqc-delete-item" title="<?php esc_attr_e( 'Delete', 'site-quality-check' ); ?>">✕</button>
            </span>
        </li>
        <?php
    } // End render_item()


    /**
     * Record the current user as the last modifier of a checklist.
     *
     * @param int $checklist_id
     * @return void
     */
    public static function touch( int $checklist_id ) : void {
        update_post_meta( $checklist_id, 'sqc_last_modified_by', get_current_user_id() );
        wp_update_post( [ 'ID' => $checklist_id ] ); // bumps post_modified automatically
    } // End touch()

} // End class Checklists

Checklists::instance();