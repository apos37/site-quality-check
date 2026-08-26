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
    public const SEEDED_OPTION = 'sqcheck_defaults_seeded';


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
        add_action( 'sqcheck_activated', [ $this, 'maybe_seed' ] );
    } // End __construct()


    /**
     * Seed default checklists if not already done (called on activation).
     *
     * @return void
     */
    public function maybe_seed() : void {
        if ( get_option( self::SEEDED_OPTION ) ) {
            return;
        }

        $this->seed_now();

        update_option( self::SEEDED_OPTION, true );
    } // End maybe_seed()


    /**
     * Actually create the default checklists, regardless of the seeded flag.
     * Used both by maybe_seed() on activation and by the manual "Preload
     * Default Checklists" button when all checklists have been deleted.
     *
     * @return void
     */
    public function seed_now() : void {
        foreach ( $this->get_default_checklists() as $order => $checklist ) {
            Checklists::create( $checklist[ 'title' ], $checklist[ 'sections' ], $order );
        }
    } // End seed_now()


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
                        __( 'Update WordPress core, themes, and plugins; verify site loads correctly after updating', 'site-quality-check' ),
                        __( 'Back up this site\'s codebase (files, not just database)', 'site-quality-check' ),
                        __( 'Check for malware scan alerts on this site (if a scanner is installed) and review results', 'site-quality-check' ),
                        __( 'Confirm this site\'s backup completed successfully', 'site-quality-check' ),
                    ] ),
                    $this->build_section( __( 'Monthly', 'site-quality-check' ), 'monthly', [
                        __( 'Review installed plugins/themes and remove anything unused', 'site-quality-check' ),
                        __( 'Check for PHP version updates and confirm compatibility', 'site-quality-check' ),
                        __( 'Review admin/user accounts on this site — remove inactive or unnecessary access', 'site-quality-check' ),
                        __( 'Check error logs for PHP errors, warnings, and notices', 'site-quality-check' ),
                        __( 'Confirm offsite/offline backup copy exists and is current', 'site-quality-check' ),
                    ] ),
                    $this->build_section( __( 'Quarterly', 'site-quality-check' ), 'quarterly', [
                        __( 'Review this site\'s security plugin settings/configuration', 'site-quality-check' ),
                        __( 'Review this site\'s code for outdated custom code or known vulnerable patterns', 'site-quality-check' ),
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