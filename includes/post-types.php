<?php

// 1. Creiamo il Custom Post Type "Operatrici"
function aos_crea_cpt_operatrici() {
    $labels = array(
        'name'                  => _x( 'Operatrici', 'Post Type General Name', 'text_domain' ),
        'singular_name'         => _x( 'Operatrice', 'Post Type Singular Name', 'text_domain' ),
        'menu_name'             => __( 'Operatrici', 'text_domain' ),
        'name_admin_bar'        => __( 'Operatrice', 'text_domain' ),
        'archives'              => __( 'Archivio Operatrici', 'text_domain' ),
        'attributes'            => __( 'Attributi Operatrice', 'text_domain' ),
        'parent_item_colon'     => __( 'Operatrice Padre:', 'text_domain' ),
        'all_items'             => __( 'Tutte le Operatrici', 'text_domain' ),
        'add_new_item'          => __( 'Aggiungi Nuova Operatrice', 'text_domain' ),
        'add_new'               => __( 'Aggiungi Nuova', 'text_domain' ),
        'new_item'              => __( 'Nuova Operatrice', 'text_domain' ),
        'edit_item'             => __( 'Modifica Operatrice', 'text_domain' ),
        'update_item'           => __( 'Aggiorna Operatrice', 'text_domain' ),
        'view_item'             => __( 'Visualizza Operatrice', 'text_domain' ),
        'view_items'            => __( 'Visualizza Operatrici', 'text_domain' ),
        'search_items'          => __( 'Cerca Operatrice', 'text_domain' ),
    );
    $args = array(
        'label'                 => __( 'Operatrice', 'text_domain' ),
        'description'           => __( 'Profili delle operatrici del servizio', 'text_domain' ),
        'labels'                => $labels,
        'supports'              => array( 'title', 'editor', 'thumbnail', 'custom-fields' ),
        'hierarchical'          => false,
        'public'                => true,
        'show_ui'               => true,
        'show_in_menu'          => true,
        'menu_position'         => 5,
        'menu_icon'             => 'dashicons-groups',
        'show_in_admin_bar'     => true,
        'show_in_nav_menus'     => true,
        'can_export'            => true,
        'has_archive'           => true,
        'exclude_from_search'   => false,
        'publicly_queryable'    => true,
        'capability_type'       => 'post',
        'show_in_rest'          => true,
    );
    register_post_type( 'operatrice', $args );
}
add_action( 'init', 'aos_crea_cpt_operatrici', 0 );

// 2. Creiamo la Tassonomia Custom "Genere"
function aos_crea_tassonomia_genere() {
    $labels = array(
        'name'              => _x( 'Generi', 'taxonomy general name', 'text_domain' ),
        'singular_name'     => _x( 'Genere', 'taxonomy singular name', 'text_domain' ),
        'search_items'      => __( 'Cerca Generi', 'text_domain' ),
        'all_items'         => __( 'Tutti i Generi', 'text_domain' ),
        'parent_item'       => __( 'Genere Padre', 'text_domain' ),
        'parent_item_colon' => __( 'Genere Padre:', 'text_domain' ),
        'edit_item'         => __( 'Modifica Genere', 'text_domain' ),
        'update_item'       => __( 'Aggiorna Genere', 'text_domain' ),
        'add_new_item'      => __( 'Aggiungi Nuovo Genere', 'text_domain' ),
        'new_item_name'     => __( 'Nome Nuovo Genere', 'text_domain' ),
        'menu_name'         => __( 'Genere', 'text_domain' ),
    );
    $args = array(
        'hierarchical'      => true,
        'labels'            => $labels,
        'show_ui'           => true,
        'show_admin_column' => true,
        'query_var'         => true,
        'rewrite'           => array( 'slug' => 'genere' ),
        'show_in_rest'      => true,
    );
    register_taxonomy( 'genere', array( 'operatrice' ), $args );
}
add_action( 'init', 'aos_crea_tassonomia_genere', 0 );

