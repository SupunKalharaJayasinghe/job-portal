<?php
require_once 'core/db.php';
require_once 'core/functions.php';

checkLoggedIn();

$userId = $_SESSION['user_id'] ?? null;
$role = $_SESSION['role'] ?? '';

if ($role !== 'admin') {
    header('Location: dashboard.php');
    exit();
}

$section = $_GET['section'] ?? 'dashboard';
$section = preg_replace('/[^a-z_]/', '', strtolower($section));

$success = '';
$error = '';

// Handle user actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['entity'])) {
    $entity = $_POST['entity'];
    $action = $_POST['action'] ?? '';

    if ($entity === 'user' && isset($_POST['id'])) {
        $targetId = (int) $_POST['id'];
        if ($targetId > 0 && $targetId !== $userId) {
            if ($action === 'delete') {
                $del = $conn->prepare('DELETE FROM users WHERE id = ?');
                if ($del) {
                    $del->bind_param('i', $targetId);
                    $del->execute();
                    $del->close();
                    $success = 'User deleted.';
                }
            } elseif ($action === 'suspend' || $action === 'ban') {
                $colCheck = $conn->query("SHOW COLUMNS FROM users LIKE 'status'");
                if ($colCheck && $colCheck->num_rows > 0) {
                    $status = $action === 'suspend' ? 'suspended' : 'banned';
                    $upd = $conn->prepare('UPDATE users SET status = ? WHERE id = ?');
                    if ($upd) {
                        $upd->bind_param('si', $status, $targetId);
                        $upd->execute();
                        $upd->close();
                        $success = 'User status updated.';
                    }
                }
            }
        }
    } elseif ($entity === 'job' && isset($_POST['id'])) {
        $jobId = (int) $_POST['id'];
        if ($jobId > 0) {
            if ($action === 'delete') {
                $del = $conn->prepare('DELETE FROM jobs WHERE id = ?');
                if ($del) {
                    $del->bind_param('i', $jobId);
                    $del->execute();
                    $del->close();
                    $success = 'Job deleted.';
                }
            } elseif ($action === 'approve' || $action === 'close') {
                $status = $action === 'approve' ? 'active' : 'closed';
                $upd = $conn->prepare('UPDATE jobs SET status = ? WHERE id = ?');
                if ($upd) {
                    $upd->bind_param('si', $status, $jobId);
                    $upd->execute();
                    $upd->close();
                    $success = 'Job status updated.';
                }
            }
        }
    }
}

// Stats for dashboard
$adminStats = [
    'users' => 0,
    'jobs' => 0,
    'applications' => 0,
    'revenue' => 0,
];

$uRes = $conn->query('SELECT COUNT(*) AS c FROM users');
if ($uRes && $row = $uRes->fetch_assoc()) {
    $adminStats['users'] = (int) $row['c'];
}
$jRes = $conn->query('SELECT COUNT(*) AS c FROM jobs');
if ($jRes && $row = $jRes->fetch_assoc()) {
    $adminStats['jobs'] = (int) $row['c'];
}
$aRes = $conn->query('SELECT COUNT(*) AS c FROM applications');
if ($aRes && $row = $aRes->fetch_assoc()) {
    $adminStats['applications'] = (int) $row['c'];
}
$tblCheck = $conn->query("SHOW TABLES LIKE 'payments'");
if ($tblCheck && $tblCheck->num_rows > 0) {
    $revRes = $conn->query('SELECT COALESCE(SUM(amount), 0) AS total FROM payments');
    if ($revRes && $row = $revRes->fetch_assoc()) {
        $adminStats['revenue'] = (float) $row['total'];
    }
}

// Data for users/jobs/applications sections
$users = [];
$jobs = [];
$applications = [];

