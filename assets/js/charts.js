/**
 * =====================================================
 * ShortLink Pro — Chart Initialization
 * =====================================================
 * Built by Smart Holla Tech
 * =====================================================
 * Reads JSON from <script id="chart-data"> and renders:
 *   - Line chart (clicks over time)
 *   - Doughnut chart (top countries)
 *   - Bar chart (devices)
 *
 * Sizing:
 *   Every <canvas> is wrapped in a .chart-wrap div with a fixed
 *   height. Chart.js fills the wrapper. This keeps charts from
 *   growing taller than intended.
 * =====================================================
 */

'use strict';

(function initCharts() {
    const dataEl = document.getElementById('chart-data');
    if (!dataEl || typeof Chart === 'undefined') return;

    let chartData;
    try {
        chartData = JSON.parse(dataEl.textContent.trim());
    } catch (err) {
        console.error('Invalid chart data:', err);
        return;
    }

    // ---- Force sensible global defaults ----
    Chart.defaults.maintainAspectRatio = false;
    Chart.defaults.responsive = true;
    Chart.defaults.font.family =
        '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif';
    Chart.defaults.font.size = 12;

    // ---- Theme-aware colors ----
    function themeColors() {
        const styles = getComputedStyle(document.documentElement);
        return {
            text:    styles.getPropertyValue('--text').trim()        || '#0f172a',
            muted:   styles.getPropertyValue('--text-muted').trim()  || '#64748b',
            grid:    styles.getPropertyValue('--border').trim()      || '#e2e8f0',
            primary: styles.getPropertyValue('--primary').trim()     || '#6366f1',
            accent:  styles.getPropertyValue('--accent').trim()      || '#06b6d4',
            success: styles.getPropertyValue('--success').trim()     || '#10b981',
            warning: styles.getPropertyValue('--warning').trim()     || '#f59e0b',
            danger:  styles.getPropertyValue('--danger').trim()      || '#ef4444',
        };
    }

    const colors = themeColors();

    // =====================================================
    // 1. Clicks over time (line chart)
    // =====================================================
    const clicksCanvas = document.getElementById('chart-clicks');
    if (clicksCanvas && chartData.clicks) {
        const ctx = clicksCanvas.getContext('2d');
        const gradient = ctx.createLinearGradient(0, 0, 0, 240);
        gradient.addColorStop(0, 'rgba(99, 102, 241, 0.25)');
        gradient.addColorStop(1, 'rgba(99, 102, 241, 0)');

        new Chart(clicksCanvas, {
            type: 'line',
            data: {
                labels: chartData.clicks.labels,
                datasets: [{
                    label: 'Clicks',
                    data: chartData.clicks.values,
                    borderColor: colors.primary,
                    backgroundColor: gradient,
                    borderWidth: 2,
                    fill: true,
                    tension: 0.35,
                    pointBackgroundColor: colors.primary,
                    pointBorderColor: '#fff',
                    pointBorderWidth: 2,
                    pointRadius: 4,
                    pointHoverRadius: 6,
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                animation: { duration: 500 },
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        backgroundColor: colors.text,
                        padding: 10,
                        cornerRadius: 6,
                        titleFont: { weight: '600' },
                        displayColors: false,
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            precision: 0,
                            color: colors.muted,
                            font: { size: 11 },
                        },
                        grid: {
                            color: colors.grid,
                            drawBorder: false,
                        },
                    },
                    x: {
                        ticks: {
                            color: colors.muted,
                            font: { size: 11 },
                        },
                        grid: { display: false },
                    }
                }
            }
        });
    }

    // =====================================================
    // 2. Top countries (doughnut chart)
    // =====================================================
    const countriesCanvas = document.getElementById('chart-countries');
    if (countriesCanvas && chartData.countries && chartData.countries.labels.length) {
        new Chart(countriesCanvas, {
            type: 'doughnut',
            data: {
                labels: chartData.countries.labels,
                datasets: [{
                    data: chartData.countries.values,
                    backgroundColor: [
                        colors.primary,
                        colors.accent,
                        colors.success,
                        colors.warning,
                        colors.danger,
                        '#8b5cf6',
                    ],
                    borderWidth: 0,
                    hoverOffset: 6,
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                animation: { duration: 500 },
                cutout: '65%',
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            color: colors.text,
                            boxWidth: 10,
                            boxHeight: 10,
                            padding: 12,
                            font: { size: 11 },
                        }
                    },
                    tooltip: {
                        backgroundColor: colors.text,
                        cornerRadius: 6,
                    }
                }
            }
        });
    }

    // =====================================================
    // 3. Devices (bar chart)
    // =====================================================
    const devicesCanvas = document.getElementById('chart-devices');
    if (devicesCanvas && chartData.devices && chartData.devices.labels.length) {
        new Chart(devicesCanvas, {
            type: 'bar',
            data: {
                labels: chartData.devices.labels,
                datasets: [{
                    data: chartData.devices.values,
                    backgroundColor: colors.primary,
                    borderRadius: 6,
                    barThickness: 32,
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                animation: { duration: 500 },
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        backgroundColor: colors.text,
                        cornerRadius: 6,
                        displayColors: false,
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            precision: 0,
                            color: colors.muted,
                            font: { size: 11 },
                        },
                        grid: {
                            color: colors.grid,
                            drawBorder: false,
                        },
                    },
                    x: {
                        ticks: {
                            color: colors.muted,
                            font: { size: 11 },
                        },
                        grid: { display: false },
                    }
                }
            }
        });
    }
})();