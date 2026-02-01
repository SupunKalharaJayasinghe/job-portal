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
                $revSql = "SELECT cr.id,
                                   cr.seeker_id,
                                   cr.rating,
                                   cr.comment,
                                   cr.created_at,
                                   u.username AS seeker_name
                            FROM company_reviews cr
                            JOIN users u ON cr.seeker_id = u.id
                            WHERE cr.employer_id = ?
                            ORDER BY cr.created_at DESC";
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

include 'includes/header.php';
?>

<main class="profile-page">
    <section class="profile-hero">
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

            if (!$isEmployer && $seekerProfile) {
                $headline = $seekerProfile['headline'] ?? '';
                $location = $seekerProfile['location'] ?? '';
                $experienceLevel = $seekerProfile['experience_level'] ?? '';
                $availability = $seekerProfile['availability'] ?? '';
                $visibility = $seekerProfile['profile_visibility'] ?? 'public';
            } elseif ($isEmployer && $employerProfile) {
                $location = $employerProfile['location'] ?? '';
            }
            ?>
            <h1><?php echo htmlspecialchars($displayName); ?></h1>
            <?php if ($headline !== ''): ?>
                <p><?php echo htmlspecialchars($headline); ?></p>
            <?php endif; ?>
            <p class="muted-text">
                <?php echo $isEmployer ? 'Employer' : 'Job Seeker'; ?>
                <?php if ($location !== ''): ?> · <?php echo htmlspecialchars($location); ?><?php endif; ?>
            </p>
            <?php if (!$isEmployer && $experienceLevel !== ''): ?>
                <p>
                    <span class="badge"><?php echo htmlspecialchars($experienceLevel); ?></span>
                    <?php if ($availability !== ''): ?>
                        <span class="badge badge-muted"><?php echo htmlspecialchars($availability); ?></span>
                    <?php endif; ?>
                </p>
            <?php elseif ($isEmployer && $employerProfile && !empty($employerProfile['verified_company'])): ?>
                <p><span class="badge">Verified Company ✓</span></p>
            <?php endif; ?>
        <?php endif; ?>
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
                ?>
                <section class="profile-layout">
                    <div class="profile-card">
                        <h2>About the Candidate</h2>
                        <?php if ($bio !== ''): ?>
                            <p><?php echo nl2br(htmlspecialchars($bio)); ?></p>
                        <?php else: ?>
                            <p>No bio provided yet.</p>
                        <?php endif; ?>

                        <?php if (!empty($seekerSkills)) : ?>
                            <h3>Skills</h3>
                            <div class="tag-list">
                                <?php foreach ($seekerSkills as $skillRow) : ?>
                                    <?php $label = $skillRow['skill_name'] ?? ($skillRow['name'] ?? ''); if ($label === '') continue; ?>
                                    <span class="badge badge-outline"><?php echo htmlspecialchars($label); ?></span>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>

                        <?php if ($experienceLevel !== '' || $yearsExp > 0 || $availability !== ''): ?>
                            <h3>Experience</h3>
                            <p>
                                <?php if ($experienceLevel !== ''): ?>
                                    Level: <?php echo htmlspecialchars($experienceLevel); ?><?php echo ($yearsExp > 0 || $availability !== '') ? ' · ' : ''; ?>
                                <?php endif; ?>
                                <?php if ($yearsExp > 0): ?>
                                    <?php echo (int) $yearsExp; ?> years total<?php echo $availability !== '' ? ' · ' : ''; ?>
                                <?php endif; ?>
                                <?php if ($availability !== ''): ?>
                                    Availability: <?php echo htmlspecialchars($availability); ?>
                                <?php endif; ?>
                            </p>
                        <?php endif; ?>

                        <?php if (!empty($seekerExperience)) : ?>
                            <h3>Experience Timeline</h3>
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
                        <?php endif; ?>

                        <?php if (!empty($seekerEducation)) : ?>
                            <h3>Education</h3>
                            <ul class="value-list">
                                <?php foreach ($seekerEducation as $edu) : ?>
                                    <li class="value-item">
                                        <strong><?php echo htmlspecialchars($edu['institution'] ?? ''); ?></strong>
                                        <br><?php echo htmlspecialchars($edu['degree'] ?? ''); ?>
                                        <br><span class="muted-text"><?php echo htmlspecialchars((string) ($edu['start_year'] ?? '')); ?> - <?php echo htmlspecialchars((string) ($edu['end_year'] ?? '')); ?></span>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>

                        <?php if ($viewerRole === 'employer'): ?>
                            <h3>Contact</h3>
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
                            <?php
                            $subject = 'Opportunity with ' . ($_SESSION['username'] ?? 'your company');
                            $mailto = 'mailto:' . $profileUser['email'] . '?subject=' . rawurlencode($subject);
                            ?>
                            <a class="btn-primary" href="<?php echo htmlspecialchars($mailto); ?>">Message</a>
                        <?php endif; ?>
                    </div>
                </section>
            <?php endif; ?>
        <?php else: ?>
            <?php
            $companyName = $employerProfile['company_name'] ?? $profileUser['username'];
            $industry = $employerProfile['industry'] ?? '';
            $location = $employerProfile['location'] ?? '';
            $website = $employerProfile['website'] ?? '';
            $size = $employerProfile['company_size'] ?? '';
            $description = $employerProfile['description'] ?? '';
            $logo = $employerProfile['company_logo'] ?? '';
            $verified = (int) ($employerProfile['verified_company'] ?? 0);
            ?>
            <section class="profile-layout">
                <div class="profile-card">
                    <?php if ($logo !== ''): ?>
                        <div class="profile-logo">
                            <img src="uploads/logos/<?php echo htmlspecialchars($logo); ?>" alt="Company logo" style="width:96px;height:96px;object-fit:cover;border-radius:12px;">
                        </div>
                    <?php endif; ?>

                    <h2><?php echo htmlspecialchars($companyName); ?></h2>
                    <p class="muted-text">
                        <?php if ($industry !== ''): ?><?php echo htmlspecialchars($industry); ?><?php endif; ?>
                        <?php if ($location !== ''): ?> · <?php echo htmlspecialchars($location); ?><?php endif; ?>
                        <?php if ($verified === 1): ?> · <span class="badge">Verified ✓</span><?php endif; ?>
                    </p>

                    <h3>About the Company</h3>
                    <?php if ($description !== ''): ?>
                        <p><?php echo nl2br(htmlspecialchars($description)); ?></p>
                    <?php else: ?>
                        <p>No company description provided yet.</p>
                    <?php endif; ?>

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

                    <h3>Contact</h3>
                    <p>
                        <strong>Email:</strong>
                        <a href="mailto:<?php echo htmlspecialchars($profileUser['email']); ?>"><?php echo htmlspecialchars($profileUser['email']); ?></a>
                    </p>
                </div>

                <?php if (!empty($companyReviews)): ?>
                    <div class="profile-card">
                        <h3>Reviews</h3>
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
                        <h3>Share Your Experience</h3>
                        <p class="muted-text">Have you worked with this company? You can leave a review in a future update.</p>
                    </div>
                <?php endif; ?>

                <?php if (!empty($activeJobs)): ?>
                    <div class="profile-card">
                        <h3>Active Jobs</h3>
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
            </section>
        <?php endif; ?>
    <?php endif; ?>
</main>

<?php include 'includes/footer.php'; ?>
