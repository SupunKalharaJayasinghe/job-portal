
<?php include 'includes/header.php'; ?>

<main class="home-page">
    <section class="hero-section">
        <div class="hero-content">
            <h1>Find Your Next Opportunity</h1>
            <p class="hero-subtitle">Search curated opportunities and connect with top employers.</p>
            <form class="job-search-form" action="jobs.php" method="get">
                <div class="form-group">
                    <label for="keyword">Keyword</label>
                    <input type="text" id="keyword" name="keywords" placeholder="Job title or company">
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

    <section class="trending-jobs">
        <div class="section-header">
            <h2>Trending Jobs</h2>
            <p>Roles with the highest demand this week.</p>
        </div>
        <div class="job-grid">
            <article class="job-card">
                <h3>AI Engineer</h3>
                <p class="company-name">Visionary Labs</p>
                <p class="job-location">Remote</p>
                <p class="job-salary">USD 3,500 - 4,500</p>
                <a class="btn-secondary" href="job-details.php">View Details</a>
            </article>
            <article class="job-card">
                <h3>Full Stack Developer</h3>
                <p class="company-name">Orbit Softworks</p>
                <p class="job-location">Colombo</p>
                <p class="job-salary">LKR 220,000 - 300,000</p>
                <a class="btn-secondary" href="job-details.php">View Details</a>
            </article>
            <article class="job-card">
                <h3>Data Analyst</h3>
                <p class="company-name">InsightX</p>
                <p class="job-location">Hybrid</p>
                <p class="job-salary">USD 1,800 - 2,400</p>
                <a class="btn-secondary" href="job-details.php">View Details</a>
            </article>
        </div>
    </section>

    <section class="job-seekers-highlight">
        <div class="section-header">
            <h2>Featured Job Seekers</h2>
            <p>Top candidates ready for their next challenge.</p>
        </div>
        <div class="job-grid">
            <article class="job-card">
                <h3>Amara Perera</h3>
                <p class="company-name">Product Designer · 6 yrs</p>
                <p class="job-location">Remote · Portfolio ready</p>
                <p class="job-salary">Preferred: USD 3k+</p>
                <a class="btn-secondary" href="profile.php">View Profile</a>
            </article>
            <article class="job-card">
                <h3>Ravi Fernando</h3>
                <p class="company-name">Full Stack Engineer · 8 yrs</p>
                <p class="job-location">Colombo · React / Laravel</p>
                <p class="job-salary">Preferred: LKR 350k+</p>
                <a class="btn-secondary" href="profile.php">View Profile</a>
            </article>
            <article class="job-card">
                <h3>Nadia Rahman</h3>
                <p class="company-name">Marketing Lead · 10 yrs</p>
                <p class="job-location">Hybrid · B2B SaaS</p>
                <p class="job-salary">Preferred: USD 4k+</p>
                <a class="btn-secondary" href="profile.php">View Profile</a>
            </article>
        </div>
    </section>

    <section class="top-categories">
        <div class="section-header">
            <h2>Top Categories</h2>
            <p>Discover roles by specialty.</p>
        </div>
        <div class="category-grid">
            <div class="category-card">
                <h3>Engineering</h3>
                <p>Backend, Frontend, DevOps, QA</p>
            </div>
            <div class="category-card">
                <h3>Product & Design</h3>
                <p>Product Managers, UX/UI, Research</p>
            </div>
            <div class="category-card">
                <h3>Marketing & Growth</h3>
                <p>Performance, Content, Brand</p>
            </div>
            <div class="category-card">
                <h3>Data & AI</h3>
                <p>Data Science, Analytics, ML Ops</p>
            </div>
            <div class="category-card">
                <h3>Operations</h3>
                <p>People Ops, Finance, Admin</p>
            </div>
            <div class="category-card">
                <h3>Sales & Success</h3>
                <p>AE, SDR, Customer Success</p>
            </div>
        </div>
    </section>
</main>

<?php include 'includes/footer.php'; ?>
