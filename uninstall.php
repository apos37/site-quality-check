<?php
/**
 * UNINSTALL
 *
 * Fires only on full plugin deletion via the WordPress Plugins screen.
 * Clears all plugin data only if the user opted in via the Advanced settings.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) exit;

if ( ! get_option( 'sqcheck_clear_data_on_uninstall' ) ) {
    return;
}

$sqcheck_checklists = get_posts( [
    'post_type'      => 'sqcheck_checklist',
    'posts_per_page' => -1,
    'post_status'    => 'any',
    'fields'         => 'ids',
] );

foreach ( $sqcheck_checklists as $sqcheck_checklist_id ) {
    wp_delete_post( $sqcheck_checklist_id, true );
}

$sqcheck_omitted_posts = get_posts( [
    'post_type'      => 'any',
    'posts_per_page' => -1,
    'post_status'    => 'any',
    'fields'         => 'ids',
    'meta_query'     => [ // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
        [
            'key'     => 'sqcheck_stale_omitted',
            'compare' => 'EXISTS',
        ],
    ],
] );

foreach ( $sqcheck_omitted_posts as $sqcheck_post_id ) {
    delete_post_meta( $sqcheck_post_id, 'sqcheck_stale_omitted' );
}

global $wpdb;
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}sqcheck_audit_results" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.DirectDatabaseQuery.NoCaching -- schema drop on uninstall, not a cacheable operation.

delete_option( 'sqcheck_stale_thresholds' );
delete_option( 'sqcheck_stale_post_types' );
delete_option( 'sqcheck_contact_page_id' );
delete_option( 'sqcheck_contact_form_id' );
delete_option( 'sqcheck_enabled_quick_actions' );
delete_option( 'sqcheck_defaults_seeded' );
delete_option( 'sqcheck_activated_time' );
delete_option( 'sqcheck_clear_data_on_uninstall' );
delete_option( 'sqcheck_menu_title' );
delete_option( 'sqcheck_page_title' );
delete_option( 'sqcheck_menu_icon' );
delete_option( 'sqcheck_logo' );
delete_option( 'sqcheck_allowed_roles' );
delete_option( 'sqcheck_db_version' );

wp_clear_scheduled_hook( 'sqcheck_recurrence_check' );