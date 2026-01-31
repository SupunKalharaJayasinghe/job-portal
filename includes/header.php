<?php
require_once __DIR__ . '/../core/functions.php';
require_once __DIR__ . '/../core/db.php';

$navUser = null;
$isLoggedIn = !empty($_SESSION['user_id']);
$currentRole = $_SESSION['role'] ?? '';

if ($isLoggedIn) {
    $navStmt = $conn->prepare("SELECT username, logo_file FROM users WHERE id = ? LIMIT 1");
    $navStmt->bind_param('i', $_SESSION['user_id']);
    $navStmt->execute();
    $navRes = $navStmt->get_result();
    $navUser = $navRes ? $navRes->fetch_assoc() : null;
    $navStmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CareerNest</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <header class="site-header">
        <nav class="navbar">
            <a class="logo" href="index.php">CareerNest</a>
            <ul class="nav-links">
                <li><a href="index.php">Home</a></li>
                <li><a href="jobs.php">Jobs</a></li>

                <?php if (!$isLoggedIn): ?>
                    <li><a href="about.php">About</a></li>
                    <li><a href="news.php">News</a></li>
                    <li><a href="login.php">Login</a></li>
                    <li><a href="register.php">Register</a></li>
                <?php elseif ($currentRole === 'seeker'): ?>
                    <li><a href="dashboard.php">Dashboard</a></li>
                    <li><a href="profile.php">Profile</a></li>
                    <li><a href="logout.php">Logout</a></li>
                <?php elseif ($currentRole === 'employer'): ?>
                    <li><a href="seekers.php">Job Seekers</a></li>
                    <li><a href="dashboard.php">Dashboard</a></li>
                    <li><a href="profile.php">Profile</a></li>
                    <li><a href="post-job.php">Post Job</a></li>
                    <li><a href="logout.php">Logout</a></li>
                <?php elseif ($currentRole === 'admin'): ?>
                    <li><a href="seekers.php">Job Seekers</a></li>
                    <li><a href="about.php">About</a></li>
                    <li><a href="news.php">News</a></li>
                    <li><a href="dashboard.php">Dashboard</a></li>
                    <li><a href="profile.php">Profile</a></li>
                    <li><a href="post-job.php">Post Job</a></li>
                    <li><a href="admin.php">Admin Panel</a></li>
                    <li><a href="logout.php">Logout</a></li>
                <?php endif; ?>

                <?php if (!empty($navUser['logo_file'])) : ?>
                    <li>
                        <img class="nav-avatar" src="uploads/logos/<?php echo htmlspecialchars($navUser['logo_file']); ?>" alt="logo" style="width:32px;height:32px;border-radius:50%;object-fit:cover;">
                    </li>
                <?php endif; ?>
            </ul>
        </nav>
    </header>
