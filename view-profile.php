<?php
require_once 'core/db.php';
require_once 'core/functions.php';

$profileId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$profile = null;

if ($profileId > 0) {
    $stmt = $conn->prepare("SELECT id, username, email, role, headline, bio, skills, phone, website, logo_file FROM users WHERE id = ? LIMIT 1");
    $stmt->bind_param('i', $profileId);
    $stmt->execute();
    $res = $stmt->get_result();
    $profile = $res ? $res->fetch_assoc() : null;
    $stmt->close();
}

$viewerRole = $_SESSION['role'] ?? '';

include 'includes/header.php';
?>

<main class="profile-page">
    <section class="profile-hero">
        <?php if (!$profile): ?>
            <h1>Profile Not Found</h1>
            <p class="error-text">The profile you are looking for does not exist.</p>
        <?php else: ?>
            <?php
            $isEmployerProfile = ($profile['role'] === 'employer');
            $displayName = $profile['username'];
            $headline = $profile['headline'] ?? '';
            ?>
            <h1><?php echo htmlspecialchars($displayName); ?></h1>
            <?php if ($headline !== ''): ?>
                <p><?php echo htmlspecialchars($headline); ?></p>
            <?php endif; ?>
            <p class="muted-text">
                <?php echo $isEmployerProfile ? 'Employer' : 'Job Seeker'; ?>
            </p>
        <?php endif; ?>
    </section>

    <?php if ($profile): ?>
        <section class="profile-layout">
            <div class="profile-card">
                <?php if ($isEmployerProfile): ?>
                    <?php if (!empty($profile['logo_file'])): ?>
                        <div class="profile-logo">
                            <img src="uploads/logos/<?php echo htmlspecialchars($profile['logo_file']); ?>" alt="Company logo" style="width:96px;height:96px;object-fit:cover;border-radius:12px;">
                        </div>
                    <?php endif; ?>

                    <h2>About the Company</h2>
                    <?php if (!empty($profile['bio'])): ?>
                        <p><?php echo nl2br(htmlspecialchars($profile['bio'])); ?></p>
                    <?php else: ?>
                        <p>No company description provided yet.</p>
                    <?php endif; ?>

                    <?php if (!empty($profile['website'])): ?>
                        <p><strong>Website:</strong>
                            <a href="<?php echo htmlspecialchars($profile['website']); ?>" target="_blank" rel="noopener">
                                <?php echo htmlspecialchars($profile['website']); ?>
                            </a>
                        </p>
                    <?php endif; ?>

                    <h3>Contact</h3>
                    <p>
                        <strong>Email:</strong>
                        <a href="mailto:<?php echo htmlspecialchars($profile['email']); ?>"><?php echo htmlspecialchars($profile['email']); ?></a>
                    </p>
                    <?php if (!empty($profile['phone'])): ?>
                        <p><strong>Phone:</strong> <?php echo htmlspecialchars($profile['phone']); ?></p>
                    <?php endif; ?>

                    <?php if ($viewerRole === 'seeker'): ?>
                        <p class="muted-text">You are viewing an employer profile. Use the contact details above to reach out about opportunities.</p>
                    <?php endif; ?>
                <?php else: ?>
                    <h2>About the Candidate</h2>
                    <?php if (!empty($profile['bio'])): ?>
                        <p><?php echo nl2br(htmlspecialchars($profile['bio'])); ?></p>
                    <?php else: ?>
                        <p>No bio provided yet.</p>
                    <?php endif; ?>

                    <?php if (!empty($profile['skills'])): ?>
                        <h3>Skills</h3>
                        <p><?php echo nl2br(htmlspecialchars($profile['skills'])); ?></p>
                    <?php endif; ?>

                    <h3>Contact</h3>
                    <p>
                        <strong>Email:</strong>
                        <a href="mailto:<?php echo htmlspecialchars($profile['email']); ?>"><?php echo htmlspecialchars($profile['email']); ?></a>
                    </p>
                    <?php if (!empty($profile['phone'])): ?>
                        <p><strong>Phone:</strong> <?php echo htmlspecialchars($profile['phone']); ?></p>
                    <?php endif; ?>

                    <?php if ($viewerRole === 'employer'): ?>
                        <?php
                        $subject = 'Opportunity with ' . ($_SESSION['username'] ?? 'your company');
                        $mailto = 'mailto:' . $profile['email'] . '?subject=' . rawurlencode($subject);
                        ?>
                        <a class="btn-primary" href="<?php echo htmlspecialchars($mailto); ?>">Contact Candidate</a>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </section>
    <?php endif; ?>
</main>

<?php include 'includes/footer.php'; ?>
