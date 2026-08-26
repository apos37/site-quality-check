<?php
/**
 * SETTINGS
 *
 * Stale content thresholds, included post types, contact form selection,
 * and quick action toggles.
 */

namespace PluginRx\SiteQualityCheck;

if ( ! defined( 'ABSPATH' ) ) exit;


class Settings {

    /**
     * Settings group name.
     */
    public const OPTION_GROUP = 'sqc_settings';


    /**
     * @var Settings|null Singleton instance
     */
    private static ?Settings $instance = null;


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
        add_action( 'admin_init', [ $this, 'register_settings' ] );
        add_action( 'wp_ajax_sqc_save_settings', [ $this, 'ajax_save_settings' ] );
        add_action( 'sqc_subheader_left', [ $this, 'render_subheader_buttons' ] );
    } // End __construct()


    /**
     * Register all settings fields.
     *
     * @return void
     */
    public function register_settings() : void {
        register_setting( self::OPTION_GROUP, 'sqc_menu_title', [
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default'           => __( 'Quality Check', 'site-quality-check' ),
        ] );

        register_setting( self::OPTION_GROUP, 'sqc_page_title', [
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default'           => __( 'Site Quality Check', 'site-quality-check' ),
        ] );

        register_setting( self::OPTION_GROUP, 'sqc_menu_icon', [
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default'           => 'dashicons-yes-alt',
        ] );

        register_setting( self::OPTION_GROUP, 'sqc_menu_position', [
            'type'              => 'integer',
            'sanitize_callback' => 'absint',
            'default'           => 2,
        ] );

        register_setting( self::OPTION_GROUP, 'sqc_logo', [
            'type'              => 'string',
            'sanitize_callback' => 'esc_url_raw',
            'default'           => '',
        ] );

        register_setting( self::OPTION_GROUP, 'sqc_stale_thresholds', [
            'type'              => 'array',
            'sanitize_callback' => [ __CLASS__, 'sanitize_thresholds' ],
            'default'           => StaleContent::DEFAULT_THRESHOLDS,
        ] );

        register_setting( self::OPTION_GROUP, 'sqc_stale_post_types', [
            'type'              => 'array',
            'sanitize_callback' => [ __CLASS__, 'sanitize_post_types' ],
            'default'           => [ 'post', 'page' ],
        ] );

        register_setting( self::OPTION_GROUP, 'sqc_contact_page_id', [
            'type'              => 'integer',
            'sanitize_callback' => 'absint',
            'default'           => 0,
        ] );

        register_setting( self::OPTION_GROUP, 'sqc_contact_form_id', [
            'type'              => 'integer',
            'sanitize_callback' => 'absint',
            'default'           => 0,
        ] );

        register_setting( self::OPTION_GROUP, 'sqc_enabled_quick_actions', [
            'type'              => 'array',
            'sanitize_callback' => [ __CLASS__, 'sanitize_enabled_actions' ],
            'default'           => array_keys( QuickActions::get_available_actions() ),
        ] );

        register_setting( self::OPTION_GROUP, 'sqc_clear_data_on_uninstall', [
            'type'              => 'boolean',
            'sanitize_callback' => 'rest_sanitize_boolean',
            'default'           => false,
        ] );
    } // End register_settings()


    /**
     * Sanitize stale content threshold inputs (days, must be positive integers, ascending order enforced).
     *
     * @param array $input
     * @return array
     */
    public static function sanitize_thresholds( $input ) : array {
        $input = is_array( $input ) ? $input : [];

        $warning = max( 1, absint( $input[ 'warning' ] ?? StaleContent::DEFAULT_THRESHOLDS[ 'warning' ] ) );
        $danger = max( $warning + 1, absint( $input[ 'danger' ] ?? StaleContent::DEFAULT_THRESHOLDS[ 'danger' ] ) );
        $critical = max( $danger + 1, absint( $input[ 'critical' ] ?? StaleContent::DEFAULT_THRESHOLDS[ 'critical' ] ) );

        return [
            'warning'  => $warning,
            'danger'   => $danger,
            'critical' => $critical,
        ];
    } // End sanitize_thresholds()


    /**
     * Sanitize the list of post types included in stale content checks.
     *
     * @param array $input
     * @return array
     */
    public static function sanitize_post_types( $input ) : array {
        $input = is_array( $input ) ? $input : [];
        $valid = array_keys( get_post_types( [ 'public' => true ] ) );
        $sanitized = array_intersect( array_map( 'sanitize_key', $input ), $valid );

        return ! empty( $sanitized ) ? array_values( $sanitized ) : [ 'post', 'page' ];
    } // End sanitize_post_types()


    /**
     * Sanitize the list of enabled quick actions.
     *
     * @param array $input
     * @return array
     */
    public static function sanitize_enabled_actions( $input ) : array {
        $input = is_array( $input ) ? $input : [];
        $valid = array_keys( QuickActions::get_available_actions() );

        return array_values( array_intersect( array_map( 'sanitize_key', $input ), $valid ) );
    } // End sanitize_enabled_actions()


    /**
     * Render the Save button and dirty-state reminder in the subheader.
     *
     * @param string $active_page
     * @return void
     */
    public function render_subheader_buttons( string $active_page ) : void {
        if ( Menu::MENU_SLUG . '-settings' !== $active_page ) {
            return;
        }
        ?>
        <button type="submit" form="sqc-settings-form" class="sqc-button"><?php esc_html_e( 'Save', 'site-quality-check' ); ?></button>
        <span id="sqc-save-reminder"><?php esc_html_e( 'Remember to click "Save" after making changes to your settings.', 'site-quality-check' ); ?></span>
        <?php
    } // End render_subheader_buttons()


    /**
     * Render the Settings admin page.
     *
     * @return void
     */
    public static function render_page() : void {
        if ( ! Access::can_manage() ) {
            wp_die( esc_html__( 'You do not have permission to view this page.', 'site-quality-check' ) );
        }

        wp_enqueue_script(
            'sqc-settings',
            Bootstrap::url() . 'inc/js/settings.js',
            [ 'jquery' ],
            Bootstrap::script_version(),
            true
        );

        wp_localize_script( 'sqc-settings', 'sqcSettings', [
            'nonce'      => wp_create_nonce( 'sqc_settings_nonce' ),
            'savingText' => __( 'Saving', 'site-quality-check' ),
            'savedText'  => __( 'Settings saved successfully.', 'site-quality-check' ),
            'errorText'  => __( 'Error saving settings. Please try again.', 'site-quality-check' ),
        ] );

        $thresholds = StaleContent::get_thresholds();
        $included_post_types = StaleContent::get_included_post_types();
        $contact_page_id = (int) get_option( 'sqc_contact_page_id', 0 );
        $contact_form_id = (int) get_option( 'sqc_contact_form_id', 0 );
        $enabled_actions = get_option( 'sqc_enabled_quick_actions', array_keys( QuickActions::get_available_actions() ) );
        $public_post_types = get_post_types( [ 'public' => true ], 'objects' );
        $menu_title = get_option( 'sqc_menu_title', __( 'Quality Check', 'site-quality-check' ) );
        $page_title = get_option( 'sqc_page_title', __( 'Site Quality Check', 'site-quality-check' ) );
        $menu_icon = get_option( 'sqc_menu_icon', 'dashicons-yes-alt' );
        $logo = get_option( 'sqc_logo', '' );
        $allowed_roles = get_option( 'sqc_allowed_roles', [] );
        ?>
        <div class="wrap sqc-content-wrap sqc-settings">
            <?php Advanced::render_notices(); ?>
            <div class="sqc-settings-grid">

                <div class="sqc-box">
                    <div class="sqc-box-header"><h2><?php esc_html_e( 'Interface', 'site-quality-check' ); ?></h2></div>
                    <div class="sqc-box-body">
                        <div class="sqc-field">
                            <label><?php esc_html_e( 'Menu Title', 'site-quality-check' ); ?></label>
                            <input type="text" id="sqc_menu_title" name="sqc_menu_title" value="<?php echo esc_attr( $menu_title ); ?>">
                        </div>
                        <div class="sqc-field">
                            <label>
                                <?php esc_html_e( 'Menu Icon', 'site-quality-check' ); ?> — 
                                <a href="https://developer.wordpress.org/resource/dashicons/" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'View Dashicons', 'site-quality-check' ); ?> <span class="dashicons dashicons-external"></span></a>
                            </label>
                            <select id="sqc_menu_icon" name="sqc_menu_icon">
                                <?php foreach ( Helpers::get_dashicons() as $label ) : ?>
                                    <option value="<?php echo esc_attr( $label ); ?>" <?php selected( $menu_icon, $label ); ?>><?php echo esc_html( $label ); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="sqc-field">
                            <label><?php esc_html_e( 'Page Title', 'site-quality-check' ); ?></label>
                            <input type="text" id="sqc_page_title" name="sqc_page_title" value="<?php echo esc_attr( $page_title ); ?>">
                        </div>
                        <div class="sqc-field">
                            <label>
                                <?php esc_html_e( 'Page Logo', 'site-quality-check' ); ?>
                                <?php Helpers::tooltip( __( 'Preferred size: 100x100 pixels. Accepted formats: jpg | jpeg | png | webp', 'site-quality-check' ) ); ?>
                            </label>
                            <input type="text" id="sqc_logo" name="sqc_logo" value="<?php echo esc_attr( $logo ); ?>" placeholder="https://example.com/logo.png">
                        </div>
                    </div>
                </div>

                <div class="sqc-box">
                    <div class="sqc-box-header"><h2><?php esc_html_e( 'Stale Content Thresholds', 'site-quality-check' ); ?></h2></div>
                    <div class="sqc-box-body">
                        <?php foreach ( [ 'warning' => __( 'Warning (days)', 'site-quality-check' ), 'danger' => __( 'Danger (days)', 'site-quality-check' ), 'critical' => __( 'Critical (days)', 'site-quality-check' ) ] as $key => $label ) : ?>
                            <div class="sqc-field">
                                <label><?php echo esc_html( $label ); ?></label>
                                <input type="number" min="1" name="sqc_stale_thresholds[<?php echo esc_attr( $key ); ?>]" value="<?php echo esc_attr( $thresholds[ $key ] ); ?>">
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="sqc-box">
                    <div class="sqc-box-header"><h2><?php esc_html_e( 'Post Types to Check', 'site-quality-check' ); ?></h2></div>
                    <div class="sqc-box-body">
                        <div class="sqc-checkboxes">
                            <?php foreach ( $public_post_types as $post_type ) : ?>
                                <label>
                                    <input type="checkbox" name="sqc_stale_post_types[]" value="<?php echo esc_attr( $post_type->name ); ?>" <?php checked( in_array( $post_type->name, $included_post_types, true ), true ); ?>>
                                    <?php echo esc_html( $post_type->labels->singular_name ); ?>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <div class="sqc-box">
                    <div class="sqc-box-header"><h2><?php esc_html_e( 'Contact Page & Form', 'site-quality-check' ); ?></h2></div>
                    <div class="sqc-box-body">
                        <div class="sqc-field">
                            <label>
                                <?php esc_html_e( 'Contact Page', 'site-quality-check' ); ?>
                                <?php Helpers::tooltip( __( 'Used by the "Check Key Pages for 404s" quick action.', 'site-quality-check' ) ); ?>
                            </label>
                            <?php
                            wp_dropdown_pages( [
                                'name'              => 'sqc_contact_page_id',
                                'selected'          => $contact_page_id,
                                'show_option_none'  => __( '— Select a page —', 'site-quality-check' ),
                                'option_none_value' => 0,
                            ] );
                            ?>
                        </div>

                        <?php if ( Integrations::is_gravity_forms_active() ) : ?>
                            <div class="sqc-field">
                                <label>
                                    <?php esc_html_e( 'Contact Form', 'site-quality-check' ); ?>
                                    <?php Helpers::tooltip( __( 'Used by the "Test Contact Form" quick action. If left unselected, all forms are checked.', 'site-quality-check' ) ); ?>
                                </label>
                                <select name="sqc_contact_form_id">
                                    <option value="0"><?php esc_html_e( '— Select a form —', 'site-quality-check' ); ?></option>
                                    <?php foreach ( \GFAPI::get_forms() as $form ) : ?>
                                        <option value="<?php echo esc_attr( $form[ 'id' ] ); ?>" <?php selected( $contact_form_id, $form[ 'id' ] ); ?>><?php echo esc_html( $form[ 'title' ] ); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="sqc-box">
                    <div class="sqc-box-header"><h2><?php esc_html_e( 'Quick Actions', 'site-quality-check' ); ?></h2></div>
                    <div class="sqc-box-body">
                        <div class="sqc-checkboxes">
                            <?php foreach ( QuickActions::get_available_actions() as $slug => $action ) : ?>
                                <?php if ( ! $action[ 'available' ] ) continue; ?>
                                <label style="display: block; font-weight: 400;">
                                    <input type="checkbox" name="sqc_enabled_quick_actions[]" value="<?php echo esc_attr( $slug ); ?>" <?php checked( in_array( $slug, $enabled_actions, true ), true ); ?>>
                                    <?php echo esc_html( $action[ 'label' ] ); ?>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <div class="sqc-box">
                    <div class="sqc-box-header"><h2><?php esc_html_e( 'Access', 'site-quality-check' ); ?></h2></div>
                    <div class="sqc-box-body">
                        <div class="sqc-field">
                            <label>
                                <?php esc_html_e( 'Additional Roles With Access', 'site-quality-check' ); ?>
                                <?php Helpers::tooltip( __( 'Administrators always have full access. Select any additional roles that should be able to use this plugin.', 'site-quality-check' ) ); ?>
                            </label>
                            <div class="sqc-checkboxes">
                                <?php foreach ( Access::get_assignable_roles() as $slug => $label ) : ?>
                                    <label>
                                        <input type="checkbox" name="sqc_allowed_roles[]" value="<?php echo esc_attr( $slug ); ?>" <?php checked( in_array( $slug, $allowed_roles, true ), true ); ?>>
                                        <?php echo esc_html( $label ); ?>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="sqc-box">
                    <div class="sqc-box-header"><h2><?php esc_html_e( 'Advanced', 'site-quality-check' ); ?></h2></div>
                    <div class="sqc-box-body">

                        <div class="sqc-field">
                            <label style="font-weight: 400; display: flex; align-items: center; gap: 6px;">
                                <input type="checkbox" name="sqc_clear_data_on_uninstall" value="1" <?php checked( get_option( 'sqc_clear_data_on_uninstall' ), true ); ?>>
                                <?php esc_html_e( 'Remove All Plugin Data on Uninstall', 'site-quality-check' ); ?>
                                <?php Helpers::tooltip( __( 'Deletes all plugin settings and documentation permanently when the plugin is deleted.', 'site-quality-check' ) ); ?>
                            </label>
                        </div>

                        <div class="sqc-field" id="sqc_field_upload_download_settings">
                            <label>
                                <?php esc_html_e( 'Upload/Download Settings', 'site-quality-check' ); ?>
                                <?php Helpers::tooltip( __( 'Export your current settings as a JSON file for backup or transfer to another site. You can also import settings from a JSON file exported from this plugin. Note: Importing settings will overwrite your current settings.', 'site-quality-check' ) ); ?>
                            </label>
                            <div style="display: flex; gap: 8px; align-items: center; flex-wrap: wrap;">
                                <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                                    <?php wp_nonce_field( 'sqc_advanced_action' ); ?>
                                    <input type="hidden" name="action" value="sqc_export">
                                    <button type="submit" class="sqc-button"><?php esc_html_e( 'Download Settings', 'site-quality-check' ); ?></button>
                                </form>
                                <label for="sqc-upload-settings" class="sqc-button" style="cursor: pointer;"><?php esc_html_e( 'Upload Settings', 'site-quality-check' ); ?></label>
                                <input type="file" id="sqc-upload-settings" accept="application/json" style="display: none;">
                                <span id="sqc-upload-filename"></span>
                            </div>
                        </div>

                        <div class="sqc-field" id="sqc_field_reset_settings">
                            <label>
                                <?php esc_html_e( 'Reset Settings', 'site-quality-check' ); ?>
                                <?php Helpers::tooltip( __( 'This will not delete checklist data; it will reset all settings on this page to their defaults.', 'site-quality-check' ) ); ?>
                            </label>
                            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" onsubmit="return confirm('<?php echo esc_js( __( 'Reset all settings to defaults?', 'site-quality-check' ) ); ?>');">
                                <?php wp_nonce_field( 'sqc_advanced_action' ); ?>
                                <input type="hidden" name="action" value="sqc_reset_settings">
                                <button type="submit" class="sqc-button"><?php esc_html_e( 'Reset Settings', 'site-quality-check' ); ?></button>
                            </form>
                        </div>

                    </div>
                </div>

            </div>
        </div>
        <?php
    } // End render_page()


    /**
     * AJAX handler to save all settings fields at once.
     *
     * @return void
     */
    public function ajax_save_settings() : void {
        check_ajax_referer( 'sqc_settings_nonce', 'nonce' );

        if ( ! Access::can_manage() ) {
            wp_send_json_error( __( 'Insufficient permissions.', 'site-quality-check' ) );
        }

        $settings = wp_unslash( $_POST[ 'settings' ] ?? [] );

        if ( ! is_array( $settings ) ) {
            wp_send_json_error( __( 'Invalid data.', 'site-quality-check' ) );
        }

        $errors = [];

        $simple_fields = [
            'sqc_menu_title'      => 'sanitize_text_field',
            'sqc_page_title'      => 'sanitize_text_field',
            'sqc_menu_icon'       => 'sanitize_text_field',
            'sqc_logo'            => 'esc_url_raw',
            'sqc_contact_page_id' => 'absint',
            'sqc_contact_form_id' => 'absint',
        ];

        foreach ( $simple_fields as $key => $sanitizer ) {
            if ( isset( $settings[ $key ] ) ) {
                $value = call_user_func( $sanitizer, $settings[ $key ] );

                if ( false === update_option( $key, $value ) && get_option( $key ) !== $value ) {
                    $errors[] = $key;
                }
            }
        }

        update_option( 'sqc_clear_data_on_uninstall', isset( $settings[ 'sqc_clear_data_on_uninstall' ] ) && $settings[ 'sqc_clear_data_on_uninstall' ] ? 1 : 0 );

        if ( isset( $settings[ 'sqc_stale_thresholds' ] ) && is_array( $settings[ 'sqc_stale_thresholds' ] ) ) {
            update_option( 'sqc_stale_thresholds', self::sanitize_thresholds( $settings[ 'sqc_stale_thresholds' ] ) );
        }

        if ( isset( $settings[ 'sqc_stale_post_types' ] ) ) {
            update_option( 'sqc_stale_post_types', self::sanitize_post_types( (array) $settings[ 'sqc_stale_post_types' ] ) );
        }

        if ( isset( $settings[ 'sqc_enabled_quick_actions' ] ) ) {
            update_option( 'sqc_enabled_quick_actions', self::sanitize_enabled_actions( (array) $settings[ 'sqc_enabled_quick_actions' ] ) );
        }

        if ( isset( $settings[ 'sqc_allowed_roles' ] ) ) {
            $allowed_roles = array_map( 'sanitize_key', (array) $settings[ 'sqc_allowed_roles' ] );
            update_option( 'sqc_allowed_roles', $allowed_roles );
        }

        if ( empty( $errors ) ) {
            wp_send_json_success();
        } else {
            wp_send_json_error( __( 'Failed to save: ', 'site-quality-check' ) . implode( ', ', $errors ) );
        }
    } // End ajax_save_settings()

} // End class Settings

Settings::instance();