<?php
require_once 'vendor/autoload.php'; // Include Google API Client Library for PHP autoload file

$client = new Google_Client(['client_id' => '176136185388-j7rvbt5qg5jqn9imnje2nl7u6d9a5etm.apps.googleusercontent.com']);  // Specify the CLIENT_ID of the app that accesses the backend
$id_token = $_POST['id_token'];

try {
    $payload = $client->verifyIdToken($id_token);
    if ($payload) {
        $userid = $payload['sub'];
        // If request specified a G Suite domain:
        //$domain = $payload['hd'];

        // Here you can create a session or store user information in your database
        echo json_encode(['success' => true]);
    } else {
        // Invalid ID token
        echo json_encode(['success' => false]);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>

