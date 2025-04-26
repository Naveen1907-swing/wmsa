<?php
session_start();
header('Content-Type: application/json');
header('Cache-Control: no-cache');

if (!isset($_GET['user_id'])) {
    die(json_encode(['error' => 'Missing user_id parameter']));
}

$host = 'localhost';
$dbname = 'test';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // For admin (user_id = 0), get all unread notifications
    if ($_GET['user_id'] == '0') {
        $sql = "SELECT * FROM notifications 
                WHERE user_id = 0 AND is_read = FALSE 
                ORDER BY created_at DESC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
    } 
    // For labor/user, get notifications since last_id
    else {
        $last_id = $_GET['last_id'] ?? 0;
        $sql = "SELECT * FROM notifications 
                WHERE user_id = :user_id 
                AND id > :last_id 
                AND is_read = FALSE 
                ORDER BY created_at DESC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':user_id' => $_GET['user_id'],
            ':last_id' => $last_id
        ]);
    }

    $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Mark notifications as read
    if (!empty($notifications)) {
        $update_sql = "UPDATE notifications SET is_read = TRUE 
                      WHERE id IN (" . implode(',', array_column($notifications, 'id')) . ")";
        $pdo->exec($update_sql);
    }

    echo json_encode([
        'success' => true,
        'notifications' => $notifications
    ]);

} catch(PDOException $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
?>
