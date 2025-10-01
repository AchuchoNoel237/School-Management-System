<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header('Location: ../login.php');
    exit();
}

include '../includes/config.php';

$success = '';
$error = '';

// Get users for specific targeting
$teachers = [];
$students = [];

if ($_SERVER['REQUEST_METHOD'] == 'GET') {
    $stmt = $pdo->prepare("SELECT id, full_name, email FROM users WHERE role = 'teacher' AND account_status = 'approved' ORDER BY full_name");
    $stmt->execute();
    $teachers = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $pdo->prepare("SELECT id, full_name, email FROM users WHERE role = 'student' AND account_status = 'approved' ORDER BY full_name");
    $stmt->execute();
    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Handle message sending
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['send_announcement'])) {
    $title = trim($_POST['title']);
    $message = trim($_POST['message']);
    $audience_type = $_POST['audience_type'];
    $specific_recipients = isset($_POST['specific_recipients']) ? $_POST['specific_recipients'] : [];
    $also_email = isset($_POST['also_email']);

    if (empty($title) || empty($message)) {
        $error = "Title and message are required!";
    } else {
        try {
            $pdo->beginTransaction();

            // Save announcement
            $specific_json = $audience_type == 'specific' ? json_encode($specific_recipients) : null;
            
            $stmt = $pdo->prepare("INSERT INTO announcements (sender_id, title, message, audience_type, specific_recipients) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$_SESSION['user_id'], $title, $message, $audience_type, $specific_json]);
            $announcement_id = $pdo->lastInsertId();

            // Determine recipients based on audience type
            $recipients = [];
            
            switch ($audience_type) {
                case 'all':
                    $stmt = $pdo->prepare("SELECT id FROM users WHERE account_status = 'approved'");
                    $stmt->execute();
                    $recipients = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);
                    break;
                    
                case 'teachers':
                    $stmt = $pdo->prepare("SELECT id FROM users WHERE role = 'teacher' AND account_status = 'approved'");
                    $stmt->execute();
                    $recipients = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);
                    break;
                    
                case 'students':
                    $stmt = $pdo->prepare("SELECT id FROM users WHERE role = 'student' AND account_status = 'approved'");
                    $stmt->execute();
                    $recipients = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);
                    break;
                    
                case 'specific':
                    $recipients = array_map('intval', $specific_recipients);
                    break;
            }

            // Create receipts for all recipients
            $stmt = $pdo->prepare("INSERT INTO announcement_receipts (announcement_id, recipient_id) VALUES (?, ?)");
            foreach ($recipients as $recipient_id) {
                $stmt->execute([$announcement_id, $recipient_id]);
            }

            $pdo->commit();
            $success = "Announcement sent to " . count($recipients) . " recipients successfully!";

            // TODO: Add email functionality here if $also_email is true

        } catch (PDOException $e) {
            $pdo->rollBack();
            $error = "Error sending announcement: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Broadcast Announcement - NgahTech Institute</title>
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
        <h1>Broadcast Announcement</h1>
        
        <?php if ($success): ?>
            <div class="alert success"><?php echo $success; ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert error"><?php echo $error; ?></div>
        <?php endif; ?>

        <form method="POST" action="" class="broadcast-form">
            <div class="form-group">
                <label for="title">Announcement Title:</label>
                <input type="text" id="title" name="title" required placeholder="Important Announcement...">
            </div>

            <div class="form-group">
                <label for="message">Message:</label>
                <textarea id="message" name="message" rows="6" required placeholder="Type your announcement here..."></textarea>
            </div>

            <div class="form-group">
                <label for="audience_type">Send To:</label>
                <select id="audience_type" name="audience_type" required onchange="toggleSpecificRecipients()">
                    <option value="all">Everyone (All Users)</option>
                    <option value="teachers">All Teachers</option>
                    <option value="students">All Students</option>
                    <option value="specific">Specific Users</option>
                </select>
            </div>

            <div id="specific-recipients" class="form-group" style="display: none;">
                <label>Select Specific Recipients:</label>
                
                <div class="recipient-groups">
                    <div class="recipient-group">
                        <h4>Teachers:</h4>
                        <?php foreach ($teachers as $teacher): ?>
                            <label class="checkbox-label">
                                <input type="checkbox" name="specific_recipients[]" value="<?php echo $teacher['id']; ?>">
                                <?php echo htmlspecialchars($teacher['full_name']); ?> (<?php echo htmlspecialchars($teacher['email']); ?>)
                            </label>
                        <?php endforeach; ?>
                    </div>

                    <div class="recipient-group">
                        <h4>Students:</h4>
                        <?php foreach ($students as $student): ?>
                            <label class="checkbox-label">
                                <input type="checkbox" name="specific_recipients[]" value="<?php echo $student['id']; ?>">
                                <?php echo htmlspecialchars($student['full_name']); ?> (<?php echo htmlspecialchars($student['email']); ?>)
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <div class="form-group">
                <label class="checkbox-label">
                    <input type="checkbox" name="also_email" value="1">
                    Also send via email
                </label>
            </div>

            <div class="form-actions">
                <button type="submit" name="send_announcement" class="cta-button large">
                    <i class="fas fa-bullhorn"></i> Broadcast Announcement
                </button>
            </div>
        </form>

        <!-- Announcement History -->
        <div class="announcement-history">
            <h2>Recent Announcements</h2>
            <?php
            $stmt = $pdo->prepare("
                SELECT a.*, u.full_name as sender_name, 
                       COUNT(r.id) as total_recipients,
                       COUNT(CASE WHEN r.read_at IS NOT NULL THEN 1 END) as read_count
                FROM announcements a
                JOIN users u ON a.sender_id = u.id
                LEFT JOIN announcement_receipts r ON a.id = r.announcement_id
                GROUP BY a.id
                ORDER BY a.created_at DESC
                LIMIT 5
            ");
            $stmt->execute();
            $recent_announcements = $stmt->fetchAll(PDO::FETCH_ASSOC);
            ?>

            <?php if (count($recent_announcements) > 0): ?>
                <div class="announcement-list">
                    <?php foreach ($recent_announcements as $announcement): ?>
                        <div class="announcement-item">
                            <h3><?php echo htmlspecialchars($announcement['title']); ?></h3>
                            <p class="meta">Sent by <?php echo htmlspecialchars($announcement['sender_name']); ?> 
                                on <?php echo date('M j, Y g:i A', strtotime($announcement['created_at'])); ?></p>
                            <p class="message"><?php echo nl2br(htmlspecialchars($announcement['message'])); ?></p>
                            <p class="stats">
                                Recipients: <?php echo $announcement['total_recipients']; ?> | 
                                Read: <?php echo $announcement['read_count']; ?> 
                                (<?php echo $announcement['total_recipients'] > 0 ? round(($announcement['read_count'] / $announcement['total_recipients']) * 100, 1) : 0; ?>%)
                            </p>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p class="no-data">No announcements sent yet.</p>
            <?php endif; ?>
        </div>
    </div>

    <script>
    function toggleSpecificRecipients() {
        const audienceType = document.getElementById('audience_type').value;
        const specificDiv = document.getElementById('specific-recipients');
        specificDiv.style.display = audienceType === 'specific' ? 'block' : 'none';
    }

    // Initialize on page load
    toggleSpecificRecipients();
    </script>

    <footer>
        <p>&copy; 2023 NgahTech Institute. All rights reserved.</p>
    </footer>
</body>
</html>