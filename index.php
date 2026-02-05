
<?php include 'includes/header.php'; ?>

<?php
// Home page data driven by normalized schema
$highlightSeekers = [];
$recentJobs = [];
$trendingJobs = [];
$homeCategories = [];
$stats = [
    'jobs' => 0,
    'companies' => 0,
    'seekers' => 0,
];

if (isset($conn)) {
    // Featured seekers: public profiles only, show most recently registered
    $hasSeekerProfiles = tableExists($conn, 'seeker_profiles');
    $hasProfileVisibility = $hasSeekerProfiles && tableHasColumn($conn, 'seeker_profiles', 'profile_visibility');
    $hasHeadlineCol = $hasSeekerProfiles && tableHasColumn($conn, 'seeker_profiles', 'headline');
    $hasLocationCol = $hasSeekerProfiles && tableHasColumn($conn, 'seeker_profiles', 'location');
    $hasExperienceLevelCol = $hasSeekerProfiles && tableHasColumn($conn, 'seeker_profiles', 'experience_level');

    $featuredSelect = "users.id, users.username";
    if ($hasSeekerProfiles) {
        $featuredSelect .= ", " . ($hasHeadlineCol ? 'sp.headline' : 'NULL AS headline');
        $featuredSelect .= ", " . ($hasLocationCol ? 'sp.location' : 'NULL AS location');
        $featuredSelect .= ", " . ($hasExperienceLevelCol ? 'sp.experience_level' : 'NULL AS experience_level');
    } else {
        $featuredSelect .= ", NULL AS headline, NULL AS location, NULL AS experience_level";
    }

    $featuredSql = "SELECT " . $featuredSelect . " FROM users";
    if ($hasSeekerProfiles) {
        $featuredSql .= " LEFT JOIN seeker_profiles sp ON users.id = sp.user_id";
    }

    $featuredSql .= " WHERE TRIM(LOWER(users.role)) IN ('seeker', 'jobseeker', 'job_seeker', 'job seeker')";
    if ($hasSeekerProfiles && $hasProfileVisibility) {
        $featuredSql .= " AND (sp.profile_visibility IS NULL OR TRIM(LOWER(sp.profile_visibility)) <> 'private')";
    }
    if ($hasSeekerProfiles && $hasHeadlineCol) {
        $featuredSql .= " AND sp.headline IS NOT NULL AND sp.headline <> ''";
    }
    $featuredSql .= " ORDER BY users.id DESC LIMIT 4";

    $stmt = $conn->prepare($featuredSql);
    if ($stmt) {
        $stmt->execute();
        $res = $stmt->get_result();
        $highlightSeekers = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
        $stmt->close();
    }

    // Recent active jobs, with employer company data
    $recentSql = "SELECT jobs.id,
                         jobs.title,
                         jobs.location,
                         jobs.job_type,
                         jobs.salary_min,
                         jobs.salary_max,
                         COALESCE(employer_profiles.company_name, employer_user.username) AS company_name,
                         employer_profiles.company_logo
                  FROM jobs
                  JOIN users employer_user ON jobs.employer_id = employer_user.id
                  LEFT JOIN employer_profiles ON jobs.employer_id = employer_profiles.user_id
                  WHERE jobs.status = 'active'
                  ORDER BY jobs.created_at DESC
                  LIMIT 6";
    $recentRes = $conn->query($recentSql);
    if ($recentRes && $recentRes->num_rows > 0) {
        $recentJobs = $recentRes->fetch_all(MYSQLI_ASSOC);
    }

    // Trending jobs: highest views first, then most recent
    $trendingSql = "SELECT jobs.id,
                            jobs.title,
                            jobs.location,
                            jobs.job_type,
                            jobs.salary_min,
                            jobs.salary_max,
                            COALESCE(employer_profiles.company_name, employer_user.username) AS company_name
                     FROM jobs
                     JOIN users employer_user ON jobs.employer_id = employer_user.id
                     LEFT JOIN employer_profiles ON jobs.employer_id = employer_profiles.user_id
                     WHERE jobs.status = 'active'
                     ORDER BY jobs.views DESC, jobs.created_at DESC
                     LIMIT 6";
    $trendingRes = $conn->query($trendingSql);
    if ($trendingRes && $trendingRes->num_rows > 0) {
        $trendingJobs = $trendingRes->fetch_all(MYSQLI_ASSOC);
    }

    // Distinct job categories for the homepage "Browse by Category" section
    $catSql = "SELECT DISTINCT category
               FROM jobs
               WHERE category IS NOT NULL AND category <> ''
               ORDER BY category ASC";
    $catRes = $conn->query($catSql);
    if ($catRes && $catRes->num_rows > 0) {
        $homeCategories = $catRes->fetch_all(MYSQLI_ASSOC);
    }

    // Stats
    $jobsRes = $conn->query("SELECT COUNT(*) AS total_jobs FROM jobs WHERE status = 'active'");
    if ($jobsRes && $row = $jobsRes->fetch_assoc()) {
        $stats['jobs'] = (int) $row['total_jobs'];
    }

    $companiesRes = $conn->query("SELECT COUNT(*) AS total_companies FROM users WHERE TRIM(LOWER(role)) = 'employer'");
    if ($companiesRes && $row = $companiesRes->fetch_assoc()) {
        $stats['companies'] = (int) $row['total_companies'];
    }

    $seekersRes = $conn->query("SELECT COUNT(*) AS total_seekers FROM users WHERE TRIM(LOWER(role)) IN ('seeker', 'jobseeker', 'job_seeker', 'job seeker')");
    if ($seekersRes && $row = $seekersRes->fetch_assoc()) {
        $stats['seekers'] = (int) $row['total_seekers'];
    }
}
?>

