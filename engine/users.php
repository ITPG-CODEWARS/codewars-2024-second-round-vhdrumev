<?php
// Start a session - needed to handle login/logout & user session data
session_start();

// Database configuration - these are just the credentials
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "codewars"; // database where user data and short URLs are stored

try {
    // Connect to MySQL using PDO - Exception mode enabled for error handling
    $conn = new PDO("mysql:host=$servername;dbname=$dbname", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Helper function to refresh the page
    function redirect(): void
    {
        header("Location: " . $_SERVER['PHP_SELF']);
        exit;
    }

    // destroys session & redirects user to main page
    if (isset($_GET['action']) && $_GET['action'] === 'logout') {
        session_destroy();
        header("Location: ../"); // Main page redirection if logout
        exit;
    }

    // Registration section
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'register') {
        $username = trim($_POST['username']); // removes extra whitespace trim function
        $email = trim($_POST['email']);
        $password = trim($_POST['password']);
        $confirm_password = trim($_POST['confirm_password']);

        // Basic password match check
        if ($password !== $confirm_password) {
            echo "Passwords do not match!";
        } else {
            // Check if username or email already in use
            $stmt = $conn->prepare("SELECT * FROM users WHERE username = :username OR email = :email");
            $stmt->bindParam(':username', $username);
            $stmt->bindParam(':email', $email);
            $stmt->execute();

            if ($stmt->rowCount() > 0) {
                echo "Username or Email already exists!";
            } else {
                // All good, so let's hash the password and add the user
                $hashed_password = password_hash($password, PASSWORD_BCRYPT);

                // Insert user data to the database, with empty 'created_links' initially
                $stmt = $conn->prepare("INSERT INTO users (username, email, password, created_links, last_login, created_at, updated_at) 
                                        VALUES (:username, :email, :password, :created_links, NOW(), NOW(), NOW())"); // put values on
                $stmt->bindParam(':username', $username);
                $stmt->bindParam(':email', $email);
                $stmt->bindParam(':password', $hashed_password);
                $emptyJson = json_encode([]);
                $stmt->bindParam(':created_links', $emptyJson);
                $stmt->execute();

                // Auto-login right after registration
                $_SESSION['user_id'] = $conn->lastInsertId();
                $_SESSION['username'] = $username;

                redirect(); // avoid resubmission of form
            }
        }
    }

    // Login section
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'login') {
        $username = trim($_POST['username']);
        $password = trim($_POST['password']);

        // Retrieve user from the database
        $stmt = $conn->prepare("SELECT * FROM users WHERE username = :username");
        $stmt->bindParam(':username', $username);
        $stmt->execute();
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        // Verify password
        if ($user && password_verify($password, $user['password'])) {
            // Update last login timestamp
            $stmt = $conn->prepare("UPDATE users SET last_login = NOW() WHERE id = :id");
            $stmt->bindParam(':id', $user['id']);
            $stmt->execute();

            // Session variables for logged-in state
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];

            redirect();
        } else {
            echo "<p class='error'>Invalid username or password!</p>";
        }
    }

    // Profile update section
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

        // Verify old password before allowing updates
        $stmt = $conn->prepare("SELECT * FROM users WHERE id = :id"); // select ONLY from id for user
        $stmt->bindParam(':id', $user_id);
        $stmt->execute();
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($old_password, $user['password'])) {
            // update username if given
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

            // update email if given
            if (!empty($new_email)) {
                $stmt = $conn->prepare("UPDATE users SET email = :email WHERE id = :id");
                $stmt->bindParam(':email', $new_email);
                $stmt->bindParam(':id', $user_id);
                $stmt->execute();
            }

            // update password if given
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
            echo "<p class='error'>Incorrect old password!</p>";
        }
    }

} catch (PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Registration, Login, and Profile Update</title>

    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Arial', sans-serif;
        }

        .error {
            text-align: center;
            padding: 10px 0;
            width: 1100px;
            margin-bottom: 20px;
            background-color: rgba(255, 0, 0, 0.2);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 15px;
        }

        body {
            background: linear-gradient(135deg, #3e8e41, #64b3a5);
            color: #fff;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: flex-start;
            min-height: 100vh;
            padding: 20px;
        }

        .container {
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            gap: 20px;
            width: 100%;
            max-width: 1200px;
        }

        .form-container {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.3);
            padding: 20px;
            width: 45%;
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        h2, h3 {
            text-align: center;
            font-size: 1.75rem;
            color: #fff;
        }

        h3 {
            font-size: 1.5rem;
        }

        form {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        label {
            font-size: 1rem;
        }

        input[type="text"],
        input[type="email"],
        input[type="password"],
        input[type="submit"] {
            padding: 0.75rem;
            border-radius: 10px;
            border: 1px solid rgba(255, 255, 255, 0.3);
            background-color: rgba(255, 255, 255, 0.1);
            color: #fff;
            font-size: 1rem;
            transition: .25s;
        }

        input[type="password"]:focus,
        input[type="text"]:focus,
        input[type="email"]:focus {
            border-radius: 20px;
            outline: none;
            border-color: rgba(255, 255, 255, 0.4);
            background-color: rgba(255, 255, 255, 0.2);
        }

        button[type="button"] {
            background: none;
            color: #f1f1f1;
            border: none;
            font-size: 1rem;
            cursor: pointer;
        }

        button[type="button"]:hover {
            color: #fff;
            text-decoration: underline;
        }

        input[type="submit"] {
            background-color: transparent;
            color: #f1f1f1;
            padding: 1rem;
            border-radius: 10px;
            border: 1px solid rgba(255, 255, 255, 0.2);
            cursor: pointer;
            transition: background-color 0.3s ease;
        }

        input[type="submit"]:hover {
            background-color: rgba(255, 255, 255, 0.2);
            border-color: rgba(255, 255, 255, 0.2);
        }

        a {
            color: #f1f1f1;
            text-decoration: none;
            font-size: 1rem;
            display: block;
            margin-top: 15px;
        }

        a:hover {
            text-decoration: underline;
        }

        /* Links Table */
        table {
            width: 100%;
            margin-top: 20px;
            background: rgba(255, 255, 255, 0.2);
            border-collapse: collapse;
            border-radius: 10px;
            backdrop-filter: blur(10px);
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
        }

        th, td {
            padding: 15px;
            text-align: left;
            color: #fff;
            border-bottom: 1px solid rgba(255, 255, 255, 0.3);
        }

        th {
            background: rgba(0, 0, 0, 0.3);
        }

        tr {
            transition: background-color 0.3s;
        }

        tr:hover {
            background-color: rgba(255, 255, 255, 0.1);
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .form-container {
                width: 100%;
                flex-direction: column;
            }

            h2, h3 {
                font-size: 1.5rem;
            }

            input[type="submit"] {
                width: 100%;
            }
        }
    </style>

</head>
<body>
<?php if (isset($_SESSION['user_id'])): ?>
    <div class="container">
        <div class="form-container">
            <h2>Welcome, <?= htmlspecialchars($_SESSION['username']); ?>!</h2>
            <h3>Update Profile</h3>
            <form method="post" action="">
                <input type="hidden" name="action" value="update">
                <label for="old_password">Old Password:</label><br>
                <input type="password" id="old_password" name="old_password" required>
                <button type="button" onclick="togglePassword('old_password', 'toggleOld')">Show</button><br><br>
                <label for="new_username">New Username:</label><br>
                <input type="text" name="new_username"><br><br>
                <label for="new_email">New Email:</label><br><br>
                <input type="email" name="new_email"><br><br>
                <label for="new_password">New Password:</label><br>
                <input type="password" id="new_password" name="new_password">
                <button type="button" onclick="togglePassword('new_password', 'toggleNew')">Show</button><br><br>
                <input type="submit" value="Update Profile">
            </form>
            <a href="?action=logout">Logout</a>
        </div>
        <div class="form-container">
            <h2>Your Created Links:</h2>
            <?php
            // Fetch user's created links from the database
            $user_id = $_SESSION['user_id'];
            $stmt = $conn->prepare("SELECT created_links FROM users WHERE id = :id");
            $stmt->bindParam(':id', $user_id);
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            // Check if user has created links and display them in a table
            if ($result && !empty($result['created_links'])) {
                $created_links = json_decode($result['created_links'], true); // Decode JSON

                if (!empty($created_links)) {

                    // Loop through each link ID and fetch the corresponding shortened URL
                    foreach ($created_links as $link_id) {
                        // Query to fetch the shortened URL from the shortener table using the link's ID
                        $stmt = $conn->prepare("SELECT shortened_url FROM shortener WHERE id = :link_id");
                        $stmt->bindParam(':link_id', $link_id);
                        $stmt->execute();
                        $shortened_result = $stmt->fetch(PDO::FETCH_ASSOC);

                        // Debugging output for the link ID and result
                        //echo "<p>Processing Link ID: " . htmlspecialchars($link_id) . "</p>";

                        // Check if a shortened URL is found
                        if ($shortened_result && !empty($shortened_result['shortened_url'])) {
                            $shortened_url = $shortened_result['shortened_url'];
                            echo "<a href='../" . htmlspecialchars($shortened_url) . "' target='_blank'>" . htmlspecialchars($shortened_url) . "</a><br>";
                        } else {
                            echo "<p>Link with ID " . htmlspecialchars($link_id) . " not found.</p>";
                        }
                    }
                } else {
                    echo "<p>No links created yet.</p>";
                }
            } else {
                echo "<p>No links created yet.</p>";
            }
            ?>
        </div>
    </div>
<?php else: ?>
    <div class="container">
        <div class="form-container">
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
        </div>

        <div class="form-container">
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
        </div>
    </div>
<?php endif; ?>


<script type="text/javascript" src="../public/script/background.js"></script>
<script type="text/javascript" src="../public/script/password.js"></script>

</body>
</html>
