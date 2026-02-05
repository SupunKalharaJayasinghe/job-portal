<?php
require_once 'core/db.php';
require_once 'core/functions.php';

$jobId = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($jobId <= 0) {
    header('Location: jobs.php');
    exit();
}

// Fetch job with employer profile and user info
$jobStmt = $conn->prepare(
    "SELECT jobs.*,
            COALESCE(employer_profiles.company_name, users.username) AS company_name,
            employer_profiles.company_logo,
            users.id AS employer_user_id
     FROM jobs
     JOIN users ON jobs.employer_id = users.id
     LEFT JOIN employer_profiles ON jobs.employer_id = employer_profiles.user_id
     WHERE jobs.id = ?
     LIMIT 1"
);
$jobStmt->bind_param('i', $jobId);
$jobStmt->execute();
$jobResult = $jobStmt->get_result();
$job = $jobResult ? $jobResult->fetch_assoc() : null;
$jobStmt->close();

if (!$job) {
    header('Location: jobs.php');
    exit();
}

// Increment job views so employer dashboard stats stay up to date
$viewsUpdate = $conn->prepare('UPDATE jobs SET views = COALESCE(views, 0) + 1 WHERE id = ?');
if ($viewsUpdate) {
    $viewsUpdate->bind_param('i', $jobId);
    $viewsUpdate->execute();
    $viewsUpdate->close();

    // Reflect the increment in the current page view
    if (isset($job['views'])) {
        $job['views'] = (int) $job['views'] + 1;
    } else {
        $job['views'] = 1;
    }
}

// Load job tags
$jobTags = [];
if (tableExists($conn, 'job_tag_map') && tableExists($conn, 'job_tags')) {
    $tagStmt = $conn->prepare(
        'SELECT job_tags.name
         FROM job_tag_map
         JOIN job_tags ON job_tag_map.tag_id = job_tags.id
         WHERE job_tag_map.job_id = ?'
    );
    if ($tagStmt) {
        $tagStmt->bind_param('i', $jobId);
        $tagStmt->execute();
        $tagRes = $tagStmt->get_result();
        if ($tagRes && $tagRes->num_rows > 0) {
            while ($tagRow = $tagRes->fetch_assoc()) {
                $jobTags[] = $tagRow['name'];
            }
        }
        $tagStmt->close();
    }
}

$isLoggedIn = !empty($_SESSION['user_id']);
$userId = $_SESSION['user_id'] ?? null;
$role = $_SESSION['role'] ?? '';
$applyMessage = '';
$applyError = '';
$hasApplied = false;

if ($isLoggedIn && $role === 'seeker') {
    $checkStmt = $conn->prepare("SELECT id FROM applications WHERE job_id = ? AND seeker_id = ? LIMIT 1");
    $checkStmt->bind_param('ii', $jobId, $userId);
    $checkStmt->execute();
    $checkRes = $checkStmt->get_result();
    $hasApplied = $checkRes && $checkRes->num_rows > 0;
    $checkStmt->close();
}

