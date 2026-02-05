
<?php
require_once 'core/db.php';
require_once 'core/functions.php';

checkLoggedIn();

$userId = $_SESSION['user_id'] ?? null;
$username = $_SESSION['username'] ?? 'User';
$role = $_SESSION['role'] ?? '';

$hasApplicationUpdatedAt = false;
try {
    $colRes = $conn->query("SHOW COLUMNS FROM applications LIKE 'updated_at'");
    if ($colRes && $colRes->num_rows > 0) {
        $hasApplicationUpdatedAt = true;
    }
} catch (mysqli_sql_exception $e) {
}

$employerCompanyName = '';
$employerApproveStatus = 'interview';
if ($role === 'employer') {
    try {
        $stRes = $conn->query("SHOW COLUMNS FROM applications LIKE 'status'");
        if ($stRes && $row = $stRes->fetch_assoc()) {
            $type = strtolower((string) ($row['Type'] ?? ''));
            if (strpos($type, 'enum(') !== false) {
                if (strpos($type, "'approved'") !== false) {
                    $employerApproveStatus = 'approved';
                }
            }
        }
    } catch (mysqli_sql_exception $e) {
    }

    try {
        $coStmt = $conn->prepare(
            'SELECT COALESCE(ep.company_name, u.username) AS company_name\n'
                . 'FROM users u\n'
                . 'LEFT JOIN employer_profiles ep ON u.id = ep.user_id\n'
                . 'WHERE u.id = ? LIMIT 1'
        );
        if ($coStmt) {
            $coStmt->bind_param('i', $userId);
            $coStmt->execute();
            $coRes = $coStmt->get_result();
            if ($coRes && $coRow = $coRes->fetch_assoc()) {
                $employerCompanyName = (string) ($coRow['company_name'] ?? '');
            }
            $coStmt->close();
        }
    } catch (mysqli_sql_exception $e) {
    }
}

// Handle delete request for employer jobs
if ($role === 'employer' && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_job_id'])) {
    $deleteId = (int) $_POST['delete_job_id'];
    if ($deleteId > 0) {
        $delStmt = $conn->prepare("DELETE FROM jobs WHERE id = ? AND employer_id = ?");
        $delStmt->bind_param('ii', $deleteId, $userId);
        $delStmt->execute();
        $delStmt->close();
        header('Location: dashboard.php?deleted=1');
        exit();
    }
}

