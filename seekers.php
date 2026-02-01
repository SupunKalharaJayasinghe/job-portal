<?php
require_once 'core/db.php';
require_once 'core/functions.php';

checkLoggedIn();

$viewerRole = $_SESSION['role'] ?? '';
if ($viewerRole !== 'employer') {
    header('Location: dashboard.php?error=Access%20Denied');
    exit();
}

$keywords = isset($_GET['keywords']) ? sanitizeInput($_GET['keywords']) : '';
$locationFilter = isset($_GET['location']) ? sanitizeInput($_GET['location']) : '';
$skillsFilter = isset($_GET['skills']) ? sanitizeInput($_GET['skills']) : '';

$experienceFilter = $_GET['experience'] ?? [];
if (!is_array($experienceFilter)) {
    $experienceFilter = $experienceFilter !== '' ? [$experienceFilter] : [];
}
$experienceFilter = array_values(array_filter(array_map('sanitizeInput', $experienceFilter)));

$availabilityFilter = $_GET['availability'] ?? [];
if (!is_array($availabilityFilter)) {
    $availabilityFilter = $availabilityFilter !== '' ? [$availabilityFilter] : [];
}
$availabilityFilter = array_values(array_filter(array_map('sanitizeInput', $availabilityFilter)));

$seekers = [];

$sql = "SELECT users.id,
               users.username,
               users.email,
               seeker_profiles.headline,
               seeker_profiles.bio,
               seeker_profiles.phone,
               seeker_profiles.location,
               seeker_profiles.experience_level,
               seeker_profiles.years_experience,
               seeker_profiles.availability
        FROM users
        JOIN seeker_profiles ON users.id = seeker_profiles.user_id
        WHERE users.role = 'seeker'
          AND seeker_profiles.profile_visibility = 'public'";

$params = [];
$types = '';

if ($keywords !== '') {
    $sql .= " AND (users.username LIKE ? OR seeker_profiles.headline LIKE ? OR seeker_profiles.bio LIKE ?)";
    $kwLike = '%' . $keywords . '%';
    $params[] = $kwLike;
    $params[] = $kwLike;
    $params[] = $kwLike;
    $types .= 'sss';
}

if ($locationFilter !== '') {
    $sql .= " AND seeker_profiles.location LIKE ?";
    $locLike = '%' . $locationFilter . '%';
    $params[] = $locLike;
    $types .= 's';
}

if (!empty($experienceFilter)) {
    $placeholders = implode(',', array_fill(0, count($experienceFilter), '?'));
    $sql .= " AND seeker_profiles.experience_level IN ($placeholders)";
    foreach ($experienceFilter as $exp) {
        $params[] = $exp;
        $types .= 's';
    }
}

if (!empty($availabilityFilter)) {
    $placeholders = implode(',', array_fill(0, count($availabilityFilter), '?'));
    $sql .= " AND seeker_profiles.availability IN ($placeholders)";
    foreach ($availabilityFilter as $av) {
        $params[] = $av;
        $types .= 's';
    }
}

if ($skillsFilter !== '') {
    // Match skills via seeker_skills
    $sql .= " AND EXISTS (SELECT 1 FROM seeker_skills WHERE seeker_skills.user_id = users.id AND seeker_skills.skill_name LIKE ? )";
    $skillsLike = '%' . $skillsFilter . '%';
    $params[] = $skillsLike;
    $types .= 's';
}

$sql .= " ORDER BY (seeker_profiles.headline IS NOT NULL AND seeker_profiles.headline <> '') DESC, users.username ASC";

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$res = $stmt->get_result();
$seekers = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
$stmt->close();

