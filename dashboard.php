<?php
$menuPath = "./";
$currentPage = "dashboard";
include("./components/sidebar.php");
include("./components/topbar.php");
?>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/apexcharts/dist/apexcharts.css">
<script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>

<style>
    /* Dashboard Styles */
    .dashboard-container {
        padding: 20px;
        font-family: 'Poppins', sans-serif;
    }

    .dashboard-header {
        margin-bottom: 25px;
    }

    .dashboard-title {
        font-size: 24px;
        font-weight: 700;
        color: #2c3e50;
        margin-bottom: 10px;
    }

    .date-range {
        display: flex;
        align-items: center;
        background: #f5f7fa;
        border-radius: 8px;
        padding: 8px 15px;
        max-width: fit-content;
        color: #5d6778;
        font-size: 14px;
        font-weight: 500;
        cursor: pointer;
        transition: all 0.3s ease;
    }

    .date-range:hover {
        background: #edf2f7;
    }

    .date-range i {
        margin-right: 8px;
        color: var(--primary-color);
    }

    /* Stats Cards */
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 20px;
        margin-bottom: 25px;
    }

    .stat-card {
        background: white;
        border-radius: 10px;
        padding: 20px;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
        transition: all 0.3s ease;
        overflow: hidden;
        position: relative;
    }

    .stat-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 8px 15px rgba(0, 0, 0, 0.1);
    }

    .stat-icon {
        position: absolute;
        top: 20px;
        right: 20px;
        width: 48px;
        height: 48px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-size: 22px;
    }

    .customers-icon {
        background: linear-gradient(135deg, #3a7bd5, #00d2ff);
    }

    .revenue-icon {
        background: linear-gradient(135deg, #11998e, #38ef7d);
    }

    .schemes-icon {
        background: linear-gradient(135deg, #f2994a, #f2c94c);
    }

    .payments-icon {
        background: linear-gradient(135deg, #6a11cb, #2575fc);
    }

    .stat-title {
        font-size: 14px;
        color: #6c757d;
        font-weight: 500;
        margin-bottom: 15px;
    }

    .stat-value {
        font-size: 28px;
        font-weight: 700;
        color: #2c3e50;
        margin-bottom: 5px;
    }

    .stat-change {
        display: flex;
        align-items: center;
        font-size: 13px;
        margin-top: 10px;
    }

    .positive-change {
        color: #38ef7d;
    }

    .negative-change {
        color: #f53b57;
    }

    /* Charts Section */
    .charts-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(500px, 1fr));
        gap: 25px;
        margin-bottom: 25px;
    }

    @media (max-width: 1100px) {
        .charts-grid {
            grid-template-columns: 1fr;
        }
    }

    .chart-card {
        background: white;
        border-radius: 10px;
        padding: 20px;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
    }

    .chart-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 20px;
    }

    .chart-title {
        font-size: 18px;
        font-weight: 600;
        color: #2c3e50;
    }

    .chart-actions {
        display: flex;
        align-items: center;
    }

    .chart-action {
        background: #f5f7fa;
        border: none;
        border-radius: 6px;
        padding: 6px 12px;
        font-size: 13px;
        color: #5d6778;
        margin-left: 8px;
        cursor: pointer;
        transition: all 0.3s ease;
    }

    .chart-action:hover {
        background: #edf2f7;
        color: var(--primary-color);
    }

    .chart-action.active {
        background: var(--primary-color);
        color: white;
    }

    .chart-container {
        height: 300px;
    }

    /* Quick Access and Activity Sections */
    .bottom-grid {
        display: grid;
        grid-template-columns: 2fr 1fr;
        gap: 25px;
        margin-bottom: 25px;
    }

    @media (max-width: 992px) {
        .bottom-grid {
            grid-template-columns: 1fr;
        }
    }

    .quick-access {
        background: white;
        border-radius: 10px;
        padding: 20px;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
    }

    .quick-access-title {
        font-size: 18px;
        font-weight: 600;
        color: #2c3e50;
        margin-bottom: 20px;
    }

    .actions-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
        gap: 15px;
    }

    .action-card {
        background: #f5f7fa;
        border-radius: 10px;
        padding: 15px;
        text-align: center;
        transition: all 0.3s ease;
        cursor: pointer;
        text-decoration: none;
    }

    .action-card:hover {
        background: #edf2f7;
        transform: translateY(-3px);
    }

    .action-icon {
        width: 50px;
        height: 50px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto 15px auto;
        color: white;
        font-size: 20px;
    }

    .action-name {
        font-size: 14px;
        font-weight: 500;
        color: #2c3e50;
    }

    .activity-feed {
        background: white;
        border-radius: 10px;
        padding: 20px;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
    }

    .activity-title {
        font-size: 18px;
        font-weight: 600;
        color: #2c3e50;
        margin-bottom: 20px;
    }

    .activity-list {
        max-height: 380px;
        overflow-y: auto;
    }

    .activity-item {
        display: flex;
        padding: 12px 0;
        border-bottom: 1px solid #f0f0f0;
    }

    .activity-item:last-child {
        border-bottom: none;
    }

    .activity-icon {
        width: 36px;
        height: 36px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        margin-right: 15px;
        flex-shrink: 0;
    }

    .activity-content {
        flex: 1;
    }

    .activity-message {
        font-size: 14px;
        color: #2c3e50;
        margin-bottom: 4px;
    }

    .activity-message strong {
        font-weight: 600;
    }

    .activity-time {
        font-size: 12px;
        color: #6c757d;
    }

    /* Recent Payments Table */
    .recent-payments {
        background: white;
        border-radius: 10px;
        padding: 20px;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
        margin-bottom: 25px;
        overflow-x: auto;
    }

    .payments-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 20px;
    }

    .payments-title {
        font-size: 18px;
        font-weight: 600;
        color: #2c3e50;
    }

    .payments-table {
        width: 100%;
        border-collapse: collapse;
    }

    .payments-table th {
        text-align: left;
        padding: 12px 15px;
        background: #f8f9fa;
        font-size: 14px;
        font-weight: 600;
        color: #5d6778;
        border-bottom: 1px solid #dee2e6;
    }

    .payments-table td {
        padding: 12px 15px;
        font-size: 14px;
        color: #2c3e50;
        border-bottom: 1px solid #f0f0f0;
    }

    .payments-table tr:last-child td {
        border-bottom: none;
    }

    .customer-cell {
        display: flex;
        align-items: center;
    }

    .customer-avatar {
        width: 30px;
        height: 30px;
        border-radius: 50%;
        background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
        color: white;
        font-size: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        margin-right: 10px;
    }

    .payment-status {
        padding: 4px 8px;
        border-radius: 20px;
        font-size: 12px;
        font-weight: 500;
    }

    .status-verified {
        background: rgba(56, 239, 125, 0.1);
        color: #11998e;
    }

    .status-pending {
        background: rgba(242, 201, 76, 0.1);
        color: #f2994a;
    }

    .status-rejected {
        background: rgba(245, 59, 87, 0.1);
        color: #f53b57;
    }

    .action-btn {
        background: none;
        border: none;
        color: var(--primary-color);
        cursor: pointer;
        transition: all 0.3s ease;
    }

    .action-btn:hover {
        color: var(--secondary-color);
    }

    /* Scroll customization */
    ::-webkit-scrollbar {
        width: 8px;
        height: 8px;
    }

    ::-webkit-scrollbar-track {
        background: #f1f1f1;
        border-radius: 4px;
    }

    ::-webkit-scrollbar-thumb {
        background: #c1c1c1;
        border-radius: 4px;
    }

    ::-webkit-scrollbar-thumb:hover {
        background: #a8a8a8;
    }

    /* Tooltip Styles */
    .custom-tooltip {
        position: relative;
    }

    .custom-tooltip:hover::after {
        content: attr(data-tooltip);
        position: absolute;
        bottom: 100%;
        left: 50%;
        transform: translateX(-50%);
        background: #2c3e50;
        color: white;
        padding: 5px 10px;
        border-radius: 5px;
        font-size: 12px;
        font-weight: 400;
        white-space: nowrap;
        z-index: 1000;
        margin-bottom: 5px;
    }

    /* Loading animation */
    .loading-overlay {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(255, 255, 255, 0.7);
        display: flex;
        align-items: center;
        justify-content: center;
        z-index: 9999;
    }

    .spinner {
        width: 40px;
        height: 40px;
        border: 4px solid rgba(58, 123, 213, 0.1);
        border-radius: 50%;
        border-left-color: var(--primary-color);
        animation: spin 1s linear infinite;
    }

    @keyframes spin {
        0% {
            transform: rotate(0deg);
        }

        100% {
            transform: rotate(360deg);
        }
    }
