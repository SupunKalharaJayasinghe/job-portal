<?php
require_once __DIR__ . '/../core/functions.php';
require_once __DIR__ . '/../core/db.php';

$navUser = null;
$navProfile = null;
$isLoggedIn = !empty($_SESSION['user_id']);
$currentRole = $_SESSION['role'] ?? '';

if ($isLoggedIn) {
    $navStmt = $conn->prepare("SELECT id, username, role FROM users WHERE id = ? LIMIT 1");
    $navStmt->bind_param('i', $_SESSION['user_id']);
    $navStmt->execute();
    $navRes = $navStmt->get_result();
    $navUser = $navRes ? $navRes->fetch_assoc() : null;
    $navStmt->close();

    if ($navUser) {
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
    <title>CareerNest</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <header class="site-header">
        <nav class="navbar">
            <a class="logo" href="index.php">
                <img src="assets/img/logo1.png" alt="CareerNest logo">
            </a>
            <button class="menu-toggle" type="button" aria-label="Toggle navigation">
                <i class="fa-solid fa-bars"></i>
            </button>
            <ul class="nav-links">
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
                    <li><a href="profile.php">Profile</a></li>
                    <li><a href="contact.php">Contact</a></li>
                    <li><a href="logout.php">Logout</a></li>
                <?php elseif ($currentRole === 'employer'): ?>
                    <li><a href="seekers.php">Job Seekers</a></li>
                    <li><a href="dashboard.php">Dashboard</a></li>
                    <li><a href="profile.php">Profile</a></li>
                    <li><a href="post-job.php">Post Job</a></li>
                    <li><a href="contact.php">Contact</a></li>
                    <li><a href="logout.php">Logout</a></li>
                <?php elseif ($currentRole === 'admin'): ?>
                    <li><a href="seekers.php">Job Seekers</a></li>
                    <li><a href="about.php">About</a></li>
                    <li><a href="news.php">News</a></li>
                    <li><a href="contact.php">Contact</a></li>
                    <li><a href="dashboard.php">Dashboard</a></li>
                    <li><a href="profile.php">Profile</a></li>
                    <li><a href="post-job.php">Post Job</a></li>
                    <li><a href="admin.php">Admin Panel</a></li>
                    <li><a href="logout.php">Logout</a></li>
                <?php endif; ?>

                <?php if ($isLoggedIn && $currentRole === 'seeker' && !empty($navProfile['headline'])) : ?>
                    <li class="nav-headline">
                        <span><?php echo htmlspecialchars($navProfile['headline']); ?></span>
                    </li>
                <?php endif; ?>

                <?php if ($isLoggedIn && $currentRole === 'employer' && !empty($navProfile['company_logo'])) : ?>
                    <li>
                        <img class="nav-avatar" src="uploads/logos/<?php echo htmlspecialchars($navProfile['company_logo']); ?>" alt="<?php echo htmlspecialchars($navProfile['company_name'] ?? ($navUser['username'] ?? 'Company logo')); ?>" style="width:32px;height:32px;border-radius:50%;object-fit:cover;">
                    </li>
                <?php endif; ?>
            </ul>
        </nav>
    </header>
