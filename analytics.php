<?php
session_start();

// Database connection
$host = 'localhost';
$dbname = 'test';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("ERROR: Could not connect. " . $e->getMessage());
}

// Fetch waste data for analytics
$sql = "SELECT location, waste_type, created_at, status FROM waste ORDER BY created_at DESC";
$stmt = $pdo->query($sql);
$waste_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Process data for charts
$locations = [];
$waste_types = [];
$monthly_collection = [];
$status_count = ['approved' => 0, 'pending' => 0, 'rejected' => 0];

foreach ($waste_data as $waste) {
    $locations[$waste['location']] = ($locations[$waste['location']] ?? 0) + 1;
    $waste_types[$waste['waste_type']] = ($waste_types[$waste['waste_type']] ?? 0) + 1;
    $month = date('Y-m', strtotime($waste['created_at']));
    $monthly_collection[$month] = ($monthly_collection[$month] ?? 0) + 1;
    $status_count[$waste['status']]++;
}

arsort($locations);
arsort($waste_types);
ksort($monthly_collection);

$top_locations = array_slice($locations, 0, 5);
$top_waste_types = array_slice($waste_types, 0, 5);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>WasteWise Analytics</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap');
        body {
            font-family: 'Poppins', sans-serif;
        }
    </style>
</head>
<body class="bg-gray-100">
    <nav class="bg-green-600 shadow-lg">
        <div class="max-w-7xl mx-auto px-4">
            <div class="flex justify-between items-center py-4">
                <div class="flex items-center">
                    <a href="admin.php" class="flex items-center">
                        <i class="fas fa-chart-line text-white text-2xl mr-2"></i>
                        <span class="font-semibold text-white text-lg">WasteWise Analytics</span>
                    </a>
                </div>
                <div>
                    <a href="admin.php" class="py-2 px-4 font-medium text-green-600 bg-white rounded-full hover:bg-green-50 transition duration-300">
                        <i class="fas fa-arrow-left mr-2"></i>Back to Dashboard
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <div class="container mx-auto px-4 py-8">
        <h1 class="text-3xl font-bold text-gray-800 mb-8">Waste Collection Analytics</h1>
        
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
            <div class="bg-white rounded-lg shadow-md p-6 border-l-4 border-blue-500">
                <h2 class="text-xl font-semibold mb-2 text-gray-700">Total Collections</h2>
                <p class="text-4xl font-bold text-blue-600"><?php echo count($waste_data); ?></p>
            </div>
            <div class="bg-white rounded-lg shadow-md p-6 border-l-4 border-green-500">
                <h2 class="text-xl font-semibold mb-2 text-gray-700">Approved</h2>
                <p class="text-4xl font-bold text-green-600"><?php echo $status_count['approved']; ?></p>
            </div>
            <div class="bg-white rounded-lg shadow-md p-6 border-l-4 border-yellow-500">
                <h2 class="text-xl font-semibold mb-2 text-gray-700">Pending</h2>
                <p class="text-4xl font-bold text-yellow-600"><?php echo $status_count['pending']; ?></p>
            </div>
            <div class="bg-white rounded-lg shadow-md p-6 border-l-4 border-red-500">
                <h2 class="text-xl font-semibold mb-2 text-gray-700">Rejected</h2>
                <p class="text-4xl font-bold text-red-600"><?php echo $status_count['rejected']; ?></p>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-8">
            <div class="bg-white rounded-lg shadow-md p-6">
                <h2 class="text-2xl font-semibold mb-4 text-gray-800">Top 5 Waste Collection Areas</h2>
                <div id="topLocationsChart"></div>
            </div>
            <div class="bg-white rounded-lg shadow-md p-6">
                <h2 class="text-2xl font-semibold mb-4 text-gray-800">Top 5 Waste Types</h2>
                <div id="topWasteTypesChart"></div>
            </div>
        </div>

        <div class="grid grid-cols-1 gap-8 mb-8">
            <div class="bg-white rounded-lg shadow-md p-6">
                <h2 class="text-2xl font-semibold mb-4 text-gray-800">Monthly Waste Collection Trend</h2>
                <canvas id="monthlyTrendChart"></canvas>
            </div>
        </div>

        <div class="bg-white rounded-lg shadow-md p-6">
            <h2 class="text-2xl font-semibold mb-4 text-gray-800">Waste Collection Status</h2>
            <div id="statusChart"></div>
        </div>
    </div>

    <script>
        // Top 5 Waste Collection Areas Chart
        var topLocationsOptions = {
            series: [{
                data: <?php echo json_encode(array_values($top_locations)); ?>
            }],
            chart: {
                type: 'bar',
                height: 350
            },
            plotOptions: {
                bar: {
                    borderRadius: 4,
                    horizontal: true,
                }
            },
            dataLabels: {
                enabled: false
            },
            xaxis: {
                categories: <?php echo json_encode(array_keys($top_locations)); ?>,
            },
            colors: ['#4CAF50']
        };

        var topLocationsChart = new ApexCharts(document.querySelector("#topLocationsChart"), topLocationsOptions);
        topLocationsChart.render();

        // Top 5 Waste Types Chart
        var topWasteTypesOptions = {
            series: <?php echo json_encode(array_values($top_waste_types)); ?>,
            chart: {
                type: 'donut',
                height: 350
            },
            labels: <?php echo json_encode(array_keys($top_waste_types)); ?>,
            responsive: [{
                breakpoint: 480,
                options: {
                    chart: {
                        width: 200
                    },
                    legend: {
                        position: 'bottom'
                    }
                }
            }],
            colors: ['#FF6384', '#36A2EB', '#FFCE56', '#4BC0C0', '#9966FF']
        };

        var topWasteTypesChart = new ApexCharts(document.querySelector("#topWasteTypesChart"), topWasteTypesOptions);
        topWasteTypesChart.render();

        // Monthly Waste Collection Trend Chart
        var ctx = document.getElementById('monthlyTrendChart').getContext('2d');
        var monthlyTrendChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode(array_keys($monthly_collection)); ?>,
                datasets: [{
                    label: 'Waste Collections',
                    data: <?php echo json_encode(array_values($monthly_collection)); ?>,
                    borderColor: 'rgb(75, 192, 192)',
                    tension: 0.1
                }]
            },
            options: {
                responsive: true,
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });

        // Waste Collection Status Chart
        var statusOptions = {
            series: [<?php echo $status_count['approved']; ?>, <?php echo $status_count['pending']; ?>, <?php echo $status_count['rejected']; ?>],
            chart: {
                type: 'radialBar',
                height: 350
            },
            plotOptions: {
                radialBar: {
                    dataLabels: {
                        name: {
                            fontSize: '22px',
                        },
                        value: {
                            fontSize: '16px',
                        },
                        total: {
                            show: true,
                            label: 'Total',
                            formatter: function (w) {
                                return <?php echo count($waste_data); ?>;
                            }
                        }
                    }
                }
            },
            labels: ['Approved', 'Pending', 'Rejected'],
            colors: ['#4CAF50', '#FFC107', '#F44336']
        };

        var statusChart = new ApexCharts(document.querySelector("#statusChart"), statusOptions);
        statusChart.render();
    </script>
</body>
</html>

