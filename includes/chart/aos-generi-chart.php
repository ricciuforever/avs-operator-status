<?php
// includes/chart/aos-generi-chart.php
// Questo file genera il grafico delle top 5 generi (escludendo "Basso Costo").
// Autore: Emanuele Tolomei

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Funzione per ottenere i dati per il grafico dei top 5 generi.
 *
 * @param string $query_date_start La data di inizio del periodo di query.
 * @param string $query_date_end La data di fine del periodo di query.
 * @return array Un array contenente 'labels' e 'datasets' per Chart.js.
 */
function aos_get_generi_chart_data( $query_date_start, $query_date_end ) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'aos_click_tracking';

    // Query per identificare le top 5 generi nel periodo, escludendo "Basso Costo"
    $top_5_generi_ids = $wpdb->get_col($wpdb->prepare(
        "SELECT t.term_id
         FROM {$table_name} AS c
         INNER JOIN {$wpdb->term_relationships} AS tr ON c.post_id = tr.object_id
         INNER JOIN {$wpdb->term_taxonomy} AS tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
         INNER JOIN {$wpdb->terms} AS t ON tt.term_id = t.term_id
         WHERE tt.taxonomy = 'genere'
           AND t.name NOT LIKE %s
           AND c.click_timestamp BETWEEN %s AND %s
         GROUP BY t.term_id
         ORDER BY COUNT(c.id) DESC
         LIMIT 5",
        '%Basso Costo%', // Esclude i generi che contengono "Basso Costo"
        $query_date_start,
        $query_date_end
    ));

    $chart_data = [];
    $labels = [];

    if (!empty($top_5_generi_ids)) {
        // Genera tutte le date nel range selezionato per le etichette dell'asse X
        $current_date = strtotime($query_date_start);
        $end_date = strtotime($query_date_end);
        while ($current_date <= $end_date) {
            $labels[] = date('Y-m-d', $current_date);
            $current_date = strtotime('+1 day', $current_date);
        }

        foreach ($top_5_generi_ids as $term_id) {
            $genere_term = get_term($term_id, 'genere');
            if ($genere_term && !is_wp_error($genere_term)) {
                $genere_name = $genere_term->name;

                // Query per ottenere i click giornalieri per questo genere
                $daily_clicks = $wpdb->get_results($wpdb->prepare(
                    "SELECT DATE(c.click_timestamp) as click_date, COUNT(c.id) as daily_count
                     FROM {$table_name} AS c
                     INNER JOIN {$wpdb->term_relationships} AS tr ON c.post_id = tr.object_id
                     INNER JOIN {$wpdb->term_taxonomy} AS tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
                     WHERE tt.taxonomy = 'genere'
                       AND tt.term_id = %d
                       AND c.click_timestamp BETWEEN %s AND %s
                     GROUP BY click_date
                     ORDER BY click_date ASC",
                    $term_id,
                    $query_date_start,
                    $query_date_end
                ), OBJECT_K);

                $data_points = [];
                foreach ($labels as $date) {
                    $data_points[] = isset($daily_clicks[$date]) ? (int)$daily_clicks[$date]->daily_count : 0;
                }

                $chart_data[] = [
                    'label' => esc_html($genere_name),
                    'data' => $data_points,
                    'borderColor' => sprintf('#%06X', mt_rand(0, 0xFFFFFF)), // Colore esadecimale random
                    'backgroundColor' => 'rgba(0, 0, 0, 0)', // Linee senza riempimento
                    'fill' => false,
                    'tension' => 0.4
                ];
            }
        }
    }
    
    return ['labels' => $labels, 'datasets' => $chart_data];
}

/**
 * Funzione per stampare il markup del canvas per il grafico dei generi.
 *
 * @param array $chart_data Dati del grafico restituiti da aos_get_generi_chart_data.
 */
function aos_render_generi_chart_canvas($chart_data) {
    ?>
    <div class="aos-chart-container">
        <?php if (empty($chart_data['datasets'])) : ?>
            <p>Nessun dato disponibile per il grafico dei top 5 generi nel periodo selezionato.</p>
        <?php else : ?>
            <canvas id="aosGeneriClicksChart" style="min-height: 350px; max-height: 600px; width: 100%;"></canvas>
        <?php endif; ?>
    </div>
    <?php
}