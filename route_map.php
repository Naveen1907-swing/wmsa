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

// Assuming labor is logged in and has an ID in the session
$labor_id = $_SESSION['labor_id'] ?? 1; // Replace with actual session variable

// Fetch today's tasks for this labor
$sql = "SELECT t.id, w.location, w.waste_type, u.username 
        FROM tasks t 
        JOIN waste w ON t.waste_id = w.id 
        JOIN users u ON w.user_id = u.id 
        WHERE t.labor_id = :labor_id 
        AND DATE(t.created_at) = CURDATE() 
        AND t.status != 'completed'
        ORDER BY t.created_at ASC";
$stmt = $pdo->prepare($sql);
$stmt->execute([':labor_id' => $labor_id]);
$tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);

$cities = [
    'Mumbai', 'Delhi', 'Bangalore', 'Hyderabad', 'Chennai', 
    'Kolkata', 'Pune', 'Ahmedabad', 'Jaipur', 'Surat'
];

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daily Route Map - Waste Collection</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css" rel="stylesheet">
    <style>
        .map-container {
            position: relative;
            width: 100%;
            height: 600px;
            background-color: #f0f0f0;
            border: 2px solid #ccc;
            border-radius: 8px;
            overflow: hidden;
        }
        .map-point {
            position: absolute;
            width: 20px;
            height: 20px;
            background-color: #e74c3c;
            border: 2px solid #c0392b;
            border-radius: 50%;
            display: flex;
            justify-content: center;
            align-items: center;
            color: white;
            font-weight: bold;
            font-size: 12px;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        .map-point.active {
            background-color: #3498db;
            border-color: #2980b9;
            transform: scale(1.2);
        }
        .map-label {
            position: absolute;
            font-size: 12px;
            font-weight: bold;
            color: #333;
            text-shadow: 1px 1px 1px white;
        }
    </style>
</head>
<body class="bg-gray-100">
    <nav class="bg-green-600 shadow-lg">
        <div class="max-w-7xl mx-auto px-4">
            <div class="flex justify-between items-center py-4">
                <div class="flex items-center">
                    <a href="#" class="flex items-center">
                        <i class="fas fa-recycle text-white text-2xl mr-2"></i>
                        <span class="font-semibold text-white text-lg">Daily Route Map</span>
                    </a>
                </div>
                <div>
                    <a href="labour.php" class="text-white hover:text-gray-200 mr-4">Dashboard</a>
                    <a href="logout.php" class="py-2 px-4 font-medium text-white bg-red-500 rounded hover:bg-red-400 transition duration-300">
                        <i class="fas fa-sign-out-alt mr-2"></i>Logout
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <div class="container mx-auto px-4 py-8">
        <h1 class="text-3xl font-bold text-gray-800 mb-8">Today's Waste Collection Route</h1>
        
        <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
            <div class="bg-white shadow-md rounded-lg p-6">
                <h2 class="text-xl font-semibold mb-4">Collection Points</h2>
                <ul class="space-y-4">
                    <?php foreach ($tasks as $index => $task): ?>
                    <li class="flex items-center">
                        <span class="bg-green-500 text-white rounded-full w-6 h-6 flex items-center justify-center mr-3"><?php echo $index + 1; ?></span>
                        <div>
                            <p class="font-semibold"><?php echo htmlspecialchars($task['location']); ?></p>
                            <p class="text-sm text-gray-600">
                                <?php echo htmlspecialchars($task['username']); ?>
                            </p>
                            <p class="text-sm text-gray-600">Waste Type: <?php echo htmlspecialchars($task['waste_type']); ?></p>
                        </div>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <div class="bg-white shadow-md rounded-lg p-6">
                <h2 class="text-xl font-semibold mb-4">Route Visualization</h2>
                <div class="map-container" id="map">
                    <!-- Map points will be added here by JavaScript -->
                </div>
            </div>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const map = document.getElementById('map');
        const tasks = <?php echo json_encode($tasks); ?>;
        const cities = <?php echo json_encode($cities); ?>;
        const totalCities = cities.length;

        cities.forEach((city, index) => {
            const angle = (index / totalCities) * 2 * Math.PI;
            const radius = 250;
            const x = 300 + radius * Math.cos(angle);
            const y = 300 + radius * Math.sin(angle);

            const point = document.createElement('div');
            point.className = 'map-point';
            point.textContent = index + 1;
            point.style.left = `${x}px`;
            point.style.top = `${y}px`;
            map.appendChild(point);

            const label = document.createElement('div');
            label.className = 'map-label';
            label.textContent = city;
            label.style.left = `${x + 15}px`;
            label.style.top = `${y + 15}px`;
            map.appendChild(label);

            // Check if this city has an assigned task
            const hasTask = tasks.some(task => task.location.toLowerCase() === city.toLowerCase());
            if (hasTask) {
                point.classList.add('active');
            }
        });
    });
    </script>
</body>
</html>
