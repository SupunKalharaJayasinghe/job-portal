<?php
require_once 'core/db.php';
require_once 'core/functions.php';

$error = '';

if (!empty($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit();
}

$usernameValue = '';
$emailValue = '';
$roleValue = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = sanitizeInput($_POST['username'] ?? '');
    $email = sanitizeInput($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $role = $_POST['user_type'] ?? '';

    $usernameValue = $username;
    $emailValue = $email;
    $roleValue = $role;

    if ($username && $email && $password && in_array($role, ['seeker', 'employer'], true)) {
        // Check if email already exists
        $check = $conn->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
        $check->bind_param('s', $email);
        $check->execute();
        $res = $check->get_result();
        $exists = $res && $res->num_rows > 0;
        $check->close();

        if ($exists) {
            $error = 'An account with this email already exists.';
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $ins = $conn->prepare('INSERT INTO users (username, email, password, role) VALUES (?, ?, ?, ?)');
            $ins->bind_param('ssss', $username, $email, $hash, $role);
            if ($ins->execute()) {
                $ins->close();
                header('Location: login.php?registered=1');
                exit();
            } else {
                $error = 'Could not create account. Please try again.';
                $ins->close();
            }
        }
    } else {
        $error = 'All fields are required and you must select a valid role.';
    }
}

include 'includes/header.php';
?>

<main class="auth-page">
    <section class="form-section">
        <div class="section-header">
            <h1>Create Your Account</h1>
            <p>Register as a job seeker or employer to get started.</p>
        </div>
        <?php if (!empty($error)) : ?>
            <p class="error-text"><?php echo htmlspecialchars($error); ?></p>
        <?php endif; ?>
        <form class="auth-form" action="" method="post">
            <div class="form-group">
                <label for="username">Username</label>
                <input type="text" id="username" name="username" placeholder="Enter your username" value="<?php echo htmlspecialchars($usernameValue); ?>" required>
            </div>
            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" placeholder="Enter your email" value="<?php echo htmlspecialchars($emailValue); ?>" required>
            </div>
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" placeholder="Create a password" required>
            </div>
            <div class="form-group">
                <label for="user_type">I am a...</label>
                <select id="user_type" name="user_type" required>
                    <option value="" disabled <?php echo $roleValue === '' ? 'selected' : ''; ?>>Select an option</option>
                    <option value="seeker" <?php echo $roleValue === 'seeker' ? 'selected' : ''; ?>>Job Seeker</option>
                    <option value="employer" <?php echo $roleValue === 'employer' ? 'selected' : ''; ?>>Employer</option>
                </select>
            </div>
            <button class="btn-primary" type="submit">Register</button>
        </form>
    </section>
</main>

<?php include 'includes/footer.php'; ?>
