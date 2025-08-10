<?php
// includes/shortcode-vetrina-svizzera.php

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Registra lo shortcode [aos_generi_svizzera].
 */
function aos_register_generi_svizzera_shortcode() {
    add_shortcode('aos_generi_svizzera', 'aos_display_generi_svizzera_shortcode');
}
add_action('init', 'aos_register_generi_svizzera_shortcode');

/**
 * Funzione di callback per lo shortcode [aos_generi_svizzera].
 * Mostra una vetrina di generi che hanno una numerazione svizzera.
 *
 * @param array $atts Attributi dello shortcode.
 * @return string L'HTML della vetrina.
 */
function aos_display_generi_svizzera_shortcode($atts) {
    // 1. Recupera tutte le numerazioni e i generi
    $tutte_le_numerazioni = get_terms([
        'taxonomy'   => 'numerazione',
        'hide_empty' => false,
    ]);
    
    $tutti_i_generi = get_terms([
        'taxonomy' => 'genere',
        'hide_empty' => true,
    ]);

    if (is_wp_error($tutte_le_numerazioni) || empty($tutte_le_numerazioni) || is_wp_error($tutti_i_generi) || empty($tutti_i_generi)) {
        return '';
    }

    $promo_card_data = null;

    // 2. Crea una mappa dei generi che hanno una numerazione svizzera
    $generi_svizzeri = [];
    foreach ($tutte_le_numerazioni as $numerazione) {

        if (trim($numerazione->description) === 'Carta di Credito Svizzera') {
            $promo_card_data = [
                'number'    => $numerazione->name,
                'tariffe'   => get_term_meta($numerazione->term_id, '_aos_tariffe_meta', true) ?: [],
                'is_promo'  => true,
            ];
        }

        if (stripos($numerazione->description, 'Svizzera') !== false) {
            foreach ($tutti_i_generi as $genere) {
                if (stripos($numerazione->description, $genere->name) !== false) {
                    if (!isset($generi_svizzeri[$genere->slug])) {

                        $image_url = 'https://via.placeholder.com/400x300.png?text=' . urlencode($genere->name);
                        $intro_html = '';
                        $operatrice_name = '';
                        // MODIFICA: Inizializza l'ID dell'operatrice a 0
                        $operatrice_id_da_tracciare = 0; 
                        
                        $args_operatrice = [
                            'post_type' => 'operatrice',
                            'post_status' => 'publish',
                            'posts_per_page' => 1,
                            'orderby' => 'rand',
                            'tax_query' => [
                                [
                                    'taxonomy' => 'genere',
                                    'field' => 'slug',
                                    'terms' => $genere->slug,
                                ],
                            ],
                        ];
                        $operatrici_query = new WP_Query($args_operatrice);
                        if ($operatrici_query->have_posts()) {
                            $random_operatrice_post = $operatrici_query->posts[0];
                            // MODIFICA: Salviamo l'ID corretto dell'operatrice per il tracking
                            $operatrice_id_da_tracciare = $random_operatrice_post->ID; 
                            
                            $operatrice_name = $random_operatrice_post->post_title;

                            if (has_post_thumbnail($operatrice_id_da_tracciare)) {
                                $image_url = get_the_post_thumbnail_url($operatrice_id_da_tracciare, 'medium_large');
                            }
                            
                            if (!empty($random_operatrice_post->post_content)) {
                                $intro_html = apply_filters('the_content', $random_operatrice_post->post_content);
                            }
                        }
                        wp_reset_postdata();
                        
                        $tariffe = get_term_meta($numerazione->term_id, '_aos_tariffe_meta', true);

                        $generi_svizzeri[$genere->slug] = [
                            // MODIFICA: Salviamo l'ID dell'operatrice nell'array
                            'operatrice_id'   => $operatrice_id_da_tracciare, 
                            'name'            => $genere->name,
                            'number'          => $numerazione->name,
                            'image_url'       => $image_url,
                            'tariffe'         => is_array($tariffe) ? $tariffe : [],
                            'intro_html'      => $intro_html,
                            'operatrice_name' => $operatrice_name,
                        ];
                    }
                }
            }
        }
    }
    
    if (empty($generi_svizzeri)) {
        return '<p>Nessun genere con numerazione svizzera trovato.</p>';
    }

    // 3. Randomizza l'ordine dei generi
    $keys = array_keys($generi_svizzeri);
    shuffle($keys);
    
    $random_generi_svizzeri = [];
    foreach ($keys as $key) {
        $random_generi_svizzeri[$key] = $generi_svizzeri[$key];
    }
    
    if ($promo_card_data && !empty($random_generi_svizzeri)) {
        $middle_position = floor(count($random_generi_svizzeri) / 2);
        array_splice($random_generi_svizzeri, $middle_position, 0, [$promo_card_data]);
    }

    // 4. Genera l'HTML
    ob_start();
    ?>
     <p class="uk-text-center uk-margin-medium-bottom">Componi il numero e chiedi di parlare con l'operatrice scelta</p>

    <div class="uk-grid-match uk-grid-small uk-child-width-1-2@s uk-child-width-1-3@m" uk-grid>
        <?php foreach ($random_generi_svizzeri as $genere_data) : ?>
            
            <?php if (isset($genere_data['is_promo']) && $genere_data['is_promo']) : ?>
                <div>
                    <?php // MODIFICA: La card promozionale non ha un 'data-codice' perché non rappresenta una singola operatrice ?>
                    <div class="uk-card uk-card-primary uk-card-body uk-text-center uk-flex uk-flex-column uk-padding-small cartomante">
                        <div class="uk-card-media-top uk-margin-bottom">
                            <img class="uk-border-circle" src="/wp-content/uploads/2025/07/ricarica-carta-credito-svizzera.jpg" alt="Ricarica con Carta di Credito Svizzera" style="width: 500px; object-fit: cover;">
                        </div>
                        
                        <div class="uk-card-body uk-flex-1 uk-padding-remove-top">
                            <h3 class="uk-card-title uk-margin-remove-bottom">Ricarica con Carta di Credito</h3>
                            <p class="uk-margin-small-top uk-text-small">Perché spendere di più? Con la carta di credito il prezzo è scontato a 0.91 CHF/min. Approfittane!</p>
                            <p class="uk-margin-small-top uk-text-small">Chiedi all'operatrice con chi desideri parlare.</p>
                            
                            <div class="uk-flex uk-flex-center uk-margin-top">
                                <img src="https://cdn.jsdelivr.net/npm/payment-icons/min/flat/visa.svg" alt="Visa" style="height: 24px; margin: 0 5px;">
                                <img src="https://cdn.jsdelivr.net/npm/payment-icons/min/flat/mastercard.svg" alt="Mastercard" style="height: 24px; margin: 0 5px;">
                                <img src="https://cdn.jsdelivr.net/npm/payment-icons/min/flat/amex.svg" alt="American Express" style="height: 24px; margin: 0 5px;">
                            </div>
                        </div>

                        <div class="uk-card-footer uk-padding-remove-horizontal uk-padding-remove-bottom">
                            <?php
                            $numero_promo = $genere_data['number'];
                            $icon_html = '<img src="/wp-content/uploads/2025/02/svizzera11.png" width="25" alt="Svizzera" class="uk-margin-small-right">';
                            ?>
                            <a href="tel:<?php echo esc_attr($numero_promo); ?>" class="uk-button uk-button-default uk-width-1-1">
                                <?php echo $icon_html . esc_html($numero_promo); ?>
                            </a>
                        </div>
                    </div>
                </div>
                <?php continue; ?>
            <?php endif; ?>

            <div>
                <?php // MODIFICA: Ora 'data-codice' contiene l'ID corretto dell'operatrice mostrata ?>
                <div class="uk-card uk-card-secondary uk-card-body uk-text-center uk-flex uk-flex-column uk-padding-small cartomante" data-codice="<?php echo esc_attr($genere_data['operatrice_id']); ?>">
                    <div class="uk-card-media-top">
                        <img class="uk-border-circle" src="<?php echo esc_url($genere_data['image_url']); ?>" alt="Operatrice per <?php echo esc_attr($genere_data['name']); ?>">
                    </div>
                    
                    <?php if (!empty($genere_data['intro_html'])) : ?>
                    <div class="uk-text-small cartintro uk-margin-small-top uk-padding-small uk-padding-remove-bottom">
                        <?php if (!empty($genere_data['operatrice_name'])) : ?>
                            <h4 class="uk-margin-small-bottom uk-text-bold uk-text-primary"><?php echo esc_html($genere_data['operatrice_name']); ?></h4>
                        <?php endif; ?>
                        
                        <?php echo $genere_data['intro_html']; ?>
                    </div>
                    <?php endif; ?>

                    <div class="uk-card-body uk-flex-1 uk-padding-remove-top">
                        <h3 class="uk-card-title uk-margin-top uk-margin-remove-bottom">
                            <?php echo esc_html($genere_data['name']); ?>
                        </h3>
                    </div>
                    <div class="uk-card-footer uk-padding-remove-horizontal uk-padding-remove-bottom">
                        <?php
                        $icon_html = '<img src="/wp-content/uploads/2025/02/svizzera11.png" width="25" alt="Svizzera" class="uk-margin-small-right">';
                        ?>
                        <a href="tel:<?php echo esc_attr($genere_data['number']); ?>" class="uk-button uk-button-primary uk-width-1-1">
                            <?php echo $icon_html . esc_html($genere_data['number']); ?>
                        </a>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
    <?php
    return ob_get_clean();
}