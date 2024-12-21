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

// Fetch waste data
try {
    $sql = "SELECT w.*, u.username, t.status as task_status, l.name as labor_name 
            FROM waste w 
            JOIN users u ON w.user_id = u.id 
            LEFT JOIN tasks t ON w.id = t.waste_id 
            LEFT JOIN labor l ON t.labor_id = l.id 
            ORDER BY w.created_at DESC";
    $stmt = $pdo->query($sql);
    $waste_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Error fetching waste data: " . $e->getMessage());
}

// Fetch notifications
try {
    $notificationSql = "SELECT * FROM notifications WHERE user_id = 0 AND is_read = FALSE ORDER BY created_at DESC LIMIT 5";
    $notificationStmt = $pdo->query($notificationSql);
    $notifications = $notificationStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Error fetching notifications: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Waste Management</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
    function assignTask(form) {
        const formData = new FormData(form);
        fetch('assign_task.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Task assigned successfully');
                location.reload();
            } else {
                alert('Failed to assign task');
            }
        })
        .catch(error => console.error('Error:', error));
        return false;
    }
    </script>
</head>
<body class="bg-gray-100">
    <nav class="bg-green-600 shadow-lg">
        <div class="max-w-7xl mx-auto px-4">
            <div class="flex flex-col md:flex-row justify-between items-center">
                <div class="flex items-center py-4">
                    <a href="#" class="flex items-center">
                        <i class="fas fa-recycle text-white text-2xl mr-2"></i>
                        <span class="font-semibold text-white text-lg">Admin Dashboard</span>
                    </a>
                </div>
                <div class="flex flex-col md:flex-row items-center space-y-2 md:space-y-0 md:space-x-3 pb-4 md:pb-0">
                    <a href="reward_assignment.php" class="w-full md:w-auto py-2 px-4 font-medium text-white bg-blue-500 rounded hover:bg-blue-400 transition duration-300 text-center">
                        <i class="fas fa-gift mr-2"></i>Reward Assignment
                    </a>
                    <a href="task_assignment.php" class="w-full md:w-auto py-2 px-4 font-medium text-white bg-yellow-500 rounded hover:bg-yellow-400 transition duration-300 text-center">
                        <i class="fas fa-tasks mr-2"></i>Task Assignment
                    </a>
                    <a href="analytics.php" class="w-full md:w-auto py-2 px-4 font-medium text-white bg-purple-500 rounded hover:bg-purple-400 transition duration-300 text-center">
                        <i class="fas fa-chart-line mr-2"></i>Analytics
                    </a>
                    <a href="logout.php" class="w-full md:w-auto py-2 px-4 font-medium text-white bg-red-500 rounded hover:bg-red-400 transition duration-300 text-center">
                        <i class="fas fa-sign-out-alt mr-2"></i>Logout
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <div class="container mx-auto px-4 py-8">
        <h1 class="text-3xl font-bold text-gray-800 mb-8">Waste Management Overview</h1>
        
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mb-12">
            <div class="bg-white rounded-lg shadow-md p-6 border-l-4 border-green-500">
                <h2 class="text-xl font-semibold mb-2 text-gray-700">Total Waste Entries</h2>
                <p class="text-4xl font-bold text-green-600"><?php echo count($waste_data); ?></p>
            </div>
            <div class="bg-white rounded-lg shadow-md p-6 border-l-4 border-yellow-500">
                <h2 class="text-xl font-semibold mb-2 text-gray-700">Pending Approvals</h2>
                <p class="text-4xl font-bold text-yellow-600"><?php echo count(array_filter($waste_data, function($w) { return !isset($w['status']) || $w['status'] === 'pending'; })); ?></p>
            </div>
            <div class="bg-white rounded-lg shadow-md p-6 border-l-4 border-blue-500">
                <h2 class="text-xl font-semibold mb-2 text-gray-700">Unassigned Tasks</h2>
                <p class="text-4xl font-bold text-blue-600"><?php echo count(array_filter($waste_data, function($w) { return !isset($w['task_status']); })); ?></p>
            </div>
        </div>

        <div class="overflow-x-auto">
            <h2 class="text-2xl font-bold text-gray-800 mb-4">All Waste Entries</h2>
            <div class="bg-white shadow-md rounded-lg overflow-hidden">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">User</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Location</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Waste Type</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Task Status</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Assigned To</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Assign Task</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($waste_data as $waste): ?>
                        <tr class="hover:bg-gray-50 transition duration-150">
                            <td class="px-4 py-4 whitespace-nowrap text-sm"><?php echo htmlspecialchars($waste['username']); ?></td>
                            <td class="px-4 py-4 whitespace-nowrap text-sm"><?php echo htmlspecialchars($waste['location']); ?></td>
                            <td class="px-4 py-4 whitespace-nowrap text-sm"><?php echo htmlspecialchars($waste['waste_type']); ?></td>
                            <td class="px-4 py-4 whitespace-nowrap text-sm"><?php echo htmlspecialchars($waste['created_at']); ?></td>
                            <td class="px-4 py-4 whitespace-nowrap text-sm">
                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?php echo isset($waste['status']) && $waste['status'] === 'approved' ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800'; ?>">
                                    <?php echo isset($waste['status']) ? htmlspecialchars($waste['status']) : 'pending'; ?>
                                </span>
                            </td>
                            <td class="px-4 py-4 whitespace-nowrap text-sm">
                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?php echo isset($waste['task_status']) ? 'bg-blue-100 text-blue-800' : 'bg-gray-100 text-gray-800'; ?>">
                                    <?php echo $waste['task_status'] ?? 'Unassigned'; ?>
                                </span>
                            </td>
                            <td class="px-4 py-4 whitespace-nowrap text-sm"><?php echo $waste['labor_name'] ?? '-'; ?></td>
                            <td class="px-4 py-4 whitespace-nowrap text-sm">
                                <?php if (!isset($waste['task_status'])): ?>
                                <form onsubmit="return assignTask(this);" class="flex flex-col sm:flex-row items-center">
                                    <input type="hidden" name="waste_id" value="<?php echo $waste['id']; ?>">
                                    <select name="labor_id" required class="w-full sm:w-auto mb-2 sm:mb-0 sm:mr-2 border rounded px-3 py-1 focus:outline-none focus:ring-2 focus:ring-green-500">
                                        <option value="">Select Labor</option>
                                        <?php
                                        $labor_sql = "SELECT id, name FROM labor";
                                        $labor_stmt = $pdo->query($labor_sql);
                                        while ($labor = $labor_stmt->fetch(PDO::FETCH_ASSOC)) {
                                            echo "<option value='{$labor['id']}'>{$labor['name']}</option>";
                                        }
                                        ?>
                                    </select>
                                    <button type="submit" class="w-full sm:w-auto bg-blue-500 hover:bg-blue-600 text-white font-bold py-2 px-4 rounded transition duration-300">
                                        Assign
                                    </button>
                                </form>
                                <?php else: ?>
                                Already assigned
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Notifications -->
        <div class="mb-8">
            <h2 class="text-2xl font-bold text-gray-800 mb-4">Recent Notifications</h2>
            <div class="bg-white shadow-md rounded-lg p-4">
                <?php if (empty($notifications)): ?>
                    <p class="text-gray-600">No new notifications.</p>
                <?php else: ?>
                    <ul class="space-y-2">
                        <?php foreach ($notifications as $notification): ?>
                            <li class="flex items-center justify-between bg-blue-50 p-2 rounded">
                                <span class="text-blue-800"><?php echo htmlspecialchars($notification['message']); ?></span>
                                <span class="text-sm text-gray-500"><?php echo date('M d, H:i', strtotime($notification['created_at'])); ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        // Function to fetch new notifications
        function fetchNotifications() {
            fetch('sse_notifications.php?user_id=0')
                .then(response => response.json())
                .then(data => {
                    if (data.notifications && data.notifications.length > 0) {
                        Swal.fire({
                            title: 'New Notification',
                            text: data.notifications[0].message,
                            icon: 'info',
                            confirmButtonText: 'OK'
                        }).then(() => {
                            location.reload(); // Reload the page to show the new notification in the list
                        });
                    }
                });
        }

        // Check for new notifications every 30 seconds
        setInterval(fetchNotifications, 30000);
    </script>
</body>
</html>
