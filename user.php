<?php
ob_start(); // Start output buffering
session_start();

// Check if the user is logged in and has a valid user_id
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

// Debug: Check if the user exists in the database
$check_user_sql = "SELECT id FROM users WHERE id = :user_id";
$check_user_stmt = $pdo->prepare($check_user_sql);
$check_user_stmt->execute([':user_id' => $user_id]);
$user_exists = $check_user_stmt->fetch();

if (!$user_exists) {
    die("Error: User with ID {$user_id} does not exist in the database.");
}

$message = '';

// Define popular cities and waste types
$popular_cities = [
    'Block 1', 'Block 2', 'Block 3', 'Block 4', 'Block 5', 
    'Block 6', 'Block 7', 'Block 8', 'Block 9', 'Block 10'
];

$waste_types = [
    'Organic', 'Plastic', 'Paper', 'Glass', 'Metal', 
    'E-waste', 'Hazardous', 'Textile', 'Construction', 'Other'
];

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (!isset($_FILES["waste_image"]) || $_FILES["waste_image"]["error"] != 0) {
        $_SESSION['message'] = "Error uploading file. Please try again.";
        header("Location: " . $_SERVER['PHP_SELF']);
        exit;
    } elseif (!isset($_POST['location']) || empty($_POST['location'])) {
        $_SESSION['message'] = "Location is required.";
        header("Location: " . $_SERVER['PHP_SELF']);
        exit;
    } elseif ($_POST['location'] === 'other' && (!isset($_POST['other_location']) || empty($_POST['other_location']))) {
        $_SESSION['message'] = "Please specify the other location.";
        header("Location: " . $_SERVER['PHP_SELF']);
        exit;
    } else {
        $target_dir = "uploads/";
        if (!file_exists($target_dir)) {
            mkdir($target_dir, 0777, true);
        }
        
        // Create a temporary file path
        $temp_file = $target_dir . "temp_" . basename($_FILES["waste_image"]["name"]);
        $uploadOk = 1;
        $imageFileType = strtolower(pathinfo($temp_file, PATHINFO_EXTENSION));

        // Check if image file is an actual image
        $check = getimagesize($_FILES["waste_image"]["tmp_name"]);
        if($check === false) {
            $message = "File is not an image.";
            $uploadOk = 0;
        }

        if ($uploadOk == 1 && move_uploaded_file($_FILES["waste_image"]["tmp_name"], $temp_file)) {
            // Convert image to base64
            $imageData = base64_encode(file_get_contents($temp_file));
            $API_KEY = 'AIzaSyDRpJORsoLZMRG60l_68TEzH5b3jd6DGZ4';
            
            // First API call to validate if it's waste
            $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent?key=" . $API_KEY;
            $validation_data = [
                'contents' => [[
                    'parts' => [
                        [
                            'text' => "Is this image showing waste or garbage? Respond with only 'YES' or 'NO'."
                        ],
                        [
                            'inlineData' => [
                                'mimeType' => $_FILES["waste_image"]["type"],
                                'data' => $imageData
                            ]
                        ]
                    ]
                ]],
                'generationConfig' => [
                    'temperature' => 0.4,
                    'topK' => 32,
                    'topP' => 1,
                    'maxOutputTokens' => 2048,
                ]
            ];

            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($validation_data));
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);

            $response = curl_exec($ch);
            curl_close($ch);

            $result = json_decode($response, true);
            
            if (isset($result['candidates'][0]['content']['parts'][0]['text'])) {
                $validation_response = strtoupper(trim($result['candidates'][0]['content']['parts'][0]['text']));
                
                if ($validation_response === 'YES') {
                    // Second API call for detailed analysis
                    $analysis_data = [
                        'contents' => [[
                            'parts' => [
                                [
                                    'text' => "Please analyze this waste image and provide ONLY these two details:
1. List all types of waste visible in the image (e.g., plastic, organic, metal)
2. Estimate the total volume in cubic meters (m³)

Format the response exactly like this example:
Waste Types: Plastic, Metal
Estimated Volume: 0.5 m³"
                                ],
                                [
                                    'inlineData' => [
                                        'mimeType' => $_FILES["waste_image"]["type"],
                                        'data' => $imageData
                                    ]
                                ]
                            ]
                        ]],
                        'generationConfig' => [
                            'temperature' => 0.4,
                            'topK' => 32,
                            'topP' => 1,
                            'maxOutputTokens' => 2048,
                        ]
                    ];

                    $ch = curl_init($url);
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch, CURLOPT_POST, true);
                    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($analysis_data));
                    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);

                    $analysis_response = curl_exec($ch);
                    curl_close($ch);

                    $analysis_result = json_decode($analysis_response, true);
                    
                    if (isset($analysis_result['candidates'][0]['content']['parts'][0]['text'])) {
                        $analysis = $analysis_result['candidates'][0]['content']['parts'][0]['text'];
                        
                        // Extract waste types
                        preg_match('/Waste Types: (.*?)(?:\n|$)/i', $analysis, $type_matches);
                        $waste_types = isset($type_matches[1]) ? trim($type_matches[1]) : 'Unspecified';
                        
                        // Extract volume
                        preg_match('/Estimated Volume: ([\d.]+)\s*m³/i', $analysis, $volume_matches);
                        $volume = isset($volume_matches[1]) ? trim($volume_matches[1]) : 'Unknown';
                        
                        // Move temp file to final destination
                        $target_file = $target_dir . basename($_FILES["waste_image"]["name"]);
                        rename($temp_file, $target_file);
                        
                        // Insert into database
                        $location = ($_POST['location'] === 'other') ? $_POST['other_location'] : $_POST['location'];
                        $sql = "INSERT INTO waste (user_id, image_path, location, waste_type, ai_analysis) 
                               VALUES (:user_id, :image_path, :location, :waste_type, :ai_analysis)";
                        $stmt = $pdo->prepare($sql);
                        
                        try {
                            $stmt->execute([
                                ':user_id' => $user_id,
                                ':image_path' => $target_file,
                                ':location' => $location,
                                ':waste_type' => $waste_types,
                                ':ai_analysis' => $analysis
                            ]);
                            $_SESSION['message'] = "Analysis Complete:\n" . $analysis;
                            header("Location: " . $_SERVER['PHP_SELF']);
                            exit;
                        } catch (PDOException $e) {
                            $_SESSION['message'] = "Database Error: " . $e->getMessage();
                            error_log("Database Error in user.php: " . $e->getMessage());
                            header("Location: " . $_SERVER['PHP_SELF']);
                            exit;
                        }
                    } else {
                        $message = "Error analyzing the waste types and volume.";
                    }
                } else {
                    unlink($temp_file); // Delete the temporary file
                    $message = "The uploaded image does not appear to contain waste. Please upload an image of waste or garbage.";
                }
            } else {
                $message = "Error validating the image. Please try again.";
            }
        } else {
            $message = "Sorry, there was an error uploading your file.";
        }
    }
}

