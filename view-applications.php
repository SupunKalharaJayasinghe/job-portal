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
$ownStmt = $conn->prepare("SELECT id, title FROM jobs WHERE id = ? AND employer_id = ? LIMIT 1");
$ownStmt->bind_param('ii', $jobId, $userId);
$ownStmt->execute();
$ownRes = $ownStmt->get_result();
$job = $ownRes ? $ownRes->fetch_assoc() : null;
$ownStmt->close();

if (!$job) {
    die('Access Denied');
}

// Handle status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['application_id'], $_POST['status'])) {
    $appId = (int) $_POST['application_id'];
    $status = strtolower(sanitizeInput($_POST['status']));
    $allowed = ['pending', 'reviewed', 'interview', 'hired', 'rejected'];
    if (in_array($status, $allowed, true)) {
        $updStmt = $conn->prepare("UPDATE applications SET status = ? WHERE id = ? AND job_id = ?");
        $updStmt->bind_param('sii', $status, $appId, $jobId);
        $updStmt->execute();
        $updStmt->close();
        header('Location: view-applications.php?job_id=' . $jobId . '&updated=1');
        exit();
    }
}

$applications = [];
$appStmt = $conn->prepare("SELECT applications.*, users.username AS seeker_name, users.email AS seeker_email
                           FROM applications
                           JOIN users ON applications.seeker_id = users.id
                           WHERE applications.job_id = ?
                           ORDER BY applications.applied_at DESC");
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
        <p>Manage applicants, review resumes, and update statuses.</p>
    </section>

    <section class="table-wrapper">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Applicant</th>
                    <th>Email</th>
                    <th>Applied Date</th>
                    <th>Resume</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($applications)) : ?>
                    <?php foreach ($applications as $app) : ?>
                        <tr>
                            <td><?php echo htmlspecialchars($app['seeker_name']); ?></td>
                            <td><?php echo htmlspecialchars($app['seeker_email']); ?></td>
                            <td><?php echo htmlspecialchars(date('Y-m-d', strtotime($app['applied_at']))); ?></td>
                            <td>
                                <?php if (!empty($app['resume_file'])) : ?>
                                    <a href="uploads/resumes/<?php echo htmlspecialchars($app['resume_file']); ?>" target="_blank">View Resume</a>
                                <?php else : ?>
                                    N/A
                                <?php endif; ?>
                            </td>
                            <td><?php echo htmlspecialchars($app['status']); ?></td>
                            <td>
                                <form method="post" style="display:flex; gap:0.5rem; align-items:center;">
                                    <input type="hidden" name="application_id" value="<?php echo (int) $app['id']; ?>">
                                    <select name="status">
                                        <?php
                                        $options = ['pending', 'reviewed', 'interview', 'hired', 'rejected'];
                                        foreach ($options as $opt) {
                                            $label = ucwords($opt);
                                            $sel = ($app['status'] === $opt) ? 'selected' : '';
                                            echo '<option value="' . htmlspecialchars($opt) . '" ' . $sel . '>' . htmlspecialchars($label) . '</option>';
                                        }
                                        ?>
                                    </select>
                                    <button class="btn-secondary" type="submit">Update</button>
                                </form>
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
</main>

<?php include 'includes/footer.php'; ?>
