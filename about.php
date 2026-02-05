<?php include 'includes/header.php'; ?>

<?php
$aboutStats = [
    'active_jobs' => 0,
    'seekers' => 0,
    'applications' => 0,
    'avg_hours_to_shortlist' => null,
];

if (!function_exists('formatMetric')) {
    function formatMetric(int $value): string
    {
        if ($value >= 1000000) {
            $num = $value / 1000000;
            $formatted = number_format($num, $num >= 10 ? 0 : 1);
            return $formatted . 'M';
        }
        if ($value >= 1000) {
            $num = $value / 1000;
            $formatted = number_format($num, $num >= 10 ? 0 : 1);
            return $formatted . 'K';
        }
        return (string) $value;
    }
}

if (!function_exists('formatDurationHours')) {
    function formatDurationHours(float $hours): string
    {
        if ($hours >= 72) {
            $days = $hours / 24;
            return number_format($days, $days >= 10 ? 0 : 1) . 'd';
        }
        return (string) ((int) round($hours)) . 'h';
    }
}

try {
    $res = $conn->query("SELECT COUNT(*) AS total FROM jobs WHERE status = 'active'");
    if ($res && $row = $res->fetch_assoc()) {
        $aboutStats['active_jobs'] = (int) $row['total'];
    }

    $res = $conn->query("SELECT COUNT(*) AS total FROM users WHERE role = 'seeker'");
    if ($res && $row = $res->fetch_assoc()) {
        $aboutStats['seekers'] = (int) $row['total'];
    }

    $res = $conn->query('SELECT COUNT(*) AS total FROM applications');
    if ($res && $row = $res->fetch_assoc()) {
        $aboutStats['applications'] = (int) $row['total'];
    }

    $hasUpdatedAt = false;
    $colRes = $conn->query("SHOW COLUMNS FROM applications LIKE 'updated_at'");
    if ($colRes && $colRes->num_rows > 0) {
        $hasUpdatedAt = true;
    }

    if ($hasUpdatedAt) {
        $avgRes = $conn->query(
            "SELECT AVG(TIMESTAMPDIFF(HOUR, applied_at, updated_at)) AS avg_hours\n"
            . "FROM applications\n"
            . "WHERE applied_at IS NOT NULL\n"
            . "  AND updated_at IS NOT NULL\n"
            . "  AND updated_at >= applied_at\n"
            . "  AND status <> 'pending'"
        );
        if ($avgRes && $row = $avgRes->fetch_assoc()) {
            $avgVal = $row['avg_hours'];
            if ($avgVal !== null) {
                $aboutStats['avg_hours_to_shortlist'] = (float) $avgVal;
            }
        }
    }

    if ($aboutStats['avg_hours_to_shortlist'] === null) {
        $hasInterviews = false;
        $tRes = $conn->query("SHOW TABLES LIKE 'interviews'");
        if ($tRes && $tRes->num_rows > 0) {
            $hasInterviews = true;
        }

        if ($hasInterviews) {
            $avgRes = $conn->query(
                'SELECT AVG(TIMESTAMPDIFF(HOUR, a.applied_at, i.first_interview)) AS avg_hours\n'
                . 'FROM applications a\n'
                . 'JOIN (SELECT application_id, MIN(interview_date) AS first_interview FROM interviews GROUP BY application_id) i\n'
                . '  ON i.application_id = a.id\n'
                . 'WHERE a.applied_at IS NOT NULL AND i.first_interview IS NOT NULL AND i.first_interview >= a.applied_at'
            );
            if ($avgRes && $row = $avgRes->fetch_assoc()) {
                $avgVal = $row['avg_hours'];
                if ($avgVal !== null) {
                    $aboutStats['avg_hours_to_shortlist'] = (float) $avgVal;
                }
            }
        }
    }
} catch (mysqli_sql_exception $e) {
}

$activeJobsLabel = formatMetric($aboutStats['active_jobs']);
$seekersLabel = formatMetric($aboutStats['seekers']);
$applicationsLabel = formatMetric($aboutStats['applications']);
$avgHours = $aboutStats['avg_hours_to_shortlist'];
$avgLabel = $avgHours !== null ? formatDurationHours($avgHours) : 'N/A';
?>

