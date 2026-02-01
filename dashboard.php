
<?php
require_once 'core/db.php';
require_once 'core/functions.php';

checkLoggedIn();

$userId = $_SESSION['user_id'] ?? null;
$username = $_SESSION['username'] ?? 'User';
$role = $_SESSION['role'] ?? '';

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

// Handle remove saved job for seekers
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
    $appSql = "SELECT a.*, j.title, j.location, ep.company_name
               FROM applications a
               JOIN jobs j ON a.job_id = j.id
               JOIN employer_profiles ep ON j.employer_id = ep.user_id
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
    $savedSql = "SELECT j.*, ep.company_name
                 FROM saved_jobs sj
                 JOIN jobs j ON sj.job_id = j.id
                 JOIN employer_profiles ep ON j.employer_id = ep.user_id
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
    $intListSql = "SELECT i.*, j.title, ep.company_name
                   FROM interviews i
                   JOIN applications a ON i.application_id = a.id
                   JOIN jobs j ON a.job_id = j.id
                   JOIN employer_profiles ep ON j.employer_id = ep.user_id
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
    <section class="welcome-section">
        <?php if ($role === 'seeker') : ?>
            <h1>Welcome back, <?php echo htmlspecialchars($username); ?></h1>
            <p>Your profile is approximately <strong><?php echo (int) $seekerStats['completion']; ?>%</strong> complete.</p>
        <?php elseif ($role === 'employer') : ?>
            <h1>Welcome back, <?php echo htmlspecialchars($username); ?></h1>
            <p>Review your open roles and candidates at a glance.</p>
        <?php elseif ($role === 'admin') : ?>
            <h1>Admin Dashboard</h1>
            <p>Monitor platform-wide activity and jump into key tools.</p>
        <?php else : ?>
            <h1>Welcome, <?php echo htmlspecialchars($username); ?></h1>
            <p>Manage your activity and keep track of your jobs in one place.</p>
        <?php endif; ?>
    </section>

    <?php if ($role === 'seeker') : ?>
        <section class="home-stats">
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

        <section class="seeker-section">
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

        <section class="seeker-section">
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
                            <form action="" method="post" style="margin-top:0.5rem;">
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

        <section class="seeker-section">
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
        <section class="home-stats">
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
                    <h3><i class="fa-solid fa-briefcase" style="color:var(--primary);"></i></h3>
                    <p>Hire your next teammate</p>
                </div>
            </div>
        </section>

        <section class="employer-section">
            <div class="section-header">
                <div>
                    <h2>Your Jobs</h2>
                    <p>Manage all roles you currently have open or closed</p>
                </div>
                <div style="display:flex;gap:0.5rem;flex-wrap:wrap;">
                    <a class="btn-primary" href="post-job.php">Post New Job</a>
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
                                        <form method="post" style="display:inline;">
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

        <section class="employer-section" id="recent-applications">
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
                                <tr>
                                    <td><?php echo htmlspecialchars($app['seeker_name']); ?></td>
                                    <td><?php echo htmlspecialchars($app['title']); ?></td>
                                    <td><?php echo htmlspecialchars(date('Y-m-d', strtotime($app['applied_at']))); ?></td>
                                    <td><span class="badge badge-outline"><?php echo htmlspecialchars(ucwords($app['status'])); ?></span></td>
                                    <td>
                                        <a class="btn-secondary" href="view-profile.php?id=<?php echo (int) $app['seeker_id']; ?>">View</a>
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
    <?php elseif ($role === 'admin') : ?>
        <section class="home-stats">
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

        <section class="employer-section">
            <div class="section-header">
                <h2>Quick Links</h2>
            </div>
            <div class="job-grid">
                <a class="btn-primary" href="admin.php?section=users">Manage Users</a>
                <a class="btn-secondary" href="admin.php?section=jobs">Manage Jobs</a>
                <a class="btn-secondary" href="admin.php?section=reports">View Reports</a>
                <a class="btn-secondary" href="admin.php?section=audit">Audit Logs</a>
            </div>
        </section>
    <?php endif; ?>
</main>

<?php include 'includes/footer.php'; ?>
