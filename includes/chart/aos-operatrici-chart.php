<?php
// includes/chart/aos-operatrici-chart.php
// Questo file genera il grafico delle top 5 operatrici.
// Autore: Emanuele Tolomei

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Funzione per generare e visualizzare il markup del canvas per il grafico delle top 5 operatrici.
 * Restituisce i dati del grafico per l'inizializzazione JavaScript.
 *
 * @param string $query_date_start La data di inizio del periodo di query.
 * @param string $query_date_end La data di fine del periodo di query.
 * @return array Un array contenente 'labels' e 'datasets' per Chart.js.
 */
function aos_get_operatrici_chart_data( $query_date_start, $query_date_end ) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'aos_click_tracking';

    // Query per identificare le top 5 operatrici nel periodo
    $top_5_operatrici_ids = $wpdb->get_col($wpdb->prepare(
        "SELECT post_id
         FROM {$table_name}
         WHERE post_id > 0
           AND click_timestamp BETWEEN %s AND %s
         GROUP BY post_id
         ORDER BY COUNT(id) DESC
         LIMIT 5",
        $query_date_start,
        $query_date_end
    ));

    $chart_data = [];
    $labels = [];

    if (!empty($top_5_operatrici_ids)) {
        // Genera tutte le date nel range selezionato per le etichette dell'asse X
        $current_date = strtotime($query_date_start);
        $end_date = strtotime($query_date_end);
        while ($current_date <= $end_date) {
            $labels[] = date('Y-m-d', $current_date);
            $current_date = strtotime('+1 day', $current_date);
        }

        foreach ($top_5_operatrici_ids as $post_id) {
            $operatrice_name = get_the_title($post_id);
            if ($operatrice_name) {
                // Query per ottenere i click giornalieri per questa operatrice
                $daily_clicks = $wpdb->get_results($wpdb->prepare(
                    "SELECT DATE(click_timestamp) as click_date, COUNT(id) as daily_count
                     FROM {$table_name}
                     WHERE post_id = %d
                       AND click_timestamp BETWEEN %s AND %s
                     GROUP BY click_date
                     ORDER BY click_date ASC",
                    $post_id,
                    $query_date_start,
                    $query_date_end
                ), OBJECT_K);

                $data_points = [];
                foreach ($labels as $date) {
                    $data_points[] = isset($daily_clicks[$date]) ? (int)$daily_clicks[$date]->daily_count : 0;
                }

                $chart_data[] = [
                    'label' => esc_html($operatrice_name),
                    'data' => $data_points,
                    'borderColor' => sprintf('#%06X', mt_rand(0, 0xFFFFFF)), // Colore esadecimale random
                    'backgroundColor' => 'rgba(0, 0, 0, 0)', // Linee senza riempimento
                    'fill' => false,
                    'tension' => 0.4
                ];
            }
        }
    }
    
    // Ritorna i dati direttamente
    return ['labels' => $labels, 'datasets' => $chart_data];
}

// Stampa il markup del canvas.
// Questa funzione sarà chiamata da admin-statistics-page.php
function aos_render_operatrici_chart_canvas($chart_data) {
    ?>
    <div class="aos-chart-container">
        <?php if (empty($chart_data['datasets'])) : ?>
            <p>Nessun dato disponibile per il grafico delle top 5 operatrici nel periodo selezionato.</p>
        <?php else : ?>
            <canvas id="aosOperatriciClicksChart" style="min-height: 350px; max-height: 600px; width: 100%;"></canvas>
        <?php endif; ?>
    </div>
    <?php
}