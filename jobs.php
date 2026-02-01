<?php
require_once 'core/db.php';
require_once 'core/functions.php';

$keywords = sanitizeInput($_GET['keywords'] ?? '');
$location = sanitizeInput($_GET['location'] ?? '');
$category = sanitizeInput($_GET['category'] ?? '');

$selectedJobTypes = $_GET['job_type'] ?? [];
if (!is_array($selectedJobTypes)) {
    $selectedJobTypes = $selectedJobTypes !== '' ? [$selectedJobTypes] : [];
}
$selectedJobTypes = array_values(array_filter(array_map('sanitizeInput', $selectedJobTypes)));

$selectedExperience = $_GET['experience'] ?? [];
if (!is_array($selectedExperience)) {
    $selectedExperience = $selectedExperience !== '' ? [$selectedExperience] : [];
}
$selectedExperience = array_values(array_filter(array_map('sanitizeInput', $selectedExperience)));

$minSalary = isset($_GET['min_salary']) && $_GET['min_salary'] !== '' ? (int) $_GET['min_salary'] : null;
$maxSalary = isset($_GET['max_salary']) && $_GET['max_salary'] !== '' ? (int) $_GET['max_salary'] : null;

$perPage = 10;
$currentPage = isset($_GET['page']) && ctype_digit((string) $_GET['page']) && (int) $_GET['page'] > 0
    ? (int) $_GET['page']
    : 1;
$offset = ($currentPage - 1) * $perPage;

// Fixed option sets for filters
$categoryOptions = ['IT', 'Marketing', 'Design', 'Finance', 'Healthcare', 'Other'];
$jobTypeOptions = [
    'Full-time' => 'Full Time',
    'Part-time' => 'Part Time',
    'Contract' => 'Contract',
    'Internship' => 'Internship',
];
$experienceOptions = ['Intern', 'Junior', 'Mid', 'Senior', 'Lead'];

$jobs = [];
$totalJobs = 0;
$totalPages = 1;

// Base query joining employer profiles and only active jobs
$baseSql = " FROM jobs
             JOIN employer_profiles ON jobs.employer_id = employer_profiles.user_id
             WHERE jobs.status = 'active'";

$conditions = [];
$params = [];
$types = '';

if ($keywords !== '') {
    $conditions[] = "(jobs.title LIKE ? OR jobs.description LIKE ? OR employer_profiles.company_name LIKE ?)";
    $like = '%' . $keywords . '%';
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $types .= 'sss';
}

if ($location !== '') {
    $conditions[] = "jobs.location LIKE ?";
    $params[] = '%' . $location . '%';
    $types .= 's';
}

if ($category !== '') {
    $conditions[] = "jobs.category = ?";
    $params[] = $category;
    $types .= 's';
}

if (!empty($selectedJobTypes)) {
    $placeholders = implode(',', array_fill(0, count($selectedJobTypes), '?'));
    $conditions[] = "jobs.job_type IN ($placeholders)";
    foreach ($selectedJobTypes as $jt) {
        $params[] = $jt;
        $types .= 's';
    }
}

if (!empty($selectedExperience)) {
    $placeholders = implode(',', array_fill(0, count($selectedExperience), '?'));
    $conditions[] = "jobs.experience_required IN ($placeholders)";
    foreach ($selectedExperience as $exp) {
        $params[] = $exp;
        $types .= 's';
    }
}

if ($minSalary !== null) {
    $conditions[] = "jobs.salary_min >= ?";
    $params[] = $minSalary;
    $types .= 'i';
}

if ($maxSalary !== null) {
    $conditions[] = "jobs.salary_max <= ?";
    $params[] = $maxSalary;
    $types .= 'i';
}

$whereSql = '';
if (!empty($conditions)) {
    $whereSql = ' AND ' . implode(' AND ', $conditions);
}

