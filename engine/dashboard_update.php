<?php

const USERNAME = 'root';
const PASSWORD = 'root';

const MAX_ATTEMPTS = 3;
const WAIT_TIME = 10; // in seconds

// Start the session to track attempts
session_start();

// Initialize the attempts counter if not already set
if (!isset($_SESSION['attempts'])) {
    $_SESSION['attempts'] = 0;
}

// if the user exceeds the maximum attempts, force them to wait
if ($_SESSION['attempts'] >= MAX_ATTEMPTS) {
    $waitUntil = $_SESSION['wait_until'] ?? 0; // isset($_SESSION['wait_until']) ? $_SESSION['wait_until'] : 0 (short)
    if ($waitUntil > time()) {
        // If the user is still in the wait period
        $remainingWait = $waitUntil - time();
        echo "Too many failed attempts. Please wait $remainingWait seconds.";
        exit;
    } else {
        // give on emore chance
        $_SESSION['attempts']--;
    }
}

// Check if the user has sent an Authorization header
if (!isset($_SERVER['PHP_AUTH_USER']) || !isset($_SERVER['PHP_AUTH_PW']) ||
    $_SERVER['PHP_AUTH_USER'] !== USERNAME || $_SERVER['PHP_AUTH_PW'] !== PASSWORD) {

    // If not, increment the failed attempts counter
    $_SESSION['attempts']++;

    // If attempts exceed the maximum, start the wait period
    if ($_SESSION['attempts'] >= MAX_ATTEMPTS) {
        $_SESSION['wait_until'] = time() + WAIT_TIME; // Set wait period
        echo 'Too many failed attempts. Please try again after 10 seconds.';
        exit;
    }

    // if any sneak traps, display this:
    header('HTTP/1.1 401 Unauthorized');
    header('WWW-Authenticate: Basic realm="Restricted Area"');
    echo 'Authorization required. Attempt ' . $_SESSION['attempts'] . ' of ' . MAX_ATTEMPTS . '.';
    exit; // this is very important
} // from now on code is shown ONLY if correct username and password are given

// Successful login, reset attempts counter
$_SESSION['attempts'] = 0;

// START OF CODE -------------------------------------------------------------------------------------------------------

$host = 'localhost';
$dbname = 'codewars';
$username = 'root';
$password = '';

$error_message = '';

