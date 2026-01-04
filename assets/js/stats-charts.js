/**
 * Stats Charts - ECharts-based time series charts for sysadmin stats
 */

(function() {
    'use strict';

    // Chart instances
    let pollsChart = null;
    let responsesChart = null;
    let emailsChart = null;

    // Current selected days
    let currentDays = '30';

    // Email type colors and labels
    const emailTypes = {
        verification: { color: '#3498db', label: 'Verification' },
        password_reset: { color: '#e74c3c', label: 'Password Reset' },
        invitation: { color: '#2ecc71', label: 'Invitation' },
        response_notification: { color: '#f39c12', label: 'Response Notification' },
        other: { color: '#95a5a6', label: 'Other' }
    };

    /**
     * Calculate 7-day moving average
     */
    function calculateMovingAverage(data, windowSize = 7) {
        const result = [];
        for (let i = 0; i < data.length; i++) {
            if (i < windowSize - 1) {
                // Not enough data points yet
                result.push(null);
            } else {
                let sum = 0;
                for (let j = 0; j < windowSize; j++) {
                    sum += data[i - j];
                }
                result.push(Math.round((sum / windowSize) * 100) / 100);
            }
        }
        return result;
    }

    /**
     * Create chart options for a simple metric (polls, responses)
     */
    function createChartOptions(dates, values, color) {
        const movingAvg = calculateMovingAverage(values);

        return {
            tooltip: {
                trigger: 'axis',
                axisPointer: {
                    type: 'cross'
                }
            },
            grid: {
                left: '3%',
                right: '4%',
                bottom: '3%',
                top: '10%',
                containLabel: true
            },
            xAxis: {
                type: 'category',
                data: dates,
                axisLabel: {
                    rotate: 45,
                    formatter: function(value) {
                        // Show shorter date format
                        const parts = value.split('-');
                        return parts[1] + '/' + parts[2];
                    }
                }
            },
            yAxis: {
                type: 'value',
                minInterval: 1
            },
            series: [
                {
                    name: 'Daily',
                    type: 'bar',
                    data: values,
                    itemStyle: {
                        color: color
                    },
                    barMaxWidth: 20
                },
                {
                    name: '7-day avg',
                    type: 'line',
                    data: movingAvg,
                    smooth: true,
                    lineStyle: {
                        color: '#e74c3c',
                        width: 2
                    },
                    itemStyle: {
                        color: '#e74c3c'
                    },
                    symbol: 'none'
                }
            ]
        };
    }

    /**
     * Create stacked bar chart options for emails
     */
    function createEmailChartOptions(dates, emailData) {
        // Extract data for each email type
        const typeData = {};
        for (const type of Object.keys(emailTypes)) {
            typeData[type] = emailData.map(d => d[type] || 0);
        }

        // Calculate totals for the moving average
        const totals = emailData.map(d => {
            let sum = 0;
            for (const type of Object.keys(emailTypes)) {
                sum += d[type] || 0;
            }
            return sum;
        });
        const movingAvg = calculateMovingAverage(totals);

        // Build series for each email type (stacked bars)
        const series = Object.entries(emailTypes).map(([type, config]) => ({
            name: config.label,
            type: 'bar',
            stack: 'emails',
            data: typeData[type],
            itemStyle: {
                color: config.color
            },
            barMaxWidth: 20
        }));

        // Add moving average line
        series.push({
            name: '7-day avg',
            type: 'line',
            data: movingAvg,
            smooth: true,
            lineStyle: {
                color: '#2c3e50',
                width: 2
            },
            itemStyle: {
                color: '#2c3e50'
            },
            symbol: 'none'
        });

        return {
            tooltip: {
                trigger: 'axis',
                axisPointer: {
                    type: 'cross'
                }
            },
            legend: {
                data: [...Object.values(emailTypes).map(t => t.label), '7-day avg'],
                bottom: 0,
                type: 'scroll'
            },
            grid: {
                left: '3%',
                right: '4%',
                bottom: '15%',
                top: '10%',
                containLabel: true
            },
            xAxis: {
                type: 'category',
                data: dates,
                axisLabel: {
                    rotate: 45,
                    formatter: function(value) {
                        const parts = value.split('-');
                        return parts[1] + '/' + parts[2];
                    }
                }
            },
            yAxis: {
                type: 'value',
                minInterval: 1
            },
            series: series
        };
    }

    /**
     * Initialize or update charts with data
     */
    function updateCharts(historyData) {
        const dates = historyData.map(d => d.date);
        const polls = historyData.map(d => d.polls);
        const responses = historyData.map(d => d.responses);
        const emails = historyData.map(d => d.emails);

        // Initialize charts if needed
        if (!pollsChart) {
            pollsChart = echarts.init(document.getElementById('pollsChart'));
            responsesChart = echarts.init(document.getElementById('responsesChart'));
            emailsChart = echarts.init(document.getElementById('emailsChart'));

            // Handle window resize
            window.addEventListener('resize', function() {
                pollsChart.resize();
                responsesChart.resize();
                emailsChart.resize();
            });
        }

        // Set options
        pollsChart.setOption(createChartOptions(dates, polls, '#3498db'));
        responsesChart.setOption(createChartOptions(dates, responses, '#2ecc71'));
        emailsChart.setOption(createEmailChartOptions(dates, emails));
    }

    /**
     * Fetch stats history from API
     */
    async function fetchStatsHistory(days) {
        try {
            const response = await fetch(`${window.BASE_PATH || ''}/api/sysadmin/stats/history?days=${days}`, {
                credentials: 'same-origin'
            });

            if (!response.ok) {
                throw new Error('Failed to fetch stats history');
            }

            const data = await response.json();
            return data.history;
        } catch (error) {
            console.error('Error fetching stats history:', error);
            return null;
        }
    }

    /**
     * Handle date range button click
     */
    function handleDateRangeClick(event) {
        const button = event.target.closest('[data-days]');
        if (!button) return;

        const days = button.dataset.days;
        if (days === currentDays) return;

        // Update active button
        document.querySelectorAll('.date-range-selector button').forEach(btn => {
            btn.classList.remove('active');
        });
        button.classList.add('active');

        // Fetch and update charts
        currentDays = days;
        loadCharts();
    }

    /**
     * Load charts with current settings
     */
    async function loadCharts() {
        // Show loading state
        [pollsChart, responsesChart, emailsChart].forEach(chart => {
            if (chart) {
                chart.showLoading();
            }
        });

        const historyData = await fetchStatsHistory(currentDays);

        if (historyData) {
            updateCharts(historyData);
        }

        // Hide loading state
        [pollsChart, responsesChart, emailsChart].forEach(chart => {
            if (chart) {
                chart.hideLoading();
            }
        });
    }

    /**
     * Initialize on DOM ready
     */
    function init() {
        // Check if we're on the stats page
        if (!document.getElementById('pollsChart')) {
            return;
        }

        // Attach event listeners
        const selector = document.querySelector('.date-range-selector');
        if (selector) {
            selector.addEventListener('click', handleDateRangeClick);
        }

        // Load initial data
        loadCharts();
    }

    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
