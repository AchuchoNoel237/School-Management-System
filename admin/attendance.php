<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header('Location: ../login.php');
    exit();
}

include '../includes/config.php';

// Default to today's date
$date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');
$previous_date = date('Y-m-d', strtotime($date . ' -1 day'));
$next_date = date('Y-m-d', strtotime($date . ' +1 day'));

// Get attendance statistics for the selected date
$stmt = $pdo->prepare("
    SELECT 
        status,
        COUNT(*) as count,
        ROUND((COUNT(*) * 100.0 / (SELECT COUNT(*) FROM attendance WHERE attendance_date = ?)), 2) as percentage
    FROM attendance 
    WHERE attendance_date = ?
    GROUP BY status
");
$stmt->execute([$date, $date]);
$attendance_stats = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get total students
$stmt = $pdo->prepare("SELECT COUNT(*) as total FROM users WHERE role = 'student' AND account_status = 'approved'");
$stmt->execute();
$total_students = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Get attendance by course
$stmt = $pdo->prepare("
    SELECT 
        c.course_name,
        c.course_code,
        COUNT(a.id) as recorded,
        SUM(CASE WHEN a.status = 'present' THEN 1 ELSE 0 END) as present,
        SUM(CASE WHEN a.status = 'absent' THEN 1 ELSE 0 END) as absent,
        SUM(CASE WHEN a.status = 'late' THEN 1 ELSE 0 END) as late,
        SUM(CASE WHEN a.status = 'excused' THEN 1 ELSE 0 END) as excused,
        ROUND((SUM(CASE WHEN a.status = 'present' THEN 1 ELSE 0 END) * 100.0 / COUNT(a.id)), 2) as attendance_rate
    FROM courses c
    LEFT JOIN attendance a ON c.id = a.course_id AND a.attendance_date = ?
    GROUP BY c.id
    ORDER BY c.course_name
");
$stmt->execute([$date]);
$course_attendance = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate overall percentages
$total_recorded = 0;
$total_present = 0;
$total_absent = 0;

foreach ($course_attendance as $course) {
    $total_recorded += $course['recorded'];
    $total_present += $course['present'];
    $total_absent += $course['absent'] + $course['late']; // Count late as absent for overall stats
}

$overall_attendance_rate = $total_recorded > 0 ? round(($total_present / $total_recorded) * 100, 2) : 0;
$overall_absence_rate = $total_recorded > 0 ? round(($total_absent / $total_recorded) * 100, 2) : 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Attendance Overview - NgahTech Institute</title>
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
                <li><a href="approve_users.php">Approvals</a></li>
                <li><a href="attendance.php" class="active">Attendance</a></li>
                <li><a href="broadcast.php" class="active">Broadcast</a></li>
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
        <h1>Attendance Overview</h1>
        
        <!-- Date Navigation -->
        <div class="date-navigation">
            <a href="?date=<?php echo $previous_date; ?>" class="date-nav-button">
                <i class="fas fa-chevron-left"></i> Previous Day
            </a>
            
            <h2><?php echo date('F j, Y', strtotime($date)); ?></h2>
            
            <a href="?date=<?php echo $next_date; ?>" class="date-nav-button">
                Next Day <i class="fas fa-chevron-right"></i>
            </a>
        </div>

        <!-- Overall Statistics -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon" style="background: #28a745;">
                    <i class="fas fa-users"></i>
                </div>
                <div class="stat-info">
                    <h3>Total Students</h3>
                    <div class="stat-number"><?php echo $total_students; ?></div>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon" style="background: #17a2b8;">
                    <i class="fas fa-clipboard-check"></i>
                </div>
                <div class="stat-info">
                    <h3>Attendance Recorded</h3>
                    <div class="stat-number"><?php echo $total_recorded; ?></div>
                    <div class="stat-subtext"><?php echo round(($total_recorded / $total_students) * 100, 2); ?>% of students</div>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon" style="background: #ffc107;">
                    <i class="fas fa-user-check"></i>
                </div>
                <div class="stat-info">
                    <h3>Overall Attendance Rate</h3>
                    <div class="stat-number"><?php echo $overall_attendance_rate; ?>%</div>
                    <div class="stat-subtext"><?php echo $total_present; ?> present</div>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon" style="background: #dc3545;">
                    <i class="fas fa-user-times"></i>
                </div>
                <div class="stat-info">
                    <h3>Overall Absence Rate</h3>
                    <div class="stat-number"><?php echo $overall_absence_rate; ?>%</div>
                    <div class="stat-subtext"><?php echo $total_absent; ?> absent/late</div>
                </div>
            </div>
        </div>

        <!-- Detailed Attendance Breakdown -->
        <div class="attendance-breakdown">
            <h2>Attendance by Course</h2>
            <table class="attendance-report-table">
                <thead>
                    <tr>
                        <th>Course</th>
                        <th>Recorded</th>
                        <th>Present</th>
                        <th>Absent</th>
                        <th>Late</th>
                        <th>Excused</th>
                        <th>Attendance Rate</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($course_attendance as $course): ?>
                        <tr>
                            <td>
                                <strong><?php echo htmlspecialchars($course['course_code']); ?></strong><br>
                                <small><?php echo htmlspecialchars($course['course_name']); ?></small>
                            </td>
                            <td><?php echo $course['recorded']; ?></td>
                            <td class="positive"><?php echo $course['present']; ?></td>
                            <td class="negative"><?php echo $course['absent']; ?></td>
                            <td class="warning"><?php echo $course['late']; ?></td>
                            <td class="neutral"><?php echo $course['excused']; ?></td>
                            <td>
                                <span class="attendance-rate <?php echo ($course['attendance_rate'] >= 90) ? 'excellent' : (($course['attendance_rate'] >= 80) ? 'good' : 'poor'); ?>">
                                    <?php echo $course['attendance_rate'] ? $course['attendance_rate'] . '%' : 'N/A'; ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Status Distribution -->
        <div class="status-distribution">
            <h2>Attendance Status Distribution</h2>
            <div class="status-cards">
                <?php foreach ($attendance_stats as $stat): ?>
                    <div class="status-card status-<?php echo $stat['status']; ?>">
                        <h3><?php echo ucfirst($stat['status']); ?></h3>
                        <div class="status-count"><?php echo $stat['count']; ?></div>
                        <div class="status-percentage"><?php echo $stat['percentage']; ?>%</div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <footer>
        <p>&copy; 2023 NgahTech Institute. All rights reserved.</p>
    </footer>
</body>
</html>