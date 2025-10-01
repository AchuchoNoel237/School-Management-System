<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

include 'includes/config.php';

$user_id = $_SESSION['user_id'];

// Mark announcement as read if requested
if (isset($_GET['mark_as_read']) && is_numeric($_GET['mark_as_read'])) {
    $announcement_id = intval($_GET['mark_as_read']);
    
    $stmt = $pdo->prepare("
        UPDATE announcement_receipts 
        SET read_at = NOW() 
        WHERE announcement_id = ? AND recipient_id = ? AND read_at IS NULL
    ");
    $stmt->execute([$announcement_id, $user_id]);
    
    // Redirect to avoid resubmission on refresh
    header('Location: notifications.php');
    exit();
}

// Mark all as read
if (isset($_GET['mark_all_read'])) {
    $stmt = $pdo->prepare("
        UPDATE announcement_receipts 
        SET read_at = NOW() 
        WHERE recipient_id = ? AND read_at IS NULL
    ");
    $stmt->execute([$user_id]);
    
    header('Location: notifications.php');
    exit();
}

// Get user's announcements
$stmt = $pdo->prepare("
    SELECT 
        a.*,
        u.full_name as sender_name,
        r.read_at,
        CASE 
            WHEN r.read_at IS NULL THEN 'unread'
            ELSE 'read'
        END as status
    FROM announcement_receipts r
    JOIN announcements a ON r.announcement_id = a.id
    JOIN users u ON a.sender_id = u.id
    WHERE r.recipient_id = ?
    ORDER BY a.created_at DESC
");
$stmt->execute([$user_id]);
$announcements = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Count unread announcements
$unread_count = 0;
foreach ($announcements as $announcement) {
    if ($announcement['read_at'] === null) {
        $unread_count++;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifications - NgahTech Institute</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <nav class="navbar">
        <div class="nav-container">
            <a href="index.php" class="logo">NgahTech</a>
            <ul class="nav-menu">
                <?php if ($_SESSION['role'] == 'student'): ?>
                    <li><a href="students/dashboard.php">Dashboard</a></li>
                    <li><a href="students/grades.php">Grades</a></li>
                    <li><a href="students/assignments.php">Assignments</a></li>
                <?php elseif ($_SESSION['role'] == 'teacher'): ?>
                    <li><a href="teachers/dashboard.php">Dashboard</a></li>
                    <li><a href="teachers/grades.php">Gradebook</a></li>
                    <li><a href="teachers/attendance.php">Attendance</a></li>
                    <li><a href="teachers/assignments.php">Assignments</a></li>
                <?php elseif ($_SESSION['role'] == 'admin'): ?>
                    <li><a href="admin/dashboard.php" class="active">Dashboard</a></li>
                    <li><a href="admin/approve_users.php" class="active">Approvals</a></li>
                    <li><a href="admin/attendance.php" class="active">Attendance</a></li>
                    <li><a href="admin/broadcast.php" class="active">Broadcast</a></li>
                    <li><a href="admin/courses.php" class="active">Courses</a></li>
                    <li><a href="admin/analytics.php" class="active">Analytics</a></li>
                <?php endif; ?>
                <li><a href="notifications.php" class="active">
                    Notifications 
                    <?php if ($unread_count > 0): ?>
                        <span class="notification-badge"><?php echo $unread_count; ?></span>
                    <?php endif; ?>
                </a></li>
                <li><a href="logout.php">Logout</a></li>
            </ul>
        </div>
    </nav>

    <div class="dashboard-container">
        <div class="notifications-header">
            <h1>My Notifications</h1>
            <div class="notification-actions">
                <?php if ($unread_count > 0): ?>
                    <a href="?mark_all_read=1" class="cta-button">
                        <i class="fas fa-check-double"></i> Mark All as Read
                    </a>
                <?php endif; ?>
                <span class="unread-count">
                    <?php echo $unread_count; ?> unread of <?php echo count($announcements); ?> total
                </span>
            </div>
        </div>

        <?php if (count($announcements) > 0): ?>
            <div class="notifications-list">
                <?php foreach ($announcements as $announcement): ?>
                    <div class="notification-item <?php echo $announcement['status']; ?>">
                        <div class="notification-header">
                            <h3><?php echo htmlspecialchars($announcement['title']); ?></h3>
                            <div class="notification-meta">
                                <span class="sender">From: <?php echo htmlspecialchars($announcement['sender_name']); ?></span>
                                <span class="date"><?php echo date('M j, Y g:i A', strtotime($announcement['created_at'])); ?></span>
                                <?php if ($announcement['status'] == 'unread'): ?>
                                    <span class="status-badge unread">New</span>
                                <?php else: ?>
                                    <span class="status-badge read">Read</span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="notification-content">
                            <p><?php echo nl2br(htmlspecialchars($announcement['message'])); ?></p>
                        </div>

                        <div class="notification-footer">
                            <?php if ($announcement['status'] == 'unread'): ?>
                                <a href="?mark_as_read=<?php echo $announcement['id']; ?>" class="mark-read-btn">
                                    <i class="fas fa-check"></i> Mark as Read
                                </a>
                            <?php else: ?>
                                <span class="read-time">Read on: <?php echo date('M j, Y g:i A', strtotime($announcement['read_at'])); ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="no-notifications">
                <i class="fas fa-bell-slash fa-3x"></i>
                <h2>No notifications yet</h2>
                <p>You'll see important announcements here when they're sent by administrators.</p>
            </div>
        <?php endif; ?>
    </div>

    <footer>
        <p>&copy; 2023 NgahTech Institute. All rights reserved.</p>
    </footer>
</body>
</html>