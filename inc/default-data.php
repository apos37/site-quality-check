<?php
/**
 * DEFAULT DATA
 *
 * Seeds default checklist tabs, sections, and items on plugin activation.
 * Only runs once — respects existing user data on reactivation.
 */

namespace PluginRx\SiteQualityCheck;

if ( ! defined( 'ABSPATH' ) ) exit;


class DefaultData {

    /**
     * Option flag marking that default checklists have been seeded.
     */
    public const SEEDED_OPTION = 'sqc_defaults_seeded';


    /**
     * @var DefaultData|null Singleton instance
     */
    private static ?DefaultData $instance = null;


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
        add_action( 'sqc_activated', [ $this, 'maybe_seed' ] );
    } // End __construct()


    /**
     * Seed default checklists if not already done.
     *
     * @return void
     */
    public function maybe_seed() : void {
        if ( get_option( self::SEEDED_OPTION ) ) {
            return;
        }

        foreach ( $this->get_default_checklists() as $order => $checklist ) {
            Checklists::create( $checklist[ 'title' ], $checklist[ 'sections' ], $order );
        }

        update_option( self::SEEDED_OPTION, true );
    } // End maybe_seed()


    /**
     * Default checklist definitions: Developer, Designer, Content Editor.
     *
     * @return array
     */
    private function get_default_checklists() : array {
        return [
            [
                'title'    => __( 'Developer', 'site-quality-check' ),
                'sections' => [
                    $this->build_section( __( 'Weekly', 'site-quality-check' ), 'weekly', [
                        __( 'Update plugins and themes', 'site-quality-check' ),
                        __( 'Check for broken links', 'site-quality-check' ),
                        __( 'Review error/debug logs', 'site-quality-check' ),
                    ] ),
                    $this->build_section( __( 'Monthly', 'site-quality-check' ), 'monthly', [
                        __( 'Verify staging/backup restore works', 'site-quality-check' ),
                        __( 'Check SSL certificate expiration', 'site-quality-check' ),
                        __( 'Review PHP and WordPress core version support', 'site-quality-check' ),
                    ] ),
                    $this->build_section( __( 'Quarterly', 'site-quality-check' ), 'quarterly', [
                        __( 'Audit installed plugins for unused/abandoned ones', 'site-quality-check' ),
                        __( 'Review hosting performance and uptime reports', 'site-quality-check' ),
                    ] ),
                ],
            ],
            [
                'title'    => __( 'Designer', 'site-quality-check' ),
                'sections' => [
                    $this->build_section( __( 'Monthly', 'site-quality-check' ), 'monthly', [
                        __( 'Confirm homepage visuals are current', 'site-quality-check' ),
                        __( 'Check responsive layout on mobile/tablet', 'site-quality-check' ),
                        __( 'Verify brand colors and logo are up to date', 'site-quality-check' ),
                    ] ),
                    $this->build_section( __( 'Quarterly', 'site-quality-check' ), 'quarterly', [
                        __( 'Review image alt text coverage', 'site-quality-check' ),
                        __( 'Check for outdated seasonal or promotional graphics', 'site-quality-check' ),
                    ] ),
                ],
            ],
            [
                'title'    => __( 'Content Editor', 'site-quality-check' ),
                'sections' => [
                    $this->build_section( __( 'Weekly', 'site-quality-check' ), 'weekly', [
                        __( 'Review and clear contact form spam', 'site-quality-check' ),
                        __( 'Test contact form submissions', 'site-quality-check' ),
                    ] ),
                    $this->build_section( __( 'Monthly', 'site-quality-check' ), 'monthly', [
                        __( 'Review stale content list', 'site-quality-check' ),
                        __( 'Verify staff/team page is current', 'site-quality-check' ),
                        __( 'Check that contact info routes to the correct people', 'site-quality-check' ),
                    ] ),
                    $this->build_section( __( 'Annually', 'site-quality-check' ), 'annually', [
                        __( 'Review privacy policy and terms of service dates', 'site-quality-check' ),
                        __( 'Audit copyright year in footer', 'site-quality-check' ),
                    ] ),
                ],
            ],
        ];
    } // End get_default_checklists()


    /**
     * Build a section array with items from a flat label list.
     *
     * @param string $label
     * @param string $recurrence
     * @param array $item_labels
     * @return array
     */
    private function build_section( string $label, string $recurrence, array $item_labels ) : array {
        $items = [];

        foreach ( $item_labels as $order => $item_label ) {
            $items[] = [
                'id'             => Helpers::generate_id( 'item' ),
                'label'          => $item_label,
                'order'          => $order,
                'status'         => 'incomplete',
                'last_completed' => null,
                'snoozed_until'  => null,
            ];
        }

        return [
            'id'         => Helpers::generate_id( 'section' ),
            'label'      => $label,
            'recurrence' => $recurrence,
            'order'      => 0,
            'items'      => $items,
        ];
    } // End build_section()

} // End class DefaultData

DefaultData::instance();