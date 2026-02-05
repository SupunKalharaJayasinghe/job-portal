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

$hasApplicationUpdatedAt = false;
try {
    $colRes = $conn->query("SHOW COLUMNS FROM applications LIKE 'updated_at'");
    if ($colRes && $colRes->num_rows > 0) {
        $hasApplicationUpdatedAt = true;
    }
} catch (mysqli_sql_exception $e) {
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
            $currentStatus = null;
            if ($hasApplicationUpdatedAt) {
                $curStmt = $conn->prepare('SELECT status FROM applications WHERE id = ? AND job_id = ? LIMIT 1');
                $curStmt->bind_param('ii', $appId, $jobId);
                $curStmt->execute();
                $curRes = $curStmt->get_result();
                if ($curRes && $row = $curRes->fetch_assoc()) {
                    $currentStatus = strtolower((string) ($row['status'] ?? ''));
                }
                $curStmt->close();
            }

            if ($hasApplicationUpdatedAt && $currentStatus === 'pending' && $status !== 'pending') {
                $updStmt = $conn->prepare("UPDATE applications SET status = ?, updated_at = COALESCE(updated_at, NOW()) WHERE id = ? AND job_id = ?");
            } else {
                $updStmt = $conn->prepare("UPDATE applications SET status = ? WHERE id = ? AND job_id = ?");
            }

            $updStmt->bind_param('sii', $status, $appId, $jobId);
            $updStmt->execute();
            $updStmt->close();

            if ($currentStatus === null || $currentStatus !== $status) {
                $seekerId = 0;
                if (tableHasColumn($conn, 'applications', 'seeker_id')) {
                    $sidStmt = $conn->prepare('SELECT seeker_id FROM applications WHERE id = ? AND job_id = ? LIMIT 1');
                    if ($sidStmt) {
                        $sidStmt->bind_param('ii', $appId, $jobId);
                        $sidStmt->execute();
                        $sidRes = $sidStmt->get_result();
                        if ($sidRes && $sidRow = $sidRes->fetch_assoc()) {
                            $seekerId = (int) ($sidRow['seeker_id'] ?? 0);
                        }
                        $sidStmt->close();
                    }
                }

                if ($seekerId > 0) {
                    $jobTitle = (string) ($job['title'] ?? 'a job');
                    createNotification($conn, $seekerId, 'Application update', 'Your application for "' . $jobTitle . '" is now: ' . ucwords($status));
                }
            }

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

            if (tableExists($conn, 'interviews')
                && tableHasColumn($conn, 'interviews', 'application_id')
                && tableHasColumn($conn, 'interviews', 'interview_date')
                && tableHasColumn($conn, 'interviews', 'mode')
                && tableHasColumn($conn, 'interviews', 'location')
                && tableHasColumn($conn, 'interviews', 'status')) {
                $intStmt = $conn->prepare("INSERT INTO interviews (application_id, interview_date, mode, location, status) VALUES (?, ?, ?, ?, 'Scheduled')");
                if ($intStmt) {
                    $intStmt->bind_param('isss', $appId, $datetime, $mode, $location);
                    $intStmt->execute();
                    $intStmt->close();
                }
            }

            $seekerId = 0;
            if (tableHasColumn($conn, 'applications', 'seeker_id')) {
                $sidStmt = $conn->prepare('SELECT seeker_id FROM applications WHERE id = ? AND job_id = ? LIMIT 1');
                if ($sidStmt) {
                    $sidStmt->bind_param('ii', $appId, $jobId);
                    $sidStmt->execute();
                    $sidRes = $sidStmt->get_result();
                    if ($sidRes && $sidRow = $sidRes->fetch_assoc()) {
                        $seekerId = (int) ($sidRow['seeker_id'] ?? 0);
                    }
                    $sidStmt->close();
                }
            }

            if ($seekerId > 0) {
                $jobTitle = (string) ($job['title'] ?? 'a job');
                $detail = 'Interview scheduled for "' . $jobTitle . '" on ' . $date . ' ' . $time;
                if ($mode !== '') {
                    $detail .= ' (' . $mode . ')';
                }
                if ($location !== '') {
                    $detail .= ' - ' . $location;
                }
                createNotification($conn, $seekerId, 'Interview scheduled', $detail);
            }

            header('Location: view-applications.php?job_id=' . $jobId . '&scheduled=1');
            exit();
        }
    }
}

$applications = [];
$profileJoin = '';
$selectHeadline = 'NULL';
$selectPhone = 'NULL';
$selectLinkedin = 'NULL';
if (tableExists($conn, 'seeker_profiles')) {
    $profileJoin = ' LEFT JOIN seeker_profiles sp ON u.id = sp.user_id';
    if (tableHasColumn($conn, 'seeker_profiles', 'headline')) {
        $selectHeadline = 'sp.headline';
    }
    if (tableHasColumn($conn, 'seeker_profiles', 'phone')) {
        $selectPhone = 'sp.phone';
    }
    if (tableHasColumn($conn, 'seeker_profiles', 'linkedin_url')) {
        $selectLinkedin = 'sp.linkedin_url';
    }
}

$appSql = "SELECT a.*, 
                   u.username AS seeker_name,
                   u.email AS seeker_email,
                   {$selectHeadline} AS seeker_headline,
                   {$selectPhone} AS seeker_phone,
                   {$selectLinkedin} AS seeker_linkedin
            FROM applications a
            JOIN users u ON a.seeker_id = u.id";
$appSql .= $profileJoin;
$appSql .= " WHERE a.job_id = ?
             ORDER BY a.applied_at DESC";
