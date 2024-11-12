<?php
session_start(); // start the session for the user. We need this to keep track of stuff like logged-in users or success messages

// set timezone to Sofia, Bulgaria time zone, makes it consistent
date_default_timezone_set('Europe/Sofia'); // cuz

// Headers here prevent caching issues, so we always get fresh content
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

// database settings — gotta connect to the local database called 'codewars' with root user and no password
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "codewars";

$error_message = ""; // placeholder for error messages (empty)

try {
    // PDO connection for database interaction, setting it up to throw errors if things go wrong
    $conn = new PDO("mysql:host=$servername;dbname=$dbname", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // if user provided a short code (like /xyz123) in the URL, then we need to redirect
    if (isset($_GET['code'])) {
        $shortened_url = $_GET['code'];

        // search database for original link info, including click count, limits, etc.
        $sql = "SELECT id, original_url, click_count, max_clicks, password, expiration_date FROM shortener WHERE shortened_url = :shortened_url"; // get
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':shortened_url', $shortened_url);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($result) {
            // checking expiration, if expiration date is set & has passed, then URL is dead
            if ($result['expiration_date'] !== "0000-00-00 00:00:00" && !empty($result['expiration_date']) && new DateTime() > new DateTime($result['expiration_date'])) {
                $error_message = "This shortened URL has expired!";
            } elseif (!empty($result['max_clicks']) && $result['click_count'] >= $result['max_clicks']) {
                // check if we've hit the max click limit
                $error_message = "This shortened URL has reached its maximum click limit!";
            } elseif (!empty($result['password'])) {
                // if password is required, we handle it here
                if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['password'])) {
                    // check if entered password matches the hashed one in DB
                    if (password_verify($_POST['password'], $result['password'])) {
                        incrementClickCount($conn, $shortened_url, $result['click_count']);
                        header("Location: " . $result['original_url']);
                        exit;
                    } else {
                        $error_message = "Incorrect password!";
                    }
                } else {
                    // if password not submitted, prompt user to enter it
                    echo "<form method='post'>
                            <label for='password'>Enter Password:</label><br>
                            <input type='password' name='password' required><br><br>
                            <input type='submit' value='Submit'>
                          </form>"; // not added styles tho
                    exit;
                }
            } else {
                // no password needed, just increment count and redirect
                incrementClickCount($conn, $shortened_url, $result['click_count']);
                header("Location: " . $result['original_url']);
                exit;
            }
        } else {
            $error_message = "Shortened URL not found!";
        }
    }

    // processing the form if it's submitted for URL shortening
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['original_url'])) {
        $original_url = $_POST['original_url'];
        $custom_code = trim($_POST['custom_code']);
        $password = !empty($_POST['password']) ? password_hash($_POST['password'], PASSWORD_DEFAULT) : null;
        $max_clicks = !empty($_POST['max_clicks']) ? intval($_POST['max_clicks']) : null;
        $expiration_date = !empty($_POST['expiration_date']) ? $_POST['expiration_date'] : null;

        // handle special case of "infinite" expiration date
        if ($expiration_date === "0000-00-00T00:00") {
            $expiration_date = null;
        }

        // length of the short code if custom one isn’t provided
        $short_code_length = isset($_POST['short_code_length']) ? intval($_POST['short_code_length']) : 6;
        if ($short_code_length < 1) {
            $short_code_length = 6;
        }

        if (!empty($custom_code)) {
            // check if custom code is unique
            if (isShortCodeUnique($conn, $custom_code)) {
                $shortened_url = $custom_code;
            } else {
                $error_message = "The custom short code is already taken. Please choose a different one.";
            }
        } else {
            // auto-generate code if custom code isn’t provided
            $max_attempts = pow(62, $short_code_length);
            $attempt_count = 0;

            do {
                $shortened_url = generateShortCode($short_code_length);
                $attempt_count++;

                if ($attempt_count > $max_attempts) {
                    $error_message = "Couldn't find any URL, try again with higher length";
                    break;
                }
            } while (!isShortCodeUnique($conn, $shortened_url));
        }

        if (empty($error_message)) {
            // create insert statement with optional fields
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

            // add link to user if logged in
            $link_id = $conn->lastInsertId();
            if (isset($_SESSION['user_id'])) {
                $user_id = $_SESSION['user_id'];
                $stmt = $conn->prepare("SELECT created_links FROM users WHERE id = :user_id");
                $stmt->bindParam(':user_id', $user_id);
                $stmt->execute();
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
                $created_links = !empty($user['created_links']) ? json_decode($user['created_links'], true) : [];
                $created_links[] = $link_id;
                $stmt = $conn->prepare("UPDATE users SET created_links = :created_links WHERE id = :user_id");
                $stmt->bindParam(':created_links', json_encode($created_links));
                $stmt->bindParam(':user_id', $user_id);
                $stmt->execute();
            }

            $_SESSION['success_message'] = "Shortened URL: <a href='./$shortened_url'>$shortened_url</a>";

            // refresh page to show success message
            header("Location: " . $_SERVER['PHP_SELF']);
            exit;
        }
    }
} catch (PDOException $e) {
    $error_message = "Connection failed: " . $e->getMessage(); // welp, you're on your own then bro
}

