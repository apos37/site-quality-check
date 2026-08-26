<?php
/**
 * HELPERS
 *
 * Shared utility functions used across the plugin.
 */

namespace PluginRx\SiteQualityCheck;

if ( ! defined( 'ABSPATH' ) ) exit;


class Helpers {

    /**
     * Format a Unix timestamp using WordPress's configured date format.
     *
     * @param int|null $timestamp
     * @return string
     */
    public static function format_date( ?int $timestamp ) : string {
        if ( empty( $timestamp ) ) {
            return '';
        }

        return date_i18n( get_option( 'date_format' ), $timestamp );
    } // End format_date()


    /**
     * Format a Unix timestamp using WordPress's configured date and time format.
     *
     * @param int|null $timestamp
     * @return string
     */
    public static function format_datetime( ?int $timestamp ) : string {
        if ( empty( $timestamp ) ) {
            return '';
        }

        return date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $timestamp );
    } // End format_datetime()


    /**
     * Generate a unique, prefixed ID for checklist sections/items.
     *
     * @param string $prefix
     * @return string
     */
    public static function generate_id( string $prefix ) : string {
        return $prefix . '_' . uniqid();
    } // End generate_id()


    /**
     * Get a human-readable label for a recurrence key.
     *
     * @param string $recurrence
     * @return string
     */
    public static function recurrence_label( string $recurrence ) : string {
        $labels = [
            'daily'     => __( 'Daily', 'site-quality-check' ),
            'weekly'    => __( 'Weekly', 'site-quality-check' ),
            'monthly'   => __( 'Monthly', 'site-quality-check' ),
            'quarterly' => __( 'Quarterly', 'site-quality-check' ),
            'annually'  => __( 'Annually', 'site-quality-check' ),
        ];

        return $labels[ $recurrence ] ?? ucfirst( $recurrence );
    } // End recurrence_label()


    /**
     * Get a human-readable label for a staleness tier.
     *
     * @param string $tier
     * @return string
     */
    public static function tier_label( string $tier ) : string {
        $labels = [
            'warning'  => __( 'Warning', 'site-quality-check' ),
            'danger'   => __( 'Danger', 'site-quality-check' ),
            'critical' => __( 'Critical', 'site-quality-check' ),
        ];

        return $labels[ $tier ] ?? ucfirst( $tier );
    } // End tier_label()


    /**
     * Check whether the current admin screen belongs to this plugin.
     *
     * @return bool
     */
    public static function is_plugin_screen() : bool {
        $screen = get_current_screen();

        if ( ! $screen ) {
            return false;
        }

        return false !== strpos( $screen->id, Menu::MENU_SLUG );
    } // End is_plugin_screen()


    /**
     * Get a curated list of common Dashicons for the menu icon select field.
     *
     * @return array Associative array of dashicons-{slug} => Label
     */
    public static function get_dashicons() {
        $dashicons = [
            'menu', 'admin-site', 'dashboard', 'admin-media', 'admin-page', 'admin-comments', 'admin-appearance', 'admin-plugins', 'admin-users', 'admin-tools', 'admin-settings',
            'admin-network', 'admin-generic', 'admin-home', 'admin-collapse', 'filter', 'admin-customizer', 'admin-multisite', 'admin-links', 'format-links', 'admin-post',
            'format-standard', 'format-image', 'format-gallery', 'format-audio', 'format-video', 'format-chat', 'format-status', 'format-aside', 'format-quote', 'welcome-write-blog',
            'welcome-edit-page', 'welcome-add-page', 'welcome-view-site', 'welcome-widgets-menus', 'welcome-comments', 'welcome-learn-more', 'image-crop', 'image-rotate', 'image-rotate-left',
            'image-rotate-right', 'image-flip-vertical', 'image-flip-horizontal', 'image-filter', 'undo', 'redo', 'editor-bold', 'editor-italic', 'editor-ul', 'editor-ol', 'editor-quote',
            'editor-alignleft', 'editor-aligncenter', 'editor-alignright', 'editor-insertmore', 'editor-spellcheck', 'editor-expand', 'editor-contract',
            'editor-kitchensink', 'editor-underline', 'editor-justify', 'editor-textcolor', 'editor-paste-word', 'editor-paste-text', 'editor-removeformatting', 'editor-video',
            'editor-customchar', 'editor-outdent', 'editor-indent', 'editor-help', 'editor-strikethrough', 'editor-unlink', 'editor-rtl', 'editor-break', 'editor-code', 'editor-paragraph',
            'editor-table', 'align-left', 'align-right', 'align-center', 'align-none', 'lock', 'unlock', 'calendar', 'calendar-alt', 'visibility', 'hidden', 'post-status', 'edit',
            'post-trash', 'trash', 'sticky', 'external', 'arrow-up', 'arrow-down', 'arrow-left', 'arrow-right', 'arrow-up-alt', 'arrow-down-alt', 'arrow-left-alt', 'arrow-right-alt',
            'arrow-up-alt2', 'arrow-down-alt2', 'arrow-left-alt2', 'arrow-right-alt2', 'leftright', 'sort', 'randomize', 'list-view', 'excerpt-view', 'grid-view', 'hammer', 'art', 'migrate',
            'performance', 'universal-access', 'universal-access-alt', 'tickets', 'nametag', 'clipboard', 'heart', 'megaphone', 'schedule', 'wordpress', 'wordpress-alt', 'pressthis', 'update',
            'screenoptions', 'cart', 'feedback', 'cloud', 'translation', 'tag', 'category', 'archive', 'tagcloud', 'text', 'media-archive', 'media-audio', 'media-code', 'media-default',
            'media-document', 'media-interactive', 'media-spreadsheet', 'media-text', 'media-video', 'playlist-audio', 'playlist-video', 'controls-play', 'controls-pause', 'controls-forward',
            'controls-skipforward', 'controls-back', 'controls-skipback', 'controls-repeat', 'controls-volumeon', 'controls-volumeoff', 'yes', 'no', 'no-alt', 'plus', 'plus-alt',
            'plus-alt2', 'minus', 'dismiss', 'marker', 'star-filled', 'star-half', 'star-empty', 'flag', 'info', 'warning', 'share', 'share1', 'share-alt', 'share-alt2', 'twitter', 'rss',
            'email', 'email-alt', 'facebook', 'facebook-alt', 'networking', 'googleplus', 'location', 'location-alt', 'camera', 'images-alt', 'images-alt2', 'video-alt', 'video-alt2', 'video-alt3',
            'vault', 'shield', 'shield-alt', 'sos', 'search', 'slides', 'analytics', 'chart-pie', 'chart-bar', 'chart-line', 'chart-area', 'groups', 'businessman', 'id', 'id-alt', 'products',
            'awards', 'forms', 'testimonial', 'portfolio', 'book', 'book-alt', 'download', 'upload', 'backup', 'clock', 'lightbulb', 'microphone', 'desktop', 'tablet', 'smartphone', 'phone',
            'smiley', 'index-card', 'carrot', 'building', 'store', 'album', 'palmtree', 'tickets-alt', 'money', 'thumbs-up', 'thumbs-down', 'layout', 'align-pull-left', 'align-pull-right',
            'block-default', 'cloud-saved', 'cloud-upload', 'columns', 'cover-image', 'embed-audio', 'embed-generic', 'embed-photo', 'embed-post', 'embed-video', 'exit', 'html', 'info-outline',
            'insert-after', 'insert-before', 'insert', 'remove', 'shortcode', 'table-col-after', 'table-col-before', 'table-col-delete', 'table-row-after', 'table-row-before', 'table-row-delete',
            'saved', 'amazon', 'google', 'linkedin', 'pinterest', 'podio', 'reddit', 'spotify', 'twitch', 'whatsapp', 'xing', 'youtube', 'database-add', 'database-export', 'database-import',
            'database-remove', 'database-view', 'database', 'bell', 'airplane', 'car', 'calculator', 'printer', 'beer', 'coffee', 'drumstick', 'food', 'bank', 'hourglass', 'money-alt',
            'open-folder', 'pdf', 'pets', 'privacy', 'superhero', 'superhero-alt', 'edit-page', 'fullscreen-alt', 'fullscreen-exit-alt'
        ];

        $dashicons = apply_filters( 'helpdocs_dashicons', $dashicons );
        $dashicons = apply_filters( 'sqc_dashicons', $dashicons );
        sort( $dashicons );
        return $dashicons;
    } // End get_dashicons()


    /**
     * Render a tooltip icon with hover text, matching Admin Help Docs' pattern.
     *
     * @param string $text
     * @return void
     */
    public static function tooltip( string $text ) : void {
        if ( '' === $text ) {
            return;
        }
        ?>
        <span class="sqc-tooltip">
            <span class="dashicons dashicons-editor-help"></span>
            <span class="sqc-tooltip-text"><?php echo wp_kses_post( $text ); ?></span>
        </span>
        <?php
    } // End tooltip()

} // End class Helpers