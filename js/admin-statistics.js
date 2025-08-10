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
});
