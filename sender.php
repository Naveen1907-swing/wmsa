<?php
$host = 'localhost';
$dbname = 'test';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $receiver_id = $_POST['receiver_id'];
    $message = $_POST['message'];
    
    if (!empty($receiver_id) && !empty($message)) {
        $stmt = $pdo->prepare("INSERT INTO notifications (user_id, message) VALUES (?, ?)");
        if ($stmt->execute([$receiver_id, $message])) {
            $success_message = "Notification sent successfully!";
        } else {
            $error_message = "Failed to send notification. Please try again.";
        }
    } else {
        $error_message = "Please fill in all fields.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Send Notification</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.1.1/css/all.min.css">
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            margin: 0;
            padding: 20px;
            background-color: #f4f4f4;
        }
        .container {
            max-width: 600px;
            margin: 0 auto;
            background-color: #fff;
            padding: 20px;
            border-radius: 5px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }
        h1 {
            text-align: center;
            color: #333;
        }
        form {
            display: flex;
            flex-direction: column;
        }
        label {
            margin-bottom: 5px;
            color: #666;
        }
        input, textarea {
            padding: 10px;
            margin-bottom: 15px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 16px;
        }
        textarea {
            height: 100px;
            resize: vertical;
        }
        button {
            padding: 10px;
            background-color: #4CAF50;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
            transition: background-color 0.3s;
        }
        button:hover {
            background-color: #45a049;
        }
        .success {
            color: #4CAF50;
            text-align: center;
            margin-bottom: 15px;
        }
        .error {
            color: #f44336;
            text-align: center;
            margin-bottom: 15px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1><i class="fas fa-bell"></i> Send Notification</h1>
        <?php if ($success_message): ?>
            <p class="success"><i class="fas fa-check-circle"></i> <?php echo $success_message; ?></p>
        <?php endif; ?>
        <?php if ($error_message): ?>
            <p class="error"><i class="fas fa-exclamation-circle"></i> <?php echo $error_message; ?></p>
        <?php endif; ?>
        <form method="POST">
            <label for="receiver_id"><i class="fas fa-user"></i> Receiver ID:</label>
            <input type="number" id="receiver_id" name="receiver_id" required>
            
            <label for="message"><i class="fas fa-envelope"></i> Message:</label>
            <textarea id="message" name="message" required></textarea>
            
            <button type="submit"><i class="fas fa-paper-plane"></i> Send Notification</button>
        </form>
    </div>
</body>
</html>
