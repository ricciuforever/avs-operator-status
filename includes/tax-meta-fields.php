<?php

// Aggiunge i campi nella pagina "Aggiungi Nuova Numerazione"
add_action( 'numerazione_add_form_fields', 'aos_add_numerazione_meta_fields' );
function aos_add_numerazione_meta_fields( $taxonomy ) {
    ?>
    <div class="form-field">
        <label><?php _e( 'Tariffe per Operatore', 'aos_domain' ); ?></label>
        <div id="aos-tariffe-container">
            <?php // Il JS aggiungerà qui la prima riga dinamicamente ?>
        </div>
        <button type="button" class="button" id="aos-add-tariffa-row"><?php _e( 'Aggiungi Tariffa', 'aos_domain' ); ?></button>
        <p class="description"><?php _e( 'Aggiungi una riga per ogni operatore per specificare le tariffe.', 'aos_domain' ); ?></p>
    </div>

    <template id="aos-tariffa-template">
        <div class="aos-tariffa-row">
            <select name="aos_tariffe[operatore][]">
                <option value="">-- Seleziona Operatore --</option>
                <option value="telecom">Telecom</option>
                <option value="tim">TIM</option>
                <option value="vodafone">Vodafone</option>
                <option value="wind3">Wind3</option>
                <option value="iliad">Iliad</option>
            </select>
            <input type="number" step="0.01" name="aos_tariffe[scatto][]" placeholder="Scatto risp. (es: 0.15)">
            <input type="number" step="0.01" name="aos_tariffe[importo][]" placeholder="Importo/min (es: 1.22)">
            <button type="button" class="button aos-duplicate-tariffa-row">Duplica</button>
            <button type="button" class="button aos-remove-tariffa-row">Rimuovi</button>
        </div>
    </template>
    <?php
}

