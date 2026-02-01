
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

// Options aligned with filters and schema
$categoryOptions = ['IT', 'Marketing', 'Design', 'Finance', 'Healthcare', 'Other'];
$jobTypeOptions = ['Full-time', 'Part-time', 'Contract', 'Internship'];
$experienceOptions = ['Intern', 'Junior', 'Mid', 'Senior', 'Lead'];
$statusOptions = [
    'draft' => 'Draft',
    'active' => 'Active',
];

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = sanitizeInput($_POST['job_title'] ?? '');
    $description = sanitizeInput($_POST['job_description'] ?? '');
    $category = sanitizeInput($_POST['job_category'] ?? '');
    $location = sanitizeInput($_POST['job_location'] ?? '');
    $jobType = sanitizeInput($_POST['job_type'] ?? '');
    $experienceRequired = sanitizeInput($_POST['experience_required'] ?? '');

    $salaryMinRaw = $_POST['salary_min'] ?? '';
    $salaryMaxRaw = $_POST['salary_max'] ?? '';
    $salaryMin = $salaryMinRaw !== '' ? (int) $salaryMinRaw : null;
    $salaryMax = $salaryMaxRaw !== '' ? (int) $salaryMaxRaw : null;

    $statusRaw = strtolower(sanitizeInput($_POST['status'] ?? 'draft'));
    $status = array_key_exists($statusRaw, $statusOptions) ? $statusRaw : 'draft';

    $tagsInput = $_POST['tags'] ?? '';
    $tags = [];
    if ($tagsInput !== '') {
        $rawTags = explode(',', $tagsInput);
        foreach ($rawTags as $tag) {
            $tag = trim($tag);
            if ($tag !== '') {
                $tags[] = $tag;
            }
        }
        $tags = array_values(array_unique($tags));
    }

    // Basic validation
    if ($title === '' || $description === '' || $category === '' || $location === '' || $jobType === '' || $experienceRequired === '' || $salaryMin === null || $salaryMax === null) {
        $error = 'Please fill in all required fields.';
    } elseif (!in_array($category, $categoryOptions, true)) {
        $error = 'Invalid category selected.';
    } elseif (!in_array($jobType, $jobTypeOptions, true)) {
        $error = 'Invalid job type selected.';
    } elseif (!in_array($experienceRequired, $experienceOptions, true)) {
        $error = 'Invalid experience level selected.';
    } elseif ($salaryMin < 0 || $salaryMax < 0 || $salaryMin > $salaryMax) {
        $error = 'Salary range is not valid.';
    }

    if ($error === '') {
        $stmt = $conn->prepare("INSERT INTO jobs (employer_id, title, description, category, location, job_type, experience_required, salary_min, salary_max, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        if ($stmt) {
            $stmt->bind_param(
                'issssssiis',
                $userId,
                $title,
                $description,
                $category,
                $location,
                $jobType,
                $experienceRequired,
                $salaryMin,
                $salaryMax,
                $status
            );
            if ($stmt->execute()) {
                $jobId = $stmt->insert_id;
                $stmt->close();

                // Handle tags using job_tags and job_tag_map tables
                if (!empty($tags) && $jobId > 0) {
                    $tagSelect = $conn->prepare('SELECT id FROM job_tags WHERE name = ? LIMIT 1');
                    $tagInsert = $conn->prepare('INSERT INTO job_tags (name) VALUES (?)');
                    $mapInsert = $conn->prepare('INSERT INTO job_tag_map (job_id, tag_id) VALUES (?, ?)');

                    if ($tagSelect && $tagInsert && $mapInsert) {
                        foreach ($tags as $tagName) {
                            $cleanTag = sanitizeInput($tagName);
                            if ($cleanTag === '') {
                                continue;
                            }

                            $tagId = null;
                            $tagSelect->bind_param('s', $cleanTag);
                            $tagSelect->execute();
                            $tagRes = $tagSelect->get_result();
                            if ($tagRes && $row = $tagRes->fetch_assoc()) {
                                $tagId = (int) $row['id'];
                            } else {
                                $tagInsert->bind_param('s', $cleanTag);
                                if ($tagInsert->execute()) {
                                    $tagId = $tagInsert->insert_id;
                                }
                            }

                            if ($tagId) {
                                $mapInsert->bind_param('ii', $jobId, $tagId);
                                $mapInsert->execute();
                            }
                        }

                        $tagSelect->close();
                        $tagInsert->close();
                        $mapInsert->close();
                    }
                }

                header('Location: dashboard.php?created=1');
                exit();
            } else {
                $error = 'Could not save the job. Please try again.';
                $stmt->close();
            }
        } else {
            $error = 'Could not prepare job statement.';
        }
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
                <label for="job_category">Category</label>
                <select id="job_category" name="job_category" required>
                    <option value="">Select category</option>
                    <?php foreach ($categoryOptions as $cat) : ?>
                        <option value="<?php echo htmlspecialchars($cat); ?>"><?php echo htmlspecialchars($cat); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="job_location">Location</label>
                <input type="text" id="job_location" name="job_location" placeholder="City or Remote" required>
            </div>

            <div class="form-group">
                <label for="job_type">Job Type</label>
                <select id="job_type" name="job_type" required>
                    <option value="">Select type</option>
                    <?php foreach ($jobTypeOptions as $opt) : ?>
                        <option value="<?php echo htmlspecialchars($opt); ?>"><?php echo htmlspecialchars($opt); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="experience_required">Experience Required</label>
                <select id="experience_required" name="experience_required" required>
                    <option value="">Select level</option>
                    <?php foreach ($experienceOptions as $exp) : ?>
                        <option value="<?php echo htmlspecialchars($exp); ?>"><?php echo htmlspecialchars($exp); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label>Salary Range (per month)</label>
                <div class="filter-salary-range">
                    <input type="number" id="salary_min" name="salary_min" min="0" placeholder="Min" required>
                    <span>-</span>
                    <input type="number" id="salary_max" name="salary_max" min="0" placeholder="Max" required>
                </div>
            </div>

            <div class="form-group">
                <label for="job_description">Job Description</label>
                <textarea id="job_description" name="job_description" rows="6" placeholder="Describe the role, responsibilities, and ideal candidate" required></textarea>
            </div>

            <div class="form-group">
                <label for="tags">Tags (comma-separated)</label>
                <input type="text" id="tags" name="tags" placeholder="e.g. PHP, Laravel, Remote, Fintech">
            </div>

            <div class="form-group">
                <label for="status">Status</label>
                <select id="status" name="status" required>
                    <?php foreach ($statusOptions as $value => $label) : ?>
                        <option value="<?php echo htmlspecialchars($value); ?>"><?php echo htmlspecialchars($label); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <button class="btn-primary" type="submit">Publish Job</button>
        </form>
    </section>
</main>

<?php include 'includes/footer.php'; ?>
