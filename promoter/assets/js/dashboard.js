// Dashboard Charts and Functionality

// Show loading spinner
function showLoading() {
  const overlay = document.createElement("div");
  overlay.className = "loading-overlay";
  overlay.innerHTML = '<div class="spinner"></div>';
  document.body.appendChild(overlay);
}

// Hide loading spinner
function hideLoading() {
  const overlay = document.querySelector(".loading-overlay");
  if (overlay) {
    overlay.remove();
  }
}

// Initialize Revenue Chart
function initRevenueChart(labels, values) {
  const options = {
    series: [
      {
        name: "Revenue",
        data: values,
      },
    ],
    chart: {
      height: 300,
      type: "area",
      toolbar: {
        show: false,
      },
      fontFamily: "Poppins, sans-serif",
    },
    dataLabels: {
      enabled: false,
    },
    stroke: {
      curve: "smooth",
      width: 3,
      colors: ["#3a7bd5"],
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
      labels: {
        formatter: function (value) {
          if (value >= 100000) {
            return "₹" + (value / 100000).toFixed(1) + "L";
          } else if (value >= 1000) {
            return "₹" + (value / 1000).toFixed(1) + "K";
          } else {
            return "₹" + value.toFixed(0);
          }
        },
        style: {
          colors: "#6c757d",
          fontSize: "12px",
          fontFamily: "Poppins, sans-serif",
        },
      },
    },
    tooltip: {
      y: {
        formatter: function (value) {
          return "₹" + value.toLocaleString();
        },
      },
    },
    fill: {
      type: "gradient",
      gradient: {
        shadeIntensity: 1,
        opacityFrom: 0.7,
        opacityTo: 0.2,
        stops: [0, 90, 100],
        colorStops: [
          {
            offset: 0,
            color: "#3a7bd5",
            opacity: 0.4,
          },
          {
            offset: 100,
            color: "#3a7bd5",
            opacity: 0.1,
          },
        ],
      },
    },
    grid: {
      borderColor: "#f1f1f1",
      row: {
        colors: ["transparent", "transparent"],
        opacity: 0.5,
      },
    },
    markers: {
      size: 4,
      colors: ["#3a7bd5"],
      strokeColors: "#fff",
      strokeWidth: 2,
      hover: {
        size: 7,
      },
    },
  };

  const chart = new ApexCharts(document.querySelector("#revenue-chart"), options);
  chart.render();

  // Save chart instance to window for updating later
  window.revenueChart = chart;
}

// Initialize Customers Chart
function initCustomersChart(labels, newCustomers, returningCustomers) {
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
      type: "bar",
      height: 300,
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

  const chart = new ApexCharts(document.querySelector("#customers-chart"), options);
  chart.render();

  // Save chart instance to window for updating later
  window.customersChart = chart;
}

// Set up period controls for charts
function setupChartPeriodControls() {
  const chartActions = document.querySelectorAll(".chart-action");
  chartActions.forEach((button) => {
    button.addEventListener("click", function () {
      // Get parent chart card
      const chartCard = this.closest(".chart-card");

      // If already active, do nothing
      if (this.classList.contains("active")) {
        return;
      }

      // Remove active class from all siblings
      chartCard.querySelectorAll(".chart-action").forEach((btn) => {
        btn.classList.remove("active");
      });

      // Add active class to this button
      this.classList.add("active");

      // Update chart based on selected period
      const period = this.getAttribute("data-period");
      const chartId = chartCard.querySelector(".chart-container").id;

      // Show loading spinner
      showLoading();

      // Fetch new data from server based on period
      fetch(`../api/chart-data.php?chart=${chartId}&period=${period}`)
        .then((response) => response.json())
        .then((data) => {
          if (chartId === "revenue-chart") {
            window.revenueChart.updateOptions({
              series: [
                {
                  data: data.values,
                },
              ],
              xaxis: {
                categories: data.labels,
              },
            });
          } else if (chartId === "customers-chart") {
            window.customersChart.updateOptions({
              series: [
                {
                  name: "New Customers",
                  data: data.new,
                },
                {
                  name: "Returning Customers",
                  data: data.returning,
                },
              ],
              xaxis: {
                categories: data.labels,
              },
            });
          }
          hideLoading();
        })
        .catch((error) => {
          console.error("Error fetching chart data:", error);
          hideLoading();

          // Fallback to simulated data if API fails
          simulatePeriodChange(chartId, period);
        });
    });
  });
}

