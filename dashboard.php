<?php
session_start();
// Redirect to login if not authenticated
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Redirect teachers and admins to their proper dashboards
if ($_SESSION['role'] == 'teacher') {
    header('Location: teachers/dashboard.php');
    exit();
} elseif ($_SESSION['role'] == 'admin') {
    header('Location: admin/dashboard.php');
    exit();
}
?>

<?php include 'includes/header.php'; ?>

<div class="dashboard-container">
    <h1>Welcome, <?php echo $_SESSION['full_name']; ?>!</h1>
    <p>Your role: <strong><?php echo ucfirst($_SESSION['role']); ?></strong></p>
    
    <div class="dashboard-card">
        <h2>Student Dashboard</h2>
        <p>Here you'll see your grades, attendance, and assignments.</p>
        <p>More student features coming soon! 🚀</p>
    </div>
    
    <a href="logout.php" class="logout-btn">Logout</a>
</div>

<?php include 'includes/footer.php'; ?>