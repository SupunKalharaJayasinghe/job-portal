<?php
require_once 'core/db.php';
require_once 'core/functions.php';

checkLoggedIn();

$userId = $_SESSION['user_id'] ?? null;
$role = $_SESSION['role'] ?? '';

if ($role !== 'employer') {
    die('Access Denied');
}

$jobId = isset($_GET['job_id']) ? (int) $_GET['job_id'] : 0;
if ($jobId <= 0) {
    die('Access Denied');
}

// Verify ownership
$ownStmt = $conn->prepare("SELECT id, title, status FROM jobs WHERE id = ? AND employer_id = ? LIMIT 1");
$ownStmt->bind_param('ii', $jobId, $userId);
$ownStmt->execute();
$ownRes = $ownStmt->get_result();
$job = $ownRes ? $ownRes->fetch_assoc() : null;
$ownStmt->close();

if (!$job) {
    die('Access Denied');
}

// Handle actions: status update or schedule interview
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['application_id'])) {
    $appId = (int) $_POST['application_id'];
    $action = $_POST['action'] ?? 'update_status';

    if ($action === 'update_status' && isset($_POST['status'])) {
        $status = strtolower(sanitizeInput($_POST['status']));
        $allowed = ['pending', 'reviewed', 'interview', 'offered', 'hired', 'rejected'];
        if (in_array($status, $allowed, true)) {
            $updStmt = $conn->prepare("UPDATE applications SET status = ? WHERE id = ? AND job_id = ?");
            $updStmt->bind_param('sii', $status, $appId, $jobId);
            $updStmt->execute();
            $updStmt->close();
            header('Location: view-applications.php?job_id=' . $jobId . '&updated=1');
            exit();
        }
    } elseif ($action === 'schedule_interview') {
        $date = sanitizeInput($_POST['interview_date'] ?? '');
        $time = sanitizeInput($_POST['interview_time'] ?? '');
        $mode = sanitizeInput($_POST['mode'] ?? '');
        $location = sanitizeInput($_POST['location'] ?? '');

        if ($date !== '' && $time !== '') {
            $datetime = $date . ' ' . $time . ':00';
            $intStmt = $conn->prepare("INSERT INTO interviews (application_id, interview_date, mode, location, status) VALUES (?, ?, ?, ?, 'Scheduled')");
            if ($intStmt) {
                $intStmt->bind_param('isss', $appId, $datetime, $mode, $location);
                $intStmt->execute();
                $intStmt->close();
            }
            header('Location: view-applications.php?job_id=' . $jobId . '&scheduled=1');
            exit();
        }
    }
}

$applications = [];
$appSql = "SELECT a.*, 
                   u.username AS seeker_name,
                   u.email AS seeker_email,
                   sp.headline AS seeker_headline,
                   sp.phone AS seeker_phone,
                   sp.linkedin_url AS seeker_linkedin
            FROM applications a
            JOIN users u ON a.seeker_id = u.id
            LEFT JOIN seeker_profiles sp ON u.id = sp.user_id
            WHERE a.job_id = ?
            ORDER BY a.applied_at DESC";
$appStmt = $conn->prepare($appSql);
$appStmt->bind_param('i', $jobId);
$appStmt->execute();
$appRes = $appStmt->get_result();
$applications = $appRes ? $appRes->fetch_all(MYSQLI_ASSOC) : [];
$appStmt->close();

include 'includes/header.php';
?>

