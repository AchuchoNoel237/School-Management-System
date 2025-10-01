<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'student') {
    header('Location: ../login.php');
    exit();
}

include '../includes/config.php';

$student_id = $_SESSION['user_id'];
$success = '';
$error = '';

// Handle assignment submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['submit_assignment'])) {
    $assignment_id = intval($_POST['assignment_id']);
    $submission_text = trim($_POST['submission_text']);
    
    // File upload handling
    $submitted_file = null;
    if (isset($_FILES['submitted_file']) && $_FILES['submitted_file']['error'] == UPLOAD_ERR_OK) {
        $upload_dir = '../uploads/assignments/';
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        $file_extension = pathinfo($_FILES['submitted_file']['name'], PATHINFO_EXTENSION);
        $filename = 'assignment_' . $assignment_id . '_student_' . $student_id . '_' . time() . '.' . $file_extension;
        $filepath = $upload_dir . $filename;
        
        if (move_uploaded_file($_FILES['submitted_file']['tmp_name'], $filepath)) {
            $submitted_file = $filename;
        } else {
            $error = "File upload failed. Please try again.";
        }
    }
    
    if (!$error) {
        try {
            // Check if already submitted
            $stmt = $pdo->prepare("SELECT id FROM assignment_submissions WHERE assignment_id = ? AND student_id = ?");
            $stmt->execute([$assignment_id, $student_id]);
            
            if ($stmt->fetch()) {
                // Update existing submission
                $stmt = $pdo->prepare("UPDATE assignment_submissions SET submitted_file = ?, submission_text = ?, submitted_at = NOW() WHERE assignment_id = ? AND student_id = ?");
                $stmt->execute([$submitted_file, $submission_text, $assignment_id, $student_id]);
            } else {
                // Insert new submission
                $stmt = $pdo->prepare("INSERT INTO assignment_submissions (assignment_id, student_id, submitted_file, submission_text) VALUES (?, ?, ?, ?)");
                $stmt->execute([$assignment_id, $student_id, $submitted_file, $submission_text]);
            }
            
            $success = "Assignment submitted successfully!";
        } catch (PDOException $e) {
            $error = "Error submitting assignment: " . $e->getMessage();
        }
    }
}

