<?php
// includes/helpers.php

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Genera e restituisce una mappa che associa lo slug di un genere
 * al suo corrispondente numero di ricarica (DDI).
 *
 * La funzione analizza tutte le numerazioni e costruisce un array
 * del tipo [ 'genere-slug' => 'numero_ddi' ].
 *
 * @return array La mappa dei generi e dei loro DDI.
 */
function aos_get_ddi_map() {
    $mappa_genere_ddi = [];

    // Recuperiamo tutte le tassonomie una sola volta per efficienza
    $tutte_le_numerazioni = get_terms(['taxonomy' => 'numerazione', 'hide_empty' => false]);
    $tutti_i_generi = get_terms(['taxonomy' => 'genere', 'hide_empty' => false]);

    if (is_wp_error($tutte_le_numerazioni) || empty($tutte_le_numerazioni) || is_wp_error($tutti_i_generi) || empty($tutti_i_generi)) {
        return $mappa_genere_ddi; // Restituisce un array vuoto se non ci sono dati
    }

    foreach ($tutte_le_numerazioni as $numerazione) {
        // Cerchiamo solo le numerazioni che sono per la ricarica online
        if (stripos($numerazione->description, 'Ricarica Online') !== false) {
            foreach ($tutti_i_generi as $genere) {
                // Se la descrizione della numerazione contiene il nome del genere...
                if ($genere && stripos($numerazione->description, $genere->name) !== false) {
                    // ...lo aggiungiamo alla nostra mappa e passiamo alla numerazione successiva.
                    $mappa_genere_ddi[$genere->slug] = $numerazione->name;
                    break; // Ottimizzazione: trovato il genere, non serve continuare il ciclo interno
                }
            }
        }
    }

    return $mappa_genere_ddi;
}

/**
 * Seleziona un'operatrice casuale da un dato genere, con un peso
 * maggiore per le 3 più popolari degli ultimi 30 giorni.
 *
 * @param int $genere_term_id L'ID del termine della tassonomia 'genere'.
 * @return WP_Post|null L'oggetto post dell'operatrice scelta, o null se non trovata.
 */
function aos_get_weighted_random_operator_post( $genere_term_id ) {
    if ( ! $genere_term_id ) {
        return null;
    }

    global $wpdb;
    $table_name_tracking = $wpdb->prefix . 'aos_click_tracking';
    $trenta_giorni_fa = date('Y-m-d H:i:s', strtotime('-30 days'));

    // 1. Identifica le 3 OPERATRICI più popolari per questo genere.
    $top_operatrici_ids = $wpdb->get_col($wpdb->prepare(
        "SELECT c.post_id FROM {$table_name_tracking} AS c
         INNER JOIN {$wpdb->term_relationships} AS tr ON c.post_id = tr.object_id
         INNER JOIN {$wpdb->term_taxonomy} AS tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
         INNER JOIN {$wpdb->terms} AS t ON tt.term_id = t.term_id
         WHERE tt.taxonomy = 'genere' AND tt.term_id = %d AND c.click_timestamp >= %s
         GROUP BY c.post_id ORDER BY COUNT(c.id) DESC LIMIT 3",
        $genere_term_id,
        $trenta_giorni_fa
    ));

    // 2. Prendi TUTTE le operatrici di questo genere.
    $operatrici_query = new WP_Query([
        'post_type'      => 'operatrice',
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'tax_query'      => [['taxonomy' => 'genere', 'field' => 'term_id', 'terms' => $genere_term_id]],
        'fields'         => 'ids'
    ]);
    $all_operator_ids = $operatrici_query->posts;

    if ( empty( $all_operator_ids ) ) {
        return null; // Nessuna operatrice in questo genere
    }

    // 3. Crea la "lotteria" pesata.
    $operator_pool = [];
    $peso_top_op = 5; // Le operatrici top hanno 5 "biglietti"
    $peso_normale_op = 1; // Le altre ne hanno 1

    foreach ($all_operator_ids as $op_id) {
        $peso = in_array($op_id, $top_operatrici_ids) ? $peso_top_op : $peso_normale_op;
        for ($i = 0; $i < $peso; $i++) {
            $operator_pool[] = $op_id;
        }
    }
    
    if ( empty( $operator_pool ) ) {
        return null;
    }

    // 4. Estrai un ID a caso e restituisci l'oggetto post completo.
    $random_id = $operator_pool[array_rand($operator_pool)];
    
    return get_post($random_id);
}