// Count total jobs for pagination
$countSql = 'SELECT COUNT(*) AS total' . $baseSql . $whereSql;
if ($types !== '') {
    $countStmt = $conn->prepare($countSql);
    if ($countStmt) {
        $countStmt->bind_param($types, ...$params);
        $countStmt->execute();
        $countRes = $countStmt->get_result();
        if ($countRes && $row = $countRes->fetch_assoc()) {
            $totalJobs = (int) $row['total'];
        }
        $countStmt->close();
    }
} else {
    $countRes = $conn->query($countSql);
    if ($countRes && $row = $countRes->fetch_assoc()) {
        $totalJobs = (int) $row['total'];
    }
}

if ($totalJobs > 0) {
    $totalPages = (int) ceil($totalJobs / $perPage);
    if ($currentPage > $totalPages) {
        $currentPage = $totalPages;
        $offset = ($currentPage - 1) * $perPage;
    }
}

// Fetch paginated jobs
$dataSql = 'SELECT jobs.*,
                   employer_profiles.company_name,
                   employer_profiles.company_logo'
    . $baseSql . $whereSql .
    ' ORDER BY jobs.created_at DESC
      LIMIT ? OFFSET ?';

$dataParams = $params;
$dataTypes = $types . 'ii';
$dataParams[] = $perPage;
$dataParams[] = $offset;

