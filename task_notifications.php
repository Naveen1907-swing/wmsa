<?php
header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('Connection: keep-alive');

session_start();
$labor_id = $_SESSION['labor_id'] ?? 0;

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

while (true) {
    $sql = "SELECT COUNT(*) as count FROM tasks WHERE labor_id = :labor_id AND notified = 0";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':labor_id' => $labor_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($result['count'] > 0) {
        echo "data: " . json_encode(['new_tasks' => $result['count']]) . "\n\n";
        
        // Mark tasks as notified
        $update_sql = "UPDATE tasks SET notified = 1 WHERE labor_id = :labor_id AND notified = 0";
        $update_stmt = $pdo->prepare($update_sql);
        $update_stmt->execute([':labor_id' => $labor_id]);
    }

    ob_flush();
    flush();

    sleep(5); // Check every 5 seconds
}
