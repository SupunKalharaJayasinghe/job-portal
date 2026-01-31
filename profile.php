<?php
require_once 'core/db.php';
require_once 'core/functions.php';

checkLoggedIn();

$userId = $_SESSION['user_id'] ?? null;
$role = $_SESSION['role'] ?? '';
$success = '';
$error = '';

// Fetch current user data
$userStmt = $conn->prepare("SELECT username, email, role, headline, bio, skills, phone, website, logo_file FROM users WHERE id = ? LIMIT 1");
$userStmt->bind_param('i', $userId);
$userStmt->execute();
$res = $userStmt->get_result();
$user = $res ? $res->fetch_assoc() : null;
$userStmt->close();

if (!$user) {
    die('User not found');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($role === 'employer') {
        $username = sanitizeInput($_POST['company_name'] ?? $user['username']);
        $website = sanitizeInput($_POST['website'] ?? $user['website']);
        $headline = sanitizeInput($_POST['headline'] ?? $user['headline']);
        $bio = sanitizeInput($_POST['bio'] ?? $user['bio']);
        $phone = sanitizeInput($_POST['phone'] ?? $user['phone']);
        $logoFileName = $user['logo_file'];

        // Handle logo upload
        if (!empty($_FILES['logo']['name']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
            $tmp = $_FILES['logo']['tmp_name'];
            $size = (int) $_FILES['logo']['size'];
            $info = getimagesize($tmp);
            $allowedExt = ['png', 'jpg', 'jpeg', 'gif'];
            $ext = strtolower(pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION));

            if (!$info || !in_array($ext, $allowedExt, true)) {
                $error = 'Logo must be an image (png, jpg, jpeg, gif).';
            } elseif ($size > 2 * 1024 * 1024) {
                $error = 'Logo must be 2MB or smaller.';
            } else {
                $uploadDir = __DIR__ . '/uploads/logos';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0777, true);
                }
                $logoFileName = 'logo_' . $userId . '_' . time() . '.' . $ext;
                $dest = $uploadDir . '/' . $logoFileName;
                if (!move_uploaded_file($tmp, $dest)) {
                    $error = 'Failed to upload logo.';
                }
            }
        }

        if (empty($error)) {
            $sql = "UPDATE users SET username = ?, headline = ?, bio = ?, website = ?, phone = ?";
            $params = [$username, $headline, $bio, $website, $phone];
            $types = 'sssss';
            if (!empty($logoFileName)) {
                $sql .= ", logo_file = ?";
                $params[] = $logoFileName;
                $types .= 's';
            }
            $sql .= " WHERE id = ?";
            $params[] = $userId;
            $types .= 'i';

            $upd = $conn->prepare($sql);
            $upd->bind_param($types, ...$params);
            if ($upd->execute()) {
                $success = 'Profile updated successfully.';
                $user['username'] = $username;
                $user['headline'] = $headline;
                $user['bio'] = $bio;
                $user['website'] = $website;
                $user['phone'] = $phone;
                $user['logo_file'] = $logoFileName;
                $_SESSION['username'] = $username;
            } else {
                $error = 'Failed to update profile.';
            }
            $upd->close();
        }
    } else { // seeker
        $username = sanitizeInput($_POST['full_name'] ?? $user['username']);
        $headline = sanitizeInput($_POST['headline'] ?? $user['headline']);
        $bio = sanitizeInput($_POST['bio'] ?? $user['bio']);
        $skills = sanitizeInput($_POST['skills'] ?? $user['skills']);
        $phone = sanitizeInput($_POST['phone'] ?? $user['phone']);

        $upd = $conn->prepare("UPDATE users SET username = ?, headline = ?, bio = ?, skills = ?, phone = ? WHERE id = ?");
        $upd->bind_param('sssssi', $username, $headline, $bio, $skills, $phone, $userId);
        if ($upd->execute()) {
            $success = 'Profile updated successfully.';
            $user['username'] = $username;
            $user['headline'] = $headline;
            $user['bio'] = $bio;
            $user['skills'] = $skills;
            $user['phone'] = $phone;
            $_SESSION['username'] = $username;
        } else {
            $error = 'Failed to update profile.';
        }
        $upd->close();
    }
}