$stmt = $conn->prepare($dataSql);
if ($stmt) {
    $stmt->bind_param($dataTypes, ...$dataParams);
    $stmt->execute();
    $jobResult = $stmt->get_result();
    if ($jobResult && $jobResult->num_rows > 0) {
        $jobs = $jobResult->fetch_all(MYSQLI_ASSOC);
    }
    $stmt->close();
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

include 'includes/header.php';
?>

<main class="jobs-page">
    <section class="section-header">
        <h1>Browse Jobs</h1>
        <p>Find opportunities that match your skills and preferences.</p>
    </section>

    <section class="layout-with-filters">
        <aside class="filter-panel">
            <h3>Filters</h3>
            <form method="get" action="jobs.php" class="list-grid">
                <div class="filter-group">
                    <label for="f_keyword">Keywords</label>
                    <input id="f_keyword" name="keywords" type="text" placeholder="e.g., Designer, Laravel" value="<?php echo htmlspecialchars($keywords); ?>">
                </div>
                <div class="filter-group">
                    <label for="f_location">Location</label>
                    <input id="f_location" name="location" type="text" placeholder="City or Remote" value="<?php echo htmlspecialchars($location); ?>">
                </div>
                <div class="filter-group">
                    <label for="f_category">Category</label>
                    <select id="f_category" name="category">
                        <option value="">Any</option>
                        <?php foreach ($categoryOptions as $catOption) : ?>
                            <option value="<?php echo htmlspecialchars($catOption); ?>" <?php echo ($category === $catOption) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($catOption); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="filter-group">
                    <label>Job Type</label>
                    <div class="checkbox-group">
                        <?php foreach ($jobTypeOptions as $value => $label) : ?>
                            <label class="checkbox-inline">
                                <input type="checkbox" name="job_type[]" value="<?php echo htmlspecialchars($value); ?>" <?php echo in_array($value, $selectedJobTypes, true) ? 'checked' : ''; ?>>
                                <span><?php echo htmlspecialchars($label); ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="filter-group">
                    <label>Experience Level</label>
                    <div class="checkbox-group">
                        <?php foreach ($experienceOptions as $exp) : ?>
                            <label class="checkbox-inline">
                                <input type="checkbox" name="experience[]" value="<?php echo htmlspecialchars($exp); ?>" <?php echo in_array($exp, $selectedExperience, true) ? 'checked' : ''; ?>>
                                <span><?php echo htmlspecialchars($exp); ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="filter-group">
                    <label>Salary Range ($)</label>
                    <div class="filter-salary-range">
                        <input type="number" name="min_salary" min="0" placeholder="Min" value="<?php echo $minSalary !== null ? htmlspecialchars((string) $minSalary) : ''; ?>">
                        <span>-</span>
                        <input type="number" name="max_salary" min="0" placeholder="Max" value="<?php echo $maxSalary !== null ? htmlspecialchars((string) $maxSalary) : ''; ?>">
                    </div>
                </div>
                <button class="btn-primary" type="submit">Apply Filters</button>
            </form>
        </aside>

        <div class="list-grid">
            <?php if (!empty($jobs)) : ?>
                <?php foreach ($jobs as $job) : ?>
                    <?php
                    $min = isset($job['salary_min']) ? (float) $job['salary_min'] : 0;
                    $max = isset($job['salary_max']) ? (float) $job['salary_max'] : 0;
                    $salaryText = 'Negotiable';
                    if ($min > 0 && $max > 0) {
                        $salaryText = '$' . number_format($min) . ' - $' . number_format($max);
                    } elseif ($min > 0) {
                        $salaryText = 'From $' . number_format($min);
                    } elseif ($max > 0) {
                        $salaryText = 'Up to $' . number_format($max);
                    } elseif (!empty($job['salary'])) {
                        $salaryText = $job['salary'];
                    }
                    $postedAgo = !empty($job['created_at']) ? formatRelativeTime($job['created_at']) : '';
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
                            <?php if (!empty($_SESSION['user_id']) && (($_SESSION['role'] ?? '') === 'seeker')) : ?>
                                <button class="save-job-button" type="button" title="Save job">
                                    <i class="fa-regular fa-heart"></i>
                                </button>
                            <?php endif; ?>
                        </div>
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
                        <?php if (!empty($salaryText)) : ?>
                            <p class="job-salary"><?php echo htmlspecialchars($salaryText); ?></p>
                        <?php endif; ?>
                        <p class="job-meta-line">
                            <?php if ($postedAgo !== '') : ?>
                                <span class="muted-text"><?php echo htmlspecialchars($postedAgo); ?></span>
                            <?php endif; ?>
                        </p>
                        <a class="btn-secondary" href="job-details.php?id=<?php echo (int) $job['id']; ?>">View Details</a>
                    </article>
                <?php endforeach; ?>
            <?php else : ?>
                <article class="job-card">
                    <h3>No jobs found</h3>
                    <p class="company-name">Check back soon or adjust your filters.</p>
                </article>
            <?php endif; ?>
        </div>

        <?php if ($totalPages > 1) : ?>
            <?php
            $baseQuery = [
                'keywords' => $keywords,
                'location' => $location,
                'category' => $category,
            ];
            if ($minSalary !== null) {
                $baseQuery['min_salary'] = $minSalary;
            }
            if ($maxSalary !== null) {
                $baseQuery['max_salary'] = $maxSalary;
            }
            if (!empty($selectedJobTypes)) {
                foreach ($selectedJobTypes as $jt) {
                    $baseQuery['job_type'][] = $jt;
                }
            }
            if (!empty($selectedExperience)) {
                foreach ($selectedExperience as $exp) {
                    $baseQuery['experience'][] = $exp;
                }
            }
            ?>
            <nav class="pagination">
                <?php if ($currentPage > 1) : ?>
                    <?php $prevQuery = $baseQuery; $prevQuery['page'] = $currentPage - 1; ?>
                    <a class="page-link" href="jobs.php?<?php echo htmlspecialchars(http_build_query($prevQuery)); ?>">Previous</a>
                <?php endif; ?>

                <?php for ($p = 1; $p <= $totalPages; $p++) : ?>
                    <?php $pageQuery = $baseQuery; $pageQuery['page'] = $p; ?>
                    <a class="page-link<?php echo $p === $currentPage ? ' active' : ''; ?>" href="jobs.php?<?php echo htmlspecialchars(http_build_query($pageQuery)); ?>">
                        <?php echo $p; ?>
                    </a>
                <?php endfor; ?>

                <?php if ($currentPage < $totalPages) : ?>
                    <?php $nextQuery = $baseQuery; $nextQuery['page'] = $currentPage + 1; ?>
                    <a class="page-link" href="jobs.php?<?php echo htmlspecialchars(http_build_query($nextQuery)); ?>">Next</a>
                <?php endif; ?>
            </nav>
        <?php endif; ?>
    </section>
</main>

<?php include 'includes/footer.php'; ?>