// Check for message in session
if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    unset($_SESSION['message']);
}

// Fetch user's rewards
$sql = "SELECT w.id, w.created_at, w.status, w.reward_points 
        FROM waste w 
        WHERE w.user_id = :user_id 
        ORDER BY w.created_at DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute([':user_id' => $user_id]);
$rewards = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate total approved reward points
$total_reward_points = array_reduce($rewards, function($carry, $item) {
    return $carry + ($item['status'] === 'approved' ? $item['reward_points'] : 0);
}, 0);

ob_end_clean(); // Discard any output that has been generated so far
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>WasteWise - User Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css" rel="stylesheet">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap');
        body {
            font-family: 'Poppins', sans-serif;
        }
    </style>
    <script>
    // Request notification permission when page loads
    document.addEventListener('DOMContentLoaded', function() {
        if (Notification.permission !== 'granted') {
            Notification.requestPermission();
        }
    });

    function showNotification(message) {
        if (Notification.permission === "granted") {
            const notification = new Notification("WasteWise Reward", {
                body: message,
                icon: '/path/to/your/logo.png' // Add your logo path here
            });
            
            // Close notification after 5 seconds
            setTimeout(() => {
                notification.close();
            }, 5000);
        }
    }

    function checkForRewards() {
        fetch('check_rewards.php')
            .then(response => response.json())
            .then(data => {
                if (data.success && data.data.length > 0) {
                    data.data.forEach(reward => {
                        showNotification(reward.message);
                        updateRewardsDisplay();
                    });
                }
            })
            .catch(error => console.error('Error:', error));
    }

    function updateRewardsDisplay() {
        fetch('get_rewards.php')
            .then(response => response.json())
            .then(data => {
                const rewardsContainer = document.getElementById('rewards-container');
                if (rewardsContainer && data.rewards) {
                    rewardsContainer.innerHTML = data.rewards;
                }
            });
    }

    // Check for rewards every 30 seconds
    setInterval(checkForRewards, 30000);

    // Initial check
    checkForRewards();
    </script>
