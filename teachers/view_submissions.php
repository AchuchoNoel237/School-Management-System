<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'teacher') {
    header('Location: ../login.php');
    exit();
}

include '../includes/config.php';

$teacher_id = $_SESSION['user_id'];
$assignment_id = isset($_GET['assignment_id']) ? intval($_GET['assignment_id']) : null;

// Get assignment details and verify teacher ownership
$assignment = null;
$submissions = [];

if ($assignment_id) {
    $stmt = $pdo->prepare("
        SELECT a.*, c.course_name, c.course_code 
        FROM assignments a 
        JOIN courses c ON a.course_id = c.id 
        WHERE a.id = ? AND a.teacher_id = ?
    ");
    $stmt->execute([$assignment_id, $teacher_id]);
    $assignment = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($assignment) {
        // Get all submissions for this assignment
        $stmt = $pdo->prepare("
            SELECT s.*, u.full_name as student_name, u.email as student_email
            FROM assignment_submissions s
            JOIN users u ON s.student_id = u.id
            WHERE s.assignment_id = ?
            ORDER BY s.submitted_at DESC
        ");
        $stmt->execute([$assignment_id]);
        $submissions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

// Redirect if no valid assignment
if (!$assignment) {
    header('Location: assignments.php');
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Submissions - NgahTech Institute</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <nav class="navbar">
        <div class="nav-container">
            <a href="#" class="logo">NgahTech</a>
            <ul class="nav-menu">
                <li><a href="../index.php">Home</a></li>
                <li><a href="dashboard.php">Dashboard</a></li>
                <li><a href="grades.php">Gradebook</a></li>
                <li><a href="attendance.php">Attendance</a></li>
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
        <div class="back-nav">
            <a href="assignments.php" class="back-button">
                <i class="fas fa-arrow-left"></i> Back to Assignments
            </a>
        </div>

        <h1>Submissions for: <?php echo htmlspecialchars($assignment['title']); ?></h1>
        <p class="course-info">Course: <?php echo htmlspecialchars($assignment['course_code'] . ' - ' . $assignment['course_name']); ?></p>
        <p class="due-date">Due: <?php echo date('M j, Y g:i A', strtotime($assignment['due_date'])); ?></p>
        
        <div class="submission-stats">
            <div class="stat-card">
                <div class="stat-icon" style="background: #17a2b8;">
                    <i class="fas fa-paper-plane"></i>
                </div>
                <div class="stat-info">
                    <h3>Total Submissions</h3>
                    <div class="stat-number"><?php echo count($submissions); ?></div>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon" style="background: #6f42c1;">
                    <i class="fas fa-users"></i>
                </div>
                <div class="stat-info">
                    <h3>Total Students</h3>
                    <div class="stat-number">
                        <?php
                        $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE role = 'student' AND account_status = 'approved'");
                        $stmt->execute();
                        echo $stmt->fetchColumn();
                        ?>
                    </div>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon" style="background: <?php echo (count($submissions) > 0) ? '#28a745' : '#dc3545'; ?>;">
                    <i class="fas fa-chart-pie"></i>
                </div>
                <div class="stat-info">
                    <h3>Submission Rate</h3>
                    <div class="stat-number">
                        <?php
                        $total_students = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'student' AND account_status = 'approved'")->fetchColumn();
                        $submission_rate = $total_students > 0 ? round((count($submissions) / $total_students) * 100, 1) : 0;
                        echo $submission_rate . '%';
                        ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Submissions List -->
        <div class="submissions-list">
            <h2>Student Submissions</h2>
            
            <?php if (count($submissions) > 0): ?>
                <div class="submissions-grid">
                    <?php foreach ($submissions as $submission): ?>
                        <div class="submission-card <?php echo $submission['grade'] !== null ? 'graded' : 'ungraded'; ?>">
                            <div class="submission-header">
                                <h3><?php echo htmlspecialchars($submission['student_name']); ?></h3>
                                <span class="status-badge <?php echo $submission['grade'] !== null ? 'graded' : 'ungraded'; ?>">
                                    <?php echo $submission['grade'] !== null ? 'Graded' : 'Pending'; ?>
                                </span>
                            </div>
                            
                            <div class="submission-details">
                                <p class="email"><?php echo htmlspecialchars($submission['student_email']); ?></p>
                                <p class="submitted">Submitted: <?php echo date('M j, Y g:i A', strtotime($submission['submitted_at'])); ?></p>
                                
                                <?php if ($submission['submitted_file']): ?>
                                    <p class="file">
                                        <i class="fas fa-file"></i> 
                                        <a href="../uploads/assignments/<?php echo htmlspecialchars($submission['submitted_file']); ?>" 
                                           download class="file-link">
                                            Download Submitted File
                                        </a>
                                    </p>
                                <?php endif; ?>
                                
                                <?php if ($submission['submission_text']): ?>
                                    <div class="text-submission">
                                        <h4>Text Submission:</h4>
                                        <div class="submission-text">
                                            <?php echo nl2br(htmlspecialchars($submission['submission_text'])); ?>
                                        </div>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if ($submission['grade'] !== null): ?>
                                    <div class="grade-info">
                                        <h4>Grading Details:</h4>
                                        <p class="grade">Grade: <strong><?php echo $submission['grade']; ?>/<?php echo $assignment['max_points']; ?></strong></p>
                                        <p class="percentage">Percentage: <strong><?php echo round(($submission['grade'] / $assignment['max_points']) * 100, 1); ?>%</strong></p>
                                        <?php if ($submission['feedback']): ?>
                                            <p class="feedback">Feedback: <?php echo nl2br(htmlspecialchars($submission['feedback'])); ?></p>
                                        <?php endif; ?>
                                        <p class="graded-date">Graded on: <?php echo date('M j, Y g:i A', strtotime($submission['graded_at'])); ?></p>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="submission-actions">
                                <a href="grade_submission.php?submission_id=<?php echo $submission['id']; ?>" class="cta-button">
                                    <?php echo $submission['grade'] !== null ? 'Update Grade' : 'Grade Submission'; ?>
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="no-submissions">
                    <i class="fas fa-inbox fa-3x"></i>
                    <h3>No submissions yet</h3>
                    <p>Students haven't submitted any work for this assignment yet.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <footer>
        <p>&copy; 2023 NgahTech Institute. All rights reserved.</p>
    </footer>
</body>
</html>