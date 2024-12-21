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
    'Mumbai', 'Delhi', 'Bangalore', 'Hyderabad', 'Chennai', 
    'Kolkata', 'Pune', 'Ahmedabad', 'Jaipur', 'Surat'
];

$waste_types = [
    'Organic', 'Plastic', 'Paper', 'Glass', 'Metal', 
    'E-waste', 'Hazardous', 'Textile', 'Construction', 'Other'
];

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (!isset($_FILES["waste_image"]) || $_FILES["waste_image"]["error"] != 0) {
        $message = "Error uploading file. Please try again.";
    } elseif (!isset($_POST['location']) || empty($_POST['location'])) {
        $message = "Location is required.";
    } elseif ($_POST['location'] === 'other' && (!isset($_POST['other_location']) || empty($_POST['other_location']))) {
        $message = "Please specify the other location.";
    } elseif (!isset($_POST['waste_type']) || empty($_POST['waste_type'])) {
        $message = "Waste type is required.";
    } else {
        $target_dir = "uploads/";
        if (!file_exists($target_dir)) {
            mkdir($target_dir, 0777, true);
        }
        $target_file = $target_dir . basename($_FILES["waste_image"]["name"]);
        $uploadOk = 1;
        $imageFileType = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));

        // Check if image file is an actual image or fake image
        $check = getimagesize($_FILES["waste_image"]["tmp_name"]);
        if($check === false) {
            $message = "File is not an image.";
            $uploadOk = 0;
        }

        // Check file size
        if ($_FILES["waste_image"]["size"] > 500000) {
            $message = "Sorry, your file is too large.";
            $uploadOk = 0;
        }

        // Allow certain file formats
        if($imageFileType != "jpg" && $imageFileType != "png" && $imageFileType != "jpeg"
        && $imageFileType != "gif" ) {
            $message = "Sorry, only JPG, JPEG, PNG & GIF files are allowed.";
            $uploadOk = 0;
        }

        // If everything is ok, try to upload file
        if ($uploadOk == 1) {
            if (move_uploaded_file($_FILES["waste_image"]["tmp_name"], $target_file)) {
                $message = "The file ". basename( $_FILES["waste_image"]["name"]). " has been uploaded.";
                
                // Insert data into the database
                $location = ($_POST['location'] === 'other') ? $_POST['other_location'] : $_POST['location'];
                $waste_type = $_POST['waste_type'];
                $sql = "INSERT INTO waste (user_id, image_path, location, waste_type) VALUES (:user_id, :image_path, :location, :waste_type)";
                $stmt = $pdo->prepare($sql);
                try {
                    $stmt->execute([
                        ':user_id' => $user_id,
                        ':image_path' => $target_file,
                        ':location' => $location,
                        ':waste_type' => $waste_type
                    ]);
                    $message .= " Waste information has been recorded.";
                    
                    // Redirect after successful submission
                    $_SESSION['message'] = $message;
                    header("Location: " . $_SERVER['PHP_SELF']);
                    exit;
                } catch (PDOException $e) {
                    $message = "Database Error: " . $e->getMessage();
                    error_log("Database Error in user.php: " . $e->getMessage());
                }
            } else {
                $message = "Sorry, there was an error uploading your file.";
            }
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
                                <label for="waste_type" class="block text-sm font-medium text-gray-700">Waste Type</label>
                                <select id="waste_type" name="waste_type" required class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-green-500 focus:border-green-500 sm:text-sm rounded-md">
                                    <option value="">Select waste type</option>
                                    <?php foreach ($waste_types as $type): ?>
                                        <option value="<?php echo htmlspecialchars($type); ?>"><?php echo htmlspecialchars($type); ?></option>
                                    <?php endforeach; ?>
                                </select>
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
