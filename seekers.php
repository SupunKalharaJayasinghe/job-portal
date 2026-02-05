<?php
require_once 'core/db.php';
require_once 'core/functions.php';

checkLoggedIn();

$viewerRole = $_SESSION['role'] ?? '';
if (!in_array($viewerRole, ['employer', 'admin'], true)) {
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

$hasSeekerProfiles = tableExists($conn, 'seeker_profiles');
$hasProfileVisibility = $hasSeekerProfiles && tableHasColumn($conn, 'seeker_profiles', 'profile_visibility');
$hasHeadlineCol = $hasSeekerProfiles && tableHasColumn($conn, 'seeker_profiles', 'headline');
$hasBioCol = $hasSeekerProfiles && tableHasColumn($conn, 'seeker_profiles', 'bio');
$hasPhoneCol = $hasSeekerProfiles && tableHasColumn($conn, 'seeker_profiles', 'phone');
$hasLocationCol = $hasSeekerProfiles && tableHasColumn($conn, 'seeker_profiles', 'location');
$hasExperienceLevelCol = $hasSeekerProfiles && tableHasColumn($conn, 'seeker_profiles', 'experience_level');
$hasYearsExpCol = $hasSeekerProfiles && tableHasColumn($conn, 'seeker_profiles', 'years_experience');
$hasAvailabilityCol = $hasSeekerProfiles && tableHasColumn($conn, 'seeker_profiles', 'availability');

$headlineSelect = $hasHeadlineCol ? 'seeker_profiles.headline' : 'NULL AS headline';
$bioSelect = $hasBioCol ? 'seeker_profiles.bio' : 'NULL AS bio';
$phoneSelect = $hasPhoneCol ? 'seeker_profiles.phone' : 'NULL AS phone';
$locationSelect = $hasLocationCol ? 'seeker_profiles.location' : 'NULL AS location';
$experienceSelect = $hasExperienceLevelCol ? 'seeker_profiles.experience_level' : 'NULL AS experience_level';
$yearsSelect = $hasYearsExpCol ? 'seeker_profiles.years_experience' : 'NULL AS years_experience';
$availabilitySelect = $hasAvailabilityCol ? 'seeker_profiles.availability' : 'NULL AS availability';

$profileJoin = $hasSeekerProfiles ? ' LEFT JOIN seeker_profiles ON users.id = seeker_profiles.user_id' : '';

$sql = "SELECT users.id,
               users.username,
               users.email,
               " . ($hasProfileVisibility ? 'seeker_profiles.profile_visibility' : "NULL AS profile_visibility") . ",
               {$headlineSelect},
               {$bioSelect},
               {$phoneSelect},
               {$locationSelect},
               {$experienceSelect},
               {$yearsSelect},
               {$availabilitySelect}
        FROM users";
$sql .= $profileJoin;
$sql .= " WHERE TRIM(LOWER(users.role)) IN ('seeker', 'jobseeker', 'job_seeker', 'job seeker')";

if ($viewerRole !== 'admin' && $hasProfileVisibility) {
    $sql .= " AND (seeker_profiles.profile_visibility IS NULL OR TRIM(LOWER(seeker_profiles.profile_visibility)) <> 'private')";
}

$params = [];
$types = '';

if ($keywords !== '') {
    $kwParts = ['users.username LIKE ?'];
    if ($hasHeadlineCol) {
        $kwParts[] = 'seeker_profiles.headline LIKE ?';
    }
    if ($hasBioCol) {
        $kwParts[] = 'seeker_profiles.bio LIKE ?';
    }
    $sql .= " AND (" . implode(' OR ', $kwParts) . ")";
    $kwLike = '%' . $keywords . '%';
    $params[] = $kwLike;
    $types .= 's';
    if ($hasHeadlineCol) {
        $params[] = $kwLike;
        $types .= 's';
    }
    if ($hasBioCol) {
        $params[] = $kwLike;
        $types .= 's';
    }
}

if ($locationFilter !== '') {
    if ($hasLocationCol) {
        $sql .= " AND seeker_profiles.location LIKE ?";
        $locLike = '%' . $locationFilter . '%';
        $params[] = $locLike;
        $types .= 's';
    }
}

if (!empty($experienceFilter)) {
    if ($hasExperienceLevelCol) {
        $placeholders = implode(',', array_fill(0, count($experienceFilter), '?'));
        $sql .= " AND seeker_profiles.experience_level IN ($placeholders)";
        foreach ($experienceFilter as $exp) {
            $params[] = $exp;
            $types .= 's';
        }
    }
}

if (!empty($availabilityFilter)) {
    if ($hasAvailabilityCol) {
        $placeholders = implode(',', array_fill(0, count($availabilityFilter), '?'));
        $sql .= " AND seeker_profiles.availability IN ($placeholders)";
        foreach ($availabilityFilter as $av) {
            $params[] = $av;
            $types .= 's';
        }
    }
}

if ($skillsFilter !== '') {
    $skillsLike = '%' . $skillsFilter . '%';
    if (tableExists($conn, 'seeker_skills')) {
        $skillColumn = firstExistingColumn($conn, 'seeker_skills', ['skill_name', 'name', 'skill']);
        if ($skillColumn !== null) {
            $sql .= " AND EXISTS (SELECT 1 FROM seeker_skills WHERE seeker_skills.user_id = users.id AND seeker_skills.`{$skillColumn}` LIKE ? )";
            $params[] = $skillsLike;
            $types .= 's';
        } else {
            $fallback = [];
            if ($hasBioCol) {
                $fallback[] = 'seeker_profiles.bio LIKE ?';
                $params[] = $skillsLike;
                $types .= 's';
            }
            if ($hasHeadlineCol) {
                $fallback[] = 'seeker_profiles.headline LIKE ?';
                $params[] = $skillsLike;
                $types .= 's';
            }
            if (!empty($fallback)) {
                $sql .= ' AND (' . implode(' OR ', $fallback) . ')';
            }
        }
    } else {
        $fallback = [];
        if ($hasBioCol) {
            $fallback[] = 'seeker_profiles.bio LIKE ?';
            $params[] = $skillsLike;
            $types .= 's';
        }
        if ($hasHeadlineCol) {
            $fallback[] = 'seeker_profiles.headline LIKE ?';
            $params[] = $skillsLike;
            $types .= 's';
        }
        if (!empty($fallback)) {
            $sql .= ' AND (' . implode(' OR ', $fallback) . ')';
        }
    }
}

$sql .= $hasHeadlineCol
    ? " ORDER BY (seeker_profiles.headline IS NOT NULL AND seeker_profiles.headline <> '') DESC, users.username ASC"
    : " ORDER BY users.username ASC";

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    bindStmtParams($stmt, $types, $params);
}
$stmt->execute();
$res = $stmt->get_result();
$seekers = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
$stmt->close();

