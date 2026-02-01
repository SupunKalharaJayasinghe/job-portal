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
$companyNameValue = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = sanitizeInput($_POST['username'] ?? ''); // Full name
    $email = sanitizeInput($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    $rawRole = $_POST['user_type'] ?? '';
    $companyName = sanitizeInput($_POST['company_name'] ?? '');

    $usernameValue = $username;
    $emailValue = $email;
    $roleValue = $rawRole;
    $companyNameValue = $companyName;

    // Default role to 'seeker' if none or invalid is provided
    if (!in_array($rawRole, ['seeker', 'employer'], true)) {
        $role = 'seeker';
    } else {
        $role = $rawRole;
    }

    if ($username && $email && $password && $confirmPassword) {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please enter a valid email address.';
        } elseif (strlen($password) < 6) {
            $error = 'Password must be at least 6 characters long.';
        } elseif ($password !== $confirmPassword) {
            $error = 'Passwords do not match.';
        } elseif ($role === 'employer' && $companyName === '') {
            $error = 'Company name is required for employer accounts.';
        } else {
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
                    $newUserId = $ins->insert_id;
                    $ins->close();

                    // Initialize profile records for normalized schema
                    if ($role === 'employer') {
                        if ($companyName !== '') {
                            $empStmt = $conn->prepare('INSERT INTO employer_profiles (user_id, company_name) VALUES (?, ?)');
                            $empStmt->bind_param('is', $newUserId, $companyName);
                            $empStmt->execute();
                            $empStmt->close();
                        }
                    } else {
                        // Create a basic seeker profile row so future lookups work
                        $seekStmt = $conn->prepare('INSERT INTO seeker_profiles (user_id) VALUES (?)');
                        $seekStmt->bind_param('i', $newUserId);
                        $seekStmt->execute();
                        $seekStmt->close();
                    }

                    header('Location: login.php?registered=1');
                    exit();
                } else {
                    $error = 'Could not create account. Please try again.';
                    $ins->close();
                }
            }
        }
    } else {
        $error = 'Full name, email, password, and confirm password are required.';
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
        <div id="registerError" class="error-text" style="display:none;"></div>
        <form id="registerForm" class="auth-form" action="" method="post" novalidate>
            <div class="form-group">
                <label for="username"><i class="fa-solid fa-user"></i> Full Name</label>
                <input type="text" id="username" name="username" placeholder="Enter your full name" value="<?php echo htmlspecialchars($usernameValue); ?>" required>
            </div>
            <div class="form-group">
                <label for="email"><i class="fa-solid fa-envelope"></i> Email</label>
                <input type="email" id="email" name="email" placeholder="Enter your email" value="<?php echo htmlspecialchars($emailValue); ?>" required>
            </div>
            <div class="form-group">
                <label for="password"><i class="fa-solid fa-lock"></i> Password</label>
                <input type="password" id="password" name="password" placeholder="Create a password" required>
            </div>
            <div class="form-group">
                <label for="confirm_password"><i class="fa-solid fa-lock"></i> Confirm Password</label>
                <input type="password" id="confirm_password" name="confirm_password" placeholder="Re-enter your password" required>
            </div>
            <div class="form-group">
                <label for="user_type"><i class="fa-solid fa-user-check"></i> I am a...</label>
                <select id="user_type" name="user_type" required>
                    <option value="" disabled <?php echo $roleValue === '' ? 'selected' : ''; ?>>Select an option</option>
                    <option value="seeker" <?php echo $roleValue === 'seeker' ? 'selected' : ''; ?>>Job Seeker</option>
                    <option value="employer" <?php echo $roleValue === 'employer' ? 'selected' : ''; ?>>Employer</option>
                </select>
            </div>
            <div class="form-group" id="company_field_group" style="display:none;">
                <label for="company_name"><i class="fa-solid fa-building"></i> Company Name</label>
                <input type="text" id="company_name" name="company_name" placeholder="Enter your company name" value="<?php echo htmlspecialchars($companyNameValue); ?>">
            </div>
            <button class="btn-primary" type="submit">Register</button>
        </form>
    </section>
</main>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var form = document.getElementById('registerForm');
    var roleSelect = document.getElementById('user_type');
    var companyGroup = document.getElementById('company_field_group');
    var errorBox = document.getElementById('registerError');

    function toggleCompanyField() {
        if (!roleSelect) return;
        if (roleSelect.value === 'employer') {
            companyGroup.style.display = 'block';
        } else {
            companyGroup.style.display = 'none';
        }
    }

    if (roleSelect && companyGroup) {
        roleSelect.addEventListener('change', toggleCompanyField);
        toggleCompanyField();
    }

    if (form) {
        form.addEventListener('submit', function (event) {
            var fullName = document.getElementById('username');
            var email = document.getElementById('email');
            var password = document.getElementById('password');
            var confirmPassword = document.getElementById('confirm_password');
            var companyName = document.getElementById('company_name');

            var messages = [];

            if (!fullName.value.trim()) {
                messages.push('Full name is required.');
            }

            var emailValue = email.value.trim();
            var emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailValue || !emailPattern.test(emailValue)) {
                messages.push('A valid email address is required.');
            }

            if (!password.value || password.value.length < 6) {
                messages.push('Password must be at least 6 characters long.');
            }

            if (!confirmPassword.value || password.value !== confirmPassword.value) {
                messages.push('Passwords do not match.');
            }

            if (!roleSelect.value) {
                messages.push('Please select whether you are a job seeker or employer.');
            }

            if (roleSelect.value === 'employer' && companyName && !companyName.value.trim()) {
                messages.push('Company name is required for employer accounts.');
            }

            if (messages.length > 0) {
                event.preventDefault();
                if (errorBox) {
                    errorBox.style.display = 'block';
                    errorBox.textContent = messages.join(' ');
                } else {
                    alert(messages.join('\n'));
                }
            }
        });
    }
});
</script>

<?php include 'includes/footer.php'; ?>
