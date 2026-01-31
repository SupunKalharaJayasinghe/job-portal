
<?php
require_once 'core/db.php';
require_once 'core/functions.php';

checkLoggedIn();

$userId = $_SESSION['user_id'] ?? null;
$role = $_SESSION['role'] ?? '';

if ($role !== 'employer') {
    header('Location: dashboard.php?error=Access%20Denied');
    exit();
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = sanitizeInput($_POST['job_title'] ?? '');
    $description = sanitizeInput($_POST['job_description'] ?? '');
    $category = sanitizeInput($_POST['job_category'] ?? '');
    $location = sanitizeInput($_POST['job_location'] ?? '');
    $salary = sanitizeInput($_POST['job_salary'] ?? '');

    if ($title && $description && $category && $location && $salary) {
        $stmt = $conn->prepare("INSERT INTO jobs (title, description, category, location, salary, employer_id, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");
        $stmt->bind_param('sssssi', $title, $description, $category, $location, $salary, $userId);
        if ($stmt->execute()) {
            $stmt->close();
            header('Location: dashboard.php?created=1');
            exit();
        } else {
            $error = 'Could not save the job. Please try again.';
        }
        $stmt->close();
    } else {
        $error = 'All fields are required.';
    }
}

include 'includes/header.php';
?>

<main class="post-job-page">
    <section class="form-section">
        <div class="section-header">
            <h1>Post a New Job</h1>
            <p>Share the details of your open role with job seekers.</p>
        </div>
        <?php if (!empty($error)) : ?>
            <p class="error-text"><?php echo htmlspecialchars($error); ?></p>
        <?php endif; ?>
        <form class="job-form" action="" method="post">
            <div class="form-group">
                <label for="job_title">Job Title</label>
                <input type="text" id="job_title" name="job_title" placeholder="e.g. Backend Engineer" required>
            </div>
            <div class="form-group">
                <label for="job_description">Job Description</label>
                <textarea id="job_description" name="job_description" rows="6" placeholder="Describe the role and responsibilities" required></textarea>
            </div>
            <div class="form-group">
                <label for="job_category">Category</label>
                <input type="text" id="job_category" name="job_category" placeholder="e.g. Engineering" required>
            </div>
            <div class="form-group">
                <label for="job_location">Location</label>
                <input type="text" id="job_location" name="job_location" placeholder="City or Remote" required>
            </div>
            <div class="form-group">
                <label for="job_salary">Salary Range</label>
                <input type="text" id="job_salary" name="job_salary" placeholder="e.g. LKR 150,000 - 200,000" required>
            </div>
            <button class="btn-primary" type="submit">Publish Job</button>
        </form>
    </section>
</main>

<?php include 'includes/footer.php'; ?>