<main class="dashboard-page">
    <section class="welcome-section">
        <h1>Applications for <?php echo htmlspecialchars($job['title']); ?></h1>
        <p>Manage applicants, review resumes, update statuses, and schedule interviews.</p>
        <p class="muted-text">Job status: <span class="badge badge-outline"><?php echo htmlspecialchars(ucwords($job['status'] ?? '')); ?></span></p>
    </section>

    <section class="table-wrapper">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Applicant</th>
                    <th>Headline</th>
                    <th>Applied Date</th>
                    <th>Status</th>
                    <th>Resume</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($applications)) : ?>
                    <?php foreach ($applications as $app) : ?>
                        <tr>
                            <td><?php echo htmlspecialchars($app['seeker_name']); ?></td>
                            <td><?php echo htmlspecialchars($app['seeker_headline'] ?? ''); ?></td>
                            <td><?php echo htmlspecialchars(date('Y-m-d', strtotime($app['applied_at']))); ?></td>
                            <td><span class="badge badge-outline"><?php echo htmlspecialchars(ucwords($app['status'])); ?></span></td>
                            <td>
                                <?php if (!empty($app['resume_file'])) : ?>
                                    <a href="uploads/resumes/<?php echo htmlspecialchars($app['resume_file']); ?>" target="_blank">View Resume</a>
                                <?php else : ?>
                                    N/A
                                <?php endif; ?>
                            </td>
                            <td>
                                <form method="post" style="display:flex; gap:0.5rem; align-items:center; margin-bottom:0.25rem;">
                                    <input type="hidden" name="action" value="update_status">
                                    <input type="hidden" name="application_id" value="<?php echo (int) $app['id']; ?>">
                                    <select name="status">
                                        <?php
                                        $options = ['pending', 'reviewed', 'interview', 'offered', 'hired', 'rejected'];
                                        foreach ($options as $opt) {
                                            $label = ucwords($opt);
                                            $sel = ($app['status'] === $opt) ? 'selected' : '';
                                            echo '<option value="' . htmlspecialchars($opt) . '" ' . $sel . '>' . htmlspecialchars($label) . '</option>';
                                        }
                                        ?>
                                    </select>
                                    <button class="btn-secondary" type="submit">Update</button>
                                </form>
                                <button class="btn-secondary schedule-btn" type="button"
                                        data-app-id="<?php echo (int) $app['id']; ?>"
                                        data-applicant="<?php echo htmlspecialchars($app['seeker_name']); ?>"
                                        data-job-title="<?php echo htmlspecialchars($job['title']); ?>">
                                    Schedule Interview
                                </button>
                                <a class="btn-secondary" href="view-profile.php?id=<?php echo (int) $app['seeker_id']; ?>">View Profile</a>
                                <?php
                                $mailtoSubject = 'Interview invitation for ' . $job['title'];
                                $mailto = 'mailto:' . $app['seeker_email'] . '?subject=' . rawurlencode($mailtoSubject);
                                ?>
                                <a class="btn-secondary" href="<?php echo htmlspecialchars($mailto); ?>">Message</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else : ?>
                    <tr>
                        <td colspan="6">No applications yet.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </section>
        <?php if (!empty($applications)) : ?>
            <div class="modal-overlay" id="scheduleModal" style="display:none;">
                <div class="modal">
                    <div class="modal-header">
                        <h2 id="scheduleModalTitle">Schedule Interview</h2>
                        <button type="button" class="modal-close" id="closeScheduleModal">&times;</button>
                    </div>
                    <form class="auth-form" method="post" action="">
                        <input type="hidden" name="action" value="schedule_interview">
                        <input type="hidden" name="application_id" id="scheduleAppId" value="">
                        <div class="form-group">
                            <label for="interview_date">Date</label>
                            <input type="date" id="interview_date" name="interview_date" required>
                        </div>
                        <div class="form-group">
                            <label for="interview_time">Time</label>
                            <input type="time" id="interview_time" name="interview_time" required>
                        </div>
                        <div class="form-group">
                            <label for="mode">Mode</label>
                            <select id="mode" name="mode">
                                <option value="Online">Online</option>
                                <option value="Onsite">Onsite</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="location">Location / Link</label>
                            <input type="text" id="location" name="location" placeholder="Video link or office address">
                        </div>
                        <button class="btn-primary" type="submit">Schedule</button>
                    </form>
                </div>
            </div>
        <?php endif; ?>
    </section>
</main>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var modal = document.getElementById('scheduleModal');
    var closeBtn = document.getElementById('closeScheduleModal');
    var appIdInput = document.getElementById('scheduleAppId');
    var titleEl = document.getElementById('scheduleModalTitle');
    var scheduleButtons = document.querySelectorAll('.schedule-btn');

    function openModal(appId, applicant, jobTitle) {
        if (!modal) return;
        appIdInput.value = appId;
        if (titleEl) {
            titleEl.textContent = 'Schedule Interview: ' + jobTitle + ' – ' + applicant;
        }
        modal.style.display = 'flex';
    }

    function closeModal() {
        if (modal) {
            modal.style.display = 'none';
        }
    }

    if (scheduleButtons) {
        scheduleButtons.forEach(function (btn) {
            btn.addEventListener('click', function () {
                var appId = this.getAttribute('data-app-id');
                var applicant = this.getAttribute('data-applicant') || '';
                var jobTitle = this.getAttribute('data-job-title') || '';
                openModal(appId, applicant, jobTitle);
            });
        });
    }

    if (closeBtn) {
        closeBtn.addEventListener('click', closeModal);
    }

    if (modal) {
        modal.addEventListener('click', function (e) {
            if (e.target === modal) {
                closeModal();
            }
        });
    }
});
</script>

<?php include 'includes/footer.php'; ?>
