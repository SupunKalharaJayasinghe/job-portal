<?php
require_once 'core/db.php';
require_once 'core/functions.php';

$profileId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$profileUser = null;
$seekerProfile = null;
$employerProfile = null;
$seekerSkills = [];
$seekerEducation = [];
$seekerExperience = [];
$companyReviews = [];
$activeJobs = [];

if ($profileId > 0) {
    $stmt = $conn->prepare("SELECT id, username, email, role FROM users WHERE id = ? LIMIT 1");
    $stmt->bind_param('i', $profileId);
    $stmt->execute();
    $res = $stmt->get_result();
    $profileUser = $res ? $res->fetch_assoc() : null;
    $stmt->close();
}

$viewerId = $_SESSION['user_id'] ?? null;
$viewerRole = $_SESSION['role'] ?? '';
$canInAppMessage = !empty($viewerId)
    && $viewerId !== $profileId
    && tableExists($conn, 'messages')
    && tableHasColumn($conn, 'messages', 'sender_id')
    && tableHasColumn($conn, 'messages', 'receiver_id')
    && tableHasColumn($conn, 'messages', 'body');

if ($profileUser) {
    if ($profileUser['role'] === 'seeker') {
        // Load seeker profile
        $spStmt = $conn->prepare('SELECT * FROM seeker_profiles WHERE user_id = ? LIMIT 1');
        if ($spStmt) {
            $spStmt->bind_param('i', $profileId);
            $spStmt->execute();
            $spRes = $spStmt->get_result();
            $seekerProfile = $spRes ? $spRes->fetch_assoc() : null;
            $spStmt->close();
        }

        if ($seekerProfile) {
            // Visibility check: hide details if profile is private and viewer is not owner
            $visibility = $seekerProfile['profile_visibility'] ?? 'public';
            if ($visibility === 'private' && $viewerId !== $profileId) {
                // Keep only minimal info; rest handled in template
            }

            // Load related tables
            $skillStmt = $conn->prepare('SELECT * FROM seeker_skills WHERE user_id = ? ORDER BY id ASC');
            if ($skillStmt) {
                $skillStmt->bind_param('i', $profileId);
                $skillStmt->execute();
                $skillRes = $skillStmt->get_result();
                $seekerSkills = $skillRes ? $skillRes->fetch_all(MYSQLI_ASSOC) : [];
                $skillStmt->close();
            }

            $eduStmt = $conn->prepare('SELECT * FROM seeker_education WHERE user_id = ? ORDER BY start_year DESC, end_year DESC');
            if ($eduStmt) {
                $eduStmt->bind_param('i', $profileId);
                $eduStmt->execute();
                $eduRes = $eduStmt->get_result();
                $seekerEducation = $eduRes ? $eduRes->fetch_all(MYSQLI_ASSOC) : [];
                $eduStmt->close();
            }

            $expStmt = $conn->prepare('SELECT * FROM seeker_experience WHERE user_id = ? ORDER BY start_date DESC');
            if ($expStmt) {
                $expStmt->bind_param('i', $profileId);
                $expStmt->execute();
                $expRes = $expStmt->get_result();
                $seekerExperience = $expRes ? $expRes->fetch_all(MYSQLI_ASSOC) : [];
                $expStmt->close();
            }
        }
    } else {
        // Employer profile
        $epStmt = $conn->prepare('SELECT * FROM employer_profiles WHERE user_id = ? LIMIT 1');
        if ($epStmt) {
            $epStmt->bind_param('i', $profileId);
            $epStmt->execute();
            $epRes = $epStmt->get_result();
            $employerProfile = $epRes ? $epRes->fetch_assoc() : null;
            $epStmt->close();
        }

        if ($employerProfile) {
            // Active jobs for this employer
            $jobStmt = $conn->prepare("SELECT id, title, location, job_type, salary_min, salary_max FROM jobs WHERE employer_id = ? AND status = 'active' ORDER BY created_at DESC LIMIT 10");
            if ($jobStmt) {
                $jobStmt->bind_param('i', $profileId);
                $jobStmt->execute();
                $jobRes = $jobStmt->get_result();
                $activeJobs = $jobRes ? $jobRes->fetch_all(MYSQLI_ASSOC) : [];
                $jobStmt->close();
            }

            // Company reviews, if table exists
            $tableCheck = $conn->query("SHOW TABLES LIKE 'company_reviews'");
            if ($tableCheck && $tableCheck->num_rows > 0) {
                $cols = [];
                $colRes = $conn->query("SHOW COLUMNS FROM company_reviews");
                if ($colRes) {
                    while ($col = $colRes->fetch_assoc()) {
                        if (!empty($col['Field'])) {
                            $cols[] = $col['Field'];
                        }
                    }
                }

                $hasEmployerId = in_array('employer_id', $cols, true);
                $hasSeekerId = in_array('seeker_id', $cols, true);

                $ratingField = '';
                foreach (['rating', 'stars', 'score'] as $candidate) {
                    if (in_array($candidate, $cols, true)) {
                        $ratingField = $candidate;
                        break;
                    }
                }

                $commentField = '';
                foreach (['comment', 'review', 'review_text', 'feedback', 'message', 'content', 'notes'] as $candidate) {
                    if (in_array($candidate, $cols, true)) {
                        $commentField = $candidate;
                        break;
                    }
                }

                $createdField = '';
                foreach (['created_at', 'created', 'createdOn', 'created_date'] as $candidate) {
                    if (in_array($candidate, $cols, true)) {
                        $createdField = $candidate;
                        break;
                    }
                }

                if ($hasEmployerId && $hasSeekerId) {
                    $ratingSelect = $ratingField !== '' ? ('cr.`' . $ratingField . '` AS rating') : 'NULL AS rating';
                    $commentSelect = $commentField !== '' ? ('cr.`' . $commentField . '` AS comment') : "'' AS comment";
                    $createdSelect = $createdField !== '' ? ('cr.`' . $createdField . '` AS created_at') : 'NULL AS created_at';
                    $orderBy = $createdField !== '' ? ('cr.`' . $createdField . '` DESC') : 'cr.id DESC';

                    $revSql = "SELECT cr.id,
                                       cr.seeker_id,
                                       {$ratingSelect},
                                       {$commentSelect},
                                       {$createdSelect},
                                       u.username AS seeker_name
                                FROM company_reviews cr
                                JOIN users u ON cr.seeker_id = u.id
                                WHERE cr.employer_id = ?
                                ORDER BY {$orderBy}";
                    $revStmt = $conn->prepare($revSql);
                    if ($revStmt) {
                        $revStmt->bind_param('i', $profileId);
                        $revStmt->execute();
                        $revRes = $revStmt->get_result();
                        $companyReviews = $revRes ? $revRes->fetch_all(MYSQLI_ASSOC) : [];
                        $revStmt->close();
                    }
                }
            }
        }
    }
}

