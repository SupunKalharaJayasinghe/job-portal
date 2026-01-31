<?php
require_once 'core/db.php';
require_once 'core/functions.php';

$error = '';

if (!empty($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = sanitizeInput($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($email && $password) {
        $stmt = $conn->prepare("SELECT id, username, email, password, role FROM users WHERE email = ? LIMIT 1");
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $res = $stmt->get_result();
        $user = $res ? $res->fetch_assoc() : null;
        $stmt->close();

        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];
            header('Location: dashboard.php');
            exit();
        } else {
            $error = 'Invalid email or password.';
        }
    } else {
        $error = 'Email and password are required.';
    }
}

include 'includes/header.php';
?>

<main class="auth-page">
    <section class="form-section">
        <div class="section-header">
            <h1>Welcome Back</h1>
            <p>Log in to access your dashboard.</p>
        </div>
        <?php if (isset($_GET['registered'])) : ?>
            <p class="success-text">Account created successfully. Please log in.</p>
        <?php endif; ?>
        <?php if (!empty($error)) : ?>
            <p class="error-text"><?php echo htmlspecialchars($error); ?></p>
        <?php endif; ?>
        <form class="auth-form" action="" method="post">
            <div class="form-group">
                <label for="login_email">Email</label>
                <input type="email" id="login_email" name="email" placeholder="Enter your email" required>
            </div>
            <div class="form-group">
                <label for="login_password">Password</label>
                <input type="password" id="login_password" name="password" placeholder="Enter your password" required>
            </div>
            <button class="btn-primary" type="submit">Login</button>
        </form>
    </section>
</main>

<?php include 'includes/footer.php'; ?>
