<?php
session_start();

$host = 'localhost';
$dbname = 'test';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

// Assume the labor is logged in and has an ID in the session
$labor_id = $_SESSION['labor_id'] ?? 1; // Replace with actual session variable

// Predefined cities
$cities = [
    ['name' => 'Mumbai', 'lat' => 19.0760, 'lng' => 72.8777],
    ['name' => 'Delhi', 'lat' => 28.6139, 'lng' => 77.2090],
    ['name' => 'Bangalore', 'lat' => 12.9716, 'lng' => 77.5946],
    ['name' => 'Hyderabad', 'lat' => 17.3850, 'lng' => 78.4867],
    ['name' => 'Chennai', 'lat' => 13.0827, 'lng' => 80.2707],
    ['name' => 'Kolkata', 'lat' => 22.5726, 'lng' => 88.3639],
    ['name' => 'Pune', 'lat' => 18.5204, 'lng' => 73.8567],
    ['name' => 'Ahmedabad', 'lat' => 23.0225, 'lng' => 72.5714],
    ['name' => 'Jaipur', 'lat' => 26.9124, 'lng' => 75.7873],
    ['name' => 'Surat', 'lat' => 21.1702, 'lng' => 72.8311]
];

// Fetch assigned tasks for these cities
$placeholders = implode(',', array_fill(0, count($cities), '?'));
$city_names = array_column($cities, 'name');
$task_stmt = $pdo->prepare("
    SELECT t.id, t.status, w.waste_type, w.location, w.latitude, w.longitude
    FROM tasks t
    JOIN waste w ON t.waste_id = w.id
    WHERE t.labor_id = ? AND w.location IN ($placeholders) AND t.status != 'completed'
    ORDER BY t.created_at DESC
");
$task_stmt->execute(array_merge([$labor_id], $city_names));
$assigned_tasks = $task_stmt->fetchAll(PDO::FETCH_ASSOC);

// Merge assigned tasks with cities
foreach ($cities as &$city) {
    $city['assigned'] = false;
    foreach ($assigned_tasks as $task) {
        if ($task['location'] == $city['name']) {
            $city['assigned'] = true;
            $city['waste_type'] = $task['waste_type'];
            $city['status'] = $task['status'];
            break;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>WasteWise - Labor Route Map</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.7.1/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.7.1/dist/leaflet.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap');
        body {
            font-family: 'Poppins', sans-serif;
        }
        #map { 
            height: 70vh;
            border-radius: 15px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
        }
    </style>
</head>
<body class="bg-gray-100">
    <nav class="bg-green-600 shadow-lg">
        <div class="max-w-7xl mx-auto px-4">
            <div class="flex justify-between items-center py-4">
                <div class="flex items-center">
                    <a href="labour.php" class="flex items-center">
                        <i class="fas fa-recycle text-white text-2xl mr-2"></i>
                        <span class="font-semibold text-white text-lg">WasteWise Route Map</span>
                    </a>
                </div>
                <div>
                    <a href="labour.php" class="py-2 px-4 font-medium text-green-600 bg-white rounded-full hover:bg-green-50 transition duration-300">
                        <i class="fas fa-arrow-left mr-2"></i>Back to Dashboard
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <div class="container mx-auto px-4 py-8">
        <h1 class="text-3xl font-bold text-gray-800 mb-8">India Waste Collection Map</h1>
        
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <div class="lg:col-span-2">
                <div id="map"></div>
            </div>
            <div>
                <div class="bg-white shadow-md rounded-lg p-6 mb-8">
                    <h2 class="text-xl font-semibold mb-4">Task Summary</h2>
                    <canvas id="taskChart"></canvas>
                </div>
                <div class="bg-white shadow-md rounded-lg p-6">
                    <h2 class="text-xl font-semibold mb-4">Waste Type Distribution</h2>
                    <canvas id="wasteTypeChart"></canvas>
                </div>
            </div>
        </div>

        <div class="mt-8 bg-white shadow-md rounded-lg overflow-hidden">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">City</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Assigned</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Waste Type</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php foreach ($cities as $city): ?>
                    <tr class="hover:bg-gray-50 transition duration-150">
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900"><?php echo htmlspecialchars($city['name']); ?></td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                            <?php echo $city['assigned'] ? '<span class="text-green-600"><i class="fas fa-check-circle mr-1"></i>Yes</span>' : '<span class="text-red-600"><i class="fas fa-times-circle mr-1"></i>No</span>'; ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                            <?php echo $city['assigned'] ? htmlspecialchars($city['waste_type']) : '-'; ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                            <?php if ($city['assigned']): ?>
                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?php echo $city['status'] == 'completed' ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800'; ?>">
                                    <?php echo ucfirst(htmlspecialchars($city['status'])); ?>
                                </span>
                            <?php else: ?>
                                -
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <script>
        var map = L.map('map').setView([20.5937, 78.9629], 5);

        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
        }).addTo(map);

        // Add GeoJSON layer for India's boundaries
        fetch('https://raw.githubusercontent.com/geohacker/india/master/state/india-states.geojson')
            .then(response => response.json())
            .then(data => {
                L.geoJSON(data, {
                    style: {
                        color: "#22c55e",
                        weight: 2,
                        opacity: 0.65,
                        fillColor: "#dcfce7",
                        fillOpacity: 0.3
                    }
                }).addTo(map);
            });

        var cities = <?php echo json_encode($cities); ?>;
        var markers = [];

        var assignedIcon = L.divIcon({
            html: '<i class="fas fa-map-marker-alt text-red-600 text-4xl"></i>',
            iconSize: [20, 20],
            iconAnchor: [10, 20],
            popupAnchor: [0, -20],
            className: 'custom-div-icon'
        });

        var unassignedIcon = L.divIcon({
            html: '<i class="fas fa-map-marker-alt text-gray-400 text-4xl"></i>',
            iconSize: [20, 20],
            iconAnchor: [10, 20],
            popupAnchor: [0, -20],
            className: 'custom-div-icon'
        });

        cities.forEach(function(city) {
            var icon = city.assigned ? assignedIcon : unassignedIcon;
            var marker = L.marker([city.lat, city.lng], {icon: icon}).addTo(map);
            var popupContent = `<strong>${city.name}</strong>`;
            if (city.assigned) {
                popupContent += `<br>Waste Type: ${city.waste_type}<br>Status: ${city.status}`;
            }
            marker.bindPopup(popupContent);
            markers.push(marker);
        });

        var group = new L.featureGroup(markers);
        map.fitBounds(group.getBounds().pad(0.1));

        // Task Summary Chart
        var taskCtx = document.getElementById('taskChart').getContext('2d');
        var assignedTasks = cities.filter(city => city.assigned).length;
        var unassignedTasks = cities.length - assignedTasks;
        new Chart(taskCtx, {
            type: 'doughnut',
            data: {
                labels: ['Assigned', 'Unassigned'],
                datasets: [{
                    data: [assignedTasks, unassignedTasks],
                    backgroundColor: ['#22c55e', '#e5e7eb']
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'bottom',
                    },
                    title: {
                        display: true,
                        text: 'Task Assignment'
                    }
                }
            }
        });

        // Waste Type Distribution Chart
        var wasteTypeCtx = document.getElementById('wasteTypeChart').getContext('2d');
        var wasteTypes = {};
        cities.forEach(function(city) {
            if (city.assigned) {
                wasteTypes[city.waste_type] = (wasteTypes[city.waste_type] || 0) + 1;
            }
        });
        new Chart(wasteTypeCtx, {
            type: 'bar',
            data: {
                labels: Object.keys(wasteTypes),
                datasets: [{
                    label: 'Number of Tasks',
                    data: Object.values(wasteTypes),
                    backgroundColor: '#22c55e'
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        display: false,
                    },
                    title: {
                        display: true,
                        text: 'Waste Type Distribution'
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            stepSize: 1
                        }
                    }
                }
            }
        });
    </script>
</body>
</html>
