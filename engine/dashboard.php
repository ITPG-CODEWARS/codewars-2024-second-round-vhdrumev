<?php
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

    // Handle delete/restore functionality
    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        if (isset($_POST['delete_shortener'])) {
            $idToDelete = $_POST['delete_shortener'];
            // Add ID to the deleted_shortener session array
            $_SESSION['deleted_shortener'][] = $idToDelete;
        } elseif (isset($_POST['restore_shortener'])) {
            $idToRestore = $_POST['restore_shortener'];
            // Remove ID from the deleted_shortener session array
            $_SESSION['deleted_shortener'] = array_diff($_SESSION['deleted_shortener'], [$idToRestore]);
        } elseif (isset($_POST['delete_user'])) {
            $idToDelete = $_POST['delete_user'];
            // Add ID to the deleted_users session array
            $_SESSION['deleted_users'][] = $idToDelete;
        } elseif (isset($_POST['restore_user'])) {
            $idToRestore = $_POST['restore_user'];
            // Remove ID from the deleted_users session array
            $_SESSION['deleted_users'] = array_diff($_SESSION['deleted_users'], [$idToRestore]);
        } elseif (isset($_POST['update'])) {
            // Delete items marked for deletion in the shortener table
            if (isset($_SESSION['deleted_shortener'])) {
                foreach ($_SESSION['deleted_shortener'] as $deletedId) {
                    $stmt = $conn->prepare("DELETE FROM shortener WHERE id = :id");
                    $stmt->bindParam(':id', $deletedId);
                    $stmt->execute();
                }
                // Clear the deleted_shortener session array
                unset($_SESSION['deleted_shortener']);
            }

            // Delete items marked for deletion in the users table
            if (isset($_SESSION['deleted_users'])) {
                foreach ($_SESSION['deleted_users'] as $deletedId) {
                    $stmt = $conn->prepare("DELETE FROM users WHERE id = :id");
                    $stmt->bindParam(':id', $deletedId);
                    $stmt->execute();
                }
                // Clear the deleted_users session array
                unset($_SESSION['deleted_users']);
            }

            // Refresh the page to show updated data
            header("Location: " . $_SERVER['PHP_SELF']);
            exit();
        }
    }

    // Prepare data for display
    $deletedShortenerIds = isset($_SESSION['deleted_shortener']) ? $_SESSION['deleted_shortener'] : [];
    $deletedUserIds = isset($_SESSION['deleted_users']) ? $_SESSION['deleted_users'] : [];

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
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        table, th, td {
            border: 1px solid black;
        }
        th, td {
            padding: 8px;
            text-align: left;
        }
        .deleted {
            background-color: red;
            color: white;
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

</body>
</html>
