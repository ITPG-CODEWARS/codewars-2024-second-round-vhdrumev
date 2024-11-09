<?php
// Start a session
session_start();

// Database configuration
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "codewars"; // Your database name

try {
    // Create a new PDO connection
    $conn = new PDO("mysql:host=$servername;dbname=$dbname", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Function to redirect to the same page
    function redirect() {
        header("Location: " . $_SERVER['PHP_SELF']);
        exit;
    }

    // Handle user registration
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'register') {
        $username = trim($_POST['username']);
        $email = trim($_POST['email']);
        $password = trim($_POST['password']);
        $confirm_password = trim($_POST['confirm_password']);

        // Check if passwords match
        if ($password !== $confirm_password) {
            echo "Passwords do not match!";
        } else {
            // Check if the username or email already exists
            $stmt = $conn->prepare("SELECT * FROM users WHERE username = :username OR email = :email");
            $stmt->bindParam(':username', $username);
            $stmt->bindParam(':email', $email);
            $stmt->execute();

            if ($stmt->rowCount() > 0) {
                echo "Username or Email already exists!";
            } else {
                // Hash and salt the password
                $hashed_password = password_hash($password, PASSWORD_BCRYPT);

                // Insert user into the database
                $stmt = $conn->prepare("INSERT INTO users (username, email, password, created_links, last_login, created_at, updated_at) 
                                        VALUES (:username, :email, :password, :created_links, NOW(), NOW(), NOW())");
                $stmt->bindParam(':username', $username);
                $stmt->bindParam(':email', $email);
                $stmt->bindParam(':password', $hashed_password);
                $emptyJson = json_encode([]);
                $stmt->bindParam(':created_links', $emptyJson);
                $stmt->execute();

                // Automatically log the user in
                $_SESSION['user_id'] = $conn->lastInsertId();
                $_SESSION['username'] = $username;

                redirect();
            }
        }
    }

    // Handle user login (no changes needed)
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'login') {
        $username = trim($_POST['username']);
        $password = trim($_POST['password']);

        // Find the user in the database
        $stmt = $conn->prepare("SELECT * FROM users WHERE username = :username");
        $stmt->bindParam(':username', $username);
        $stmt->execute();
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($password, $user['password'])) {
            // Update last login timestamp
            $stmt = $conn->prepare("UPDATE users SET last_login = NOW() WHERE id = :id");
            $stmt->bindParam(':id', $user['id']);
            $stmt->execute();

            // Set session variables
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];

            redirect();
        } else {
            echo "Invalid username or password!";
        }
    }

    // Handle user update (no changes needed)
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update') {
        if (!isset($_SESSION['user_id'])) {
            echo "You are not logged in!";
            exit;
        }

        $user_id = $_SESSION['user_id'];
        $old_password = trim($_POST['old_password']);
        $new_username = trim($_POST['new_username']);
        $new_email = trim($_POST['new_email']);
        $new_password = trim($_POST['new_password']);

        // Verify the old password
        $stmt = $conn->prepare("SELECT * FROM users WHERE id = :id");
        $stmt->bindParam(':id', $user_id);
        $stmt->execute();
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($old_password, $user['password'])) {
            // Update username if provided
            if (!empty($new_username)) {
                $stmt = $conn->prepare("UPDATE users SET username = :username WHERE id = :id");
                $stmt->bindParam(':username', $new_username);
                $stmt->bindParam(':id', $user_id);
                $stmt->execute();

                $stmt = $conn->prepare("UPDATE users SET updated_at = NOW() WHERE id = :id");
                $stmt->bindParam(':id', $user_id);
                $stmt->execute();
                $_SESSION['username'] = $new_username;
            }

            // Update email if provided
            if (!empty($new_email)) {
                $stmt = $conn->prepare("UPDATE users SET email = :email WHERE id = :id");
                $stmt->bindParam(':email', $new_email);
                $stmt->bindParam(':id', $user_id);
                $stmt->execute();
            }

            // Update password if provided
            if (!empty($new_password)) {
                $hashed_new_password = password_hash($new_password, PASSWORD_BCRYPT);
                $stmt = $conn->prepare("UPDATE users SET password = :password WHERE id = :id");
                $stmt->bindParam(':password', $hashed_new_password);
                $stmt->bindParam(':id', $user_id);
                $stmt->execute();
            }

            echo "Profile updated successfully!";
            redirect();
        } else {
            echo "Incorrect old password!";
        }
    }

} catch (PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

?>

<!DOCTYPE html>
<html>
<head>
    <title>User Registration, Login, and Profile Update</title>
    <script>
        // Function to toggle password visibility
        function togglePassword(inputId, toggleId) {
            const input = document.getElementById(inputId);
            const toggle = document.getElementById(toggleId);
            if (input.type === "password") {
                input.type = "text";
                toggle.textContent = "Hide";
            } else {
                input.type = "password";
                toggle.textContent = "Show";
            }
        }
    </script>
</head>
<body>
<?php if (isset($_SESSION['user_id'])): ?>
    <h2>Welcome, <?= htmlspecialchars($_SESSION['username']); ?>!</h2>
    <h3>Update Profile</h3>
    <form method="post" action="">
        <input type="hidden" name="action" value="update">
        <label for="old_password">Old Password:</label><br>
        <input type="password" id="old_password" name="old_password" required>
        <button type="button" onclick="togglePassword('old_password', 'toggleOld')">Show</button><br><br>
        <label for="new_username">New Username:</label><br>
        <input type="text" name="new_username"><br><br>
        <label for="new_email">New Email:</label><br>
        <input type="email" name="new_email"><br><br>
        <label for="new_password">New Password:</label><br>
        <input type="password" id="new_password" name="new_password">
        <button type="button" onclick="togglePassword('new_password', 'toggleNew')">Show</button><br><br>
        <input type="submit" value="Update Profile">
    </form>
    <a href="logout.php">Logout</a>
<?php else: ?>
    <h2>User Registration</h2>
    <form method="post" action="">
        <input type="hidden" name="action" value="register">
        <label for="username">Username:</label><br>
        <input type="text" name="username" required><br><br>
        <label for="email">Email:</label><br>
        <input type="email" name="email" required><br><br>
        <label for="password">Password:</label><br>
        <input type="password" id="password" name="password" required>
        <button type="button" onclick="togglePassword('password', 'togglePassword')">Show</button><br><br>
        <label for="confirm_password">Confirm Password:</label><br>
        <input type="password" id="confirm_password" name="confirm_password" required>
        <button type="button" onclick="togglePassword('confirm_password', 'toggleConfirm')">Show</button><br><br>
        <input type="submit" value="Register">
    </form>

    <h2>User Login</h2>
    <form method="post" action="">
        <input type="hidden" name="action" value="login">
        <label for="username">Username:</label><br>
        <input type="text" name="username" required><br><br>
        <label for="password">Password:</label><br>
        <input type="password" id="login_password" name="password" required>
        <button type="button" onclick="togglePassword('login_password', 'toggleLogin')">Show</button><br><br>
        <input type="submit" value="Login">
    </form>
<?php endif; ?>
</body>
</html>
