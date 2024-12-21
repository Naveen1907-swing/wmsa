<?php
session_start();
require_once 'vendor/autoload.php'; // Adjust the path as necessary

// Database connection
$host = 'localhost';
$dbname = 'test';
$username = 'root';  // Replace with your database username
$password = '';      // Replace with your database password

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("ERROR: Could not connect. " . $e->getMessage());
}

$error = '';
$success = '';


$redirectUri = 'http://localhost:3000/Desktop/hackare/register.php'; // Update this to your actual redirect URI

$client = new Google_Client();
$client->setClientId($clientID);
$client->setClientSecret($clientSecret);
$client->setRedirectUri($redirectUri);
$client->addScope("email");
$client->addScope("profile");

// Handle Google OAuth response
if (isset($_GET['code'])) {
    $token = $client->fetchAccessTokenWithAuthCode($_GET['code']);
    $client->setAccessToken($token['access_token']);

    $google_oauth = new Google_Service_Oauth2($client);
    $google_account_info = $google_oauth->userinfo->get();
    $email = $google_account_info->email;
    $name = $google_account_info->name;
    $google_id = $google_account_info->id;

    // Check if the user exists in your database
    $sql = "SELECT id FROM users WHERE google_id = :google_id OR email = :email";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':google_id', $google_id, PDO::PARAM_STR);
    $stmt->bindParam(':email', $email, PDO::PARAM_STR);
    $stmt->execute();

    if ($stmt->rowCount() == 0) {
        // User does not exist, create a new user
        $sql = "INSERT INTO users (username, email, google_id) VALUES (:username, :email, :google_id)";
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':username', $name, PDO::PARAM_STR);
        $stmt->bindParam(':email', $email, PDO::PARAM_STR);
        $stmt->bindParam(':google_id', $google_id, PDO::PARAM_STR);
        $stmt->execute();
        $user_id = $pdo->lastInsertId();
        $success = "Registration successful! You can now login.";
    } else {
        $error = "User already exists. Please login.";
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = $_POST['name'];
    $email = $_POST['email'];
    $password = $_POST['password'];

    if (empty($username) || empty($email) || empty($password)) {
        $error = "Please fill in all fields.";
    } else {
        $sql = "INSERT INTO users (username, email, password) VALUES (:username, :email, :password)";
        
        if($stmt = $pdo->prepare($sql)){
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            
            $stmt->bindParam(":username", $username, PDO::PARAM_STR);
            $stmt->bindParam(":email", $email, PDO::PARAM_STR);
            $stmt->bindParam(":password", $hashed_password, PDO::PARAM_STR);
            
            if($stmt->execute()){
                $success = "Registration successful! You can now login.";
            } else{
                $error = "Something went wrong. Please try again later.";
            }

            unset($stmt);
        }
    }
    
    unset($pdo);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://accounts.google.com/gsi/client" async defer></script>
</head>
<body class="bg-gray-100">
    <div class="min-h-screen flex items-center justify-center">
        <div class="max-w-md w-full space-y-8 p-10 bg-white rounded-xl shadow-lg z-10">
            <div class="text-center">
                <h2 class="mt-6 text-3xl font-bold text-gray-900">
                    Create an account
                </h2>
                <p class="mt-2 text-sm text-gray-600">Sign up to get started</p>
            </div>
            <?php if ($error): ?>
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative" role="alert">
                    <span class="block sm:inline"><?php echo $error; ?></span>
                </div>
            <?php endif; ?>
            <?php if ($success): ?>
                <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative" role="alert">
                    <span class="block sm:inline"><?php echo $success; ?></span>
                </div>
            <?php endif; ?>
            <form class="mt-8 space-y-6" action="" method="POST">
                <div class="rounded-md shadow-sm -space-y-px">
                    <div>
                        <label for="name" class="sr-only">Full Name</label>
                        <input id="name" name="name" type="text" required class="appearance-none rounded-none relative block w-full px-3 py-2 border border-gray-300 placeholder-gray-500 text-gray-900 rounded-t-md focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 focus:z-10 sm:text-sm" placeholder="Full Name">
                    </div>
                    <div>
                        <label for="email-address" class="sr-only">Email address</label>
                        <input id="email-address" name="email" type="email" autocomplete="email" required class="appearance-none rounded-none relative block w-full px-3 py-2 border border-gray-300 placeholder-gray-500 text-gray-900 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 focus:z-10 sm:text-sm" placeholder="Email address">
                    </div>
                    <div>
                        <label for="password" class="sr-only">Password</label>
                        <input id="password" name="password" type="password" autocomplete="new-password" required class="appearance-none rounded-none relative block w-full px-3 py-2 border border-gray-300 placeholder-gray-500 text-gray-900 rounded-b-md focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 focus:z-10 sm:text-sm" placeholder="Password">
                    </div>
                </div>

                <div>
                    <button type="submit" class="group relative w-full flex justify-center py-2 px-4 border border-transparent text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                        Register
                    </button>
                </div>
            </form>
            <div class="mt-6">
                <a href="<?php echo $client->createAuthUrl() ?>" class="group relative w-full flex justify-center py-2 px-4 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                    <span class="absolute left-0 inset-y-0 flex items-center pl-3">
                        <img src="https://www.google.com/favicon.ico" alt="Google" class="h-5 w-5">
                    </span>
                    Sign up with Google
                </a>
            </div>
            <div class="text-sm text-center mt-6">
                <a href="login.php" class="font-medium text-indigo-600 hover:text-indigo-500">
                    Already have an account? Login
                </a>
            </div>
        </div>
    </div>
</body>
</html>
