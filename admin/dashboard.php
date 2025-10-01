<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header('Location: ../login.php');
    exit();
}

include '../includes/config.php';

// Count pending users
$stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE account_status = 'pending'");
$stmt->execute();
$pending_count = $stmt->fetchColumn();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - NgahTech Institute</title>
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
        <h1>Admin Dashboard</h1>
        <p>Welcome, <?php echo $_SESSION['full_name']; ?>!</p>
        
        <div class="admin-stats">
            <div class="stat-card">
                <h3>Pending Approvals</h3>
                <p class="stat-number"><?php echo $pending_count; ?></p>
                <a href="approve_users.php" class="cta-button">Manage Approvals</a>
            </div>
        </div>
        
        <a href="../logout.php" class="logout-btn">Logout</a>
    </div>

    <footer>
        <p>&copy; 2023 NgahTech Institute. All rights reserved.</p>
    </footer>
</body>
</html>