<?php include 'includes/header.php'; ?>

<?php
$productUpdates = [
    [
        'slug' => 'smart-matching-2',
        'tag' => 'Release',
        'title' => 'Smart Matching 2.0',
        'summary' => 'Experience more relevant matches powered by improved scoring and skills weighting.',
        'date' => 'Jan 2026',
        'category' => 'Platform',
        'icon' => 'fa-bolt',
    ],
    [
        'slug' => 'team-hiring-workspaces',
        'tag' => 'Feature',
        'title' => 'Team Hiring Workspaces',
        'summary' => 'Invite hiring managers to collaborate with shared notes, reviews, and approvals.',
        'date' => 'Jan 2026',
        'category' => 'Employers',
        'icon' => 'fa-people-group',
    ],
    [
        'slug' => 'salary-benchmarks-2026',
        'tag' => 'Guide',
        'title' => '2026 Salary Benchmarks',
        'summary' => 'Regional market data to set competitive offers and negotiate confidently.',
        'date' => 'Dec 2025',
        'category' => 'Insights',
        'icon' => 'fa-chart-line',
    ],
];

$insights = [
    [
        'slug' => 'structured-interviewing-playbook',
        'tag' => 'Hiring',
        'title' => 'Structured Interviewing Playbook',
        'summary' => 'Reduce bias and speed up hiring with reusable scorecards and rubrics.',
        'date' => 'Dec 2025',
        'category' => 'Playbook',
        'icon' => 'fa-clipboard-check',
    ],
    [
        'slug' => 'standout-portfolio',
        'tag' => 'Career',
        'title' => 'Build a Standout Portfolio',
        'summary' => 'Practical steps for designers and developers to showcase real impact.',
        'date' => 'Nov 2025',
        'category' => 'Guides',
        'icon' => 'fa-layer-group',
    ],
    [
        'slug' => 'upcoming-webinars-amas',
        'tag' => 'Community',
        'title' => 'Upcoming Webinars & AMAs',
        'summary' => 'Join live sessions with recruiters, founders, and senior engineers.',
        'date' => 'Nov 2025',
        'category' => 'Events',
        'icon' => 'fa-video',
    ],
];

$featuredUpdate = $productUpdates[0] ?? null;
$moreUpdates = array_slice($productUpdates, 1);
?>

<main class="news-page">
    <section class="news-hero">
        <div class="news-hero-inner">
            <div class="news-hero-eyebrow">News</div>
            <h1>Updates that move your hiring forward</h1>
            <p>Platform releases, hiring playbooks, and career insights — curated for employers and job seekers.</p>
            <div class="news-hero-actions">
                <a class="btn-primary" href="jobs.php">Explore Jobs</a>
                <a class="btn-secondary news-hero-secondary" href="#product-updates">Product updates</a>
            </div>
            <div class="news-hero-chips">
                <a class="chip" href="#product-updates"><i class="fa-solid fa-cube"></i> Product</a>
                <a class="chip" href="#insights"><i class="fa-solid fa-lightbulb"></i> Insights</a>
                <a class="chip" href="#guides"><i class="fa-solid fa-compass"></i> Guides</a>
            </div>
        </div>
    </section>

    <section class="news-section" id="product-updates">
        <header class="news-section-header">
            <h2>Product updates</h2>
            <p>New features and improvements to make hiring and job seeking effortless.</p>
        </header>

        <?php if ($featuredUpdate) : ?>
            <div class="news-featured-layout">
                <article class="news-card news-card--featured">
                    <div class="news-card-top">
                        <div class="tag"><?php echo htmlspecialchars($featuredUpdate['tag']); ?></div>
                        <div class="news-meta"><span><?php echo htmlspecialchars($featuredUpdate['date']); ?></span><span><?php echo htmlspecialchars($featuredUpdate['category']); ?></span></div>
                    </div>
                    <div class="news-featured-graphic" aria-hidden="true">
                        <i class="fa-solid <?php echo htmlspecialchars($featuredUpdate['icon'] ?? 'fa-bolt'); ?>"></i>
                    </div>
                    <h3><?php echo htmlspecialchars($featuredUpdate['title']); ?></h3>
                    <p><?php echo htmlspecialchars($featuredUpdate['summary']); ?></p>
                    <a class="btn-secondary" href="news-details.php?slug=<?php echo urlencode($featuredUpdate['slug']); ?>">
                        Read more <i class="fa-solid fa-arrow-right"></i>
                    </a>
                </article>

                <div class="news-list">
                    <?php foreach ($moreUpdates as $item) : ?>
                        <article class="news-card news-card--compact">
                            <div class="news-card-top">
                                <div class="tag"><?php echo htmlspecialchars($item['tag']); ?></div>
                                <div class="news-meta"><span><?php echo htmlspecialchars($item['date']); ?></span><span><?php echo htmlspecialchars($item['category']); ?></span></div>
                            </div>
                            <h3>
                                <a class="news-title-link" href="news-details.php?slug=<?php echo urlencode($item['slug']); ?>">
                                    <?php echo htmlspecialchars($item['title']); ?>
                                </a>
                            </h3>
                            <p><?php echo htmlspecialchars($item['summary']); ?></p>
                        </article>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
    </section>

    <section class="news-section" id="insights">
        <header class="news-section-header">
            <h2>Insights & tips</h2>
            <p>Curated advice for growing teams and ambitious talent.</p>
        </header>

        <div class="news-grid news-grid--modern" id="guides">
            <?php foreach ($insights as $item) : ?>
                <article class="news-card">
                    <div class="news-card-top">
                        <div class="tag"><?php echo htmlspecialchars($item['tag']); ?></div>
                        <div class="news-meta"><span><?php echo htmlspecialchars($item['date']); ?></span><span><?php echo htmlspecialchars($item['category']); ?></span></div>
                    </div>
                    <h3>
                        <a class="news-title-link" href="news-details.php?slug=<?php echo urlencode($item['slug']); ?>">
                            <?php echo htmlspecialchars($item['title']); ?>
                        </a>
                    </h3>
                    <p><?php echo htmlspecialchars($item['summary']); ?></p>
                    <a class="news-readmore" href="news-details.php?slug=<?php echo urlencode($item['slug']); ?>">
                        Read more <i class="fa-solid fa-arrow-right"></i>
                    </a>
                </article>
            <?php endforeach; ?>
        </div>
    </section>
</main>

<?php include 'includes/footer.php'; ?>