</head>
<body class="bg-gray-100">
    <div class="min-h-screen flex flex-col">
        <nav class="bg-green-600 shadow-lg">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex justify-between h-16">
                    <div class="flex">
                        <div class="flex-shrink-0 flex items-center">
                            <i class="fas fa-recycle text-white text-2xl mr-2"></i>
                            <span class="font-bold text-white text-lg">WasteWise</span>
                        </div>
                    </div>
                    <div class="flex items-center">
                        <span class="text-white mr-4">
                            <i class="fas fa-coins mr-2"></i><?php echo $total_reward_points; ?> Points
                        </span>
                        <a href="redeem.php" class="bg-white text-green-600 hover:bg-green-100 px-3 py-2 rounded-md text-sm font-medium">Redeem</a>
                        <a href="login.php" class="ml-4 text-white hover:bg-green-700 px-3 py-2 rounded-md text-sm font-medium">Logout</a>
                    </div>
                </div>
            </div>
        </nav>

        <main class="flex-grow container mx-auto px-4 sm:px-6 lg:px-8 py-8">
            <div class="md:flex md:space-x-8">
                <div class="md:w-1/2">
                    <div class="bg-white shadow-md rounded-lg p-6 mb-8">
                        <h2 class="text-2xl font-bold mb-6 text-gray-800">Upload Waste Information</h2>
                        <?php if ($message): ?>
                            <div class="bg-blue-100 border-l-4 border-blue-500 text-blue-700 p-4 mb-6" role="alert">
                                <p><?php echo htmlspecialchars($message); ?></p>
                            </div>
                        <?php endif; ?>
                        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="POST" enctype="multipart/form-data" class="space-y-6">
                            <div>
                                <label for="location" class="block text-sm font-medium text-gray-700">Location</label>
                                <select id="location" name="location" required class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-green-500 focus:border-green-500 sm:text-sm rounded-md">
                                    <option value="">Select a city</option>
                                    <?php foreach ($popular_cities as $city): ?>
                                        <option value="<?php echo htmlspecialchars($city); ?>"><?php echo htmlspecialchars($city); ?></option>
                                    <?php endforeach; ?>
                                    <option value="other">Other</option>
                                </select>
                            </div>
                            <div id="other_location_div" style="display: none;">
                                <label for="other_location" class="block text-sm font-medium text-gray-700">Other Location</label>
                                <input id="other_location" name="other_location" type="text" class="mt-1 focus:ring-green-500 focus:border-green-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md" placeholder="Enter your city">
                            </div>
                            <div>
                                <label for="waste_image" class="block text-sm font-medium text-gray-700">Upload Waste Image</label>
                                <div class="mt-1 flex justify-center px-6 pt-5 pb-6 border-2 border-gray-300 border-dashed rounded-md">
                                    <div class="space-y-1 text-center">
                                        <svg class="mx-auto h-12 w-12 text-gray-400" stroke="currentColor" fill="none" viewBox="0 0 48 48" aria-hidden="true">
                                            <path d="M28 8H12a4 4 0 00-4 4v20m32-12v8m0 0v8a4 4 0 01-4 4H12a4 4 0 01-4-4v-4m32-4l-3.172-3.172a4 4 0 00-5.656 0L28 28M8 32l9.172-9.172a4 4 0 015.656 0L28 28m0 0l4 4m4-24h8m-4-4v8m-12 4h.02" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                                        </svg>
                                        <div class="flex text-sm text-gray-600">
                                            <label for="waste_image" class="relative cursor-pointer bg-white rounded-md font-medium text-green-600 hover:text-green-500 focus-within:outline-none focus-within:ring-2 focus-within:ring-offset-2 focus-within:ring-green-500">
                                                <span>Upload a file</span>
                                                <input id="waste_image" name="waste_image" type="file" accept="image/*" required class="sr-only">
                                            </label>
                                            <p class="pl-1">or drag and drop</p>
                                        </div>
                                        <p class="text-xs text-gray-500">PNG, JPG, GIF up to 10MB</p>
                                    </div>
                                </div>
                            </div>
                            <div>
                                <button type="submit" class="w-full flex justify-center py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500">
                                    Upload Waste Information
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
                <div class="md:w-1/2">
                    <div class="bg-white shadow-md rounded-lg p-6">
                        <h2 class="text-2xl font-bold mb-6 text-gray-800">Your Rewards</h2>
                        <p class="text-sm text-gray-600 mb-4">Total Approved Points: <span class="font-bold text-green-600"><?php echo $total_reward_points; ?></span></p>
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Points</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <?php foreach ($rewards as $reward): ?>
                                    <tr>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo htmlspecialchars($reward['created_at']); ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?php echo $reward['status'] === 'approved' ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800'; ?>">
                                                <?php echo htmlspecialchars($reward['status']); ?>
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo $reward['status'] === 'approved' ? htmlspecialchars($reward['reward_points']) : '-'; ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
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
        document.getElementById('location').addEventListener('change', function() {
            var otherLocationDiv = document.getElementById('other_location_div');
            if (this.value === 'other') {
                otherLocationDiv.style.display = 'block';
            } else {
                otherLocationDiv.style.display = 'none';
            }
        });

        // Preview uploaded image
        document.getElementById('waste_image').addEventListener('change', function(event) {
            var file = event.target.files[0];
            var reader = new FileReader();
            reader.onload = function(e) {
                var img = document.createElement('img');
                img.src = e.target.result;
                img.className = 'mt-2 rounded-md max-h-48 mx-auto';
                var container = document.querySelector('.space-y-1');
                container.appendChild(img);
            }
            reader.readAsDataURL(file);
        });
    </script>
</body>
</html>