</style>

<div class="dashboard-container">
    <div class="dashboard-header">
        <h1 class="dashboard-title">Dashboard Overview</h1>
        <div class="date-range">
            <i class="fas fa-calendar-alt"></i>
            <span>March 1 - March 31, 2023</span>
        </div>
    </div>

    <!-- Stats Cards -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon customers-icon">
                <i class="fas fa-users"></i>
            </div>
            <div class="stat-title">Total Customers</div>
            <div class="stat-value">5,248</div>
            <div class="stat-change positive-change">
                <i class="fas fa-arrow-up"></i>
                <span>12.5% this month</span>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon revenue-icon">
                <i class="fas fa-rupee-sign"></i>
            </div>
            <div class="stat-title">Total Revenue</div>
            <div class="stat-value">₹25.4L</div>
            <div class="stat-change positive-change">
                <i class="fas fa-arrow-up"></i>
                <span>8.2% this month</span>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon schemes-icon">
                <i class="fas fa-project-diagram"></i>
            </div>
            <div class="stat-title">Active Schemes</div>
            <div class="stat-value">24</div>
            <div class="stat-change positive-change">
                <i class="fas fa-arrow-up"></i>
                <span>3 new this month</span>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon payments-icon">
                <i class="fas fa-credit-card"></i>
            </div>
            <div class="stat-title">New Payments</div>
            <div class="stat-value">1,352</div>
            <div class="stat-change negative-change">
                <i class="fas fa-arrow-down"></i>
                <span>5.3% this month</span>
            </div>
        </div>
    </div>

    <!-- Charts Section -->
    <div class="charts-grid">
        <div class="chart-card">
            <div class="chart-header">
                <h3 class="chart-title">Revenue Overview</h3>
                <div class="chart-actions">
                    <button class="chart-action" data-period="day">Day</button>
                    <button class="chart-action active" data-period="week">Week</button>
                    <button class="chart-action" data-period="month">Month</button>
                </div>
            </div>
            <div class="chart-container" id="revenue-chart"></div>
        </div>

        <div class="chart-card">
            <div class="chart-header">
                <h3 class="chart-title">Customer Growth</h3>
                <div class="chart-actions">
                    <button class="chart-action" data-period="day">Day</button>
                    <button class="chart-action active" data-period="week">Week</button>
                    <button class="chart-action" data-period="month">Month</button>
                </div>
            </div>
            <div class="chart-container" id="customers-chart"></div>
        </div>
    </div>

    <!-- Recent Payments Table -->
    <div class="recent-payments">
        <div class="payments-header">
            <h3 class="payments-title">Recent Payments</h3>
            <a href="<?php echo $menuPath; ?>payments.php" class="chart-action">View All</a>
        </div>
        <table class="payments-table">
            <thead>
                <tr>
                    <th>Customer</th>
                    <th>Scheme</th>
                    <th>Date</th>
                    <th>Amount</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>
                        <div class="customer-cell">
                            <div class="customer-avatar">RK</div>
                            <span>Rajesh Kumar</span>
                        </div>
                    </td>
                    <td>Gold Savings</td>
                    <td>Mar 28, 2023</td>
                    <td>₹15,000</td>
                    <td><span class="payment-status status-verified">Verified</span></td>
                    <td>
                        <button class="action-btn custom-tooltip" data-tooltip="View Details">
                            <i class="fas fa-eye"></i>
                        </button>
                    </td>
                </tr>
                <tr>
                    <td>
                        <div class="customer-cell">
                            <div class="customer-avatar">SP</div>
                            <span>Sanjay Patel</span>
                        </div>
                    </td>
                    <td>Silver Plus</td>
                    <td>Mar 27, 2023</td>
                    <td>₹8,500</td>
                    <td><span class="payment-status status-pending">Pending</span></td>
                    <td>
                        <button class="action-btn custom-tooltip" data-tooltip="View Details">
                            <i class="fas fa-eye"></i>
                        </button>
                    </td>
                </tr>
                <tr>
                    <td>
                        <div class="customer-cell">
                            <div class="customer-avatar">AM</div>
                            <span>Anita Mehta</span>
                        </div>
                    </td>
                    <td>Platinum Elite</td>
                    <td>Mar 27, 2023</td>
                    <td>₹25,000</td>
                    <td><span class="payment-status status-verified">Verified</span></td>
                    <td>
                        <button class="action-btn custom-tooltip" data-tooltip="View Details">
                            <i class="fas fa-eye"></i>
                        </button>
                    </td>
                </tr>
                <tr>
                    <td>
                        <div class="customer-cell">
                            <div class="customer-avatar">VJ</div>
                            <span>Vikram Joshi</span>
                        </div>
                    </td>
                    <td>Diamond Prestige</td>
                    <td>Mar 26, 2023</td>
                    <td>₹50,000</td>
                    <td><span class="payment-status status-rejected">Rejected</span></td>
                    <td>
                        <button class="action-btn custom-tooltip" data-tooltip="View Details">
                            <i class="fas fa-eye"></i>
                        </button>
                    </td>
                </tr>
                <tr>
                    <td>
                        <div class="customer-cell">
                            <div class="customer-avatar">NS</div>
                            <span>Neha Singh</span>
                        </div>
                    </td>
                    <td>Gold Savings</td>
                    <td>Mar 25, 2023</td>
                    <td>₹12,000</td>
                    <td><span class="payment-status status-verified">Verified</span></td>
                    <td>
                        <button class="action-btn custom-tooltip" data-tooltip="View Details">
                            <i class="fas fa-eye"></i>
                        </button>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>

    <!-- Quick Access and Activity Feed -->
    <div class="bottom-grid">
        <div class="quick-access">
            <h3 class="quick-access-title">Quick Actions</h3>
            <div class="actions-grid">
                <a href="<?php echo $menuPath; ?>addCustomer.php" class="action-card">
                    <div class="action-icon" style="background: linear-gradient(135deg, #3a7bd5, #00d2ff)">
                        <i class="fas fa-user-plus"></i>
                    </div>
                    <div class="action-name">Add Customer</div>
                </a>
                <a href="<?php echo $menuPath; ?>addPromoter.php" class="action-card">
                    <div class="action-icon" style="background: linear-gradient(135deg, #11998e, #38ef7d)">
                        <i class="fas fa-user-tie"></i>
                    </div>
                    <div class="action-name">Add Promoter</div>
                </a>
                <a href="<?php echo $menuPath; ?>addScheme.php" class="action-card">
                    <div class="action-icon" style="background: linear-gradient(135deg, #f2994a, #f2c94c)">
                        <i class="fas fa-plus-circle"></i>
                    </div>
                    <div class="action-name">New Scheme</div>
                </a>
                <a href="<?php echo $menuPath; ?>verifyPayments.php" class="action-card">
                    <div class="action-icon" style="background: linear-gradient(135deg, #6a11cb, #2575fc)">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="action-name">Verify Payments</div>
                </a>
                <a href="<?php echo $menuPath; ?>reports.php" class="action-card">
                    <div class="action-icon" style="background: linear-gradient(135deg, #1a2a6c, #b21f1f)">
                        <i class="fas fa-chart-bar"></i>
                    </div>
                    <div class="action-name">Reports</div>
                </a>
                <a href="<?php echo $menuPath; ?>addWinner.php" class="action-card">
                    <div class="action-icon" style="background: linear-gradient(135deg, #c31432, #240b36)">
                        <i class="fas fa-trophy"></i>
                    </div>
                    <div class="action-name">Add Winner</div>
                </a>
            </div>
        </div>

        <div class="activity-feed">
            <h3 class="activity-title">Recent Activity</h3>
            <div class="activity-list">
                <div class="activity-item">
                    <div class="activity-icon" style="background: #3a7bd5">
                        <i class="fas fa-user-plus"></i>
                    </div>
                    <div class="activity-content">
                        <div class="activity-message">
                            <strong>New customer</strong> Priya Sharma registered
                        </div>
                        <div class="activity-time">10 minutes ago</div>
                    </div>
                </div>
                <div class="activity-item">
                    <div class="activity-icon" style="background: #11998e">
                        <i class="fas fa-credit-card"></i>
                    </div>
                    <div class="activity-content">
                        <div class="activity-message">
                            <strong>Payment verified</strong> for Rajesh Kumar
                        </div>
                        <div class="activity-time">45 minutes ago</div>
                    </div>
                </div>
                <div class="activity-item">
                    <div class="activity-icon" style="background: #f2994a">
                        <i class="fas fa-user-tie"></i>
                    </div>
                    <div class="activity-content">
                        <div class="activity-message">
                            <strong>New promoter</strong> Vijay Malhotra added
                        </div>
                        <div class="activity-time">1 hour ago</div>
                    </div>
                </div>
                <div class="activity-item">
                    <div class="activity-icon" style="background: #6a11cb">
                        <i class="fas fa-project-diagram"></i>
                    </div>
                    <div class="activity-content">
                        <div class="activity-message">
                            <strong>New scheme</strong> Diamond Plus launched
                        </div>
                        <div class="activity-time">3 hours ago</div>
                    </div>
                </div>
                <div class="activity-item">
                    <div class="activity-icon" style="background: #c31432">
                        <i class="fas fa-trophy"></i>
                    </div>
                    <div class="activity-content">
                        <div class="activity-message">
                            <strong>Winner announced</strong> - Anita Mehta
                        </div>
                        <div class="activity-time">5 hours ago</div>
                    </div>
                </div>
                <div class="activity-item">
                    <div class="activity-icon" style="background: #1a2a6c">
                        <i class="fas fa-cog"></i>
                    </div>
                    <div class="activity-content">
                        <div class="activity-message">
                            <strong>System update</strong> completed successfully
                        </div>
                        <div class="activity-time">Yesterday</div>
                    </div>
                </div>
                <div class="activity-item">
                    <div class="activity-icon" style="background: #f53b57">
                        <i class="fas fa-times-circle"></i>
                    </div>
                    <div class="activity-content">
                        <div class="activity-message">
                            <strong>Payment rejected</strong> for Vikram Joshi
                        </div>
                        <div class="activity-time">Yesterday</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Initialize charts after a small delay to ensure the DOM is ready
        setTimeout(function() {
            initRevenueChart();
            initCustomersChart();

            // Add click event for chart period buttons
            const chartActions = document.querySelectorAll('.chart-action');
            chartActions.forEach(button => {
                button.addEventListener('click', function() {
                    // Get parent chart card
                    const chartCard = this.closest('.chart-card');
                    // Remove active class from all siblings
                    chartCard.querySelectorAll('.chart-action').forEach(btn => {
                        btn.classList.remove('active');
                    });
                    // Add active class to this button
                    this.classList.add('active');

                    // Update chart based on selected period
                    // In a real application, you would fetch new data based on the period
                    const period = this.getAttribute('data-period');
                    const chartId = chartCard.querySelector('.chart-container').id;

                    // Simulate chart update with loading state
                    showLoading();
                    setTimeout(() => {
                        if (chartId === 'revenue-chart') {
                            updateRevenueChart(period);
                        } else if (chartId === 'customers-chart') {
                            updateCustomersChart(period);
                        }
                        hideLoading();
                    }, 800);
                });
            });
        }, 300);
    });

    function showLoading() {
        const overlay = document.createElement('div');
        overlay.className = 'loading-overlay';
        overlay.innerHTML = '<div class="spinner"></div>';
        document.body.appendChild(overlay);
    }

    function hideLoading() {
        const overlay = document.querySelector('.loading-overlay');
        if (overlay) {
            overlay.remove();
        }
    }

    function initRevenueChart() {
        const weeklyData = [48000, 65000, 42000, 76000, 95000, 80000, 54000];
        const options = {
            series: [{
                name: 'Revenue',
                data: weeklyData
            }],
            chart: {
                height: 300,
                type: 'area',
                toolbar: {
                    show: false
                },
                fontFamily: 'Poppins, sans-serif',
            },
            dataLabels: {
                enabled: false
            },
            stroke: {
                curve: 'smooth',
                width: 3,
                colors: ['#3a7bd5']
            },
            xaxis: {
                categories: ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],
                labels: {
                    style: {
                        colors: '#6c757d',
                        fontSize: '12px',
                        fontFamily: 'Poppins, sans-serif',
                    }
                }
            },
            yaxis: {
                labels: {
                    formatter: function(value) {
                        return '₹' + (value / 1000) + 'K';
                    },
                    style: {
                        colors: '#6c757d',
                        fontSize: '12px',
                        fontFamily: 'Poppins, sans-serif',
                    }
                }
            },
            tooltip: {
                y: {
                    formatter: function(value) {
                        return '₹' + value.toLocaleString();
                    }
                }
            },
            fill: {
                type: 'gradient',
                gradient: {
                    shadeIntensity: 1,
                    opacityFrom: 0.7,
                    opacityTo: 0.2,
                    stops: [0, 90, 100],
                    colorStops: [{
                            offset: 0,
                            color: '#3a7bd5',
                            opacity: 0.4
                        },
                        {
                            offset: 100,
                            color: '#3a7bd5',
                            opacity: 0.1
                        }
                    ]
                }
            },
            grid: {
                borderColor: '#f1f1f1',
                row: {
                    colors: ['transparent', 'transparent'],
                    opacity: 0.5
                }
            },
            markers: {
                size: 4,
                colors: ['#3a7bd5'],
                strokeColors: '#fff',
                strokeWidth: 2,
                hover: {
                    size: 7,
                }
            }
        };

        const chart = new ApexCharts(document.querySelector("#revenue-chart"), options);
        chart.render();

        // Save chart instance to window for updating later
        window.revenueChart = chart;
    }

    function updateRevenueChart(period) {
        let newData = [];
        let newCategories = [];

        if (period === 'day') {
            newData = [12000, 18000, 15000, 22000, 19000, 25000, 20000, 17000, 21000, 24000, 22000, 18000];
            newCategories = ['8AM', '9AM', '10AM', '11AM', '12PM', '1PM', '2PM', '3PM', '4PM', '5PM', '6PM', '7PM'];
        } else if (period === 'week') {
            newData = [48000, 65000, 42000, 76000, 95000, 80000, 54000];
            newCategories = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
        } else if (period === 'month') {
            newData = [150000, 220000, 180000, 250000, 210000, 290000, 240000, 260000, 230000, 270000, 300000, 280000];
            newCategories = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
        }

        window.revenueChart.updateOptions({
            series: [{
                data: newData
            }],
            xaxis: {
                categories: newCategories
            }
        });
    }

    function initCustomersChart() {
        const options = {
            series: [{
                name: 'New Customers',
                data: [76, 85, 101, 98, 87, 105, 91]
            }, {
                name: 'Returning Customers',
                data: [44, 55, 57, 56, 61, 58, 63]
            }],
            chart: {
                type: 'bar',
                height: 300,
                toolbar: {
                    show: false
                },
                fontFamily: 'Poppins, sans-serif',
            },
            plotOptions: {
                bar: {
                    horizontal: false,
                    columnWidth: '55%',
                    borderRadius: 5,
                    endingShape: 'rounded'
                },
            },
            dataLabels: {
                enabled: false
            },
            stroke: {
                show: true,
                width: 2,
                colors: ['transparent']
            },
            xaxis: {
                categories: ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],
                labels: {
                    style: {
                        colors: '#6c757d',
                        fontSize: '12px',
                        fontFamily: 'Poppins, sans-serif',
                    }
                }
            },
            yaxis: {
                title: {
                    text: 'Customers',
                    style: {
                        color: '#6c757d',
                        fontSize: '14px',
                        fontFamily: 'Poppins, sans-serif',
                    }
                },
                labels: {
                    style: {
                        colors: '#6c757d',
                        fontSize: '12px',
                        fontFamily: 'Poppins, sans-serif',
                    }
                }
            },
            fill: {
                type: 'gradient',
                gradient: {
                    shade: 'light',
                    type: "vertical",
                    shadeIntensity: 0.25,
                    gradientToColors: undefined,
                    inverseColors: true,
                    opacityFrom: 1,
                    opacityTo: 0.85,
                    stops: [50, 100]
                },
            },
            colors: ['#3a7bd5', '#00d2ff'],
            tooltip: {
                y: {
                    formatter: function(val) {
                        return val + " customers"
                    }
                }
            },
            legend: {
                position: 'top',
                horizontalAlign: 'right',
                fontFamily: 'Poppins, sans-serif',
                fontSize: '13px',
                markers: {
                    width: 12,
                    height: 12,
                    radius: 12
                }
            }
        };

        const chart = new ApexCharts(document.querySelector("#customers-chart"), options);
        chart.render();

        // Save chart instance to window for updating later
        window.customersChart = chart;
    }

    function updateCustomersChart(period) {
        let newDataNew = [];
        let newDataReturning = [];
        let newCategories = [];

        if (period === 'day') {
            newDataNew = [15, 20, 25, 30, 35, 25, 30, 20, 25, 30, 35, 25];
            newDataReturning = [10, 15, 15, 20, 25, 15, 20, 15, 10, 15, 20, 15];
            newCategories = ['8AM', '9AM', '10AM', '11AM', '12PM', '1PM', '2PM', '3PM', '4PM', '5PM', '6PM', '7PM'];
        } else if (period === 'week') {
            newDataNew = [76, 85, 101, 98, 87, 105, 91];
            newDataReturning = [44, 55, 57, 56, 61, 58, 63];
            newCategories = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
        } else if (period === 'month') {
            newDataNew = [320, 350, 450, 380, 410, 430, 470, 520, 490, 540, 560, 610];
            newDataReturning = [220, 240, 270, 250, 230, 260, 280, 300, 320, 350, 380, 400];
            newCategories = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
        }

        window.customersChart.updateOptions({
            series: [{
                name: 'New Customers',
                data: newDataNew
            }, {
                name: 'Returning Customers',
                data: newDataReturning
            }],
            xaxis: {
                categories: newCategories
            }
        });
    }

    // Date range picker functionality
    document.querySelector('.date-range').addEventListener('click', function() {
        // In a real application, you would initialize a date picker here
        // For this example, we'll just show an alert
        alert('Date range picker would open here');
    });

    // Make table rows clickable
    document.querySelectorAll('.payments-table tbody tr').forEach(row => {
        row.addEventListener('click', function() {
            // In a real application, you would redirect to the payment details page
            // For this example, we'll just show an alert
            const customerName = this.querySelector('.customer-cell span').textContent;
            alert(`Viewing payment details for ${customerName}`);
        });

        // Change cursor to pointer to indicate clickable
        row.style.cursor = 'pointer';
    });
</script>