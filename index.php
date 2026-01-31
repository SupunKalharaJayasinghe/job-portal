
<?php include 'includes/header.php'; ?>

<main class="home-page">
    <section class="hero-section">
        <div class="hero-content">
            <h1>Find Your Next Opportunity</h1>
            <p class="hero-subtitle">Search curated opportunities and connect with top employers.</p>
            <form class="job-search-form" action="#" method="get">
                <div class="form-group">
                    <label for="keyword">Keyword</label>
                    <input type="text" id="keyword" name="keyword" placeholder="Job title or company">
                </div>
                <div class="form-group">
                    <label for="location">Location</label>
                    <input type="text" id="location" name="location" placeholder="City or remote">
                </div>
                <button class="btn-primary" type="submit">Search Jobs</button>
            </form>
        </div>
    </section>

    <section class="recent-jobs">
        <div class="section-header">
            <h2>Recent Jobs</h2>
            <p>Explore the latest openings from trusted employers.</p>
        </div>
        <div class="job-grid">
            <article class="job-card">
                <h3>Frontend Developer</h3>
                <p class="company-name">BrightTech Solutions</p>
                <p class="job-location">Colombo, Sri Lanka</p>
                <p class="job-salary">LKR 180,000 - 240,000</p>
                <a class="btn-secondary" href="job-details.php">View Details</a>
            </article>
            <article class="job-card">
                <h3>Product Designer</h3>
                <p class="company-name">Nimbus Labs</p>
                <p class="job-location">Remote</p>
                <p class="job-salary">USD 2,000 - 2,800</p>
                <a class="btn-secondary" href="job-details.php">View Details</a>
            </article>
            <article class="job-card">
                <h3>Marketing Specialist</h3>
                <p class="company-name">Atlas Commerce</p>
                <p class="job-location">Kandy, Sri Lanka</p>
                <p class="job-salary">LKR 120,000 - 160,000</p>
                <a class="btn-secondary" href="job-details.php">View Details</a>
            </article>
        </div>
    </section>
</main>

<?php include 'includes/footer.php'; ?>