// Aggiunge i campi nella pagina "Modifica Numerazione"
add_action( 'numerazione_edit_form_fields', 'aos_edit_numerazione_meta_fields' );
function aos_edit_numerazione_meta_fields( $term ) {
    // Recupero dati esistenti
    $tariffe = get_term_meta( $term->term_id, '_aos_tariffe_meta', true );
    $numero_telefono = get_term_meta( $term->term_id, '_aos_numero_telefono', true ); // Recuperiamo anche il numero
    $promo_attiva = get_term_meta( $term->term_id, '_aos_promo_attiva', true );
    $promo_messaggio = get_term_meta( $term->term_id, '_aos_promo_messaggio', true );
    ?>

    <?php // +++ CAMPO PER IL NUMERO DI TELEFONO DIRETTO +++ ?>
    <tr class="form-field">
        <th><label for="_aos_numero_telefono"><?php _e( 'Numero di Telefono', 'aos_domain' ); ?></label></th>
        <td>
            <input type="text" name="_aos_numero_telefono" id="_aos_numero_telefono" value="<?php echo esc_attr( $numero_telefono ); ?>">
            <p class="description"><?php _e( 'Il numero di telefono effettivo per i link "tel:".', 'aos_domain' ); ?></p>
        </td>
    </tr>

    <?php // SEZIONE TARIFFE (invariata) ?>
    <tr class="form-field">
        <th><label><?php _e( 'Tariffe per Operatore', 'aos_domain' ); ?></label></th>
        <td>
            <div id="aos-tariffe-container">
                <?php if ( ! empty( $tariffe ) && is_array( $tariffe ) ) : ?>
                    <?php foreach ( $tariffe as $index => $tariffa ) : ?>
                        <div class="aos-tariffa-row">
                             <select name="aos_tariffe[operatore][]">
                                <option value="">-- Seleziona Operatore --</option>
                                <option value="telecom" <?php selected( $tariffa['operatore'], 'telecom' ); ?>>Telecom</option>
                                <option value="tim" <?php selected( $tariffa['operatore'], 'tim' ); ?>>TIM</option>
                                <option value="vodafone" <?php selected( $tariffa['operatore'], 'vodafone' ); ?>>Vodafone</option>
                                <option value="wind3" <?php selected( $tariffa['operatore'], 'wind3' ); ?>>Wind3</option>
                                <option value="iliad" <?php selected( $tariffa['operatore'], 'iliad' ); ?>>Iliad</option>
                             </select>
                             <input type="number" step="0.01" name="aos_tariffe[scatto][]" placeholder="Scatto risp. (es: 0.15)" value="<?php echo esc_attr( $tariffa['scatto'] ); ?>">
                             <input type="number" step="0.01" name="aos_tariffe[importo][]" placeholder="Importo/min (es: 1.22)" value="<?php echo esc_attr( $tariffa['importo'] ); ?>">
                             <button type="button" class="button aos-duplicate-tariffa-row">Duplica</button>
                             <button type="button" class="button aos-remove-tariffa-row">Rimuovi</button>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            <button type="button" class="button" id="aos-add-tariffa-row"><?php _e( 'Aggiungi Tariffa', 'aos_domain' ); ?></button>
            <p class="description"><?php _e( 'Aggiungi o modifica le tariffe per ogni operatore.', 'aos_domain' ); ?></p>
            <template id="aos-tariffa-template">
                <?php // template qui... ?>
            </template>
        </td>
    </tr>

    <?php // +++ NUOVA SEZIONE PROMO +++ ?>
    <tr class="form-field">
        <th><label for="aos_promo_attiva">Promo Attiva</label></th>
        <td>
            <select name="aos_promo_attiva" id="aos_promo_attiva">
                <option value="no" <?php selected( $promo_attiva, 'no' ); ?>>No</option>
                <option value="si" <?php selected( $promo_attiva, 'si' ); ?>>Sì</option>
            </select>
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

// Salva i dati - VERSIONE AGGIORNATA
add_action( 'created_numerazione', 'aos_save_numerazione_meta_fields' );
add_action( 'edited_numerazione', 'aos_save_numerazione_meta_fields' );
function aos_save_numerazione_meta_fields( $term_id ) {
    // Salvataggio tariffe (logica invariata, solo una pulizia)
    if ( isset( $_POST['aos_tariffe'] ) ) {
        $new_tariffe = [];
        $operatori = isset($_POST['aos_tariffe']['operatore']) ? (array) $_POST['aos_tariffe']['operatore'] : [];
        $scatti = isset($_POST['aos_tariffe']['scatto']) ? (array) $_POST['aos_tariffe']['scatto'] : [];
        $importi = isset($_POST['aos_tariffe']['importo']) ? (array) $_POST['aos_tariffe']['importo'] : [];
        
        if ( ! empty($operatori) ) {
            for ( $i = 0; $i < count( $operatori ); $i++ ) {
                if ( empty( $operatori[$i] ) ) continue;
                $new_tariffe[] = [
                    'operatore' => sanitize_text_field( $operatori[$i] ),
                    'scatto'    => isset($scatti[$i]) ? floatval( str_replace(',', '.', $scatti[$i]) ) : 0,
                    'importo'   => isset($importi[$i]) ? floatval( str_replace(',', '.', $importi[$i]) ) : 0,
                ];
            }
        }
        
        if ( ! empty( $new_tariffe ) ) {
            update_term_meta( $term_id, '_aos_tariffe_meta', $new_tariffe );
        } else {
            delete_term_meta( $term_id, '_aos_tariffe_meta' );
        }
    }

    // +++ SALVATAGGIO DEI NUOVI CAMPI +++
    // Salva il numero di telefono
    if ( isset( $_POST['_aos_numero_telefono'] ) ) {
        update_term_meta( $term_id, '_aos_numero_telefono', sanitize_text_field( $_POST['_aos_numero_telefono'] ) );
    }
    // Salva lo stato della promo
    if ( isset( $_POST['aos_promo_attiva'] ) ) {
        update_term_meta( $term_id, '_aos_promo_attiva', sanitize_text_field( $_POST['aos_promo_attiva'] ) );
    }
    // Salva il messaggio della promo
    if ( isset( $_POST['aos_promo_messaggio'] ) ) {
        update_term_meta( $term_id, '_aos_promo_messaggio', sanitize_textarea_field( $_POST['aos_promo_messaggio'] ) );
    }
}


// Carica script e stili
add_action( 'admin_enqueue_scripts', 'aos_enqueue_taxonomy_admin_scripts' );
function aos_enqueue_taxonomy_admin_scripts( $hook ) {
    // Condizione per caricare solo nelle pagine della tassonomia 'numerazione'
    if ( ('term.php' === $hook || 'edit-tags.php' === $hook) && isset($_GET['taxonomy']) && 'numerazione' === $_GET['taxonomy'] ) {
        // Accoda il file JS
        wp_enqueue_script(
            'aos-taxonomy-fields-script', 
            plugin_dir_url( __FILE__ ) . '../js/admin-script.js', 
            ['jquery'], 
            '1.0', 
            true
        );

        // Aggiungi gli stili (possiamo lasciarli inline qui per semplicità)
        $css = "
            .aos-tariffa-row { display: flex; gap: 10px; align-items: center; margin-bottom: 10px; }
            #aos-tariffe-container { margin-top: 10px; padding: 10px; border: 1px dashed #ccd0d4; }
        ";
        wp_add_inline_style( 'wp-admin', $css );
    }
}