if ($section === 'users') {
    $roleFilter = $_GET['role'] ?? '';
    $roleFilter = in_array($roleFilter, ['seeker', 'employer', 'admin'], true) ? $roleFilter : '';

    if ($roleFilter !== '') {
        $stmt = $conn->prepare('SELECT id, username, email, role FROM users WHERE role = ? ORDER BY id DESC');
        if ($stmt) {
            $stmt->bind_param('s', $roleFilter);
            $stmt->execute();
            $res = $stmt->get_result();
            $users = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
            $stmt->close();
        }
    } else {
        $res = $conn->query('SELECT id, username, email, role FROM users ORDER BY id DESC');
        if ($res) {
            $users = $res->fetch_all(MYSQLI_ASSOC);
        }
    }
} elseif ($section === 'jobs') {
    $sql = 'SELECT j.*, u.username AS employer_name FROM jobs j JOIN users u ON j.employer_id = u.id ORDER BY j.created_at DESC';
    $res = $conn->query($sql);
    if ($res) {
        $jobs = $res->fetch_all(MYSQLI_ASSOC);
    }
} elseif ($section === 'applications') {
    $sql = 'SELECT a.*, u.username AS seeker_name, j.title, j.id AS job_id FROM applications a JOIN users u ON a.seeker_id = u.id JOIN jobs j ON a.job_id = j.id ORDER BY a.applied_at DESC LIMIT 50';
    $res = $conn->query($sql);
    if ($res) {
        $applications = $res->fetch_all(MYSQLI_ASSOC);
    }
}

include 'includes/header.php';
?>

