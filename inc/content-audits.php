<?php
/**
 * CONTENT AUDITS
 *
 * Yoast SEO missing-meta report. Only active if Yoast SEO is installed.
 */

namespace PluginRx\SiteQualityCheck;

if ( ! defined( 'ABSPATH' ) ) exit;


class ContentAudits {

    /**
     * @var ContentAudits|null Singleton instance
     */
    private static ?ContentAudits $instance = null;


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
        // No hooks needed beyond the menu registration handled in menu.php.
    } // End __construct()


    /**
     * Get all published posts (from included post types) missing a Yoast title or meta description.
     *
     * @return array Array of [ 'post' => WP_Post, 'missing_title' => bool, 'missing_description' => bool ]
     */
    public static function get_missing_yoast_meta() : array {
        if ( ! Integrations::is_yoast_active() ) {
            return [];
        }

        $post_types = StaleContent::get_included_post_types();

        $posts = get_posts( [
            'post_type'      => $post_types,
            'post_status'    => 'publish',
            'posts_per_page' => -1,
        ] );

        $results = [];

        foreach ( $posts as $post ) {
            $title = Integrations::get_yoast_title( $post->ID );
            $description = Integrations::get_yoast_meta_description( $post->ID );

            $missing_title = '' === trim( $title );
            $missing_description = '' === trim( $description );

            if ( $missing_title || $missing_description ) {
                $results[] = [
                    'post'                => $post,
                    'missing_title'       => $missing_title,
                    'missing_description' => $missing_description,
                ];
            }
        }

        return $results;
    } // End get_missing_yoast_meta()


    /**
     * Get all images (from post content and featured images) missing alt text.
     *
     * @return array Array of [ 'post' => WP_Post, 'image_src' => string ]
     */
    public static function get_missing_alt_text() : array {
        $post_types = StaleContent::get_included_post_types();

        $posts = get_posts( [
            'post_type'      => $post_types,
            'post_status'    => 'publish',
            'posts_per_page' => -1,
        ] );

        $results = [];

        foreach ( $posts as $post ) {
            $featured_id = get_post_thumbnail_id( $post );

            if ( $featured_id ) {
                $alt = get_post_meta( $featured_id, '_wp_attachment_image_alt', true );

                if ( '' === trim( (string) $alt ) ) {
                    $results[] = [
                        'post'      => $post,
                        'image_src' => wp_get_attachment_url( $featured_id ),
                    ];
                }
            }

            if ( preg_match_all( '/<img[^>]+>/i', $post->post_content, $tags ) ) {
                foreach ( $tags[ 0 ] as $tag ) {
                    if ( preg_match( '/alt=["\']([^"\']*)["\']/i', $tag, $alt_match ) ) {
                        if ( '' !== trim( $alt_match[ 1 ] ) ) {
                            continue;
                        }
                    }

                    preg_match( '/src=["\']([^"\']*)["\']/i', $tag, $src_match );

                    $results[] = [
                        'post'      => $post,
                        'image_src' => $src_match[ 1 ] ?? '',
                    ];
                }
            }
        }

        return $results;
    } // End get_missing_alt_text()


    /**
     * Get all published posts (from included post types) that no other post links to internally.
     * Excludes the homepage and any page set as the posts page.
     *
     * @return array Array of WP_Post
     */
    public static function get_orphaned_pages() : array {
        $post_types = StaleContent::get_included_post_types();

        $posts = get_posts( [
            'post_type'      => $post_types,
            'post_status'    => 'publish',
            'posts_per_page' => -1,
        ] );

        $home_id = (int) get_option( 'page_on_front' );
        $blog_id = (int) get_option( 'page_for_posts' );

        $all_content = '';

        foreach ( $posts as $post ) {
            $all_content .= ' ' . $post->post_content;
        }

        $results = [];

        foreach ( $posts as $post ) {
            if ( $post->ID === $home_id || $post->ID === $blog_id ) {
                continue;
            }

            $permalink = get_permalink( $post );
            $path = wp_parse_url( $permalink, PHP_URL_PATH );

            if ( ! $path || false !== strpos( $all_content, $path ) ) {
                continue;
            }

            $results[] = $post;
        }

        return $results;
    } // End get_orphaned_pages()


    /**
     * Get all published posts (from included post types) containing http:// references
     * on an HTTPS site — mixed content risk.
     *
     * @return array Array of [ 'post' => WP_Post, 'urls' => array ]
     */
    public static function get_mixed_content() : array {
        if ( ! is_ssl() && 'https' !== wp_parse_url( home_url(), PHP_URL_SCHEME ) ) {
            return [];
        }

        $post_types = StaleContent::get_included_post_types();

        $posts = get_posts( [
            'post_type'      => $post_types,
            'post_status'    => 'publish',
            'posts_per_page' => -1,
        ] );

        $results = [];

        foreach ( $posts as $post ) {
            $urls = [];

            if ( preg_match_all( '/(src|href)=["\']http:\/\/[^"\']+["\']/i', $post->post_content, $matches ) ) {
                $urls = array_merge( $urls, $matches[ 0 ] );
            }

            $featured_id = get_post_thumbnail_id( $post );

            if ( $featured_id ) {
                $featured_url = wp_get_attachment_url( $featured_id );

                if ( $featured_url && 0 === strpos( $featured_url, 'http://' ) ) {
                    $urls[] = $featured_url;
                }
            }

            if ( ! empty( $urls ) ) {
                $results[] = [
                    'post' => $post,
                    'urls' => array_unique( $urls ),
                ];
            }
        }

        return $results;
    } // End get_mixed_content()


        /**
     * Render the Content Audits admin page.
     *
     * @return void
     */
    public static function render_page() : void {
        if ( ! current_user_can( 'edit_posts' ) ) {
            wp_die( esc_html__( 'You do not have permission to view this page.', 'site-quality-check' ) );
        }
        ?>
        <div class="wrap sqc-content-wrap sqc-content-audits">
            <?php if ( ! Integrations::is_yoast_active() ) : ?>
                <h2><?php esc_html_e( 'SEO Meta', 'site-quality-check' ); ?></h2>
                <p><?php esc_html_e( 'Yoast SEO is not active. Install it to see missing meta title and description reports.', 'site-quality-check' ); ?></p>
            <?php else : ?>
                <?php self::render_yoast_section(); ?>
            <?php endif; ?>

            <?php self::render_alt_text_section(); ?>
            <?php self::render_orphaned_pages_section(); ?>
            <?php self::render_mixed_content_section(); ?>
        </div>
        <?php
    } // End render_page()


    /**
     * Render the missing Yoast meta section.
     *
     * @return void
     */
    private static function render_yoast_section() : void {
        $missing = self::get_missing_yoast_meta();
        ?>
        <h2><?php esc_html_e( 'SEO Meta', 'site-quality-check' ); ?></h2>

        <?php if ( empty( $missing ) ) : ?>
            <p><?php esc_html_e( 'All published content has a title and meta description set.', 'site-quality-check' ); ?></p>
        <?php else : ?>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php esc_html_e( 'Title', 'site-quality-check' ); ?></th>
                        <th><?php esc_html_e( 'Type', 'site-quality-check' ); ?></th>
                        <th><?php esc_html_e( 'Missing', 'site-quality-check' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $missing as $item ) : ?>
                        <?php
                        $post = $item[ 'post' ];
                        $missing_labels = [];

                        if ( $item[ 'missing_title' ] ) {
                            $missing_labels[] = __( 'SEO Title', 'site-quality-check' );
                        }

                        if ( $item[ 'missing_description' ] ) {
                            $missing_labels[] = __( 'Meta Description', 'site-quality-check' );
                        }
                        ?>
                        <tr>
                            <td><a href="<?php echo esc_url( get_edit_post_link( $post->ID ) ); ?>"><?php echo esc_html( get_the_title( $post ) ); ?></a></td>
                            <td><?php echo esc_html( get_post_type_object( $post->post_type )->labels->singular_name ); ?></td>
                            <td><?php echo esc_html( implode( ', ', $missing_labels ) ); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
        <?php
    } // End render_yoast_section()


    /**
     * Render the missing alt text section.
     *
     * @return void
     */
    private static function render_alt_text_section() : void {
        $missing = self::get_missing_alt_text();
        ?>
        <h2><?php esc_html_e( 'Missing Image Alt Text', 'site-quality-check' ); ?></h2>

        <?php if ( empty( $missing ) ) : ?>
            <p><?php esc_html_e( 'No images are missing alt text.', 'site-quality-check' ); ?></p>
        <?php else : ?>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php esc_html_e( 'Post', 'site-quality-check' ); ?></th>
                        <th><?php esc_html_e( 'Image', 'site-quality-check' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $missing as $item ) : ?>
                        <tr>
                            <td><a href="<?php echo esc_url( get_edit_post_link( $item[ 'post' ]->ID ) ); ?>"><?php echo esc_html( get_the_title( $item[ 'post' ] ) ); ?></a></td>
                            <td><?php echo esc_html( $item[ 'image_src' ] ); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
        <?php
    } // End render_alt_text_section()


    /**
     * Render the orphaned pages section.
     *
     * @return void
     */
    private static function render_orphaned_pages_section() : void {
        $orphaned = self::get_orphaned_pages();
        ?>
        <h2><?php esc_html_e( 'Orphaned Pages', 'site-quality-check' ); ?></h2>

        <?php if ( empty( $orphaned ) ) : ?>
            <p><?php esc_html_e( 'No orphaned pages found.', 'site-quality-check' ); ?></p>
        <?php else : ?>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php esc_html_e( 'Title', 'site-quality-check' ); ?></th>
                        <th><?php esc_html_e( 'Type', 'site-quality-check' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $orphaned as $post ) : ?>
                        <tr>
                            <td><a href="<?php echo esc_url( get_edit_post_link( $post->ID ) ); ?>"><?php echo esc_html( get_the_title( $post ) ); ?></a></td>
                            <td><?php echo esc_html( get_post_type_object( $post->post_type )->labels->singular_name ); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
        <?php
    } // End render_orphaned_pages_section()


    /**
     * Render the mixed content section.
     *
     * @return void
     */
    private static function render_mixed_content_section() : void {
        $mixed = self::get_mixed_content();
        ?>
        <h2><?php esc_html_e( 'Mixed Content', 'site-quality-check' ); ?></h2>

        <?php if ( empty( $mixed ) ) : ?>
            <p><?php esc_html_e( 'No mixed content found.', 'site-quality-check' ); ?></p>
        <?php else : ?>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php esc_html_e( 'Post', 'site-quality-check' ); ?></th>
                        <th><?php esc_html_e( 'Insecure URLs', 'site-quality-check' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $mixed as $item ) : ?>
                        <tr>
                            <td><a href="<?php echo esc_url( get_edit_post_link( $item[ 'post' ]->ID ) ); ?>"><?php echo esc_html( get_the_title( $item[ 'post' ] ) ); ?></a></td>
                            <td><?php echo esc_html( implode( ', ', $item[ 'urls' ] ) ); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
        <?php
    } // End render_mixed_content_section()

} // End class ContentAudits

ContentAudits::instance();