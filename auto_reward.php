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

function calculateReward($waste_type, $volume) {
    // Base points per cubic meter
    $points_per_m3 = [
        'Plastic' => 20,
        'Paper' => 15,
        'Glass' => 25,
        'Metal' => 30,
        'E-waste' => 40,
        'Organic' => 10,
        'Hazardous' => 50,
        'Textile' => 15,
        'Construction' => 20,
        'Other' => 15,
        'Paper, Cardboard' => 15
    ];

    // Get base points for waste type
    $base_points = isset($points_per_m3[$waste_type]) ? $points_per_m3[$waste_type] : 15;
    
    // Calculate points based on volume
    $points = round($base_points * $volume);
    
    // Cap maximum points at 100
    return min($points, 100);
}

// Fetch pending waste entries
$sql = "SELECT id, waste_type, ai_analysis FROM waste WHERE status = 'pending' AND ai_analysis IS NOT NULL";
$stmt = $pdo->query($sql);
$pending_waste = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($pending_waste as $waste) {
    // Extract volume from AI analysis
    $volume = 0;
    if (preg_match('/Estimated Volume: ([\d.]+) m³/', $waste['ai_analysis'], $matches)) {
        $volume = floatval($matches[1]);
    }
    
    // Calculate reward points
    $reward_points = calculateReward($waste['waste_type'], $volume);
    
    // Update waste record with reward points and status
    $update_sql = "UPDATE waste SET status = 'approved', reward_points = :reward_points WHERE id = :waste_id";
    $update_stmt = $pdo->prepare($update_sql);
    $update_stmt->execute([
        ':reward_points' => $reward_points,
        ':waste_id' => $waste['id']
    ]);
    
    // Add notification for the user
    $notify_sql = "INSERT INTO notifications (user_id, notification_type, waste_type, message, is_read) 
                   SELECT user_id, 'reward_assigned', waste_type, 
                          CONCAT('You received ', :reward_points, ' points for your ', waste_type, ' waste submission.'),
                          0
                   FROM waste WHERE id = :waste_id";
    $notify_stmt = $pdo->prepare($notify_sql);
    $notify_stmt->execute([
        ':reward_points' => $reward_points,
        ':waste_id' => $waste['id']
    ]);
}

// Set up a cron job to run this script automatically
// Add to crontab:
// */5 * * * * php /path/to/your/auto_reward.php
?> 