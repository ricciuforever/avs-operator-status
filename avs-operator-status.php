<?php
/**
 * Plugin Name:       AVS Operator Status
 * Description:       Mostra lo stato delle operatrici tramite API SOAP di AVS. Gestisci le ragazze dal menu "Gestione Ragazze". Usa lo shortcode [avs_operator_status].
 * Version:           6.0 - Struttura a File Separati
 * Author:            Emanuele Tolomei
 * License:           GPL-2.0-or-later
 */

if ( ! defined( 'ABSPATH' ) ) exit;

register_activation_hook( __FILE__, 'aos_plugin_activate' );

// Includi il file delle funzioni personalizzate
require_once plugin_dir_path( __FILE__ ) . 'includes/functions.php';


function aos_plugin_activate() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'aos_click_tracking';
    $charset_collate = $wpdb->get_charset_collate();

    // MODIFICA: Rimossa la colonna 'visitor_ip', aggiunta 'click_context'
    $sql = "CREATE TABLE $table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        post_id bigint(20) NOT NULL,
        numerazione_term_id bigint(20) NOT NULL,
        click_context varchar(255) NOT NULL DEFAULT '',
        click_timestamp datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
        PRIMARY KEY  (id)
    ) $charset_collate;";

    require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
    dbDelta( $sql );
}

// In avs-operator-status.php

/**
 * Classe per gestire la creazione e la stampa di schemi JSON-LD.
 */
class AOS_Schema_Manager {
    private static $schema_to_print = [];

    public static function init() {
        // Aggancia la nostra funzione di stampa al footer di WordPress.
        add_action('wp_footer', [__CLASS__, 'print_schema']);
    }

    /**
     * Aggiunge un nuovo blocco schema all'array di quelli da stampare.
     * @param array $schema Il blocco schema da aggiungere.
     */
    public static function add_schema($schema) {
        self::$schema_to_print[] = $schema;
    }

    /**
     * Stampa tutti gli schemi raccolti in un unico tag <script>.
     */
    public static function print_schema() {
        if (empty(self::$schema_to_print)) {
            return;
        }

        // Se abbiamo più di un blocco, li avvolgiamo in un @graph per una migliore organizzazione.
        $output_schema = (count(self::$schema_to_print) === 1)
            ? self::$schema_to_print[0]
            : ['@context' => 'https://schema.org', '@graph' => self::$schema_to_print];
        
        echo '<script type="application/ld+json">' . wp_json_encode($output_schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . '</script>';
        
        // Resetta l'array per la prossima pagina
        self::$schema_to_print = [];
    }
}
// Avvia il gestore.
AOS_Schema_Manager::init();

// Carica i file con la logica del plugin
require_once plugin_dir_path(__FILE__) . 'includes/post-types.php';
require_once plugin_dir_path(__FILE__) . 'includes/tax-meta-fields.php';
require_once plugin_dir_path(__FILE__) . 'includes/helpers.php';
require_once plugin_dir_path(__FILE__) . 'includes/backend.php';
require_once plugin_dir_path(__FILE__) . 'includes/frontend.php';
require_once plugin_dir_path(__FILE__) . 'includes/shortcodes.php';
require_once plugin_dir_path(__FILE__) . 'includes/admin-statistics-page.php';
//require_once plugin_dir_path(__FILE__) . 'includes/admin-sync-page.php';
require_once plugin_dir_path(__FILE__) . 'includes/quiz-logic.php';
// Include le funzioni per l'audio
require_once plugin_dir_path( __FILE__ ) . 'includes/audio.php';

// 3. Avvia le funzionalità di backend e frontend
// Le funzioni che aggiungono gli 'add_action' sono ora nei rispettivi file.
aos_frontend_setup();