// 3. Creiamo la Tassonomia Custom "Numerazione"
function aos_crea_tassonomia_numerazione() {
    $labels = array(
        'name'              => _x( 'Numerazioni', 'taxonomy general name', 'text_domain' ),
        'singular_name'     => _x( 'Numerazione', 'taxonomy singular name', 'text_domain' ),
        'search_items'      => __( 'Cerca Numerazioni', 'text_domain' ),
        'all_items'         => __( 'Tutte le Numerazioni', 'text_domain' ),
        'parent_item'       => __( 'Numerazione Padre', 'text_domain' ),
        'parent_item_colon' => __( 'Numerazione Padre:', 'text_domain' ),
        'edit_item'         => __( 'Modifica Numerazione', 'text_domain' ),
        'update_item'       => __( 'Aggiorna Numerazione', 'text_domain' ),
        'add_new_item'      => __( 'Aggiungi Nuova Numerazione', 'text_domain' ),
        'new_item_name'     => __( 'Nome Nuova Numerazione', 'text_domain' ),
        'menu_name'         => __( 'Numerazioni', 'text_domain' ),
    );
    $args = array(
        'hierarchical'      => true,
        'labels'            => $labels,
        'show_ui'           => true,
        'show_admin_column' => true,
        'query_var'         => true,
        'rewrite'           => array( 'slug' => 'numerazione' ),
        'show_in_rest'      => true,
    );
    register_taxonomy( 'numerazione', array( 'operatrice' ), $args );
}
add_action( 'init', 'aos_crea_tassonomia_numerazione', 0 );

// ===================================================================
// GESTIONE COLONNE CUSTOM NELLA LISTA OPERATRICI
// ===================================================================

/**
 * 1. Aggiunge le nuove colonne (Immagine, Views, Clicks) alla tabella.
 */
add_filter( 'manage_operatrice_posts_columns', 'aos_aggiungi_colonne_operatrice' );

function aos_aggiungi_colonne_operatrice( $columns ) {
    $new_columns = [];
    foreach ($columns as $key => $title) {
        $new_columns[$key] = $title;
        if ($key === 'title') {
            $new_columns['featured_image'] = __( 'Immagine', 'aos_domain' );
            $new_columns['views'] = __( 'Views', 'aos_domain' );
            $new_columns['clicks'] = __( 'Clicks', 'aos_domain' );
            $new_columns['favorites'] = __( '❤️ Cuori', 'aos_domain' ); // <-- NUOVA COLONNA
        }
    }
    return $new_columns;
}


/**
 * 2. Mostra il contenuto per ogni nostra colonna custom.
 */
add_action( 'manage_operatrice_posts_custom_column', 'aos_mostra_contenuto_colonne_operatrice', 10, 2 );

function aos_mostra_contenuto_colonne_operatrice( $column_name, $post_id ) {
    switch ($column_name) {
        case 'featured_image':
            if ( has_post_thumbnail( $post_id ) ) { echo get_the_post_thumbnail( $post_id, [60, 60] ); } else { echo '—'; }
            break;
        case 'views':
            echo (int) get_post_meta($post_id, 'aos_views', true);
            break;
        case 'clicks':
            echo (int) get_post_meta($post_id, 'aos_clicks', true);
            break;
        case 'favorites': // <-- NUOVO CASE PER I CUORI
            echo (int) get_post_meta($post_id, '_aos_favorites_count', true);
            break;
    }
}


/**
 * 3. Rende le nuove colonne ordinabili.
 */
add_filter( 'manage_edit-operatrice_sortable_columns', 'aos_rendi_colonne_operatrice_ordinabili' );

function aos_rendi_colonne_operatrice_ordinabili( $columns ) {
    $columns['views'] = 'aos_views';
    $columns['clicks'] = 'aos_clicks';
    $columns['favorites'] = '_aos_favorites_count'; // <-- NUOVA COLONNA ORDINABILE
    return $columns;
}


/**
 * 4. Prepara la query per l'ordinamento custom.
 * Questa funzione ora si limita a impostare le basi.
 */
add_action( 'pre_get_posts', 'aos_ordina_colonne_operatrice_query' );
function aos_ordina_colonne_operatrice_query( $query ) {
    if ( ! is_admin() || ! $query->is_main_query() || $query->get('post_type') !== 'operatrice' ) {
        return;
    }

    $orderby = $query->get('orderby');

    // AGGIUNTA LA NOSTRA NUOVA CHIAVE ALLA CONDIZIONE
    if ( in_array($orderby, ['aos_views', 'aos_clicks', '_aos_favorites_count']) ) {
        $query->set('meta_key', $orderby);
        $query->set('orderby', 'meta_value_num');
    }
}

/**
 * 5. NUOVO: Modifica la clausola SQL "ORDER BY" per forzare il cast a numero.
 * Questa è la soluzione chirurgica che risolve il bug dell'ordinamento discendente.
 */
add_filter('posts_orderby', 'aos_forza_ordinamento_numerico_orderby', 10, 2);
function aos_forza_ordinamento_numerico_orderby($orderby, $query) {
    if (!is_admin() || !$query->is_main_query()) { return $orderby; }
    
    $orderby_key = $query->get('orderby');
    $meta_key = $query->get('meta_key');

    // AGGIUNTA LA NOSTRA NUOVA CHIAVE ALLA CONDIZIONE
    if ('meta_value_num' === $orderby_key && in_array($meta_key, ['aos_views', 'aos_clicks', '_aos_favorites_count'])) {
        global $wpdb;
        $order = $query->get('order');
        $orderby = "CAST({$wpdb->postmeta}.meta_value AS SIGNED) " . $order;
    }

    return $orderby;
}

