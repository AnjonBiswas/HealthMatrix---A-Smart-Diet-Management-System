(function (window, document) {
    "use strict";

    const THEME_GREEN = "#2ECC71";

    function getCtx(canvasId) {
        const canvas = document.getElementById(canvasId);
        if (!canvas) return null;
        return canvas.getContext("2d");
    }

    function defaultOptions() {
        return {
            responsive: true,
            maintainAspectRatio: false,
            animation: { duration: 700, easing: "easeOutQuart" },
            plugins: {
                legend: { display: true, labels: { usePointStyle: true, boxWidth: 10 } },
                tooltip: { enabled: true, intersect: false, mode: "index" }
            },
        };
    }

    function initCalorieChart(canvasId, consumed, goal) {
        const ctx = getCtx(canvasId); if (!ctx) return null;
        const rem = Math.max(0, (goal || 0) - (consumed || 0));
        return new Chart(ctx, {
            type: "doughnut",
            data: {
                labels: ["Consumed", "Remaining"],
                datasets: [{ data: [consumed || 0, rem], backgroundColor: [THEME_GREEN, "#E5ECEF"], borderWidth: 0 }]
            },
            options: Object.assign(defaultOptions(), { cutout: "68%" })
        });
    }

    function initWeightChart(canvasId, labels, data) {
        const ctx = getCtx(canvasId); if (!ctx) return null;
        const gradient = ctx.createLinearGradient(0, 0, 0, 220);
        gradient.addColorStop(0, "rgba(46,204,113,0.35)");
        gradient.addColorStop(1, "rgba(46,204,113,0.03)");
        return new Chart(ctx, {
            type: "line",
            data: { labels: labels || [], datasets: [{ label: "Weight", data: data || [], borderColor: THEME_GREEN, backgroundColor: gradient, fill: true, tension: 0.35, pointRadius: 3 }] },
            options: defaultOptions()
        });
    }

    function initCalorieHistoryChart(canvasId, labels, data) {
        const ctx = getCtx(canvasId); if (!ctx) return null;
        return new Chart(ctx, {
            type: "bar",
            data: { labels: labels || [], datasets: [{ label: "Calories", data: data || [], backgroundColor: THEME_GREEN, borderRadius: 6 }] },
            options: Object.assign(defaultOptions(), { plugins: Object.assign(defaultOptions().plugins, { legend: { display: false } }) })
        });
    }

    function initMacroChart(canvasId, protein, carbs, fat) {
        const ctx = getCtx(canvasId); if (!ctx) return null;
        return new Chart(ctx, {
            type: "doughnut",
            data: {
                labels: ["Protein", "Carbs", "Fat"],
                datasets: [{
                    data: [protein || 0, carbs || 0, fat || 0],
                    backgroundColor: [THEME_GREEN, "#F39C12", "#3498DB"],
                    borderWidth: 0
                }]
            },
            options: Object.assign(defaultOptions(), { cutout: "62%" })
        });
    }

    function initWaterChart(canvasId, labels, data) {
        const ctx = getCtx(canvasId); if (!ctx) return null;
        return new Chart(ctx, {
            type: "bar",
            data: { labels: labels || [], datasets: [{ label: "Water (ml)", data: data || [], backgroundColor: "#3498DB", borderRadius: 6 }] },
            options: defaultOptions()
        });
    }

    function initRegistrationChart(canvasId, labels, data) {
        const ctx = getCtx(canvasId); if (!ctx) return null;
        return new Chart(ctx, {
            type: "line",
            data: {
                labels: labels || [],
                datasets: [{ label: "Registrations", data: data || [], borderColor: THEME_GREEN, backgroundColor: "rgba(46,204,113,0.14)", fill: true, tension: 0.3, pointRadius: 2.5 }]
            },
            options: defaultOptions()
        });
    }

    function updateChart(chartInstance, newData) {
        if (!chartInstance || !newData) return;
        if (Array.isArray(newData.labels)) chartInstance.data.labels = newData.labels;
        if (Array.isArray(newData.datasets)) chartInstance.data.datasets = newData.datasets;
        if (Array.isArray(newData.data) && chartInstance.data.datasets[0]) chartInstance.data.datasets[0].data = newData.data;
        chartInstance.update();
    }

    window.HMCharts = {
        initCalorieChart,
        initWeightChart,
        initCalorieHistoryChart,
        initMacroChart,
        initWaterChart,
        initRegistrationChart,
        updateChart
    };
})(window, document);

