(() => {
    if (!window.__yjhChartsThemeSyncBound) {
        window.__yjhChartsThemeSyncBound = true;
        window.addEventListener('yjh:theme-changed', () => {
            window.setTimeout(() => window.location.reload(), 40);
        });
    }

    const t = (value) => (window.uiT ? window.uiT(value) : value);
    const bodyStyles = getComputedStyle(document.body);
    const textColor = bodyStyles.getPropertyValue('--text')?.trim() || '#e2e8f0';
    const gridColor = 'rgba(148, 163, 184, 0.18)';
    const isDark = document.documentElement.classList.contains('theme-dark') || document.body.classList.contains('theme-dark');
    const contrastText = isDark ? '#ffffff' : '#0f172a';
    const tickColor = textColor;
    const legendColor = textColor;
    const palette = {
        opened: '#ef4444',
        closed: '#3b82f6',
        inProgress: '#22c55e'
    };
    const withProject = (path, params, projectId) => {
        const query = new URLSearchParams(params || {});
        if (Number(projectId) > 0) {
            query.set('project_id', String(projectId));
        }
        const qs = query.toString();
        return qs ? `${path}?${qs}` : path;
    };

    const commonOptions = {
        responsive: true,
        maintainAspectRatio: false,
        animation: {
            duration: 700,
            easing: 'easeOutQuart'
        },
        interaction: {
            mode: 'nearest',
            intersect: true
        },
        plugins: {
            legend: {
                labels: {
                    color: legendColor,
                    font: {
                        size: 14,
                        weight: '700'
                    },
                    boxWidth: 14,
                    boxHeight: 14,
                    usePointStyle: true,
                    pointStyle: 'circle',
                    padding: 18
                }
            },
            tooltip: {
                backgroundColor: isDark ? '#0b1220' : '#ffffff',
                titleColor: contrastText,
                bodyColor: contrastText,
                borderColor: gridColor,
                borderWidth: 1,
                padding: 10,
                cornerRadius: 10
            }
        }
    };

    if (window.Chart && Chart.defaults) {
        Chart.defaults.color = textColor;
        Chart.defaults.borderColor = gridColor;
    }

    const donut = document.getElementById('chartPassFail');
    if (donut) {
        donut.style.cursor = 'pointer';
        const data = JSON.parse(donut.dataset.chart || '{}');
        const projectId = Number(data.project_id || 0);
        const pass = Number(data.pass || 0);
        const fail = Number(data.fail || 0);
        const inProgress = Number(data.in_progress || 0);
        const groups = [
            { label: t('New Bugs'), value: inProgress, result: 'in_progress', color: '#22c55e' },
            { label: t('In Progress'), value: fail, result: 'fail', color: '#ef4444' },
            { label: t('Closed'), value: pass, result: 'pass', color: '#3b82f6' }
        ];
        const total = groups.reduce((sum, group) => sum + group.value, 0);
        const ringBorder = isDark ? '#16233a' : '#e2e8f0';
        const chartValues = total > 0 ? groups.map((group) => group.value) : [1];
        const chartColors = total > 0 ? groups.map((group) => group.color) : [isDark ? '#334155' : '#cbd5e1'];

        const donutChart = new Chart(donut, {
            type: 'doughnut',
            data: {
                labels: total > 0 ? groups.map((item) => item.label) : [t('No open tasks.')],
                datasets: [{
                    data: chartValues,
                    backgroundColor: chartColors,
                    borderColor: ringBorder,
                    borderWidth: 2,
                    hoverOffset: 6
                }]
            },
            options: {
                ...commonOptions,
                cutout: '46%',
                plugins: {
                    ...commonOptions.plugins,
                    legend: {
                        ...commonOptions.plugins.legend,
                        position: 'bottom',
                        align: 'center',
                        labels: {
                            ...commonOptions.plugins.legend.labels,
                            boxWidth: 10,
                            boxHeight: 10,
                            padding: 10,
                            generateLabels(chart) {
                                if (total <= 0) {
                                    return [{
                                        text: t('No open tasks.'),
                                        color: legendColor,
                                        fontColor: legendColor,
                                        fillStyle: chartColors[0],
                                        strokeStyle: chartColors[0],
                                        lineWidth: 0,
                                        hidden: false,
                                        index: 0
                                    }];
                                }
                                const dataset = chart.data.datasets[0];
                                const bg = dataset.backgroundColor || [];
                                return groups.map((group, index) => ({
                                    text: `${group.label}: ${group.value}`,
                                    color: legendColor,
                                    fontColor: legendColor,
                                    fillStyle: bg[index] || group.color,
                                    strokeStyle: bg[index] || group.color,
                                    lineWidth: 0,
                                    hidden: false,
                                    index
                                }));
                            }
                        }
                    },
                    tooltip: {
                        ...commonOptions.plugins.tooltip,
                        callbacks: {
                            label(context) {
                                if (total <= 0) return t('No open tasks.');
                                const meta = groups[context.dataIndex] || groups[0];
                                const value = Number(meta.value || 0);
                                const pct = total > 0 ? Math.round((value / total) * 100) : 0;
                                return `${meta.label}: ${value} (${pct}%)`;
                            }
                        }
                    }
                }
            }
        });
        donut.addEventListener('click', (event) => {
            const points = donutChart.getElementsAtEventForMode(event, 'nearest', { intersect: true }, true);
            if (!points.length) {
                return;
            }
            const index = points[0].index;
            const meta = groups[index];
            const result = meta ? meta.result : '';
            if (!result) return;
            window.location.href = withProject('/testruns.php', { result }, projectId);
        });
    }

    const bar = document.getElementById('chartPriority');
    if (bar) {
        bar.style.cursor = 'pointer';
        const data = JSON.parse(bar.dataset.chart || '{}');
        const projectId = Number(data.project_id || 0);
        const barChart = new Chart(bar, {
            type: 'bar',
            data: {
                labels: data.labels || [],
                datasets: [{
                    label: t('High Priority Bugs'),
                    data: data.values || [],
                    backgroundColor: palette.closed,
                    borderRadius: 8,
                    borderSkipped: false,
                    maxBarThickness: 34,
                    barPercentage: 0.5,
                    categoryPercentage: 0.62
                }]
            },
            options: {
                ...commonOptions,
                plugins: {
                    ...commonOptions.plugins,
                    legend: {
                        ...commonOptions.plugins.legend,
                        position: 'top',
                        align: 'center'
                    }
                },
                scales: {
                    x: {
                        ticks: { color: tickColor, font: { size: 12, weight: '600' } },
                        grid: { color: gridColor }
                    },
                    y: {
                        beginAtZero: true,
                        ticks: { color: tickColor, precision: 0, stepSize: 1, font: { size: 12, weight: '600' } },
                        grid: { color: gridColor }
                    }
                }
            }
        });
        bar.addEventListener('click', (event) => {
            const points = barChart.getElementsAtEventForMode(event, 'nearest', { intersect: true }, true);
            if (!points.length) {
                return;
            }
            const label = data.labels?.[points[0].index];
            if (!label) {
                return;
            }
            window.location.href = withProject('/bugs.php', { priority_group: 'high', status: label }, projectId);
        });
    }

    const line = document.getElementById('chartTrends');
    if (line) {
        line.style.cursor = 'pointer';
        const data = JSON.parse(line.dataset.chart || '{}');
        const projectId = Number(data.project_id || 0);
        const lineChart = new Chart(line, {
            type: 'line',
            data: {
                labels: data.labels || [],
                datasets: [
                    {
                        label: t('Opened'),
                        data: data.opened || [],
                        borderColor: palette.opened,
                        backgroundColor: (context) => {
                            const { chart } = context;
                            const { ctx, chartArea } = chart;
                            if (!chartArea) return 'rgba(239, 68, 68, 0.14)';
                            const gradient = ctx.createLinearGradient(0, chartArea.top, 0, chartArea.bottom);
                            gradient.addColorStop(0, 'rgba(239, 68, 68, 0.24)');
                            gradient.addColorStop(1, 'rgba(239, 68, 68, 0.04)');
                            return gradient;
                        },
                        pointBackgroundColor: palette.opened,
                        pointBorderColor: palette.opened,
                        pointRadius: 3,
                        pointHoverRadius: 6,
                        tension: 0.45,
                        borderWidth: 2.5,
                        fill: true
                    },
                    {
                        label: t('Closed'),
                        data: data.closed || [],
                        borderColor: palette.closed,
                        backgroundColor: (context) => {
                            const { chart } = context;
                            const { ctx, chartArea } = chart;
                            if (!chartArea) return 'rgba(59, 130, 246, 0.12)';
                            const gradient = ctx.createLinearGradient(0, chartArea.top, 0, chartArea.bottom);
                            gradient.addColorStop(0, 'rgba(59, 130, 246, 0.2)');
                            gradient.addColorStop(1, 'rgba(59, 130, 246, 0.04)');
                            return gradient;
                        },
                        pointBackgroundColor: palette.closed,
                        pointBorderColor: palette.closed,
                        pointRadius: 3,
                        pointHoverRadius: 6,
                        tension: 0.45,
                        borderWidth: 2.5,
                        fill: true
                    }
                ]
            },
            options: {
                ...commonOptions,
                scales: {
                    x: {
                        ticks: { color: tickColor, font: { size: 13, weight: '600' } },
                        grid: { color: gridColor }
                    },
                    y: {
                        beginAtZero: true,
                        ticks: { color: tickColor, font: { size: 13, weight: '600' }, precision: 0 },
                        grid: { color: gridColor }
                    }
                }
            }
        });
        line.addEventListener('click', (event) => {
            const points = lineChart.getElementsAtEventForMode(event, 'nearest', { intersect: true }, true);
            if (!points.length) {
                return;
            }
            const point = points[0];
            const date = data.labels?.[point.index];
            if (!date) {
                return;
            }
            if (point.datasetIndex === 0) {
                window.location.href = withProject('/bugs.php', { created_date: date }, projectId);
                return;
            }
            window.location.href = withProject('/bugs.php', { status: 'closed', closed_date: date }, projectId);
        });
    }
})();