// increments click count
function incrementClickCount($conn, $shortened_url, $current_count): void {
    $new_click_count = $current_count + 1; // increase
    $update_stmt = $conn->prepare("UPDATE shortener SET click_count = :click_count WHERE shortened_url = :shortened_url"); // set
    $update_stmt->bindParam(':click_count', $new_click_count);
    $update_stmt->bindParam(':shortened_url', $shortened_url);
    $update_stmt->execute(); // finish
}

// generates short code
function generateShortCode($length = 6): string {
    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $shortCode = '';
    for ($i = 0; $i < $length; $i++) {
        $shortCode .= $characters[rand(0, strlen($characters) - 1)]; // select random char from string
    }
    return $shortCode;
}

// checks if short code is unique
function isShortCodeUnique($conn, $shortened_url): bool {
    if ($shortened_url === 'profile')
        return false;

    $stmt = $conn->prepare("SELECT COUNT(*) FROM shortener WHERE shortened_url = :shortened_url"); // check if exists
    $stmt->bindParam(':shortened_url', $shortened_url);
    $stmt->execute();
    return $stmt->fetchColumn() == 0; // if it's 0 (doesn't exist) return true, otherwise false
}

?>






<!DOCTYPE html>
<html lang="en">
<head>
    <title>URL Shortener</title>
    <link rel="stylesheet" type="text/css" href="./public/style/index.css" />
</head>
<body>

<form method="post" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" class="url-form">
    <h2>URL Shortener</h2>

    <?php if (isset($_SESSION['user_id'])): ?>
        <h2>Welcome, <a href="profile"><?= htmlspecialchars($_SESSION['username']); ?></a>!</h2>
    <?php else: ?>
        <h2>You're a guest right now! <a href="profile">Sign Up/Log In</a></h2>
    <?php endif; ?>

    <?php if (isset($_SESSION['success_message'])): ?>
        <p class="success-message"><?= $_SESSION['success_message']; ?></p>
        <?php unset($_SESSION['success_message']); ?>
    <?php endif; ?>

    <?php if (!empty($error_message)): ?>
        <p class="error-message"><?= $error_message; ?></p>
    <?php endif; ?>

    <label for="original_url">Enter URL to Shorten:</label><br>
    <input type="url" name="original_url" required><br><br>

    <label for="custom_code">Custom Short Code (optional):</label><br>
    <input type="text" name="custom_code" maxlength="10"><br><br>

    <label for="password">Password Protect (optional):</label><br>
    <input type="password" name="password" id="password"><br><br>
    <button type="button" onclick="togglePassword('password', 'toggleNew')">Show</button><br><br>

    <label for="max_clicks">Max Clicks (optional):</label><br>
    <input type="number" name="max_clicks" min="1"><br><br>

    <label for="short_code_length">Short Code Length (optional, default 6):</label><br>
    <input type="number" name="short_code_length" min="1"><br><br>

    <label for="expiration_date">Expiration Date and Time (optional):</label><br>
    <input type="datetime-local" name="expiration_date"><br><br>

    <input type="submit" value="Shorten URL" class="submit-btn">
</form>

<script type="text/javascript" src="./public/script/background.js"></script>
<script type="text/javascript" src="./public/script/password.js"></script>




</body>
</html>
