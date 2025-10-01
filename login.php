<?php
// Enable full error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

// Include configuration
$config_file = 'includes/config.php';
if (!file_exists($config_file)) {
    die("Error: Configuration file not found at $config_file");
}

include $config_file;

// Check if database connection is working
if (!isset($pdo)) {
    die("Error: Database connection not established");
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    
    echo "Debug: Email received - " . htmlspecialchars($email) . "<br>";

    try {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user) {
            echo "Debug: User found - " . htmlspecialchars($user['email']) . "<br>";
            echo "Debug: Account status - " . $user['account_status'] . "<br>";
            echo "Debug: Stored password hash - " . $user['password'] . "<br>";
            
            // Check if account is approved
            if ($user['account_status'] !== 'approved') {
                $error = "Account pending admin approval. Please wait.";
                echo "Debug: Account not approved<br>";
            } elseif (password_verify($password, $user['password'])) {
                echo "Debug: Password verification successful!<br>";
                
                // Password is correct, start session
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['email'] = $user['email'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['full_name'] = $user['full_name'];

                echo "Debug: Session variables set<br>";
                echo "Debug: User role - " . $user['role'] . "<br>";

                // Redirect based on role
                if ($user['role'] == 'admin') {
                    echo "Debug: Redirecting to admin dashboard<br>";
                    header('Location: admin/dashboard.php');
                } elseif ($user['role'] == 'teacher') {
                    echo "Debug: Redirecting to teacher dashboard<br>";
                    header('Location: teachers/dashboard.php');
                } else {
                    echo "Debug: Redirecting to student dashboard<br>";
                    header('Location: students/dashboard.php');
                }
                exit();
            } else {
                $error = "Invalid email or password.";
                echo "Debug: Password verification failed<br>";
                echo "Debug: Input password - " . htmlspecialchars($password) . "<br>";
            }
        } else {
            $error = "Invalid email or password.";
            echo "Debug: No user found with that email<br>";
        }
    } catch (PDOException $e) {
        $error = "Database error: " . $e->getMessage();
        echo "Debug: PDO Exception - " . $e->getMessage() . "<br>";
    }
}

// If we reach here, either it's a GET request or login failed
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - NgahTech Institute</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/auth.css">
</head>
<body>
    <!-- Simple header for login page -->
    <nav class="navbar">
        <div class="nav-container">
            <a href="index.php" class="logo">NgahTech</a>
            <ul class="nav-menu">
                <li><a href="index.php">Home</a></li>
                <li><a href="signup.php">Sign Up</a></li>
            </ul>
        </div>
    </nav>

    <div class="auth-container">
        <h2>Login to NgahTech Institute</h2>
        
        <?php if ($error): ?>
            <div class="alert error"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <form method="POST" action="">
            <input type="email" name="email" placeholder="Email Address" required 
                   value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
            
            <div class="password-wrapper">
                <input type="password" name="password" id="password" placeholder="Password" required>
                <i class="fas fa-eye" id="togglePassword"></i>
            </div>
            
            <button type="submit">Login</button>
        </form>
        
        <p>Don't have an account? <a href="signup.php">Sign up here</a></p>
    </div>

    <script>
    const togglePassword = document.querySelector('#togglePassword');
    const password = document.querySelector('#password');

    togglePassword.addEventListener('click', function () {
        const type = password.getAttribute('type') === 'password' ? 'text' : 'password';
        password.setAttribute('type', type);
        this.classList.toggle('fa-eye-slash');
    });
    </script>

    <footer>
        <p>&copy; 2023 NgahTech Institute. All rights reserved.</p>
    </footer>
</body>
</html>