document.addEventListener('DOMContentLoaded', function () {
    if (typeof Chart === 'undefined') {
        console.error('Chart.js is not loaded.');
        return;
    }

    // Data is passed from PHP via wp_localize_script
    if (typeof aosStatisticsData === 'undefined') {
        console.error('Statistics data is not available.');
        return;
    }

    const { operatrici, generi, numerazioni } = aosStatisticsData;

    function createChart(canvasId, chartData, chartLabel) {
        const ctx = document.getElementById(canvasId);
        if (!ctx) {
            console.error(`Canvas with id ${canvasId} not found.`);
            return;
        }

        if (!chartData || !chartData.labels || !chartData.datasets || chartData.datasets.length === 0) {
            const context = ctx.getContext('2d');
            context.font = "16px Arial";
            context.fillText("Dati non disponibili per questo grafico.", 10, 50);
            return;
        }

        new Chart(ctx.getContext('2d'), {
            type: 'line',
            data: {
                labels: chartData.labels,
                datasets: chartData.datasets
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true
                    }
                },
                plugins: {
                    legend: {
                        position: 'top',
                    },
                    title: {
                        display: true,
                        text: chartLabel
                    }
                }
            }
        });
    }

    createChart('operatriciChart', operatrici, 'Click per Operatrice');
    createChart('generiChart', generi, 'Click per Genere');
    createChart('numerazioniChart', numerazioni, 'Click per Numerazione');

    // --- Preset Date Filter Logic ---
    const presetButtons = document.querySelectorAll('.preset-filters .button');
    presetButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            const range = this.dataset.range;
            const today = new Date();
            let startDate, endDate;

            switch(range) {
                case 'today':
                    startDate = endDate = today;
                    break;
                case 'yesterday':
                    startDate = endDate = new Date(today.setDate(today.getDate() - 1));
                    break;
                case 'last7days':
                    endDate = new Date();
                    startDate = new Date();
                    startDate.setDate(startDate.getDate() - 6);
                    break;
                case 'all':
                    const firstDate = document.getElementById('filter_date_start').min;
                    document.getElementById('filter_date_start').value = firstDate;
                    document.getElementById('filter_date_end').value = new Date().toISOString().split('T')[0];
                    document.getElementById('statistics-filter-form').submit();
                    return; // Exit after submitting
            }

            // Format dates as YYYY-MM-DD
            const yyyy_start = startDate.getFullYear();
            const mm_start = String(startDate.getMonth() + 1).padStart(2, '0');
            const dd_start = String(startDate.getDate()).padStart(2, '0');

            const yyyy_end = endDate.getFullYear();
            const mm_end = String(endDate.getMonth() + 1).padStart(2, '0');
            const dd_end = String(endDate.getDate()).padStart(2, '0');

            document.getElementById('filter_date_start').value = `${yyyy_start}-${mm_start}-${dd_start}`;
            document.getElementById('filter_date_end').value = `${yyyy_end}-${mm_end}-${dd_end}`;

            document.getElementById('statistics-filter-form').submit();
        });
    });
});
