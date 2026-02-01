<?php
require_once 'core/db.php';
require_once 'core/functions.php';

checkLoggedIn();

$userId = $_SESSION['user_id'] ?? null;
$role = $_SESSION['role'] ?? '';

if ($role !== 'seeker') {
    header('Location: dashboard.php');
    exit();
}

$statusFilter = isset($_GET['status']) ? strtolower(sanitizeInput($_GET['status'])) : '';
$allowedStatuses = ['', 'pending', 'reviewed', 'interview', 'offered', 'hired', 'rejected'];
if (!in_array($statusFilter, $allowedStatuses, true)) {
    $statusFilter = '';
}

// Handle withdraw action (only pending)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['withdraw_id'])) {
    $withdrawId = (int) $_POST['withdraw_id'];
    if ($withdrawId > 0) {
        $wStmt = $conn->prepare("DELETE FROM applications WHERE id = ? AND seeker_id = ? AND status = 'pending'");
        if ($wStmt) {
            $wStmt->bind_param('ii', $withdrawId, $userId);
            $wStmt->execute();
            $wStmt->close();
        }
        header('Location: my-applications.php?withdrawn=1');
        exit();
    }
}

$applications = [];
$sql = "SELECT a.*, j.title, j.location, ep.company_name
        FROM applications a
        JOIN jobs j ON a.job_id = j.id
        JOIN employer_profiles ep ON j.employer_id = ep.user_id
        WHERE a.seeker_id = ?";

$params = [$userId];
$types = 'i';

if ($statusFilter !== '') {
    $sql .= " AND a.status = ?";
    $params[] = $statusFilter;
    $types .= 's';
}

$sql .= " ORDER BY a.applied_at DESC";

$stmt = $conn->prepare($sql);
if ($stmt) {
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $res = $stmt->get_result();
    $applications = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
    $stmt->close();
}

include 'includes/header.php';
?>

<main class="dashboard-page">
    <section class="welcome-section">
        <h1>My Applications</h1>
        <p>Review every role you've applied for and track status over time.</p>
    </section>

    <section class="table-wrapper">
        <form class="auth-form" method="get" action="my-applications.php" style="margin-bottom:1rem;">
            <div class="form-group">
                <label for="status">Filter by Status</label>
                <select id="status" name="status">
                    <option value="">All</option>
                    <?php foreach (['pending','reviewed','interview','offered','hired','rejected'] as $st) : ?>
                        <option value="<?php echo htmlspecialchars($st); ?>" <?php echo $statusFilter === $st ? 'selected' : ''; ?>><?php echo htmlspecialchars(ucwords($st)); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button class="btn-secondary" type="submit">Apply Filter</button>
        </form>

        <table class="data-table">
            <thead>
                <tr>
                    <th>Job Title</th>
                    <th>Company</th>
                    <th>Location</th>
                    <th>Applied On</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($applications)) : ?>
                    <?php foreach ($applications as $app) : ?>
                        <tr>
                            <td><?php echo htmlspecialchars($app['title']); ?></td>
                            <td><?php echo htmlspecialchars($app['company_name']); ?></td>
                            <td><?php echo htmlspecialchars($app['location']); ?></td>
                            <td><?php echo htmlspecialchars(date('Y-m-d', strtotime($app['applied_at']))); ?></td>
                            <td><span class="badge badge-outline"><?php echo htmlspecialchars(ucwords($app['status'])); ?></span></td>
                            <td>
                                <a class="btn-secondary" href="job-details.php?id=<?php echo (int) $app['job_id']; ?>">View Job</a>
                                <?php if ($app['status'] === 'pending') : ?>
                                    <form method="post" action="" style="display:inline-block;margin-left:0.25rem;">
                                        <input type="hidden" name="withdraw_id" value="<?php echo (int) $app['id']; ?>">
                                        <button class="btn-secondary" type="submit">Withdraw</button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else : ?>
                    <tr>
                        <td colspan="6">No applications found for this filter.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </section>
</main>

<?php include 'includes/footer.php'; ?>
