<?php

namespace AvsOperatorStatus;

/**
 * Main Plugin Class.
 *
 * Acts as a service container and orchestrator for the plugin.
 */
final class Plugin {
    /**
     * The single instance of the class.
     *
     * @var Plugin|null
     */
    private static ?Plugin $instance = null;

    /**
     * The asset manager instance.
     *
     * @var Core\AssetManager
     */
    public Core\AssetManager $asset_manager;

    /**
     * Get the singleton instance of the class.
     *
     * @return Plugin
     */
    public static function instance(): Plugin {
        if ( is_null( self::$instance ) ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Plugin constructor.
     */
    private function __construct() {
        $this->init_core();
        $this->init_setup();
        $this->init_shortcodes();
        $this->init_features();
        $this->init_admin();
        $this->init_integrations();
    }

    /**
     * Initialize core components like the Asset Manager.
     */
    private function init_core() {
        $this->asset_manager = new Core\AssetManager();
        $this->asset_manager->register();

        $frontend = new Core\Frontend();
        $frontend->register();
    }

    /**
     * Initialize setup classes like Post Types and Taxonomies.
     */
    private function init_setup() {
        $post_types = new Setup\PostTypes();
        $post_types->register();

        $taxonomies = new Setup\Taxonomies();
        $taxonomies->register();
    }

    /**
     * Initialize all features.
     */
    private function init_features() {
        $quiz = new Features\Quiz();
        $quiz->register();

        $sync = new Features\SyncNumerazioni();
        $sync->register();

        $ajax = new Features\Ajax();
        $ajax->register();
    }

    /**
     * Initialize all admin-specific functionality.
     */
    private function init_admin() {
        if ( ! is_admin() ) {
            return;
        }
        $admin_columns = new Admin\OperatriceAdminColumns();
        $admin_columns->register();

        $statistics_page = new Admin\StatisticsPage();
        $statistics_page->register();

        $sync_page = new Admin\SyncPage();
        $sync_page->register();
    }

    /**
     * Initialize all third-party integrations.
     */
    private function init_integrations() {
        $yoast = new Integrations\Yoast();
        $yoast->register();

        $rest_api = new Integrations\RestApi();
        $rest_api->register();
    }

    /**
     * Initialize all shortcodes.
     */
    private function init_shortcodes() {
        $shortcodes = [
            Shortcodes\OperatriciShortcode::class,
            Shortcodes\VetrinaGeneriShortcode::class,
            Shortcodes\VetrinaSvizzeraShortcode::class,
            Shortcodes\BassoCostoVetrinaShortcode::class,
            Shortcodes\VetrinaCartaCreditoShortcode::class,
            Shortcodes\TariffeNumerazioniShortcode::class,
            Shortcodes\MieiPreferitiShortcode::class,
            Shortcodes\BottoniSingolaShortcode::class,
            Shortcodes\VetrinaGenereCorrenteShortcode::class,
            Shortcodes\NumeroShortcode::class,
            Shortcodes\LineebollentiGeneriCaroselliShortcode::class,
            Shortcodes\LineebollentiAllGenresGridShortcode::class,
            Shortcodes\LineebollentiPagesGridShortcode::class,
            Shortcodes\LineebollentiAllOperatorsGridShortcode::class,
        ];

        foreach ( $shortcodes as $shortcode_class ) {
            $instance = new $shortcode_class();
            $instance->register();
        }
    }

    /**
     * Cloning is forbidden.
     */
    public function __clone() {
        _doing_it_wrong( __FUNCTION__, esc_html__( 'Cheatin&#8217; huh?', 'avs-operator-status' ), '1.0.0' );
    }

    /**
     * Unserializing instances of this class is forbidden.
     */
    public function __wakeup() {
        _doing_it_wrong( __FUNCTION__, esc_html__( 'Cheatin&#8217; huh?', 'avs-operator-status' ), '1.0.0' );
    }
}
