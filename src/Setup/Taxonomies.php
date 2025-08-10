<?php

namespace AvsOperatorStatus\Setup;

/**
 * Class Taxonomies
 *
 * Handles the registration of Custom Taxonomies and their associated meta fields.
 */
class Taxonomies {
    /**
     * Registers all taxonomies and hooks for the plugin.
     */
    public function register() {
        add_action( 'init', [ $this, 'register_genere_taxonomy' ], 0 );
        add_action( 'init', [ $this, 'register_numerazione_taxonomy' ], 0 );

        // Hooks for Numerazione meta fields
        add_action( 'numerazione_add_form_fields', [ $this, 'add_numerazione_meta_fields' ] );
        add_action( 'numerazione_edit_form_fields', [ $this, 'edit_numerazione_meta_fields' ] );
        add_action( 'created_numerazione', [ $this, 'save_numerazione_meta_fields' ] );
        add_action( 'edited_numerazione', [ $this, 'save_numerazione_meta_fields' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_scripts' ] );
    }

    /**
     * Registers the "Genere" taxonomy.
     */
    public function register_genere_taxonomy() {
        $labels = [
            'name' => _x( 'Generi', 'taxonomy general name', 'avs-operator-status' ),
            'singular_name' => _x( 'Genere', 'taxonomy singular name', 'avs-operator-status' ),
            'menu_name' => __( 'Genere', 'avs-operator-status' ),
        ];
        $args = [
            'hierarchical' => true,
            'labels' => $labels,
            'show_ui' => true,
            'show_admin_column' => true,
            'query_var' => true,
            'rewrite' => [ 'slug' => 'genere' ],
            'show_in_rest' => true,
        ];
        register_taxonomy( 'genere', [ 'operatrice' ], $args );
    }

    /**
     * Registers the "Numerazione" taxonomy.
     */
    public function register_numerazione_taxonomy() {
        $labels = [
            'name' => _x( 'Numerazioni', 'taxonomy general name', 'avs-operator-status' ),
            'singular_name' => _x( 'Numerazione', 'taxonomy singular name', 'avs-operator-status' ),
            'menu_name' => __( 'Numerazioni', 'avs-operator-status' ),
        ];
        $args = [
            'hierarchical' => true,
            'labels' => $labels,
            'show_ui' => true,
            'show_admin_column' => true,
            'query_var' => true,
            'rewrite' => [ 'slug' => 'numerazione' ],
            'show_in_rest' => true,
        ];
        register_taxonomy( 'numerazione', [ 'operatrice' ], $args );
    }

    /**
     * Adds meta fields to the "Add New Numerazione" page.
     */
    public function add_numerazione_meta_fields( $taxonomy ) {
        ?>
        <div class="form-field">
            <label><?php _e( 'Tariffe per Operatore', 'avs-operator-status' ); ?></label>
            <div id="aos-tariffe-container"></div>
            <button type="button" class="button" id="aos-add-tariffa-row"><?php _e( 'Aggiungi Tariffa', 'avs-operator-status' ); ?></button>
            <p class="description"><?php _e( 'Aggiungi una riga per ogni operatore per specificare le tariffe.', 'avs-operator-status' ); ?></p>
        </div>
        <template id="aos-tariffa-template">
            <div class="aos-tariffa-row">
                <select name="aos_tariffe[operatore][]"><option value="">-- Seleziona Operatore --</option><option value="telecom">Telecom</option><option value="tim">TIM</option><option value="vodafone">Vodafone</option><option value="wind3">Wind3</option><option value="iliad">Iliad</option></select>
                <input type="number" step="0.01" name="aos_tariffe[scatto][]" placeholder="Scatto risp. (es: 0.15)">
                <input type="number" step="0.01" name="aos_tariffe[importo][]" placeholder="Importo/min (es: 1.22)">
                <button type="button" class="button aos-duplicate-tariffa-row">Duplica</button>
                <button type="button" class="button aos-remove-tariffa-row">Rimuovi</button>
            </div>
        </template>
        <?php
    }

    /**
     * Adds meta fields to the "Edit Numerazione" page.
     */
    public function edit_numerazione_meta_fields( $term ) {
        $tariffe = get_term_meta( $term->term_id, '_aos_tariffe_meta', true );
        $numero_telefono = get_term_meta( $term->term_id, '_aos_numero_telefono', true );
        $promo_attiva = get_term_meta( $term->term_id, '_aos_promo_attiva', true );
        $promo_messaggio = get_term_meta( $term->term_id, '_aos_promo_messaggio', true );
        ?>
        <tr class="form-field">
            <th><label for="_aos_numero_telefono"><?php _e( 'Numero di Telefono', 'avs-operator-status' ); ?></label></th>
            <td>
                <input type="text" name="_aos_numero_telefono" id="_aos_numero_telefono" value="<?php echo esc_attr( $numero_telefono ); ?>">
                <p class="description"><?php _e( 'Il numero di telefono effettivo per i link "tel:".', 'avs-operator-status' ); ?></p>
            </td>
        </tr>
        <tr class="form-field">
            <th><label><?php _e( 'Tariffe per Operatore', 'avs-operator-status' ); ?></label></th>
            <td>
                <div id="aos-tariffe-container">
                    <?php if ( ! empty( $tariffe ) && is_array( $tariffe ) ) : ?>
                        <?php foreach ( $tariffe as $tariffa ) : ?>
                            <div class="aos-tariffa-row">
                                <select name="aos_tariffe[operatore][]"><option value="">-- Seleziona Operatore --</option><option value="telecom" <?php selected( $tariffa['operatore'], 'telecom' ); ?>>Telecom</option><option value="tim" <?php selected( $tariffa['operatore'], 'tim' ); ?>>TIM</option><option value="vodafone" <?php selected( $tariffa['operatore'], 'vodafone' ); ?>>Vodafone</option><option value="wind3" <?php selected( $tariffa['operatore'], 'wind3' ); ?>>Wind3</option><option value="iliad" <?php selected( $tariffa['operatore'], 'iliad' ); ?>>Iliad</option></select>
                                <input type="number" step="0.01" name="aos_tariffe[scatto][]" placeholder="Scatto risp. (es: 0.15)" value="<?php echo esc_attr( $tariffa['scatto'] ); ?>">
                                <input type="number" step="0.01" name="aos_tariffe[importo][]" placeholder="Importo/min (es: 1.22)" value="<?php echo esc_attr( $tariffa['importo'] ); ?>">
                                <button type="button" class="button aos-duplicate-tariffa-row">Duplica</button>
                                <button type="button" class="button aos-remove-tariffa-row">Rimuovi</button>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                <button type="button" class="button" id="aos-add-tariffa-row"><?php _e( 'Aggiungi Tariffa', 'avs-operator-status' ); ?></button>
                <p class="description"><?php _e( 'Aggiungi o modifica le tariffe per ogni operatore.', 'avs-operator-status' ); ?></p>
                <template id="aos-tariffa-template"></template>
            </td>
        </tr>
        <tr class="form-field">
            <th><label for="aos_promo_attiva">Promo Attiva</label></th>
            <td>
                <select name="aos_promo_attiva" id="aos_promo_attiva"><option value="no" <?php selected( $promo_attiva, 'no' ); ?>>No</option><option value="si" <?php selected( $promo_attiva, 'si' ); ?>>Sì</option></select>
                <p class="description">Se impostato su 'Sì', mostrerà un'icona promozionale sul numero.</p>
            </td>
        </tr>
        <tr class="form-field">
            <th><label for="aos_promo_messaggio">Messaggio della Promo</label></th>
            <td>
                <textarea name="aos_promo_messaggio" id="aos_promo_messaggio" rows="5" cols="50" class="large-text"><?php echo esc_textarea( $promo_messaggio ); ?></textarea>
                <p class="description">Questo testo apparirà nella finestra modale che si apre cliccando sull'icona della promo.</p>
            </td>
        </tr>
        <?php
    }

    /**
     * Saves the custom meta fields for the "Numerazione" taxonomy.
     */
    public function save_numerazione_meta_fields( $term_id ) {
        if ( isset( $_POST['aos_tariffe'] ) ) {
            $new_tariffe = [];
            $operatori = (array) ( $_POST['aos_tariffe']['operatore'] ?? [] );
            $scatti = (array) ( $_POST['aos_tariffe']['scatto'] ?? [] );
            $importi = (array) ( $_POST['aos_tariffe']['importo'] ?? [] );

            for ( $i = 0; $i < count( $operatori ); $i++ ) {
                if ( empty( $operatori[ $i ] ) ) continue;
                $new_tariffe[] = [
                    'operatore' => sanitize_text_field( $operatori[ $i ] ),
                    'scatto' => isset( $scatti[ $i ] ) ? floatval( str_replace( ',', '.', $scatti[ $i ] ) ) : 0,
                    'importo' => isset( $importi[ $i ] ) ? floatval( str_replace( ',', '.', $importi[ $i ] ) ) : 0,
                ];
            }
            if ( ! empty( $new_tariffe ) ) {
                update_term_meta( $term_id, '_aos_tariffe_meta', $new_tariffe );
            } else {
                delete_term_meta( $term_id, '_aos_tariffe_meta' );
            }
        }

        if ( isset( $_POST['_aos_numero_telefono'] ) ) {
            update_term_meta( $term_id, '_aos_numero_telefono', sanitize_text_field( $_POST['_aos_numero_telefono'] ) );
        }
        if ( isset( $_POST['aos_promo_attiva'] ) ) {
            update_term_meta( $term_id, '_aos_promo_attiva', sanitize_text_field( $_POST['aos_promo_attiva'] ) );
        }
        if ( isset( $_POST['aos_promo_messaggio'] ) ) {
            update_term_meta( $term_id, '_aos_promo_messaggio', sanitize_textarea_field( $_POST['aos_promo_messaggio'] ) );
        }
    }

    /**
     * Enqueues admin scripts and styles for the taxonomy pages.
     */
    public function enqueue_admin_scripts( $hook ) {
        if ( ( 'term.php' === $hook || 'edit-tags.php' === $hook ) && isset( $_GET['taxonomy'] ) && 'numerazione' === $_GET['taxonomy'] ) {
            wp_enqueue_script( 'aos-taxonomy-fields-script', AOS_PLUGIN_URL . 'js/admin-script.js', [ 'jquery' ], '1.0', true );
            $css = ".aos-tariffa-row { display: flex; gap: 10px; align-items: center; margin-bottom: 10px; } #aos-tariffe-container { margin-top: 10px; padding: 10px; border: 1px dashed #ccd0d4; }";
            wp_add_inline_style( 'wp-admin', $css );
        }
    }
}
