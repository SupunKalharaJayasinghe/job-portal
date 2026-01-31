<?php
require_once 'core/db.php';
require_once 'core/functions.php';

$jobId = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($jobId <= 0) {
    header('Location: jobs.php');
    exit();
}

// Fetch job with employer username
$jobStmt = $conn->prepare("SELECT jobs.*, users.username AS company_name FROM jobs JOIN users ON jobs.employer_id = users.id WHERE jobs.id = ? LIMIT 1");
$jobStmt->bind_param('i', $jobId);
$jobStmt->execute();
$jobResult = $jobStmt->get_result();
$job = $jobResult ? $jobResult->fetch_assoc() : null;
$jobStmt->close();

if (!$job) {
    header('Location: jobs.php');
    exit();
}

$isLoggedIn = !empty($_SESSION['user_id']);
$userId = $_SESSION['user_id'] ?? null;
$role = $_SESSION['role'] ?? '';
$applyMessage = '';
$applyError = '';

// Handle application submission for seekers
if ($isLoggedIn && $role === 'seeker' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    // Check if already applied
    $checkStmt = $conn->prepare("SELECT id FROM applications WHERE job_id = ? AND seeker_id = ? LIMIT 1");
    $checkStmt->bind_param('ii', $jobId, $userId);
    $checkStmt->execute();
    $hasApplied = $checkStmt->get_result()->num_rows > 0;
    $checkStmt->close();

    if ($hasApplied) {
        $applyMessage = 'Already Applied';
    } else {
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
                    // status uses lower-case ENUM values defined in schema
                    $status = 'pending';
                    $insertStmt = $conn->prepare("INSERT INTO applications (job_id, seeker_id, resume_file, status) VALUES (?, ?, ?, ?)");
                    $insertStmt->bind_param('iiss', $jobId, $userId, $newName, $status);
                    if ($insertStmt->execute()) {
                        $applyMessage = 'Application submitted successfully.';
                    } else {
                        $applyError = 'Could not save your application.';
                    }
                    $insertStmt->close();
                } else {
                    $applyError = 'File upload failed. Please try again.';
                }
            }
        } else {
            $applyError = 'Please upload your resume (PDF).';
        }
    }
}

include 'includes/header.php';
?>

<main class="job-details-page">
    <section class="job-details">
        <div class="job-header">
            <h1><?php echo htmlspecialchars($job['title']); ?></h1>
            <p class="company-name"><?php echo htmlspecialchars($job['company_name']); ?> · <?php echo htmlspecialchars($job['location']); ?></p>
            <p class="job-salary"><?php echo htmlspecialchars($job['salary']); ?></p>
        </div>
        <div class="job-description">
            <h2>Full Description</h2>
            <p><?php echo nl2br(htmlspecialchars($job['description'])); ?></p>
        </div>
        <div class="job-requirements">
            <h2>Category</h2>
            <ul>
                <li><?php echo htmlspecialchars($job['category']); ?></li>
            </ul>
        </div>

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
                <?php if ($applyMessage === 'Already Applied') : ?>
                    <p>You have already applied to this job.</p>
                <?php else : ?>
                    <form class="auth-form" action="" method="post" enctype="multipart/form-data">
                        <div class="form-group">
                            <label for="resume">Upload Resume (PDF, max 5MB)</label>
                            <input type="file" id="resume" name="resume" accept="application/pdf" required>
                        </div>
                        <button class="btn-primary" type="submit">Apply Now</button>
                    </form>
                <?php endif; ?>
            <?php else : ?>
                <p>Employers cannot apply to jobs.</p>
            <?php endif; ?>
        </div>
    </section>
</main>

<?php include 'includes/footer.php'; ?>