<main class="dashboard-page admin-page">
    <section class="layout-with-filters">
        <aside class="filter-panel">
            <h3>Admin Navigation</h3>
            <ul class="list-grid">
                <li><a href="admin.php?section=dashboard">Dashboard</a></li>
                <li><a href="admin.php?section=users">Users</a></li>
                <li><a href="admin.php?section=jobs">Jobs</a></li>
                <li><a href="admin.php?section=applications">Applications</a></li>
                <li><a href="admin.php?section=reports">Reports</a></li>
                <li><a href="admin.php?section=payments">Payments</a></li>
                <li><a href="admin.php?section=audit">Audit Logs</a></li>
            </ul>
        </aside>

        <section class="profile-card" style="flex:1;">
            <?php if (!empty($success)) : ?>
                <p class="success-text"><?php echo htmlspecialchars($success); ?></p>
            <?php endif; ?>
            <?php if (!empty($error)) : ?>
                <p class="error-text"><?php echo htmlspecialchars($error); ?></p>
            <?php endif; ?>

            <?php if ($section === 'dashboard') : ?>
                <h2>Platform Overview</h2>
                <div class="stats-grid" style="margin-top:1rem;">
                    <div class="stat-card">
                        <h3><?php echo number_format($adminStats['users']); ?></h3>
                        <p>Total Users</p>
                    </div>
                    <div class="stat-card">
                        <h3><?php echo number_format($adminStats['jobs']); ?></h3>
                        <p>Total Jobs</p>
                    </div>
                    <div class="stat-card">
                        <h3><?php echo number_format($adminStats['applications']); ?></h3>
                        <p>Total Applications</p>
                    </div>
                    <div class="stat-card">
                        <h3><?php echo number_format($adminStats['revenue'], 2); ?></h3>
                        <p>Total Revenue</p>
                    </div>
                </div>

            <?php elseif ($section === 'users') : ?>
                <h2>Users Management</h2>
                <form class="auth-form" method="get" action="admin.php">
                    <input type="hidden" name="section" value="users">
                    <div class="form-group">
                        <label for="role">Filter by Role</label>
                        <select id="role" name="role">
                            <option value="">All</option>
                            <option value="seeker" <?php echo (($_GET['role'] ?? '') === 'seeker') ? 'selected' : ''; ?>>Seeker</option>
                            <option value="employer" <?php echo (($_GET['role'] ?? '') === 'employer') ? 'selected' : ''; ?>>Employer</option>
                            <option value="admin" <?php echo (($_GET['role'] ?? '') === 'admin') ? 'selected' : ''; ?>>Admin</option>
                        </select>
                    </div>
                    <button class="btn-secondary" type="submit">Apply Filter</button>
                </form>

                <div class="table-wrapper" style="margin-top:1rem;">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Role</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($users)) : ?>
                                <?php foreach ($users as $u) : ?>
                                    <tr>
                                        <td><?php echo (int) $u['id']; ?></td>
                                        <td><?php echo htmlspecialchars($u['username']); ?></td>
                                        <td><?php echo htmlspecialchars($u['email']); ?></td>
                                        <td><?php echo htmlspecialchars($u['role']); ?></td>
                                        <td>
                                            <form action="" method="post" style="display:inline-block;">
                                                <input type="hidden" name="entity" value="user">
                                                <input type="hidden" name="id" value="<?php echo (int) $u['id']; ?>">
                                                <input type="hidden" name="action" value="suspend">
                                                <button class="btn-secondary" type="submit">Suspend</button>
                                            </form>
                                            <form action="" method="post" style="display:inline-block;margin-left:0.25rem;">
                                                <input type="hidden" name="entity" value="user">
                                                <input type="hidden" name="id" value="<?php echo (int) $u['id']; ?>">
                                                <input type="hidden" name="action" value="ban">
                                                <button class="btn-secondary" type="submit">Ban</button>
                                            </form>
                                            <form action="" method="post" style="display:inline-block;margin-left:0.25rem;">
                                                <input type="hidden" name="entity" value="user">
                                                <input type="hidden" name="id" value="<?php echo (int) $u['id']; ?>">
                                                <input type="hidden" name="action" value="delete">
                                                <button class="btn-secondary" type="submit">Delete</button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else : ?>
                                <tr>
                                    <td colspan="5">No users found.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

            <?php elseif ($section === 'jobs') : ?>
                <h2>Jobs Management</h2>
                <div class="table-wrapper" style="margin-top:1rem;">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Title</th>
                                <th>Employer</th>
                                <th>Status</th>
                                <th>Posted</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($jobs)) : ?>
                                <?php foreach ($jobs as $j) : ?>
                                    <tr>
                                        <td><?php echo (int) $j['id']; ?></td>
                                        <td><?php echo htmlspecialchars($j['title']); ?></td>
                                        <td><?php echo htmlspecialchars($j['employer_name']); ?></td>
                                        <td><span class="badge badge-outline"><?php echo htmlspecialchars(ucwords($j['status'] ?? '')); ?></span></td>
                                        <td><?php echo !empty($j['created_at']) ? htmlspecialchars(date('Y-m-d', strtotime($j['created_at']))) : ''; ?></td>
                                        <td>
                                            <form action="" method="post" style="display:inline-block;">
                                                <input type="hidden" name="entity" value="job">
                                                <input type="hidden" name="id" value="<?php echo (int) $j['id']; ?>">
                                                <input type="hidden" name="action" value="approve">
                                                <button class="btn-secondary" type="submit">Approve</button>
                                            </form>
                                            <form action="" method="post" style="display:inline-block;margin-left:0.25rem;">
                                                <input type="hidden" name="entity" value="job">
                                                <input type="hidden" name="id" value="<?php echo (int) $j['id']; ?>">
                                                <input type="hidden" name="action" value="close">
                                                <button class="btn-secondary" type="submit">Close</button>
                                            </form>
                                            <form action="" method="post" style="display:inline-block;margin-left:0.25rem;">
                                                <input type="hidden" name="entity" value="job">
                                                <input type="hidden" name="id" value="<?php echo (int) $j['id']; ?>">
                                                <input type="hidden" name="action" value="delete">
                                                <button class="btn-secondary" type="submit">Delete</button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else : ?>
                                <tr>
                                    <td colspan="6">No jobs found.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

            <?php elseif ($section === 'applications') : ?>
                <h2>Applications</h2>
                <div class="table-wrapper" style="margin-top:1rem;">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Job</th>
                                <th>Applicant</th>
                                <th>Applied On</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($applications)) : ?>
                                <?php foreach ($applications as $a) : ?>
                                    <tr>
                                        <td><?php echo (int) $a['id']; ?></td>
                                        <td><?php echo htmlspecialchars($a['title']); ?></td>
                                        <td><?php echo htmlspecialchars($a['seeker_name']); ?></td>
                                        <td><?php echo htmlspecialchars(date('Y-m-d', strtotime($a['applied_at']))); ?></td>
                                        <td><span class="badge badge-outline"><?php echo htmlspecialchars(ucwords($a['status'])); ?></span></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else : ?>
                                <tr>
                                    <td colspan="5">No applications found.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

            <?php elseif ($section === 'reports') : ?>
                <h2>Reports</h2>
                <?php
                $tblCheck = $conn->query("SHOW TABLES LIKE 'reports'");
                if ($tblCheck && $tblCheck->num_rows > 0) {
                    $res = $conn->query('SELECT * FROM reports ORDER BY created_at DESC LIMIT 100');
                    if ($res && $res->num_rows > 0) {
                        echo '<div class="table-wrapper"><table class="data-table"><thead><tr><th>ID</th><th>Type</th><th>Subject</th><th>Created</th></tr></thead><tbody>';
                        while ($r = $res->fetch_assoc()) {
                            echo '<tr><td>' . (int) $r['id'] . '</td><td>' . htmlspecialchars($r['type'] ?? '') . '</td><td>' . htmlspecialchars($r['subject'] ?? '') . '</td><td>' . htmlspecialchars($r['created_at'] ?? '') . '</td></tr>';
                        }
                        echo '</tbody></table></div>';
                    } else {
                        echo '<p class="muted-text">No reports found.</p>';
                    }
                } else {
                    echo '<p class="muted-text">Reports table not configured yet.</p>';
                }
                ?>

            <?php elseif ($section === 'payments') : ?>
                <h2>Payments</h2>
                <?php
                $tblCheck = $conn->query("SHOW TABLES LIKE 'payments'");
                if ($tblCheck && $tblCheck->num_rows > 0) {
                    $res = $conn->query('SELECT * FROM payments ORDER BY created_at DESC LIMIT 50');
                    if ($res && $res->num_rows > 0) {
                        echo '<div class="table-wrapper"><table class="data-table"><thead><tr><th>ID</th><th>User</th><th>Amount</th><th>Created</th></tr></thead><tbody>';
                        while ($p = $res->fetch_assoc()) {
                            echo '<tr><td>' . (int) $p['id'] . '</td><td>' . htmlspecialchars($p['user_id'] ?? '') . '</td><td>' . htmlspecialchars($p['amount'] ?? '') . '</td><td>' . htmlspecialchars($p['created_at'] ?? '') . '</td></tr>';
                        }
                        echo '</tbody></table></div>';
                    } else {
                        echo '<p class="muted-text">No payments found.</p>';
                    }
                } else {
                    echo '<p class="muted-text">Payments table not configured yet.</p>';
                }
                ?>

            <?php elseif ($section === 'audit') : ?>
                <h2>Audit Logs</h2>
                <?php
                $tblCheck = $conn->query("SHOW TABLES LIKE 'audit_logs'");
                if ($tblCheck && $tblCheck->num_rows > 0) {
                    $res = $conn->query('SELECT * FROM audit_logs ORDER BY created_at DESC LIMIT 100');
                    if ($res && $res->num_rows > 0) {
                        echo '<div class="table-wrapper"><table class="data-table"><thead><tr><th>ID</th><th>User</th><th>Action</th><th>Created</th></tr></thead><tbody>';
                        while ($l = $res->fetch_assoc()) {
                            echo '<tr><td>' . (int) $l['id'] . '</td><td>' . htmlspecialchars($l['user_id'] ?? '') . '</td><td>' . htmlspecialchars($l['action'] ?? '') . '</td><td>' . htmlspecialchars($l['created_at'] ?? '') . '</td></tr>';
                        }
                        echo '</tbody></table></div>';
                    } else {
                        echo '<p class="muted-text">No audit logs found.</p>';
                    }
                } else {
                    echo '<p class="muted-text">Audit logs table not configured yet.</p>';
                }
                ?>

            <?php endif; ?>
        </section>
    </section>
</main>

<?php include 'includes/footer.php'; ?>
