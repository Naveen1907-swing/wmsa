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
    $update_stmt = $pdo->prepare("UPDATE tasks SET status = ? WHERE id = ? AND labor_id = ?");
    $update_stmt->execute([$new_status, $task_id, $labor_id]);
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
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
        // Request permission for desktop notifications
        if (Notification.permission !== "granted") {
            Notification.requestPermission();
        }

        // Function to handle new notifications
        function handleNewNotification(message, created_at) {
            // Show desktop notification
            if (Notification.permission === "granted") {
                new Notification("New Task Assigned", { body: message });
            }
            // Reload the page to update the task list
            location.reload();
        }

        let lastId = <?php echo empty($notifications) ? 0 : $notifications[0]['id']; ?>;

        function checkNotifications() {
            $.ajax({
                url: 'sse_notifications.php',
                method: 'GET',
                data: { last_id: lastId, user_id: <?php echo $labor_id; ?> },
                dataType: 'json',
                success: function(data) {
                    if (data.notifications && data.notifications.length > 0) {
                        data.notifications.forEach(function(notification) {
                            handleNewNotification(notification.message, notification.created_at);
                        });
                        lastId = data.notifications[data.notifications.length - 1].id;
                    }
                },
                complete: function() {
                    // Check for new notifications every 5 seconds
                    setTimeout(checkNotifications, 5000);
                }
            });
        }

        // Start checking for notifications
        checkNotifications();
    </script>
</body>
</html>