// Handle application submission for seekers via modal form
if ($isLoggedIn && $role === 'seeker' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($hasApplied) {
        $applyMessage = 'Already Applied';
    } else {
        $coverLetter = sanitizeInput($_POST['cover_letter'] ?? '');

        if (isset($_FILES['resume']) && $_FILES['resume']['error'] === UPLOAD_ERR_OK) {
            $fileTmp = $_FILES['resume']['tmp_name'];
            $fileName = $_FILES['resume']['name'];
            $fileSize = (int) $_FILES['resume']['size'];
            $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

            if ($ext !== 'pdf') {
                $applyError = 'Only PDF files are allowed.';
            } elseif ($fileSize > 5 * 1024 * 1024) {
                $applyError = 'File size must be 5MB or less.';
            } else {
                $uploadsDir = __DIR__ . '/uploads/resumes';
                if (!is_dir($uploadsDir)) {
                    mkdir($uploadsDir, 0777, true);
                }
                $newName = 'resume_' . $userId . '_' . $jobId . '_' . time() . '.pdf';
                $destPath = $uploadsDir . '/' . $newName;

                if (move_uploaded_file($fileTmp, $destPath)) {
                    $status = 'pending';
                    $insertSql = tableHasColumn($conn, 'applications', 'cover_letter')
                        ? "INSERT INTO applications (job_id, seeker_id, resume_file, cover_letter, status) VALUES (?, ?, ?, ?, ?)"
                        : "INSERT INTO applications (job_id, seeker_id, resume_file, status) VALUES (?, ?, ?, ?)";
                    $insertStmt = $conn->prepare($insertSql);
                    if ($insertStmt) {
                        if (tableHasColumn($conn, 'applications', 'cover_letter')) {
                            $insertStmt->bind_param('iisss', $jobId, $userId, $newName, $coverLetter, $status);
                        } else {
                            $insertStmt->bind_param('iiss', $jobId, $userId, $newName, $status);
                        }
                        if ($insertStmt->execute()) {
                            $applyMessage = 'Application submitted successfully.';
                            $hasApplied = true;

                            $employerId = isset($job['employer_user_id']) ? (int) $job['employer_user_id'] : 0;
                            $seekerName = (string) ($_SESSION['username'] ?? 'A candidate');
                            $jobTitle = (string) ($job['title'] ?? 'your job');
                            createNotification($conn, $employerId, 'New application', $seekerName . ' applied for: ' . $jobTitle);
                        } else {
                            $applyError = 'Could not save your application.';
                        }
                        $insertStmt->close();
                    } else {
                        $applyError = 'Could not prepare application statement.';
                    }
                } else {
                    $applyError = 'File upload failed. Please try again.';
                }
            }
        } else {
            $applyError = 'Please upload your resume (PDF).';
        }
    }
}

if (!function_exists('formatRelativeTime')) {
    function formatRelativeTime(string $datetime): string
    {
        $timestamp = strtotime($datetime);
        if (!$timestamp) {
            return '';
        }

        $diff = time() - $timestamp;
        if ($diff < 60) {
            return 'Just now';
        }
        if ($diff < 3600) {
            $minutes = (int) floor($diff / 60);
            return $minutes === 1 ? '1 minute ago' : $minutes . ' minutes ago';
        }
        if ($diff < 86400) {
            $hours = (int) floor($diff / 3600);
            return $hours === 1 ? '1 hour ago' : $hours . ' hours ago';
        }
        $days = (int) floor($diff / 86400);
        if ($days < 30) {
            return $days === 1 ? '1 day ago' : $days . ' days ago';
        }

        return date('M j, Y', $timestamp);
    }
}

// Derived display fields
$salaryText = 'Negotiable';
$min = isset($job['salary_min']) ? (float) $job['salary_min'] : 0;
$max = isset($job['salary_max']) ? (float) $job['salary_max'] : 0;
if ($min > 0 && $max > 0) {
    $salaryText = '$' . number_format($min) . ' - $' . number_format($max);
} elseif ($min > 0) {
    $salaryText = 'From $' . number_format($min);
} elseif ($max > 0) {
    $salaryText = 'Up to $' . number_format($max);
}

$postedAgo = !empty($job['created_at']) ? formatRelativeTime($job['created_at']) : '';
$viewsCount = isset($job['views']) ? (int) $job['views'] : 0;

$companyName = (string) ($job['company_name'] ?? 'Company');
$logoName = !empty($job['company_logo']) ? basename((string) $job['company_logo']) : '';
$logoDiskPath = $logoName !== '' ? (__DIR__ . '/uploads/logos/' . $logoName) : '';
$hasLogo = $logoDiskPath !== '' && is_file($logoDiskPath);

include 'includes/header.php';
?>

