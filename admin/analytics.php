<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header('Location: ../login.php');
    exit();
}

include '../includes/config.php';

// Get date range filters with defaults
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01');
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-t');

// Get attendance analytics
$attendance_data = [
    'daily' => [],
    'weekly' => [],
    'by_course' => []
];

// Daily attendance trend
$stmt = $pdo->prepare("
    SELECT 
        DATE(attendance_date) as date,
        COUNT(*) as total_records,
        SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) as present,
        SUM(CASE WHEN status = 'absent' THEN 1 ELSE 0 END) as absent,
        SUM(CASE WHEN status = 'late' THEN 1 ELSE 0 END) as late,
        ROUND((SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) * 100.0 / COUNT(*)), 2) as attendance_rate
    FROM attendance 
    WHERE attendance_date BETWEEN ? AND ?
    GROUP BY DATE(attendance_date)
    ORDER BY date
");
$stmt->execute([$start_date, $end_date]);
$attendance_data['daily'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Attendance by course
$stmt = $pdo->prepare("
    SELECT 
        c.course_code,
        c.course_name,
        COUNT(a.id) as total_records,
        SUM(CASE WHEN a.status = 'present' THEN 1 ELSE 0 END) as present,
        ROUND((SUM(CASE WHEN a.status = 'present' THEN 1 ELSE 0 END) * 100.0 / COUNT(a.id)), 2) as attendance_rate
    FROM attendance a
    JOIN courses c ON a.course_id = c.id
    WHERE a.attendance_date BETWEEN ? AND ?
    GROUP BY c.id
    ORDER BY attendance_rate DESC
");
$stmt->execute([$start_date, $end_date]);
$attendance_data['by_course'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Grade analytics
$grade_data = [
    'distribution' => [],
    'by_course' => [],
    'trends' => []
];

// Grade distribution
$stmt = $pdo->prepare("
    SELECT 
        CASE
            WHEN grade_value >= 90 THEN 'A'
            WHEN grade_value >= 80 THEN 'B' 
            WHEN grade_value >= 70 THEN 'C'
            WHEN grade_value >= 60 THEN 'D'
            ELSE 'F'
        END as grade_band,
        COUNT(*) as count,
        ROUND((COUNT(*) * 100.0 / (SELECT COUNT(*) FROM grades)), 2) as percentage
    FROM grades
    GROUP BY 
        CASE
            WHEN grade_value >= 90 THEN 'A'
            WHEN grade_value >= 80 THEN 'B'
            WHEN grade_value >= 70 THEN 'C'
            WHEN grade_value >= 60 THEN 'D'
            ELSE 'F'
        END
    ORDER BY grade_band
");
$stmt->execute();
$grade_data['distribution'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Grades by course
$stmt = $pdo->prepare("
    SELECT 
        c.course_code,
        c.course_name,
        COUNT(g.id) as grade_count,
        ROUND(AVG(g.grade_value), 2) as average_grade,
        ROUND(MIN(g.grade_value), 2) as min_grade,
        ROUND(MAX(g.grade_value), 2) as max_grade
    FROM grades g
    JOIN courses c ON g.course_id = c.id
    GROUP BY c.id
    ORDER BY average_grade DESC
");
$stmt->execute();
$grade_data['by_course'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Enrollment statistics
$enrollment_stats = [
    'total_students' => 0,
    'total_teachers' => 0,
    'total_courses' => 0,
    'students_by_status' => []
];

$stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE role = 'student' AND account_status = 'approved'");
$stmt->execute();
$enrollment_stats['total_students'] = $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE role = 'teacher' AND account_status = 'approved'");
$stmt->execute();
$enrollment_stats['total_teachers'] = $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM courses");
$stmt->execute();
$enrollment_stats['total_courses'] = $stmt->fetchColumn();

// Student status distribution
$stmt = $pdo->prepare("
    SELECT 
        account_status,
        COUNT(*) as count
    FROM users 
    WHERE role = 'student'
    GROUP BY account_status
");
$stmt->execute();
$enrollment_stats['students_by_status'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Analytics Dashboard - NgahTech Institute</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <!-- Chart.js Library -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
                <li><a href="courses.php">Courses</a></li>
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
        <h1>Analytics Dashboard</h1>
        
        <!-- Date Filter -->
        <div class="analytics-filter">
            <form method="GET" action="">
                <div class="filter-row">
                    <div class="form-group">
                        <label for="start_date">Start Date:</label>
                        <input type="date" id="start_date" name="start_date" value="<?php echo $start_date; ?>">
                    </div>
                    <div class="form-group">
                        <label for="end_date">End Date:</label>
                        <input type="date" id="end_date" name="end_date" value="<?php echo $end_date; ?>">
                    </div>
                    <button type="submit" class="cta-button">
                        <i class="fas fa-filter"></i> Apply Filter
                    </button>
                </div>
            </form>
        </div>

        <!-- Overview Stats -->
        <div class="overview-stats">
            <h2>Overview</h2>
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon" style="background: #28a745;">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="stat-info">
                        <h3>Total Students</h3>
                        <div class="stat-number"><?php echo $enrollment_stats['total_students']; ?></div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon" style="background: #17a2b8;">
                        <i class="fas fa-chalkboard-teacher"></i>
                    </div>
                    <div class="stat-info">
                        <h3>Total Teachers</h3>
                        <div class="stat-number"><?php echo $enrollment_stats['total_teachers']; ?></div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon" style="background: #6f42c1;">
                        <i class="fas fa-book"></i>
                    </div>
                    <div class="stat-info">
                        <h3>Total Courses</h3>
                        <div class="stat-number"><?php echo $enrollment_stats['total_courses']; ?></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Attendance Analytics -->
        <div class="analytics-section">
            <h2>Attendance Analytics</h2>
            <div class="charts-grid">
                <div class="chart-card">
                    <h3>Daily Attendance Trend</h3>
                    <canvas id="attendanceTrendChart" height="250"></canvas>
                </div>
                <div class="chart-card">
                    <h3>Attendance by Course</h3>
                    <canvas id="attendanceByCourseChart" height="250"></canvas>
                </div>
            </div>
        </div>

        <!-- Grade Analytics -->
        <div class="analytics-section">
            <h2>Grade Analytics</h2>
            <div class="charts-grid">
                <div class="chart-card">
                    <h3>Grade Distribution</h3>
                    <canvas id="gradeDistributionChart" height="250"></canvas>
                </div>
                <div class="chart-card">
                    <h3>Average Grades by Course</h3>
                    <canvas id="gradesByCourseChart" height="250"></canvas>
                </div>
            </div>
        </div>

        <!-- Detailed Reports -->
        <div class="analytics-section">
            <h2>Detailed Reports</h2>
            <div class="reports-grid">
                <div class="report-card">
                    <h3>Top Performing Courses</h3>
                    <div class="report-content">
                        <?php foreach (array_slice($grade_data['by_course'], 0, 5) as $course): ?>
                            <div class="report-item">
                                <span class="course-name"><?php echo $course['course_code']; ?></span>
                                <span class="course-grade"><?php echo $course['average_grade']; ?>%</span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="report-card">
                    <h3>Best Attendance Rates</h3>
                    <div class="report-content">
                        <?php foreach (array_slice($attendance_data['by_course'], 0, 5) as $course): ?>
                            <div class="report-item">
                                <span class="course-name"><?php echo $course['course_code']; ?></span>
                                <span class="attendance-rate"><?php echo $course['attendance_rate']; ?>%</span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- JavaScript for Charts -->
    <script>
    // Attendance Trend Chart
    const attendanceTrendCtx = document.getElementById('attendanceTrendChart').getContext('2d');
    new Chart(attendanceTrendCtx, {
        type: 'line',
        data: {
            labels: [<?php echo implode(',', array_map(function($item) { return "'" . date('M j', strtotime($item['date'])) . "'"; }, $attendance_data['daily'])); ?>],
            datasets: [{
                label: 'Attendance Rate (%)',
                data: [<?php echo implode(',', array_column($attendance_data['daily'], 'attendance_rate')); ?>],
                borderColor: '#28a745',
                backgroundColor: 'rgba(40, 167, 69, 0.1)',
                fill: true,
                tension: 0.4
            }]
        },
        options: {
            responsive: true,
            plugins: {
                title: { display: true, text: 'Daily Attendance Trend' }
            }
        }
    });

    // Grade Distribution Chart
    const gradeDistCtx = document.getElementById('gradeDistributionChart').getContext('2d');
    new Chart(gradeDistCtx, {
        type: 'pie',
        data: {
            labels: [<?php echo implode(',', array_map(function($item) { return "'Grade " . $item['grade_band'] . "'"; }, $grade_data['distribution'])); ?>],
            datasets: [{
                data: [<?php echo implode(',', array_column($grade_data['distribution'], 'count')); ?>],
                backgroundColor: ['#28a745', '#17a2b8', '#ffc107', '#fd7e14', '#dc3545']
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: { position: 'bottom' }
            }
        }
    });

    // Attendance by Course Chart
    const attendanceCourseCtx = document.getElementById('attendanceByCourseChart').getContext('2d');
    new Chart(attendanceCourseCtx, {
        type: 'bar',
        data: {
            labels: [<?php echo implode(',', array_map(function($item) { return "'" . $item['course_code'] . "'"; }, array_slice($attendance_data['by_course'], 0, 8))); ?>],
            datasets: [{
                label: 'Attendance Rate (%)',
                data: [<?php echo implode(',', array_map(function($item) { return $item['attendance_rate']; }, array_slice($attendance_data['by_course'], 0, 8))); ?>],
                backgroundColor: 'rgba(23, 162, 184, 0.8)'
            }]
        },
        options: {
            responsive: true,
            scales: {
                y: { beginAtZero: true, max: 100 }
            }
        }
    });

    // Grades by Course Chart
    const gradesCourseCtx = document.getElementById('gradesByCourseChart').getContext('2d');
    new Chart(gradesCourseCtx, {
        type: 'bar',
        data: {
            labels: [<?php echo implode(',', array_map(function($item) { return "'" . $item['course_code'] . "'"; }, array_slice($grade_data['by_course'], 0, 8))); ?>],
            datasets: [{
                label: 'Average Grade (%)',
                data: [<?php echo implode(',', array_map(function($item) { return $item['average_grade']; }, array_slice($grade_data['by_course'], 0, 8))); ?>],
                backgroundColor: 'rgba(111, 66, 193, 0.8)'
            }]
        },
        options: {
            responsive: true,
            scales: {
                y: { beginAtZero: true, max: 100 }
            }
        }
    });
    </script>

    <footer>
        <p>&copy; 2023 NgahTech Institute. All rights reserved.</p>
    </footer>
</body>
</html>