<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'teacher') {
    header('Location: ../login.php');
    exit();
}

include '../includes/config.php';

$teacher_id = $_SESSION['user_id'];
$success = '';
$error = '';

// Get teacher's courses
$stmt = $pdo->prepare("SELECT * FROM courses WHERE teacher_id = ?");
$stmt->execute([$teacher_id]);
$courses = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Handle assignment creation
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['create_assignment'])) {
    $course_id = intval($_POST['course_id']);
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $due_date = $_POST['due_date'];
    $max_points = intval($_POST['max_points']);

    // Verify course belongs to teacher
    $stmt = $pdo->prepare("SELECT id FROM courses WHERE id = ? AND teacher_id = ?");
    $stmt->execute([$course_id, $teacher_id]);
    
    if ($stmt->fetch()) {
        try {
            $stmt = $pdo->prepare("INSERT INTO assignments (course_id, teacher_id, title, description, due_date, max_points) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$course_id, $teacher_id, $title, $description, $due_date, $max_points]);
            $success = "Assignment created successfully!";
        } catch (PDOException $e) {
            $error = "Error creating assignment: " . $e->getMessage();
        }
    } else {
        $error = "Invalid course selection";
    }
}

// Get teacher's assignments
$stmt = $pdo->prepare("
    SELECT a.*, c.course_name, c.course_code, 
           COUNT(s.id) as submission_count
    FROM assignments a
    JOIN courses c ON a.course_id = c.id
    LEFT JOIN assignment_submissions s ON a.id = s.assignment_id
    WHERE a.teacher_id = ?
    GROUP BY a.id
    ORDER BY a.due_date DESC
");
$stmt->execute([$teacher_id]);
$assignments = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Assignments - NgahTech Institute</title>
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
                <li><a href="attendance.php">Attendance</a></li>
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
        <h1>Assignment Management</h1>
        
        <?php if ($success): ?>
            <div class="alert success"><?php echo $success; ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert error"><?php echo $error; ?></div>
        <?php endif; ?>

        <!-- Create Assignment Form -->
        <div class="create-assignment">
            <h2>Create New Assignment</h2>
            <form method="POST" action="">
                <div class="form-group">
                    <label>Course:</label>
                    <select name="course_id" required>
                        <option value="">Select Course</option>
                        <?php foreach ($courses as $course): ?>
                            <option value="<?php echo $course['id']; ?>">
                                <?php echo htmlspecialchars($course['course_name']); ?> (<?php echo htmlspecialchars($course['course_code']); ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>Assignment Title:</label>
                    <input type="text" name="title" required placeholder="e.g., Midterm Project">
                </div>
                
                <div class="form-group">
                    <label>Description:</label>
                    <textarea name="description" rows="4" placeholder="Assignment instructions and requirements..."></textarea>
                </div>
                
                <div class="form-group">
                    <label>Due Date:</label>
                    <input type="datetime-local" name="due_date" required>
                </div>
                
                <div class="form-group">
                    <label>Maximum Points:</label>
                    <input type="number" name="max_points" value="100" min="1" required>
                </div>
                
                <button type="submit" name="create_assignment" class="cta-button">Create Assignment</button>
            </form>
        </div>

        <!-- Assignments List -->
        <div class="assignments-list">
            <h2>Your Assignments</h2>
            <?php if (count($assignments) > 0): ?>
                <div class="assignment-grid">
                    <?php foreach ($assignments as $assignment): ?>
                        <div class="assignment-card">
                            <h3><?php echo htmlspecialchars($assignment['title']); ?></h3>
                            <p class="course-info"><?php echo htmlspecialchars($assignment['course_code']); ?></p>
                            <p class="due-date">Due: <?php echo date('M j, Y g:i A', strtotime($assignment['due_date'])); ?></p>
                            <p class="points">Max Points: <?php echo $assignment['max_points']; ?></p>
                            <p class="submissions">Submissions: <?php echo $assignment['submission_count']; ?></p>
                            
                            <div class="assignment-actions">
                                <a href="view_submissions.php?assignment_id=<?php echo $assignment['id']; ?>" class="btn view-btn">
                                    View Submissions
                                </a>
                                <a href="grade_assignment.php?assignment_id=<?php echo $assignment['id']; ?>" class="btn grade-btn">
                                    Grade
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p class="no-data">No assignments created yet.</p>
            <?php endif; ?>
        </div>
    </div>

    <footer>
        <p>&copy; 2023 NgahTech Institute. All rights reserved.</p>
    </footer>
</body>
</html>