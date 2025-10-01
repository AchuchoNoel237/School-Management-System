<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'teacher') {
    header('Location: ../login.php');
    exit();
}

include '../includes/config.php';

$teacher_id = $_SESSION['user_id'];
$submission_id = isset($_GET['submission_id']) ? intval($_GET['submission_id']) : null;
$success = '';
$error = '';

// Get submission details and verify teacher ownership
$submission = null;
$assignment = null;

if ($submission_id) {
    $stmt = $pdo->prepare("
        SELECT s.*, a.*, c.course_name, c.course_code, u.full_name as student_name, u.email as student_email
        FROM assignment_submissions s
        JOIN assignments a ON s.assignment_id = a.id
        JOIN courses c ON a.course_id = c.id
        JOIN users u ON s.student_id = u.id
        WHERE s.id = ? AND a.teacher_id = ?
    ");
    $stmt->execute([$submission_id, $teacher_id]);
    $submission = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($submission) {
        $assignment = [
            'id' => $submission['assignment_id'],
            'title' => $submission['title'],
            'max_points' => $submission['max_points'],
            'course_code' => $submission['course_code'],
            'course_name' => $submission['course_name']
        ];
    }
}

// Redirect if no valid submission
if (!$submission) {
    header('Location: assignments.php');
    exit();
}

// Handle grade submission
// Handle grade submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['submit_grade'])) {
    $grade = floatval($_POST['grade']);
    $feedback = trim($_POST['feedback']);
    
    // Validate grade
    if ($grade < 0 || $grade > $submission['max_points']) {
        $error = "Grade must be between 0 and " . $submission['max_points'];
    } else {
        try {
            // Update submission with grade and feedback
            $stmt = $pdo->prepare("
                UPDATE assignment_submissions 
                SET grade = ?, feedback = ?, graded_at = NOW() 
                WHERE id = ?
            ");
            $stmt->execute([$grade, $feedback, $submission_id]);
            
            // Also add to grades table for consistency
            $percentage = round(($grade / $submission['max_points']) * 100, 2);
            
            $stmt = $pdo->prepare("
                INSERT INTO grades (student_id, course_id, grade_value, grade_type, description)
                VALUES (?, ?, ?, 'assignment', ?)
                ON DUPLICATE KEY UPDATE grade_value = ?, description = ?
            ");
            $desc = "Assignment: " . $submission['title'];
            $stmt->execute([
                $submission['student_id'],
                $submission['course_id'],
                $percentage,
                $desc,
                $percentage,
                $desc
            ]);
            
            $success = "Grade submitted successfully!";
            
            // Refresh submission data WITH ALL JOINS
            $stmt = $pdo->prepare("
                SELECT s.*, a.*, c.course_name, c.course_code, u.full_name as student_name, u.email as student_email
                FROM assignment_submissions s
                JOIN assignments a ON s.assignment_id = a.id
                JOIN courses c ON a.course_id = c.id
                JOIN users u ON s.student_id = u.id
                WHERE s.id = ?
            ");
            $stmt->execute([$submission_id]);
            $submission = $stmt->fetch(PDO::FETCH_ASSOC);
            
        } catch (PDOException $e) {
            $error = "Error submitting grade: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Grade Submission - NgahTech Institute</title>
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
            <a href="view_submissions.php?assignment_id=<?php echo $submission['assignment_id']; ?>" class="back-button">
                <i class="fas fa-arrow-left"></i> Back to Submissions
            </a>
        </div>

        <h1>Grade Submission</h1>
        
        <?php if ($success): ?>
            <div class="alert success"><?php echo $success; ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert error"><?php echo $error; ?></div>
        <?php endif; ?>

        <!-- Submission Details -->
        <div class="submission-details-card">
            <h2>Submission Details</h2>
            <div class="detail-grid">
                <div class="detail-item">
                    <label>Student:</label>
                    <span><?php echo htmlspecialchars($submission['student_name']); ?></span>
                </div>
                <div class="detail-item">
                    <label>Email:</label>
                    <span><?php echo htmlspecialchars($submission['student_email']); ?></span>
                </div>
                <div class="detail-item">
                    <label>Assignment:</label>
                    <span><?php echo htmlspecialchars($submission['title']); ?></span>
                </div>
                <div class="detail-item">
                    <label>Course:</label>
                    <span><?php echo htmlspecialchars($submission['course_code'] . ' - ' . $submission['course_name']); ?></span>
                </div>
                <div class="detail-item">
                    <label>Max Points:</label>
                    <span><?php echo $submission['max_points']; ?></span>
                </div>
                <div class="detail-item">
                    <label>Submitted:</label>
                    <span><?php echo date('M j, Y g:i A', strtotime($submission['submitted_at'])); ?></span>
                </div>
            </div>
        </div>

        <!-- Submission Content -->
        <div class="submission-content">
            <?php if ($submission['submitted_file']): ?>
                <div class="content-section">
                    <h3>Submitted File</h3>
                    <div class="file-download">
                        <i class="fas fa-file fa-2x"></i>
                        <a href="../uploads/assignments/<?php echo htmlspecialchars($submission['submitted_file']); ?>" 
                           download class="cta-button">
                            Download File
                        </a>
                        <span class="file-info"><?php echo htmlspecialchars($submission['submitted_file']); ?></span>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($submission['submission_text']): ?>
                <div class="content-section">
                    <h3>Text Submission</h3>
                    <div class="text-content">
                        <?php echo nl2br(htmlspecialchars($submission['submission_text'])); ?>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (!$submission['submitted_file'] && !$submission['submission_text']): ?>
                <div class="content-section">
                    <div class="no-content">
                        <i class="fas fa-exclamation-triangle fa-2x"></i>
                        <p>No submission content provided by student.</p>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <!-- Grading Form -->
        <div class="grading-form">
            <h2>Grading</h2>
            <form method="POST" action="">
                <div class="form-row">
                    <div class="form-group">
                        <label for="grade">Grade (0 - <?php echo $submission['max_points']; ?>):</label>
                        <input type="number" id="grade" name="grade" 
                               min="0" max="<?php echo $submission['max_points']; ?>" 
                               step="0.1" value="<?php echo $submission['grade'] !== null ? $submission['grade'] : ''; ?>"
                               required>
                        <div class="grade-preview">
                            <span id="grade-percentage">0%</span>
                            <span id="grade-letter">F</span>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="feedback">Feedback:</label>
                        <textarea id="feedback" name="feedback" rows="6" 
                                  placeholder="Provide constructive feedback for the student..."><?php echo htmlspecialchars($submission['feedback'] ?? ''); ?></textarea>
                    </div>
                </div>

                <div class="form-actions">
                    <button type="submit" name="submit_grade" class="cta-button large">
                        <i class="fas fa-check"></i> Submit Grade
                    </button>
                    
                    <?php if ($submission['grade'] !== null): ?>
                        <span class="last-graded">
                            Last graded: <?php echo date('M j, Y g:i A', strtotime($submission['graded_at'])); ?>
                        </span>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <!-- Grade History -->
        <?php if ($submission['grade'] !== null): ?>
            <div class="grade-history">
                <h3>Current Grade</h3>
                <div class="current-grade">
                    <div class="grade-display">
                        <span class="grade-value"><?php echo $submission['grade']; ?>/<?php echo $submission['max_points']; ?></span>
                        <span class="grade-percentage">(<?php echo round(($submission['grade'] / $submission['max_points']) * 100, 1); ?>%)</span>
                    </div>
                    <?php if ($submission['feedback']): ?>
                        <div class="current-feedback">
                            <h4>Feedback:</h4>
                            <p><?php echo nl2br(htmlspecialchars($submission['feedback'])); ?></p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <script>
    // Real-time grade calculation
    const gradeInput = document.getElementById('grade');
    const maxPoints = <?php echo $submission['max_points']; ?>;
    const gradePercentage = document.getElementById('grade-percentage');
    const gradeLetter = document.getElementById('grade-letter');

    function updateGradePreview() {
        const grade = parseFloat(gradeInput.value) || 0;
        const percentage = (grade / maxPoints) * 100;
        
        gradePercentage.textContent = percentage.toFixed(1) + '%';
        
        // Determine letter grade
        let letter = 'F';
        if (percentage >= 90) letter = 'A';
        else if (percentage >= 80) letter = 'B';
        else if (percentage >= 70) letter = 'C';
        else if (percentage >= 60) letter = 'D';
        
        gradeLetter.textContent = letter;
        gradeLetter.className = 'grade-' + letter;
    }

    gradeInput.addEventListener('input', updateGradePreview);
    gradeInput.addEventListener('change', updateGradePreview);
    
    // Initialize on page load
    updateGradePreview();
    </script>

    <footer>
        <p>&copy; 2023 NgahTech Institute. All rights reserved.</p>
    </footer>
</body>
</html>