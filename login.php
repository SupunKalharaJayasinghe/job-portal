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
    $remember = !empty($_POST['remember_me']);

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

            if ($remember) {
                setcookie('remember_email', $user['email'], time() + (30 * 24 * 60 * 60), '/');
            } else {
                if (isset($_COOKIE['remember_email'])) {
                    setcookie('remember_email', '', time() - 3600, '/');
                }
            }

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
        <form class="auth-form" action="" method="post" novalidate>
            <div class="form-group">
                <label for="login_email"><i class="fa-solid fa-envelope"></i> Email</label>
                <input
                    type="email"
                    id="login_email"
                    name="email"
                    placeholder="Enter your email"
                    value="<?php echo isset($_COOKIE['remember_email']) ? htmlspecialchars($_COOKIE['remember_email']) : ''; ?>"
                    required
                >
            </div>
            <div class="form-group">
                <label for="login_password"><i class="fa-solid fa-lock"></i> Password</label>
                <input type="password" id="login_password" name="password" placeholder="Enter your password" required>
            </div>
            <div class="form-group" style="display:flex;align-items:center;justify-content:space-between;gap:0.5rem;flex-wrap:wrap;">
                <label style="display:flex;align-items:center;gap:0.4rem;font-weight:400;">
                    <input type="checkbox" name="remember_me" value="1" <?php echo isset($_COOKIE['remember_email']) ? 'checked' : ''; ?>>
                    <span>Remember me</span>
                </label>
                <a href="#" class="muted-text" style="font-size:0.875rem;">Forgot Password?</a>
            </div>
            <button class="btn-primary" type="submit">Login</button>
        </form>
    </section>
</main>

<?php include 'includes/footer.php'; ?>
