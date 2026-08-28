<?php
/**
 * INTEGRATION: ADMIN HELP DOCS
 */

namespace PluginRx\SiteQualityCheck;

use PhpToken;

if ( ! defined( 'ABSPATH' ) ) exit;


/**
 * Add the Admin Help Docs plugin to the list of integrations.
 */
add_filter( 'sqcheck_integration_plugins', function ( array $plugins ) : array {
    $plugins[ 'admin-help-docs' ] = [
        'name'        => 'Admin Help Docs',
        'author'      => 'PluginRx',
        'file'        => 'admin-help-docs/admin-help-docs.php',
        'url'         => 'https://wordpress.org/plugins/admin-help-docs/',
        'description' => __( 'Add documentation directly inside wp-admin.', 'site-quality-check' ),
        'integration' => __( 'Shares its color theme with Site Quality Check for a matching look, and can import ready-made help docs for using this plugin.', 'site-quality-check' ),
        'logo'        => Bootstrap::url() . 'inc/integrations/admin-help-docs/logo.png',
        'wp_repo'     => true,
    ];

    return $plugins;
} );


/**
 * Gate the integration code so it only runs if the plugin is active.
 */
if ( ! Integrations::is_active( 'admin-help-docs/admin-help-docs.php' ) ) {
    return;
}


/**
 * Add a quick action to import Site Quality Check's help docs into Admin Help Docs.
 */
add_filter( 'sqcheck_default_logo', function ( string $logo ) : string {
    $ahd_logo = get_option( 'helpdocs_logo', '' ); // phpcs:ignore

    return $ahd_logo ? sanitize_text_field( $ahd_logo ) : $logo;
} );


/**
 * Add Admin Help Docs' color theme.
 */
add_filter( 'sqcheck_theme_colors', function ( array $colors ) : array {
    $ahd_colors = get_option( 'helpdocs_colors', [] ); // phpcs:ignore

    if ( ! is_array( $ahd_colors ) || empty( $ahd_colors ) ) {
        return $colors;
    }

    $map = [
        'header_bg'       => 'header-bg',
        'header_font'     => 'header-font',
        'header_tab'      => 'header-tab',
        'header_tab_link' => 'header-tab-link',
        'doc_accent'      => 'accent',
        'button'          => 'button',
        'button_font'     => 'button-font',
        'button_hover'    => 'button-hover',
    ];

    foreach ( $map as $ahd_key => $sqcheck_key ) {
        if ( ! empty( $ahd_colors[ $ahd_key ] ) ) {
            $colors[ $sqcheck_key ] = sanitize_hex_color( $ahd_colors[ $ahd_key ] );
        }
    }

    return $colors;
} );


/**
 * Import Site Quality Check's help docs into Admin Help Docs under a
 * "Quality Check" folder, if not already imported.
 *
 * @return bool True on success, false if already imported or import failed.
 */
function sqcheck_import_admin_help_docs() : bool {
    if ( get_option( 'sqcheck_ahd_docs_imported' ) ) {
        return false;
    }

    if ( ! class_exists( '\HelpDocs' ) || ! class_exists( '\Folders' ) ) {
        return false;
    }

    $docs_file = Bootstrap::dir() . 'inc/integrations/admin-help-docs/load-docs.json';

    if ( ! file_exists( $docs_file ) ) {
        return false;
    }

    $docs = json_decode( file_get_contents( $docs_file ), true );

    if ( ! is_array( $docs ) || empty( $docs ) ) {
        return false;
    }

    $folder_taxonomy = \Folders::$taxonomy;
    $folder_name = __( 'Quality Check', 'site-quality-check' );
    $folder = get_term_by( 'name', $folder_name, $folder_taxonomy );

    if ( ! $folder ) {
        $new_folder = wp_insert_term( $folder_name, $folder_taxonomy );

        if ( is_wp_error( $new_folder ) ) {
            return false;
        }

        $folder_id = (int) $new_folder[ 'term_id' ];
    } else {
        $folder_id = (int) $folder->term_id;
    }

    foreach ( $docs as $doc ) {
        if ( empty( $doc[ 'title' ] ) || empty( $doc[ 'content' ] ) ) {
            continue;
        }

        $post_id = wp_insert_post( [
            'post_title'   => sanitize_text_field( $doc[ 'title' ] ),
            'post_content' => wp_kses_post( $doc[ 'content' ] ),
            'post_excerpt' => sanitize_text_field( $doc[ 'excerpt' ] ?? '' ),
            'post_status'  => 'publish',
            'post_type'    => \HelpDocs::$post_type,
        ] );

        if ( ! is_wp_error( $post_id ) ) {
            wp_set_object_terms( $post_id, [ $folder_id ], $folder_taxonomy );
        }
    }

    update_option( 'sqcheck_ahd_docs_imported', true );

    return true;
} // End sqcheck_import_admin_help_docs()


/**
 * AJAX: trigger the help docs import.
 */
add_action( 'wp_ajax_sqcheck_import_ahd_docs', function () : void {
    check_ajax_referer( 'sqcheck_integrations_nonce', 'nonce' );

    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( __( 'You do not have permission to do this.', 'site-quality-check' ) );
    }

    $imported = sqcheck_import_admin_help_docs();

    if ( ! $imported ) {
        wp_send_json_error( __( 'Docs already imported, or something went wrong.', 'site-quality-check' ) );
    }

    wp_send_json_success();
} );