include 'includes/header.php';
?>

<main class="profile-page">
    <section class="profile-hero">
        <h1>Your Profile</h1>
        <p>Showcase your experience, preferences, and portfolio to get matched faster.</p>
        <?php if (!empty($success)) : ?>
            <p class="success-text"><?php echo htmlspecialchars($success); ?></p>
        <?php endif; ?>
        <?php if (!empty($error)) : ?>
            <p class="error-text"><?php echo htmlspecialchars($error); ?></p>
        <?php endif; ?>
    </section>

    <section class="profile-layout">
        <div class="profile-card">
            <?php if ($role === 'employer') : ?>
                <h2>Company Profile</h2>
                <form class="auth-form" action="" method="post" enctype="multipart/form-data">
                    <div class="form-group">
                        <label for="company_name">Company Name</label>
                        <input id="company_name" name="company_name" type="text" value="<?php echo htmlspecialchars($user['username']); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="email">Email</label>
                        <input id="email" name="email" type="email" value="<?php echo htmlspecialchars($user['email']); ?>" readonly>
                    </div>
                    <div class="form-group">
                        <label for="headline">Headline</label>
                        <input id="headline" name="headline" type="text" value="<?php echo htmlspecialchars($user['headline']); ?>" placeholder="e.g., Building the future of fintech">
                    </div>
                    <div class="form-group">
                        <label for="website">Website</label>
                        <input id="website" name="website" type="url" value="<?php echo htmlspecialchars($user['website']); ?>" placeholder="https://company.com">
                    </div>
                    <div class="form-group">
                        <label for="phone">Phone</label>
                        <input id="phone" name="phone" type="text" value="<?php echo htmlspecialchars($user['phone']); ?>">
                    </div>
                    <div class="form-group">
                        <label for="bio">Company Description</label>
                        <textarea id="bio" name="bio" rows="4" placeholder="Who you are, what you build, and why candidates should care."><?php echo htmlspecialchars($user['bio']); ?></textarea>
                    </div>
                    <div class="form-group">
                        <label for="logo">Company Logo (max 2MB)</label>
                        <input id="logo" name="logo" type="file" accept="image/*">
                        <?php if (!empty($user['logo_file'])) : ?>
                            <p>Current: <img src="uploads/logos/<?php echo htmlspecialchars($user['logo_file']); ?>" alt="logo" style="width:64px;height:64px;object-fit:cover;border-radius:8px;"></p>
                        <?php endif; ?>
                    </div>
                    <button class="btn-primary" type="submit">Save Profile</button>
                </form>
            <?php else : ?>
                <h2>Your Details</h2>
                <form class="auth-form" action="" method="post">
                    <div class="form-group">
                        <label for="full_name">Full Name</label>
                        <input id="full_name" name="full_name" type="text" value="<?php echo htmlspecialchars($user['username']); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="email">Email</label>
                        <input id="email" name="email" type="email" value="<?php echo htmlspecialchars($user['email']); ?>" readonly>
                    </div>
                    <div class="form-group">
                        <label for="headline">Headline</label>
                        <input id="headline" name="headline" type="text" placeholder="e.g., Senior Java Dev" value="<?php echo htmlspecialchars($user['headline']); ?>">
                    </div>
                    <div class="form-group">
                        <label for="bio">Bio</label>
                        <textarea id="bio" name="bio" rows="4" placeholder="Summarize your impact, specialties, and industries."><?php echo htmlspecialchars($user['bio']); ?></textarea>
                    </div>
                    <div class="form-group">
                        <label for="skills">Skills</label>
                        <textarea id="skills" name="skills" rows="3" placeholder="React, Java, SQL"><?php echo htmlspecialchars($user['skills']); ?></textarea>
                    </div>
                    <div class="form-group">
                        <label for="phone">Phone</label>
                        <input id="phone" name="phone" type="text" value="<?php echo htmlspecialchars($user['phone']); ?>">
                    </div>
                    <button class="btn-primary" type="submit">Save Profile</button>
                </form>
            <?php endif; ?>
        </div>
    </section>
</main>

<?php include 'includes/footer.php'; ?>
