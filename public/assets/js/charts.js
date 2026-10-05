/**
 * Shared Chart.js helpers + semantic SaaS palette for EMS reports.
 */
(function () {
    'use strict';

    const palette = {
        present: '#22C55E',
        absent: '#EF4444',
        late: '#F59E0B',
        leave: '#FFB547',
        half: '#3BA4FF',
        payroll: '#7251D4',
        loan: '#3BA4FF',
        document: '#00C896',
        chat: '#F35BA6',
        neutral: '#98A2B3',
        primary: '#07989A',
        soft: ['#07989A', '#1688D9', '#7251D4', '#22C55E', '#FFB547', '#EF6351', '#31B5AE', '#4F7CAC', '#8AB17D'],
    };

    const isDark = document.documentElement.getAttribute('data-theme') === 'dark';
    const chartText = isDark ? '#9BABC0' : '#667085';
    const chartMuted = isDark ? '#8395AA' : '#98A2B3';
    const chartGrid = isDark ? '#283748' : '#EEF2F8';

    const defaults = {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                labels: {
                    boxWidth: 12,
                    usePointStyle: true,
                    pointStyle: 'circle',
                    font: { size: 11, family: 'Plus Jakarta Sans', weight: '600' },
                    color: chartText,
                },
            },
            tooltip: {
                mode: 'index',
                intersect: false,
                backgroundColor: '#1D2333',
                titleFont: { family: 'Plus Jakarta Sans', weight: '700' },
                bodyFont: { family: 'Plus Jakarta Sans' },
                cornerRadius: 10,
                padding: 10,
            },
        },
        scales: {
            x: {
                grid: { display: false },
                ticks: { font: { size: 10, family: 'Plus Jakarta Sans' }, color: chartMuted },
                border: { display: false },
            },
            y: {
                beginAtZero: true,
                grid: { color: chartGrid },
                ticks: { font: { size: 10, family: 'Plus Jakarta Sans' }, color: chartMuted },
                border: { display: false },
            },
        },
    };

    const charts = {};

    function destroy(id) {
        if (charts[id]) {
            charts[id].destroy();
            delete charts[id];
        }
    }

    function line(id, labels, datasets, options) {
        destroy(id);
        const ctx = document.getElementById(id);
        if (!ctx || !window.Chart) return null;
        charts[id] = new Chart(ctx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: datasets.map(function (ds, i) {
                    return Object.assign({
                        borderColor: palette.soft[i % palette.soft.length],
                        backgroundColor: hexAlpha(palette.soft[i % palette.soft.length], .14),
                        fill: true,
                        tension: .4,
                        pointRadius: 3,
                        pointHoverRadius: 5,
                        borderWidth: 2.5,
                    }, ds);
                }),
            },
            options: deepMerge(defaults, options || {}),
        });
        return charts[id];
    }

    function doughnut(id, labels, values, colors) {
        destroy(id);
        const ctx = document.getElementById(id);
        if (!ctx || !window.Chart) return null;
        charts[id] = new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: labels,
                datasets: [{
                    data: values,
                    backgroundColor: colors || palette.soft.slice(0, values.length),
                    borderWidth: 0,
                    hoverOffset: 6,
                }],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: { boxWidth: 10, usePointStyle: true, font: { size: 11, family: 'Plus Jakarta Sans', weight: '600' } },
                    },
                },
                cutout: '70%',
            },
        });
        return charts[id];
    }

    function bar(id, labels, datasets, horizontal) {
        destroy(id);
        const ctx = document.getElementById(id);
        if (!ctx || !window.Chart) return null;
        charts[id] = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: datasets.map(function (ds, i) {
                    return Object.assign({
                        backgroundColor: palette.soft[i % palette.soft.length],
                        borderRadius: 10,
                        maxBarThickness: 28,
                    }, ds);
                }),
            },
            options: deepMerge(defaults, {
                indexAxis: horizontal ? 'y' : 'x',
            }),
        });
        return charts[id];
    }

    function hexAlpha(hex, alpha) {
        const h = hex.replace('#', '');
        const r = parseInt(h.substring(0, 2), 16);
        const g = parseInt(h.substring(2, 4), 16);
        const b = parseInt(h.substring(4, 6), 16);
        return 'rgba(' + r + ',' + g + ',' + b + ',' + alpha + ')';
    }

    function deepMerge(a, b) {
        const out = Object.assign({}, a);
        Object.keys(b || {}).forEach(function (k) {
            if (b[k] && typeof b[k] === 'object' && !Array.isArray(b[k]) && typeof b[k] !== 'function') {
                out[k] = deepMerge(a[k] || {}, b[k]);
            } else {
                out[k] = b[k];
            }
        });
        return out;
    }

    function emptyState(container, message) {
        if (!container) return;
        container.innerHTML = '<div class="empty-state"><i data-lucide="inbox"></i>' + (message || 'No data for the selected period.') + '</div>';
        if (window.lucide) lucide.createIcons();
    }

    window.EMSCharts = {
        palette: palette,
        line: line,
        doughnut: doughnut,
        bar: bar,
        destroy: destroy,
        emptyState: emptyState,
    };
})();
