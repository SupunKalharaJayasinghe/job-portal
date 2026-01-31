<?php
require_once 'core/db.php';
require_once 'core/functions.php';

$keywords = isset($_GET['keywords']) ? sanitizeInput($_GET['keywords']) : '';
$skillsFilter = isset($_GET['skills']) ? sanitizeInput($_GET['skills']) : '';

$seekers = [];

$sql = "SELECT id, username, email, headline, bio, skills, phone
        FROM users
        WHERE role = 'seeker'
          AND ((headline IS NOT NULL AND headline <> '')
               OR (bio IS NOT NULL AND bio <> ''))";

$params = [];
$types = '';

if ($keywords !== '') {
    $sql .= " AND (username LIKE ? OR headline LIKE ? OR bio LIKE ?)";
    $kwLike = '%' . $keywords . '%';
    $params[] = $kwLike;
    $params[] = $kwLike;
    $params[] = $kwLike;
    $types .= 'sss';
}

if ($skillsFilter !== '') {
    $sql .= " AND skills LIKE ?";
    $skillsLike = '%' . $skillsFilter . '%';
    $params[] = $skillsLike;
    $types .= 's';
}

$sql .= " ORDER BY (headline IS NOT NULL AND headline <> '') DESC, username ASC";

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$res = $stmt->get_result();
$seekers = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
$stmt->close();

include 'includes/header.php';
?>

<main class="seekers-page">
    <section class="section-header">
        <h1>Job Seekers</h1>
        <p>Discover top talent ready to join your team.</p>
    </section>

    <section class="layout-with-filters">
        <aside class="filter-panel">
            <h3>Filters</h3>
            <form class="filter-form" action="seekers.php" method="get">
                <div class="filter-group">
                    <label for="s_keyword">Keyword</label>
                    <input id="s_keyword" name="keywords" type="text" placeholder="e.g., React, Marketing" value="<?php echo htmlspecialchars($keywords); ?>">
                </div>
                <div class="filter-group">
                    <label for="s_skills">Skills</label>
                    <input id="s_skills" name="skills" type="text" placeholder="e.g., React, SQL" value="<?php echo htmlspecialchars($skillsFilter); ?>">
                </div>
                <div class="filter-group">
                    <label>Experience Level</label>
                    <div class="filter-badges">
                        <span class="badge">Junior</span>
                        <span class="badge">Mid</span>
                        <span class="badge">Senior</span>
                        <span class="badge">Lead</span>
                    </div>
                </div>
                <div class="filter-group">
                    <label>Availability</label>
                    <div class="filter-badges">
                        <span class="badge">Immediate</span>
                        <span class="badge">1 Month</span>
                        <span class="badge">Open to Offers</span>
                    </div>
                </div>
                <button class="btn-primary" type="submit">Apply Filters</button>
            </form>
        </aside>

        <div class="list-grid">
            <?php if (!empty($seekers)) : ?>
                <?php foreach ($seekers as $s) : ?>
                    <?php
                    $headline = !empty($s['headline']) ? $s['headline'] : 'Open to new opportunities';
                    $skillsText = is_string($s['skills']) ? $s['skills'] : '';
                    if (strlen($skillsText) > 80) {
                        $skillsText = substr($skillsText, 0, 77) . '...';
                    }
                    $phoneText = is_string($s['phone']) ? $s['phone'] : '';
                    ?>
                    <article class="job-card">
                        <h3><?php echo htmlspecialchars($s['username']); ?></h3>
                        <p class="company-name"><?php echo htmlspecialchars($headline); ?></p>
                        <?php if ($phoneText !== '') : ?>
                            <p class="job-location"><?php echo htmlspecialchars($phoneText); ?></p>
                        <?php endif; ?>
                        <?php if ($skillsText !== '') : ?>
                            <p class="job-salary"><?php echo htmlspecialchars($skillsText); ?></p>
                        <?php endif; ?>
                        <a class="btn-secondary" href="view-profile.php?id=<?php echo (int) $s['id']; ?>">View Profile</a>
                    </article>
                <?php endforeach; ?>
            <?php else : ?>
                <p>No candidates found. Try adjusting your filters.</p>
            <?php endif; ?>
        </div>
    </section>
</main>

<?php include 'includes/footer.php'; ?>
