<?php
session_start();
// Redirect if not admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header('Location: ../login.php');
    exit();
}

include '../includes/config.php';

// Handle approval or rejection
if (isset($_GET['action']) && isset($_GET['user_id'])) {
    $user_id = intval($_GET['user_id']);
    $action = $_GET['action'];
    
    if ($action === 'approve') {
        $stmt = $pdo->prepare("UPDATE users SET account_status = 'approved' WHERE id = ?");
        $stmt->execute([$user_id]);
        $message = "User approved successfully!";
    } elseif ($action === 'reject') {
        $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $message = "User rejected and removed!";
    }
}

// Fetch all pending users
$stmt = $pdo->prepare("SELECT * FROM users WHERE account_status = 'pending' ORDER BY created_at DESC");
$stmt->execute();
$pending_users = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Approve Users - NgahTech Institute</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <nav class="navbar">
        <div class="nav-container">
            <a href="../index.php" class="logo">NgahTech</a>
            <ul class="nav-menu">
                <!-- <li><a href="../index.php">Home</a></li> -->
                <li><a href="dashboard.php">Dashboard</a></li>
                <li><a href="approve_users.php">Approvals</a></li>
                <li><a href="attendance.php">Attendance</a></li>
                <li><a href="broadcast.php" class="active">Broadcast</a></li>
                <li><a href="courses.php" class="active">Courses</a></li>
                <li><a href="analytics.php" class="active">Analytics</a></li>
                <li><a href="../notifications.php">
    Notifications 
    <?php
    // Add this PHP code to show unread count
    if (isset($_SESSION['user_id'])) {
        include '../includes/config.php';
        $stmt = $pdo->prepare("
            SELECT COUNT(*) 
            FROM announcement_receipts 
            WHERE recipient_id = ? AND read_at IS NULL
        ");
        $stmt->execute([$_SESSION['user_id']]);
        $unread_count = $stmt->fetchColumn();
        
        if ($unread_count > 0) {
            echo '<span class="notification-badge">' . $unread_count . '</span>';
        }
    }
    ?>
</a></li>
                <li><a href="../logout.php">Logout</a></li>
            </ul>
        </div>
    </nav>

    <div class="dashboard-container">
        <h1>Pending User Approvals</h1>
        
        <?php if (isset($message)): ?>
            <div class="alert success"><?php echo $message; ?></div>
        <?php endif; ?>
        
        <?php if (count($pending_users) > 0): ?>
            <table class="users-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Signup Date</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($pending_users as $user): ?>
                        <tr>
                            <td><?php echo $user['id']; ?></td>
                            <td><?php echo htmlspecialchars($user['full_name']); ?></td>
                            <td><?php echo htmlspecialchars($user['email']); ?></td>
                            <td><?php echo ucfirst($user['role']); ?></td>
                            <td><?php echo date('M j, Y', strtotime($user['created_at'])); ?></td>
                            <td class="actions">
                                <a href="?action=approve&user_id=<?php echo $user['id']; ?>" class="btn approve">Approve</a>
                                <a href="?action=reject&user_id=<?php echo $user['id']; ?>" class="btn reject" onclick="return confirm('Are you sure you want to reject this user?')">Reject</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p class="no-pending">No pending users for approval. 🎉</p>
        <?php endif; ?>
        
        <br>
        <a href="dashboard.php">&larr; Back to Dashboard</a>
    </div>

    <footer>
        <p>&copy; 2023 NgahTech Institute. All rights reserved.</p>
    </footer>
</body>
</html>