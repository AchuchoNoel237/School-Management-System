<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'teacher') {
    header('Location: ../login.php');
    exit();
}

include '../includes/config.php';

$teacher_id = $_SESSION['user_id'];
$course_id = isset($_GET['course_id']) ? intval($_GET['course_id']) : null;

// Get teacher's courses
$stmt = $pdo->prepare("SELECT * FROM courses WHERE teacher_id = ?");
$stmt->execute([$teacher_id]);
$courses = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get students for selected course
$students = [];
$selected_course = null;

if ($course_id) {
    // Verify the course belongs to this teacher
    $stmt = $pdo->prepare("SELECT * FROM courses WHERE id = ? AND teacher_id = ?");
    $stmt->execute([$course_id, $teacher_id]);
    $selected_course = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($selected_course) {
        // Get all approved students
        $stmt = $pdo->prepare("SELECT * FROM users WHERE role = 'student' AND account_status = 'approved'");
        $stmt->execute();
        $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

// Handle grade submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['submit_grades'])) {
    $student_id = intval($_POST['student_id']);
    $grade_value = floatval($_POST['grade_value']);
    $grade_type = $_POST['grade_type'];
    $description = trim($_POST['description']);
    
    try {
        $stmt = $pdo->prepare("INSERT INTO grades (student_id, course_id, grade_value, grade_type, description) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$student_id, $course_id, $grade_value, $grade_type, $description]);
        $success = "Grade added successfully!";
    } catch (PDOException $e) {
        $error = "Error adding grade: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gradebook - NgahTech Institute</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <nav class="navbar">
        <div class="nav-container">
            <a href="#" class="logo">NgahTech</a>
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
        <h1>Gradebook Management</h1>
        
        <?php if (isset($success)): ?>
            <div class="alert success"><?php echo $success; ?></div>
        <?php endif; ?>
        <?php if (isset($error)): ?>
            <div class="alert error"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <!-- Course Selection -->
        <div class="course-selection">
            <h2>Select a Course</h2>
            <div class="course-buttons">
                <?php foreach ($courses as $course): ?>
                    <a href="?course_id=<?php echo $course['id']; ?>" class="cta-button <?php echo ($course_id == $course['id']) ? 'active' : ''; ?>">
                        <?php echo htmlspecialchars($course['course_name']); ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Grade Entry Form -->
        <?php if ($selected_course): ?>
            <div class="grade-entry">
                <h2>Add Grade for <?php echo htmlspecialchars($selected_course['course_name']); ?></h2>
                <form method="POST" action="">
                    <input type="hidden" name="course_id" value="<?php echo $course_id; ?>">
                    
                    <div class="form-group">
                        <label>Student:</label>
                        <select name="student_id" required>
                            <option value="">Select Student</option>
                            <?php foreach ($students as $student): ?>
                                <option value="<?php echo $student['id']; ?>">
                                    <?php echo htmlspecialchars($student['full_name']); ?> (<?php echo htmlspecialchars($student['email']); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Grade:</label>
                        <input type="number" name="grade_value" min="0" max="100" step="0.01" required placeholder="0-100">
                    </div>
                    
                    <div class="form-group">
                        <label>Grade Type:</label>
                        <select name="grade_type" required>
                            <option value="">Select Type</option>
                            <option value="assignment">Assignment</option>
                            <option value="exam">Exam</option>
                            <option value="participation">Participation</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Description:</label>
                        <input type="text" name="description" placeholder="e.g., Midterm Exam, Homework 1">
                    </div>
                    
                    <button type="submit" name="submit_grades" class="cta-button">Add Grade</button>
                </form>
            </div>
        <?php endif; ?>
        
        <br>
        <a href="dashboard.php">&larr; Back to Dashboard</a>
    </div>

    <footer>
        <p>&copy; 2023 NgahTech Institute. All rights reserved.</p>
    </footer>
</body>
</html>