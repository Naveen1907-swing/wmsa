<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    die(json_encode(['error' => 'Not logged in']));
}

// Database connection
$host = 'localhost';
$dbname = 'test';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $sql = "SELECT w.id, w.created_at, w.status, w.reward_points 
            FROM waste w 
            WHERE w.user_id = :user_id 
            ORDER BY w.created_at DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':user_id' => $_SESSION['user_id']]);
    $rewards = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Calculate total points
    $total_points = array_sum(array_column($rewards, 'reward_points'));

    $html = "<div class='mb-4'><strong>Total Points:</strong> {$total_points}</div>";
    $html .= "<div class='space-y-2'>";
    foreach ($rewards as $reward) {
        $html .= "<div class='bg-white p-4 rounded shadow'>";
        $html .= "<div>Date: " . date('Y-m-d H:i', strtotime($reward['created_at'])) . "</div>";
        $html .= "<div>Status: " . ucfirst($reward['status']) . "</div>";
        $html .= "<div>Points: " . $reward['reward_points'] . "</div>";
        $html .= "</div>";
    }
    $html .= "</div>";

    echo json_encode(['success' => true, 'rewards' => $html]);
} catch(PDOException $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
?> 