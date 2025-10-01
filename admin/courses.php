<?php
session_start();

echo "<!-- DEBUG: Session user_id: " . $_SESSION['user_id'] . " -->";
echo "<!-- DEBUG: Session role: " . $_SESSION['role'] . " -->";

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header('Location: ../login.php');
    exit();
}

include '../includes/config.php';

$success = '';
$error = '';

// Get all teachers for dropdown
$stmt = $pdo->prepare("SELECT id, full_name FROM users WHERE role = 'teacher' AND account_status = 'approved' ORDER BY full_name");
$stmt->execute();
$teachers = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get all courses with teacher info
$stmt = $pdo->prepare("
    SELECT c.*, u.full_name as teacher_name 
    FROM courses c 
    LEFT JOIN users u ON c.teacher_id = u.id 
    ORDER BY c.course_code
");
$stmt->execute();
$courses = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    echo "<!-- DEBUG: POST data received -->";
    echo "<!-- DEBUG: POST: " . print_r($_POST, true) . " -->";

    if (isset($_POST['create_course'])) {
        // Create new course
        $course_code = trim($_POST['course_code']);
        $course_name = trim($_POST['course_name']);
        $description = trim($_POST['description']);
        $credits = intval($_POST['credits']);
        $teacher_id = !empty($_POST['teacher_id']) ? intval($_POST['teacher_id']) : null;

        try {
            $stmt = $pdo->prepare("INSERT INTO courses (course_code, course_name, description, credits, teacher_id) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$course_code, $course_name, $description, $credits, $teacher_id]);
            $success = "Course created successfully!";
        } catch (PDOException $e) {
            $error = "Error creating course: " . $e->getMessage();
        }
    }
    elseif (isset($_POST['assign_teacher'])) {
        // Assign teacher to course
        $course_id = intval($_POST['course_id']);
        $teacher_id = intval($_POST['teacher_id']);

        try {
            $stmt = $pdo->prepare("UPDATE courses SET teacher_id = ? WHERE id = ?");
            $stmt->execute([$teacher_id, $course_id]);
            $success = "Teacher assigned to course successfully!";
        } catch (PDOException $e) {
            $error = "Error assigning teacher: " . $e->getMessage();
        }
    }
    elseif (isset($_POST['remove_teacher'])) {
        // Remove teacher from course
        $course_id = intval($_POST['course_id']);

        try {
            $stmt = $pdo->prepare("UPDATE courses SET teacher_id = NULL WHERE id = ?");
            $stmt->execute([$course_id]);
            $success = "Teacher removed from course successfully!";
        } catch (PDOException $e) {
            $error = "Error removing teacher: " . $e->getMessage();
        }
    }

    // Refresh data after changes
    // header("Location: courses.php?success=" . urlencode($success) . "&error=" . urlencode($error));
    // exit();
}

// Check for success/error messages from redirect
if (isset($_GET['success'])) $success = $_GET['success'];
if (isset($_GET['error'])) $error = $_GET['error'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Course Management - NgahTech Institute</title>
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
                <li><a href="approve_users.php">Approvals</a></li>
                <li><a href="attendance.php">Attendance</a></li>
                <li><a href="broadcast.php">Broadcast</a></li>
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
        <h1>Course Management</h1>
        
        <?php if ($success): ?>
            <div class="alert success"><?php echo $success; ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert error"><?php echo $error; ?></div>
        <?php endif; ?>

        <!-- Create Course Form -->
        <div class="create-course-form">
            <h2>Create New Course</h2>
            <form method="POST" action="">
                <div class="form-row">
                    <div class="form-group">
                        <label for="course_code">Course Code:</label>
                        <input type="text" id="course_code" name="course_code" required placeholder="e.g., MATH101">
                    </div>
                    <div class="form-group">
                        <label for="course_name">Course Name:</label>
                        <input type="text" id="course_name" name="course_name" required placeholder="e.g., Mathematics 101">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="description">Description:</label>
                        <textarea id="description" name="description" rows="3" placeholder="Course description..."></textarea>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="credits">Credits:</label>
                        <input type="number" id="credits" name="credits" value="3" min="1" max="6">
                    </div>
                    <div class="form-group">
                        <label for="teacher_id">Assign Teacher (optional):</label>
                        <select id="teacher_id" name="teacher_id">
                            <option value="">-- Select Teacher --</option>
                            <?php foreach ($teachers as $teacher): ?>
                                <option value="<?php echo $teacher['id']; ?>"><?php echo htmlspecialchars($teacher['full_name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <button type="submit" name="create_course" class="cta-button">
                    <i class="fas fa-plus"></i> Create Course
                </button>
            </form>
        </div>

        <!-- Course List -->
        <div class="course-management">
            <h2>Course Catalog</h2>
            
            <?php if (count($courses) > 0): ?>
                <div class="courses-table-container">
                    <table class="courses-table">
                        <thead>
                            <tr>
                                <th>Course Code</th>
                                <th>Course Name</th>
                                <th>Credits</th>
                                <th>Assigned Teacher</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($courses as $course): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($course['course_code']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($course['course_name']); ?></td>
                                    <td><?php echo $course['credits']; ?></td>
                                    <td>
                                        <?php if ($course['teacher_name']): ?>
                                            <?php echo htmlspecialchars($course['teacher_name']); ?>
                                        <?php else: ?>
                                            <span class="no-teacher">Not assigned</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="course-actions">
                                        <!-- Assign Teacher Form -->
                                        <form method="POST" action="" class="inline-form">
                                            <input type="hidden" name="course_id" value="<?php echo $course['id']; ?>">
                                            <select name="teacher_id" required>
                                                <option value="">Assign Teacher</option>
                                                <?php foreach ($teachers as $teacher): ?>
                                                    <option value="<?php echo $teacher['id']; ?>">
                                                        <?php echo htmlspecialchars($teacher['full_name']); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                            <button type="submit" name="assign_teacher" class="btn assign-btn">
                                                <i class="fas fa-user-plus"></i> Assign
                                            </button>
                                        </form>

                                        <!-- Remove Teacher Button -->
                                        <?php if ($course['teacher_id']): ?>
                                            <form method="POST" action="" class="inline-form">
                                                <input type="hidden" name="course_id" value="<?php echo $course['id']; ?>">
                                                <button type="submit" name="remove_teacher" class="btn remove-btn" 
                                                        onclick="return confirm('Remove teacher from this course?')">
                                                    <i class="fas fa-times"></i> Remove
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="no-courses">
                    <i class="fas fa-book-open fa-3x"></i>
                    <h3>No courses created yet</h3>
                    <p>Use the form above to create your first course.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <footer>
        <p>&copy; 2023 NgahTech Institute. All rights reserved.</p>
    </footer>
</body>
</html>