// 3. (Opzionale) Aggiunge CSS per stilizzare la nuova colonna e l'immagine
add_action( 'admin_head', 'aos_aggiungi_css_colonna_immagine' );
function aos_aggiungi_css_colonna_immagine() {
    // Applichiamo lo stile solo nella pagina di elenco delle operatrici
    $screen = get_current_screen();
    if ( $screen && 'edit-operatrice' === $screen->id ) {
        ?>
        <style type="text/css">
            /* Imposta una larghezza fissa per la colonna */
            .column-featured_image {
                width: 100px;
                text-align: center;
            }
            /* Rende l'immagine un cerchio e la centra, per un look più pulito */
            .column-featured_image img {
                width: 60px;
                height: 60px;
                border-radius: 50%;
                object-fit: cover; /* Assicura che l'immagine copra l'area senza deformarsi */
            }
        </style>
        <?php
    }
}

// in includes/post-types.php

/**
 * Funzione principale per sincronizzare le numerazioni di una singola operatrice.
 *
 * @param int $post_id L'ID del post operatrice.
 */
function aos_sincronizza_numerazioni_per_post($post_id) {
    // Assicurati che il post sia del tipo corretto
    if (get_post_type($post_id) !== 'operatrice') {
        return;
    }

    // 1. Ottieni i generi di questa operatrice
    $generi_operatrice = get_the_terms($post_id, 'genere');

    if (empty($generi_operatrice) || is_wp_error($generi_operatrice)) {
        // Se non ha generi, rimuovi tutte le associazioni con le numerazioni
        wp_set_post_terms($post_id, null, 'numerazione');
        return;
    }
    
    // Usiamo solo il primo genere per la corrispondenza
    $genere_nome = $generi_operatrice[0]->name;
    
    // 2. Trova tutte le numerazioni che corrispondono a quel genere
    $tutte_le_numerazioni = get_terms([
        'taxonomy'   => 'numerazione',
        'hide_empty' => false,
    ]);

    $ids_numerazioni_da_assegnare = [];
    foreach ($tutte_le_numerazioni as $numerazione) {
        // Se la descrizione della numerazione contiene il nome del genere...
        if (stripos($numerazione->description, $genere_nome) !== false) {
            // ...la aggiungiamo alla lista di quelle da assegnare.
            $ids_numerazioni_da_assegnare[] = $numerazione->term_id;
        }
    }

    // 3. Assegna le numerazioni trovate all'operatrice, rimpiazzando quelle vecchie.
    // Il quarto parametro 'false' significa "sostituisci", non "aggiungi".
    wp_set_post_terms($post_id, $ids_numerazioni_da_assegnare, 'numerazione', false);
}

/**
 * Aggancia la funzione di sincronizzazione all'evento di salvataggio di un post.
 * Si attiverà ogni volta che crei o aggiorni un'operatrice.
 */
//add_action('save_post_operatrice', 'aos_sincronizza_numerazioni_per_post');

/**
 * Modifica il titolo SEO dell'archivio del CPT 'operatrice' usando il filtro di Yoast SEO.
 * @param string $title Il titolo originale.
 * @return string Il nuovo titolo modificato.
 * @author Emanuele Tolomei
 */
function aos_custom_yoast_title_operatrice_archive( $title ) {
    // Controlliamo se siamo nell'archivio del nostro CPT 'operatrice'
    if ( is_post_type_archive( 'operatrice' ) ) {
        // Imposta qui il tuo nuovo titolo.
        // Puoi usare il nome del CPT o un testo personalizzato.
        // Esempio 1: Testo statico personalizzato
        $new_title = 'Elenco Completo delle Nostre Operatrici | Linee Bollenti';

        // Esempio 2: Titolo dinamico
        // $cpt_object = get_post_type_object( 'operatrice' );
        // $new_title = 'Archivio ' . $cpt_object->labels->name . ' - ' . get_bloginfo('name');
        
        return $new_title;
    }

    // Se non siamo nella pagina giusta, restituiamo il titolo originale
    return $title;
}

// Aggiungiamo il filtro. La priorità 100 assicura che il nostro codice si esegua dopo Yoast.
add_filter( 'wpseo_title', 'aos_custom_yoast_title_operatrice_archive', 100, 1 );