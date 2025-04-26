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

// Fetch unread notifications (keep this for functionality)
$stmt = $pdo->prepare("SELECT * FROM notifications WHERE user_id = ? AND is_read = FALSE ORDER BY created_at DESC");
$stmt->execute([$labor_id]);
$notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch assigned tasks
$task_stmt = $pdo->prepare("
    SELECT t.id, t.status, w.waste_type, w.location, w.latitude, w.longitude, u.username as reporter_name
    FROM tasks t
    JOIN waste w ON t.waste_id = w.id
    JOIN users u ON w.user_id = u.id
    WHERE t.labor_id = ?
    ORDER BY t.created_at DESC
");
$task_stmt->execute([$labor_id]);
$assigned_tasks = $task_stmt->fetchAll(PDO::FETCH_ASSOC);

// Handle task status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $task_id = $_POST['task_id'];
    $new_status = $_POST['new_status'];
    
    try {
        $pdo->beginTransaction();
        
        // Update task status
        $update_stmt = $pdo->prepare("UPDATE tasks SET status = ? WHERE id = ? AND labor_id = ?");
        $update_stmt->execute([$new_status, $task_id, $labor_id]);

        // If task is marked as completed, process the reward
        if ($new_status === 'completed') {
            // Get waste details
            $waste_sql = "SELECT w.id, w.user_id, w.waste_type, w.ai_analysis 
                         FROM waste w 
                         JOIN tasks t ON w.id = t.waste_id 
                         WHERE t.id = ?";
            $waste_stmt = $pdo->prepare($waste_sql);
            $waste_stmt->execute([$task_id]);
            $waste = $waste_stmt->fetch(PDO::FETCH_ASSOC);

            // Calculate reward points
            $volume = 0;
            if (preg_match('/Estimated Volume: ([\d.]+) m³/', $waste['ai_analysis'], $matches)) {
                $volume = floatval($matches[1]);
            }

            // Calculate reward based on waste type and volume
            $points_per_m3 = [
                'Plastic' => 20,
                'Paper' => 15,
                'Glass' => 25,
                'Metal' => 30,
                'E-waste' => 40,
                'Organic' => 10,
                'Hazardous' => 50,
                'Textile' => 15,
                'Construction' => 20,
                'Other' => 15,
                'Paper, Cardboard' => 15
            ];

            $base_points = isset($points_per_m3[$waste['waste_type']]) ? $points_per_m3[$waste['waste_type']] : 15;
            $reward_points = min(round($base_points * $volume), 100);

            // Update waste status and reward points
            $update_waste_sql = "UPDATE waste SET status = 'approved', reward_points = ? WHERE id = ?";
            $update_waste_stmt = $pdo->prepare($update_waste_sql);
            $update_waste_stmt->execute([$reward_points, $waste['id']]);

            // Add notification for the user
            $notify_sql = "INSERT INTO notifications (user_id, notification_type, waste_type, message, is_read) 
                          VALUES (?, 'reward_assigned', ?, ?, 0)";
            $notify_stmt = $pdo->prepare($notify_sql);
            $notify_stmt->execute([
                $waste['user_id'],
                $waste['waste_type'],
                "You received {$reward_points} points for your {$waste['waste_type']} waste collection task completion."
            ]);
        }

        $pdo->commit();
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Error in labour.php: " . $e->getMessage());
        // Handle error appropriately
        header("Location: " . $_SERVER['PHP_SELF'] . "?error=1");
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Labor Dashboard - WasteWise</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.1.1/css/all.min.css">
</head>
<body class="bg-gray-100">
    <div class="min-h-screen flex flex-col">
        <!-- Navigation -->
        <nav class="bg-green-600 shadow-lg">
            <div class="max-w-7xl mx-auto px-4">
                <div class="flex justify-between items-center py-4">
                    <div class="flex items-center">
                        <i class="fas fa-recycle text-white text-2xl mr-2"></i>
                        <span class="font-semibold text-white text-lg">WasteWise</span>
                    </div>
                    <div class="flex items-center">
                        <a href="#" class="text-white hover:text-green-200 px-3 py-2 rounded-md text-sm font-medium">Dashboard</a>
                        <a href="labor_route_map.php" class="bg-white text-green-600 hover:bg-green-100 px-3 py-2 rounded-md text-sm font-medium ml-4">
                            <i class="fas fa-map-marked-alt mr-2"></i>Route Map
                        </a>
                    </div>
                </div>
            </div>
        </nav>

        <!-- Main Content -->
        <main class="flex-grow container mx-auto px-4 py-8">
            <h1 class="text-3xl font-bold text-gray-800 mb-8">Labor Dashboard</h1>
            
            <!-- Assigned Tasks Section -->
            <div class="bg-white shadow-md rounded-lg overflow-hidden">
                <h2 class="text-2xl font-bold text-gray-800 p-6 bg-gray-50 border-b border-gray-200">
                    <i class="fas fa-tasks mr-2"></i>Assigned Tasks
                </h2>
                <ul class="divide-y divide-gray-200">
                    <?php foreach ($assigned_tasks as $task): ?>
                        <li class="p-6 hover:bg-gray-50 transition duration-150 ease-in-out">
                            <div class="flex flex-col md:flex-row justify-between items-start md:items-center">
                                <div class="mb-4 md:mb-0">
                                    <h3 class="font-semibold text-lg text-gray-800"><?php echo htmlspecialchars($task['waste_type']); ?> Waste</h3>
                                    <p class="text-sm text-gray-600"><i class="fas fa-map-marker-alt mr-2"></i><?php echo htmlspecialchars($task['location']); ?></p>
                                    <p class="text-sm text-gray-600"><i class="fas fa-user mr-2"></i>Reported by: <?php echo htmlspecialchars($task['reporter_name']); ?></p>
                                    <p class="text-sm text-gray-600">
                                        <i class="fas fa-info-circle mr-2"></i>Status: 
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?php echo $task['status'] == 'completed' ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800'; ?>">
                                            <?php echo ucfirst(htmlspecialchars($task['status'])); ?>
                                        </span>
                                    </p>
                                </div>
                                <form method="POST" class="flex items-center">
                                    <input type="hidden" name="task_id" value="<?php echo $task['id']; ?>">
                                    <select name="new_status" class="mr-2 p-2 border rounded-md text-gray-700 focus:outline-none focus:ring-2 focus:ring-green-500">
                                        <option value="in_progress" <?php echo $task['status'] == 'in_progress' ? 'selected' : ''; ?>>In Progress</option>
                                        <option value="completed" <?php echo $task['status'] == 'completed' ? 'selected' : ''; ?>>Completed</option>
                                    </select>
                                    <button type="submit" name="update_status" class="bg-green-500 text-white px-4 py-2 rounded-md hover:bg-green-600 transition duration-300 ease-in-out focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-opacity-50">
                                        Update
                                    </button>
                                </form>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </main>

        <!-- Footer -->
        <footer class="bg-gray-800 text-white py-4">
            <div class="container mx-auto px-4 text-center">
                <p>&copy; 2023 WasteWise. All rights reserved.</p>
            </div>
        </footer>
    </div>

    <!-- Hidden notifications container -->
    <div id="notifications" class="hidden"></div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        // Request notification permission when page loads
        document.addEventListener('DOMContentLoaded', function() {
            if (Notification.permission !== 'granted') {
                Notification.requestPermission();
            }
        });

        function handleNewNotification(message) {
            if (Notification.permission === "granted") {
                const notification = new Notification("WasteWise Task", {
                    body: message,
                    icon: '/path/to/your/logo.png' // Add your logo path here
                });
                
                // Close notification after 5 seconds
                setTimeout(() => {
                    notification.close();
                }, 5000);
                
                // Reload the page to update the task list
                location.reload();
            }
        }

        let lastId = <?php echo empty($notifications) ? 0 : $notifications[0]['id']; ?>;

        function checkNotifications() {
            fetch('sse_notifications.php?' + new URLSearchParams({
                last_id: lastId,
                user_id: laborId
            }))
            .then(response => response.json())
            .then(data => {
                if (data.notifications && data.notifications.length > 0) {
                    data.notifications.forEach(function(notification) {
                        handleNewNotification(notification.message);
                    });
                    lastId = data.notifications[data.notifications.length - 1].id;
                }
            })
            .catch(error => console.error('Error:', error))
            .finally(() => {
                setTimeout(checkNotifications, 5000);
            });
        }

        // Start checking for notifications
        checkNotifications();
    </script>
</body>
</html>
