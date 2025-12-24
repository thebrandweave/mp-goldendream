// Customer Chart Initialization
function initCustomerChart(labels, newCustomers, returningCustomers) {
  console.log("Initializing chart with:", { labels, newCustomers, returningCustomers });

  // Check if we have real data
  const hasRealData = newCustomers.some((value) => value > 0) || returningCustomers.some((value) => value > 0);
  console.log("Has real data:", hasRealData);

  // Get the chart container
  const chartElement = document.getElementById("customer-chart");
  if (!chartElement) {
    console.error("Chart container not found!");
    return null;
  }

  // If no real data, show a message in the chart
  if (!hasRealData) {
    chartElement.innerHTML = '<div style="height:300px;display:flex;align-items:center;justify-content:center;flex-direction:column;"><i class="fas fa-users" style="font-size:48px;color:#d1d1d1;margin-bottom:15px;"></i><span style="color:#6c757d;font-size:14px;">No customer data available yet</span></div>';
    return null;
  }

  try {
    // Clear any existing content
    chartElement.innerHTML = "";

    const options = {
      series: [
        {
          name: "New Customers",
          data: newCustomers,
        },
        {
          name: "Returning Customers",
          data: returningCustomers,
        },
      ],
      chart: {
        height: 300,
        type: "bar",
        toolbar: {
          show: false,
        },
        fontFamily: "Poppins, sans-serif",
      },
      plotOptions: {
        bar: {
          horizontal: false,
          columnWidth: "55%",
          borderRadius: 5,
          endingShape: "rounded",
        },
      },
      dataLabels: {
        enabled: false,
      },
      stroke: {
        show: true,
        width: 2,
        colors: ["transparent"],
      },
      xaxis: {
        categories: labels,
        labels: {
          style: {
            colors: "#6c757d",
            fontSize: "12px",
            fontFamily: "Poppins, sans-serif",
          },
        },
      },
      yaxis: {
        title: {
          text: "Customers",
          style: {
            color: "#6c757d",
            fontSize: "14px",
            fontFamily: "Poppins, sans-serif",
          },
        },
        labels: {
          style: {
            colors: "#6c757d",
            fontSize: "12px",
            fontFamily: "Poppins, sans-serif",
          },
        },
      },
      fill: {
        type: "gradient",
        gradient: {
          shade: "light",
          type: "vertical",
          shadeIntensity: 0.25,
          gradientToColors: undefined,
          inverseColors: true,
          opacityFrom: 1,
          opacityTo: 0.85,
          stops: [50, 100],
        },
      },
      colors: ["#3a7bd5", "#00d2ff"],
      tooltip: {
        y: {
          formatter: function (val) {
            return val + " customers";
          },
        },
      },
      legend: {
        position: "top",
        horizontalAlign: "right",
        fontFamily: "Poppins, sans-serif",
        fontSize: "13px",
        markers: {
          width: 12,
          height: 12,
          radius: 12,
        },
      },
    };

    console.log("Chart options:", options);

    // Create and render the chart
    const chart = new ApexCharts(chartElement, options);
    chart.render();
    console.log("Chart rendered successfully");

    return chart;
  } catch (error) {
    console.error("Error initializing chart:", error);
    chartElement.innerHTML = '<div style="height:300px;display:flex;align-items:center;justify-content:center;flex-direction:column;"><i class="fas fa-exclamation-triangle" style="font-size:48px;color:#f8d7da;margin-bottom:15px;"></i><span style="color:#721c24;font-size:14px;">Error initializing chart</span></div>';
    return null;
  }
}

// Set up month navigation
function setupMonthNavigation() {
  const prevMonthBtn = document.querySelector(".date-nav-btn:first-child");
  const nextMonthBtn = document.querySelector(".date-nav-btn:last-child");

  if (prevMonthBtn && nextMonthBtn) {
    prevMonthBtn.addEventListener("click", function (e) {
      e.preventDefault();
      const currentMonth = new URLSearchParams(window.location.search).get("month");
      const prevMonth = new Date(currentMonth + "-01");
      prevMonth.setMonth(prevMonth.getMonth() - 1);
      const newMonth = prevMonth.toISOString().slice(0, 7);
      window.location.href = `?month=${newMonth}`;
    });

    nextMonthBtn.addEventListener("click", function (e) {
      e.preventDefault();
      const currentMonth = new URLSearchParams(window.location.search).get("month");
      const nextMonth = new Date(currentMonth + "-01");
      nextMonth.setMonth(nextMonth.getMonth() + 1);
      const newMonth = nextMonth.toISOString().slice(0, 7);
      window.location.href = `?month=${newMonth}`;
    });
  }
}

// Initialize when DOM is loaded
document.addEventListener("DOMContentLoaded", function () {
  // Set up month navigation
  setupMonthNavigation();
});
