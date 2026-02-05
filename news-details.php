<?php
include 'includes/header.php';

$slug = $_GET['slug'] ?? '';

$articles = [
    'smart-matching-2' => [
        'title' => 'Smart Matching 2.0',
        'tag' => 'Release',
        'date' => 'Jan 2026',
        'category' => 'Platform',
        'summary' => 'Experience more relevant matches powered by improved scoring and skills weighting.',
        'body' => 'Smart Matching 2.0 introduces a more intelligent scoring engine that looks beyond simple keyword matches. It takes into account skills, seniority, industry, and location preferences, helping employers see the most relevant candidates first and giving seekers better job recommendations.\n\nKey improvements include:\n- Multi-factor scoring for candidates and jobs\n- Better weighting for must-have vs nice-to-have skills\n- Continuous learning from hiring outcomes to refine suggestions over time.'
    ],
    'team-hiring-workspaces' => [
        'title' => 'Team Hiring Workspaces',
        'tag' => 'Feature',
        'date' => 'Jan 2026',
        'category' => 'Employers',
        'summary' => 'Invite hiring managers to collaborate with shared notes, reviews, and approvals.',
        'body' => 'Team Hiring Workspaces allow recruiters and hiring managers to work together in one place. Every job has its own workspace with candidate lists, feedback, and decisions in a single view.\n\nWith workspaces you can:\n- Share shortlists and pipelines with stakeholders\n- Collect structured feedback on each candidate\n- Track approvals and offers in a transparent timeline.'
    ],
    'salary-benchmarks-2026' => [
        'title' => '2026 Salary Benchmarks',
        'tag' => 'Guide',
        'date' => 'Dec 2025',
        'category' => 'Insights',
        'summary' => 'Regional market data to set competitive offers and negotiate confidently.',
        'body' => 'Our 2026 Salary Benchmarks report compiles data across roles, regions, and company stages to help you understand current compensation trends.\n\nInside the report you will find:\n- Median salary ranges by role and seniority\n- Differences between remote, hybrid, and on-site compensation\n- Guidance for both employers and candidates on structuring fair offers.'
    ],
    'structured-interviewing-playbook' => [
        'title' => 'Structured Interviewing Playbook',
        'tag' => 'Hiring',
        'date' => 'Dec 2025',
        'category' => 'Playbook',
        'summary' => 'Reduce bias and speed up hiring with reusable scorecards and rubrics.',
        'body' => 'The Structured Interviewing Playbook offers templates and best practices to make every interview more consistent and fair.\n\nHighlights include:\n- Role-specific question banks\n- Scorecard templates for skills and behaviors\n- Tips for running efficient panel interviews and debriefs.'
    ],
    'standout-portfolio' => [
        'title' => 'Build a Standout Portfolio',
        'tag' => 'Career',
        'date' => 'Nov 2025',
        'category' => 'Guides',
        'summary' => 'Practical steps for designers and developers to showcase real impact.',
        'body' => 'A standout portfolio focuses on outcomes, not just outputs. This guide walks through how to select projects, tell clear stories, and frame your work so reviewers quickly see your strengths.\n\nYou will learn how to:\n- Choose projects that map to your target roles\n- Write concise case studies with context, constraints, and results\n- Present your portfolio across web, PDF, and live walkthroughs.'
    ],
    'upcoming-webinars-amas' => [
        'title' => 'Upcoming Webinars & AMAs',
        'tag' => 'Community',
        'date' => 'Nov 2025',
        'category' => 'Events',
        'summary' => 'Join live sessions with recruiters, founders, and senior engineers.',
        'body' => 'We regularly host webinars and Ask-Me-Anything sessions featuring hiring leaders, founders, and senior practitioners.\n\nOn these sessions you can:\n- Learn how top teams hire and grow\n- Ask questions about your own search or hiring challenges\n- Connect with a wider community of peers.'
    ],
];

$article = $articles[$slug] ?? null;
?>

<main class="news-page">
    <?php if (!$article): ?>
        <section class="news-hero news-hero--article">
            <div class="news-hero-inner">
                <div class="news-hero-eyebrow">News</div>
                <h1>News not found</h1>
                <p>The news article you are looking for could not be found.</p>
                <div class="news-hero-actions">
                    <a class="btn-secondary news-hero-secondary" href="news.php">
                        <i class="fa-solid fa-arrow-left"></i> Back to News
                    </a>
                    <a class="btn-primary" href="jobs.php">Explore Jobs</a>
                </div>
            </div>
        </section>
    <?php else: ?>
        <section class="news-hero news-hero--article">
            <div class="news-hero-inner">
                <a class="news-back" href="news.php">
                    <i class="fa-solid fa-arrow-left"></i>
                    Back to News
                </a>
                <div class="tag"><?php echo htmlspecialchars($article['tag']); ?></div>
                <h1><?php echo htmlspecialchars($article['title']); ?></h1>
                <p><?php echo htmlspecialchars($article['summary']); ?></p>
                <div class="news-meta news-meta--hero">
                    <span><i class="fa-regular fa-calendar"></i> <?php echo htmlspecialchars($article['date']); ?></span>
                    <span><i class="fa-regular fa-folder"></i> <?php echo htmlspecialchars($article['category']); ?></span>
                </div>
            </div>
        </section>

        <section class="news-article">
            <article class="news-article-card">
                <div class="news-prose"><?php echo htmlspecialchars($article['body']); ?></div>
            </article>

            <div class="news-article-cta">
                <div>
                    <h2>Keep exploring</h2>
                    <p>Browse more updates or jump back into the job search.</p>
                </div>
                <div class="news-article-cta-actions">
                    <a class="btn-secondary" href="news.php">More news</a>
                    <a class="btn-primary" href="jobs.php">Explore jobs</a>
                </div>
            </div>
        </section>
    <?php endif; ?>
</main>

<?php include 'includes/footer.php'; ?>
