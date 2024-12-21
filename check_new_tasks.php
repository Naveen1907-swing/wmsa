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

$labor_id = $_SESSION['labor_id'] ?? 0;

$sql = "SELECT COUNT(*) as count FROM tasks WHERE labor_id = :labor_id AND notified = 0";
$stmt = $pdo->prepare($sql);
$stmt->execute([':labor_id' => $labor_id]);
$result = $stmt->fetch(PDO::FETCH_ASSOC);

if ($result['count'] > 0) {
    // Mark tasks as notified
    $update_sql = "UPDATE tasks SET notified = 1 WHERE labor_id = :labor_id AND notified = 0";
    $update_stmt = $pdo->prepare($update_sql);
    $update_stmt->execute([':labor_id' => $labor_id]);

    echo json_encode(['new_tasks' => $result['count']]);
} else {
    echo json_encode(['new_tasks' => 0]);
}
