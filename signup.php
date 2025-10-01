<?php
include 'includes/config.php';
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $full_name = trim($_POST['full_name']);
    $email = trim($_POST['email']);
    $role = $_POST['role'];
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];

    // Check if passwords match
    if ($password !== $confirm_password) {
        $error = "Passwords do not match!";
    } else {
        // Hash password and insert user
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        try {
            $stmt = $pdo->prepare("INSERT INTO users (full_name, email, password, role) VALUES (?, ?, ?, ?)");
            $stmt->execute([$full_name, $email, $hashed_password, $role]);
            $success = "Registration successful! Waiting for admin approval.";
        } catch (PDOException $e) {
            $error = "Email already exists or invalid data.";
        }
    }
}
?>

<?php include 'includes/header.php'; ?>

<div class="auth-container">
    <h2>Sign Up for NgahTech Institute</h2>
    <?php if ($success): ?>
        <div class="alert success"><?php echo $success; ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert error"><?php echo $error; ?></div>
    <?php endif; ?>
    <form method="POST" action="" id="signupForm">
        <input type="text" name="full_name" placeholder="Full Name" required>
        <input type="email" name="email" placeholder="Email Address" required>
        <select name="role" required>
            <option value="">Select Role</option>
            <option value="teacher">Teacher</option>
            <option value="student">Student</option>
        </select>
        <div class="password-wrapper">
            <input type="password" name="password" id="password" placeholder="Password" required>
            <i class="fas fa-eye" id="togglePassword"></i>
        </div>
        <div class="password-wrapper">
            <input type="password" name="confirm_password" id="confirm_password" placeholder="Confirm Password" required>
            <i class="fas fa-eye" id="toggleConfirmPassword"></i>
        </div>
        <button type="submit">Sign Up</button>
    </form>
    <p>Already have an account? <a href="login.php">Login here</a></p>
</div>

<script>
const togglePassword = document.querySelector('#togglePassword');
const password = document.querySelector('#password');
const toggleConfirmPassword = document.querySelector('#toggleConfirmPassword');
const confirmPassword = document.querySelector('#confirm_password');

togglePassword.addEventListener('click', function () {
    const type = password.getAttribute('type') === 'password' ? 'text' : 'password';
    password.setAttribute('type', type);
    this.classList.toggle('fa-eye-slash');
});

toggleConfirmPassword.addEventListener('click', function () {
    const type = confirmPassword.getAttribute('type') === 'password' ? 'text' : 'password';
    confirmPassword.setAttribute('type', type);
    this.classList.toggle('fa-eye-slash');
});

// Real-time password matching validation
const form = document.getElementById('signupForm');
form.addEventListener('submit', function (e) {
    if (password.value !== confirmPassword.value) {
        e.preventDefault();
        alert('Passwords do not match!');
    }
});

// Real-time password matching feedback
confirmPassword.addEventListener('input', function () {
    const matchText = document.getElementById('passwordMatch');
    if (!matchText) {
        const hint = document.createElement('small');
        hint.id = 'passwordMatch';
        hint.classList.add('password-match');
        confirmPassword.parentNode.appendChild(hint);
    }
    
    const hint = document.getElementById('passwordMatch');
    if (password.value === confirmPassword.value) {
        hint.textContent = 'Passwords match!';
        hint.className = 'password-match match';
    } else {
        hint.textContent = 'Passwords do not match!';
        hint.className = 'password-match no-match';
    }
});
</script>

<?php include 'includes/footer.php'; ?>