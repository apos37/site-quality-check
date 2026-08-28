<?php
/**
 * INTEGRATION: YOAST SEO
 */

namespace PluginRx\SiteQualityCheck;

if ( ! defined( 'ABSPATH' ) ) exit;


/**
 * Add Yoast SEO to the list of integrations.
 */
add_filter( 'sqcheck_integration_plugins', function ( array $plugins ) : array {
    $plugins[ 'wordpress-seo' ] = [
        'name'        => 'Yoast SEO',
        'author'      => 'Yoast',
        'file'        => 'wordpress-seo/wp-seo.php',
        'url'         => 'https://wordpress.org/plugins/wordpress-seo/',
        'description' => __( 'SEO title and meta description management.', 'site-quality-check' ),
        'integration' => __( 'Powers the SEO Meta audit under Content Audits, flagging pages missing a title or meta description.', 'site-quality-check' ),
        'logo'        => Bootstrap::url() . 'inc/integrations/yoast-seo/logo.png',
        'wp_repo'     => true,
    ];

    return $plugins;
} );


/**
 * Gate the integration code so it only runs if the plugin is active.
 */
if ( ! class_exists( '\WPSEO_Meta' ) && ! defined( 'WPSEO_VERSION' ) ) {
    return;
}


/**
 * Check if Yoast SEO is active. Kept as a plain function here since Content
 * Audits calls it directly to decide whether to run the seo_meta audit type.
 *
 * @return bool
 */
function sqcheck_is_yoast_active() : bool {
    return true; // this file only loads at all when Yoast is active, see guard above.
} // End sqcheck_is_yoast_active()


/**
 * Get Yoast meta description for a post, falling back to raw postmeta if the object API is unavailable.
 *
 * @param int $post_id
 * @return string
 */
function sqcheck_get_yoast_meta_description( int $post_id ) : string {
    if ( function_exists( 'YoastSEO' ) ) {
        $meta = YoastSEO()->meta->for_post( $post_id );

        if ( $meta && isset( $meta->meta_description ) ) {
            return (string) $meta->meta_description;
        }
    }

    return (string) get_post_meta( $post_id, '_yoast_wpseo_metadesc', true );
} // End sqcheck_get_yoast_meta_description()


/**
 * Get Yoast SEO title for a post, falling back to raw postmeta if the object API is unavailable.
 *
 * @param int $post_id
 * @return string
 */
function sqcheck_get_yoast_title( int $post_id ) : string {
    if ( function_exists( 'YoastSEO' ) ) {
        $meta = YoastSEO()->meta->for_post( $post_id );

        if ( $meta && isset( $meta->title ) ) {
            return (string) $meta->title;
        }
    }

    return (string) get_post_meta( $post_id, '_yoast_wpseo_title', true );
} // End sqcheck_get_yoast_title()