<?php

// Database configuration
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "codewars";

try {
    // Create a new PDO connection
    $conn = new PDO("mysql:host=$servername;dbname=$dbname", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Check if a short code is provided for redirection
    if (isset($_GET['code'])) {
        $shortened_url = $_GET['code'];

        // Find the original URL from the database
        $sql = "SELECT original_url, click_count FROM shortener WHERE shortened_url = :shortened_url";
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':shortened_url', $shortened_url);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($result) {
            // Update click count
            $new_click_count = $result['click_count'] + 1;
            $update_stmt = $conn->prepare("UPDATE shortener SET click_count = :click_count WHERE shortened_url = :shortened_url");
            $update_stmt->bindParam(':click_count', $new_click_count);
            $update_stmt->bindParam(':shortened_url', $shortened_url);
            $update_stmt->execute();

            // Redirect to the original URL
            header("Location: " . $result['original_url']);
        } else {
            echo "Shortened URL not found!";
        }
        exit;
    }

    // Check if the form is submitted for URL shortening
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['original_url'])) {
        $original_url = $_POST['original_url'];
        $shortened_url = generateShortCode(); // Generate a unique short code

        // Insert the URL into the database
        $stmt = $conn->prepare("INSERT INTO shortener (original_url, shortened_url, created_at, click_count) 
                                VALUES (:original_url, :shortened_url, NOW(), 0)");
        $stmt->bindParam(':original_url', $original_url);
        $stmt->bindParam(':shortened_url', $shortened_url);
        $stmt->execute();

        echo "Shortened URL: <a href='?code=$shortened_url'>$shortened_url</a>";
    }

} catch (PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}



// Function to generate a short code
function generateShortCode($length = 6) {
    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $charactersLength = strlen($characters);
    $shortCode = '';
    for ($i = 0; $i < $length; $i++) {
        $shortCode .= $characters[rand(0, $charactersLength - 1)];
    }
    return $shortCode;
}

?>

<!DOCTYPE html>
<html>
<head>
    <title>URL Shortener</title>
</head>
<body>
<h2>URL Shortener</h2>
<form method="post" action="">
    <label for="original_url">Enter URL to Shorten:</label><br>
    <input type="url" name="original_url" required><br><br>
    <input type="submit" value="Shorten URL">
</form>
</body>
</html>