include 'includes/header.php';
?>

<main class="profile-page">
    <section class="profile-hero profile-hero--premium">
        <div class="profile-hero-inner">
            <?php if (!$profileUser): ?>
                <h1>Profile Not Found</h1>
                <p class="error-text">The profile you are looking for does not exist.</p>
            <?php else: ?>
                <?php
                $isEmployer = ($profileUser['role'] === 'employer');
                $displayName = $profileUser['username'];
                $headline = '';
                $location = '';
                $experienceLevel = '';
                $availability = '';
                $visibility = 'public';

                $heroLogoName = '';
                $heroLogoPath = '';
                $hasHeroLogo = false;

                if (!$isEmployer && $seekerProfile) {
                    $headline = $seekerProfile['headline'] ?? '';
                    $location = $seekerProfile['location'] ?? '';
                    $experienceLevel = $seekerProfile['experience_level'] ?? '';
                    $availability = $seekerProfile['availability'] ?? '';
                    $visibility = $seekerProfile['profile_visibility'] ?? 'public';
                } elseif ($isEmployer && $employerProfile) {
                    $displayName = $employerProfile['company_name'] ?? $displayName;
                    $headline = $employerProfile['industry'] ?? '';
                    $location = $employerProfile['location'] ?? '';
                    $heroLogoName = !empty($employerProfile['company_logo']) ? basename((string) $employerProfile['company_logo']) : '';
                    $heroLogoPath = $heroLogoName !== '' ? (__DIR__ . '/uploads/logos/' . $heroLogoName) : '';
                    $hasHeroLogo = $heroLogoPath !== '' && is_file($heroLogoPath);
                }

                $avatarLabel = strtoupper(substr((string) $displayName, 0, 1));
                ?>

                <div class="profile-hero-top">
                    <div class="profile-hero-avatar" aria-hidden="true">
                        <?php if ($isEmployer && $hasHeroLogo): ?>
                            <img src="uploads/logos/<?php echo htmlspecialchars($heroLogoName); ?>" alt="<?php echo htmlspecialchars($displayName); ?>">
                        <?php else: ?>
                            <span><?php echo htmlspecialchars($avatarLabel); ?></span>
                        <?php endif; ?>
                    </div>

                    <div class="profile-hero-body">
                        <div class="profile-hero-eyebrow"><?php echo $isEmployer ? 'Company Profile' : 'Candidate Profile'; ?></div>
                        <h1><?php echo htmlspecialchars($displayName); ?></h1>
                        <?php if ($headline !== ''): ?>
                            <p class="profile-hero-subtitle"><?php echo htmlspecialchars($headline); ?></p>
                        <?php endif; ?>
                        <p class="profile-hero-role">
                            <?php echo $isEmployer ? 'Employer' : 'Job Seeker'; ?>
                            <?php if ($location !== ''): ?> · <?php echo htmlspecialchars($location); ?><?php endif; ?>
                        </p>

                        <div class="profile-hero-chips">
                            <?php if (!$isEmployer && $experienceLevel !== ''): ?>
                                <span class="profile-chip"><i class="fa-solid fa-chart-line"></i> <?php echo htmlspecialchars($experienceLevel); ?></span>
                            <?php endif; ?>
                            <?php if (!$isEmployer && $availability !== ''): ?>
                                <span class="profile-chip"><i class="fa-regular fa-clock"></i> <?php echo htmlspecialchars($availability); ?></span>
                            <?php endif; ?>
                            <?php if ($isEmployer && !empty($activeJobs)) : ?>
                                <span class="profile-chip"><i class="fa-solid fa-briefcase"></i> <?php echo (int) count($activeJobs); ?> open roles</span>
                            <?php endif; ?>
                            <?php if ($isEmployer && $employerProfile && !empty($employerProfile['verified_company'])): ?>
                                <span class="profile-chip"><i class="fa-solid fa-shield"></i> Verified Company</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <?php if ($profileUser): ?>
        <?php if ($profileUser['role'] === 'seeker'): ?>
            <?php $visibility = $seekerProfile['profile_visibility'] ?? 'public'; ?>
            <?php if ($visibility === 'private' && $viewerId !== $profileId): ?>
                <section class="profile-layout">
                    <div class="profile-card">
                        <p class="muted-text">This profile is private.</p>
                    </div>
                </section>
            <?php else: ?>
                <?php
                $bio = $seekerProfile['bio'] ?? '';
                $yearsExp = isset($seekerProfile['years_experience']) ? (int) $seekerProfile['years_experience'] : 0;
                $linkedin = $seekerProfile['linkedin_url'] ?? '';
                $portfolio = $seekerProfile['portfolio_url'] ?? '';
                $phone = $seekerProfile['phone'] ?? '';

                $showSeekerSidebar = (!empty($seekerSkills) || $viewerRole === 'employer');
                $seekerLayoutClass = 'profile-layout profile-layout--premium' . ($showSeekerSidebar ? '' : ' profile-layout--single');
                ?>
                <section class="<?php echo htmlspecialchars($seekerLayoutClass); ?>">
                    <div class="profile-main">
                        <div class="profile-card">
                            <h2>About the Candidate</h2>
                            <?php if ($bio !== ''): ?>
                                <p><?php echo nl2br(htmlspecialchars($bio)); ?></p>
                            <?php else: ?>
                                <p>No bio provided yet.</p>
                            <?php endif; ?>

                            <?php if ($experienceLevel !== '' || $yearsExp > 0 || $availability !== ''): ?>
                                <div class="profile-facts">
                                    <?php if ($experienceLevel !== ''): ?>
                                        <div class="profile-fact"><span class="profile-fact-label">Level</span><span class="profile-fact-value"><?php echo htmlspecialchars($experienceLevel); ?></span></div>
                                    <?php endif; ?>
                                    <?php if ($yearsExp > 0): ?>
                                        <div class="profile-fact"><span class="profile-fact-label">Experience</span><span class="profile-fact-value"><?php echo (int) $yearsExp; ?> years</span></div>
                                    <?php endif; ?>
                                    <?php if ($availability !== ''): ?>
                                        <div class="profile-fact"><span class="profile-fact-label">Availability</span><span class="profile-fact-value"><?php echo htmlspecialchars($availability); ?></span></div>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </div>

                        <?php if (!empty($seekerExperience)) : ?>
                            <div class="profile-card">
                                <h2>Experience Timeline</h2>
                                <ul class="value-list">
                                    <?php foreach ($seekerExperience as $exp) : ?>
                                        <li class="value-item">
                                            <strong><?php echo htmlspecialchars($exp['job_title'] ?? ''); ?></strong>
                                            <?php if (!empty($exp['company'])) : ?>
                                                at <?php echo htmlspecialchars($exp['company']); ?>
                                            <?php endif; ?>
                                            <br><span class="muted-text"><?php echo htmlspecialchars($exp['start_date'] ?? ''); ?><?php echo !empty($exp['end_date']) ? ' - ' . htmlspecialchars($exp['end_date']) : ' - Present'; ?></span>
                                            <?php if (!empty($exp['description'])) : ?>
                                                <br><span><?php echo nl2br(htmlspecialchars($exp['description'])); ?></span>
                                            <?php endif; ?>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>

                        <?php if (!empty($seekerEducation)) : ?>
                            <div class="profile-card">
                                <h2>Education</h2>
                                <ul class="value-list">
                                    <?php foreach ($seekerEducation as $edu) : ?>
                                        <li class="value-item">
                                            <strong><?php echo htmlspecialchars($edu['institution'] ?? ''); ?></strong>
                                            <br><?php echo htmlspecialchars($edu['degree'] ?? ''); ?>
                                            <br><span class="muted-text"><?php echo htmlspecialchars((string) ($edu['start_year'] ?? '')); ?> - <?php echo htmlspecialchars((string) ($edu['end_year'] ?? '')); ?></span>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>
                    </div>

                    <?php if ($showSeekerSidebar): ?>
                        <aside class="profile-side">
                            <?php if (!empty($seekerSkills)) : ?>
                                <div class="profile-card">
                                    <h2>Skills</h2>
                                    <div class="tag-list">
                                        <?php foreach ($seekerSkills as $skillRow) : ?>
                                            <?php $label = $skillRow['skill_name'] ?? ($skillRow['name'] ?? ''); if ($label === '') continue; ?>
                                            <span class="badge badge-outline"><?php echo htmlspecialchars($label); ?></span>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <?php if ($viewerRole === 'employer'): ?>
                                <div class="profile-card">
                                    <h2>Contact</h2>
                                    <p>
                                        <strong>Email:</strong>
                                        <a href="mailto:<?php echo htmlspecialchars($profileUser['email']); ?>"><?php echo htmlspecialchars($profileUser['email']); ?></a>
                                    </p>
                                    <?php if ($phone !== ''): ?>
                                        <p><strong>Phone:</strong> <?php echo htmlspecialchars($phone); ?></p>
                                    <?php endif; ?>
                                    <?php if ($linkedin !== '' || $portfolio !== ''): ?>
                                        <p>
                                            <?php if ($linkedin !== ''): ?>
                                                <a href="<?php echo htmlspecialchars($linkedin); ?>" target="_blank" rel="noopener">LinkedIn</a>
                                            <?php endif; ?>
                                            <?php if ($portfolio !== ''): ?>
                                                <?php if ($linkedin !== ''): ?> · <?php endif; ?>
                                                <a href="<?php echo htmlspecialchars($portfolio); ?>" target="_blank" rel="noopener">Portfolio</a>
                                            <?php endif; ?>
                                        </p>
                                    <?php endif; ?>
                                    <?php if ($canInAppMessage) : ?>
                                        <a class="btn-primary" href="messages.php?with=<?php echo (int) $profileId; ?>">Message</a>
                                    <?php else : ?>
                                        <?php
                                        $subject = 'Opportunity with ' . ($_SESSION['username'] ?? 'your company');
                                        $mailto = 'mailto:' . $profileUser['email'] . '?subject=' . rawurlencode($subject);
                                        ?>
                                        <a class="btn-primary" href="<?php echo htmlspecialchars($mailto); ?>">Message</a>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </aside>
                    <?php endif; ?>
                </section>
            <?php endif; ?>
        <?php else: ?>
            <?php
            $companyName = $employerProfile['company_name'] ?? $profileUser['username'];
            $industry = $employerProfile['industry'] ?? '';
            $location = $employerProfile['location'] ?? '';
            $website = $employerProfile['website'] ?? '';
            $size = $employerProfile['company_size'] ?? '';
            $descCol = firstExistingColumn($conn, 'employer_profiles', ['description', 'bio', 'about']);
            $description = $descCol !== null ? ($employerProfile[$descCol] ?? '') : '';
            $logo = $employerProfile['company_logo'] ?? '';
            $verified = (int) ($employerProfile['verified_company'] ?? 0);

            $showEmployerSidebar = (!empty($companyReviews) || $viewerRole === 'seeker');
            $employerLayoutClass = 'profile-layout profile-layout--premium' . ($showEmployerSidebar ? '' : ' profile-layout--single');
            ?>
            <section class="<?php echo htmlspecialchars($employerLayoutClass); ?>">
                <div class="profile-main">
                    <div class="profile-card">
                        <h2>About the Company</h2>

                        <?php if ($industry !== '' || $location !== '' || $verified === 1): ?>
                            <div class="profile-facts">
                                <?php if ($industry !== ''): ?>
                                    <div class="profile-fact"><span class="profile-fact-label">Industry</span><span class="profile-fact-value"><?php echo htmlspecialchars($industry); ?></span></div>
                                <?php endif; ?>
                                <?php if ($location !== ''): ?>
                                    <div class="profile-fact"><span class="profile-fact-label">Location</span><span class="profile-fact-value"><?php echo htmlspecialchars($location); ?></span></div>
                                <?php endif; ?>
                                <?php if ($verified === 1): ?>
                                    <div class="profile-fact"><span class="profile-fact-label">Trust</span><span class="profile-fact-value">Verified ✓</span></div>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>

                        <?php if ($description !== ''): ?>
                            <p><?php echo nl2br(htmlspecialchars($description)); ?></p>
                        <?php else: ?>
                            <p>No company description provided yet.</p>
                        <?php endif; ?>

                        <?php if ($size !== '' || $website !== ''): ?>
                            <h3>Company Stats</h3>
                            <p>
                                <?php if ($size !== ''): ?>
                                    <strong>Size:</strong> <?php echo htmlspecialchars($size); ?><br>
                                <?php endif; ?>
                                <?php if ($website !== ''): ?>
                                    <strong>Website:</strong>
                                    <a href="<?php echo htmlspecialchars($website); ?>" target="_blank" rel="noopener"><?php echo htmlspecialchars($website); ?></a>
                                <?php endif; ?>
                            </p>
                        <?php endif; ?>

                        <h3>Contact</h3>
                        <p>
                            <strong>Email:</strong>
                            <a href="mailto:<?php echo htmlspecialchars($profileUser['email']); ?>"><?php echo htmlspecialchars($profileUser['email']); ?></a>
                        </p>
                    </div>

                    <?php if (!empty($activeJobs)): ?>
                        <div class="profile-card">
                            <h2>Active Jobs</h2>
                            <div class="job-grid">
                                <?php foreach ($activeJobs as $job) : ?>
                                    <?php
                                    $min = isset($job['salary_min']) ? (float) $job['salary_min'] : 0;
                                    $max = isset($job['salary_max']) ? (float) $job['salary_max'] : 0;
                                    $salaryText = '';
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
                                        <p class="job-location"><?php echo htmlspecialchars($job['location']); ?><?php echo !empty($job['job_type']) ? ' · ' . htmlspecialchars($job['job_type']) : ''; ?></p>
                                        <?php if ($salaryText !== '') : ?>
                                            <p class="job-salary"><?php echo htmlspecialchars($salaryText); ?></p>
                                        <?php endif; ?>
                                        <a class="btn-secondary" href="job-details.php?id=<?php echo (int) $job['id']; ?>">View Job</a>
                                    </article>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>

                <?php if ($showEmployerSidebar): ?>
                    <aside class="profile-side">
                        <?php if (!empty($companyReviews)): ?>
                            <div class="profile-card">
                                <h2>Reviews</h2>
                                <ul class="value-list">
                                    <?php foreach ($companyReviews as $rev) : ?>
                                        <li class="value-item">
                                            <strong><?php echo htmlspecialchars($rev['seeker_name'] ?? 'Anonymous'); ?></strong>
                                            <?php if (isset($rev['rating'])) : ?>
                                                <br><span class="muted-text">Rating: <?php echo (int) $rev['rating']; ?>/5</span>
                                            <?php endif; ?>
                                            <?php if (!empty($rev['comment'])) : ?>
                                                <br><span><?php echo nl2br(htmlspecialchars($rev['comment'])); ?></span>
                                            <?php endif; ?>
                                            <?php if (!empty($rev['created_at'])) : ?>
                                                <br><span class="muted-text"><?php echo htmlspecialchars($rev['created_at']); ?></span>
                                            <?php endif; ?>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>

                        <?php if ($viewerRole === 'seeker'): ?>
                            <div class="profile-card">
                                <h2>Share Your Experience</h2>
                                <p class="muted-text">Have you worked with this company? You can leave a review in a future update.</p>
                            </div>
                        <?php endif; ?>
                    </aside>
                <?php endif; ?>
            </section>
        <?php endif; ?>
    <?php endif; ?>
</main>

<?php include 'includes/footer.php'; ?>