// Simulate period change with fake data if API call fails
function simulatePeriodChange(chartId, period) {
  if (chartId === "revenue-chart") {
    let newData = [];
    let newCategories = [];

    if (period === "day") {
      newData = [12000, 18000, 15000, 22000, 19000, 25000, 20000, 17000, 21000, 24000, 22000, 18000];
      newCategories = ["8AM", "9AM", "10AM", "11AM", "12PM", "1PM", "2PM", "3PM", "4PM", "5PM", "6PM", "7PM"];
    } else if (period === "week") {
      newData = [48000, 65000, 42000, 76000, 95000, 80000, 54000];
      newCategories = ["Sun", "Mon", "Tue", "Wed", "Thu", "Fri", "Sat"];
    } else if (period === "month") {
      newData = [150000, 220000, 180000, 250000, 210000, 290000, 240000, 260000, 230000, 270000, 300000, 280000];
      newCategories = ["Jan", "Feb", "Mar", "Apr", "May", "Jun", "Jul", "Aug", "Sep", "Oct", "Nov", "Dec"];
    }

    window.revenueChart.updateOptions({
      series: [
        {
          data: newData,
        },
      ],
      xaxis: {
        categories: newCategories,
      },
    });
  } else if (chartId === "customers-chart") {
    let newDataNew = [];
    let newDataReturning = [];
    let newCategories = [];

    if (period === "day") {
      newDataNew = [15, 20, 25, 30, 35, 25, 30, 20, 25, 30, 35, 25];
      newDataReturning = [10, 15, 18, 22, 25, 20, 18, 15, 20, 22, 25, 18];
      newCategories = ["8AM", "9AM", "10AM", "11AM", "12PM", "1PM", "2PM", "3PM", "4PM", "5PM", "6PM", "7PM"];
    } else if (period === "week") {
      newDataNew = [42, 38, 45, 50, 55, 60, 48];
      newDataReturning = [30, 25, 35, 40, 45, 38, 32];
      newCategories = ["Sun", "Mon", "Tue", "Wed", "Thu", "Fri", "Sat"];
    } else if (period === "month") {
      newDataNew = [120, 150, 135, 180, 190, 170, 160, 185, 175, 200, 190, 210];
      newDataReturning = [85, 95, 105, 125, 140, 135, 120, 130, 145, 155, 150, 165];
      newCategories = ["Jan", "Feb", "Mar", "Apr", "May", "Jun", "Jul", "Aug", "Sep", "Oct", "Nov", "Dec"];
    }

    window.customersChart.updateOptions({
      series: [
        {
          name: "New Customers",
          data: newDataNew,
        },
        {
          name: "Returning Customers",
          data: newDataReturning,
        },
      ],
      xaxis: {
        categories: newCategories,
      },
    });
  }
}

// Quick Actions Event Handlers
function setupQuickActions() {
  // Add Customer Modal
  const addCustomerBtn = document.getElementById("add-customer-btn");
  const addCustomerModal = document.getElementById("add-customer-modal");

  if (addCustomerBtn && addCustomerModal) {
    addCustomerBtn.addEventListener("click", function () {
      addCustomerModal.style.display = "flex";
    });

    // Close modal when clicking outside
    addCustomerModal.addEventListener("click", function (e) {
      if (e.target === this) {
        this.style.display = "none";
      }
    });

    // Close button
    const closeBtn = addCustomerModal.querySelector(".close-modal");
    if (closeBtn) {
      closeBtn.addEventListener("click", function () {
        addCustomerModal.style.display = "none";
      });
    }
  }

  // Process Payment Modal
  const processPaymentBtn = document.getElementById("process-payment-btn");
  const processPaymentModal = document.getElementById("process-payment-modal");

  if (processPaymentBtn && processPaymentModal) {
    processPaymentBtn.addEventListener("click", function () {
      processPaymentModal.style.display = "flex";
    });

    // Close modal when clicking outside
    processPaymentModal.addEventListener("click", function (e) {
      if (e.target === this) {
        this.style.display = "none";
      }
    });

    // Close button
    const closeBtn = processPaymentModal.querySelector(".close-modal");
    if (closeBtn) {
      closeBtn.addEventListener("click", function () {
        processPaymentModal.style.display = "none";
      });
    }
  }

  // Add Scheme Modal
  const addSchemeBtn = document.getElementById("add-scheme-btn");
  const addSchemeModal = document.getElementById("add-scheme-modal");

  if (addSchemeBtn && addSchemeModal) {
    addSchemeBtn.addEventListener("click", function () {
      addSchemeModal.style.display = "flex";
    });

    // Close modal when clicking outside
    addSchemeModal.addEventListener("click", function (e) {
      if (e.target === this) {
        this.style.display = "none";
      }
    });

    // Close button
    const closeBtn = addSchemeModal.querySelector(".close-modal");
    if (closeBtn) {
      closeBtn.addEventListener("click", function () {
        addSchemeModal.style.display = "none";
      });
    }
  }

  // Send Notification Modal
  const sendNotificationBtn = document.getElementById("send-notification-btn");
  const sendNotificationModal = document.getElementById("send-notification-modal");

  if (sendNotificationBtn && sendNotificationModal) {
    sendNotificationBtn.addEventListener("click", function () {
      sendNotificationModal.style.display = "flex";
    });

    // Close modal when clicking outside
    sendNotificationModal.addEventListener("click", function (e) {
      if (e.target === this) {
        this.style.display = "none";
      }
    });

    // Close button
    const closeBtn = sendNotificationModal.querySelector(".close-modal");
    if (closeBtn) {
      closeBtn.addEventListener("click", function () {
        sendNotificationModal.style.display = "none";
      });
    }
  }
}

