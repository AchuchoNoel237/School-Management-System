<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'teacher') {
    header('Location: ../login.php');
    exit();
}

include '../includes/config.php';

$teacher_id = $_SESSION['user_id'];
$course_id = isset($_GET['course_id']) ? intval($_GET['course_id']) : null;
$attendance_date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');

// Get teacher's courses
$stmt = $pdo->prepare("SELECT * FROM courses WHERE teacher_id = ?");
$stmt->execute([$teacher_id]);
$courses = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get students and attendance for selected course
$students = [];
$selected_course = null;
$existing_attendance = [];

if ($course_id) {
    // Verify course belongs to teacher
    $stmt = $pdo->prepare("SELECT * FROM courses WHERE id = ? AND teacher_id = ?");
    $stmt->execute([$course_id, $teacher_id]);
    $selected_course = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($selected_course) {
        // Get approved students
        $stmt = $pdo->prepare("SELECT * FROM users WHERE role = 'student' AND account_status = 'approved'");
        $stmt->execute();
        $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get existing attendance for this date
        $stmt = $pdo->prepare("SELECT * FROM attendance WHERE course_id = ? AND attendance_date = ?");
        $stmt->execute([$course_id, $attendance_date]);
        $existing_attendance = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

// Process attendance submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['submit_attendance'])) {
    $attendance_date = $_POST['attendance_date'];
    $course_id = intval($_POST['course_id']);
    
    foreach ($_POST['attendance'] as $student_id => $status) {
        $student_id = intval($student_id);
        $notes = trim($_POST['notes'][$student_id]);
        
        try {
            // Check if attendance already exists
            $stmt = $pdo->prepare("SELECT id FROM attendance WHERE student_id = ? AND course_id = ? AND attendance_date = ?");
            $stmt->execute([$student_id, $course_id, $attendance_date]);
            $existing = $stmt->fetch();
            
            if ($existing) {
                // Update existing
                $stmt = $pdo->prepare("UPDATE attendance SET status = ?, notes = ?, recorded_by = ? WHERE id = ?");
                $stmt->execute([$status, $notes, $teacher_id, $existing['id']]);
            } else {
                // Insert new
                $stmt = $pdo->prepare("INSERT INTO attendance (student_id, course_id, attendance_date, status, notes, recorded_by) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->execute([$student_id, $course_id, $attendance_date, $status, $notes, $teacher_id]);
            }
        } catch (PDOException $e) {
            $error = "Error saving attendance: " . $e->getMessage();
        }
    }
    
    $success = "Attendance saved successfully!";
    // Refresh existing attendance data
    $stmt = $pdo->prepare("SELECT * FROM attendance WHERE course_id = ? AND attendance_date = ?");
    $stmt->execute([$course_id, $attendance_date]);
    $existing_attendance = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Attendance - NgahTech Institute</title>
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
        <h1>Attendance Management</h1>
        
        <?php if (isset($success)): ?>
            <div class="alert success"><?php echo $success; ?></div>
        <?php endif; ?>
        <?php if (isset($error)): ?>
            <div class="alert error"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <!-- Course and Date Selection -->
        <div class="attendance-selection">
            <form method="GET" action="">
                <div class="form-group">
                    <label>Select Course:</label>
                    <select name="course_id" onchange="this.form.submit()" required>
                        <option value="">Choose Course</option>
                        <?php foreach ($courses as $course): ?>
                            <option value="<?php echo $course['id']; ?>" <?php echo ($course_id == $course['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($course['course_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>Date:</label>
                    <input type="date" name="date" value="<?php echo $attendance_date; ?>" onchange="this.form.submit()">
                </div>
            </form>
        </div>

        <!-- Attendance Form -->
        <?php if ($selected_course && count($students) > 0): ?>
            <form method="POST" action="">
                <input type="hidden" name="course_id" value="<?php echo $course_id; ?>">
                <input type="hidden" name="attendance_date" value="<?php echo $attendance_date; ?>">
                
                <h2>Attendance for <?php echo htmlspecialchars($selected_course['course_name']); ?> on <?php echo date('M j, Y', strtotime($attendance_date)); ?></h2>
                
                <table class="attendance-table">
                    <thead>
                        <tr>
                            <th>Student Name</th>
                            <th>Status</th>
                            <th>Notes</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($students as $student): 
                            $existing_record = null;
                            foreach ($existing_attendance as $record) {
                                if ($record['student_id'] == $student['id']) {
                                    $existing_record = $record;
                                    break;
                                }
                            }
                        ?>
                            <tr>
                                <td><?php echo htmlspecialchars($student['full_name']); ?></td>
                                <td>
                                    <select name="attendance[<?php echo $student['id']; ?>]" required>
                                        <option value="present" <?php echo ($existing_record && $existing_record['status'] == 'present') ? 'selected' : ''; ?>>Present</option>
                                        <option value="absent" <?php echo ($existing_record && $existing_record['status'] == 'absent') ? 'selected' : ''; ?>>Absent</option>
                                        <option value="late" <?php echo ($existing_record && $existing_record['status'] == 'late') ? 'selected' : ''; ?>>Late</option>
                                        <option value="excused" <?php echo ($existing_record && $existing_record['status'] == 'excused') ? 'selected' : ''; ?>>Excused</option>
                                    </select>
                                </td>
                                <td>
                                    <input type="text" name="notes[<?php echo $student['id']; ?>]" 
                                           value="<?php echo $existing_record ? htmlspecialchars($existing_record['notes']) : ''; ?>" 
                                           placeholder="Optional notes">
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                
                <button type="submit" name="submit_attendance" class="cta-button">Save Attendance</button>
            </form>
        <?php elseif ($selected_course): ?>
            <p class="no-data">No students enrolled in this course.</p>
        <?php else: ?>
            <p class="no-data">Please select a course to manage attendance.</p>
        <?php endif; ?>
    </div>

    <footer>
        <p>&copy; 2023 NgahTech Institute. All rights reserved.</p>
    </footer>
</body>
</html>