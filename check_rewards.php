<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    die(json_encode(['error' => 'Not logged in']));
}

$host = 'localhost';
$dbname = 'test';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Check for new rewards and notifications
    $sql = "SELECT w.reward_points, w.waste_type, n.message 
            FROM waste w 
            LEFT JOIN notifications n ON w.user_id = n.user_id 
            WHERE w.user_id = :user_id 
            AND w.status = 'approved' 
            AND (n.is_read = 0 OR n.is_read IS NULL)";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':user_id' => $_SESSION['user_id']]);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'data' => $results]);
} catch(PDOException $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
?> 