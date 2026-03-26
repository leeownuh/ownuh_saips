"use strict";

// Override the basic_column_chart with Authentication Events Trend data
document.addEventListener("DOMContentLoaded", function () {
    // Wait for ApexCharts to be loaded, then override the chart
    const overrideChart = () => {
        if (typeof ApexCharts === "undefined") {
            setTimeout(overrideChart, 300);
            return;
        }
        const el = document.querySelector("#basic_column_chart");
        if (!el) return;

        // Destroy any existing chart instance and re-render
        if (el._apexcharts) {
            el._apexcharts.destroy();
        }

        el.innerHTML = "";
        const options = {
            series: [
                { name: "Successful Logins",  data: [312, 289, 401, 367, 443, 512, 478, 530, 495] },
                { name: "Failed Attempts",    data: [44,  38,  67,  52,  71,  89,  63,  77,  58]  },
                { name: "Blocked IPs",        data: [12,  9,   21,  17,  28,  35,  19,  24,  18]  }
            ],
            chart: { type: "bar", height: 350, toolbar: { show: false } },
            plotOptions: { bar: { horizontal: false, columnWidth: "55%", borderRadius: 5, borderRadiusApplication: "end" } },
            dataLabels: { enabled: false },
            stroke: { show: true, width: 2, colors: ["transparent"] },
            xaxis: { categories: ["Feb", "Mar", "Apr", "May", "Jun", "Jul", "Aug", "Sep", "Oct"] },
            yaxis: { title: { text: "Events" } },
            colors: ["#0d6efd", "#ffc107", "#dc3545"],
            fill: { opacity: 1 },
            tooltip: { y: { formatter: function (val) { return val + " events"; } } },
            legend: { position: "bottom" }
        };

        const chart = new ApexCharts(el, options);
        chart.render();
    };

    setTimeout(overrideChart, 800);
});
