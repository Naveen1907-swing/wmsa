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

// Handle reward assignment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['assign_reward'])) {
    $waste_id = $_POST['waste_id'];
    $reward_points = $_POST['reward_points'];
    $sql = "UPDATE waste SET status = 'approved', reward_points = :reward_points WHERE id = :waste_id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':reward_points' => $reward_points, ':waste_id' => $waste_id]);
}

// Fetch pending waste data
try {
    $sql = "SELECT w.*, u.username
            FROM waste w 
            JOIN users u ON w.user_id = u.id 
            WHERE w.status IS NULL OR w.status = 'pending'
            ORDER BY w.created_at DESC";
    $stmt = $pdo->query($sql);
    $pending_waste = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Error fetching pending waste data: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reward Assignment - Waste Management</title>
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
                    <a href="task_assignment.php" class="py-2 px-4 font-medium text-white bg-yellow-500 rounded hover:bg-yellow-400 transition duration-300">
                        <i class="fas fa-tasks mr-2"></i>Task Assignment
                    </a>
                    <a href="logout.php" class="py-2 px-4 font-medium text-white bg-red-500 rounded hover:bg-red-400 transition duration-300">
                        <i class="fas fa-sign-out-alt mr-2"></i>Logout
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <div class="container mx-auto px-4 py-8">
        <h1 class="text-3xl font-bold text-gray-800 mb-8">Reward Assignment</h1>
        
        <div class="bg-white shadow-md rounded-lg overflow-hidden">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">User</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Waste Type</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Image</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Action</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php foreach ($pending_waste as $waste): ?>
                    <tr class="hover:bg-gray-50 transition duration-150">
                        <td class="px-6 py-4 whitespace-nowrap"><?php echo htmlspecialchars($waste['username']); ?></td>
                        <td class="px-6 py-4 whitespace-nowrap"><?php echo htmlspecialchars($waste['waste_type']); ?></td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <img src="<?php echo htmlspecialchars($waste['image_path']); ?>" alt="Waste Image" class="w-20 h-20 object-cover rounded shadow">
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap"><?php echo htmlspecialchars($waste['created_at']); ?></td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <form method="POST" class="flex items-center">
                                <input type="hidden" name="waste_id" value="<?php echo $waste['id']; ?>">
                                <input type="number" name="reward_points" min="0" max="100" required class="mr-2 w-20 px-2 py-1 border rounded focus:outline-none focus:ring-2 focus:ring-green-500" placeholder="Points">
                                <button type="submit" name="assign_reward" class="bg-green-500 hover:bg-green-600 text-white font-bold py-2 px-4 rounded transition duration-300">
                                    Approve & Reward
                                </button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($pending_waste)): ?>
                    <tr>
                        <td colspan="5" class="px-6 py-4 text-center text-gray-500">No pending approvals</td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>
