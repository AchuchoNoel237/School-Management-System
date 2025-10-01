<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'student') {
    header('Location: ../login.php');
    exit();
}

include '../includes/config.php';

$student_id = $_SESSION['user_id'];

// Get all grades for the student with course info
$stmt = $pdo->prepare("
    SELECT 
        g.*,
        c.course_name,
        c.course_code,
        u.full_name as teacher_name,
        a.title as assignment_title,
        a.max_points
    FROM grades g
    JOIN courses c ON g.course_id = c.id
    JOIN users u ON c.teacher_id = u.id
    LEFT JOIN assignments a ON g.description LIKE CONCAT('%', a.title, '%') OR a.id = g.course_id
    WHERE g.student_id = ?
    ORDER BY g.created_at DESC
");
$stmt->execute([$student_id]);
$grades = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate statistics
$total_grades = 0;
$grade_count = count($grades);
$grade_sum = 0;

foreach ($grades as $grade) {
    if ($grade['grade_value'] !== null) {
        $grade_sum += $grade['grade_value'];
        $total_grades++;
    }
}

$average_grade = $total_grades > 0 ? round($grade_sum / $total_grades, 2) : 0;

// Get grade distribution
$grade_distribution = [
    'A' => 0, 'B' => 0, 'C' => 0, 'D' => 0, 'F' => 0
];

foreach ($grades as $grade) {
    if ($grade['grade_value'] !== null) {
        if ($grade['grade_value'] >= 90) $grade_distribution['A']++;
        elseif ($grade['grade_value'] >= 80) $grade_distribution['B']++;
        elseif ($grade['grade_value'] >= 70) $grade_distribution['C']++;
        elseif ($grade['grade_value'] >= 60) $grade_distribution['D']++;
        else $grade_distribution['F']++;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Grades - NgahTech Institute</title>
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
                <li><a href="grades.php" class="active">Grades</a></li>
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
        <h1>My Grades</h1>
        
        <!-- Grade Statistics -->
        <div class="grade-statistics">
            <div class="stat-card">
                <div class="stat-icon" style="background: #28a745;">
                    <i class="fas fa-chart-line"></i>
                </div>
                <div class="stat-info">
                    <h3>Overall Average</h3>
                    <div class="stat-number"><?php echo $average_grade; ?>%</div>
                    <div class="stat-subtext">Based on <?php echo $total_grades; ?> graded items</div>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon" style="background: #17a2b8;">
                    <i class="fas fa-clipboard-list"></i>
                </div>
                <div class="stat-info">
                    <h3>Total Grades</h3>
                    <div class="stat-number"><?php echo $total_grades; ?></div>
                    <div class="stat-subtext">Graded assignments & exams</div>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon" style="background: #6f42c1;">
                    <i class="fas fa-book"></i>
                </div>
                <div class="stat-info">
                    <h3>Courses</h3>
                    <div class="stat-number">
                        <?php 
                        $unique_courses = array_unique(array_column($grades, 'course_id'));
                        echo count($unique_courses); 
                        ?>
                    </div>
                    <div class="stat-subtext">With graded work</div>
                </div>
            </div>
        </div>

        <!-- Grade Distribution -->
        <div class="grade-distribution">
            <h2>Grade Distribution</h2>
            <div class="distribution-chart">
                <?php foreach ($grade_distribution as $letter => $count): 
                    if ($total_grades > 0) {
                        $percentage = round(($count / $total_grades) * 100, 1);
                    } else {
                        $percentage = 0;
                    }
                ?>
                    <div class="distribution-item">
                        <span class="letter-grade grade-<?php echo $letter; ?>"><?php echo $letter; ?></span>
                        <div class="distribution-bar">
                            <div class="bar-fill" style="width: <?php echo $percentage; ?>%; 
                                background: <?php 
                                if ($letter == 'A') echo '#28a745';
                                elseif ($letter == 'B') echo '#17a2b8';
                                elseif ($letter == 'C') echo '#ffc107';
                                elseif ($letter == 'D') echo '#fd7e14';
                                else echo '#dc3545';
                                ?>;">
                            </div>
                        </div>
                        <span class="distribution-count"><?php echo $count; ?> (<?php echo $percentage; ?>%)</span>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Detailed Grades Table -->
        <div class="grades-detailed">
            <h2>Grade Details</h2>
            
            <?php if (count($grades) > 0): ?>
                <div class="table-container">
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
                                    <td data-label="Course">
                                        <strong><?php echo htmlspecialchars($grade['course_code']); ?></strong><br>
                                        <small><?php echo htmlspecialchars($grade['course_name']); ?></small>
                                    </td>
                                    <td data-label="Assignment">
                                        <?php 
                                        if (!empty($grade['assignment_title'])) {
                                            echo htmlspecialchars($grade['assignment_title']);
                                        } elseif (!empty($grade['description'])) {
                                            echo htmlspecialchars($grade['description']);
                                        } else {
                                            echo 'N/A';
                                        }
                                        ?>
                                    </td>
                                    <td data-label="Grade">
                                        <span class="grade-badge <?php echo getGradeClass($grade['grade_value']); ?>">
                                            <?php echo $grade['grade_value']; ?>%
                                            <?php if (!empty($grade['max_points'])): ?>
                                                <small>(<?php echo calculatePoints($grade['grade_value'], $grade['max_points']); ?>/<?php echo $grade['max_points']; ?>)</small>
                                            <?php endif; ?>
                                        </span>
                                    </td>
                                    <td data-label="Type"><?php echo ucfirst($grade['grade_type']); ?></td>
                                    <td data-label="Teacher"><?php echo htmlspecialchars($grade['teacher_name']); ?></td>
                                    <td data-label="Date"><?php echo date('M j, Y', strtotime($grade['created_at'])); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="no-grades">
                    <i class="fas fa-book-open fa-3x"></i>
                    <h3>No grades yet</h3>
                    <p>Your grades will appear here once your teachers start grading your work.</p>
                </div>
            <?php endif; ?>
        </div>
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

// Helper function to calculate points from percentage
function calculatePoints($percentage, $max_points) {
    return round(($percentage / 100) * $max_points, 1);
}