<?php
// includes/shortcode-tariffe-numerazioni.php

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * Registra lo shortcode [tariffe_numerazioni].
 * PREFISSI AGGIORNATI.
 */
function aos_register_tariffe_shortcode() {
    add_shortcode('tariffe_numerazioni', 'aos_display_tariffe_table_shortcode');
}
// Chiamiamo subito la funzione per assicurarci che lo shortcode sia registrato
aos_register_tariffe_shortcode();


/**
 * Funzione di callback per lo shortcode [tariffe_numerazioni].
 * PREFISSI AGGIORNATI.
 *
 * @param array $atts Attributi dello shortcode (non usati in questa versione).
 * @return string L'HTML della tabella delle tariffe.
 */
function aos_display_tariffe_table_shortcode($atts) {

    // 1. Recupera tutti i termini della tassonomia 'numerazione'
    $numerazioni = get_terms([
        'taxonomy'   => 'numerazione',
        'hide_empty' => false,
        'orderby'    => 'name',
        'order'      => 'ASC',
    ]);

    if (is_wp_error($numerazioni) || empty($numerazioni)) {
        return '';
    }

    ob_start();

    // 2. Inizia il ciclo per ogni numerazione
    foreach ($numerazioni as $numerazione) {
        
        // Logica per la valuta dinamica (rimane invariata)
        $currency_symbol = '€';
        if (strpos(trim($numerazione->name), '+41') === 0) {
            $currency_symbol = 'CHF';
        }
        
        // META KEY AGGIORNATA
        $tariffe = get_term_meta($numerazione->term_id, '_aos_tariffe_meta', true);

        ?>
        <h3 class="uk-heading-divider uk-margin-medium-top">
            <?php 
            echo esc_html($numerazione->name); 
            if (!empty($numerazione->description)) {
                echo ' <span class="uk-text-muted">(' . esc_html($numerazione->description) . ')</span>';
            }
            ?>
        </h3>

        <?php
        // CONDIZIONE FINALE E COMPLETA: Include tutte e 4 le regole.
        $mostra_riga_unica = (
            stripos($numerazione->description, 'Carta di Credito') !== false ||
            stripos($numerazione->description, 'Ricarica Online') !== false ||
            stripos($numerazione->description, 'Svizzera') !== false ||
            strpos(trim($numerazione->name), '+41') === 0
        );
        
        if (!empty($tariffe) && is_array($tariffe)) {
            ?>
            <div class="uk-overflow-auto">
                <table class="uk-table uk-table-hover uk-table-striped uk-table-middle uk-text-center">
                    <thead>
                        <tr>
                            <th class="uk-text-center">Gestore</th>
                            <th class="uk-text-center">Scatto alla risposta</th>
                            <th class="uk-text-center">Costo al minuto</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        // Se una delle condizioni è vera, mostra la riga unica
                        if ($mostra_riga_unica) :
                            $prima_tariffa = $tariffe[0]; // Usiamo solo la prima tariffa inserita
                        ?>
                            <tr>
                                <td><strong>Da qualsiasi gestore</strong></td>
                                <td><?php echo number_format_i18n($prima_tariffa['scatto'], 2); ?> <?php echo esc_html($currency_symbol); ?></td>
                                <td><?php echo number_format_i18n($prima_tariffa['importo'], 2); ?> <?php echo esc_html($currency_symbol); ?> / min</td>
                            </tr>
                        <?php 
                        // Altrimenti, mostra il caso normale con tutti gli operatori
                        else :
                            foreach ($tariffe as $tariffa) : 
                            ?>
                                <tr>
                                    <td><?php echo esc_html(ucfirst($tariffa['operatore'])); ?></td>
                                    <td><?php echo number_format_i18n($tariffa['scatto'], 2); ?> <?php echo esc_html($currency_symbol); ?></td>
                                    <td><?php echo number_format_i18n($tariffa['importo'], 2); ?> <?php echo esc_html($currency_symbol); ?> / min</td>
                                </tr>
                            <?php 
                            endforeach;
                        endif; 
                        ?>
                    </tbody>
                </table>
            </div>
            <?php
        } else {
            // Messaggio da mostrare se non ci sono tariffe
            ?>
            <p><em>Le tariffe per questa numerazione non sono ancora disponibili.</em></p>
            <?php
        }
        
    } // Fine del ciclo per ogni numerazione

    return ob_get_clean();
}