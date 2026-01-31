
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

$employerJobs = [];
if ($role === 'employer') {
    $jobStmt = $conn->prepare("SELECT j.id, j.title, j.category, j.location, j.salary, j.created_at, COUNT(a.id) AS app_count
                                FROM jobs j
                                LEFT JOIN applications a ON a.job_id = j.id
                                WHERE j.employer_id = ?
                                GROUP BY j.id, j.title, j.category, j.location, j.salary, j.created_at
                                ORDER BY j.created_at DESC");
    $jobStmt->bind_param('i', $userId);
    $jobStmt->execute();
    $result = $jobStmt->get_result();
    $employerJobs = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
    $jobStmt->close();
}

$seekerApplications = [];
if ($role === 'seeker') {
    $appStmt = $conn->prepare("SELECT applications.*, jobs.title, jobs.location, users.username AS company
                               FROM applications
                               JOIN jobs ON applications.job_id = jobs.id
                               JOIN users ON jobs.employer_id = users.id
                               WHERE applications.seeker_id = ?
                               ORDER BY applications.applied_at DESC");
    $appStmt->bind_param('i', $userId);
    $appStmt->execute();
    $appRes = $appStmt->get_result();
    $seekerApplications = $appRes ? $appRes->fetch_all(MYSQLI_ASSOC) : [];
    $appStmt->close();
}

include 'includes/header.php';
?>

<main class="dashboard-page">
    <section class="welcome-section">
        <h1>Welcome, <?php echo htmlspecialchars($username); ?></h1>
        <p>Manage your activity and keep track of your jobs in one place.</p>
    </section>

    <?php if ($role === 'employer') : ?>
    <section class="employer-section">
        <div class="section-header">
            <h2>Your Posted Jobs</h2>
            <a class="btn-primary" href="post-job.php">Post New Job</a>
        </div>
        <div class="table-wrapper">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Job Title</th>
                        <th>Category</th>
                        <th>Location</th>
                        <th>Salary</th>
                        <th>Applications</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($employerJobs)) : ?>
                        <?php foreach ($employerJobs as $job) : ?>
                            <tr>
                                <td><?php echo htmlspecialchars($job['title']); ?></td>
                                <td><?php echo htmlspecialchars($job['category']); ?></td>
                                <td><?php echo htmlspecialchars($job['location']); ?></td>
                                <td><?php echo htmlspecialchars($job['salary']); ?></td>
                                <td>
                                    <a href="view-applications.php?job_id=<?php echo (int) $job['id']; ?>">
                                        <?php echo (int) $job['app_count']; ?>
                                    </a>
                                </td>
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
    <?php endif; ?>

    <?php if ($role === 'seeker') : ?>
    <section class="seeker-section">
        <div class="section-header">
            <h2>Jobs You Applied For</h2>
            <a class="btn-secondary" href="profile.php">Upload Resume</a>
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
                    <?php if (!empty($seekerApplications)) : ?>
                        <?php foreach ($seekerApplications as $app) : ?>
                            <tr>
                                <td><?php echo htmlspecialchars($app['title']); ?></td>
                                <td><?php echo htmlspecialchars($app['company']); ?></td>
                                <td><?php echo htmlspecialchars($app['location']); ?></td>
                                <td><?php echo htmlspecialchars(date('Y-m-d', strtotime($app['applied_at']))); ?></td>
                                <td><?php echo htmlspecialchars($app['status']); ?></td>
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
    <?php endif; ?>
</main>

<?php include 'includes/footer.php'; ?>
