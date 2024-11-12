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


session_start();

// Database variables
$servername = "localhost";
$username = "root";
$password = "";
$database = "codewars";

try {
    // Create a PDO connection
    $conn = new PDO("mysql:host=$servername;dbname=$database", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Fetch shortener data
    $stmt = $conn->prepare("SELECT * FROM shortener");
    $stmt->execute();
    $shortenerResults = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Fetch users data
    $stmt = $conn->prepare("SELECT * FROM users");
    $stmt->execute();
    $userResults = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Initialize session arrays if not already set
    if (!isset($_SESSION['deleted_shortener'])) {
        $_SESSION['deleted_shortener'] = [];
    }
    if (!isset($_SESSION['deleted_users'])) {
        $_SESSION['deleted_users'] = [];
    }

    // just a mess T^T

    // Handle delete/restore functionality
    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        if (isset($_POST['delete_shortener'])) {
            $idToDelete = $_POST['delete_shortener'];
            // Add ID to the deleted_shortener session array
            $_SESSION['deleted_shortener'][] = $idToDelete;
        } elseif (isset($_POST['restore_shortener'])) {
            $idToRestore = $_POST['restore_shortener'];
            // remove ID from the deleted_shortener session array
            $_SESSION['deleted_shortener'] = array_diff($_SESSION['deleted_shortener'], [$idToRestore]);
        } elseif (isset($_POST['delete_user'])) {
            $idToDelete = $_POST['delete_user'];
            // add ID to the deleted_users session array
            $_SESSION['deleted_users'][] = $idToDelete;
        } elseif (isset($_POST['restore_user'])) {
            $idToRestore = $_POST['restore_user'];
            // Remove ID from the deleted_users session array
            $_SESSION['deleted_users'] = array_diff($_SESSION['deleted_users'], [$idToRestore]);
        } elseif (isset($_POST['update'])) {
            // delete items marked for deletion in the shortener table
            if (isset($_SESSION['deleted_shortener'])) {
                foreach ($_SESSION['deleted_shortener'] as $deletedId) {
                    $stmt = $conn->prepare("DELETE FROM shortener WHERE id = :id");
                    $stmt->bindParam(':id', $deletedId);
                    $stmt->execute();
                }
                // Clear the deleted_shortener session array
                unset($_SESSION['deleted_shortener']);
            }

            // remove items marked for deletion in the users table
            if (isset($_SESSION['deleted_users'])) {
                foreach ($_SESSION['deleted_users'] as $deletedId) {
                    $stmt = $conn->prepare("DELETE FROM users WHERE id = :id");
                    $stmt->bindParam(':id', $deletedId);
                    $stmt->execute();
                }
                // Clear the deleted_users session array
                unset($_SESSION['deleted_users']);
            }

            // refresh the page to show updated data
            header("Location: " . $_SERVER['PHP_SELF']);
            exit();
        }
    }

    // Prepare data for display
    $deletedShortenerIds = $_SESSION['deleted_shortener'] ?? []; // isset($_SESSION['deleted_shortener']) ? $_SESSION['deleted_shortener'] : [] (short form)
    $deletedUserIds = $_SESSION['deleted_users'] ?? []; // isset($_SESSION['deleted_users']) ? $_SESSION['deleted_users'] : [] (short form)

} catch (PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Data Management</title>
    <style>

        /* Global Styles */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Arial', sans-serif;
        }

        body {
            font-family: 'Arial', sans-serif;
            color: #fff;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: flex-start;
            min-height: 100vh;
            padding: 20px;
        }

        h1 {
            font-size: 2rem;
            margin-bottom: 1.5rem;
            text-align: center;
            color: #fff;
        }

        /* Table Container */
        table {
            width: 80%;
            margin: 20px 0;
            border-collapse: collapse;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 10px;
            backdrop-filter: blur(10px);
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
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

        tr.deleted {
            background-color: rgba(255, 0, 0, 0.2);
        }

        button {
            background-color: rgba(255, 255, 255, 0.0);
            color: #fff;
            border: 1px solid rgba(255, 255, 255, 0.2);
            line-height: 100%;

            border-radius: 8px;
            cursor: pointer;
            transition: background-color 0.3s;
        }

        button:hover {
            background-color: rgba(255, 255, 255, 0.2);
        }

        form {
            display: inline;
        }

        /* Update Button */
        form button {
            width: 100%;
            padding: 1rem;
            font-size: 1rem;
        }

        @media screen and (max-width: 768px) {
            table {
                width: 95%;
            }

            th, td {
                padding: 12px;
            }

            h1 {
                font-size: 1.5rem;
            }
        }

    </style>
</head>
<body>

<h1>Shortener Data</h1>
<table>
    <thead>
    <tr>
        <th>ID</th>
        <th>URL</th>
        <th>Short URL</th>
        <th>Action</th>
    </tr>
    </thead>
    <tbody>
    <?php foreach ($shortenerResults as $row): ?>
        <tr class="<?= in_array($row['id'], $deletedShortenerIds) ? 'deleted' : '' ?>">
            <td><?= htmlspecialchars($row['id']) ?></td>
            <td><?= htmlspecialchars($row['original_url']) ?></td>
            <td><?= htmlspecialchars($row['shortened_url']) ?></td>
            <td>
                <?php if (in_array($row['id'], $deletedShortenerIds)): ?>
                    <form method="post" style="display:inline;">
                        <button type="submit" name="restore_shortener" value="<?= $row['id'] ?>">Restore</button>
                    </form>
                <?php else: ?>
                    <form method="post" style="display:inline;">
                        <button type="submit" name="delete_shortener" value="<?= $row['id'] ?>">Delete</button>
                    </form>
                <?php endif; ?>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>

<h1>User Data</h1>
<table>
    <thead>
    <tr>
        <th>ID</th>
        <th>Username</th>
        <th>Email</th>
        <th>Created Links</th>
        <th>Action</th>
    </tr>
    </thead>
    <tbody>
    <?php foreach ($userResults as $row): ?>
        <tr class="<?= in_array($row['id'], $deletedUserIds) ? 'deleted' : '' ?>">
            <td><?= htmlspecialchars($row['id']) ?></td>
            <td><?= htmlspecialchars($row['username']) ?></td>
            <td><?= htmlspecialchars($row['email']) ?></td>
            <td><?= htmlspecialchars($row['created_links']) ?></td>
            <td>
                <?php if (in_array($row['id'], $deletedUserIds)): ?>
                    <form method="post" style="display:inline;">
                        <button type="submit" name="restore_user" value="<?= $row['id'] ?>">Restore</button>
                    </form>
                <?php else: ?>
                    <form method="post" style="display:inline;">
                        <button type="submit" name="delete_user" value="<?= $row['id'] ?>">Delete</button>
                    </form>
                <?php endif; ?>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>

<!-- Update button to apply changes -->
<form method="post">
    <button type="submit" name="update">Update</button>
</form>


<script type="text/javascript" src="../public/script/background.js"></script>
<script type="text/javascript" src="../public/script/password.js"></script>
<script type="text/javascript" src="../public/script/scroll.js"></script>

</body>
</html>