// Activity Feed Auto-refresh
function setupActivityFeed() {
  const activityFeed = document.querySelector(".activity-feed");
  if (!activityFeed) return;

  // Auto-refresh activity feed every 60 seconds
  setInterval(function () {
    fetch("../api/activity-feed.php")
      .then((response) => response.json())
      .then((data) => {
        if (data.activities && data.activities.length > 0) {
          updateActivityFeed(data.activities);
        }
      })
      .catch((error) => {
        console.error("Error fetching activity feed:", error);
      });
  }, 60000); // 60 seconds
}

// Update Activity Feed with new data
function updateActivityFeed(activities) {
  const activityFeed = document.querySelector(".activity-feed");
  if (!activityFeed) return;

  // Create new activity items
  const newActivities = activities
    .map((activity) => {
      return `
            <div class="activity-item">
                <div class="activity-icon ${getActivityIconClass(activity.type)}">
                    <i class="${getActivityIcon(activity.type)}"></i>
                </div>
                <div class="activity-details">
                    <div class="activity-text">${activity.message}</div>
                    <div class="activity-time">${activity.time}</div>
                </div>
            </div>
        `;
    })
    .join("");

  // Add new activities to the top of the feed
  activityFeed.innerHTML = newActivities + activityFeed.innerHTML;

  // Limit the number of displayed activities to 10
  const items = activityFeed.querySelectorAll(".activity-item");
  if (items.length > 10) {
    for (let i = 10; i < items.length; i++) {
      items[i].remove();
    }
  }
}

// Get activity icon class based on activity type
function getActivityIconClass(type) {
  switch (type) {
    case "payment":
      return "payment-icon";
    case "customer":
      return "customer-icon";
    case "admin":
      return "admin-icon";
    case "promoter":
      return "promoter-icon";
    case "scheme":
      return "scheme-icon";
    case "winner":
      return "winner-icon";
    case "withdrawal":
      return "withdrawal-icon";
    default:
      return "default-icon";
  }
}

// Get activity icon based on activity type
function getActivityIcon(type) {
  switch (type) {
    case "payment":
      return "fas fa-rupee-sign";
    case "customer":
      return "fas fa-user";
    case "admin":
      return "fas fa-user-shield";
    case "promoter":
      return "fas fa-user-tie";
    case "scheme":
      return "fas fa-certificate";
    case "winner":
      return "fas fa-trophy";
    case "withdrawal":
      return "fas fa-money-bill-wave";
    default:
      return "fas fa-bell";
  }
}

// Initialize dashboard charts and functionality
document.addEventListener("DOMContentLoaded", function () {
  // Initialize Charts with default data
  initRevenueChart(["Jan", "Feb", "Mar", "Apr", "May", "Jun", "Jul", "Aug", "Sep", "Oct", "Nov", "Dec"], [150000, 220000, 180000, 250000, 210000, 290000, 240000, 260000, 230000, 270000, 300000, 280000]);

  initCustomersChart(["Jan", "Feb", "Mar", "Apr", "May", "Jun", "Jul", "Aug", "Sep", "Oct", "Nov", "Dec"], [120, 150, 135, 180, 190, 170, 160, 185, 175, 200, 190, 210], [85, 95, 105, 125, 140, 135, 120, 130, 145, 155, 150, 165]);

  // Set up chart period controls
  setupChartPeriodControls();

  // Set up quick actions
  setupQuickActions();

  // Set up activity feed refresh
  setupActivityFeed();

  // Add dynamic behavior to stats cards
  const statsCards = document.querySelectorAll(".stat-card");
  statsCards.forEach((card) => {
    card.addEventListener("mouseover", function () {
      this.style.transform = "translateY(-5px)";
      this.style.boxShadow = "0 8px 15px rgba(0, 0, 0, 0.1)";
    });

    card.addEventListener("mouseout", function () {
      this.style.transform = "translateY(0)";
      this.style.boxShadow = "0 4px 6px rgba(0, 0, 0, 0.05)";
    });
  });
});
