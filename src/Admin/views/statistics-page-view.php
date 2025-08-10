<div class="wrap">
    <h1>Statistiche dei Click</h1>

    <style>
        .statistics-container { display: flex; flex-wrap: wrap; gap: 20px; }
        .stats-box { flex: 1 1 45%; background: #fff; padding: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
        .stats-box h2 { margin-top: 0; }
        .stats-box table { width: 100%; border-collapse: collapse; }
        .stats-box th, .stats-box td { padding: 8px; border: 1px solid #ddd; text-align: left; }
        .chart-container { position: relative; height: 400px; width: 100%; }
        .filter-form { background: #fff; padding: 20px; margin-bottom: 20px; }
    </style>

    <div class="filter-form">
        <form method="get">
            <input type="hidden" name="post_type" value="operatrice">
            <input type="hidden" name="page" value="aos-click-statistics">
            <label for="filter_date_start">Dal:</label>
            <input type="date" id="filter_date_start" name="filter_date_start" value="<?php echo esc_attr($selected_date_start); ?>" min="<?php echo esc_attr($first_available_date); ?>" max="<?php echo esc_attr(date('Y-m-d')); ?>">
            <label for="filter_date_end">Al:</label>
            <input type="date" id="filter_date_end" name="filter_date_end" value="<?php echo esc_attr($selected_date_end); ?>" min="<?php echo esc_attr($first_available_date); ?>" max="<?php echo esc_attr(date('Y-m-d')); ?>">
            <input type="submit" value="Filtra" class="button">
        </form>
    </div>

    <div class="statistics-container">
        <div class="stats-box">
            <h2><span class="dashicons dashicons-star-filled"></span> Top 5 Operatrici</h2>
            <table>
                <thead>
                    <tr>
                        <th>Operatrice</th>
                        <th>Click Totali</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($top_operatrici)) : ?>
                        <tr><td colspan="2">Nessun dato disponibile.</td></tr>
                    <?php else : ?>
                        <?php foreach ($top_operatrici as $item) : ?>
                            <tr>
                                <td><?php echo esc_html(get_the_title($item->post_id)); ?></td>
                                <td><?php echo esc_html($item->click_count); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div class="stats-box">
            <h2><span class="dashicons dashicons-tag"></span> Top 5 Generi</h2>
            <table>
                <thead>
                    <tr>
                        <th>Genere</th>
                        <th>Click Totali</th>
                    </tr>
                </thead>
                <tbody>
                     <?php if (empty($top_generi)) : ?>
                        <tr><td colspan="2">Nessun dato disponibile.</td></tr>
                    <?php else : ?>
                        <?php foreach ($top_generi as $item) : ?>
                            <?php $term = get_term($item->term_id); ?>
                            <tr>
                                <td><?php echo esc_html($term->name); ?></td>
                                <td><?php echo esc_html($item->click_count); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div class="stats-box" style="flex-basis: 100%;">
            <h2><span class="dashicons dashicons-chart-area"></span> Andamento Click per Operatrici</h2>
            <div class="chart-container">
                <canvas id="operatriciChart"></canvas>
            </div>
        </div>

        <div class="stats-box" style="flex-basis: 100%;">
            <h2><span class="dashicons dashicons-chart-bar"></span> Andamento Click per Generi</h2>
            <div class="chart-container">
                <canvas id="generiChart"></canvas>
            </div>
        </div>

        <div class="stats-box" style="flex-basis: 100%;">
            <h2><span class="dashicons dashicons-phone"></span> Andamento Click per Numerazioni</h2>
            <div class="chart-container">
                <canvas id="numerazioniChart"></canvas>
            </div>
        </div>
    </div>
</div>

<!-- Chart rendering is now handled by js/admin-statistics.js -->