$appStmt = $conn->prepare($appSql);
$appStmt->bind_param('i', $jobId);
$appStmt->execute();
$appRes = $appStmt->get_result();
$applications = $appRes ? $appRes->fetch_all(MYSQLI_ASSOC) : [];
$appStmt->close();

include 'includes/header.php';
?>

<main class="dashboard-page view-applications-page">
    <section class="welcome-section welcome-section--dashboard">
        <div class="welcome-inner">
            <div class="welcome-eyebrow">Employer</div>
            <h1>Applications for <?php echo htmlspecialchars($job['title']); ?></h1>
            <p>Manage applicants, review resumes, update statuses, and schedule interviews.</p>
            <p>Job status: <span class="badge badge-outline"><?php echo htmlspecialchars(ucwords($job['status'] ?? '')); ?></span></p>
            <div class="welcome-actions">
                <a class="btn-secondary" href="dashboard.php#recent-applications">Back to dashboard</a>
                <a class="btn-secondary" href="interviews.php">Interviews</a>
                <a class="btn-secondary" href="job-details.php?id=<?php echo (int) $jobId; ?>" target="_blank">View job</a>
            </div>
        </div>
    </section>

    <section class="dashboard-panel employer-section">
        <div class="section-header">
            <div>
                <h2>Applicants</h2>
                <p>Review candidates and take action in seconds.</p>
            </div>
        </div>
        <div class="table-wrapper">
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
                            <?php
                            $stRaw = strtolower((string) ($app['status'] ?? ''));
                            $stAllowed = ['pending', 'reviewed', 'interview', 'offered', 'hired', 'rejected'];
                            $stKey = in_array($stRaw, $stAllowed, true) ? $stRaw : 'pending';
                            $stLabel = $stRaw !== '' ? ucwords($stRaw) : '';
                            ?>
                            <tr id="app-<?php echo (int) $app['id']; ?>">
                                <td>
                                    <div class="applicant-cell">
                                        <div class="applicant-name"><?php echo htmlspecialchars($app['seeker_name']); ?></div>
                                        <div class="muted-text applicant-meta"><?php echo htmlspecialchars($app['seeker_email'] ?? ''); ?></div>
                                    </div>
                                </td>
                                <td><?php echo htmlspecialchars($app['seeker_headline'] ?? ''); ?></td>
                                <td><?php echo htmlspecialchars(date('Y-m-d', strtotime($app['applied_at']))); ?></td>
                                <td>
                                    <span class="badge badge-outline app-status-badge app-status-badge--<?php echo htmlspecialchars($stKey); ?>">
                                        <?php echo htmlspecialchars($stLabel); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if (!empty($app['resume_file'])) : ?>
                                        <a class="btn-secondary" href="uploads/resumes/<?php echo htmlspecialchars($app['resume_file']); ?>" target="_blank">View Resume</a>
                                    <?php else : ?>
                                        <span class="muted-text">N/A</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="app-actions-cell">
                                        <form method="post" class="app-status-form">
                                            <input type="hidden" name="action" value="update_status">
                                            <input type="hidden" name="application_id" value="<?php echo (int) $app['id']; ?>">
                                            <select name="status">
                                                <?php
                                                $options = ['pending', 'reviewed', 'interview', 'offered', 'hired', 'rejected'];
                                                foreach ($options as $opt) {
                                                    $label = ucwords($opt);
                                                    $sel = ($stRaw === $opt) ? 'selected' : '';
                                                    echo '<option value="' . htmlspecialchars($opt) . '" ' . $sel . '>' . htmlspecialchars($label) . '</option>';
                                                }
                                                ?>
                                            </select>
                                            <button class="btn-secondary" type="submit">Update</button>
                                        </form>
                                        <div class="app-quick-actions">
                                            <button class="btn-secondary schedule-btn" type="button"
                                                    data-app-id="<?php echo (int) $app['id']; ?>"
                                                    data-applicant="<?php echo htmlspecialchars($app['seeker_name']); ?>"
                                                    data-job-title="<?php echo htmlspecialchars($job['title']); ?>">
                                                Schedule Interview
                                            </button>
                                            <a class="btn-secondary" href="view-profile.php?id=<?php echo (int) $app['seeker_id']; ?>">View Profile</a>
                                            <a class="btn-secondary" href="messages.php?with=<?php echo (int) $app['seeker_id']; ?>">Message</a>
                                        </div>
                                    </div>
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
        </div>
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
                        <label for="scheduleJob">Job</label>
                        <input type="text" id="scheduleJob" value="<?php echo htmlspecialchars($job['title']); ?>" readonly>
                    </div>
                    <div class="form-group">
                        <label for="scheduleApplicant">Applicant</label>
                        <input type="text" id="scheduleApplicant" value="" readonly>
                    </div>
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
                        <label for="location">Location</label>
                        <input type="text" id="location" name="location" placeholder="Video link or office address">
                    </div>
                    <button class="btn-primary" type="submit">Schedule</button>
                </form>
            </div>
        </div>
    <?php endif; ?>
</main>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var modal = document.getElementById('scheduleModal');
    var closeBtn = document.getElementById('closeScheduleModal');
    var appIdInput = document.getElementById('scheduleAppId');
    var applicantInput = document.getElementById('scheduleApplicant');
    var jobInput = document.getElementById('scheduleJob');
    var titleEl = document.getElementById('scheduleModalTitle');
    var scheduleButtons = document.querySelectorAll('.schedule-btn');

    function openModal(appId, applicant, jobTitle) {
        if (!modal) return;
        appIdInput.value = appId;
        if (applicantInput) {
            applicantInput.value = applicant || '';
        }
        if (jobInput) {
            jobInput.value = jobTitle || '';
        }
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
