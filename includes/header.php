<?php
require_once __DIR__ . '/../core/functions.php';
require_once __DIR__ . '/../core/db.php';

$navUser = null;
$navProfile = null;
$isLoggedIn = !empty($_SESSION['user_id']);
$currentRole = $_SESSION['role'] ?? '';
$unreadMessages = 0;
$unreadNotifications = 0;

if ($isLoggedIn) {
    $navStmt = $conn->prepare("SELECT id, username, role FROM users WHERE id = ? LIMIT 1");
    $navStmt->bind_param('i', $_SESSION['user_id']);
    $navStmt->execute();
    $navRes = $navStmt->get_result();
    $navUser = $navRes ? $navRes->fetch_assoc() : null;
    $navStmt->close();

    if ($navUser) {
        $unreadMessages = getUnreadMessageCount($conn, (int) $navUser['id']);
        $unreadNotifications = getUnreadNotificationCount($conn, (int) $navUser['id']);

        if ($currentRole === 'seeker') {
            $profileStmt = $conn->prepare("SELECT headline FROM seeker_profiles WHERE user_id = ? LIMIT 1");
            $profileStmt->bind_param('i', $navUser['id']);
            $profileStmt->execute();
            $profileRes = $profileStmt->get_result();
            $navProfile = $profileRes ? $profileRes->fetch_assoc() : null;
            $profileStmt->close();
        } elseif ($currentRole === 'employer') {
            $profileStmt = $conn->prepare("SELECT company_name, company_logo FROM employer_profiles WHERE user_id = ? LIMIT 1");
            $profileStmt->bind_param('i', $navUser['id']);
            $profileStmt->execute();
            $profileRes = $profileStmt->get_result();
            $navProfile = $profileRes ? $profileRes->fetch_assoc() : null;
            $profileStmt->close();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#2563eb">
    <title>CareerNest</title>
    <link rel="icon" type="image/x-icon" href="assets/img/favicon.ico">
    <link rel="icon" type="image/png" sizes="32x32" href="assets/img/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="assets/img/favicon-16x16.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css?v=<?php echo (int) (@filemtime(__DIR__ . '/../assets/css/style.css') ?: time()); ?>" />
</head>
<body>
    <header class="site-header">
        <nav class="navbar">
            <a class="logo" href="index.php">
                <img src="assets/img/logo1.png" alt="CareerNest logo">
            </a>
            <ul class="nav-links">
                <?php if ($isLoggedIn && $currentRole === 'admin'): ?>
                    <li><a href="admin.php">Admin Dashboard</a></li>
                    <li><a href="admin.php?section=users">Users</a></li>
                    <li><a href="admin.php?section=jobs">Jobs</a></li>
                    <li><a href="admin.php?section=applications">Applications</a></li>
                    <li><a href="admin.php?section=reports">Reports</a></li>
                    <li><a href="admin.php?section=payments">Payments</a></li>
                    <li><a href="admin.php?section=audit">Audit</a></li>
                    <li><a href="messages.php">Messages<?php if ($unreadMessages > 0) : ?><span class="nav-count"><?php echo (int) $unreadMessages; ?></span><?php endif; ?></a></li>
                    <li><a href="notifications.php">Notifications<?php if ($unreadNotifications > 0) : ?><span class="nav-count"><?php echo (int) $unreadNotifications; ?></span><?php endif; ?></a></li>
                    <li><a href="logout.php">Logout</a></li>
                <?php else: ?>
                    <li><a href="index.php">Home</a></li>
                    <li><a href="jobs.php">Jobs</a></li>

                    <?php if (!$isLoggedIn): ?>
                        <li><a href="about.php">About</a></li>
                        <li><a href="news.php">News</a></li>
                        <li><a href="contact.php">Contact</a></li>
                        <li><a href="login.php">Login</a></li>
                        <li><a href="register.php">Register</a></li>
                    <?php elseif ($currentRole === 'seeker'): ?>
                        <li><a href="dashboard.php">Dashboard</a></li>
                        <li><a href="messages.php">Messages<?php if ($unreadMessages > 0) : ?><span class="nav-count"><?php echo (int) $unreadMessages; ?></span><?php endif; ?></a></li>
                        <li><a href="notifications.php">Notifications<?php if ($unreadNotifications > 0) : ?><span class="nav-count"><?php echo (int) $unreadNotifications; ?></span><?php endif; ?></a></li>
                        <li><a href="profile.php">Profile</a></li>
                        <li><a href="contact.php">Contact</a></li>
                        <li><a href="logout.php">Logout</a></li>
                    <?php elseif ($currentRole === 'employer'): ?>
                        <li><a href="seekers.php">Job Seekers</a></li>
                        <li><a href="dashboard.php">Dashboard</a></li>
                        <li><a href="messages.php">Messages<?php if ($unreadMessages > 0) : ?><span class="nav-count"><?php echo (int) $unreadMessages; ?></span><?php endif; ?></a></li>
                        <li><a href="notifications.php">Notifications<?php if ($unreadNotifications > 0) : ?><span class="nav-count"><?php echo (int) $unreadNotifications; ?></span><?php endif; ?></a></li>
                        <li><a href="profile.php">Profile</a></li>
                        <li><a href="post-job.php">Post Job</a></li>
                        <li><a href="contact.php">Contact</a></li>
                        <li><a href="logout.php">Logout</a></li>
                    <?php endif; ?>
                <?php endif; ?>
            </ul>

            <div class="nav-actions">
                <?php if ($isLoggedIn && $currentRole === 'seeker' && !empty($navProfile['headline'])) : ?>
                    <div class="nav-meta">
                        <span class="nav-headline"><?php echo htmlspecialchars($navProfile['headline']); ?></span>
                    </div>
                <?php endif; ?>

                <?php if ($isLoggedIn && $currentRole === 'employer' && !empty($navProfile['company_logo'])) : ?>
                    <div class="nav-meta">
                        <img class="nav-avatar" src="uploads/logos/<?php echo htmlspecialchars($navProfile['company_logo']); ?>" alt="<?php echo htmlspecialchars($navProfile['company_name'] ?? ($navUser['username'] ?? 'Company logo')); ?>" style="width:32px;height:32px;border-radius:50%;object-fit:cover;">
                    </div>
                <?php endif; ?>

                <button class="menu-toggle" type="button" aria-label="Toggle navigation">
                    <i class="fa-solid fa-bars"></i>
                </button>
            </div>
        </nav>
    </header>