<main class="home-page">
    <section class="hero-section">
        <canvas id="ditherCanvas" class="dither-canvas"></canvas>
        <div class="hero-content">
            <h1>Find Your Next Opportunity</h1>
            <p class="hero-subtitle">Search thousands of jobs from top employers</p>
            <form class="job-search-form" action="jobs.php" method="get">
                <div class="hero-search-field">
                    <span class="hero-search-icon" aria-hidden="true"><i class="fa-solid fa-magnifying-glass"></i></span>
                    <label for="keyword"><i class="fa-solid fa-magnifying-glass"></i> Keywords</label>
                    <input type="text" id="keyword" name="keywords" placeholder="Job title, keyword, or company">
                </div>
                <div class="hero-search-field">
                    <span class="hero-search-icon" aria-hidden="true"><i class="fa-solid fa-location-dot"></i></span>
                    <label for="hero_location"><i class="fa-solid fa-location-dot"></i> Location</label>
                    <input type="text" id="hero_location" name="location" placeholder="City or Remote">
                </div>
                <div class="hero-search-field hero-search-field--select">
                    <span class="hero-search-icon" aria-hidden="true"><i class="fa-solid fa-briefcase"></i></span>
                    <label for="hero_job_type"><i class="fa-solid fa-briefcase"></i> Job Type</label>
                    <select id="hero_job_type" name="job_type">
                        <option value="">Any</option>
                        <option value="Full-time">Full-time</option>
                        <option value="Part-time">Part-time</option>
                        <option value="Contract">Contract</option>
                        <option value="Internship">Internship</option>
                        <option value="Remote">Remote</option>
                    </select>
                </div>
                <button class="btn-primary hero-search-submit" type="submit"><i class="fa-solid fa-magnifying-glass"></i> Search</button>
            </form>
        </div>
    </section>

    <section class="recent-jobs">
        <div class="section-header">
            <div>
                <h2>Recent Job Postings</h2>
                <p>Explore the latest opportunities</p>
            </div>
            <a href="jobs.php">View all jobs <i class="fa-solid fa-arrow-right"></i></a>
        </div>
        <div class="job-grid">
            <?php if (!empty($recentJobs)) : ?>
                <?php foreach ($recentJobs as $job) : ?>
                    <?php
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

                    $companyName = (string) ($job['company_name'] ?? 'Company');
                    $logoName = !empty($job['company_logo']) ? basename((string) $job['company_logo']) : '';
                    $logoDiskPath = $logoName !== '' ? (__DIR__ . '/uploads/logos/' . $logoName) : '';
                    $hasLogo = $logoDiskPath !== '' && is_file($logoDiskPath);
                    ?>
                    <article class="job-card">
                        <div class="job-card-header">
                            <?php if ($hasLogo) : ?>
                                <div class="job-card-logo">
                                    <img src="uploads/logos/<?php echo htmlspecialchars($logoName); ?>" alt="<?php echo htmlspecialchars($companyName); ?>">
                                </div>
                            <?php else : ?>
                                <div class="job-card-logo job-card-logo--placeholder" aria-hidden="true">
                                    <span><?php echo htmlspecialchars(strtoupper(substr($companyName !== '' ? $companyName : 'C', 0, 1))); ?></span>
                                </div>
                            <?php endif; ?>
                            <div class="job-card-main">
                                <h3 class="job-card-title">
                                    <a href="job-details.php?id=<?php echo (int) $job['id']; ?>">
                                        <?php echo htmlspecialchars($job['title']); ?>
                                    </a>
                                </h3>
                                <p class="company-name"><?php echo htmlspecialchars($companyName); ?></p>
                            </div>
                        </div>
                        <div class="job-meta">
                            <?php if (!empty($job['location'])) : ?>
                                <span class="badge"><?php echo htmlspecialchars($job['location']); ?></span>
                            <?php endif; ?>
                            <?php if (!empty($job['job_type'])) : ?>
                                <span class="badge badge-outline"><?php echo htmlspecialchars($job['job_type']); ?></span>
                            <?php endif; ?>
                        </div>
                        <div class="job-card-footer">
                            <p class="job-salary"><?php echo htmlspecialchars($salaryText); ?></p>
                            <a class="btn-secondary job-card-link" href="job-details.php?id=<?php echo (int) $job['id']; ?>">
                                View details <i class="fa-solid fa-arrow-right"></i>
                            </a>
                        </div>
                    </article>
                <?php endforeach; ?>
            <?php else : ?>
                <article class="job-card">
                    <h3>No jobs available yet</h3>
                    <p class="company-name">New opportunities will appear here soon.</p>
                </article>
            <?php endif; ?>
        </div>
    </section>

    <section class="trending-jobs">
        <div class="section-header">
            <div>
                <h2>Trending This Week</h2>
                <p>High-demand roles from top companies</p>
            </div>
            <a href="jobs.php">View all <i class="fa-solid fa-arrow-right"></i></a>
        </div>
        <div class="job-grid">
            <?php if (!empty($trendingJobs)) : ?>
                <?php foreach ($trendingJobs as $i => $job) : ?>
                    <?php
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
                    ?>
                    <article class="job-card">
                        <div class="trend-rank">#<?php echo (int) ($i + 1); ?></div>
                        <div class="job-card-header">
                            <div class="job-card-logo job-card-logo--placeholder" aria-hidden="true">
                                <span><?php echo htmlspecialchars(strtoupper(substr((string) ($job['company_name'] ?? 'C'), 0, 1))); ?></span>
                            </div>
                            <div class="job-card-main">
                                <h3 class="job-card-title">
                                    <a href="job-details.php?id=<?php echo (int) $job['id']; ?>">
                                        <?php echo htmlspecialchars($job['title']); ?>
                                    </a>
                                </h3>
                                <p class="company-name"><?php echo htmlspecialchars($job['company_name']); ?></p>
                            </div>
                        </div>
                        <div class="job-meta">
                            <?php if (!empty($job['location'])) : ?>
                                <span class="badge"><?php echo htmlspecialchars($job['location']); ?></span>
                            <?php endif; ?>
                            <?php if (!empty($job['job_type'])) : ?>
                                <span class="badge badge-outline"><?php echo htmlspecialchars($job['job_type']); ?></span>
                            <?php endif; ?>
                        </div>
                        <p class="job-salary"><?php echo htmlspecialchars($salaryText); ?></p>
                        <a class="btn-secondary" href="job-details.php?id=<?php echo (int) $job['id']; ?>">View Details</a>
                    </article>
                <?php endforeach; ?>
            <?php else : ?>
                <p>No trending jobs yet. Try browsing all jobs.</p>
            <?php endif; ?>
        </div>
    </section>

    <section class="job-seekers-highlight">
        <div class="section-header">
            <div>
                <h2>Featured Candidates</h2>
                <p>Top talent ready for new opportunities</p>
            </div>
            <a href="seekers.php">View all candidates <i class="fa-solid fa-arrow-right"></i></a>
        </div>
        <div class="job-grid">
            <?php if (!empty($highlightSeekers)) : ?>
                <?php foreach ($highlightSeekers as $s) : ?>
                    <article class="candidate-card">
                        <div class="candidate-header">
                            <div class="candidate-avatar" aria-hidden="true">
                                <?php echo htmlspecialchars(strtoupper(substr((string) ($s['username'] ?? 'U'), 0, 1))); ?>
                            </div>
                            <div class="candidate-main">
                                <h3 class="candidate-name">
                                    <a href="view-profile.php?id=<?php echo (int) $s['id']; ?>">
                                        <?php echo htmlspecialchars($s['username']); ?>
                                    </a>
                                </h3>
                                <p class="candidate-headline"><?php echo htmlspecialchars($s['headline'] ?? 'Open to new opportunities'); ?></p>
                            </div>
                        </div>
                        <div class="candidate-meta">
                            <?php if (!empty($s['location'])) : ?>
                                <span class="badge"><?php echo htmlspecialchars($s['location']); ?></span>
                            <?php endif; ?>
                            <?php if (!empty($s['experience_level'])) : ?>
                                <span class="badge badge-outline"><?php echo htmlspecialchars($s['experience_level']); ?></span>
                            <?php endif; ?>
                        </div>
                        <a class="btn-secondary" href="view-profile.php?id=<?php echo (int) $s['id']; ?>">View Profile</a>
                    </article>
                <?php endforeach; ?>
            <?php else : ?>
                <p>No featured job seekers yet. Encourage candidates to complete their profiles.</p>
            <?php endif; ?>
        </div>
    </section>

    <section class="home-stats">
        <div class="section-header">
            <div>
                <h2>Platform Snapshot</h2>
                <p>Discover what's happening on CareerNest right now.</p>
            </div>
        </div>
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon" aria-hidden="true"><i class="fa-solid fa-briefcase"></i></div>
                <h3><?php echo number_format($stats['jobs']); ?></h3>
                <p>Total Active Jobs</p>
            </div>
            <div class="stat-card">
                <div class="stat-icon" aria-hidden="true"><i class="fa-solid fa-building"></i></div>
                <h3><?php echo number_format($stats['companies']); ?></h3>
                <p>Hiring Companies</p>
            </div>
            <div class="stat-card">
                <div class="stat-icon" aria-hidden="true"><i class="fa-solid fa-user-group"></i></div>
                <h3><?php echo number_format($stats['seekers']); ?></h3>
                <p>Registered Job Seekers</p>
            </div>
            <div class="stat-card">
                <div class="stat-icon" aria-hidden="true"><i class="fa-solid fa-star"></i></div>
                <h3>Get Featured</h3>
                <p>Join and be featured next</p>
            </div>
        </div>
    </section>

    <section class="top-categories">
        <div class="section-header">
            <div>
                <h2>Browse by Category</h2>
                <p>Find jobs in your area of expertise</p>
            </div>
        </div>
        <div class="category-grid">
            <?php if (!empty($homeCategories)) : ?>
                <?php
                $categoryIcons = [
                    'fa-solid fa-code',
                    'fa-solid fa-pen-nib',
                    'fa-solid fa-chart-line',
                    'fa-solid fa-headset',
                    'fa-solid fa-user-tie',
                    'fa-solid fa-stethoscope',
                    'fa-solid fa-graduation-cap',
                    'fa-solid fa-screwdriver-wrench',
                    'fa-solid fa-shield-halved',
                    'fa-solid fa-bullhorn'
                ];
                $iconCount = count($categoryIcons);
                ?>
                <?php foreach ($homeCategories as $catRow) : ?>
                    <?php
                    $catName = (string) ($catRow['category'] ?? 'Category');
                    $iconIndex = $iconCount > 0 ? (abs((int) crc32($catName)) % $iconCount) : 0;
                    $iconClass = $categoryIcons[$iconIndex] ?? 'fa-solid fa-layer-group';
                    ?>
                    <div class="category-card">
                        <a href="jobs.php?category=<?php echo urlencode($catRow['category']); ?>">
                            <span class="category-icon" aria-hidden="true"><i class="<?php echo htmlspecialchars($iconClass); ?>"></i></span>
                            <div class="category-content">
                                <h3><?php echo htmlspecialchars($catRow['category']); ?></h3>
                                <p>Browse jobs in this category</p>
                            </div>
                            <span class="category-arrow" aria-hidden="true"><i class="fa-solid fa-arrow-right"></i></span>
                        </a>
                    </div>
                <?php endforeach; ?>
            <?php else : ?>
                <p>No categories available yet. Once jobs are posted, categories will appear here.</p>
            <?php endif; ?>
        </div>
    </section>
</main>

<?php include 'includes/footer.php'; ?>