try {
    // Set up the PDO connection
    $dsn = "mysql:host=$host;dbname=$dbname;charset=utf8";
    $pdo = new PDO($dsn, $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Fetch users data
    $sql_users = 'SELECT * FROM users';
    $stmt_users = $pdo->query($sql_users);
    $users = $stmt_users->fetchAll(PDO::FETCH_ASSOC);

    // Fetch shortener data
    $sql_shortener = 'SELECT * FROM shortener';
    $stmt_shortener = $pdo->query($sql_shortener);
    $shorteners = $stmt_shortener->fetchAll(PDO::FETCH_ASSOC);

    // Handle form submission to update the database
    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        // Update users
        if (isset($_POST['update_users'])) {
            foreach ($_POST['users'] as $user_id => $user_data) {
                $sql_update_user = 'UPDATE users SET username = :username, email = :email, created_links = :created_links WHERE id = :id'; // update them
                $stmt = $pdo->prepare($sql_update_user);
                $stmt->execute([
                    ':username' => $user_data['username'],
                    ':email' => $user_data['email'],
                    ':created_links' => $user_data['created_links'],
                    ':id' => $user_id
                ]);
            }
        }

        // Update shorteners
        if (isset($_POST['update_shorteners'])) {
            foreach ($_POST['shorteners'] as $shortener_id => $shortener_data) {
                // Check if the shortened URL already exists
                $sql_check_url = 'SELECT COUNT(*) FROM shortener WHERE shortened_url = :shortened_url AND id != :id'; // get
                $stmt_check = $pdo->prepare($sql_check_url);
                $stmt_check->execute([
                    ':shortened_url' => $shortener_data['shortened_url'],
                    ':id' => $shortener_id
                ]);
                $existing_url_count = $stmt_check->fetchColumn();

                // If the URL already exists, set an error message
                if ($existing_url_count > 0) {
                    $error_message = 'Error: The shortened URL "' . $shortener_data['shortened_url'] . '" is already taken. Please choose another.';
                    break;
                } else {
                    // Update shortener data if no duplicate URL is found
                    $sql_update_shortener = 'UPDATE shortener SET original_url = :original_url, shortened_url = :shortened_url, /*created_at = :created_at,*/ expiration_date = :expiration_date, click_count = :click_count, max_clicks = :max_clicks WHERE id = :id';
                    // ^^^ <- a long update sql to set everything to new values form table :)
                    $stmt = $pdo->prepare($sql_update_shortener);
                    $stmt->execute([
                        ':original_url' => $shortener_data['original_url'],
                        ':shortened_url' => $shortener_data['shortened_url'],
                        //':created_at' => $shortener_data['created_at'],
                        ':expiration_date' => $shortener_data['expiration_date'],
                        ':click_count' => $shortener_data['click_count'],
                        ':max_clicks' => $shortener_data['max_clicks'],
                        ':id' => $shortener_id
                    ]);
                }
            }
        }

        if (empty($error_message)) {
            echo 'Database updated successfully!';
        }
    }

} catch (PDOException $e) {
    echo 'Connection failed: ' . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard</title>
    <style>


        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
        }

        h3 {
            color: #eee;
            text-align: center;
            margin-top: 20px;
        }

        form {
            max-width: 1400px;
            margin: 20px auto;
            padding: 20px;
            background-color: rgba(255, 255, 255, 0.2);
            backdrop-filter: blur(10px);
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }

        table th, table td {
            padding: 12px;
            text-align: left;
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        table tr {
            transition: .25s;
        }

        table tr:hover {
            background-color: rgba(255, 255, 255, 0.2);
        }

        table th {
            background-color: rgba(255, 255, 255, 0.4);
            font-weight: bold;
            color: #eee;
        }

        input[type="text"], input[type="number"] {
            width: 100%;
            padding: 8px;
            font-size: 14px;
            border: 1px solid #ccc;
            background-color: transparent;
            color: #eee;
            border-radius: 4px;
            box-sizing: border-box;
        }

        button {
            padding: 12px 20px;
            background-color: transparent;
            border: 1px solid rgba(255, 255, 255, 0.2);
            color: white;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
            width: 100%;
            margin-top: 20px;
            transition: .25s;
        }

        button:hover {
            border-radius: 20px;
            background-color: rgba(255, 255, 255, 0.2);
            border-color: rgba(255, 255, 255, 0.4);
        }

        .edited {
            background-color: rgba(255, 255, 255, 0.6);
        }

        .edited input {
            background-color: rgba(255, 255, 255, 0.6);
        }

        .error {
            color: #ff0000;
            font-weight: bold;
            margin: 20px 0;
            text-align: center;
        }
    </style>
</head>
<body>
<h3>Users Table</h3>
<form method="POST">
    <table>
        <thead>
        <tr>
            <th>ID</th>
            <th>Username</th>
            <th>Email</th>
            <th>Created Links</th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($users as $user): ?>
            <tr class="user-row">
                <td><input type="text" name="users[<?php echo $user['id']; ?>][id]" value="<?php echo $user['id']; ?>" disabled></td>
                <td><input type="text" name="users[<?php echo $user['id']; ?>][username]" value="<?php echo htmlspecialchars($user['username']); ?>"></td>
                <td><input type="text" name="users[<?php echo $user['id']; ?>][email]" value="<?php echo htmlspecialchars($user['email']); ?>"></td>
                <td><input type="text" name="users[<?php echo $user['id']; ?>][created_links]" value="<?php echo htmlspecialchars($user['created_links']); ?>"></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <button type="submit" name="update_users">Update Users</button>
</form>

<h3>Shortener Table</h3>

<!-- Display error message if exists -->
<?php if ($error_message): ?>
    <div class="error"><?php echo $error_message; ?></div>
<?php endif; ?>

<form method="POST">
    <table>
        <thead>
        <tr>
            <th>ID</th>
            <th>Original URL</th>
            <th>Shortened URL</th>
            <th>Expiration Date</th>
            <th>Click Count</th>
            <th>Max Clicks</th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($shorteners as $shortener): ?>
            <tr class="shortener-row">
                <td><input type="text" name="shorteners[<?php echo $shortener['id']; ?>][id]" value="<?php echo $shortener['id']; ?>" disabled></td>
                <td><input type="text" name="shorteners[<?php echo $shortener['id']; ?>][original_url]" value="<?php echo htmlspecialchars($shortener['original_url']); ?>"></td>
                <td><input type="text" name="shorteners[<?php echo $shortener['id']; ?>][shortened_url]" value="<?php echo htmlspecialchars($shortener['shortened_url']); ?>"></td>
                <td><input type="text" name="shorteners[<?php echo $shortener['id']; ?>][expiration_date]" value="<?php echo htmlspecialchars($shortener['expiration_date']); ?>"></td>
                <td><input type="number" name="shorteners[<?php echo $shortener['id']; ?>][click_count]" value="<?php echo htmlspecialchars($shortener['click_count']); ?>"></td>
                <td><input type="number" name="shorteners[<?php echo $shortener['id']; ?>][max_clicks]" value="<?php echo htmlspecialchars($shortener['max_clicks']); ?>"></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <button type="submit" name="update_shorteners">Update Shorteners</button>
</form>



<script type="text/javascript" src="../public/script/background.js"></script>
<script type="text/javascript" src="../public/script/password.js"></script>
<script type="text/javascript" src="../public/script/scroll.js"></script>
</body>
</html>
