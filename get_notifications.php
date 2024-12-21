<?php
header('Content-Type: application/json');

$host = 'localhost';
$dbname = 'test';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die(json_encode(['error' => "Connection failed: " . $e->getMessage()]));
}

// For this example, we'll assume the receiver's user_id is 1
$user_id = 1;

$last_id = isset($_GET['last_id']) ? intval($_GET['last_id']) : 0;

$start_time = time();
$timeout = 30; // 30 seconds timeout

while (time() - $start_time < $timeout) {
    $stmt = $pdo->prepare("SELECT * FROM notifications WHERE user_id = ? AND id > ? AND is_read = FALSE ORDER BY created_at ASC");
    $stmt->execute([$user_id, $last_id]);
    $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (!empty($notifications)) {
        // Mark notifications as read
        $max_id = max(array_column($notifications, 'id'));
        $stmt = $pdo->prepare("UPDATE notifications SET is_read = TRUE WHERE user_id = ? AND id <= ?");
        $stmt->execute([$user_id, $max_id]);

        echo json_encode(['notifications' => $notifications]);
        exit;
    }

    // Sleep for a short time before checking again
    usleep(50000); // 0.5 seconds
}

// If no new notifications after timeout, return an empty array
echo json_encode(['notifications' => []]);