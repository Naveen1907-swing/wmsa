<?php
session_start();

// Check if the user is logged in
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || !isset($_SESSION['user_id'])) {
    header("location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];

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

// Fetch user's total approved reward points
$sql = "SELECT SUM(reward_points) as total_points 
        FROM waste 
        WHERE user_id = :user_id AND status = 'approved'";
$stmt = $pdo->prepare($sql);
$stmt->execute([':user_id' => $user_id]);
$result = $stmt->fetch(PDO::FETCH_ASSOC);
$total_points = $result['total_points'] ?? 0;


$message = '';

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $points_to_redeem = intval($_POST['points_to_redeem']);

    if ($points_to_redeem < 30) {
        $message = "You must redeem at least 30 points.";
    } elseif ($points_to_redeem > $total_points) {
        $message = "You don't have enough points to redeem.";
    } else {
        // Calculate the amount in rupees
        $amount_in_rupees = $points_to_redeem / 10;

        // Create Cashfree order
        $order_id = 'ORDER_' . time() . '_' . $user_id;
        $customer_id = 'CUST_' . $user_id;
        $order_data = [
            'order_id' => $order_id,
            'order_amount' => $amount_in_rupees,
            'order_currency' => 'INR',
            'customer_details' => [
                'customer_id' => $customer_id,
                'customer_email' => $_SESSION['email'],
                'customer_phone' => $_SESSION['phone']
            ],
            'order_meta' => [
                'return_url' => 'https://yourwebsite.com/redeem_callback.php?order_id={order_id}'
            ]
        ];

        $curl = curl_init($cashfree_url);
        curl_setopt($curl, CURLOPT_POST, 1);
        curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($order_data));
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'x-client-id: ' . $cashfree_client,
            'x-client-secret: ' . $cashfree_secret
        ]);

        $response = curl_exec($curl);
        $err = curl_error($curl);
        curl_close($curl);

        if ($err) {
            $message = "Error creating payment order. Please try again later.";
        } else {
            $response_data = json_decode($response, true);
            if (isset($response_data['payment_link'])) {
                // Redirect to Cashfree payment page
                header("Location: " . $response_data['payment_link']);
                exit;
            } else {
                $message = "Error creating payment order. Please try again later.";
            }
        }
    }
}

// Fetch user's redemption history
$sql = "SELECT * FROM redemptions WHERE user_id = :user_id ORDER BY created_at DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute([':user_id' => $user_id]);
$redemption_history = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Redeem Rewards - WasteWise</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css" rel="stylesheet">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap');
        body {
            font-family: 'Poppins', sans-serif;
        }
    </style>
</head>
<body class="bg-gray-100">
    <div class="min-h-screen flex flex-col">
        <nav class="bg-green-600 shadow-lg">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex justify-between h-16">
                    <div class="flex items-center">
                        <a href="user.php" class="flex items-center">
                            <i class="fas fa-recycle text-white text-2xl mr-2"></i>
                            <span class="font-bold text-white text-lg">WasteWise</span>
                        </a>
                    </div>
                    <div class="flex items-center">
                        <span class="text-white mr-4">
                            <i class="fas fa-coins mr-2"></i><?php echo $total_points; ?> Points
                        </span>
                        <a href="user.php" class="bg-white text-green-600 hover:bg-green-100 px-3 py-2 rounded-md text-sm font-medium transition duration-300">Dashboard</a>
                        <a href="logout.php" class="ml-4 text-white hover:bg-green-700 px-3 py-2 rounded-md text-sm font-medium transition duration-300">Logout</a>
                    </div>
                </div>
            </div>
        </nav>

        <main class="flex-grow container mx-auto px-4 sm:px-6 lg:px-8 py-8">
            <h1 class="text-3xl font-bold text-gray-800 mb-8">Redeem Your Rewards</h1>
            
            <?php if ($message): ?>
                <div class="bg-blue-100 border-l-4 border-blue-500 text-blue-700 p-4 mb-6" role="alert">
                    <p class="font-bold">Information</p>
                    <p><?php echo htmlspecialchars($message); ?></p>
                </div>
            <?php endif; ?>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                <div class="bg-white shadow-md rounded-lg p-6">
                    <h2 class="text-2xl font-semibold mb-6 text-gray-800">Redeem Points</h2>
                    <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="POST" class="space-y-4">
                        <div>
                            <label for="points_to_redeem" class="block text-sm font-medium text-gray-700">Points to Redeem</label>
                            <div class="mt-1 relative rounded-md shadow-sm">
                                <input type="number" id="points_to_redeem" name="points_to_redeem" min="100" max="<?php echo $total_points; ?>" required 
                                    class="focus:ring-green-500 focus:border-green-500 block w-full pl-7 pr-12 sm:text-sm border-gray-300 rounded-md"
                                    placeholder="Enter points">
                                <div class="absolute inset-y-0 right-0 flex items-center">
                                    <label for="currency" class="sr-only">Currency</label>
                                    <select id="currency" name="currency" class="focus:ring-green-500 focus:border-green-500 h-full py-0 pl-2 pr-7 border-transparent bg-transparent text-gray-500 sm:text-sm rounded-r-md">
                                        <option>Points</option>
                                    </select>
                                </div>
                            </div>
                            <p class="mt-2 text-sm text-gray-500">Minimum: 100 points (₹10)</p>
                        </div>
                        <div class="pt-4">
                            <button type="submit" 
                                class="w-full flex justify-center py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 transition duration-300">
                                Redeem Points
                            </button>
                        </div>
                    </form>
                </div>
                <div class="bg-white shadow-md rounded-lg p-6">
                    <h2 class="text-2xl font-semibold mb-6 text-gray-800">Redemption History</h2>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Points</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Amount</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php foreach ($redemption_history as $redemption): ?>
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo htmlspecialchars($redemption['created_at']); ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo htmlspecialchars($redemption['points_redeemed']); ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">₹<?php echo htmlspecialchars($redemption['amount']); ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?php echo $redemption['status'] === 'completed' ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800'; ?>">
                                            <?php echo htmlspecialchars($redemption['status']); ?>
                                        </span>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </main>

        <footer class="bg-gray-800 text-white py-4 mt-8">
            <div class="container mx-auto px-4 text-center">
                <p>&copy; 2023 WasteWise. All rights reserved.</p>
            </div>
        </footer>
    </div>

    <script>
        // Add any necessary JavaScript here
    </script>
</body>
</html>
