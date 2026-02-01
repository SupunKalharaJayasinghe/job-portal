
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
    // Featured seekers: public profiles only
    $stmt = $conn->prepare(
        "SELECT users.id,
                users.username,
                seeker_profiles.headline,
                seeker_profiles.location,
                seeker_profiles.experience_level
         FROM users
         JOIN seeker_profiles ON users.id = seeker_profiles.user_id
         WHERE users.role = 'seeker'
           AND seeker_profiles.profile_visibility = 'public'
           AND seeker_profiles.headline IS NOT NULL
           AND seeker_profiles.headline <> ''
         LIMIT 4"
    );
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
                         employer_profiles.company_name,
                         employer_profiles.company_logo
                  FROM jobs
                  JOIN employer_profiles ON jobs.employer_id = employer_profiles.user_id
                  WHERE jobs.status = 'active'
                  ORDER BY jobs.created_at DESC
                  LIMIT 6";
    $recentRes = $conn->query($recentSql);
    if ($recentRes && $recentRes->num_rows > 0) {
        $recentJobs = $recentRes->fetch_all(MYSQLI_ASSOC);
    }

    // Trending jobs: fall back to additional recent jobs
    $trendingSql = "SELECT jobs.id,
                            jobs.title,
                            jobs.location,
                            jobs.job_type,
                            jobs.salary_min,
                            jobs.salary_max,
                            employer_profiles.company_name
                     FROM jobs
                     JOIN employer_profiles ON jobs.employer_id = employer_profiles.user_id
                     WHERE jobs.status = 'active'
                     ORDER BY jobs.created_at DESC
                     LIMIT 6 OFFSET 6";
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

    $companiesRes = $conn->query("SELECT COUNT(DISTINCT user_id) AS total_companies FROM employer_profiles");
    if ($companiesRes && $row = $companiesRes->fetch_assoc()) {
        $stats['companies'] = (int) $row['total_companies'];
    }

    $seekersRes = $conn->query("SELECT COUNT(*) AS total_seekers FROM users WHERE role = 'seeker'");
    if ($seekersRes && $row = $seekersRes->fetch_assoc()) {
        $stats['seekers'] = (int) $row['total_seekers'];
    }
}
?>

<main class="home-page">
    <section class="hero-section">
        <div class="hero-content">
            <h1>Find Your Next Opportunity</h1>
            <p class="hero-subtitle">Search thousands of jobs from top employers</p>
            <form class="job-search-form" action="jobs.php" method="get">
                <div class="form-group">
                    <label for="keyword"><i class="fa-solid fa-magnifying-glass"></i> Keywords</label>
                    <input type="text" id="keyword" name="keywords" placeholder="Job title, keyword, or company">
                </div>
                <div class="form-group">
                    <label for="hero_location"><i class="fa-solid fa-location-dot"></i> Location</label>
                    <input type="text" id="hero_location" name="location" placeholder="City or Remote">
                </div>
                <div class="form-group">
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
                <button class="btn-primary" type="submit"><i class="fa-solid fa-magnifying-glass"></i> Search</button>
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
                    ?>
                    <article class="job-card">
                        <h3><?php echo htmlspecialchars($job['title']); ?></h3>
                        <p class="company-name"><?php echo htmlspecialchars($job['company_name']); ?></p>
                        <p class="job-location"><?php echo htmlspecialchars($job['location']); ?> · <?php echo htmlspecialchars($job['job_type'] ?? ''); ?></p>
                        <p class="job-salary"><?php echo htmlspecialchars($salaryText); ?></p>
                        <a class="btn-secondary" href="job-details.php?id=<?php echo (int) $job['id']; ?>">View Details</a>
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
                <?php foreach ($trendingJobs as $job) : ?>
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
                        <h3><?php echo htmlspecialchars($job['title']); ?></h3>
                        <p class="company-name"><?php echo htmlspecialchars($job['company_name']); ?></p>
                        <p class="job-location"><?php echo htmlspecialchars($job['location']); ?> · <?php echo htmlspecialchars($job['job_type'] ?? ''); ?></p>
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
                    <article class="job-card">
                        <h3><?php echo htmlspecialchars($s['username']); ?></h3>
                        <p class="company-name"><?php echo htmlspecialchars($s['headline'] ?? 'Open to new opportunities'); ?></p>
                        <p class="job-location"><?php echo htmlspecialchars($s['location'] ?? ''); ?><?php echo !empty($s['experience_level']) ? ' · ' . htmlspecialchars($s['experience_level']) : ''; ?></p>
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
                <h3><?php echo number_format($stats['jobs']); ?></h3>
                <p>Total Active Jobs</p>
            </div>
            <div class="stat-card">
                <h3><?php echo number_format($stats['companies']); ?></h3>
                <p>Hiring Companies</p>
            </div>
            <div class="stat-card">
                <h3><?php echo number_format($stats['seekers']); ?></h3>
                <p>Registered Job Seekers</p>
            </div>
            <div class="stat-card">
                <h3><i class="fa-solid fa-star" style="color:var(--primary);"></i></h3>
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
                <?php foreach ($homeCategories as $catRow) : ?>
                    <div class="category-card">
                        <a href="jobs.php?category=<?php echo urlencode($catRow['category']); ?>">
                            <h3><?php echo htmlspecialchars($catRow['category']); ?></h3>
                            <p>Browse jobs in this category</p>
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
