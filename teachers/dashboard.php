<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'teacher') {
    header('Location: ../login.php');
    exit();
}

include '../includes/config.php';

// Get teacher's courses
$teacher_id = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT * FROM courses WHERE teacher_id = ?");
$stmt->execute([$teacher_id]);
$courses = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teacher Dashboard - NgahTech Institute</title>
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
                <li><a href="grades.php">Gradebook</a></li>
                <li><a href="attendance.php" class="active">Attendance</a></li>
                <li><a href="assignments.php" class="active">Assignments</a></li>
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
        <h1>Teacher Dashboard</h1>
        <p>Welcome, <?php echo $_SESSION['full_name']; ?>!</p>
        
        <div class="teacher-courses">
            <h2>Your Courses</h2>
            <?php if (count($courses) > 0): ?>
                <div class="course-grid">
                    <?php foreach ($courses as $course): ?>
                        <div class="course-card">
                            <h3><?php echo htmlspecialchars($course['course_name']); ?></h3>
                            <p>Code: <?php echo htmlspecialchars($course['course_code']); ?></p>
                            <a href="grades.php?course_id=<?php echo $course['id']; ?>" class="cta-button">
                                Manage Grades
                            </a>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p class="no-courses">No courses assigned yet.</p>
            <?php endif; ?>
        </div>
        
        <a href="../logout.php" class="logout-btn">Logout</a>
    </div>

    <footer>
        <p>&copy; 2023 NgahTech Institute. All rights reserved.</p>
    </footer>
</body>
</html>