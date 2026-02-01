<?php
require_once 'core/db.php';
require_once 'core/functions.php';

checkLoggedIn();

$userId = $_SESSION['user_id'] ?? null;
$role = $_SESSION['role'] ?? '';
$success = '';
$error = '';

// Base user data (auth + name/email)
$userStmt = $conn->prepare("SELECT id, username, email, role FROM users WHERE id = ? LIMIT 1");
$userStmt->bind_param('i', $userId);
$userStmt->execute();
$res = $userStmt->get_result();
$user = $res ? $res->fetch_assoc() : null;
$userStmt->close();

if (!$user) {
    die('User not found');
}

// Normalized profile data
$seekerProfile = null;
$employerProfile = null;
$seekerSkills = [];
$seekerEducation = [];
$seekerExperience = [];

if ($role === 'seeker') {
    // Ensure seeker profile exists
    $spStmt = $conn->prepare("SELECT * FROM seeker_profiles WHERE user_id = ? LIMIT 1");
    $spStmt->bind_param('i', $userId);
    $spStmt->execute();
    $spRes = $spStmt->get_result();
    $seekerProfile = $spRes ? $spRes->fetch_assoc() : null;
    $spStmt->close();

    if (!$seekerProfile) {
        $createSp = $conn->prepare("INSERT INTO seeker_profiles (user_id, profile_visibility) VALUES (?, 'public')");
        $createSp->bind_param('i', $userId);
        $createSp->execute();
        $createSp->close();

        $spStmt = $conn->prepare("SELECT * FROM seeker_profiles WHERE user_id = ? LIMIT 1");
        $spStmt->bind_param('i', $userId);
        $spStmt->execute();
        $spRes = $spStmt->get_result();
        $seekerProfile = $spRes ? $spRes->fetch_assoc() : null;
        $spStmt->close();
    }

    // Overlay common fields for display defaults
    if ($seekerProfile) {
        foreach (['headline', 'location', 'experience_level', 'availability'] as $field) {
            if (isset($seekerProfile[$field]) && $seekerProfile[$field] !== null) {
                $user[$field] = $seekerProfile[$field];
            }
        }
        $user['profile_visibility'] = $seekerProfile['profile_visibility'] ?? 'public';
        $user['linkedin_url'] = $seekerProfile['linkedin_url'] ?? ($user['linkedin_url'] ?? '');
        $user['portfolio_url'] = $seekerProfile['portfolio_url'] ?? ($user['portfolio_url'] ?? '');
        $user['resume_file'] = $seekerProfile['resume_file'] ?? null;
        $user['bio'] = $seekerProfile['bio'] ?? ($user['bio'] ?? '');
        $user['phone'] = $seekerProfile['phone'] ?? ($user['phone'] ?? '');
        $user['years_experience'] = $seekerProfile['years_experience'] ?? ($user['years_experience'] ?? null);
    }

    // Load related seeker data
    $skillStmt = $conn->prepare('SELECT * FROM seeker_skills WHERE user_id = ? ORDER BY id ASC');
    if ($skillStmt) {
        $skillStmt->bind_param('i', $userId);
        $skillStmt->execute();
        $skillRes = $skillStmt->get_result();
        $seekerSkills = $skillRes ? $skillRes->fetch_all(MYSQLI_ASSOC) : [];
        $skillStmt->close();
    }

    $eduStmt = $conn->prepare('SELECT * FROM seeker_education WHERE user_id = ? ORDER BY start_year DESC, end_year DESC');
    if ($eduStmt) {
        $eduStmt->bind_param('i', $userId);
        $eduStmt->execute();
        $eduRes = $eduStmt->get_result();
        $seekerEducation = $eduRes ? $eduRes->fetch_all(MYSQLI_ASSOC) : [];
        $eduStmt->close();
    }

    $expStmt = $conn->prepare('SELECT * FROM seeker_experience WHERE user_id = ? ORDER BY start_date DESC');
    if ($expStmt) {
        $expStmt->bind_param('i', $userId);
        $expStmt->execute();
        $expRes = $expStmt->get_result();
        $seekerExperience = $expRes ? $expRes->fetch_all(MYSQLI_ASSOC) : [];
        $expStmt->close();
    }
} else {
    // Employer profile
    $epStmt = $conn->prepare('SELECT * FROM employer_profiles WHERE user_id = ? LIMIT 1');
    $epStmt->bind_param('i', $userId);
    $epStmt->execute();
    $epRes = $epStmt->get_result();
    $employerProfile = $epRes ? $epRes->fetch_assoc() : null;
    $epStmt->close();

    if ($employerProfile) {
        $user['username'] = $employerProfile['company_name'] ?? $user['username'];
        $user['website'] = $employerProfile['website'] ?? ($user['website'] ?? '');
        $user['phone'] = $employerProfile['phone'] ?? ($user['phone'] ?? '');
        $user['bio'] = $employerProfile['description'] ?? ($user['bio'] ?? '');
        $user['logo_file'] = $employerProfile['company_logo'] ?? ($user['logo_file'] ?? '');
        $user['industry'] = $employerProfile['industry'] ?? '';
        $user['company_size'] = $employerProfile['company_size'] ?? '';
        $user['location'] = $employerProfile['location'] ?? ($user['location'] ?? '');
        $user['verified_company'] = $employerProfile['verified_company'] ?? 0;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'update_profile';

    if ($role === 'employer') {
        if ($action === 'update_profile') {
            $username = sanitizeInput($_POST['company_name'] ?? $user['username']);
            $website = sanitizeInput($_POST['website'] ?? ($user['website'] ?? ''));
            $headline = sanitizeInput($_POST['headline'] ?? ($user['headline'] ?? ''));
            $bio = sanitizeInput($_POST['bio'] ?? ($user['bio'] ?? ''));
            $phone = sanitizeInput($_POST['phone'] ?? ($user['phone'] ?? ''));
            $industry = sanitizeInput($_POST['industry'] ?? ($user['industry'] ?? ''));
            $companySize = sanitizeInput($_POST['company_size'] ?? ($user['company_size'] ?? ''));
            $location = sanitizeInput($_POST['location'] ?? ($user['location'] ?? ''));
            $logoFileName = $user['logo_file'] ?? '';

            // Handle logo upload
            if (!empty($_FILES['logo']['name']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
                $tmp = $_FILES['logo']['tmp_name'];
                $size = (int) $_FILES['logo']['size'];
                $info = getimagesize($tmp);
                $allowedExt = ['png', 'jpg', 'jpeg', 'gif'];
                $ext = strtolower(pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION));

                if (!$info || !in_array($ext, $allowedExt, true)) {
                    $error = 'Logo must be an image (png, jpg, jpeg, gif).';
                } elseif ($size > 2 * 1024 * 1024) {
                    $error = 'Logo must be 2MB or smaller.';
                } else {
                    $uploadDir = __DIR__ . '/uploads/logos';
                    if (!is_dir($uploadDir)) {
                        mkdir($uploadDir, 0777, true);
                    }
                    $logoFileName = 'logo_' . $userId . '_' . time() . '.' . $ext;
                    $dest = $uploadDir . '/' . $logoFileName;
                    if (!move_uploaded_file($tmp, $dest)) {
                        $error = 'Failed to upload logo.';
                    }
                }
            }

            if (empty($error)) {
                // Keep username in sync for auth but store rich data in employer_profiles
                $sql = "UPDATE users SET username = ?, headline = ?, bio = ?, website = ?, phone = ?, location = ? WHERE id = ?";
                $upd = $conn->prepare($sql);
                $upd->bind_param('ssssssi', $username, $headline, $bio, $website, $phone, $location, $userId);
                if ($upd->execute()) {
                    $upd->close();

                    // Update employer profile
                    $epSql = "UPDATE employer_profiles SET company_name = ?, company_logo = ?, industry = ?, company_size = ?, location = ?, website = ?, phone = ?, description = ? WHERE user_id = ?";
                    $ep = $conn->prepare($epSql);
                    if ($ep) {
                        $ep->bind_param('ssssssssi', $username, $logoFileName, $industry, $companySize, $location, $website, $phone, $bio, $userId);
                        $ep->execute();
                        $ep->close();
                    }

                    $success = 'Profile updated successfully.';
                    $user['username'] = $username;
                    $user['headline'] = $headline;
                    $user['bio'] = $bio;
                    $user['website'] = $website;
                    $user['phone'] = $phone;
                    $user['logo_file'] = $logoFileName;
                    $user['industry'] = $industry;
                    $user['company_size'] = $companySize;
                    $user['location'] = $location;
                    $_SESSION['username'] = $username;
                } else {
                    $error = 'Failed to update profile.';
                    $upd->close();
                }
            }
        }
    } else { // seeker actions
        if ($action === 'update_profile') {
            $username = sanitizeInput($_POST['full_name'] ?? $user['username']);
            $headline = sanitizeInput($_POST['headline'] ?? ($user['headline'] ?? ''));
            $bio = sanitizeInput($_POST['bio'] ?? ($user['bio'] ?? ''));
            $phone = sanitizeInput($_POST['phone'] ?? ($user['phone'] ?? ''));
            $location = sanitizeInput($_POST['location'] ?? ($user['location'] ?? ''));
            $experienceLevel = sanitizeInput($_POST['experience_level'] ?? ($user['experience_level'] ?? ''));
            $yearsExperience = isset($_POST['years_experience']) ? (int) $_POST['years_experience'] : ($user['years_experience'] ?? 0);
            $availability = sanitizeInput($_POST['availability'] ?? ($user['availability'] ?? ''));
            $linkedinUrl = sanitizeInput($_POST['linkedin_url'] ?? ($user['linkedin_url'] ?? ''));
            $portfolioUrl = sanitizeInput($_POST['portfolio_url'] ?? ($user['portfolio_url'] ?? ''));
            $profileVisibility = ($_POST['profile_visibility'] ?? ($user['profile_visibility'] ?? 'public')) === 'private' ? 'private' : 'public';

            // Update basic user fields (name + contact)
            $upd = $conn->prepare("UPDATE users SET username = ?, phone = ? WHERE id = ?");
            $upd->bind_param('ssi', $username, $phone, $userId);
            if ($upd->execute()) {
                $upd->close();

                // Update seeker profile rich fields
                $spSql = "UPDATE seeker_profiles
                          SET headline = ?, bio = ?, location = ?, experience_level = ?, years_experience = ?, availability = ?, profile_visibility = ?, linkedin_url = ?, portfolio_url = ?, phone = ?
                          WHERE user_id = ?";
                $sp = $conn->prepare($spSql);
                if ($sp) {
                    $sp->bind_param(
                        'ssssisisssi',
                        $headline,
                        $bio,
                        $location,
                        $experienceLevel,
                        $yearsExperience,
                        $availability,
                        $profileVisibility,
                        $linkedinUrl,
                        $portfolioUrl,
                        $phone,
                        $userId
                    );
                    $sp->execute();
                    $sp->close();
                }

                $success = 'Profile updated successfully.';
                $user['username'] = $username;
                $user['headline'] = $headline;
                $user['bio'] = $bio;
                $user['phone'] = $phone;
                $user['location'] = $location;
                $user['experience_level'] = $experienceLevel;
                $user['years_experience'] = $yearsExperience;
                $user['availability'] = $availability;
                $user['profile_visibility'] = $profileVisibility;
                $user['linkedin_url'] = $linkedinUrl;
                $user['portfolio_url'] = $portfolioUrl;
                $_SESSION['username'] = $username;
            } else {
                $error = 'Failed to update profile.';
                $upd->close();
            }
        } elseif ($action === 'upload_resume') {
            if (isset($_FILES['resume']) && $_FILES['resume']['error'] === UPLOAD_ERR_OK) {
                $fileTmp = $_FILES['resume']['tmp_name'];
                $fileName = $_FILES['resume']['name'];
                $fileSize = (int) $_FILES['resume']['size'];
                $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

                if ($ext !== 'pdf') {
                    $error = 'Resume must be a PDF file.';
                } elseif ($fileSize > 5 * 1024 * 1024) {
                    $error = 'Resume must be 5MB or smaller.';
                } else {
                    $uploadsDir = __DIR__ . '/uploads/resumes';
                    if (!is_dir($uploadsDir)) {
                        mkdir($uploadsDir, 0777, true);
                    }
                    $newName = 'profile_resume_' . $userId . '_' . time() . '.pdf';
                    $destPath = $uploadsDir . '/' . $newName;
                    if (move_uploaded_file($fileTmp, $destPath)) {
                        $sp = $conn->prepare('UPDATE seeker_profiles SET resume_file = ? WHERE user_id = ?');
                        if ($sp) {
                            $sp->bind_param('si', $newName, $userId);
                            $sp->execute();
                            $sp->close();
                        }
                        $success = 'Resume uploaded successfully.';
                        $user['resume_file'] = $newName;
                    } else {
                        $error = 'Failed to upload resume.';
                    }
                }
            } else {
                $error = 'Please choose a resume file to upload.';
            }
        } elseif ($action === 'add_skill') {
            $newSkill = sanitizeInput($_POST['new_skill'] ?? '');
            if ($newSkill !== '') {
                $ins = $conn->prepare('INSERT INTO seeker_skills (user_id, skill_name) VALUES (?, ?)');
                if ($ins) {
                    $ins->bind_param('is', $userId, $newSkill);
                    $ins->execute();
                    $ins->close();
                }
            }
        } elseif ($action === 'delete_skill') {
            $skillId = isset($_POST['skill_id']) ? (int) $_POST['skill_id'] : 0;
            if ($skillId > 0) {
                $del = $conn->prepare('DELETE FROM seeker_skills WHERE id = ? AND user_id = ?');
                if ($del) {
                    $del->bind_param('ii', $skillId, $userId);
                    $del->execute();
                    $del->close();
                }
            }
        } elseif ($action === 'add_education') {
            $institution = sanitizeInput($_POST['institution'] ?? '');
            $degree = sanitizeInput($_POST['degree'] ?? '');
            $startYear = isset($_POST['start_year']) ? (int) $_POST['start_year'] : null;
            $endYear = isset($_POST['end_year']) ? (int) $_POST['end_year'] : null;
            if ($institution !== '' && $degree !== '' && $startYear && $endYear) {
                $ins = $conn->prepare('INSERT INTO seeker_education (user_id, institution, degree, start_year, end_year) VALUES (?, ?, ?, ?, ?)');
                if ($ins) {
                    $ins->bind_param('issii', $userId, $institution, $degree, $startYear, $endYear);
                    $ins->execute();
                    $ins->close();
                }
            }
        } elseif ($action === 'delete_education') {
            $eduId = isset($_POST['education_id']) ? (int) $_POST['education_id'] : 0;
            if ($eduId > 0) {
                $del = $conn->prepare('DELETE FROM seeker_education WHERE id = ? AND user_id = ?');
                if ($del) {
                    $del->bind_param('ii', $eduId, $userId);
                    $del->execute();
                    $del->close();
                }
            }
        } elseif ($action === 'add_experience') {
            $jobTitle = sanitizeInput($_POST['job_title'] ?? '');
            $company = sanitizeInput($_POST['company'] ?? '');
            $startDate = sanitizeInput($_POST['start_date'] ?? '');
            $endDate = sanitizeInput($_POST['end_date'] ?? '');
            $description = sanitizeInput($_POST['exp_description'] ?? '');
            if ($jobTitle !== '' && $company !== '' && $startDate !== '') {
                $ins = $conn->prepare('INSERT INTO seeker_experience (user_id, job_title, company, start_date, end_date, description) VALUES (?, ?, ?, ?, ?, ?)');
                if ($ins) {
                    $ins->bind_param('isssss', $userId, $jobTitle, $company, $startDate, $endDate, $description);
                    $ins->execute();
                    $ins->close();
                }
            }
        } elseif ($action === 'delete_experience') {
            $expId = isset($_POST['experience_id']) ? (int) $_POST['experience_id'] : 0;
            if ($expId > 0) {
                $del = $conn->prepare('DELETE FROM seeker_experience WHERE id = ? AND user_id = ?');
                if ($del) {
                    $del->bind_param('ii', $expId, $userId);
                    $del->execute();
                    $del->close();
                }
            }
        }
    }
}

include 'includes/header.php';
?>

<main class="profile-page">
    <section class="profile-hero">
        <h1>Your Profile</h1>
        <p>Showcase your experience, preferences, and portfolio to get matched faster.</p>
        <?php if (!empty($success)) : ?>
            <p class="success-text"><?php echo htmlspecialchars($success); ?></p>
        <?php endif; ?>
        <?php if (!empty($error)) : ?>
            <p class="error-text"><?php echo htmlspecialchars($error); ?></p>
        <?php endif; ?>
    </section>

    <section class="profile-layout">
        <div class="profile-card">
            <?php if ($role === 'employer') : ?>
                <h2>Company Profile</h2>
                <form class="auth-form" action="" method="post" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="update_profile">
                    <div class="form-group">
                        <label for="company_name">Company Name</label>
                        <input id="company_name" name="company_name" type="text" value="<?php echo htmlspecialchars($user['username']); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="email">Email</label>
                        <input id="email" name="email" type="email" value="<?php echo htmlspecialchars($user['email']); ?>" readonly>
                    </div>
                    <div class="form-group">
                        <label for="industry">Industry</label>
                        <?php $industry = $user['industry'] ?? ''; ?>
                        <select id="industry" name="industry">
                            <option value="">Select industry</option>
                            <option value="Technology" <?php echo $industry === 'Technology' ? 'selected' : ''; ?>>Technology</option>
                            <option value="Finance" <?php echo $industry === 'Finance' ? 'selected' : ''; ?>>Finance</option>
                            <option value="Healthcare" <?php echo $industry === 'Healthcare' ? 'selected' : ''; ?>>Healthcare</option>
                            <option value="Education" <?php echo $industry === 'Education' ? 'selected' : ''; ?>>Education</option>
                            <option value="Other" <?php echo $industry === 'Other' ? 'selected' : ''; ?>>Other</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="company_size">Company Size</label>
                        <?php $companySize = $user['company_size'] ?? ''; ?>
                        <select id="company_size" name="company_size">
                            <option value="">Select size</option>
                            <option value="1-10" <?php echo $companySize === '1-10' ? 'selected' : ''; ?>>1-10</option>
                            <option value="11-50" <?php echo $companySize === '11-50' ? 'selected' : ''; ?>>11-50</option>
                            <option value="51-200" <?php echo $companySize === '51-200' ? 'selected' : ''; ?>>51-200</option>
                            <option value="200+" <?php echo $companySize === '200+' ? 'selected' : ''; ?>>200+</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="location">Location</label>
                        <input id="location" name="location" type="text" value="<?php echo htmlspecialchars($user['location'] ?? ''); ?>" placeholder="City, Country">
                    </div>
                    <div class="form-group">
                        <label for="website">Website</label>
                        <input id="website" name="website" type="url" value="<?php echo htmlspecialchars($user['website'] ?? ''); ?>" placeholder="https://company.com">
                    </div>
                    <div class="form-group">
                        <label for="phone">Phone</label>
                        <input id="phone" name="phone" type="text" value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label for="bio">Company Description</label>
                        <textarea id="bio" name="bio" rows="4" placeholder="Who you are, what you build, and why candidates should care."><?php echo htmlspecialchars($user['bio'] ?? ''); ?></textarea>
                    </div>
                    <div class="form-group">
                        <label for="logo">Company Logo (max 2MB)</label>
                        <input id="logo" name="logo" type="file" accept="image/*">
                        <?php if (!empty($user['logo_file'])) : ?>
                            <p>Current: <img src="uploads/logos/<?php echo htmlspecialchars($user['logo_file']); ?>" alt="logo" style="width:64px;height:64px;object-fit:cover;border-radius:8px;"></p>
                        <?php endif; ?>
                    </div>
                    <div class="form-group">
                        <?php $verified = (int) ($user['verified_company'] ?? 0); ?>
                        <?php if ($verified === 1) : ?>
                            <span class="badge">Verified Company ✓</span>
                        <?php else : ?>
                            <span class="badge badge-muted">Not Verified</span>
                            <p class="muted-text">Verification helps candidates trust your brand. Contact support to verify your company.</p>
                        <?php endif; ?>
                    </div>
                    <button class="btn-primary" type="submit">Save Profile</button>
                </form>
            <?php else : ?>
                <h2>Your Details</h2>
                <form class="auth-form" action="" method="post">
                    <input type="hidden" name="action" value="update_profile">
                    <div class="form-group">
                        <label for="full_name">Full Name</label>
                        <input id="full_name" name="full_name" type="text" value="<?php echo htmlspecialchars($user['username']); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="email">Email</label>
                        <input id="email" name="email" type="email" value="<?php echo htmlspecialchars($user['email']); ?>" readonly>
                    </div>
                    <div class="form-group">
                        <label for="phone">Phone</label>
                        <input id="phone" name="phone" type="text" value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label for="location">Location</label>
                        <input id="location" name="location" type="text" placeholder="City, Country" value="<?php echo htmlspecialchars($user['location'] ?? ''); ?>">
                    </div>

                    <h3>Professional</h3>
                    <div class="form-group">
                        <label for="headline">Headline</label>
                        <input id="headline" name="headline" type="text" placeholder="e.g., Senior Java Dev" value="<?php echo htmlspecialchars($user['headline'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label for="bio">Bio</label>
                        <textarea id="bio" name="bio" rows="4" placeholder="Summarize your impact, specialties, and industries."><?php echo htmlspecialchars($user['bio'] ?? ''); ?></textarea>
                    </div>
                    <div class="form-group">
                        <label for="experience_level">Experience Level</label>
                        <?php $expLevel = $user['experience_level'] ?? ''; ?>
                        <select id="experience_level" name="experience_level">
                            <option value="">Select level</option>
                            <option value="Intern" <?php echo $expLevel === 'Intern' ? 'selected' : ''; ?>>Intern</option>
                            <option value="Junior" <?php echo $expLevel === 'Junior' ? 'selected' : ''; ?>>Junior</option>
                            <option value="Mid" <?php echo $expLevel === 'Mid' ? 'selected' : ''; ?>>Mid</option>
                            <option value="Senior" <?php echo $expLevel === 'Senior' ? 'selected' : ''; ?>>Senior</option>
                            <option value="Lead" <?php echo $expLevel === 'Lead' ? 'selected' : ''; ?>>Lead</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="years_experience">Years of Experience</label>
                        <input id="years_experience" name="years_experience" type="number" min="0" max="60" value="<?php echo htmlspecialchars((string) ($user['years_experience'] ?? '')); ?>">
                    </div>
                    <div class="form-group">
                        <label for="availability">Availability</label>
                        <?php $availability = $user['availability'] ?? ''; ?>
                        <select id="availability" name="availability">
                            <option value="">Select availability</option>
                            <option value="Immediate" <?php echo $availability === 'Immediate' ? 'selected' : ''; ?>>Immediate</option>
                            <option value="1 Month" <?php echo $availability === '1 Month' ? 'selected' : ''; ?>>1 Month</option>
                            <option value="Open to Offers" <?php echo $availability === 'Open to Offers' ? 'selected' : ''; ?>>Open to Offers</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="profile_visibility">Profile Visibility</label>
                        <?php $visibility = $user['profile_visibility'] ?? 'public'; ?>
                        <select id="profile_visibility" name="profile_visibility">
                            <option value="public" <?php echo $visibility === 'public' ? 'selected' : ''; ?>>Public</option>
                            <option value="private" <?php echo $visibility === 'private' ? 'selected' : ''; ?>>Private</option>
                        </select>
                    </div>

                    <h3>Links</h3>
                    <div class="form-group">
                        <label for="linkedin_url">LinkedIn URL</label>
                        <input id="linkedin_url" name="linkedin_url" type="url" placeholder="https://linkedin.com/in/username" value="<?php echo htmlspecialchars($user['linkedin_url'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label for="portfolio_url">Portfolio URL</label>
                        <input id="portfolio_url" name="portfolio_url" type="url" placeholder="https://portfolio.com" value="<?php echo htmlspecialchars($user['portfolio_url'] ?? ''); ?>">
                    </div>

                    <button class="btn-primary" type="submit">Save Profile</button>
                </form>

                <h3>Resume</h3>
                <div class="form-group">
                    <?php if (!empty($user['resume_file'])) : ?>
                        <p>Current resume: <a href="uploads/resumes/<?php echo htmlspecialchars($user['resume_file']); ?>" target="_blank" rel="noopener">Download</a></p>
                    <?php else : ?>
                        <p class="muted-text">No resume uploaded yet.</p>
                    <?php endif; ?>
                    <form class="auth-form" action="" method="post" enctype="multipart/form-data">
                        <input type="hidden" name="action" value="upload_resume">
                        <div class="form-group">
                            <label for="resume">Upload New Resume (PDF, max 5MB)</label>
                            <input type="file" id="resume" name="resume" accept="application/pdf" required>
                        </div>
                        <button class="btn-secondary" type="submit">Upload Resume</button>
                    </form>
                </div>

                <h3>Skills</h3>
                <div class="form-group">
                    <div class="tag-list">
                        <?php if (!empty($seekerSkills)) : ?>
                            <?php foreach ($seekerSkills as $skillRow) : ?>
                                <?php
                                $skillLabel = $skillRow['skill_name'] ?? ($skillRow['name'] ?? ($skillRow['skill'] ?? ''));
                                if ($skillLabel === '') {
                                    continue;
                                }
                                ?>
                                <form action="" method="post" style="display:inline-block;margin:0;">
                                    <input type="hidden" name="action" value="delete_skill">
                                    <input type="hidden" name="skill_id" value="<?php echo (int) $skillRow['id']; ?>">
                                    <button type="submit" class="badge" style="border:none;background:var(--bg-secondary);cursor:pointer;">
                                        <?php echo htmlspecialchars($skillLabel); ?> ×
                                    </button>
                                </form>
                            <?php endforeach; ?>
                        <?php else : ?>
                            <p class="muted-text">No skills added yet.</p>
                        <?php endif; ?>
                    </div>
                    <form class="auth-form" action="" method="post">
                        <input type="hidden" name="action" value="add_skill">
                        <div class="form-group">
                            <label for="new_skill">Add Skill</label>
                            <input id="new_skill" name="new_skill" type="text" placeholder="e.g., React, SQL">
                        </div>
                        <button class="btn-secondary" type="submit">Add Skill</button>
                    </form>
                </div>

                <h3>Education</h3>
                <div class="form-group">
                    <?php if (!empty($seekerEducation)) : ?>
                        <ul class="value-list">
                            <?php foreach ($seekerEducation as $edu) : ?>
                                <li class="value-item">
                                    <strong><?php echo htmlspecialchars($edu['institution'] ?? ''); ?></strong>
                                    <br><?php echo htmlspecialchars($edu['degree'] ?? ''); ?>
                                    <br><span class="muted-text"><?php echo htmlspecialchars((string) ($edu['start_year'] ?? '')); ?> - <?php echo htmlspecialchars((string) ($edu['end_year'] ?? '')); ?></span>
                                    <form action="" method="post" style="margin-top:0.25rem;">
                                        <input type="hidden" name="action" value="delete_education">
                                        <input type="hidden" name="education_id" value="<?php echo (int) $edu['id']; ?>">
                                        <button class="btn-secondary" type="submit">Delete</button>
                                    </form>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php else : ?>
                        <p class="muted-text">No education entries added yet.</p>
                    <?php endif; ?>

                    <form class="auth-form" action="" method="post">
                        <input type="hidden" name="action" value="add_education">
                        <div class="form-group">
                            <label for="institution">Institution</label>
                            <input id="institution" name="institution" type="text" required>
                        </div>
                        <div class="form-group">
                            <label for="degree">Degree</label>
                            <input id="degree" name="degree" type="text" required>
                        </div>
                        <div class="form-group">
                            <label for="start_year">Start Year</label>
                            <input id="start_year" name="start_year" type="number" min="1950" max="2100" required>
                        </div>
                        <div class="form-group">
                            <label for="end_year">End Year</label>
                            <input id="end_year" name="end_year" type="number" min="1950" max="2100" required>
                        </div>
                        <button class="btn-secondary" type="submit">Add Education</button>
                    </form>
                </div>

                <h3>Experience</h3>
                <div class="form-group">
                    <?php if (!empty($seekerExperience)) : ?>
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
                                    <form action="" method="post" style="margin-top:0.25rem;">
                                        <input type="hidden" name="action" value="delete_experience">
                                        <input type="hidden" name="experience_id" value="<?php echo (int) $exp['id']; ?>">
                                        <button class="btn-secondary" type="submit">Delete</button>
                                    </form>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php else : ?>
                        <p class="muted-text">No experience entries added yet.</p>
                    <?php endif; ?>

                    <form class="auth-form" action="" method="post">
                        <input type="hidden" name="action" value="add_experience">
                        <div class="form-group">
                            <label for="job_title">Job Title</label>
                            <input id="job_title" name="job_title" type="text" required>
                        </div>
                        <div class="form-group">
                            <label for="company">Company</label>
                            <input id="company" name="company" type="text" required>
                        </div>
                        <div class="form-group">
                            <label for="start_date">Start Date</label>
                            <input id="start_date" name="start_date" type="date" required>
                        </div>
                        <div class="form-group">
                            <label for="end_date">End Date</label>
                            <input id="end_date" name="end_date" type="date">
                        </div>
                        <div class="form-group">
                            <label for="exp_description">Description</label>
                            <textarea id="exp_description" name="exp_description" rows="3" placeholder="Key responsibilities, technologies, and outcomes."></textarea>
                        </div>
                        <button class="btn-secondary" type="submit">Add Experience</button>
                    </form>
                </div>
            <?php endif; ?>
        </div>
    </section>
</main>

<?php include 'includes/footer.php'; ?>
