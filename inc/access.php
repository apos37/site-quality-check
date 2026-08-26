<?php
/**
 * ACCESS CONTROL
 *
 * A single setting controls which roles (beyond administrators) can use
 * the plugin. Anyone with access can see and edit everything — there is
 * no per-checklist or per-feature restriction.
 */

namespace PluginRx\SiteQualityCheck;

if ( ! defined( 'ABSPATH' ) ) exit;


class Access {

    /**
     * Capability required for settings/advanced/integrations pages specifically.
     * Access to the plugin overall is broader (see can_access()); this narrower
     * check still gates configuration screens to administrators only.
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
        // No hooks needed.
    } // End __construct()


    /**
     * Check if the current user can manage plugin settings (Settings page, which includes Advanced).
     *
     * @return bool
     */
    public static function can_manage() : bool {
        return current_user_can( self::MANAGE_CAP );
    } // End can_manage()


    /**
     * Check if the current user can access the plugin at all: Dashboard, Checklists,
     * Stale Content, Content Audits, Integrations. Administrators always have access;
     * additional roles are granted via the sqc_allowed_roles setting.
     *
     * @param int|null $user_id Optional user ID, defaults to current user.
     * @return bool
     */
    public static function can_access( ?int $user_id = null ) : bool {
        $user = $user_id ? get_userdata( $user_id ) : wp_get_current_user();

        if ( ! $user || ! $user->exists() ) {
            return false;
        }

        if ( user_can( $user, self::MANAGE_CAP ) ) {
            return true;
        }

        $allowed_roles = get_option( 'sqc_allowed_roles', [] );

        if ( empty( $allowed_roles ) ) {
            return false;
        }

        return count( array_intersect( $allowed_roles, (array) $user->roles ) ) > 0;
    } // End can_access()


    /**
     * Get all available roles for the access control setting UI.
     *
     * @return array Associative array of role_slug => role_label.
     */
    public static function get_assignable_roles() : array {
        $wp_roles = wp_roles();
        $roles = [];

        foreach ( $wp_roles->roles as $slug => $role ) {
            if ( 'administrator' === $slug ) {
                continue;
            }

            $roles[ $slug ] = translate_user_role( $role[ 'name' ] );
        }

        return $roles;
    } // End get_assignable_roles()

} // End class Access

Access::instance();