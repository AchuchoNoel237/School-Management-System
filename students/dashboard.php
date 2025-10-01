<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'student') {
    header('Location: ../login.php');
    exit();
}

include '../includes/config.php';

$student_id = $_SESSION['user_id'];

// Get student's grades with course information
$stmt = $pdo->prepare("
    SELECT g.*, c.course_name, c.course_code, u.full_name as teacher_name 
    FROM grades g 
    JOIN courses c ON g.course_id = c.id 
    JOIN users u ON c.teacher_id = u.id 
    WHERE g.student_id = ? 
    ORDER BY g.created_at DESC
");
$stmt->execute([$student_id]);
$grades = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate overall average
$total_grades = 0;
$grade_count = count($grades);
foreach ($grades as $grade) {
    $total_grades += $grade['grade_value'];
}
$average_grade = $grade_count > 0 ? round($total_grades / $grade_count, 2) : 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Dashboard - NgahTech Institute</title>
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
                <li><a href="grades.php">Grades</a></li>
                <li><a href="assignments.php">Assignments</a></li>
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
        <h1>Welcome, <?php echo $_SESSION['full_name']; ?>! 👨‍🎓</h1>
        
        <!-- Grades Summary -->
        <div class="grades-summary">
            <div class="summary-card">
                <h3>Overall Average</h3>
                <div class="grade-number"><?php echo $average_grade; ?>%</div>
                <p>Based on <?php echo $grade_count; ?> graded assignments</p>
            </div>
        </div>

        <!-- Grades Table -->
        <div class="grades-section">
            <h2>Your Grades</h2>
            <?php if ($grade_count > 0): ?>
                <table class="grades-table">
                    <thead>
                        <tr>
                            <th>Course</th>
                            <th>Assignment</th>
                            <th>Grade</th>
                            <th>Type</th>
                            <th>Teacher</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($grades as $grade): ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($grade['course_code']); ?></strong><br>
                                    <small><?php echo htmlspecialchars($grade['course_name']); ?></small>
                                </td>
                                <td><?php echo htmlspecialchars($grade['description'] ?: 'N/A'); ?></td>
                                <td>
                                    <span class="grade-badge <?php echo getGradeClass($grade['grade_value']); ?>">
                                        <?php echo $grade['grade_value']; ?>%
                                    </span>
                                </td>
                                <td><?php echo ucfirst($grade['grade_type']); ?></td>
                                <td><?php echo htmlspecialchars($grade['teacher_name']); ?></td>
                                <td><?php echo date('M j, Y', strtotime($grade['created_at'])); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="no-grades">
                    <i class="fas fa-book-open fa-3x"></i>
                    <h3>No grades yet</h3>
                    <p>Your grades will appear here once your teachers start grading your work.</p>
                </div>
            <?php endif; ?>
        </div>
        
        <a href="../logout.php" class="logout-btn">Logout</a>
    </div>

    <footer>
        <p>&copy; 2023 NgahTech Institute. All rights reserved.</p>
    </footer>
</body>
</html>

<?php
// Helper function to determine grade color
function getGradeClass($grade) {
    if ($grade >= 90) return 'grade-excellent';
    if ($grade >= 80) return 'grade-good';
    if ($grade >= 70) return 'grade-average';
    if ($grade >= 60) return 'grade-poor';
    return 'grade-fail';
}
?>