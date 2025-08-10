<?php
// includes/functions.php

// Assicurati che le funzioni ausiliarie come aos_get_ddi_map, aos_render_payment_button,
// aos_render_operator_card_html e lineebollenti_display_operator_audio (se usata) siano già definite e disponibili.

/**
 * Funzione per renderizzare i caroselli di operatrici suddivisi per genere.
 * Il genere "Etero" viene sempre mostrato per primo, seguito dagli altri generi random.
 */
if ( ! function_exists( 'lineebollenti_render_genre_sliders' ) ) {
    function lineebollenti_render_genre_sliders() {
        ob_start();

        $excluded_genre_slugs = [];
        // Trova e escludi i generi "Basso Costo"
        $basso_costo_terms = get_terms([
            'taxonomy'   => 'genere',
            'name__like' => 'Basso Costo',
            'fields'     => 'slugs',
            'hide_empty' => false,
        ]);
        if (!is_wp_error($basso_costo_terms) && !empty($basso_costo_terms)) {
            $excluded_genre_slugs = array_merge($excluded_genre_slugs, $basso_costo_terms);
        }

        $generi_da_mostrare_ordinati = [];

        // 1. Recupera il genere "Etero" per primo
        $etero_term = get_term_by('name', 'Etero', 'genere');
        if ($etero_term && !in_array($etero_term->slug, $excluded_genre_slugs)) {
            $generi_da_mostrare_ordinati[] = $etero_term;
        }

        // 2. Recupera tutti gli altri generi
        $all_other_generi = get_terms([
            'taxonomy'   => 'genere',
            'hide_empty' => true,
            // Escludi "Etero" se è stato trovato e i generi "Basso Costo"
            'exclude'    => array_merge(
                (!empty($etero_term) ? [$etero_term->term_id] : []),
                array_map(function($slug) {
                    $term = get_term_by('slug', $slug, 'genere');
                    return $term ? $term->term_id : 0;
                }, $excluded_genre_slugs)
            ),
        ]);
        
        // Filtra ulteriormente per rimuovere generi già aggiunti o non validi
        $filtered_other_generi = [];
        $already_added_slugs = array_map(function($term) { return $term->slug; }, $generi_da_mostrare_ordinati);

        if (!empty($all_other_generi) && !is_wp_error($all_other_generi)) {
            foreach ($all_other_generi as $genere) {
                if (!in_array($genere->slug, $excluded_genre_slugs) && !in_array($genere->slug, $already_added_slugs)) {
                    $filtered_other_generi[] = $genere;
                }
            }
        }

        // 3. Mischia gli altri generi
        shuffle($filtered_other_generi);
        
        // 4. Combina Etero con gli altri generi mischiati
        $generi_da_mostrare_final = array_merge($generi_da_mostrare_ordinati, $filtered_other_generi);

        if (empty($generi_da_mostrare_final)) {
            echo '<p>Nessun genere di operatrici trovato.</p>';
            return ob_get_clean();
        }

        foreach ($generi_da_mostrare_final as $genere) {
            // Ottieni le operatrici per il genere corrente.
            // Puoi voler limitare il numero di operatrici per carosello, ad esempio 10.
            $operatrici_query = new WP_Query([
                'post_type'      => 'operatrice',
                'post_status'    => 'publish',
                'posts_per_page' => 10, // Limita a 10 operatrici per carosello, puoi modificare
                'tax_query'      => [[
                    'taxonomy' => 'genere',
                    'field'    => 'term_id',
                    'terms'    => $genere->term_id,
                ]],
                'orderby'        => 'rand', // Ordina in modo casuale
            ]);

            if ($operatrici_query->have_posts()) {
                ?>
                <hr>
                <div class="uk-section uk-section-small">
                    <h2 class="uk-h2 uk-text-center uk-margin-large-bottom">Genere: <?php echo esc_html($genere->name); ?></h2>

                    <div class="uk-position-relative" tabindex="-1">
                        <div class="uk-position-relative uk-visible-toggle uk-light" tabindex="-1" uk-slider="sets: true">

                            <ul class="uk-slider-items uk-child-width-1-1 uk-child-width-1-2@s uk-child-width-1-3@m uk-child-width-1-3@l uk-grid">
                                <?php
                                while ($operatrici_query->have_posts()) {
                                    $operatrici_query->the_post();
                                    ?>
                                    <li>
                                        <?php
                                        // Utilizza la tua funzione esistente per renderizzare la card dell'operatrice
                                        echo aos_render_operator_card_html(get_the_ID());
                                        ?>
                                    </li>
                                    <?php
                                }
                                wp_reset_postdata();
                                ?>
                            </ul>

                            <ul class="uk-slider-nav uk-dotnav uk-flex-center uk-margin"></ul>

                        </div>
                        
                        <div class="uk-hidden@s uk-visible-toggle uk-position-center-left-out uk-position-center-right-out uk-position-small">
                            <a class="uk-slidenav-large uk-slidenav-previous uk-slidenav-contrast" href="#" uk-slider-item="previous"></a>
                            <a class="uk-slidenav-large uk-slidenav-next uk-slidenav-contrast" href="#" uk-slider-item="next"></a>
                        </div>
                    </div>
                </div>
                <?php
            }
        } // Fine foreach ($generi_da_mostrare_final)

        return ob_get_clean();
    }

    // Aggiungi uno shortcode per poterlo inserire facilmente in YOOtheme
    add_shortcode( 'lineebollenti_generi_caroselli', 'lineebollenti_render_genre_sliders' );
}

