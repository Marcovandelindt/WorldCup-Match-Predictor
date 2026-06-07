/**
 * Bouw het scorelijn staafdiagram op de voorspellingspagina.
 * Verwacht een canvas element met id="scorelines-chart"
 * en een data-scorelines attribuut met JSON.
 */
export function buildScorelinesChart() {
    const canvas = document.getElementById('scorelines-chart');
    if (!canvas) return;

    import('chart.js/auto').then(({ default: Chart }) => {
        const scorelines = JSON.parse(canvas.dataset.scorelines);
        const labels     = scorelines.map(s => `${s.home}-${s.away}`);
        const data       = scorelines.map(s => s.probability);

        new Chart(canvas, {
            type: 'bar',
            data: {
                labels,
                datasets: [{
                    label: 'Kans (%)',
                    data,
                    backgroundColor: 'rgba(22, 193, 114, 0.7)',
                    borderColor:     'rgba(22, 193, 114, 1)',
                    borderWidth: 1,
                    borderRadius: 4,
                }],
            },
            options: {
                indexAxis: 'y',
                responsive: true,
                plugins: {
                    legend: { display: false },
                },
                scales: {
                    x: {
                        ticks: { color: '#9aa7b3', callback: v => v + '%' },
                        grid:  { color: 'rgba(255,255,255,0.05)' },
                    },
                    y: {
                        ticks: { color: '#eef2f5' },
                        grid:  { display: false },
                    },
                },
            },
        });
    });
}
