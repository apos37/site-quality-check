<?php
/**
 * UNINSTALL
 *
 * Fires only on full plugin deletion via the WordPress Plugins screen.
 * Clears all plugin data only if the user opted in via the Advanced settings.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) exit;

if ( ! get_option( 'sqc_clear_data_on_uninstall' ) ) {
    return;
}

$sqc_checklists = get_posts( [
    'post_type'      => 'sqc_checklist',
    'posts_per_page' => -1,
    'post_status'    => 'any',
    'fields'         => 'ids',
] );

foreach ( $sqc_checklists as $sqc_checklist_id ) {
    wp_delete_post( $sqc_checklist_id, true );
}

$sqc_omitted_posts = get_posts( [
    'post_type'      => 'any',
    'posts_per_page' => -1,
    'post_status'    => 'any',
    'fields'         => 'ids',
    'meta_query'     => [ // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
        [
            'key'     => 'sqc_stale_omitted',
            'compare' => 'EXISTS',
        ],
    ],
] );

foreach ( $sqc_omitted_posts as $sqc_post_id ) {
    delete_post_meta( $sqc_post_id, 'sqc_stale_omitted' );
}

global $wpdb;
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}sqc_audit_results" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.SchemaChange

delete_option( 'sqc_stale_thresholds' );
delete_option( 'sqc_stale_post_types' );
delete_option( 'sqc_contact_page_id' );
delete_option( 'sqc_contact_form_id' );
delete_option( 'sqc_enabled_quick_actions' );
delete_option( 'sqc_defaults_seeded' );
delete_option( 'sqc_activated_time' );
delete_option( 'sqc_clear_data_on_uninstall' );
delete_option( 'sqc_menu_title' );
delete_option( 'sqc_page_title' );
delete_option( 'sqc_menu_icon' );
delete_option( 'sqc_logo' );
delete_option( 'sqc_allowed_roles' );
delete_option( 'sqc_db_version' );

wp_clear_scheduled_hook( 'sqc_recurrence_check' );