if ($role === 'employer' && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['employer_app_action'], $_POST['application_id'])) {
    $appId = (int) $_POST['application_id'];
    $action = sanitizeInput($_POST['employer_app_action']);
    $jobId = isset($_POST['job_id']) ? (int) $_POST['job_id'] : 0;

    if ($appId > 0) {
        $notifySeekerId = 0;
        $notifyJobTitle = '';
        try {
            if (tableHasColumn($conn, 'applications', 'seeker_id')) {
                $ns = $conn->prepare('SELECT a.seeker_id, j.title FROM applications a JOIN jobs j ON a.job_id = j.id WHERE a.id = ? AND j.employer_id = ? LIMIT 1');
                if ($ns) {
                    $ns->bind_param('ii', $appId, $userId);
                    $ns->execute();
                    $nsRes = $ns->get_result();
                    if ($nsRes && $nsRow = $nsRes->fetch_assoc()) {
                        $notifySeekerId = (int) ($nsRow['seeker_id'] ?? 0);
                        $notifyJobTitle = (string) ($nsRow['title'] ?? '');
                    }
                    $ns->close();
                }
            }
        } catch (mysqli_sql_exception $e) {
        }

        if ($action === 'view') {
            $newStatus = 'reviewed';
            if ($hasApplicationUpdatedAt) {
                $updSql = "UPDATE applications a\n"
                    . "JOIN jobs j ON a.job_id = j.id\n"
                    . "SET a.status = ?, a.updated_at = COALESCE(a.updated_at, NOW())\n"
                    . "WHERE a.id = ? AND j.employer_id = ? AND LOWER(a.status) = 'pending'";
            } else {
                $updSql = "UPDATE applications a\n"
                    . "JOIN jobs j ON a.job_id = j.id\n"
                    . "SET a.status = ?\n"
                    . "WHERE a.id = ? AND j.employer_id = ? AND LOWER(a.status) = 'pending'";
            }

            try {
                $upd = $conn->prepare($updSql);
                if ($upd) {
                    $upd->bind_param('sii', $newStatus, $appId, $userId);
                    $upd->execute();
                    $upd->close();
                }
            } catch (mysqli_sql_exception $e) {
            }

            if ($notifySeekerId > 0) {
                $jobTitle = $notifyJobTitle !== '' ? $notifyJobTitle : 'your job application';
                createNotification($conn, $notifySeekerId, 'Application reviewed', 'Your application for "' . $jobTitle . '" has been reviewed.');
            }

            if ($jobId <= 0) {
                try {
                    $jb = $conn->prepare('SELECT a.job_id FROM applications a JOIN jobs j ON a.job_id = j.id WHERE a.id = ? AND j.employer_id = ? LIMIT 1');
                    if ($jb) {
                        $jb->bind_param('ii', $appId, $userId);
                        $jb->execute();
                        $jbRes = $jb->get_result();
                        if ($jbRes && $jbRow = $jbRes->fetch_assoc()) {
                            $jobId = (int) ($jbRow['job_id'] ?? 0);
                        }
                        $jb->close();
                    }
                } catch (mysqli_sql_exception $e) {
                }
            }

            if ($jobId > 0) {
                header('Location: view-applications.php?job_id=' . $jobId . '#app-' . $appId);
            } else {
                header('Location: dashboard.php#recent-applications');
            }
            exit();
        }

        if ($action === 'reject') {
            $newStatus = 'rejected';
            if ($hasApplicationUpdatedAt) {
                $updSql = "UPDATE applications a\n"
                    . "JOIN jobs j ON a.job_id = j.id\n"
                    . "SET a.status = ?, a.updated_at = NOW()\n"
                    . "WHERE a.id = ? AND j.employer_id = ?";
            } else {
                $updSql = "UPDATE applications a\n"
                    . "JOIN jobs j ON a.job_id = j.id\n"
                    . "SET a.status = ?\n"
                    . "WHERE a.id = ? AND j.employer_id = ?";
            }

            try {
                $upd = $conn->prepare($updSql);
                if ($upd) {
                    $upd->bind_param('sii', $newStatus, $appId, $userId);
                    $upd->execute();
                    $upd->close();
                }
            } catch (mysqli_sql_exception $e) {
            }

            if ($notifySeekerId > 0) {
                $jobTitle = $notifyJobTitle !== '' ? $notifyJobTitle : 'your job application';
                createNotification($conn, $notifySeekerId, 'Application rejected', 'Your application for "' . $jobTitle . '" has been marked as rejected.');
            }

            header('Location: dashboard.php#recent-applications');
            exit();
        }

        if ($action === 'approve') {
            $date = sanitizeInput($_POST['interview_date'] ?? '');
            $time = sanitizeInput($_POST['interview_time'] ?? '');
            $mode = sanitizeInput($_POST['mode'] ?? '');
            $location = sanitizeInput($_POST['location'] ?? '');

            if ($date !== '' && $time !== '') {
                $newStatus = $employerApproveStatus;

                if ($hasApplicationUpdatedAt) {
                    $updSql = "UPDATE applications a\n"
                        . "JOIN jobs j ON a.job_id = j.id\n"
                        . "SET a.status = ?, a.updated_at = NOW()\n"
                        . "WHERE a.id = ? AND j.employer_id = ?";
                } else {
                    $updSql = "UPDATE applications a\n"
                        . "JOIN jobs j ON a.job_id = j.id\n"
                        . "SET a.status = ?\n"
                        . "WHERE a.id = ? AND j.employer_id = ?";
                }

                try {
                    $upd = $conn->prepare($updSql);
                    if ($upd) {
                        $upd->bind_param('sii', $newStatus, $appId, $userId);
                        $upd->execute();
                        $upd->close();
                    }
                } catch (mysqli_sql_exception $e) {
                }

                if (tableExists($conn, 'interviews')
                    && tableHasColumn($conn, 'interviews', 'application_id')
                    && tableHasColumn($conn, 'interviews', 'interview_date')
                    && tableHasColumn($conn, 'interviews', 'mode')
                    && tableHasColumn($conn, 'interviews', 'location')
                    && tableHasColumn($conn, 'interviews', 'status')) {
                    $datetime = $date . ' ' . $time . ':00';
                    try {
                        $ownsApp = false;
                        $ownStmt = $conn->prepare('SELECT a.id FROM applications a JOIN jobs j ON a.job_id = j.id WHERE a.id = ? AND j.employer_id = ? LIMIT 1');
                        if ($ownStmt) {
                            $ownStmt->bind_param('ii', $appId, $userId);
                            $ownStmt->execute();
                            $ownRes = $ownStmt->get_result();
                            $ownsApp = $ownRes && $ownRes->num_rows > 0;
                            $ownStmt->close();
                        }

                        if (!$ownsApp) {
                            header('Location: dashboard.php#recent-applications');
                            exit();
                        }

                        $exists = false;
                        $chk = $conn->prepare("SELECT id FROM interviews WHERE application_id = ? AND status = 'Scheduled' LIMIT 1");
                        if ($chk) {
                            $chk->bind_param('i', $appId);
                            $chk->execute();
                            $chkRes = $chk->get_result();
                            $exists = $chkRes && $chkRes->num_rows > 0;
                            $chk->close();
                        }

                        if (!$exists) {
                            $intStmt = $conn->prepare("INSERT INTO interviews (application_id, interview_date, mode, location, status) VALUES (?, ?, ?, ?, 'Scheduled')");
                            if ($intStmt) {
                                $intStmt->bind_param('isss', $appId, $datetime, $mode, $location);
                                $intStmt->execute();
                                $intStmt->close();
                            }
                        }
                    } catch (mysqli_sql_exception $e) {
                    }
                }

                if ($notifySeekerId > 0) {
                    $jobTitle = $notifyJobTitle !== '' ? $notifyJobTitle : 'your job application';
                    $msg = 'Interview scheduled for "' . $jobTitle . '" on ' . $date . ' ' . $time;
                    if ($mode !== '') {
                        $msg .= ' (' . $mode . ')';
                    }
                    if ($location !== '') {
                        $msg .= ' - ' . $location;
                    }
                    createNotification($conn, $notifySeekerId, 'Interview scheduled', $msg);
                }
            }

            header('Location: dashboard.php#recent-applications');
            exit();
        }
    }
}