<main class="job-details-page">
    <section class="job-hero">
        <div class="job-hero-inner">
            <a class="job-back" href="jobs.php">
                <i class="fa-solid fa-arrow-left"></i>
                Back to Jobs
            </a>
            <div class="job-hero-card">
                <div class="job-hero-header">
                    <?php if ($hasLogo) : ?>
                        <div class="job-hero-logo">
                            <img src="uploads/logos/<?php echo htmlspecialchars($logoName); ?>" alt="<?php echo htmlspecialchars($companyName); ?>">
                        </div>
                    <?php else : ?>
                        <div class="job-hero-logo job-hero-logo--placeholder" aria-hidden="true">
                            <span><?php echo htmlspecialchars(strtoupper(substr($companyName !== '' ? $companyName : 'C', 0, 1))); ?></span>
                        </div>
                    <?php endif; ?>

                    <div class="job-hero-main">
                        <h1 class="job-hero-title"><?php echo htmlspecialchars($job['title']); ?></h1>
                        <div class="job-hero-company">
                            <a href="view-profile.php?id=<?php echo (int) $job['employer_user_id']; ?>">
                                <?php echo htmlspecialchars($companyName); ?>
                            </a>
                        </div>
                        <div class="job-hero-badges">
                            <?php if (!empty($job['location'])) : ?>
                                <span class="badge"><?php echo htmlspecialchars($job['location']); ?></span>
                            <?php endif; ?>
                            <?php if (!empty($job['job_type'])) : ?>
                                <span class="badge badge-outline"><?php echo htmlspecialchars($job['job_type']); ?></span>
                            <?php endif; ?>
                            <?php if (!empty($job['experience_required'])) : ?>
                                <span class="badge badge-muted"><?php echo htmlspecialchars($job['experience_required']); ?></span>
                            <?php endif; ?>
                            <?php if (!empty($job['category'])) : ?>
                                <span class="badge badge-muted"><?php echo htmlspecialchars($job['category']); ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="job-hero-facts">
                    <div class="job-fact">
                        <span class="job-fact-label">Salary</span>
                        <span class="job-fact-value"><?php echo htmlspecialchars($salaryText); ?></span>
                    </div>
                    <div class="job-fact">
                        <span class="job-fact-label">Posted</span>
                        <span class="job-fact-value"><?php echo htmlspecialchars($postedAgo !== '' ? $postedAgo : 'Recently'); ?></span>
                    </div>
                    <div class="job-fact">
                        <span class="job-fact-label">Views</span>
                        <span class="job-fact-value"><?php echo (int) $viewsCount; ?></span>
                    </div>
                </div>

                <?php if (!empty($jobTags)) : ?>
                    <div class="job-hero-tags">
                        <?php foreach ($jobTags as $tag) : ?>
                            <span class="tag"><?php echo htmlspecialchars($tag); ?></span>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <section class="job-details layout-with-filters">
        <article class="job-details-main">
            <div class="content-card">
                <div class="content-card-header">
                    <h2>Job Description</h2>
                </div>
                <div class="content-card-body job-prose">
                    <?php echo htmlspecialchars((string) ($job['description'] ?? '')); ?>
                </div>
            </div>

            <div class="content-card role-details">
                <div class="content-card-header">
                    <h2>Role Details</h2>
                </div>
                <div class="content-card-body">
                    <div class="detail-grid">
                        <?php if (!empty($job['location'])) : ?>
                            <div class="detail-item">
                                <div class="detail-label">Location</div>
                                <div class="detail-value"><?php echo htmlspecialchars($job['location']); ?></div>
                            </div>
                        <?php endif; ?>

                        <?php if (!empty($job['job_type'])) : ?>
                            <div class="detail-item">
                                <div class="detail-label">Type</div>
                                <div class="detail-value"><?php echo htmlspecialchars($job['job_type']); ?></div>
                            </div>
                        <?php endif; ?>

                        <?php if (!empty($job['category'])) : ?>
                            <div class="detail-item">
                                <div class="detail-label">Category</div>
                                <div class="detail-value"><?php echo htmlspecialchars($job['category']); ?></div>
                            </div>
                        <?php endif; ?>

                        <?php if (!empty($job['experience_required'])) : ?>
                            <div class="detail-item">
                                <div class="detail-label">Experience</div>
                                <div class="detail-value"><?php echo htmlspecialchars($job['experience_required']); ?></div>
                            </div>
                        <?php endif; ?>

                        <div class="detail-item">
                            <div class="detail-label">Salary</div>
                            <div class="detail-value"><?php echo htmlspecialchars($salaryText); ?></div>
                        </div>

                        <div class="detail-item">
                            <div class="detail-label">Posted</div>
                            <div class="detail-value"><?php echo htmlspecialchars($postedAgo !== '' ? $postedAgo : 'Recently'); ?></div>
                        </div>

                        <div class="detail-item">
                            <div class="detail-label">Views</div>
                            <div class="detail-value"><?php echo (int) $viewsCount; ?></div>
                        </div>
                    </div>
                </div>
            </div>
        </article>

        <aside class="job-sidebar">
            <div class="sidebar-card sidebar-actions">
                <?php if (!empty($applyMessage)) : ?>
                    <p class="success-text"><?php echo htmlspecialchars($applyMessage); ?></p>
                <?php endif; ?>
                <?php if (!empty($applyError)) : ?>
                    <p class="error-text"><?php echo htmlspecialchars($applyError); ?></p>
                <?php endif; ?>

                <?php if (!$isLoggedIn) : ?>
                    <a class="btn-primary" href="login.php">Login to Apply</a>
                <?php elseif ($role === 'seeker') : ?>
                    <?php if ($hasApplied) : ?>
                        <span class="badge badge-muted">Already Applied</span>
                    <?php else : ?>
                        <button class="btn-primary" type="button" id="openApplyModal">Apply Now</button>
                    <?php endif; ?>
                    <form action="save-job.php" method="post" class="sidebar-form">
                        <input type="hidden" name="job_id" value="<?php echo (int) $jobId; ?>">
                        <input type="hidden" name="return_to" value="<?php echo htmlspecialchars($_SERVER['REQUEST_URI']); ?>">
                        <button class="btn-secondary" type="submit">
                            <i class="fa-regular fa-heart"></i> Save Job
                        </button>
                    </form>
                <?php else : ?>
                    <p class="muted-text">Employers cannot apply to jobs.</p>
                <?php endif; ?>
            </div>

            <div class="sidebar-card company-card">
                <div class="company-card-header">
                    <?php if ($hasLogo) : ?>
                        <div class="company-logo">
                            <img src="uploads/logos/<?php echo htmlspecialchars($logoName); ?>" alt="<?php echo htmlspecialchars($companyName); ?>">
                        </div>
                    <?php else : ?>
                        <div class="company-logo company-logo--placeholder" aria-hidden="true">
                            <span><?php echo htmlspecialchars(strtoupper(substr($companyName !== '' ? $companyName : 'C', 0, 1))); ?></span>
                        </div>
                    <?php endif; ?>
                    <div>
                        <h3><?php echo htmlspecialchars($companyName); ?></h3>
                        <a class="company-profile-link" href="view-profile.php?id=<?php echo (int) $job['employer_user_id']; ?>">View Company Profile</a>
                    </div>
                </div>

                <div class="company-card-body">
                    <?php if (!empty($job['website'])) : ?>
                        <a class="company-website" href="<?php echo htmlspecialchars($job['website']); ?>" target="_blank" rel="noopener">
                            <i class="fa-solid fa-arrow-up-right-from-square"></i>
                            Visit Website
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </aside>
    </section>

    <?php if ($isLoggedIn && $role === 'seeker' && !$hasApplied) : ?>
        <div class="modal-overlay" id="applyModal" style="display:none;">
            <div class="modal">
                <div class="modal-header">
                    <h2>Apply to <?php echo htmlspecialchars($job['title']); ?></h2>
                    <button type="button" class="modal-close" id="closeApplyModal">&times;</button>
                </div>
                <form class="auth-form" action="" method="post" enctype="multipart/form-data">
                    <div class="form-group">
                        <label for="resume">Upload Resume (PDF, max 5MB)</label>
                        <input type="file" id="resume" name="resume" accept="application/pdf" required>
                    </div>
                    <div class="form-group">
                        <label for="cover_letter">Cover Letter (optional)</label>
                        <textarea id="cover_letter" name="cover_letter" rows="5" placeholder="Share a short note about why you are a great fit."></textarea>
                    </div>
                    <button class="btn-primary" type="submit">Submit Application</button>
                </form>
            </div>
        </div>
    <?php endif; ?>
</main>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var openBtn = document.getElementById('openApplyModal');
    var modal = document.getElementById('applyModal');
    var closeBtn = document.getElementById('closeApplyModal');

    if (openBtn && modal && closeBtn) {
        openBtn.addEventListener('click', function () {
            modal.style.display = 'flex';
        });

        closeBtn.addEventListener('click', function () {
            modal.style.display = 'none';
        });

        modal.addEventListener('click', function (e) {
            if (e.target === modal) {
                modal.style.display = 'none';
            }
        });
    }
});
</script>

<?php include 'includes/footer.php'; ?>
