import Chart from 'chart.js/auto';

const palette = ['#1f5c3b', '#d4a017', '#5aab79', '#0e7490', '#b45309', '#8dc8a2', '#7c3aed', '#be123c', '#57534e', '#f0cf6e'];

Chart.defaults.font.family = "'Instrument Sans', ui-sans-serif, system-ui, sans-serif";
Chart.defaults.color = '#57534e';

/**
 * Alpine component: <canvas x-data="vcChart({type, labels, datasets})"></canvas>
 * Datasets only need {label, data}; colours are applied from the brand palette.
 */
window.vcChart = (config) => ({
    chart: null,
    init() {
        const segmented = ['doughnut', 'pie'].includes(config.type);

        const datasets = config.datasets.map((set, index) => ({
            borderWidth: 2,
            tension: 0.35,
            borderRadius: 4,
            maxBarThickness: 36,
            pointRadius: 2,
            backgroundColor: segmented ? palette : (config.type === 'line' ? palette[index % palette.length] + '22' : palette[index % palette.length]),
            borderColor: segmented ? '#ffffff' : palette[index % palette.length],
            fill: config.type === 'line',
            ...set,
        }));

        this.chart = new Chart(this.$el, {
            type: config.type,
            data: { labels: config.labels, datasets },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                indexAxis: config.horizontal ? 'y' : 'x',
                plugins: {
                    legend: { display: segmented || datasets.length > 1, position: 'bottom', labels: { boxWidth: 12, usePointStyle: true } },
                },
                scales: segmented ? {} : {
                    x: { grid: { display: false }, stacked: !!config.stacked },
                    y: { beginAtZero: true, grid: { color: '#e7e5e4' }, stacked: !!config.stacked },
                },
            },
        });
    },
    destroy() {
        this.chart?.destroy();
    },
});


/**
 * "Fly to cart": clones the product image and animates it to the visible cart button,
 * then pulses the cart. Skipped when the visitor prefers reduced motion.
 */
window.vcBump = (el) => {
    el?.animate([{ transform: 'scale(1)' }, { transform: 'scale(1.18)' }, { transform: 'scale(1)' }], { duration: 380, easing: 'ease-out' });
};

window.vcFly = (button) => {
    const card = button.closest('[data-product-card]');
    const source = card?.querySelector('[data-product-img]');
    const target = [...document.querySelectorAll('[data-cart-target]')].find((el) => el.offsetParent !== null);

    if (!target) return;

    if (!source || window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
        setTimeout(() => window.vcBump(target), 120);
        return;
    }

    const from = source.getBoundingClientRect();
    const to = target.getBoundingClientRect();
    const ghost = source.cloneNode(true);

    Object.assign(ghost.style, {
        position: 'fixed', left: `${from.left}px`, top: `${from.top}px`, width: `${from.width}px`, height: `${from.height}px`,
        margin: '0', zIndex: 9999, pointerEvents: 'none', borderRadius: '12px', overflow: 'hidden', boxShadow: '0 10px 30px rgba(0,0,0,.25)',
    });
    document.body.appendChild(ghost);

    const dx = to.left + to.width / 2 - (from.left + from.width / 2);
    const dy = to.top + to.height / 2 - (from.top + from.height / 2);

    const flight = ghost.animate([
        { transform: 'translate(0,0) scale(1)', opacity: 1 },
        { transform: `translate(${dx * 0.45}px, ${dy * 0.45 - 90}px) scale(0.55)`, opacity: 0.95, offset: 0.5 },
        { transform: `translate(${dx}px, ${dy}px) scale(0.06)`, opacity: 0.4 },
    ], { duration: 720, easing: 'cubic-bezier(.45,0,.25,1)' });

    flight.onfinish = () => {
        ghost.remove();
        window.vcBump(target);
    };
};