/**
 * Funzione per renderizzare una griglia di tutte le tassonomie dei generi.
 * Ogni elemento mostra nome, descrizione troncata e un'immagine casuale di un'operatrice del genere.
 */
if ( ! function_exists( 'lineebollenti_render_all_genres_grid' ) ) {
    function lineebollenti_render_all_genres_grid() {
        ob_start();

        // Recupera tutti i generi (escludendo 'Basso Costo')
        $excluded_genre_ids = [];
        $basso_costo_terms = get_terms([
            'taxonomy'   => 'genere',
            'name__like' => 'Basso Costo',
            'fields'     => 'ids',
            'hide_empty' => false,
        ]);
        if (!is_wp_error($basso_costo_terms) && !empty($basso_costo_terms)) {
            $excluded_genre_ids = $basso_costo_terms;
        }

        $all_generi = get_terms([
            'taxonomy'   => 'genere',
            'hide_empty' => true,
            'exclude'    => $excluded_genre_ids,
        ]);

        if (empty($all_generi) || is_wp_error($all_generi)) {
            echo '<p>Nessun genere di operatrici disponibile per la visualizzazione.</p>';
            return ob_get_clean();
        }
        ?>

        <div class="uk-section uk-section-small">
            <h2 class="uk-h2 uk-text-center uk-margin-large-bottom">Esplora i Generi</h2>
            <div class="uk-grid-match uk-grid-small uk-child-width-1-1 uk-child-width-1-2@s uk-child-width-1-3@m" uk-grid>
                <?php
                foreach ($all_generi as $genere) {
                    $genere_link = get_term_link($genere);
                    $description_excerpt = wp_trim_words($genere->description, 20, '...'); // Tronca la descrizione

                    // Trova un'operatrice casuale per il genere, per l'immagine
                    $random_operator_image_url = '';
                    $operatrici_query_args = [
                        'post_type'      => 'operatrice',
                        'post_status'    => 'publish',
                        'posts_per_page' => 1,
                        'orderby'        => 'rand',
                        'tax_query'      => [[
                            'taxonomy' => 'genere',
                            'field'    => 'term_id',
                            'terms'    => $genere->term_id,
                        ]],
                        'meta_query' => [[ // Assicurati che l'operatrice abbia un'immagine in evidenza
                            'key'     => '_thumbnail_id',
                            'compare' => 'EXISTS',
                        ]],
                    ];
                    $operatrici_random_query = new WP_Query($operatrici_query_args);

                    if ($operatrici_random_query->have_posts()) {
                        $operatrici_random_query->the_post();
                        if (has_post_thumbnail()) {
                            // Cerca di ottenere l'immagine di dimensioni 'medium' per ottimizzare il peso
                            $image_id = get_post_thumbnail_id();
                            $image_src = wp_get_attachment_image_src($image_id, 'medium');
                            if ($image_src) {
                                $random_operator_image_url = $image_src[0];
                            } else {
                                // Fallback a 'full' se 'medium' non esiste o fallisce
                                $random_operator_image_url = get_the_post_thumbnail_url(get_the_ID(), 'full');
                            }
                        }
                        wp_reset_postdata(); // Reset della query secondaria
                    }
                    ?>
                    <div>
                        <a href="<?php echo esc_url($genere_link); ?>" class="uk-link-reset">
                            <div class="uk-card uk-card-default uk-card-hover uk-card-body uk-text-center uk-border-rounded">
                                <?php if (!empty($random_operator_image_url)) : ?>
                                    <div class="uk-card-media-top uk-margin-bottom">
                                        <img src="<?php echo esc_url($random_operator_image_url); ?>" alt="<?php echo esc_attr($genere->name); ?>" class="uk-border-circle" width="120" height="120" style="object-fit: cover;">
                                    </div>
                                <?php endif; ?>
                                <h3 class="uk-card-title uk-margin-small-top"><?php echo esc_html($genere->name); ?></h3>
                                <?php if (!empty($description_excerpt)) : ?>
                                    <p class="uk-text-small uk-text-muted"><?php echo esc_html($description_excerpt); ?></p>
                                <?php endif; ?>
                            </div>
                        </a>
                    </div>
                    <?php
                }
                ?>
            </div>
        </div>

        <?php
        return ob_get_clean();
    }

    // Aggiungi uno shortcode per poterlo inserire facilmente in YOOtheme
    add_shortcode( 'lineebollenti_all_genres_grid', 'lineebollenti_render_all_genres_grid' );
}


