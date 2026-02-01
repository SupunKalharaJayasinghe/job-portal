<?php
require_once 'core/db.php';
require_once 'core/functions.php';

checkLoggedIn();

$userId = $_SESSION['user_id'] ?? null;
$role = $_SESSION['role'] ?? '';

if ($role !== 'seeker') {
    header('Location: dashboard.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['remove_saved_id'])) {
    $jobId = (int) $_POST['remove_saved_id'];
    if ($jobId > 0) {
        $rmStmt = $conn->prepare('DELETE FROM saved_jobs WHERE job_id = ? AND user_id = ?');
        if ($rmStmt) {
            $rmStmt->bind_param('ii', $jobId, $userId);
            $rmStmt->execute();
            $rmStmt->close();
        }
    }
    header('Location: saved-jobs.php');
    exit();
}

$savedJobs = [];
$sql = "SELECT j.*, ep.company_name, ep.company_logo, sj.saved_at
        FROM saved_jobs sj
        JOIN jobs j ON sj.job_id = j.id
        JOIN employer_profiles ep ON j.employer_id = ep.user_id
        WHERE sj.user_id = ?
        ORDER BY sj.saved_at DESC";
$stmt = $conn->prepare($sql);
if ($stmt) {
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $res = $stmt->get_result();
    $savedJobs = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
    $stmt->close();
}

include 'includes/header.php';
?>

<main class="dashboard-page saved-jobs-page">
    <section class="section-header">
        <h1>Saved Jobs</h1>
        <p>Review and manage all jobs you have bookmarked.</p>
    </section>

    <section class="job-grid">
        <?php if (!empty($savedJobs)) : ?>
            <?php foreach ($savedJobs as $job) : ?>
                <?php
                $min = isset($job['salary_min']) ? (float) $job['salary_min'] : 0;
                $max = isset($job['salary_max']) ? (float) $job['salary_max'] : 0;
                $salaryText = '';
                if ($min > 0 && $max > 0) {
                    $salaryText = '$' . number_format($min) . ' - $' . number_format($max);
                } elseif ($min > 0) {
                    $salaryText = 'From $' . number_format($min);
                } elseif ($max > 0) {
                    $salaryText = 'Up to $' . number_format($max);
                }
                ?>
                <article class="job-card">
                    <div class="job-card-header">
                        <?php if (!empty($job['company_logo'])) : ?>
                            <div class="job-card-logo">
                                <img src="uploads/logos/<?php echo htmlspecialchars($job['company_logo']); ?>" alt="<?php echo htmlspecialchars($job['company_name']); ?>">
                            </div>
                        <?php endif; ?>
                        <div class="job-card-main">
                            <h3>
                                <a href="job-details.php?id=<?php echo (int) $job['id']; ?>">
                                    <?php echo htmlspecialchars($job['title']); ?>
                                </a>
                            </h3>
                            <p class="company-name"><?php echo htmlspecialchars($job['company_name']); ?></p>
                        </div>
                    </div>
                    <div class="job-meta">
                        <?php if (!empty($job['location'])) : ?>
                            <span class="badge"><?php echo htmlspecialchars($job['location']); ?></span>
                        <?php endif; ?>
                        <?php if (!empty($job['job_type'])) : ?>
                            <span class="badge badge-outline"><?php echo htmlspecialchars($job['job_type']); ?></span>
                        <?php endif; ?>
                    </div>
                    <?php if ($salaryText !== '') : ?>
                        <p class="job-salary"><?php echo htmlspecialchars($salaryText); ?></p>
                    <?php endif; ?>
                    <?php if (!empty($job['saved_at'])) : ?>
                        <p class="job-meta-line">Saved on <?php echo htmlspecialchars(date('Y-m-d', strtotime($job['saved_at']))); ?></p>
                    <?php endif; ?>
                    <div style="display:flex;gap:0.5rem;margin-top:0.5rem;flex-wrap:wrap;">
                        <a class="btn-secondary" href="job-details.php?id=<?php echo (int) $job['id']; ?>">View &amp; Apply</a>
                        <form action="" method="post">
                            <input type="hidden" name="remove_saved_id" value="<?php echo (int) $job['id']; ?>">
                            <button class="btn-secondary" type="submit">Remove</button>
                        </form>
                    </div>
                </article>
            <?php endforeach; ?>
        <?php else : ?>
            <p>No saved jobs yet. Browse jobs and use the save option to bookmark interesting roles.</p>
        <?php endif; ?>
    </section>
</main>

<?php include 'includes/footer.php'; ?>
