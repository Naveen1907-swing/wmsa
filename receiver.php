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

// For this example, we'll assume the receiver's user_id is 1
$user_id = 1;

// Fetch unread notifications
$stmt = $pdo->prepare("SELECT * FROM notifications WHERE user_id = ? AND is_read = FALSE ORDER BY created_at DESC");
$stmt->execute([$user_id]);
$notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifications</title>
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
            max-width: 800px;
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
        #notifications {
            list-style-type: none;
            padding: 0;
        }
        #notifications li {
            background-color: #e9f5ff;
            margin-bottom: 10px;
            padding: 10px;
            border-radius: 5px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }
        #notifications li .time {
            font-size: 0.8em;
            color: #666;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1><i class="fas fa-bell"></i> Notifications</h1>
        <ul id="notifications">
            <?php foreach ($notifications as $notification): ?>
                <li>
                    <i class="fas fa-envelope"></i>
                    <?php echo htmlspecialchars($notification['message']); ?>
                    <span class="time">(<?php echo $notification['created_at']; ?>)</span>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        // Request permission for desktop notifications
        if (Notification.permission !== "granted") {
            Notification.requestPermission();
        }

        // Function to add a new notification to the list
        function addNotification(message, created_at) {
            const li = $('<li>').html(`<i class="fas fa-envelope"></i> ${message} <span class="time">(${created_at})</span>`);
            $('#notifications').prepend(li);

            // Show desktop notification
            if (Notification.permission === "granted") {
                new Notification("New Notification", { body: message });
            }
        }

        let lastId = <?php echo empty($notifications) ? 0 : $notifications[0]['id']; ?>;

        function checkNotifications() {
            $.ajax({
                url: 'get_notifications.php',
                method: 'GET',
                data: { last_id: lastId },
                dataType: 'json',
                success: function(data) {
                    if (data.notifications.length > 0) {
                        data.notifications.forEach(function(notification) {
                            addNotification(notification.message, notification.created_at);
                        });
                        lastId = data.notifications[data.notifications.length - 1].id;
                    }
                },
                complete: function() {
                    // Immediately check for new notifications again
                    checkNotifications();
                }
            });
        }

        // Start checking for notifications
        checkNotifications();
    </script>
</body>
</html>
