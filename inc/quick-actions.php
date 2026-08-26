<?php
/**
 * QUICK ACTIONS
 *
 * On-demand checks triggered from dashboard buttons: contact form test,
 * 404 check on key pages, robots.txt/sitemap reachability, SSL expiration.
 * Results are shown as a toast — nothing is logged or stored.
 */

namespace PluginRx\SiteQualityCheck;

if ( ! defined( 'ABSPATH' ) ) exit;


class QuickActions {

    /**
     * @var QuickActions|null Singleton instance
     */
    private static ?QuickActions $instance = null;


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
        add_action( 'wp_ajax_sqcheck_run_quick_action', [ $this, 'ajax_run' ] );
    } // End __construct()


    /**
     * Get the list of available quick actions for rendering buttons.
     *
     * @return array Keyed by action slug: [ 'label' => string, 'available' => bool ]
     */
    public static function get_available_actions() : array {
        return [
            'test_contact_form' => [
                'label'     => __( 'Test Contact Form', 'site-quality-check' ),
                'available' => Integrations::is_gravity_forms_active(),
            ],
            'check_404s' => [
                'label'     => __( 'Check Key Pages for 404s', 'site-quality-check' ),
                'available' => true,
            ],
            'check_robots_sitemap' => [
                'label'     => __( 'Check robots.txt & Sitemap', 'site-quality-check' ),
                'available' => true,
            ],
            'check_ssl' => [
                'label'     => __( 'Check SSL Expiration', 'site-quality-check' ),
                'available' => is_ssl(),
            ],
        ];
    } // End get_available_actions()


    /**
     * AJAX handler: run a quick action and return a toast-friendly result.
     *
     * @return void
     */
    public function ajax_run() : void {
        check_ajax_referer( 'sqcheck_quick_actions_nonce', 'nonce' );

        if ( ! Access::can_manage() ) {
            wp_send_json_error( [ 'message' => __( 'You do not have permission to do this.', 'site-quality-check' ) ], 403 );
        }

        $action = sanitize_text_field( wp_unslash( $_POST[ 'quick_action' ] ?? '' ) );

        $result = match ( $action ) {
            'test_contact_form'     => $this->test_contact_form(),
            'check_404s'            => $this->check_404s(),
            'check_robots_sitemap'  => $this->check_robots_sitemap(),
            'check_ssl'             => $this->check_ssl(),
            default                 => null,
        };

        if ( null === $result ) {
            wp_send_json_error( [ 'message' => __( 'Unknown action.', 'site-quality-check' ) ] );
        }

        wp_send_json_success( $result );
    } // End ajax_run()


    /**
     * Test the site's Gravity Forms contact form by validating its notification routing.
     *
     * @return array [ 'success' => bool, 'message' => string ]
     */
    private function test_contact_form() : array {
        if ( ! Integrations::is_gravity_forms_active() ) {
            return [ 'success' => false, 'message' => __( 'Gravity Forms is not active.', 'site-quality-check' ) ];
        }

        $selected_form_id = (int) get_option( 'sqcheck_contact_form_id', 0 );
        $forms = $selected_form_id ? [ \GFAPI::get_form( $selected_form_id ) ] : \GFAPI::get_forms();
        $forms = array_filter( $forms );

        if ( empty( $forms ) ) {
            return [ 'success' => false, 'message' => __( 'No forms found.', 'site-quality-check' ) ];
        }

        $issues = [];

        foreach ( $forms as $form ) {
            $notifications = $form[ 'notifications' ] ?? [];

            if ( empty( $notifications ) ) {
                $issues[] = sprintf(
                    /* translators: %s: form title */
                    __( '"%s" has no notifications configured.', 'site-quality-check' ),
                    $form[ 'title' ]
                );
                continue;
            }

            foreach ( $notifications as $notification ) {
                $to = $notification[ 'to' ] ?? '';

                if ( '' === trim( $to ) ) {
                    $issues[] = sprintf(
                        /* translators: 1: form title, 2: notification name */
                        __( '"%1$s" notification "%2$s" has a blank recipient.', 'site-quality-check' ),
                        $form[ 'title' ],
                        $notification[ 'name' ] ?? ''
                    );
                }
            }
        }

        if ( ! empty( $issues ) ) {
            return [ 'success' => false, 'message' => implode( ' ', $issues ) ];
        }

        return [ 'success' => true, 'message' => __( 'All forms have valid notification routing.', 'site-quality-check' ) ];
    } // End test_contact_form()


    /**
     * Check key pages (homepage, contact page) for 404s.
     *
     * @return array [ 'success' => bool, 'message' => string ]
     */
    private function check_404s() : array {
        $urls = [ home_url( '/' ) ];

        $contact_page_id = (int) get_option( 'sqcheck_contact_page_id', 0 );

        if ( $contact_page_id && get_post_status( $contact_page_id ) === 'publish' ) {
            $urls[] = get_permalink( $contact_page_id );
        }

        $failures = [];

        foreach ( $urls as $url ) {
            $response = wp_remote_get( $url, [ 'timeout' => 10 ] );

            if ( is_wp_error( $response ) ) {
                $failures[] = $url . ' — ' . $response->get_error_message();
                continue;
            }

            $code = wp_remote_retrieve_response_code( $response );

            if ( $code >= 400 ) {
                $failures[] = $url . ' — HTTP ' . $code;
            }
        }

        if ( ! empty( $failures ) ) {
            return [ 'success' => false, 'message' => implode( ' | ', $failures ) ];
        }

        return [ 'success' => true, 'message' => __( 'All key pages returned successfully.', 'site-quality-check' ) ];
    } // End check_404s()


    /**
     * Check robots.txt and sitemap.xml are reachable.
     *
     * @return array [ 'success' => bool, 'message' => string ]
     */
    private function check_robots_sitemap() : array {
        $checks = [
            'robots.txt'  => home_url( '/robots.txt' ),
            'sitemap.xml' => home_url( '/sitemap.xml' ),
        ];

        $failures = [];

        foreach ( $checks as $label => $url ) {
            $response = wp_remote_get( $url, [ 'timeout' => 10 ] );

            if ( is_wp_error( $response ) ) {
                $failures[] = $label . ' — ' . $response->get_error_message();
                continue;
            }

            $code = wp_remote_retrieve_response_code( $response );

            if ( $code >= 400 ) {
                $failures[] = $label . ' — HTTP ' . $code;
            }
        }

        if ( ! empty( $failures ) ) {
            return [ 'success' => false, 'message' => implode( ' | ', $failures ) ];
        }

        return [ 'success' => true, 'message' => __( 'robots.txt and sitemap are both reachable.', 'site-quality-check' ) ];
    } // End check_robots_sitemap()


    /**
     * Check SSL certificate expiration for the site's domain.
     *
     * @return array [ 'success' => bool, 'message' => string ]
     */
    private function check_ssl() : array {
        $host = wp_parse_url( home_url(), PHP_URL_HOST );

        if ( ! $host ) {
            return [ 'success' => false, 'message' => __( 'Could not determine site host.', 'site-quality-check' ) ];
        }

        $context = stream_context_create( [ 'ssl' => [ 'capture_peer_cert' => true, 'verify_peer' => false, 'verify_peer_name' => false ] ] );
        $socket = @stream_socket_client( 'ssl://' . $host . ':443', $errno, $errstr, 10, STREAM_CLIENT_CONNECT, $context );

        if ( ! $socket ) {
            return [ 'success' => false, 'message' => __( 'Could not connect to check SSL certificate.', 'site-quality-check' ) ];
        }

        $params = stream_context_get_params( $socket );
        $cert = openssl_x509_parse( $params[ 'options' ][ 'ssl' ][ 'peer_certificate' ] );
        fclose( $socket ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- this is a network socket, not a filesystem handle.

        if ( ! $cert || empty( $cert[ 'validTo_time_t' ] ) ) {
            return [ 'success' => false, 'message' => __( 'Could not read SSL certificate.', 'site-quality-check' ) ];
        }

        $days_remaining = (int) floor( ( $cert[ 'validTo_time_t' ] - time() ) / DAY_IN_SECONDS );

        if ( $days_remaining <= 14 ) {
            return [ 'success' => false, 'message' => sprintf(
                /* translators: %d: days until expiration */
                __( 'SSL certificate expires in %d days.', 'site-quality-check' ),
                $days_remaining
            ) ];
        }

        return [ 'success' => true, 'message' => sprintf(
            /* translators: %d: days until expiration */
            __( 'SSL certificate is valid for %d more days.', 'site-quality-check' ),
            $days_remaining
        ) ];
    } // End check_ssl()

} // End class QuickActions

QuickActions::instance();