// Preload top skills for resulting seekers
$seekerIds = array_column($seekers, 'id');
$skillsByUser = [];
if (!empty($seekerIds)) {
    $placeholders = implode(',', array_fill(0, count($seekerIds), '?'));
    $typeStr = str_repeat('i', count($seekerIds));
    $skillSql = "SELECT * FROM seeker_skills WHERE user_id IN ($placeholders) ORDER BY id ASC";
    $skillStmt = $conn->prepare($skillSql);
    if ($skillStmt) {
        $skillStmt->bind_param($typeStr, ...$seekerIds);
        $skillStmt->execute();
        $skillRes = $skillStmt->get_result();
        while ($row = $skillRes->fetch_assoc()) {
            $uid = (int) $row['user_id'];
            $label = $row['skill_name'] ?? ($row['name'] ?? ($row['skill'] ?? ''));
            if ($label === '') {
                continue;
            }
            if (!isset($skillsByUser[$uid])) {
                $skillsByUser[$uid] = [];
            }
            $skillsByUser[$uid][] = $label;
        }
        $skillStmt->close();
    }
}

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
                    <label for="s_keyword">Keywords</label>
                    <input id="s_keyword" name="keywords" type="text" placeholder="Name, headline, or bio" value="<?php echo htmlspecialchars($keywords); ?>">
                </div>
                <div class="filter-group">
                    <label for="s_location">Location</label>
                    <input id="s_location" name="location" type="text" placeholder="City or Country" value="<?php echo htmlspecialchars($locationFilter); ?>">
                </div>
                <div class="filter-group">
                    <label for="s_skills">Skills</label>
                    <input id="s_skills" name="skills" type="text" placeholder="e.g., React, SQL" value="<?php echo htmlspecialchars($skillsFilter); ?>">
                </div>
                <div class="filter-group">
                    <label>Experience Level</label>
                    <div class="checkbox-group">
                        <?php $expOpts = ['Intern', 'Junior', 'Mid', 'Senior', 'Lead']; ?>
                        <?php foreach ($expOpts as $exp) : ?>
                            <label class="checkbox-inline">
                                <input type="checkbox" name="experience[]" value="<?php echo htmlspecialchars($exp); ?>" <?php echo in_array($exp, $experienceFilter, true) ? 'checked' : ''; ?>>
                                <span><?php echo htmlspecialchars($exp); ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="filter-group">
                    <label>Availability</label>
                    <div class="checkbox-group">
                        <?php $avOpts = ['Immediate', '1 Month', 'Open to Offers']; ?>
                        <?php foreach ($avOpts as $av) : ?>
                            <label class="checkbox-inline">
                                <input type="checkbox" name="availability[]" value="<?php echo htmlspecialchars($av); ?>" <?php echo in_array($av, $availabilityFilter, true) ? 'checked' : ''; ?>>
                                <span><?php echo htmlspecialchars($av); ?></span>
                            </label>
                        <?php endforeach; ?>
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
                    $locationText = is_string($s['location']) ? $s['location'] : '';
                    $availability = is_string($s['availability']) ? $s['availability'] : '';
                    $yearsExp = isset($s['years_experience']) ? (int) $s['years_experience'] : 0;
                    $expLevel = $s['experience_level'] ?? '';
                    $uid = (int) $s['id'];
                    $skillList = $skillsByUser[$uid] ?? [];
                    $topSkills = array_slice($skillList, 0, 3);
                    ?>
                    <article class="job-card">
                        <h3><?php echo htmlspecialchars($s['username']); ?></h3>
                        <p class="company-name"><?php echo htmlspecialchars($headline); ?></p>
                        <div class="job-meta">
                            <?php if ($locationText !== '') : ?>
                                <span class="badge"><?php echo htmlspecialchars($locationText); ?></span>
                            <?php endif; ?>
                            <?php if ($expLevel !== '') : ?>
                                <span class="badge badge-outline"><?php echo htmlspecialchars($expLevel); ?></span>
                            <?php endif; ?>
                            <?php if ($yearsExp > 0) : ?>
                                <span class="badge badge-muted"><?php echo (int) $yearsExp; ?> yrs</span>
                            <?php endif; ?>
                            <?php if ($availability !== '') : ?>
                                <span class="badge badge-outline"><?php echo htmlspecialchars($availability); ?></span>
                            <?php endif; ?>
                        </div>
                        <?php if (!empty($topSkills)) : ?>
                            <p class="job-meta-line">
                                <?php echo htmlspecialchars(implode(', ', $topSkills)); ?>
                            </p>
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