// Preload top skills for resulting seekers
$seekerIds = array_column($seekers, 'id');
$skillsByUser = [];
if (!empty($seekerIds) && tableExists($conn, 'seeker_skills')) {
    $placeholders = implode(',', array_fill(0, count($seekerIds), '?'));
    $typeStr = str_repeat('i', count($seekerIds));
    $skillSql = "SELECT * FROM seeker_skills WHERE user_id IN ($placeholders) ORDER BY id ASC";
    $skillStmt = $conn->prepare($skillSql);
    if ($skillStmt) {
        bindStmtParams($skillStmt, $typeStr, $seekerIds);
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
        <div>
            <h1>Job Seekers</h1>
            <p>Discover top talent ready to join your team.</p>
        </div>
        <a class="btn-secondary" href="dashboard.php"><i class="fa-solid fa-gauge"></i> Dashboard</a>
    </section>

    <?php
    $hasFilters = $keywords !== ''
        || $locationFilter !== ''
        || $skillsFilter !== ''
        || !empty($experienceFilter)
        || !empty($availabilityFilter);

    $filterChips = [];
    if ($keywords !== '') {
        $filterChips[] = ['label' => 'Keywords', 'value' => $keywords];
    }
    if ($locationFilter !== '') {
        $filterChips[] = ['label' => 'Location', 'value' => $locationFilter];
    }
    if ($skillsFilter !== '') {
        $filterChips[] = ['label' => 'Skills', 'value' => $skillsFilter];
    }
    if (!empty($experienceFilter)) {
        $filterChips[] = ['label' => 'Experience', 'value' => implode(', ', $experienceFilter)];
    }
    if (!empty($availabilityFilter)) {
        $filterChips[] = ['label' => 'Availability', 'value' => implode(', ', $availabilityFilter)];
    }
    ?>

    <section class="layout-with-filters">
        <aside class="filter-panel">
            <h3>Filters</h3>
            <form method="get" action="seekers.php" class="list-grid">
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

        <div class="results-panel">
            <div class="results-toolbar">
                <div class="results-summary">
                    <h2 class="results-title">Candidates</h2>
                    <p class="results-subtitle">
                        <?php if (!empty($seekers)) : ?>
                            Showing <?php echo (int) count($seekers); ?> result(s)
                        <?php else : ?>
                            No matches yet
                        <?php endif; ?>
                    </p>
                </div>
                <div class="results-actions">
                    <?php if ($hasFilters) : ?>
                        <a class="clear-filters" href="seekers.php">Clear filters</a>
                    <?php endif; ?>
                </div>
            </div>

            <?php if ($hasFilters && !empty($filterChips)) : ?>
                <div class="filter-chips">
                    <?php foreach ($filterChips as $chip) : ?>
                        <span class="filter-chip">
                            <span class="filter-chip-label"><?php echo htmlspecialchars($chip['label']); ?>:</span>
                            <span class="filter-chip-value"><?php echo htmlspecialchars($chip['value']); ?></span>
                        </span>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <div class="list-grid">
                <?php if (!empty($seekers)) : ?>
                    <?php foreach ($seekers as $s) : ?>
                        <?php
                        $headline = is_string($s['headline'] ?? null) ? trim((string) $s['headline']) : '';
                        $locationText = is_string($s['location']) ? $s['location'] : '';
                        $availability = is_string($s['availability']) ? $s['availability'] : '';
                        $yearsExp = isset($s['years_experience']) ? (int) $s['years_experience'] : 0;
                        $expLevel = $s['experience_level'] ?? '';
                        $uid = (int) $s['id'];
                        $skillList = $skillsByUser[$uid] ?? [];
                        $topSkills = array_slice($skillList, 0, 3);
                        ?>
                        <article class="job-card seeker-card">
                            <div class="seeker-card-header">
                                <div class="seeker-avatar" aria-hidden="true">
                                    <span><?php echo htmlspecialchars(strtoupper(substr((string) ($s['username'] ?? 'S'), 0, 1))); ?></span>
                                </div>
                                <div class="seeker-card-main">
                                    <h3 class="seeker-name"><?php echo htmlspecialchars($s['username']); ?></h3>
                                    <?php if ($headline !== '') : ?>
                                        <p class="company-name"><?php echo htmlspecialchars($headline); ?></p>
                                    <?php endif; ?>
                                </div>
                            </div>

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

                            <a class="btn-secondary seeker-card-link" href="view-profile.php?id=<?php echo (int) $s['id']; ?>">
                                View Profile <i class="fa-solid fa-arrow-right"></i>
                            </a>
                        </article>
                    <?php endforeach; ?>
                <?php else : ?>
                    <article class="job-card seeker-card seeker-card--empty">
                        <h3>No candidates found</h3>
                        <p class="company-name">Try adjusting your filters.</p>
                    </article>
                <?php endif; ?>
            </div>
        </div>
    </section>
</main>

<?php include 'includes/footer.php'; ?>