<main class="about-page">
    <section class="about-hero">
        <div class="about-hero-inner">
            <div class="about-hero-eyebrow">About</div>
            <h1>Built for modern hiring</h1>
            <p>Connecting ambitious talent with visionary companies through a premium hiring experience that feels fast, transparent, and human.</p>
            <div class="about-hero-actions">
                <a class="btn-primary" href="jobs.php">Browse Jobs</a>
                <a class="btn-secondary about-hero-secondary" href="seekers.php">Find Talent</a>
            </div>
            <div class="about-hero-badges">
                <span class="pill"><i class="fa-solid fa-shield"></i> Trusted listings</span>
                <span class="pill"><i class="fa-solid fa-bolt"></i> Faster shortlists</span>
                <span class="pill"><i class="fa-solid fa-heart"></i> Candidate-first</span>
            </div>
        </div>
    </section>

    <section class="about-section">
        <header class="about-section-header">
            <h2>Our Story</h2>
            <p>We are a modern job platform built to pair high-growth teams with top candidates faster, with transparency and trust.</p>
        </header>
        <div class="about-split">
            <article class="about-card">
                <h3>Why we exist</h3>
                <p>Hiring is high-stakes and time-sensitive. We built this platform to remove noise, increase signal, and help teams make better decisions.</p>
            </article>
            <article class="about-card">
                <h3>How we do it</h3>
                <p>Clear job information, structured filters, and simple workflows for posting, applying, and tracking — with a polished experience on every screen.</p>
            </article>
        </div>
    </section>

    <section class="about-section">
        <div class="stats-grid about-stats-grid">
            <div class="stat-card stat-card--about">
                <div class="stat-icon"><i class="fa-solid fa-briefcase"></i></div>
                <div>
                    <h3><?php echo htmlspecialchars($activeJobsLabel); ?></h3>
                    <p>Active roles posted</p>
                </div>
            </div>
            <div class="stat-card stat-card--about">
                <div class="stat-icon"><i class="fa-solid fa-user-check"></i></div>
                <div>
                    <h3><?php echo htmlspecialchars($seekersLabel); ?></h3>
                    <p>Verified job seekers</p>
                </div>
            </div>
            <div class="stat-card stat-card--about">
                <div class="stat-icon"><i class="fa-solid fa-paper-plane"></i></div>
                <div>
                    <h3><?php echo htmlspecialchars($applicationsLabel); ?></h3>
                    <p>Applications processed</p>
                </div>
            </div>
            <div class="stat-card stat-card--about">
                <div class="stat-icon"><i class="fa-solid fa-stopwatch"></i></div>
                <div>
                    <h3><?php echo htmlspecialchars($avgLabel); ?></h3>
                    <p>Avg. time to shortlist</p>
                </div>
            </div>
        </div>
    </section>

    <section class="about-section">
        <header class="about-section-header">
            <h2>What we believe</h2>
            <p>Human-first recruiting, data-informed decisions, and equitable access for everyone.</p>
        </header>
        <div class="about-values-grid">
            <article class="value-card">
                <div class="value-icon"><i class="fa-solid fa-glasses"></i></div>
                <h3>Transparency</h3>
                <p>Clear salary ranges, role expectations, and hiring steps for every listing.</p>
            </article>
            <article class="value-card">
                <div class="value-icon"><i class="fa-solid fa-award"></i></div>
                <h3>Quality</h3>
                <p>Thoughtful profiles, structured information, and a trusted marketplace experience.</p>
            </article>
            <article class="value-card">
                <div class="value-icon"><i class="fa-solid fa-wand-magic-sparkles"></i></div>
                <h3>Velocity</h3>
                <p>Smart matching and alerts to reduce hiring cycles without sacrificing fit.</p>
            </article>
        </div>
    </section>

    <section class="about-section">
        <header class="about-section-header">
            <h2>How we help</h2>
            <p>Purpose-built tooling for both sides of the marketplace.</p>
        </header>
        <div class="about-help-grid">
            <article class="about-help-card">
                <header class="about-help-header">
                    <div class="about-help-icon"><i class="fa-solid fa-building"></i></div>
                    <div>
                        <h3>For Employers</h3>
                        <p>Post roles faster, review applicants clearly, and build a brand candidates trust.</p>
                    </div>
                </header>
                <ul class="about-checklist">
                    <li>Branded company profiles and highlighted jobs</li>
                    <li>Shortlist workflow with clean applicant management</li>
                    <li>Dashboards for recruiters and hiring managers</li>
                </ul>
            </article>
            <article class="about-help-card">
                <header class="about-help-header">
                    <div class="about-help-icon"><i class="fa-solid fa-user-astronaut"></i></div>
                    <div>
                        <h3>For Job Seekers</h3>
                        <p>Find roles that fit, apply with confidence, and track everything in one place.</p>
                    </div>
                </header>
                <ul class="about-checklist">
                    <li>Tailored job search and filtering</li>
                    <li>Quick apply with resume upload</li>
                    <li>Application tracking with status updates</li>
                </ul>
            </article>
        </div>
    </section>

    <section class="about-cta">
        <div class="about-cta-card">
            <div>
                <h2>Ready to get started?</h2>
                <p>Explore opportunities or start hiring today — it takes just a few minutes to begin.</p>
            </div>
            <div class="about-cta-actions">
                <a class="btn-primary" href="jobs.php">Browse Jobs</a>
                <a class="btn-secondary" href="register.php">Create an account</a>
            </div>
        </div>
    </section>
</main>

<?php include 'includes/footer.php'; ?>
