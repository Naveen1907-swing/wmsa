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
    die(json_encode(['success' => false, 'message' => "Connection failed: " . $e->getMessage()]));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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

        // Insert notification
        $notification_sql = "INSERT INTO notifications (user_id, notification_type, task_id, waste_type, location, latitude, longitude, reporter, message, is_read) 
                             VALUES (:labor_id, 'task_assigned', :task_id, :waste_type, :location, :latitude, :longitude, :reporter, :message, FALSE)";
        $notification_stmt = $pdo->prepare($notification_sql);
        $notification_stmt->execute([
            ':labor_id' => $labor_id,
            ':task_id' => $task_id,
            ':waste_type' => $waste_details['waste_type'],
            ':location' => $waste_details['location'],
            ':latitude' => $waste_details['latitude'],
            ':longitude' => $waste_details['longitude'],
            ':reporter' => $waste_details['reporter_name'],
            ':message' => "New task assigned: {$waste_details['waste_type']} waste at {$waste_details['location']}"
        ]);

        $pdo->commit();
        echo json_encode(['success' => true]);
    } catch (PDOException $e) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}
?>
