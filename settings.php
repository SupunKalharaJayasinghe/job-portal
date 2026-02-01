<?php
require_once 'core/db.php';
require_once 'core/functions.php';

checkLoggedIn();

$userId = $_SESSION['user_id'] ?? null;
$role = $_SESSION['role'] ?? '';

$userStmt = $conn->prepare('SELECT id, email, password FROM users WHERE id = ? LIMIT 1');
$userStmt->bind_param('i', $userId);
$userStmt->execute();
$res = $userStmt->get_result();
$user = $res ? $res->fetch_assoc() : null;
$userStmt->close();

if (!$user) {
    die('User not found');
}

$success = '';
$error = '';

// Optional user_settings table for email preferences
$hasSettingsTable = false;
$settings = [
    'email_job_alerts' => 1,
    'email_application_updates' => 1,
    'email_newsletters' => 1,
];
$tblCheck = $conn->query("SHOW TABLES LIKE 'user_settings'");
if ($tblCheck && $tblCheck->num_rows > 0) {
    $hasSettingsTable = true;
    $setStmt = $conn->prepare('SELECT email_job_alerts, email_application_updates, email_newsletters FROM user_settings WHERE user_id = ? LIMIT 1');
    if ($setStmt) {
        $setStmt->bind_param('i', $userId);
        $setStmt->execute();
        $sRes = $setStmt->get_result();
        $row = $sRes ? $sRes->fetch_assoc() : null;
        if ($row) {
            foreach ($settings as $k => $v) {
                if (isset($row[$k])) {
                    $settings[$k] = (int) $row[$k];
                }
            }
        }
        $setStmt->close();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'change_password') {
        $current = $_POST['current_password'] ?? '';
        $new = $_POST['new_password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';

        if ($current === '' || $new === '' || $confirm === '') {
            $error = 'All password fields are required.';
        } elseif ($new !== $confirm) {
            $error = 'New password and confirmation do not match.';
        } elseif (!password_verify($current, $user['password'])) {
            $error = 'Current password is incorrect.';
        } elseif (strlen($new) < 6) {
            $error = 'New password must be at least 6 characters.';
        } else {
            $hash = password_hash($new, PASSWORD_DEFAULT);
            $upd = $conn->prepare('UPDATE users SET password = ? WHERE id = ?');
            if ($upd) {
                $upd->bind_param('si', $hash, $userId);
                if ($upd->execute()) {
                    $success = 'Password changed successfully.';
                } else {
                    $error = 'Could not update password.';
                }
                $upd->close();
            }
        }
    } elseif ($action === 'update_notifications' && $hasSettingsTable) {
        $settings['email_job_alerts'] = !empty($_POST['email_job_alerts']) ? 1 : 0;
        $settings['email_application_updates'] = !empty($_POST['email_application_updates']) ? 1 : 0;
        $settings['email_newsletters'] = !empty($_POST['email_newsletters']) ? 1 : 0;

        $sel = $conn->prepare('SELECT user_id FROM user_settings WHERE user_id = ? LIMIT 1');
        if ($sel) {
            $sel->bind_param('i', $userId);
            $sel->execute();
            $sRes = $sel->get_result();
            $exists = $sRes && $sRes->num_rows > 0;
            $sel->close();

            if ($exists) {
                $upd = $conn->prepare('UPDATE user_settings SET email_job_alerts = ?, email_application_updates = ?, email_newsletters = ? WHERE user_id = ?');
                if ($upd) {
                    $upd->bind_param('iiii', $settings['email_job_alerts'], $settings['email_application_updates'], $settings['email_newsletters'], $userId);
                    $upd->execute();
                    $upd->close();
                }
            } else {
                $ins = $conn->prepare('INSERT INTO user_settings (user_id, email_job_alerts, email_application_updates, email_newsletters) VALUES (?, ?, ?, ?)');
                if ($ins) {
                    $ins->bind_param('iiii', $userId, $settings['email_job_alerts'], $settings['email_application_updates'], $settings['email_newsletters']);
                    $ins->execute();
                    $ins->close();
                }
            }
        }
        $success = 'Notification preferences updated.';
    } elseif ($action === 'update_visibility' && $role === 'seeker') {
        $visibility = ($_POST['profile_visibility'] ?? 'public') === 'private' ? 'private' : 'public';
        $sp = $conn->prepare('UPDATE seeker_profiles SET profile_visibility = ? WHERE user_id = ?');
        if ($sp) {
            $sp->bind_param('si', $visibility, $userId);
            $sp->execute();
            $sp->close();
        }
        $success = 'Profile visibility updated.';
    } elseif ($action === 'delete_account') {
        $confirm = trim($_POST['confirm_text'] ?? '');
        if ($confirm !== 'DELETE') {
            $error = 'Please type DELETE to confirm account deletion.';
        } else {
            $del = $conn->prepare('DELETE FROM users WHERE id = ?');
            if ($del) {
                $del->bind_param('i', $userId);
                $del->execute();
                $del->close();
            }
            session_unset();
            session_destroy();
            header('Location: index.php');
            exit();
        }
    }
}

include 'includes/header.php';
?>

<main class="dashboard-page settings-page">
    <section class="section-header">
        <h1>Account Settings</h1>
        <p>Manage your security, preferences, and privacy.</p>
        <?php if (!empty($success)) : ?>
            <p class="success-text"><?php echo htmlspecialchars($success); ?></p>
        <?php endif; ?>
        <?php if (!empty($error)) : ?>
            <p class="error-text"><?php echo htmlspecialchars($error); ?></p>
        <?php endif; ?>
    </section>

    <section class="profile-layout">
        <div class="profile-card">
            <h2>Change Password</h2>
            <form class="auth-form" action="" method="post">
                <input type="hidden" name="action" value="change_password">
                <div class="form-group">
                    <label for="current_password">Current Password</label>
                    <input type="password" id="current_password" name="current_password" required>
                </div>
                <div class="form-group">
                    <label for="new_password">New Password</label>
                    <input type="password" id="new_password" name="new_password" required>
                </div>
                <div class="form-group">
                    <label for="confirm_password">Confirm New Password</label>
                    <input type="password" id="confirm_password" name="confirm_password" required>
                </div>
                <button class="btn-primary" type="submit">Update Password</button>
            </form>
        </div>

        <div class="profile-card">
            <h2>Email Notifications</h2>
            <form class="auth-form" action="" method="post">
                <input type="hidden" name="action" value="update_notifications">
                <div class="form-group">
                    <label>
                        <input type="checkbox" name="email_job_alerts" <?php echo !empty($settings['email_job_alerts']) ? 'checked' : ''; ?>>
                        Job alerts and recommendations
                    </label>
                </div>
                <div class="form-group">
                    <label>
                        <input type="checkbox" name="email_application_updates" <?php echo !empty($settings['email_application_updates']) ? 'checked' : ''; ?>>
                        Application status updates
                    </label>
                </div>
                <div class="form-group">
                    <label>
                        <input type="checkbox" name="email_newsletters" <?php echo !empty($settings['email_newsletters']) ? 'checked' : ''; ?>>
                        Product news and tips
                    </label>
                </div>
                <button class="btn-primary" type="submit">Save Preferences</button>
            </form>
        </div>

        <?php if ($role === 'seeker') : ?>
            <div class="profile-card">
                <h2>Profile Visibility</h2>
                <form class="auth-form" action="" method="post">
                    <input type="hidden" name="action" value="update_visibility">
                    <div class="form-group">
                        <label for="profile_visibility">Who can see your profile?</label>
                        <select id="profile_visibility" name="profile_visibility">
                            <option value="public">Public (recommended)</option>
                            <option value="private">Private</option>
                        </select>
                    </div>
                    <button class="btn-primary" type="submit">Update Visibility</button>
                </form>
            </div>
        <?php endif; ?>

        <div class="profile-card">
            <h2>Danger Zone</h2>
            <p class="error-text">Delete your account and all associated data.</p>
            <form class="auth-form" action="" method="post">
                <input type="hidden" name="action" value="delete_account">
                <div class="form-group">
                    <label for="confirm_text">Type DELETE to confirm</label>
                    <input type="text" id="confirm_text" name="confirm_text" placeholder="DELETE">
                </div>
                <button class="btn-secondary" type="submit">Delete Account</button>
            </form>
        </div>
    </section>
</main>

<?php include 'includes/footer.php'; ?>
