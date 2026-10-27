import Chart from 'chart.js/auto';

const payload = JSON.parse(document.getElementById('attendance-chart-data').textContent);
const points = payload.points;
const duration = hours => {
    const seconds = Math.max(0, Math.round(hours * 3600));
    return `${String(Math.floor(seconds / 3600)).padStart(2, '0')}:${String(Math.floor(seconds % 3600 / 60)).padStart(2, '0')}:${String(seconds % 60).padStart(2, '0')}`;
};
const labels = points.map(point => point.label);
const dataset = (key, label, color) => ({
    label, data: points.map(point => point[key] === null ? null : point[key] / 3600),
    backgroundColor: color, borderRadius: 4, maxBarThickness: 32,
});
document.querySelectorAll('[data-chart-scroll]').forEach(container => {
    container.style.minWidth = `${Math.max(320, points.length * (payload.mode === 'daily' ? 42 : 90))}px`;
});
function options(stacked) {
    return {
        responsive: true, maintainAspectRatio: false, locale: 'es-PE',
        animation: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? false : { duration: 350 },
        interaction: { mode: 'index', intersect: false },
        scales: {
            x: { stacked, grid: { display: false }, ticks: { maxRotation: payload.mode === 'daily' ? 45 : 25, autoSkip: false, font: { size: 10 } } },
            y: { stacked, beginAtZero: true, title: { display: true, text: 'Horas' }, ticks: { callback: value => `${new Intl.NumberFormat('es-PE', { maximumFractionDigits: 1 }).format(value)} h` } },
        },
        plugins: {
            legend: { position: 'bottom', labels: { usePointStyle: true, padding: 16, font: { size: 11 } } },
            tooltip: { callbacks: {
                label: context => `${context.dataset.label}: ${duration(context.parsed.y)}`,
                afterBody: items => points[items[0]?.dataIndex]?.note || '',
            } },
        },
    };
}
new Chart(document.getElementById('attendance-work-chart'), {
    type: 'bar', data: { labels, datasets: [dataset('scheduled', 'Programadas netas', '#818cf8'), dataset('worked', 'Trabajadas computables', '#0d9488')] }, options: options(false),
});
new Chart(document.getElementById('attendance-extra-chart'), {
    type: 'bar', data: { labels, datasets: [dataset('pending', 'Pendientes', '#e9ab32'), dataset('approved', 'Aprobadas', '#16a34a'), dataset('rejected', 'Rechazadas', '#e26666')] }, options: options(true),
});
