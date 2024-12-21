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

// Handle task assignment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['assign_task'])) {
    $waste_id = $_POST['waste_id'];
    $labor_id = $_POST['labor_id'];
    
    try {
        $pdo->beginTransaction();

        // Insert task
        $task_sql = "INSERT INTO tasks (waste_id, labor_id, status) VALUES (:waste_id, :labor_id, 'assigned')";
        $task_stmt = $pdo->prepare($task_sql);
        $task_stmt->execute([':waste_id' => $waste_id, ':labor_id' => $labor_id]);
        $task_id = $pdo->lastInsertId();

        // Get waste details
        $waste_sql = "SELECT w.location, w.waste_type, w.latitude, w.longitude, u.username as reporter_name 
                      FROM waste w 
                      JOIN users u ON w.user_id = u.id 
                      WHERE w.id = :waste_id";
        $waste_stmt = $pdo->prepare($waste_sql);
        $waste_stmt->execute([':waste_id' => $waste_id]);
        $waste_details = $waste_stmt->fetch(PDO::FETCH_ASSOC);

        // Create notification message
        $notification_message = "New task assigned: {$waste_details['waste_type']} waste at {$waste_details['location']}";

        // Insert notification
        $notification_sql = "INSERT INTO notifications (user_id, message, is_read) VALUES (:labor_id, :message, FALSE)";
        $notification_stmt = $pdo->prepare($notification_sql);
        $notification_stmt->execute([
            ':labor_id' => $labor_id,
            ':message' => $notification_message
        ]);

        $pdo->commit();
        $_SESSION['success_message'] = "Task assigned successfully and notification sent to the labor.";
    } catch (PDOException $e) {
        $pdo->rollBack();
        $_SESSION['error_message'] = "Error assigning task: " . $e->getMessage();
    }

    // Redirect to prevent form resubmission
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

// Fetch unassigned tasks
try {
    $sql = "SELECT w.*, u.username
            FROM waste w 
            JOIN users u ON w.user_id = u.id 
            LEFT JOIN tasks t ON w.id = t.waste_id
            WHERE t.id IS NULL
            ORDER BY w.created_at DESC";
    $stmt = $pdo->query($sql);
    $unassigned_tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Error fetching unassigned tasks: " . $e->getMessage());
}

// Fetch laborers
$sql = "SELECT * FROM labor";
$stmt = $pdo->query($sql);
$laborers = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Task Assignment - Waste Management</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100">
    <nav class="bg-green-600 shadow-lg">
        <div class="max-w-7xl mx-auto px-4">
            <div class="flex justify-between">
                <div class="flex space-x-7">
                    <div>
                        <a href="admin.php" class="flex items-center py-4 px-2">
                            <i class="fas fa-recycle text-white text-2xl mr-2"></i>
                            <span class="font-semibold text-white text-lg">Admin Dashboard</span>
                        </a>
                    </div>
                </div>
                <div class="flex items-center space-x-3">
                    <a href="reward_assignment.php" class="py-2 px-4 font-medium text-white bg-blue-500 rounded hover:bg-blue-400 transition duration-300">
                        <i class="fas fa-gift mr-2"></i>Reward Assignment
                    </a>
                    <a href="logout.php" class="py-2 px-4 font-medium text-white bg-red-500 rounded hover:bg-red-400 transition duration-300">
                        <i class="fas fa-sign-out-alt mr-2"></i>Logout
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <div class="container mx-auto px-4 py-8">
        <h1 class="text-3xl font-bold text-gray-800 mb-8">Task Assignment</h1>
        
        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4" role="alert">
                <span class="block sm:inline"><?php echo $_SESSION['success_message']; ?></span>
            </div>
            <?php unset($_SESSION['success_message']); ?>
        <?php endif; ?>

        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">
                <span class="block sm:inline"><?php echo $_SESSION['error_message']; ?></span>
            </div>
            <?php unset($_SESSION['error_message']); ?>
        <?php endif; ?>

        <div class="bg-white shadow-md rounded-lg overflow-hidden">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">User</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Location</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Waste Type</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Action</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php foreach ($unassigned_tasks as $task): ?>
                    <tr class="hover:bg-gray-50 transition duration-150">
                        <td class="px-6 py-4 whitespace-nowrap"><?php echo htmlspecialchars($task['username']); ?></td>
                        <td class="px-6 py-4 whitespace-nowrap"><?php echo htmlspecialchars($task['location']); ?></td>
                        <td class="px-6 py-4 whitespace-nowrap"><?php echo htmlspecialchars($task['waste_type']); ?></td>
                        <td class="px-6 py-4 whitespace-nowrap"><?php echo htmlspecialchars($task['created_at']); ?></td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <form method="POST" class="flex items-center">
                                <input type="hidden" name="waste_id" value="<?php echo $task['id']; ?>">
                                <select name="labor_id" required class="mr-2 px-2 py-1 border rounded focus:outline-none focus:ring-2 focus:ring-blue-500">
                                    <option value="">Select Laborer</option>
                                    <?php foreach ($laborers as $laborer): ?>
                                        <option value="<?php echo $laborer['id']; ?>"><?php echo htmlspecialchars($laborer['name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <button type="submit" name="assign_task" class="bg-blue-500 hover:bg-blue-600 text-white font-bold py-2 px-4 rounded transition duration-300">
                                    Assign Task
                                </button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($unassigned_tasks)): ?>
                    <tr>
                        <td colspan="5" class="px-6 py-4 text-center text-gray-500">No unassigned tasks</td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>
