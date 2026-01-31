<?php
require_once 'core/db.php';
require_once 'core/functions.php';

$keywords = trim($_GET['keywords'] ?? '');
$location = trim($_GET['location'] ?? '');
$category = trim($_GET['category'] ?? '');

$jobs = [];
$sql = "SELECT jobs.*, users.username AS company_name FROM jobs JOIN users ON jobs.employer_id = users.id WHERE 1=1";
$params = [];
$types = '';

if ($keywords !== '') {
    $sql .= " AND (jobs.title LIKE ? OR jobs.description LIKE ?)";
    $like = '%' . $keywords . '%';
    $params[] = $like;
    $params[] = $like;
    $types .= 'ss';
}

if ($location !== '') {
    $sql .= " AND jobs.location LIKE ?";
    $params[] = '%' . $location . '%';
    $types .= 's';
}

if ($category !== '') {
    $sql .= " AND jobs.category = ?";
    $params[] = $category;
    $types .= 's';
}

$sql .= " ORDER BY jobs.created_at DESC";

if (!empty($params)) {
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $jobResult = $stmt->get_result();
    if ($jobResult && $jobResult->num_rows > 0) {
        $jobs = $jobResult->fetch_all(MYSQLI_ASSOC);
    }
    $stmt->close();
} else {
    $jobResult = $conn->query($sql);
    if ($jobResult && $jobResult->num_rows > 0) {
        $jobs = $jobResult->fetch_all(MYSQLI_ASSOC);
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
                    <label for="f_keyword">Keyword</label>
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
                        <option value="Engineering" <?php if ($category === 'Engineering') echo 'selected'; ?>>Engineering</option>
                        <option value="Product & Design" <?php if ($category === 'Product & Design') echo 'selected'; ?>>Product & Design</option>
                        <option value="Marketing" <?php if ($category === 'Marketing') echo 'selected'; ?>>Marketing</option>
                        <option value="Data & AI" <?php if ($category === 'Data & AI') echo 'selected'; ?>>Data & AI</option>
                        <option value="Operations" <?php if ($category === 'Operations') echo 'selected'; ?>>Operations</option>
                        <option value="Sales" <?php if ($category === 'Sales') echo 'selected'; ?>>Sales</option>
                    </select>
                </div>
                <button class="btn-primary" type="submit">Apply Filters</button>
            </form>
        </aside>

        <div class="list-grid">
            <?php if (!empty($jobs)) : ?>
                <?php foreach ($jobs as $job) : ?>
                    <article class="job-card">
                        <h3><?php echo htmlspecialchars($job['title']); ?></h3>
                        <p class="company-name"><?php echo htmlspecialchars($job['company_name']); ?></p>
                        <p class="job-salary"><?php echo htmlspecialchars($job['salary']); ?></p>
                        <p class="job-location"><?php echo htmlspecialchars($job['location']); ?></p>
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
    </section>
</main>

<?php include 'includes/footer.php'; ?>
