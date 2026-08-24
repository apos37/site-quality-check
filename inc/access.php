<?php
/**
 * ACCESS CONTROL
 *
 * Handles capability checks for viewing/managing checklists and plugin admin screens.
 */

namespace PluginRx\SiteQualityCheck;

if ( ! defined( 'ABSPATH' ) ) exit;


class Access {

    /**
     * Capability required for full plugin management (settings, advanced, integrations).
     */
    public const MANAGE_CAP = 'manage_options';


    /**
     * @var Access|null Singleton instance
     */
    private static ?Access $instance = null;


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
        add_action( 'init', [ $this, 'maybe_register_role_options' ] );
    } // End __construct()


    /**
     * Check if the current user can manage plugin settings (settings, advanced, integrations pages).
     *
     * @return bool
     */
    public static function can_manage() : bool {
        return current_user_can( self::MANAGE_CAP );
    } // End can_manage()


    /**
     * Check if the current user can view/interact with a specific checklist.
     *
     * Admins always have access regardless of the checklist's assigned roles.
     *
     * @param array $allowed_roles Roles assigned to the checklist (stored in _sqc_access meta).
     * @param int|null $user_id Optional user ID, defaults to current user.
     * @return bool
     */
    public static function can_access_checklist( array $allowed_roles, ?int $user_id = null ) : bool {
        $user = $user_id ? get_userdata( $user_id ) : wp_get_current_user();

        if ( ! $user || ! $user->exists() ) {
            return false;
        }

        if ( user_can( $user, self::MANAGE_CAP ) ) {
            return true;
        }

        if ( empty( $allowed_roles ) ) {
            return true;
        }

        $user_roles = (array) $user->roles;

        return count( array_intersect( $allowed_roles, $user_roles ) ) > 0;
    } // End can_access_checklist()


    /**
     * Filter a list of checklist posts down to only those the current user can access.
     *
     * @param array $checklists Array of WP_Post objects for the site_quality_checklist CPT.
     * @return array
     */
    public static function filter_accessible_checklists( array $checklists ) : array {
        return array_values( array_filter( $checklists, function ( $checklist ) {
            $allowed_roles = get_post_meta( $checklist->ID, '_sqc_access', true );
            $allowed_roles = is_array( $allowed_roles ) ? $allowed_roles : [];

            return self::can_access_checklist( $allowed_roles );
        } ) );
    } // End filter_accessible_checklists()


    /**
     * Get all available roles for the access control UI (checklist settings, tab settings).
     *
     * @return array Associative array of role_slug => role_label.
     */
    public static function get_assignable_roles() : array {
        $wp_roles = wp_roles();
        $roles = [];

        foreach ( $wp_roles->roles as $slug => $role ) {
            $roles[ $slug ] = translate_user_role( $role[ 'name' ] );
        }

        return $roles;
    } // End get_assignable_roles()


    /**
     * Placeholder for future role-based option registration (e.g. per-role default tab).
     * Currently unused, reserved for future settings expansion.
     *
     * @return void
     */
    public function maybe_register_role_options() : void {
        // Reserved for future per-role default settings.
    } // End maybe_register_role_options()

} // End class Access