/**
 * Funzione per renderizzare una griglia di pagine, associando un'immagine di operatrice per genere.
 * L'immagine viene presa a caso da un'operatrice il cui genere corrisponde a una parola chiave nel titolo della pagina.
 */
if ( ! function_exists( 'lineebollenti_render_pages_grid_with_genre_images' ) ) {
    function lineebollenti_render_pages_grid_with_genre_images() {
        ob_start();

        // Recupera tutti i generi esistenti e i loro slug per il matching
        $all_genres = get_terms([
            'taxonomy'   => 'genere',
            'hide_empty' => true,
        ]);

        $genre_names_to_ids = [];
        $excluded_genre_ids = [];

        if (!is_wp_error($all_genres) && !empty($all_genres)) {
            foreach ($all_genres as $genere_term) {
                // Filtra i generi "Basso Costo" anche qui, se non vuoi che le loro immagini appaiano
                if (stripos($genere_term->name, 'Basso Costo') !== false) {
                    $excluded_genre_ids[] = $genere_term->term_id;
                    continue; // Salta questo genere, non lo aggiungiamo al mapping
                }
                $genre_names_to_ids[strtolower($genere_term->name)] = $genere_term->term_id;
            }
        }
        
        // Aggiusta l'ordinamento per i nomi composti se necessario (es. "Etero Basso Costo" prima di "Etero")
        krsort($genre_names_to_ids);

        // Recupera l'ID del genere "Etero" per il fallback
        $etero_genre_term = get_term_by('name', 'Etero', 'genere');
        $etero_genre_id = $etero_genre_term ? $etero_genre_term->term_id : 0;

        // Pagine da escludere
        $pages_to_exclude = [2, 298, 1323, 406, 965, 235];

        // Recupera tutte le pagine
        $all_pages = get_pages([
            'post_type'      => 'page',
            'post_status'    => 'publish',
            'posts_per_page' => -1, // Tutte le pagine
            'sort_column'    => 'menu_order', // Ordina per ordine del menu, o 'post_title'
            'exclude'        => $pages_to_exclude, // Escludi le pagine specificate
        ]);

        if (empty($all_pages)) {
            echo '<p>Nessuna pagina disponibile per la visualizzazione.</p>';
            return ob_get_clean();
        }
        ?>

        <div class="uk-section uk-section-small">
            <h2 class="uk-h2 uk-text-center uk-margin-large-bottom">I Nostri Numeri Erotici</h2>
            <div class="uk-grid-match uk-grid-small uk-child-width-1-1 uk-child-width-1-2@s uk-child-width-1-3@m" uk-grid>
                <?php
                foreach ($all_pages as $page) {
                    $page_link = get_permalink($page->ID);
                    $page_title = $page->post_title;
                    
                    // Rimuovi shortcode dal contenuto prima di troncarlo per l'estratto
                    $cleaned_content = strip_shortcodes($page->post_content);
                    $page_excerpt = wp_trim_words($cleaned_content, 20, '...');
                    
                    $page_image_url = '';
                    $matched_genre_id = 0;

                    // Cerca una corrispondenza tra il titolo della pagina e i nomi dei generi
                    foreach ($genre_names_to_ids as $genre_name_lower => $genre_id) {
                        if (preg_match('/\b' . preg_quote($genre_name_lower, '/') . '\b/i', strtolower($page_title))) {
                            $matched_genre_id = $genre_id;
                            break;
                        }
                    }
                    
                    // Logica di fallback: se nessun genere corrisponde, usa l'ID del genere "Etero"
                    if ($matched_genre_id === 0 && $etero_genre_id > 0) {
                        $matched_genre_id = $etero_genre_id;
                    }

                    if ($matched_genre_id > 0) {
                        // Trova un'operatrice casuale per il genere corrispondente, per l'immagine
                        $operatrici_query_args = [
                            'post_type'      => 'operatrice',
                            'post_status'    => 'publish',
                            'posts_per_page' => 1,
                            'orderby'        => 'rand',
                            'tax_query'      => [[
                                'taxonomy' => 'genere',
                                'field'    => 'term_id',
                                'terms'    => $matched_genre_id,
                                'operator' => 'IN', // Assicurati che l'operatore sia corretto
                            ]],
                            'meta_query' => [[
                                'key'     => '_thumbnail_id',
                                'compare' => 'EXISTS',
                            ]],
                        ];
                        $operatrici_random_query = new WP_Query($operatrici_query_args);

                        if ($operatrici_random_query->have_posts()) {
                            $operatrici_random_query->the_post();
                            if (has_post_thumbnail()) {
                                $image_id = get_post_thumbnail_id();
                                $image_src = wp_get_attachment_image_src($image_id, 'medium');
                                if ($image_src) {
                                    $page_image_url = $image_src[0];
                                } else {
                                    $page_image_url = get_the_post_thumbnail_url(get_the_ID(), 'full');
                                }
                            }
                            wp_reset_postdata();
                        }
                    }
                    ?>
                    <div>
                        <a href="<?php echo esc_url($page_link); ?>" class="uk-link-reset">
                            <div class="uk-card uk-card-default uk-card-hover uk-card-body uk-text-center uk-border-rounded">
                                <?php if (!empty($page_image_url)) : ?>
                                    <div class="uk-card-media-top uk-margin-bottom">
                                        <img src="<?php echo esc_url($page_image_url); ?>" alt="<?php echo esc_attr($page_title); ?>" class="uk-border-circle" width="120" height="120" style="object-fit: cover;">
                                    </div>
                                <?php endif; ?>
                                <h3 class="uk-card-title uk-margin-small-top"><?php echo esc_html($page_title); ?></h3>
                                <?php if (!empty($page_excerpt)) : ?>
                                    <p class="uk-text-small uk-text-muted"><?php echo wp_kses_post($page_excerpt); ?></p>
                                <?php endif; ?>
                            </div>
                        </a>
                    </div>
                    <?php
                }
                ?>
            </div>
        </div>

        <?php
        return ob_get_clean();
    }

    // Aggiungi uno shortcode per poterlo inserire facilmente in YOOtheme
    add_shortcode( 'lineebollenti_pages_grid', 'lineebollenti_render_pages_grid_with_genre_images' );
}