/**
 * Renderizza l'HTML per un singolo pulsante/banner di pagamento.
 *
 * Questa funzione contiene tutta la logica per decidere quale tipo di pulsante
 * mostrare (normale, ricarica, carta di credito, svizzera) basandosi sulla
 * descrizione della numerazione. Utilizza le classi UIkit per lo stile.
 *
 * @param WP_Term $numerazione        L'oggetto termine della numerazione.
 * @param string  $genere_slug        Lo slug del genere corrente (per la mappa DDI).
 * @param array   $mappa_genere_ddi   La mappa DDI generata da aos_get_ddi_map().
 * @param int     $codice_da_tracciare L'ID del post dell'operatrice per il data-codice.
 * @param bool    $ha_chiama_e_ricarica Flag per la logica di fallback della ricarica online.
 *
 * @return string L'HTML del pulsante/banner.
 */
function aos_render_payment_button($numerazione, $genere_slug, $mappa_genere_ddi, $codice_da_tracciare, $ha_chiama_e_ricarica) {
    
    $description = $numerazione->description;
    $number = $numerazione->name;

    ob_start();

    // Caso 1: Ricarica Online (con logica di fallback)
    if (stripos($description, 'Ricarica Online') !== false) {
        if ($ha_chiama_e_ricarica) {
            // Non fa nulla e restituisce una stringa vuota, come da regola.
        } else {
            $ddi_per_ricarica = $mappa_genere_ddi[$genere_slug] ?? '';
            if (!empty($ddi_per_ricarica)) {
                $href_ricarica = 'https://customers.b4tlc.it/application/B4tlc/index.php?r=pr_cc/CCrecharge4&ddi=' . str_replace('.', '', $ddi_per_ricarica);
                $tariffe = get_term_meta($numerazione->term_id, '_aos_tariffe_meta', true);
                $prima_tariffa = !empty($tariffe) ? $tariffe[0] : null;
                ?>
                <a href="<?php echo esc_url($href_ricarica); ?>" class="aos-ricarica-banner uk-link-reset uk-text-center" target="_blank" title="Ricarica Online per il numero <?php echo esc_attr($ddi_per_ricarica); ?>">
                    <div class="uk-text-bold">Clicca e Ricarica</div>
                    <?php if ($prima_tariffa && isset($prima_tariffa['importo'])): ?>
                        <div class="uk-text-primary uk-text-bold uk-margin-small-top">A soli <?php echo esc_html(number_format_i18n($prima_tariffa['importo'], 2)); ?>€ / min</div>
                    <?php endif; ?>
                    <div class="uk-text-small uk-margin-small-top">per il numero: <?php echo esc_html($ddi_per_ricarica); ?></div>
                    <div class="aos-payment-icons uk-margin-small-top">
                        <img src="https://cdn.jsdelivr.net/npm/payment-icons/min/flat/visa.svg" width="40" alt="Visa" style="margin: 0px;">
                        <img src="https://cdn.jsdelivr.net/npm/payment-icons/min/flat/mastercard.svg" width="40" alt="Mastercard" style="margin: 0px;">
                        <img src="https://www.lineebollenti.it/wp-content/uploads/2025/06/postepay.webp" width="40" alt="Postepay" style="margin: 0px;">
                        <img src="https://cdn.jsdelivr.net/npm/payment-icons/min/flat/amex.svg" width="40" alt="American Express" style="margin: 0px;">
                        <img src="https://cdn.jsdelivr.net/npm/payment-icons/min/flat/paypal.svg" width="40" alt="PayPal" style="margin: 0px;">
                    </div>
                </a>
                <?php
            }
        }
    // Caso 2: Carta di Credito
    } elseif (stripos($description, 'Carta di Credito') !== false) {
        $href = 'tel://' . esc_attr($number);
        $tariffe = get_term_meta($numerazione->term_id, '_aos_tariffe_meta', true);
        $prima_tariffa = !empty($tariffe) ? $tariffe[0] : null;
        ?>
        <a href="<?php echo esc_url($href); ?>" class="uk-tile-muted uk-padding-small uk-link-reset uk-text-center uk-border-rounded aos-track-click" data-codice="<?php echo esc_attr($codice_da_tracciare); ?>" title="Chiama con Carta di Credito: <?php echo esc_attr($number); ?>">
            <div class="uk-text-bold">Chiama e Ricarica</div>
            <?php if ($prima_tariffa && isset($prima_tariffa['importo'])): ?>
                <div class="uk-text-primary uk-text-small uk-margin-small-top">Tariffa unica: <?php echo esc_html(number_format_i18n($prima_tariffa['importo'], 2)); ?>€ / min</div>
            <?php endif; ?>
            <div class="aos-payment-icons uk-margin-small-top">
                <img src="https://cdn.jsdelivr.net/npm/payment-icons/min/flat/visa.svg" width="40" alt="Visa" style="margin: 0px;">
                <img src="https://cdn.jsdelivr.net/npm/payment-icons/min/flat/mastercard.svg" width="40" alt="Mastercard" style="margin: 0px;">
                <img src="https://www.lineebollenti.it/wp-content/uploads/2025/06/postepay.webp" width="40" alt="Postepay" style="margin: 0px;">
                <img src="https://cdn.jsdelivr.net/npm/payment-icons/min/flat/amex.svg" width="40" alt="American Express" style="margin: 0px;">
            </div>
            <div class="uk-text-meta uk-margin-small-top">Chiama il numero: <?php echo esc_html($number); ?></div>
        </a>
        <?php
    // Caso 3: Svizzera
    } elseif (stripos($description, 'Svizzera') !== false) {
        $href = 'tel://' . esc_attr($number);
        $button_classes = 'uk-link-reset uk-width-1-1 uk-button uk-button-primary';
        $icon_html = '<img src="/wp-content/uploads/2019/04/svizzera1.png" width="25" alt="Svizzera" class="uk-margin-small-right">';
        ?>
        <a href="<?php echo esc_url($href); ?>" class="<?php echo esc_attr($button_classes); ?> aos-track-click uk-margin-small-bottom uk-text-left" data-codice="<?php echo esc_attr($codice_da_tracciare); ?>">
            <?php echo $icon_html . esc_html($number); ?>
        </a>
        <?php
    // Caso 4: Default (tutti gli altri pulsanti 899 etc.)
    } else {
        $href = 'tel://' . esc_attr($number);
        $button_classes = 'uk-button uk-button-secondary uk-width-1-1';
        $icon_html = '<span uk-icon="icon: phone" class="uk-margin-small-right"></span>';
        ?>
           <a href="<?php echo esc_url($href); ?>" class="<?php echo esc_attr($button_classes); ?> aos-track-click uk-margin-small-bottom uk-text-left" data-codice="<?php echo esc_attr($codice_da_tracciare); ?>">
                <?php echo $icon_html . esc_html($number); ?>
            </a>
        <?php
    }
    
    return ob_get_clean();
}