if ($role === 'seeker' && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['remove_saved_id'])) {
    $savedId = (int) $_POST['remove_saved_id'];
    if ($savedId > 0) {
        $rmStmt = $conn->prepare('DELETE FROM saved_jobs WHERE job_id = ? AND user_id = ?');
        if ($rmStmt) {
            $rmStmt->bind_param('ii', $savedId, $userId);
            $rmStmt->execute();
            $rmStmt->close();
        }
        header('Location: dashboard.php?saved_removed=1');
        exit();
    }
}

// Preload role-specific data
$seekerStats = [
    'applications' => 0,
    'interviews' => 0,
    'saved_jobs' => 0,
    'completion' => 0,
];
$employerStats = [
    'active_jobs' => 0,
    'applications' => 0,
    'views' => 0,
];
$adminStats = [
    'users' => 0,
    'jobs' => 0,
    'applications' => 0,
    'revenue' => 0,
];

$recentApplications = [];
$savedJobs = [];
$upcomingInterviews = [];
$employerJobs = [];
$recentEmployerApplications = [];

if ($role === 'seeker') {
    // Profile completion percentage from seeker_profiles
    $profileCompletion = 0;
    $spStmt = $conn->prepare("SELECT headline, bio, location, experience_level, years_experience, availability, linkedin_url, portfolio_url, resume_file
                               FROM seeker_profiles WHERE user_id = ? LIMIT 1");
    if ($spStmt) {
        $spStmt->bind_param('i', $userId);
        $spStmt->execute();
        $spRes = $spStmt->get_result();
        $sp = $spRes ? $spRes->fetch_assoc() : null;
        $spStmt->close();
        if ($sp) {
            $fields = ['headline', 'bio', 'location', 'experience_level', 'years_experience', 'availability', 'linkedin_url', 'portfolio_url', 'resume_file'];
            $filled = 0;
            $total = count($fields);
            foreach ($fields as $f) {
                if (!empty($sp[$f])) {
                    $filled++;
                }
            }
            if ($total > 0) {
                $profileCompletion = (int) round(($filled / $total) * 100);
            }
        }
    }

    // Stats: total applications
    $countStmt = $conn->prepare('SELECT COUNT(*) AS c FROM applications WHERE seeker_id = ?');
    if ($countStmt) {
        $countStmt->bind_param('i', $userId);
        $countStmt->execute();
        $cRes = $countStmt->get_result();
        if ($cRes && $row = $cRes->fetch_assoc()) {
            $seekerStats['applications'] = (int) $row['c'];
        }
        $countStmt->close();
    }

    // Stats: scheduled interviews
    $intStmt = $conn->prepare(
        "SELECT COUNT(*) AS c
         FROM interviews
         JOIN applications ON interviews.application_id = applications.id
         WHERE applications.seeker_id = ? AND interviews.status = 'Scheduled'"
    );
    if ($intStmt) {
        $intStmt->bind_param('i', $userId);
        $intStmt->execute();
        $intRes = $intStmt->get_result();
        if ($intRes && $row = $intRes->fetch_assoc()) {
            $seekerStats['interviews'] = (int) $row['c'];
        }
        $intStmt->close();
    }

    // Stats: saved jobs
    $saveStmt = $conn->prepare('SELECT COUNT(*) AS c FROM saved_jobs WHERE user_id = ?');
    if ($saveStmt) {
        $saveStmt->bind_param('i', $userId);
        $saveStmt->execute();
        $saveRes = $saveStmt->get_result();
        if ($saveRes && $row = $saveRes->fetch_assoc()) {
            $seekerStats['saved_jobs'] = (int) $row['c'];
        }
        $saveStmt->close();
    }

    $seekerStats['completion'] = $profileCompletion;

    // Recent applications (last 5) with employer profiles
    $appSql = "SELECT a.*, j.title, j.location,
                      COALESCE(ep.company_name, employer_user.username) AS company_name
               FROM applications a
               JOIN jobs j ON a.job_id = j.id
               JOIN users employer_user ON j.employer_id = employer_user.id
               LEFT JOIN employer_profiles ep ON j.employer_id = ep.user_id
               WHERE a.seeker_id = ?
               ORDER BY a.applied_at DESC
               LIMIT 5";
    $appStmt = $conn->prepare($appSql);
    if ($appStmt) {
        $appStmt->bind_param('i', $userId);
        $appStmt->execute();
        $appRes = $appStmt->get_result();
        $recentApplications = $appRes ? $appRes->fetch_all(MYSQLI_ASSOC) : [];
        $appStmt->close();
    }

    // Saved jobs cards
    $savedSql = "SELECT j.*,
                        COALESCE(ep.company_name, employer_user.username) AS company_name
                 FROM saved_jobs sj
                 JOIN jobs j ON sj.job_id = j.id
                 JOIN users employer_user ON j.employer_id = employer_user.id
                 LEFT JOIN employer_profiles ep ON j.employer_id = ep.user_id
                 WHERE sj.user_id = ?
                 ORDER BY sj.saved_at DESC
                 LIMIT 4";
    $savedStmt = $conn->prepare($savedSql);
    if ($savedStmt) {
        $savedStmt->bind_param('i', $userId);
        $savedStmt->execute();
        $savedRes = $savedStmt->get_result();
        $savedJobs = $savedRes ? $savedRes->fetch_all(MYSQLI_ASSOC) : [];
        $savedStmt->close();
    }

    // Upcoming interviews
    $intListSql = "SELECT i.*, j.title,
                          COALESCE(ep.company_name, employer_user.username) AS company_name
                   FROM interviews i
                   JOIN applications a ON i.application_id = a.id
                   JOIN jobs j ON a.job_id = j.id
                   JOIN users employer_user ON j.employer_id = employer_user.id
                   LEFT JOIN employer_profiles ep ON j.employer_id = ep.user_id
                   WHERE a.seeker_id = ? AND i.status = 'Scheduled'
                   ORDER BY i.interview_date ASC";
    $intListStmt = $conn->prepare($intListSql);
    if ($intListStmt) {
        $intListStmt->bind_param('i', $userId);
        $intListStmt->execute();
        $intListRes = $intListStmt->get_result();
        $upcomingInterviews = $intListRes ? $intListRes->fetch_all(MYSQLI_ASSOC) : [];
        $intListStmt->close();
    }
} elseif ($role === 'employer') {
    // Employer stats
    $activeStmt = $conn->prepare("SELECT COUNT(*) AS c FROM jobs WHERE employer_id = ? AND status = 'active'");
    if ($activeStmt) {
        $activeStmt->bind_param('i', $userId);
        $activeStmt->execute();
        $aRes = $activeStmt->get_result();
        if ($aRes && $row = $aRes->fetch_assoc()) {
            $employerStats['active_jobs'] = (int) $row['c'];
        }
        $activeStmt->close();
    }

    $appsStmt = $conn->prepare("SELECT COUNT(*) AS c
                                 FROM applications a
                                 JOIN jobs j ON a.job_id = j.id
                                 WHERE j.employer_id = ?");
    if ($appsStmt) {
        $appsStmt->bind_param('i', $userId);
        $appsStmt->execute();
        $appsRes = $appsStmt->get_result();
        if ($appsRes && $row = $appsRes->fetch_assoc()) {
            $employerStats['applications'] = (int) $row['c'];
        }
        $appsStmt->close();
    }

    $viewsStmt = $conn->prepare('SELECT COALESCE(SUM(views), 0) AS total_views FROM jobs WHERE employer_id = ?');
    if ($viewsStmt) {
        $viewsStmt->bind_param('i', $userId);
        $viewsStmt->execute();
        $viewsRes = $viewsStmt->get_result();
        if ($viewsRes && $row = $viewsRes->fetch_assoc()) {
            $employerStats['views'] = (int) $row['total_views'];
        }
        $viewsStmt->close();
    }

    // Jobs table with application counts
    $jobSql = "SELECT jobs.*,
                      (SELECT COUNT(*) FROM applications WHERE applications.job_id = jobs.id) AS app_count
               FROM jobs
               WHERE jobs.employer_id = ?
               ORDER BY jobs.created_at DESC";
    $jobStmt = $conn->prepare($jobSql);
    if ($jobStmt) {
        $jobStmt->bind_param('i', $userId);
        $jobStmt->execute();
        $result = $jobStmt->get_result();
        $employerJobs = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
        $jobStmt->close();
    }

    // Recent applications across all jobs
    $recentAppSql = "SELECT a.*, u.username AS seeker_name, j.title
                     FROM applications a
                     JOIN jobs j ON a.job_id = j.id
                     JOIN users u ON a.seeker_id = u.id
                     WHERE j.employer_id = ?
                     ORDER BY a.applied_at DESC
                     LIMIT 5";
    $raStmt = $conn->prepare($recentAppSql);
    if ($raStmt) {
        $raStmt->bind_param('i', $userId);
        $raStmt->execute();
        $raRes = $raStmt->get_result();
        $recentEmployerApplications = $raRes ? $raRes->fetch_all(MYSQLI_ASSOC) : [];
        $raStmt->close();
    }
} elseif ($role === 'admin') {
    // Simple admin stats
    $uRes = $conn->query('SELECT COUNT(*) AS c FROM users');
    if ($uRes && $row = $uRes->fetch_assoc()) {
        $adminStats['users'] = (int) $row['c'];
    }

    $jRes = $conn->query('SELECT COUNT(*) AS c FROM jobs');
    if ($jRes && $row = $jRes->fetch_assoc()) {
        $adminStats['jobs'] = (int) $row['c'];
    }

    $apRes = $conn->query('SELECT COUNT(*) AS c FROM applications');
    if ($apRes && $row = $apRes->fetch_assoc()) {
        $adminStats['applications'] = (int) $row['c'];
    }

    // Optional revenue if payments table exists
    $tblCheck = $conn->query("SHOW TABLES LIKE 'payments'");
    if ($tblCheck && $tblCheck->num_rows > 0) {
        $revRes = $conn->query('SELECT COALESCE(SUM(amount), 0) AS total FROM payments');
        if ($revRes && $row = $revRes->fetch_assoc()) {
            $adminStats['revenue'] = (float) $row['total'];
        }
    }
}

include 'includes/header.php';
?>

<main class="dashboard-page">
    <section class="welcome-section welcome-section--dashboard">
        <div class="welcome-inner">
            <?php if ($role === 'seeker') : ?>
                <div class="welcome-eyebrow">Dashboard</div>
                <h1>Welcome back, <?php echo htmlspecialchars($username); ?></h1>
                <p>Your profile is approximately <strong><?php echo (int) $seekerStats['completion']; ?>%</strong> complete.</p>
                <div class="welcome-actions">
                    <a class="btn-primary" href="jobs.php"><i class="fa-solid fa-magnifying-glass"></i> Browse jobs</a>
                    <a class="btn-secondary" href="profile.php"><i class="fa-regular fa-user"></i> Update profile</a>
                    <a class="btn-secondary" href="messages.php"><i class="fa-regular fa-envelope"></i> Messages</a>
                    <a class="btn-secondary" href="notifications.php"><i class="fa-regular fa-bell"></i> Notifications</a>
                </div>
            <?php elseif ($role === 'employer') : ?>
                <div class="welcome-eyebrow">Dashboard</div>
                <h1>Welcome back, <?php echo htmlspecialchars($username); ?></h1>
                <p>Review your open roles and candidates at a glance.</p>
                <div class="welcome-actions">
                    <a class="btn-primary" href="post-job.php"><i class="fa-solid fa-plus"></i> Post a job</a>
                    <a class="btn-secondary" href="seekers.php"><i class="fa-solid fa-users"></i> Browse seekers</a>
                    <a class="btn-secondary" href="messages.php"><i class="fa-regular fa-envelope"></i> Messages</a>
                    <a class="btn-secondary" href="notifications.php"><i class="fa-regular fa-bell"></i> Notifications</a>
                </div>
            <?php elseif ($role === 'admin') : ?>
                <div class="welcome-eyebrow">Admin</div>
                <h1>Admin Dashboard</h1>
                <p>Monitor platform-wide activity and jump into key tools.</p>
                <div class="welcome-actions">
                    <a class="btn-primary" href="admin.php"><i class="fa-solid fa-screwdriver-wrench"></i> Open admin panel</a>
                    <a class="btn-secondary" href="messages.php"><i class="fa-regular fa-envelope"></i> Messages</a>
                    <a class="btn-secondary" href="notifications.php"><i class="fa-regular fa-bell"></i> Notifications</a>
                </div>
            <?php else : ?>
                <div class="welcome-eyebrow">Dashboard</div>
                <h1>Welcome, <?php echo htmlspecialchars($username); ?></h1>
                <p>Manage your activity and keep track of your jobs in one place.</p>
                <div class="welcome-actions">
                    <a class="btn-primary" href="jobs.php"><i class="fa-solid fa-magnifying-glass"></i> Browse jobs</a>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <?php if ($role === 'seeker') : ?>
        <section class="home-stats dashboard-stats">
            <div class="stats-grid">
                <div class="stat-card">
                    <h3><?php echo (int) $seekerStats['applications']; ?></h3>
                    <p>Total Applications</p>
                </div>
                <div class="stat-card">
                    <h3><?php echo (int) $seekerStats['interviews']; ?></h3>
                    <p>Interviews Scheduled</p>
                </div>
                <div class="stat-card">
                    <h3><?php echo (int) $seekerStats['saved_jobs']; ?></h3>
                    <p>Jobs Saved</p>
                </div>
                <div class="stat-card">
                    <h3><?php echo (int) $seekerStats['completion']; ?>%</h3>
                    <p>Profile Completion</p>
                </div>
            </div>
        </section>

        <section class="dashboard-panel seeker-section">
            <div class="section-header">
                <div>
                    <h2>Recent Applications</h2>
                    <p>Your latest activity across all jobs</p>
                </div>
                <a class="btn-secondary" href="my-applications.php">View all applications</a>
            </div>
            <div class="table-wrapper">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Job Title</th>
                            <th>Company</th>
                            <th>Location</th>
                            <th>Applied On</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($recentApplications)) : ?>
                            <?php foreach ($recentApplications as $app) : ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($app['title']); ?></td>
                                    <td><?php echo htmlspecialchars($app['company_name']); ?></td>
                                    <td><?php echo htmlspecialchars($app['location']); ?></td>
                                    <td><?php echo htmlspecialchars(date('Y-m-d', strtotime($app['applied_at']))); ?></td>
                                    <td><span class="badge badge-outline"><?php echo htmlspecialchars(ucwords($app['status'])); ?></span></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else : ?>
                            <tr>
                                <td colspan="5">You haven't applied to any jobs yet.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <section class="dashboard-panel seeker-section">
            <div class="section-header">
                <div>
                    <h2>Saved Jobs</h2>
                    <p>Roles you've bookmarked to revisit</p>
                </div>
                <a class="btn-secondary" href="jobs.php">Browse more jobs</a>
            </div>
            <div class="job-grid">
                <?php if (!empty($savedJobs)) : ?>
                    <?php foreach ($savedJobs as $job) : ?>
                        <article class="job-card">
                            <h3><?php echo htmlspecialchars($job['title']); ?></h3>
                            <p class="company-name"><?php echo htmlspecialchars($job['company_name']); ?></p>
                            <p class="job-location"><?php echo htmlspecialchars($job['location']); ?><?php echo !empty($job['job_type']) ? ' · ' . htmlspecialchars($job['job_type']) : ''; ?></p>
                            <form action="" method="post" class="saved-job-actions">
                                <input type="hidden" name="remove_saved_id" value="<?php echo (int) $job['id']; ?>">
                                <button class="btn-secondary" type="submit">Remove</button>
                            </form>
                        </article>
                    <?php endforeach; ?>
                <?php else : ?>
                    <p>No saved jobs yet. Save jobs from the listings to track them here.</p>
                <?php endif; ?>
            </div>
        </section>

        <section class="dashboard-panel seeker-section">
            <div class="section-header">
                <div>
                    <h2>Upcoming Interviews</h2>
                    <p>Stay prepared for your next conversations</p>
                </div>
            </div>
            <div class="table-wrapper">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Job</th>
                            <th>Company</th>
                            <th>Date</th>
                            <th>Time</th>
                            <th>Mode</th>
                            <th>Location</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($upcomingInterviews)) : ?>
                            <?php foreach ($upcomingInterviews as $iv) : ?>
                                <?php
                                $dt = !empty($iv['interview_date']) ? strtotime($iv['interview_date']) : null;
                                $dateStr = $dt ? date('Y-m-d', $dt) : '';
                                $timeStr = $dt ? date('H:i', $dt) : '';
                                ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($iv['title']); ?></td>
                                    <td><?php echo htmlspecialchars($iv['company_name']); ?></td>
                                    <td><?php echo htmlspecialchars($dateStr); ?></td>
                                    <td><?php echo htmlspecialchars($timeStr); ?></td>
                                    <td><?php echo htmlspecialchars($iv['mode'] ?? ''); ?></td>
                                    <td><?php echo htmlspecialchars($iv['location'] ?? ''); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else : ?>
                            <tr>
                                <td colspan="6">No upcoming interviews scheduled.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
    <?php elseif ($role === 'employer') : ?>
        <section class="home-stats dashboard-stats">
            <div class="stats-grid">
                <div class="stat-card">
                    <h3><?php echo (int) $employerStats['active_jobs']; ?></h3>
                    <p>Active Jobs</p>
                </div>
                <div class="stat-card">
                    <h3><?php echo (int) $employerStats['applications']; ?></h3>
                    <p>Total Applications</p>
                </div>
                <div class="stat-card">
                    <h3><?php echo number_format((int) $employerStats['views']); ?></h3>
                    <p>Total Views</p>
                </div>
                <div class="stat-card">
                    <h3><i class="fa-solid fa-briefcase"></i></h3>
                    <p>Hire your next teammate</p>
                </div>
            </div>
        </section>

        <section class="dashboard-panel employer-section">
            <div class="section-header">
                <div>
                    <h2>Your Jobs</h2>
                    <p>Manage all roles you currently have open or closed</p>
                </div>
                <div class="dashboard-actions">
                    <a class="btn-primary dashboard-action-primary" href="post-job.php"><i class="fa-solid fa-plus"></i> Post New Job</a>
                    <a class="btn-secondary" href="#recent-applications">View All Applications</a>
                </div>
            </div>
            <div class="table-wrapper">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Job Title</th>
                            <th>Posted</th>
                            <th>Status</th>
                            <th>Applications</th>
                            <th>Views</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($employerJobs)) : ?>
                            <?php foreach ($employerJobs as $job) : ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($job['title']); ?></td>
                                    <td><?php echo !empty($job['created_at']) ? htmlspecialchars(date('Y-m-d', strtotime($job['created_at']))) : ''; ?></td>
                                    <td><span class="badge badge-outline"><?php echo htmlspecialchars(ucwords($job['status'] ?? '')); ?></span></td>
                                    <td>
                                        <a href="view-applications.php?job_id=<?php echo (int) $job['id']; ?>">
                                            <?php echo (int) ($job['app_count'] ?? 0); ?>
                                        </a>
                                    </td>
                                    <td><?php echo isset($job['views']) ? (int) $job['views'] : 0; ?></td>
                                    <td>
                                        <form method="post" class="inline-form">
                                            <input type="hidden" name="delete_job_id" value="<?php echo (int) $job['id']; ?>">
                                            <button class="btn-secondary" type="submit">Delete</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else : ?>
                            <tr>
                                <td colspan="6">No jobs posted yet.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <section class="dashboard-panel employer-section" id="recent-applications">
            <div class="section-header">
                <div>
                    <h2>Recent Applications</h2>
                    <p>Latest candidates across all of your jobs</p>
                </div>
            </div>
            <div class="table-wrapper">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Applicant</th>
                            <th>Job Title</th>
                            <th>Applied On</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($recentEmployerApplications)) : ?>
                            <?php foreach ($recentEmployerApplications as $app) : ?>
                                <?php
                                $appStatus = strtolower((string) ($app['status'] ?? ''));
                                $statusLabel = $appStatus !== '' ? ucwords($appStatus) : '';
                                if ($appStatus === 'approved') {
                                    $statusLabel = 'Approved';
                                } elseif ($appStatus === 'interview') {
                                    $statusLabel = 'Approved';
                                }
                                ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($app['seeker_name']); ?></td>
                                    <td><?php echo htmlspecialchars($app['title']); ?></td>
                                    <td><?php echo htmlspecialchars(date('Y-m-d', strtotime($app['applied_at']))); ?></td>
                                    <td><span class="badge badge-outline"><?php echo htmlspecialchars($statusLabel); ?></span></td>
                                    <td>
                                        <?php if ($appStatus === 'pending') : ?>
                                            <form method="post" class="inline-form">
                                                <input type="hidden" name="employer_app_action" value="view">
                                                <input type="hidden" name="application_id" value="<?php echo (int) $app['id']; ?>">
                                                <input type="hidden" name="job_id" value="<?php echo (int) ($app['job_id'] ?? 0); ?>">
                                                <button class="btn-secondary" type="submit">View</button>
                                            </form>
                                        <?php elseif ($appStatus === 'reviewed') : ?>
                                            <select class="employer-app-action" data-app-id="<?php echo (int) $app['id']; ?>"
                                                    data-job-title="<?php echo htmlspecialchars($app['title']); ?>"
                                                    data-company="<?php echo htmlspecialchars($employerCompanyName !== '' ? $employerCompanyName : $username); ?>">
                                                <option value="">Actions</option>
                                                <option value="reject">Reject</option>
                                                <option value="approve">Approve</option>
                                            </select>
                                        <?php else : ?>
                                            <span class="muted-text">—</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else : ?>
                            <tr>
                                <td colspan="5">No recent applications.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <?php if (!empty($recentEmployerApplications)) : ?>
            <form id="employerRejectForm" method="post" action="" style="display:none;">
                <input type="hidden" name="employer_app_action" value="reject">
                <input type="hidden" name="application_id" id="employerRejectAppId" value="">
            </form>

            <div class="modal-overlay" id="approveModal" style="display:none;">
                <div class="modal">
                    <div class="modal-header">
                        <h2 id="approveModalTitle">Schedule Interview</h2>
                        <button type="button" class="modal-close" id="closeApproveModal">&times;</button>
                    </div>
                    <form class="auth-form" method="post" action="">
                        <input type="hidden" name="employer_app_action" value="approve">
                        <input type="hidden" name="application_id" id="approveAppId" value="">
                        <div class="form-group">
                            <label for="approve_job">Job</label>
                            <input type="text" id="approve_job" value="" readonly>
                        </div>
                        <div class="form-group">
                            <label for="approve_company">Company</label>
                            <input type="text" id="approve_company" value="" readonly>
                        </div>
                        <div class="form-group">
                            <label for="approve_date">Date</label>
                            <input type="date" id="approve_date" name="interview_date" required>
                        </div>
                        <div class="form-group">
                            <label for="approve_time">Time</label>
                            <input type="time" id="approve_time" name="interview_time" required>
                        </div>
                        <div class="form-group">
                            <label for="approve_mode">Mode</label>
                            <select id="approve_mode" name="mode">
                                <option value="Online">Online</option>
                                <option value="Onsite">Onsite</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="approve_location">Location</label>
                            <input type="text" id="approve_location" name="location" placeholder="Video link or office address">
                        </div>
                        <button class="btn-primary" type="submit">Approve & Schedule</button>
                    </form>
                </div>
            </div>

            <script>
            document.addEventListener('DOMContentLoaded', function () {
                var selects = document.querySelectorAll('.employer-app-action');
                var rejectForm = document.getElementById('employerRejectForm');
                var rejectId = document.getElementById('employerRejectAppId');

                var modal = document.getElementById('approveModal');
                var closeBtn = document.getElementById('closeApproveModal');
                var approveId = document.getElementById('approveAppId');
                var jobInput = document.getElementById('approve_job');
                var companyInput = document.getElementById('approve_company');
                var titleEl = document.getElementById('approveModalTitle');

                function openModal(appId, jobTitle, companyName) {
                    if (!modal) return;
                    if (approveId) approveId.value = appId;
                    if (jobInput) jobInput.value = jobTitle || '';
                    if (companyInput) companyInput.value = companyName || '';
                    if (titleEl) titleEl.textContent = 'Schedule Interview: ' + (jobTitle || '');
                    modal.style.display = 'flex';
                }

                function closeModal() {
                    if (modal) {
                        modal.style.display = 'none';
                    }
                }

                if (selects) {
                    selects.forEach(function (sel) {
                        sel.addEventListener('change', function () {
                            var action = this.value;
                            var appId = this.getAttribute('data-app-id');
                            var jobTitle = this.getAttribute('data-job-title') || '';
                            var companyName = this.getAttribute('data-company') || '';

                            this.value = '';

                            if (action === 'reject') {
                                if (rejectId) rejectId.value = appId;
                                if (rejectForm) rejectForm.submit();
                            } else if (action === 'approve') {
                                openModal(appId, jobTitle, companyName);
                            }
                        });
                    });
                }

                if (closeBtn) {
                    closeBtn.addEventListener('click', closeModal);
                }

                if (modal) {
                    modal.addEventListener('click', function (e) {
                        if (e.target === modal) {
                            closeModal();
                        }
                    });
                }
            });
            </script>
        <?php endif; ?>
    <?php elseif ($role === 'admin') : ?>
        <section class="home-stats dashboard-stats">
            <div class="stats-grid">
                <div class="stat-card">
                    <h3><?php echo number_format($adminStats['users']); ?></h3>
                    <p>Total Users</p>
                </div>
                <div class="stat-card">
                    <h3><?php echo number_format($adminStats['jobs']); ?></h3>
                    <p>Total Jobs</p>
                </div>
                <div class="stat-card">
                    <h3><?php echo number_format($adminStats['applications']); ?></h3>
                    <p>Total Applications</p>
                </div>
                <div class="stat-card">
                    <h3><?php echo number_format($adminStats['revenue'], 2); ?></h3>
                    <p>Total Revenue</p>
                </div>
            </div>
        </section>

        <section class="dashboard-panel admin-section">
            <div class="section-header">
                <h2>Quick Links</h2>
            </div>
            <div class="dashboard-links">
                <a class="dashboard-link-card" href="admin.php?section=users">
                    <span class="dashboard-link-icon"><i class="fa-solid fa-users"></i></span>
                    <span class="dashboard-link-text">
                        <strong>Manage Users</strong>
                        <span class="muted-text">View, verify, and manage accounts</span>
                    </span>
                    <span class="dashboard-link-arrow" aria-hidden="true"><i class="fa-solid fa-arrow-right"></i></span>
                </a>
                <a class="dashboard-link-card" href="admin.php?section=jobs">
                    <span class="dashboard-link-icon"><i class="fa-solid fa-briefcase"></i></span>
                    <span class="dashboard-link-text">
                        <strong>Manage Jobs</strong>
                        <span class="muted-text">Moderate postings and activity</span>
                    </span>
                    <span class="dashboard-link-arrow" aria-hidden="true"><i class="fa-solid fa-arrow-right"></i></span>
                </a>
                <a class="dashboard-link-card" href="admin.php?section=reports">
                    <span class="dashboard-link-icon"><i class="fa-solid fa-chart-simple"></i></span>
                    <span class="dashboard-link-text">
                        <strong>View Reports</strong>
                        <span class="muted-text">Monitor platform health</span>
                    </span>
                    <span class="dashboard-link-arrow" aria-hidden="true"><i class="fa-solid fa-arrow-right"></i></span>
                </a>
                <a class="dashboard-link-card" href="admin.php?section=audit">
                    <span class="dashboard-link-icon"><i class="fa-solid fa-shield"></i></span>
                    <span class="dashboard-link-text">
                        <strong>Audit Logs</strong>
                        <span class="muted-text">Track critical actions</span>
                    </span>
                    <span class="dashboard-link-arrow" aria-hidden="true"><i class="fa-solid fa-arrow-right"></i></span>
                </a>
            </div>
        </section>
    <?php endif; ?>
</main>

<?php include 'includes/footer.php'; ?>