/**
 * Funzione per renderizzare una griglia di tutte le operatrici.
 * Ogni elemento mostra immagine, intro, genere, numero di telefono standard e link al profilo completo.
 */
if ( ! function_exists( 'lineebollenti_render_all_operators_grid' ) ) {
    function lineebollenti_render_all_operators_grid() {
        ob_start();

        // Recupera tutte le numerazioni una sola volta per efficienza
        $tutte_le_numerazioni = get_terms(['taxonomy' => 'numerazione', 'hide_empty' => false]);
        $numerazioni_standard = [];

        // Filtra le numerazioni standard (non ricarica, non carta di credito, non Svizzera, non Basso Costo)
        if (!is_wp_error($tutte_le_numerazioni) && !empty($tutte_le_numerazioni)) {
            foreach ($tutte_le_numerazioni as $num) {
                if (
                    stripos($num->description, 'Ricarica Online') === false &&
                    stripos($num->description, 'Carta di Credito') === false &&
                    stripos($num->description, 'Svizzera') === false &&
                    stripos($num->description, 'Basso Costo') === false
                ) {
                    $numerazioni_standard[] = $num;
                }
            }
        }

        // Recupera tutte le operatrici
        $operators_query = new WP_Query([
            'post_type'      => 'operatrice',
            'post_status'    => 'publish',
            'posts_per_page' => -1, // Recupera tutte le operatrici
            'orderby'        => 'title', // Ordina per titolo (nome)
            'order'          => 'ASC',
            'meta_query' => [[ // Assicurati che l'operatrice abbia un'immagine in evidenza
                'key'     => '_thumbnail_id',
                'compare' => 'EXISTS',
            ]],
        ]);

        if ( ! $operators_query->have_posts() ) {
            echo '<p>Nessuna operatrice disponibile per la visualizzazione.</p>';
            return ob_get_clean();
        }
        ?>

        <div class="uk-section uk-section-small">
            <h2 class="uk-h2 uk-text-center uk-margin-large-bottom">Tutte le Operatrici</h2>
            <div class="uk-grid-match uk-grid-small uk-child-width-1-1 uk-child-width-1-2@s uk-child-width-1-3@m uk-child-width-1-4@l" uk-grid>
                <?php
                while ( $operators_query->have_posts() ) {
                    $operators_query->the_post();
                    $operator_id = get_the_ID();
                    $operator_title = get_the_title();
                    $operator_link = get_permalink($operator_id);
                    
                    // Rimuovi shortcode dal contenuto e tronca per l'intro
                    $cleaned_content = strip_shortcodes(get_the_content());
                    $operator_intro = wp_trim_words($cleaned_content, 15, '...'); // Intro più breve

                    $operator_image_url = '';
                    if ( has_post_thumbnail() ) {
                        $image_id = get_post_thumbnail_id($operator_id);
                        $image_src = wp_get_attachment_image_src($image_id, 'medium'); // Ottimizza dimensione immagine
                        if ($image_src) {
                            $operator_image_url = $image_src[0];
                        } else {
                            $operator_image_url = get_the_post_thumbnail_url($operator_id, 'full');
                        }
                    }

                    // Recupera il genere dell'operatrice
                    $generi = get_the_terms($operator_id, 'genere');
                    $genere_nome = ($generi && !is_wp_error($generi)) ? $generi[0]->name : '';
                    
                    $display_phone_number = '';
                    if (!empty($genere_nome)) {
                        foreach ($numerazioni_standard as $numerazione) {
                            // Trova la numerazione standard che contiene il nome del genere
                            if (stripos($numerazione->description, $genere_nome) !== false) {
                                $display_phone_number = $numerazione->name;
                                break; // Trovato il primo numero standard per questo genere
                            }
                        }
                        // Fallback per "Etero Basso Costo" che potrebbe avere "Etero" come numerazione
                        if (empty($display_phone_number) && $genere_nome === 'Etero Basso Costo') {
                             foreach ($numerazioni_standard as $numerazione) {
                                if (stripos($numerazione->description, 'Etero') !== false) {
                                    $display_phone_number = $numerazione->name;
                                    break;
                                }
                            }
                        }
                    }
                    ?>
                    <div>
                        <a href="<?php echo esc_url($operator_link); ?>" class="uk-link-reset">
                            <div class="uk-card uk-card-default uk-card-hover uk-card-body uk-text-center uk-border-rounded">
                                <?php if ( ! empty( $operator_image_url ) ) : ?>
                                    <div class="uk-card-media-top uk-margin-bottom">
                                        <img src="<?php echo esc_url($operator_image_url); ?>" alt="<?php echo esc_attr($operator_title); ?>" class="uk-border-circle" width="120" height="120" style="object-fit: cover;">
                                    </div>
                                <?php endif; ?>
                                <h3 class="uk-card-title uk-margin-small-top"><?php echo esc_html($operator_title); ?></h3>
                                
                                <?php if ( ! empty( $genere_nome ) ) : ?>
                                    <div class="uk-text-primary uk-text-small uk-margin-remove-top uk-margin-small-bottom">Genere: <?php echo esc_html($genere_nome); ?></div>
                                <?php endif; ?>

                                <?php if ( ! empty( $display_phone_number ) ) : ?>
                                    <div class="uk-text-large uk-text-bold uk-margin-small-bottom aos-phone-number">
                                        <a href="tel://<?php echo esc_attr($display_phone_number); ?>" class="uk-link-reset">
                                            <span uk-icon="icon: receiver"></span> <?php echo esc_html($display_phone_number); ?>
                                        </a>
                                    </div>
                                <?php endif; ?>

                                <?php if ( ! empty( $operator_intro ) ) : ?>
                                    <p class="uk-text-small uk-text-muted"><?php echo wp_kses_post($operator_intro); ?></p>
                                <?php endif; ?>
                                
                                <a href="<?php echo esc_url($operator_link); ?>" class="uk-button uk-button-text uk-margin-small-top">Lascia un messaggio &rarr;</a>
                            </div>
                        </a>
                    </div>
                    <?php
                }
                wp_reset_postdata(); // Reset della query principale
                ?>
            </div>
        </div>

        <?php
        return ob_get_clean();
    }

    // Aggiungi uno shortcode per poterlo inserire facilmente in YOOtheme
    add_shortcode( 'lineebollenti_all_operators_grid', 'lineebollenti_render_all_operators_grid' );
}