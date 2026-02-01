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
            employer_profiles.company_name,
            employer_profiles.company_logo,
            users.id AS employer_user_id,
            users.website
     FROM jobs
     JOIN employer_profiles ON jobs.employer_id = employer_profiles.user_id
     JOIN users ON jobs.employer_id = users.id
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

// Load job tags
$jobTags = [];
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
                    // Optional cover_letter column if present in schema
                    $insertStmt = $conn->prepare("INSERT INTO applications (job_id, seeker_id, resume_file, cover_letter, status) VALUES (?, ?, ?, ?, ?)");
                    if ($insertStmt) {
                        $insertStmt->bind_param('iisss', $jobId, $userId, $newName, $coverLetter, $status);
                        if ($insertStmt->execute()) {
                            $applyMessage = 'Application submitted successfully.';
                            $hasApplied = true;
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

include 'includes/header.php';
?>

<main class="job-details-page">
    <section class="section-header">
        <h1><?php echo htmlspecialchars($job['title']); ?></h1>
        <p>at <?php echo htmlspecialchars($job['company_name']); ?></p>
    </section>

    <section class="job-details layout-with-filters">
        <article class="job-details-main">
            <header class="job-header">
                <div class="job-header-main">
                    <?php if (!empty($job['company_logo'])) : ?>
                        <div class="job-header-logo">
                            <img src="uploads/logos/<?php echo htmlspecialchars($job['company_logo']); ?>" alt="<?php echo htmlspecialchars($job['company_name']); ?>">
                        </div>
                    <?php endif; ?>
                    <div class="job-header-text">
                        <h2><?php echo htmlspecialchars($job['title']); ?></h2>
                        <p class="company-name">
                            <a href="view-profile.php?id=<?php echo (int) $job['employer_user_id']; ?>">
                                <?php echo htmlspecialchars($job['company_name']); ?>
                            </a>
                        </p>
                        <div class="job-meta">
                            <?php if (!empty($job['location'])) : ?>
                                <span class="badge"><?php echo htmlspecialchars($job['location']); ?></span>
                            <?php endif; ?>
                            <?php if (!empty($job['job_type'])) : ?>
                                <span class="badge badge-outline"><?php echo htmlspecialchars($job['job_type']); ?></span>
                            <?php endif; ?>
                            <?php if (!empty($job['experience_required'])) : ?>
                                <span class="badge badge-muted"><?php echo htmlspecialchars($job['experience_required']); ?></span>
                            <?php endif; ?>
                        </div>
                        <p class="job-salary"><?php echo htmlspecialchars($salaryText); ?></p>
                        <p class="job-meta-line">
                            <?php if ($postedAgo !== '') : ?>
                                <span class="muted-text"><?php echo htmlspecialchars($postedAgo); ?></span>
                            <?php endif; ?>
                            <?php if ($viewsCount > 0) : ?>
                                <span class="muted-text"> · <?php echo (int) $viewsCount; ?> views</span>
                            <?php endif; ?>
                        </p>
                    </div>
                </div>
            </header>

            <section class="job-description">
                <h2>Full Description</h2>
                <p><?php echo nl2br(htmlspecialchars($job['description'])); ?></p>
            </section>

            <?php if (!empty($jobTags)) : ?>
                <section class="job-tags">
                    <h3>Tags</h3>
                    <div class="tag-list">
                        <?php foreach ($jobTags as $tag) : ?>
                            <span class="badge badge-outline"><?php echo htmlspecialchars($tag); ?></span>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endif; ?>
        </article>

        <aside class="job-sidebar">
            <div class="job-apply">
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
                    <button class="btn-secondary" type="button" style="margin-top:0.5rem;">
                        <i class="fa-regular fa-heart"></i> Save Job
                    </button>
                <?php else : ?>
                    <p>Employers cannot apply to jobs.</p>
                <?php endif; ?>
            </div>

            <div class="company-card">
                <h3>About the Company</h3>
                <?php if (!empty($job['company_logo'])) : ?>
                    <div class="company-logo">
                        <img src="uploads/logos/<?php echo htmlspecialchars($job['company_logo']); ?>" alt="<?php echo htmlspecialchars($job['company_name']); ?>">
                    </div>
                <?php endif; ?>
                <p class="company-name"><?php echo htmlspecialchars($job['company_name']); ?></p>
                <?php if (!empty($job['website'])) : ?>
                    <p>
                        <a href="<?php echo htmlspecialchars($job['website']); ?>" target="_blank" rel="noopener">Visit Website</a>
                    </p>
                <?php endif; ?>
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
