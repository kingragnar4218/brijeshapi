<?php
// Enable error reporting for debugging (remove in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Check for auth_token
if (isset($_GET['auth_token'])) {
    $auth_token = $_GET['auth_token'];
    
    // Store token in file
    $token_file = 'token.txt';
    if (file_put_contents($token_file, $auth_token) === false) {
        die('Error: Failed to save auth_token.');
    }
    
    // Redirect to index.php to handle LTP fetching
    header("Location: https://brijesh.free.nf/index.php");
    exit;
} else {
    $output = "Error: No auth_token received. Please ensure the redirect URL is registered.";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reliance Share Price Demo - Callbackwww</title>
    <style>
        body { font-family: Arial, sans-serif; text-align: center; padding: 50px; }
        .error { font-size: 18px; color: red; }
    </style>
</head>
<body>
    <h2>Reliance Industries Share Price</h2>
    <p class="error"><?php echo htmlspecialchars($output); ?></p>
    <p><a href="index.php">Back to Login</a></p>
</body>
</html>
