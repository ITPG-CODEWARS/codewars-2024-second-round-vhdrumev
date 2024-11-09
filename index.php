<?php
session_start(); // Start the session

// Set headers to prevent caching issues
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

// Database configuration
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "codewars";

$error_message = ""; // Variable to store error messages

try {
    // Create a new PDO connection
    $conn = new PDO("mysql:host=$servername;dbname=$dbname", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Check if a short code is provided for redirection
    if (isset($_GET['code'])) {
        $shortened_url = $_GET['code'];

        // Find the original URL, click count, max clicks, expiration date, and password from the database
        $sql = "SELECT id, original_url, click_count, max_clicks, password, expiration_date FROM shortener WHERE shortened_url = :shortened_url";
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':shortened_url', $shortened_url);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($result) {
            // Check if the link has expired
            if (!empty($result['expiration_date']) && new DateTime() > new DateTime($result['expiration_date'])) {
                echo "This shortened URL has expired!";
                exit;
            }

            // Check if the click count has reached the max clicks limit
            if (!empty($result['max_clicks']) && $result['click_count'] >= $result['max_clicks']) {
                echo "This shortened URL has reached its maximum click limit!";
                exit;
            }

            // Check if a password is set
            if (!empty($result['password'])) {
                if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['password'])) {
                    // Verify the password
                    if (password_verify($_POST['password'], $result['password'])) {
                        incrementClickCount($conn, $shortened_url, $result['click_count']);
                        header("Location: " . $result['original_url']);
                        exit;
                    } else {
                        $error_message = "Incorrect password!";
                    }
                } else {
                    // Show password prompt form
                    echo "<form method='post'>
                            <label for='password'>Enter Password:</label><br>
                            <input type='password' name='password' required><br><br>
                            <input type='submit' value='Submit'>
                          </form>";
                    exit;
                }
            } else {
                // No password set, proceed with redirection
                incrementClickCount($conn, $shortened_url, $result['click_count']);
                header("Location: " . $result['original_url']);
                exit;
            }
        } else {
            echo "Shortened URL not found!";
        }
        exit;
    }

    // Check if the form is submitted for URL shortening
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['original_url'])) {
        $original_url = $_POST['original_url'];
        $custom_code = trim($_POST['custom_code']);
        $password = !empty($_POST['password']) ? password_hash($_POST['password'], PASSWORD_DEFAULT) : null;
        $max_clicks = !empty($_POST['max_clicks']) ? intval($_POST['max_clicks']) : null;
        $expiration_date = !empty($_POST['expiration_date']) ? $_POST['expiration_date'] : null;

        // Validate and set short code length
        $short_code_length = isset($_POST['short_code_length']) ? intval($_POST['short_code_length']) : 6;
        if ($short_code_length < 1) {
            $short_code_length = 6;
        }

        if (!empty($custom_code)) {
            if (isShortCodeUnique($conn, $custom_code)) {
                $shortened_url = $custom_code;
            } else {
                $error_message = "The custom short code is already taken. Please choose a different one.";
            }
        } else {
            do {
                $shortened_url = generateShortCode($short_code_length);
            } while (!isShortCodeUnique($conn, $shortened_url));
        }

        if (empty($error_message)) {
            $sql = "INSERT INTO shortener (original_url, shortened_url, created_at, click_count";
            $sql .= $password ? ", password" : "";
            $sql .= $max_clicks !== null ? ", max_clicks" : "";
            $sql .= $expiration_date ? ", expiration_date" : "";
            $sql .= ") VALUES (:original_url, :shortened_url, NOW(), 0";
            $sql .= $password ? ", :password" : "";
            $sql .= $max_clicks !== null ? ", :max_clicks" : "";
            $sql .= $expiration_date ? ", :expiration_date" : "";
            $sql .= ")";

            $stmt = $conn->prepare($sql);
            $stmt->bindParam(':original_url', $original_url);
            $stmt->bindParam(':shortened_url', $shortened_url);
            if ($password) $stmt->bindParam(':password', $password);
            if ($max_clicks !== null) $stmt->bindParam(':max_clicks', $max_clicks);
            if ($expiration_date) $stmt->bindParam(':expiration_date', $expiration_date);
            $stmt->execute();

            $_SESSION['success_message'] = "Shortened URL: <a href='?code=$shortened_url'>$shortened_url</a>";

            // Redirect to refresh page
            header("Location: " . $_SERVER['PHP_SELF']);
            exit;
        }
    }
} catch (PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

// Function to increment click count
function incrementClickCount($conn, $shortened_url, $current_count): void {
    $new_click_count = $current_count + 1;
    $update_stmt = $conn->prepare("UPDATE shortener SET click_count = :click_count WHERE shortened_url = :shortened_url");
    $update_stmt->bindParam(':click_count', $new_click_count);
    $update_stmt->bindParam(':shortened_url', $shortened_url);
    $update_stmt->execute();
}

// Function to generate a short code
function generateShortCode($length = 6): string {
    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $shortCode = '';
    for ($i = 0; $i < $length; $i++) {
        $shortCode .= $characters[rand(0, strlen($characters) - 1)];
    }
    return $shortCode;
}

// Function to check if a short code is unique
function isShortCodeUnique($conn, $shortened_url): bool {
    $stmt = $conn->prepare("SELECT COUNT(*) FROM shortener WHERE shortened_url = :shortened_url");
    $stmt->bindParam(':shortened_url', $shortened_url);
    $stmt->execute();
    return $stmt->fetchColumn() == 0;
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>URL Shortener</title>
</head>
<body>
<h2>URL Shortener</h2>

<?php if (isset($_SESSION['user_id'])): ?>
    <h2>Welcome, <?= htmlspecialchars($_SESSION['username']); ?>!</h2>
<?php endif; ?>

<?php if (isset($_SESSION['success_message'])): ?>
    <p style="color: green;"><?= $_SESSION['success_message']; ?></p>
    <?php unset($_SESSION['success_message']); ?>
<?php endif; ?>

<form method="post" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>">
    <label for="original_url">Enter URL to Shorten:</label><br>
    <input type="url" name="original_url" required><br><br>

    <label for="custom_code">Custom Short Code (optional):</label><br>
    <input type="text" name="custom_code"><br><br>

    <label for="password">Password Protect (optional):</label><br>
    <input type="password" name="password"><br><br>

    <label for="max_clicks">Max Clicks (optional):</label><br>
    <input type="number" name="max_clicks" min="1"><br><br>

    <label for="short_code_length">Short Code Length (optional, default 6):</label><br>
    <input type="number" name="short_code_length" min="1"><br><br>

    <label for="expiration_date">Expiration Date and Time (optional):</label><br>
    <input type="datetime-local" name="expiration_date"><br><br>

    <input type="submit" value="Shorten URL">
</form>

<?php if (!empty($error_message)): ?>
    <p style="color: red;"><?= $error_message; ?></p>
<?php endif; ?>
</body>
</html>
