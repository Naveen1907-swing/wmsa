<?php
header('Content-Type: application/json');
header('Cache-Control: no-cache');

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

$user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;
$last_id = isset($_GET['last_id']) ? intval($_GET['last_id']) : 0;

$stmt = $pdo->prepare("SELECT * FROM notifications WHERE user_id = ? AND id > ? AND is_read = FALSE ORDER BY created_at ASC");
$stmt->execute([$user_id, $last_id]);
$notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (!empty($notifications)) {
    $last_id = $notifications[count($notifications) - 1]['id'];
    
    // Mark notifications as read
    $stmt = $pdo->prepare("UPDATE notifications SET is_read = TRUE WHERE user_id = ? AND id <= ?");
    $stmt->execute([$user_id, $last_id]);
}

echo json_encode([
    'notifications' => $notifications,
    'last_id' => $last_id
]);
?>
