<?php
/**
 * UNINSTALL
 *
 * Fires only on full plugin deletion via the WordPress Plugins screen.
 * Clears all plugin data only if the user opted in via the Advanced settings.
 */

namespace PluginRx\SiteQualityCheck;

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) exit;

if ( ! get_option( 'sqc_clear_data_on_uninstall' ) ) {
    return;
}

$checklists = get_posts( [
    'post_type'      => 'sqc_checklist',
    'posts_per_page' => -1,
    'post_status'    => 'any',
    'fields'         => 'ids',
] );

foreach ( $checklists as $checklist_id ) {
    wp_delete_post( $checklist_id, true );
}

$omitted_posts = get_posts( [
    'post_type'      => 'any',
    'posts_per_page' => -1,
    'post_status'    => 'any',
    'fields'         => 'ids',
    'meta_query'     => [
        [
            'key'     => '_sqc_stale_omitted',
            'compare' => 'EXISTS',
        ],
    ],
] );

foreach ( $omitted_posts as $post_id ) {
    delete_post_meta( $post_id, '_sqc_stale_omitted' );
}

delete_option( 'sqc_stale_thresholds' );
delete_option( 'sqc_stale_post_types' );
delete_option( 'sqc_contact_page_id' );
delete_option( 'sqc_contact_form_id' );
delete_option( 'sqc_enabled_quick_actions' );
delete_option( 'sqc_defaults_seeded' );
delete_option( 'sqc_activated_time' );
delete_option( 'sqc_clear_data_on_uninstall' );

wp_clear_scheduled_hook( 'sqc_recurrence_check' );