/**
 * Renderizza l'HTML completo per la card di una singola operatrice.
 *
 * @param WP_Post|int $operatrice_post L'oggetto post o l'ID dell'operatrice.
 * @return string L'HTML completo della card.
 */
function aos_render_operator_card_html( $operatrice_post ) {
    if ( is_numeric( $operatrice_post ) ) {
        $operatrice_post = get_post( $operatrice_post );
    }

    if ( ! $operatrice_post instanceof WP_Post || $operatrice_post->post_type !== 'operatrice' ) {
        return '';
    }

    $post_id = $operatrice_post->ID;
    $codice_da_tracciare = $post_id;
    $generi = get_the_terms($post_id, 'genere');
    $genere_nome = ($generi && !is_wp_error($generi)) ? $generi[0]->name : '';
    $genere_slug = ($generi && !is_wp_error($generi)) ? $generi[0]->slug : '';
    
    // Recupera il permalink del profilo dell'operatrice qui
    $operator_profile_link = get_permalink($post_id);

    $mappa_genere_ddi = aos_get_ddi_map(); 
    $tutte_le_numerazioni = get_terms(['taxonomy' => 'numerazione', 'hide_empty' => false]);

    ob_start();

    // Dati dell'Organizzazione Linee Bollenti (aggiornati con logo e telefono corretti)
    $linee_bollenti_organization = [
        '@type' => 'Organization',
        'name' => 'Linee Bollenti',
        'url' => 'https://www.lineebollenti.it/',
        'logo' => 'https://www.lineebollenti.it/wp-content/uploads/2025/06/LOGO_LINEE_BOLLENTI-removebg-preview.png', // Logo corretto
        'telephone' => '+390689083867', // Numero società corretto, formato internazionale
        'address' => [
            '@type' => 'PostalAddress',
            'streetAddress' => 'VIALE ANTONIO CIAMARRA 259',
            'addressLocality' => 'ROMA',
            'postalCode' => '00173',
            'addressRegion' => 'RM',
            'addressCountry' => 'IT'
        ],
        'foundingDate' => '2023-01-01', // Data di fondazione o creazione del brand se disponibile
    ];

    // Iniziamo con l'oggetto ProfessionalService
    $schema_data = [
        '@context' => 'https://schema.org',
        '@type'    => 'ProfessionalService',
        'name'     => 'Telefono Erotico ' . esc_html($genere_nome),
        'description' => 'Servizio di intrattenimento telefonico per adulti con operatrice specializzata in ' . esc_html($genere_nome) . ': ' . wp_kses_post(wp_trim_words($operatrice_post->post_content, 30, '')),
        'url'      => get_permalink($post_id),
        'provider' => [ // L'operatrice è il "provider" di questo servizio
            '@type'    => 'Person',
            'name'     => esc_html($operatrice_post->post_title),
            'description' => wp_kses_post(wp_trim_words($operatrice_post->post_content, 50, '')),
            'url'      => get_permalink($post_id),
            'disambiguatingDescription' => 'Operatrice di call center erotico specializzata in ' . esc_html($genere_nome) . '.',
            'knowsAbout' => 'Servizi telefonici erotici, intrattenimento adulto, ' . esc_html($genere_nome),
            'worksFor' => $linee_bollenti_organization // Collega l'operatrice all'organizzazione completa
        ],
        'priceRange' => '', // Verrà popolato sotto
        'address' => $linee_bollenti_organization['address'], // Indirizzo del servizio, lo stesso dell'organizzazione
        'telephone' => '', // Verrà popolato con il primo numero 899 trovato
    ];

    if (has_post_thumbnail($post_id)) {
        $thumbnail_id = get_post_thumbnail_id($post_id);
        $image_src = wp_get_attachment_image_src($thumbnail_id, 'full');
        if ($image_src) {
            $schema_data['image'] = $image_src[0];
            $schema_data['provider']['image'] = $image_src[0];
        }
    }

    $min_price = PHP_INT_MAX;
    $max_price = 0;
    $first_899_number = ''; // Variabile per memorizzare il primo numero 899 trovato
    
    if (!empty($generi) && !is_wp_error($tutte_le_numerazioni)) {
        foreach ($tutte_le_numerazioni as $numerazione) {
            $match_trovato = false;
            if (stripos($numerazione->description, $genere_nome) !== false) {
                $match_trovato = true;
            } elseif ($genere_nome === 'Etero Basso Costo' && stripos($numerazione->description, 'Etero') !== false) {
                $match_trovato = true;
            }

            // Consideriamo solo le numerazioni "normali" (come 899) per la tariffa e per il numero di telefono
            if ($match_trovato && stripos($numerazione->description, 'Svizzera') === false && stripos($numerazione->description, 'Ricarica Online') === false && stripos($numerazione->description, 'Carta di Credito') === false) {
                $tariffe = get_term_meta($numerazione->term_id, '_aos_tariffe_meta', true);
                if (!empty($tariffe)) {
                    foreach ($tariffe as $tariffa) {
                        if (isset($tariffa['importo'])) {
                            $importo = floatval($tariffa['importo']);
                            if ($importo < $min_price) $min_price = $importo;
                            if ($importo > $max_price) $max_price = $importo;
                        }
                    }
                    // Memorizza il primo numero 899 trovato per usarlo come 'telephone' del servizio
                    if (empty($first_899_number)) {
                        $first_899_number = $numerazione->name;
                    }
                }
            }
        }
    }

    // Popola priceRange
    if ($min_price !== PHP_INT_MAX && $max_price !== 0) { // Se sono stati trovati dei prezzi
        $schema_data['priceRange'] = number_format_i18n($min_price, 2) . '€ - ' . number_format_i18n($max_price, 2) . '€ / min';
    } else {
        $schema_data['priceRange'] = 'Contatta per tariffe';
    }

    // Popola il campo 'telephone' con il primo numero 899 trovato, se esiste
    if (!empty($first_899_number)) {
        $schema_data['telephone'] = $first_899_number;
    }
    ?>
    <script type="application/ld+json">
        <?php
        echo json_encode($schema_data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        ?>
    </script>
    <div class="uk-card uk-padding-small uk-card-secondary uk-card-body uk-flex uk-flex-column uk-text-center cartomante" data-codice="<?php echo esc_attr($codice_da_tracciare); ?>">
        <div class="uk-flex-1">
            <?php 
            if (has_post_thumbnail($post_id)) :
                // Crea l'alt tag dinamico qui
                $alt_text = esc_attr($operatrice_post->post_title) . ' - Telefono erotico ' . esc_attr($genere_nome);
                
                // Genera l'immagine con il nuovo alt tag
                echo get_the_post_thumbnail($post_id, 'medium', ['class' => 'uk-border-circle', 'alt' => $alt_text]);
            endif; 
            ?>
            <div class="labelnome"><?php echo esc_html($operatrice_post->post_title); ?></div>
            <div class="uk-h3 uk-margin-remove-top uk-text-primary uk-margin-small-bottom">Genere: <?php echo esc_html($genere_nome); ?></div>
            <div class="uk-text-small cartintro"><?php echo wp_kses_post(wp_trim_words($operatrice_post->post_content, 20, '...')); ?></div>
            
            <a href="<?php echo esc_url($operator_profile_link); ?>" class="uk-button uk-button-text uk-margin-small-top">Lascia un messaggio &rarr;</a>
        </div>
        <?php
        // Assumendo che il nome dell'operatrice sia il titolo del post
        // Questa è la situazione più comune in WordPress.
        $operator_name = get_the_title();

        // Chiama la nostra nuova funzione per mostrare il player
        lineebollenti_display_operator_audio($operator_name);
        ?>
        <div class="uk-margin-top">
            <?php
            $numerazioni_filtrate = [];
            if (!empty($genere_nome) && !is_wp_error($tutte_le_numerazioni)) {
                foreach ($tutte_le_numerazioni as $numerazione) {
                    $match_trovato = false;

                    // 1. Controllo primario: cerca una corrispondenza con il nome esatto del genere (es. "Mature", "Etero", "Vecchie")
                    if (stripos($numerazione->description, $genere_nome) !== false) {
                        $match_trovato = true;
                    }
                    // 2. FALLBACK: Se il genere è "Etero Basso Costo" e non è stata trovata una corrispondenza diretta,
                    //    cerca una corrispondenza con il genere "Etero" standard.
                    elseif ($genere_nome === 'Etero Basso Costo' && stripos($numerazione->description, 'Etero') !== false) {
                        $match_trovato = true;
                    }

                    // Aggiungi la numerazione solo se è stato trovato un match E se rispetta le altre regole di esclusione
                    if ($match_trovato && stripos($numerazione->description, 'Svizzera') === false && stripos($numerazione->description, 'Basso Costo') === false) {
                        $numerazioni_filtrate[] = $numerazione;
                    }
                }
            }
            if (!empty($numerazioni_filtrate)) {
                usort($numerazioni_filtrate, function($a, $b) { return aos_get_numerazione_priority($a->description) <=> aos_get_numerazione_priority($b->description); });
                $ha_chiama_e_ricarica = false;
                foreach ($numerazioni_filtrate as $numerazione_check) {
                    if (stripos($numerazione_check->description, 'Carta di Credito') !== false) {
                        $ha_chiama_e_ricarica = true;
                        break;
                    }
                }
                foreach ($numerazioni_filtrate as $numerazione) {
                    echo aos_render_payment_button($numerazione, $genere_slug, $mappa_genere_ddi, $codice_da_tracciare, $ha_chiama_e_ricarica);
                }
            } else {
                echo '<p class="uk-text-small uk-text-muted"><em>Nessuna tariffa specifica.</em></p>';
            }
            ?>
        </div>
    </div>
    <?php
    return ob_get_clean();
}