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

        if ( ! wp_next_scheduled( 'sqc_recurrence_check' ) ) {
            wp_schedule_event( time(), 'hourly', 'sqc_recurrence_check' );
        }
    } // End __construct()


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
        $raw = get_post_meta( $checklist_id, '_sqc_sections', true );

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

        return (bool) update_post_meta( $checklist_id, '_sqc_sections', $encoded );
    } // End save_sections()


    /**
     * Get a checklist's assigned access roles.
     *
     * @param int $checklist_id
     * @return array
     */
    public static function get_access( int $checklist_id ) : array {
        $roles = get_post_meta( $checklist_id, '_sqc_access', true );

        return is_array( $roles ) ? $roles : [];
    } // End get_access()


    /**
     * Save a checklist's assigned access roles.
     *
     * @param int $checklist_id
     * @param array $roles
     * @return bool
     */
    public static function save_access( int $checklist_id, array $roles ) : bool {
        return (bool) update_post_meta( $checklist_id, '_sqc_access', array_values( $roles ) );
    } // End save_access()


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
     * Render the Checklists admin page: tabs, sections, items, edit mode.
     *
     * @return void
     */
    public static function render_page() : void {
        $all_checklists = self::get_all();
        $checklists = Access::filter_accessible_checklists( $all_checklists );

        if ( empty( $checklists ) ) {
            ?>
            <div class="wrap">
                <p><?php esc_html_e( 'No checklists are available to you.', 'site-quality-check' ); ?></p>
            </div>
            <?php
            return;
        }

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
            'ajaxUrl'    => admin_url( 'admin-ajax.php' ),
            'nonce'      => wp_create_nonce( ChecklistsAjax::NONCE_ACTION ),
            'canManage'  => Access::can_manage(),
            'i18n' => [
                'addItem'       => __( 'Add item', 'site-quality-check' ),
                'addSection'    => __( 'Add section', 'site-quality-check' ),
                'addChecklist'  => __( 'Add checklist', 'site-quality-check' ),
                'edit'          => __( 'Edit', 'site-quality-check' ),
                'done'          => __( 'Done', 'site-quality-check' ),
                'delete'        => __( 'Delete', 'site-quality-check' ),
                'deleteConfirm' => __( 'Are you sure you want to delete this?', 'site-quality-check' ),
                'complete'      => __( 'Complete', 'site-quality-check' ),
                'incomplete'    => __( 'Not Complete', 'site-quality-check' ),
                'snooze'        => __( 'Remind me later', 'site-quality-check' ),
                'omit'          => __( 'Omit', 'site-quality-check' ),
            ],
        ] );

        $active_id = isset( $_GET[ 'checklist' ] ) ? (int) $_GET[ 'checklist' ] : $checklists[ 0 ]->ID;
        $active_ids = wp_list_pluck( $checklists, 'ID' );

        if ( ! in_array( $active_id, $active_ids, true ) ) {
            $active_id = $checklists[ 0 ]->ID;
        }
        ?>
        <div class="wrap sqc-content-wrap sqc-checklists" id="sqc-checklists-app">
            <h2 class="nav-tab-wrapper" id="sqc-checklist-tabs">
                <?php foreach ( $checklists as $checklist ) : ?>
                    <?php
                    $tab_url = add_query_arg( [ 'page' => Menu::MENU_SLUG . '-checklists', 'checklist' => $checklist->ID ], admin_url( 'admin.php' ) );
                    $tab_class = ( $checklist->ID === $active_id ) ? 'nav-tab nav-tab-active' : 'nav-tab';
                    ?>
                    <a href="<?php echo esc_url( $tab_url ); ?>" class="<?php echo esc_attr( $tab_class ); ?>" data-checklist-id="<?php echo esc_attr( $checklist->ID ); ?>" draggable="true"><?php echo esc_html( $checklist->post_title ); ?></a>
                <?php endforeach; ?>

                <?php if ( Access::can_manage() ) : ?>
                    <span class="sqc-add-checklist-row" id="sqc-add-checklist-row" style="display:none;">
                        <input type="text" class="sqc-add-checklist-input" placeholder="<?php esc_attr_e( 'New checklist name…', 'site-quality-check' ); ?>">
                        <button type="button" class="button button-small" id="sqc-add-checklist-confirm"><?php esc_html_e( 'Add', 'site-quality-check' ); ?></button>
                    </span>
                    <button type="button" class="nav-tab sqc-add-checklist-tab" id="sqc-add-checklist">+ <?php esc_html_e( 'Add', 'site-quality-check' ); ?></button>
                <?php endif; ?>
            </h2>

            <?php foreach ( $checklists as $checklist ) : ?>
                <?php self::render_checklist_panel( $checklist, $checklist->ID === $active_id ); ?>
            <?php endforeach; ?>
        </div>
        <?php
    } // End render_page()


    /**
     * Render a single checklist's panel: sections and items.
     *
     * @param \WP_Post $checklist
     * @param bool $is_active
     * @return void
     */
    private static function render_checklist_panel( \WP_Post $checklist, bool $is_active ) : void {
        $sections = self::get_sections( $checklist->ID );
        $can_manage = Access::can_manage();
        ?>
        <div class="sqc-checklist-panel" data-checklist-id="<?php echo esc_attr( $checklist->ID ); ?>" <?php echo $is_active ? '' : 'style="display:none;"'; ?>>
            <?php if ( $can_manage ) : ?>
                <div class="sqc-checklist-tools">
                    <button type="button" class="button sqc-edit-checklist-toggle" data-checklist-id="<?php echo esc_attr( $checklist->ID ); ?>"><?php esc_html_e( 'Edit', 'site-quality-check' ); ?></button>
                </div>
            <?php endif; ?>

            <div class="sqc-sections" data-checklist-id="<?php echo esc_attr( $checklist->ID ); ?>">
                <?php foreach ( $sections as $section ) : ?>
                    <?php self::render_section( $checklist->ID, $section ); ?>
                <?php endforeach; ?>
            </div>

            <?php if ( $can_manage ) : ?>
                <div class="sqc-add-section-row" data-checklist-id="<?php echo esc_attr( $checklist->ID ); ?>" style="display:none;">
                    <input type="text" class="sqc-add-section-input" placeholder="<?php esc_attr_e( 'New section name…', 'site-quality-check' ); ?>">
                    <select class="sqc-add-section-recurrence">
                        <?php foreach ( Checklists::RECURRENCE_INTERVALS as $key => $seconds ) : ?>
                            <option value="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( Helpers::recurrence_label( $key ) ); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button type="button" class="button button-small sqc-add-section-confirm" data-checklist-id="<?php echo esc_attr( $checklist->ID ); ?>"><?php esc_html_e( 'Add', 'site-quality-check' ); ?></button>
                </div>
                <button type="button" class="button sqc-show-add-section" data-checklist-id="<?php echo esc_attr( $checklist->ID ); ?>" style="display:none;">+ <?php esc_html_e( 'Add Section', 'site-quality-check' ); ?></button>
            <?php endif; ?>
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
        ?>
        <div class="sqc-section" data-section-id="<?php echo esc_attr( $section[ 'id' ] ); ?>" draggable="true">
            <h3 class="sqc-section-label sqc-section-header"><?php echo esc_html( $section[ 'label' ] ); ?> <span class="sqc-section-recurrence">(<?php echo esc_html( ucfirst( $section[ 'recurrence' ] ) ); ?>)</span></h3>

            <ul class="sqc-items" data-section-id="<?php echo esc_attr( $section[ 'id' ] ); ?>">
                <?php foreach ( $section[ 'items' ] ?? [] as $item ) : ?>
                    <?php if ( 'omitted' === ( $item[ 'status' ] ?? 'incomplete' ) ) continue; ?>
                    <?php self::render_item( $checklist_id, $item ); ?>
                <?php endforeach; ?>
            </ul>

            <div class="sqc-add-item-row" data-checklist-id="<?php echo esc_attr( $checklist_id ); ?>" data-section-id="<?php echo esc_attr( $section[ 'id' ] ); ?>" style="display:none;">
                <input type="text" class="sqc-add-item-input" placeholder="<?php esc_attr_e( 'New item…', 'site-quality-check' ); ?>">
                <button type="button" class="button button-small sqc-add-item-confirm"><?php esc_html_e( 'Add', 'site-quality-check' ); ?></button>
            </div>
            <button type="button" class="button-link sqc-show-add-item" data-checklist-id="<?php echo esc_attr( $checklist_id ); ?>" data-section-id="<?php echo esc_attr( $section[ 'id' ] ); ?>" style="display:none;">+ <?php esc_html_e( 'Add item', 'site-quality-check' ); ?></button>
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
        ?>
        <li class="sqc-item sqc-item-<?php echo esc_attr( $status ); ?>" data-item-id="<?php echo esc_attr( $item[ 'id' ] ); ?>" data-checklist-id="<?php echo esc_attr( $checklist_id ); ?>" draggable="true">
            <span class="sqc-drag-handle" title="<?php esc_attr_e( 'Drag to reorder', 'site-quality-check' ); ?>">::</span>

            <label class="sqc-item-checkbox">
                <input type="checkbox" class="sqc-item-toggle" <?php checked( 'complete', $status ); ?>>
                <span class="sqc-item-label"><?php echo esc_html( $item[ 'label' ] ); ?></span>
            </label>

            <span class="sqc-item-actions">
                <button type="button" class="button-link sqc-snooze-item" title="<?php esc_attr_e( 'Remind me later', 'site-quality-check' ); ?>">⏰</button>
                <button type="button" class="button-link sqc-omit-item" title="<?php esc_attr_e( 'Omit', 'site-quality-check' ); ?>">✕</button>
            </span>
        </li>
        <?php
    } // End render_item()

} // End class Checklists

Checklists::instance();