// Get student's assignments
$stmt = $pdo->prepare("
    SELECT 
        a.*, 
        c.course_name,
        c.course_code,
        s.submitted_at,
        s.grade,
        s.feedback,
        DATEDIFF(a.due_date, NOW()) as days_remaining,
        CASE 
            WHEN s.submitted_at IS NOT NULL THEN 'submitted'
            WHEN NOW() > a.due_date THEN 'overdue'
            WHEN DATEDIFF(a.due_date, NOW()) <= 2 THEN 'urgent'
            ELSE 'pending'
        END as status
    FROM assignments a
    JOIN courses c ON a.course_id = c.id
    LEFT JOIN assignment_submissions s ON a.id = s.assignment_id AND s.student_id = ?
    ORDER BY a.due_date ASC
");
$stmt->execute([$student_id]);
$assignments = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Assignments - NgahTech Institute</title>
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
                <li><a href="grades.php">Grades</a></li>
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
        <h1>My Assignments</h1>
        
        <?php if ($success): ?>
            <div class="alert success"><?php echo $success; ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert error"><?php echo $error; ?></div>
        <?php endif; ?>

        <!-- Assignments List -->
        <div class="assignments-container">
            <?php if (count($assignments) > 0): ?>
                <div class="assignment-filters">
                    <button class="filter-btn active" data-filter="all">All</button>
                    <button class="filter-btn" data-filter="pending">Pending</button>
                    <button class="filter-btn" data-filter="submitted">Submitted</button>
                    <button class="filter-btn" data-filter="graded">Graded</button>
                    <button class="filter-btn" data-filter="overdue">Overdue</button>
                </div>

                <div class="student-assignments-grid">
                    <?php foreach ($assignments as $assignment): ?>
                        <div class="student-assignment-card <?php echo $assignment['status']; ?>">
                            <div class="assignment-header">
                                <h3><?php echo htmlspecialchars($assignment['title']); ?></h3>
                                <span class="status-badge <?php echo $assignment['status']; ?>">
                                    <?php echo ucfirst($assignment['status']); ?>
                                </span>
                            </div>
                            
                            <div class="assignment-details">
                                <p class="course"><strong><?php echo htmlspecialchars($assignment['course_code']); ?></strong> - <?php echo htmlspecialchars($assignment['course_name']); ?></p>
                                <?php if (!empty($assignment['description'])): ?>
                                    <div class="assignment-description">
                                        <h4>Description:</h4>
                                        <p><?php echo nl2br(htmlspecialchars($assignment['description'])); ?></p>
                                    </div>
                                <?php endif; ?>
                                <p class="due-date">Due: <?php echo date('M j, Y g:i A', strtotime($assignment['due_date'])); ?></p>
                                <p class="points">Points: <?php echo $assignment['max_points']; ?></p>
                                
                                <?php if ($assignment['submitted_at']): ?>
                                    <p class="submitted">Submitted: <?php echo date('M j, Y g:i A', strtotime($assignment['submitted_at'])); ?></p>
                                <?php endif; ?>
                                
                                <?php if ($assignment['grade'] !== null): ?>
                                    <p class="grade">Grade: <span class="grade-value"><?php echo $assignment['grade']; ?>/<?php echo $assignment['max_points']; ?></span></p>
                                <?php endif; ?>
                                
                                <?php if ($assignment['feedback']): ?>
                                    <p class="feedback">Feedback: <?php echo htmlspecialchars($assignment['feedback']); ?></p>
                                <?php endif; ?>
                            </div>

                            <!-- Submission Form -->
                            <?php if (!$assignment['submitted_at'] && $assignment['status'] != 'overdue'): ?>
                                <div class="submission-form">
                                    <button class="toggle-form-btn" onclick="toggleForm(<?php echo $assignment['id']; ?>)">
                                        Submit Assignment
                                    </button>
                                    
                                    <form method="POST" action="" enctype="multipart/form-data" id="form-<?php echo $assignment['id']; ?>" class="submission-form-content" style="display: none;">
                                        <input type="hidden" name="assignment_id" value="<?php echo $assignment['id']; ?>">
                                        
                                        <div class="form-group">
                                            <label>Text Submission:</label>
                                            <textarea name="submission_text" rows="4" placeholder="Type your assignment here..."></textarea>
                                        </div>
                                        
                                        <div class="form-group">
                                            <label>Or Upload File (optional):</label>
                                            <input type="file" name="submitted_file" accept=".pdf,.doc,.docx,.txt,.zip">
                                            <small>Max 10MB - PDF, DOC, DOCX, TXT, ZIP</small>
                                        </div>
                                        
                                        <button type="submit" name="submit_assignment" class="cta-button">Submit Assignment</button>
                                    </form>
                                </div>
                            <?php elseif ($assignment['status'] == 'overdue'): ?>
                                <p class="overdue-message">This assignment is overdue and cannot be submitted.</p>
                            <?php else: ?>
                                <p class="submitted-message">✅ Already submitted</p>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p class="no-data">No assignments found.</p>
            <?php endif; ?>
        </div>
    </div>

    <script>
    function toggleForm(assignmentId) {
        const form = document.getElementById('form-' + assignmentId);
        form.style.display = form.style.display === 'none' ? 'block' : 'none';
    }

    // Filter functionality
    document.querySelectorAll('.filter-btn').forEach(button => {
        button.addEventListener('click', function() {
            // Update active button
            document.querySelectorAll('.filter-btn').forEach(btn => btn.classList.remove('active'));
            this.classList.add('active');
            
            const filter = this.getAttribute('data-filter');
            const cards = document.querySelectorAll('.student-assignment-card');
            
            cards.forEach(card => {
                if (filter === 'all') {
                    card.style.display = 'block';
                } else {
                    card.style.display = card.classList.contains(filter) ? 'block' : 'none';
                }
            });
        });
    });
    </script>

    <footer>
        <p>&copy; 2023 NgahTech Institute. All rights reserved.</p>
    </footer>
</body>
</html>