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
if ($section === '') {
    $section = 'dashboard';
}

$allowedSections = ['dashboard', 'users', 'jobs', 'applications', 'reports', 'payments', 'audit'];
if (!in_array($section, $allowedSections, true)) {
    $section = 'dashboard';
}

$success = '';
$error = '';

$pageTitle = 'Admin Panel';
$pageSubtitle = 'Manage your platform settings and activity.';
if ($section === 'dashboard') {
    $pageTitle = 'Admin Dashboard';
    $pageSubtitle = 'Monitor platform-wide activity and jump into key tools.';
} elseif ($section === 'users') {
    $pageTitle = 'Users';
    $pageSubtitle = 'Search, filter, and manage user roles and statuses.';
} elseif ($section === 'jobs') {
    $pageTitle = 'Jobs';
    $pageSubtitle = 'Review job postings and update job status.';
} elseif ($section === 'applications') {
    $pageTitle = 'Applications';
    $pageSubtitle = 'Track applications and manage their statuses.';
} elseif ($section === 'reports') {
    $pageTitle = 'Reports';
    $pageSubtitle = 'Review user reports and resolve platform issues.';
} elseif ($section === 'payments') {
    $pageTitle = 'Payments';
    $pageSubtitle = 'Monitor payments and update payment statuses.';
} elseif ($section === 'audit') {
    $pageTitle = 'Audit Logs';
    $pageSubtitle = 'Trace admin activity across the platform.';
}

$userStatusCol = firstExistingColumn($conn, 'users', ['account_status', 'status']);

$canWriteAudit = tableExists($conn, 'audit_logs')
    && tableHasColumn($conn, 'audit_logs', 'admin_id')
    && tableHasColumn($conn, 'audit_logs', 'action')
    && tableHasColumn($conn, 'audit_logs', 'target_type')
    && tableHasColumn($conn, 'audit_logs', 'target_id');

function adminLog(mysqli $conn, bool $enabled, int $adminId, string $action, string $targetType, int $targetId): void
{
    if (!$enabled) {
        return;
    }
    $stmt = $conn->prepare('INSERT INTO audit_logs (admin_id, action, target_type, target_id) VALUES (?, ?, ?, ?)');
    if ($stmt) {
        $stmt->bind_param('issi', $adminId, $action, $targetType, $targetId);
        $stmt->execute();
        $stmt->close();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['entity'], $_POST['action'])) {
    $entity = sanitizeInput($_POST['entity']);
    $action = sanitizeInput($_POST['action']);

    if ($entity === 'user' && isset($_POST['id'])) {
        $targetId = (int) $_POST['id'];
        if ($targetId > 0) {
            if ($action === 'delete') {
                if ($targetId === (int) $userId) {
                    $error = 'You cannot delete your own account.';
                } else {
                    $del = $conn->prepare('DELETE FROM users WHERE id = ?');
                    if ($del) {
                        $del->bind_param('i', $targetId);
                        $del->execute();
                        $del->close();
                        adminLog($conn, $canWriteAudit, (int) $userId, 'delete_user', 'user', $targetId);
                        $success = 'User deleted.';
                    }
                }
            } elseif ($action === 'set_status' && isset($_POST['status'])) {
                $status = strtolower(sanitizeInput($_POST['status']));
                $allowed = ['active', 'suspended', 'banned', 'deleted'];
                if ($targetId === (int) $userId) {
                    $error = 'You cannot change your own status.';
                } elseif (!in_array($status, $allowed, true)) {
                    $error = 'Invalid status.';
                } elseif ($userStatusCol === null) {
                    $error = 'User status column not found.';
                } else {
                    $sql = "UPDATE users SET `{$userStatusCol}` = ? WHERE id = ?";
                    $upd = $conn->prepare($sql);
                    if ($upd) {
                        $upd->bind_param('si', $status, $targetId);
                        $upd->execute();
                        $upd->close();
                        adminLog($conn, $canWriteAudit, (int) $userId, 'set_user_status:' . $status, 'user', $targetId);
                        $success = 'User status updated.';
                    }
                }
            } elseif ($action === 'set_role' && isset($_POST['role'])) {
                $newRole = strtolower(sanitizeInput($_POST['role']));
                $allowed = ['seeker', 'employer', 'admin'];
                if (!in_array($newRole, $allowed, true)) {
                    $error = 'Invalid role.';
                } elseif ($targetId === (int) $userId && $newRole !== 'admin') {
                    $error = 'You cannot remove your own admin role.';
                } else {
                    $upd = $conn->prepare('UPDATE users SET role = ? WHERE id = ?');
                    if ($upd) {
                        $upd->bind_param('si', $newRole, $targetId);
                        $upd->execute();
                        $upd->close();
                        adminLog($conn, $canWriteAudit, (int) $userId, 'set_user_role:' . $newRole, 'user', $targetId);
                        $success = 'User role updated.';
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
                    adminLog($conn, $canWriteAudit, (int) $userId, 'delete_job', 'job', $jobId);
                    $success = 'Job deleted.';
                }
            } elseif ($action === 'set_status' && isset($_POST['status'])) {
                $status = strtolower(sanitizeInput($_POST['status']));
                $allowed = ['draft', 'active', 'closed', 'expired'];
                if (!in_array($status, $allowed, true)) {
                    $error = 'Invalid job status.';
                } else {
                    $upd = $conn->prepare('UPDATE jobs SET status = ? WHERE id = ?');
                    if ($upd) {
                        $upd->bind_param('si', $status, $jobId);
                        $upd->execute();
                        $upd->close();
                        adminLog($conn, $canWriteAudit, (int) $userId, 'set_job_status:' . $status, 'job', $jobId);
                        $success = 'Job status updated.';
                    }
                }
            }
        }
    } elseif ($entity === 'application' && isset($_POST['id'])) {
        $appId = (int) $_POST['id'];
        if ($appId > 0) {
            if ($action === 'delete') {
                $del = $conn->prepare('DELETE FROM applications WHERE id = ?');
                if ($del) {
                    $del->bind_param('i', $appId);
                    $del->execute();
                    $del->close();
                    adminLog($conn, $canWriteAudit, (int) $userId, 'delete_application', 'application', $appId);
                    $success = 'Application deleted.';
                }
            } elseif ($action === 'set_status' && isset($_POST['status'])) {
                $status = strtolower(sanitizeInput($_POST['status']));
                $allowed = ['pending', 'reviewed', 'interview', 'offered', 'hired', 'rejected'];
                if (!in_array($status, $allowed, true)) {
                    $error = 'Invalid application status.';
                } else {
                    $upd = tableHasColumn($conn, 'applications', 'updated_at')
                        ? $conn->prepare("UPDATE applications SET status = ?, updated_at = COALESCE(updated_at, NOW()) WHERE id = ?")
                        : $conn->prepare('UPDATE applications SET status = ? WHERE id = ?');
                    if ($upd) {
                        $upd->bind_param('si', $status, $appId);
                        $upd->execute();
                        $upd->close();
                        adminLog($conn, $canWriteAudit, (int) $userId, 'set_application_status:' . $status, 'application', $appId);
                        $success = 'Application status updated.';
                    }
                }
            }
        }
    } elseif ($entity === 'report' && isset($_POST['id'])) {
        $reportId = (int) $_POST['id'];
        if ($reportId > 0 && tableExists($conn, 'reports')) {
            if ($action === 'delete') {
                $del = $conn->prepare('DELETE FROM reports WHERE id = ?');
                if ($del) {
                    $del->bind_param('i', $reportId);
                    $del->execute();
                    $del->close();
                    adminLog($conn, $canWriteAudit, (int) $userId, 'delete_report', 'report', $reportId);
                    $success = 'Report deleted.';
                }
            } elseif ($action === 'set_status' && isset($_POST['status'])) {
                $status = sanitizeInput($_POST['status']);
                $allowed = ['Pending', 'Reviewed', 'Resolved'];
                if (!in_array($status, $allowed, true)) {
                    $error = 'Invalid report status.';
                } else {
                    $upd = $conn->prepare('UPDATE reports SET status = ? WHERE id = ?');
                    if ($upd) {
                        $upd->bind_param('si', $status, $reportId);
                        $upd->execute();
                        $upd->close();
                        adminLog($conn, $canWriteAudit, (int) $userId, 'set_report_status:' . $status, 'report', $reportId);
                        $success = 'Report status updated.';
                    }
                }
            }
        }
    } elseif ($entity === 'payment' && isset($_POST['id'])) {
        $paymentId = (int) $_POST['id'];
        if ($paymentId > 0 && tableExists($conn, 'payments') && tableHasColumn($conn, 'payments', 'payment_status')) {
            if ($action === 'set_status' && isset($_POST['status'])) {
                $status = sanitizeInput($_POST['status']);
                $allowed = ['Pending', 'Completed', 'Failed'];
                if (!in_array($status, $allowed, true)) {
                    $error = 'Invalid payment status.';
                } else {
                    $upd = $conn->prepare('UPDATE payments SET payment_status = ? WHERE id = ?');
                    if ($upd) {
                        $upd->bind_param('si', $status, $paymentId);
                        $upd->execute();
                        $upd->close();
                        adminLog($conn, $canWriteAudit, (int) $userId, 'set_payment_status:' . $status, 'payment', $paymentId);
                        $success = 'Payment status updated.';
                    }
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
    'reports_pending' => 0,
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
    $revRes = $conn->query("SELECT COALESCE(SUM(amount), 0) AS total FROM payments WHERE payment_status = 'Completed'");
    if ($revRes && $row = $revRes->fetch_assoc()) {
        $adminStats['revenue'] = (float) $row['total'];
    }
}

$rCheck = $conn->query("SHOW TABLES LIKE 'reports'");
if ($rCheck && $rCheck->num_rows > 0) {
    $rpRes = $conn->query("SELECT COUNT(*) AS c FROM reports WHERE status = 'Pending'");
    if ($rpRes && $row = $rpRes->fetch_assoc()) {
        $adminStats['reports_pending'] = (int) $row['c'];
    }
}

// Data for users/jobs/applications sections
$users = [];
$jobs = [];
$applications = [];
$reports = [];
$payments = [];
$auditLogs = [];

if ($section === 'users') {
    $roleFilter = $_GET['role'] ?? '';
    $roleFilter = in_array($roleFilter, ['seeker', 'employer', 'admin'], true) ? $roleFilter : '';
    $statusFilter = $_GET['status'] ?? '';
    $statusFilter = in_array($statusFilter, ['', 'active', 'suspended', 'banned', 'deleted'], true) ? $statusFilter : '';
    $q = trim(sanitizeInput($_GET['q'] ?? ''));

    $sql = 'SELECT id, username, email, role';
    if ($userStatusCol !== null) {
        $sql .= ", `{$userStatusCol}` AS account_status";
    } else {
        $sql .= ', NULL AS account_status';
    }
    $sql .= ' FROM users WHERE 1=1';

    $params = [];
    $types = '';
    if ($roleFilter !== '') {
        $sql .= ' AND role = ?';
        $params[] = $roleFilter;
        $types .= 's';
    }
    if ($statusFilter !== '' && $userStatusCol !== null) {
        $sql .= " AND `{$userStatusCol}` = ?";
        $params[] = $statusFilter;
        $types .= 's';
    }
    if ($q !== '') {
        $sql .= ' AND (username LIKE ? OR email LIKE ?)';
        $like = '%' . $q . '%';
        $params[] = $like;
        $params[] = $like;
        $types .= 'ss';
    }

    $sql .= ' ORDER BY id DESC LIMIT 200';
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        if (!empty($params)) {
            bindStmtParams($stmt, $types, $params);
        }
        $stmt->execute();
        $res = $stmt->get_result();
        $users = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
        $stmt->close();
    }
} elseif ($section === 'jobs') {
    $statusFilter = $_GET['status'] ?? '';
    $statusFilter = in_array($statusFilter, ['', 'draft', 'active', 'closed', 'expired'], true) ? $statusFilter : '';
    $q = trim(sanitizeInput($_GET['q'] ?? ''));

    $sql = 'SELECT j.*, u.username AS employer_name FROM jobs j JOIN users u ON j.employer_id = u.id WHERE 1=1';
    $params = [];
    $types = '';
    if ($statusFilter !== '') {
        $sql .= ' AND j.status = ?';
        $params[] = $statusFilter;
        $types .= 's';
    }
    if ($q !== '') {
        $sql .= ' AND (j.title LIKE ? OR u.username LIKE ?)';
        $like = '%' . $q . '%';
        $params[] = $like;
        $params[] = $like;
        $types .= 'ss';
    }
    $sql .= ' ORDER BY j.created_at DESC LIMIT 200';

    $stmt = $conn->prepare($sql);
    if ($stmt) {
        if (!empty($params)) {
            bindStmtParams($stmt, $types, $params);
        }
        $stmt->execute();
        $res = $stmt->get_result();
        $jobs = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
        $stmt->close();
    }
} elseif ($section === 'applications') {
    $statusFilter = isset($_GET['status']) ? strtolower(sanitizeInput($_GET['status'])) : '';
    $statusFilter = in_array($statusFilter, ['', 'pending', 'reviewed', 'interview', 'offered', 'hired', 'rejected'], true) ? $statusFilter : '';
    $q = trim(sanitizeInput($_GET['q'] ?? ''));

    $sql = 'SELECT a.*, u.username AS seeker_name, u.email AS seeker_email, j.title, j.id AS job_id FROM applications a JOIN users u ON a.seeker_id = u.id JOIN jobs j ON a.job_id = j.id WHERE 1=1';
    $params = [];
    $types = '';
    if ($statusFilter !== '') {
        $sql .= ' AND a.status = ?';
        $params[] = $statusFilter;
        $types .= 's';
    }
    if ($q !== '') {
        $sql .= ' AND (j.title LIKE ? OR u.username LIKE ? OR u.email LIKE ?)';
        $like = '%' . $q . '%';
        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
        $types .= 'sss';
    }
    $sql .= ' ORDER BY a.applied_at DESC LIMIT 200';

    $stmt = $conn->prepare($sql);
    if ($stmt) {
        if (!empty($params)) {
            bindStmtParams($stmt, $types, $params);
        }
        $stmt->execute();
        $res = $stmt->get_result();
        $applications = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
        $stmt->close();
    }
} elseif ($section === 'reports') {
    if (tableExists($conn, 'reports')) {
        $statusFilter = $_GET['status'] ?? '';
        $statusFilter = in_array($statusFilter, ['', 'Pending', 'Reviewed', 'Resolved'], true) ? $statusFilter : '';
        $sql = "SELECT r.*, reporter.username AS reporter_name, reported.username AS reported_name
                FROM reports r
                LEFT JOIN users reporter ON r.reporter_id = reporter.id
                LEFT JOIN users reported ON r.reported_user_id = reported.id
                WHERE 1=1";
        $params = [];
        $types = '';
        if ($statusFilter !== '') {
            $sql .= ' AND r.status = ?';
            $params[] = $statusFilter;
            $types .= 's';
        }
        $sql .= ' ORDER BY r.created_at DESC LIMIT 200';

        $stmt = $conn->prepare($sql);
        if ($stmt) {
            if (!empty($params)) {
                bindStmtParams($stmt, $types, $params);
            }
            $stmt->execute();
            $res = $stmt->get_result();
            $reports = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
            $stmt->close();
        }
    }
} elseif ($section === 'payments') {
    if (tableExists($conn, 'payments')) {
        $statusFilter = $_GET['status'] ?? '';
        $statusFilter = in_array($statusFilter, ['', 'Pending', 'Completed', 'Failed'], true) ? $statusFilter : '';
        $sql = "SELECT p.*, u.username AS employer_name, u.email AS employer_email
                FROM payments p
                LEFT JOIN users u ON p.employer_id = u.id
                WHERE 1=1";
        $params = [];
        $types = '';
        if ($statusFilter !== '' && tableHasColumn($conn, 'payments', 'payment_status')) {
            $sql .= ' AND p.payment_status = ?';
            $params[] = $statusFilter;
            $types .= 's';
        }
        $sql .= ' ORDER BY p.created_at DESC LIMIT 200';
        $stmt = $conn->prepare($sql);
        if ($stmt) {
            if (!empty($params)) {
                bindStmtParams($stmt, $types, $params);
            }
            $stmt->execute();
            $res = $stmt->get_result();
            $payments = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
            $stmt->close();
        }
    }
} elseif ($section === 'audit') {
    if (tableExists($conn, 'audit_logs')) {
        $sql = "SELECT l.*, u.username AS admin_name
                FROM audit_logs l
                LEFT JOIN users u ON l.admin_id = u.id
                ORDER BY l.created_at DESC LIMIT 200";
        $res = $conn->query($sql);
        if ($res) {
            $auditLogs = $res->fetch_all(MYSQLI_ASSOC);
        }
    }
}

include 'includes/header.php';
?>

<main class="admin-page dashboard-page jobs-page">
    <section class="section-header">
        <div>
            <h1><?php echo htmlspecialchars($pageTitle); ?></h1>
            <p><?php echo htmlspecialchars($pageSubtitle); ?></p>
        </div>
    </section>

    <section class="layout-with-filters">
        <aside class="filter-panel">
            <h3>Admin Navigation</h3>
            <ul class="list-grid">
                <li><a href="admin.php?section=dashboard" <?php echo $section === 'dashboard' ? 'class="active"' : ''; ?>>Dashboard</a></li>
                <li><a href="admin.php?section=users" <?php echo $section === 'users' ? 'class="active"' : ''; ?>>Users</a></li>
                <li><a href="admin.php?section=jobs" <?php echo $section === 'jobs' ? 'class="active"' : ''; ?>>Jobs</a></li>
                <li><a href="admin.php?section=applications" <?php echo $section === 'applications' ? 'class="active"' : ''; ?>>Applications</a></li>
                <li><a href="admin.php?section=reports" <?php echo $section === 'reports' ? 'class="active"' : ''; ?>>Reports</a></li>
                <li><a href="admin.php?section=payments" <?php echo $section === 'payments' ? 'class="active"' : ''; ?>>Payments</a></li>
                <li><a href="admin.php?section=audit" <?php echo $section === 'audit' ? 'class="active"' : ''; ?>>Audit Logs</a></li>
            </ul>
        </aside>

        <div class="results-panel">
            <?php if (!empty($success)) : ?>
                <p class="success-text"><?php echo htmlspecialchars($success); ?></p>
            <?php endif; ?>
            <?php if (!empty($error)) : ?>
                <p class="error-text"><?php echo htmlspecialchars($error); ?></p>
            <?php endif; ?>

            <?php if ($section === 'dashboard') : ?>
                <section class="home-stats dashboard-stats" style="margin:0;">
                    <div class="stats-grid">
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
                        <div class="stat-card">
                            <h3><?php echo number_format($adminStats['reports_pending']); ?></h3>
                            <p>Pending Reports</p>
                        </div>
                    </div>
                </section>

                <div class="section-header" style="margin-top: 1.25rem;">
                    <div>
                        <h2>Quick Links</h2>
                        <p>Jump into key admin tools.</p>
                    </div>
                </div>
                <div class="dashboard-links">
                    <a class="dashboard-link-card" href="admin.php?section=users">
                        <span class="dashboard-link-icon"><i class="fa-solid fa-users"></i></span>
                        <span class="dashboard-link-text">
                            <strong>Manage Users</strong>
                            <span class="muted-text">Roles, status, and accounts</span>
                        </span>
                        <span class="dashboard-link-arrow" aria-hidden="true"><i class="fa-solid fa-arrow-right"></i></span>
                    </a>
                    <a class="dashboard-link-card" href="admin.php?section=jobs">
                        <span class="dashboard-link-icon"><i class="fa-solid fa-briefcase"></i></span>
                        <span class="dashboard-link-text">
                            <strong>Manage Jobs</strong>
                            <span class="muted-text">Status, cleanup, and review</span>
                        </span>
                        <span class="dashboard-link-arrow" aria-hidden="true"><i class="fa-solid fa-arrow-right"></i></span>
                    </a>
                    <a class="dashboard-link-card" href="admin.php?section=applications">
                        <span class="dashboard-link-icon"><i class="fa-solid fa-file-lines"></i></span>
                        <span class="dashboard-link-text">
                            <strong>Manage Applications</strong>
                            <span class="muted-text">Pipeline and outcomes</span>
                        </span>
                        <span class="dashboard-link-arrow" aria-hidden="true"><i class="fa-solid fa-arrow-right"></i></span>
                    </a>
                    <a class="dashboard-link-card" href="admin.php?section=reports">
                        <span class="dashboard-link-icon"><i class="fa-solid fa-flag"></i></span>
                        <span class="dashboard-link-text">
                            <strong>Review Reports</strong>
                            <span class="muted-text">Moderation queue</span>
                        </span>
                        <span class="dashboard-link-arrow" aria-hidden="true"><i class="fa-solid fa-arrow-right"></i></span>
                    </a>
                </div>

            <?php elseif ($section === 'users') : ?>
                <form class="list-grid admin-filters" method="get" action="admin.php">
                    <input type="hidden" name="section" value="users">
                    <div class="filter-group">
                        <label for="role">Role</label>
                        <select id="role" name="role">
                            <option value="">All</option>
                            <option value="seeker" <?php echo (($_GET['role'] ?? '') === 'seeker') ? 'selected' : ''; ?>>Seeker</option>
                            <option value="employer" <?php echo (($_GET['role'] ?? '') === 'employer') ? 'selected' : ''; ?>>Employer</option>
                            <option value="admin" <?php echo (($_GET['role'] ?? '') === 'admin') ? 'selected' : ''; ?>>Admin</option>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label for="status">Status</label>
                        <select id="status" name="status">
                            <option value="">All</option>
                            <?php foreach (['active', 'suspended', 'banned', 'deleted'] as $st) : ?>
                                <option value="<?php echo htmlspecialchars($st); ?>" <?php echo (($_GET['status'] ?? '') === $st) ? 'selected' : ''; ?>><?php echo htmlspecialchars(ucwords($st)); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label for="q">Search</label>
                        <input id="q" name="q" type="text" placeholder="Name or email" value="<?php echo htmlspecialchars($_GET['q'] ?? ''); ?>">
                    </div>
                    <button class="btn-primary" type="submit">Apply</button>
                </form>

                <div class="table-wrapper" style="margin-top:1rem;">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Role</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($users)) : ?>
                                <?php foreach ($users as $u) : ?>
                                    <?php
                                    $rowStatus = strtolower((string) ($u['account_status'] ?? ''));
                                    $rowStatusLabel = $rowStatus !== '' ? ucwords($rowStatus) : '';
                                    $rowStatusClass = 'badge-status--muted';
                                    if ($rowStatus === 'active') {
                                        $rowStatusClass = 'badge-status--active';
                                    } elseif ($rowStatus === 'suspended') {
                                        $rowStatusClass = 'badge-status--suspended';
                                    } elseif ($rowStatus === 'banned') {
                                        $rowStatusClass = 'badge-status--banned';
                                    } elseif ($rowStatus === 'deleted') {
                                        $rowStatusClass = 'badge-status--deleted';
                                    }
                                    ?>
                                    <tr>
                                        <td><?php echo (int) $u['id']; ?></td>
                                        <td><?php echo htmlspecialchars($u['username']); ?></td>
                                        <td><?php echo htmlspecialchars($u['email']); ?></td>
                                        <td><?php echo htmlspecialchars($u['role']); ?></td>
                                        <td>
                                            <?php if ($rowStatusLabel !== '') : ?>
                                                <span class="badge badge-outline badge-status <?php echo htmlspecialchars($rowStatusClass); ?>"><?php echo htmlspecialchars($rowStatusLabel); ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="admin-actions-cell">
                                                <div class="admin-actions-left">
                                                    <form class="admin-inline-form admin-inline-form--grid" action="" method="post">
                                                        <input type="hidden" name="entity" value="user">
                                                        <input type="hidden" name="id" value="<?php echo (int) $u['id']; ?>">
                                                        <input type="hidden" name="action" value="set_status">
                                                        <select name="status" aria-label="Update user status">
                                                            <?php foreach (['active', 'suspended', 'banned', 'deleted'] as $st) : ?>
                                                                <option value="<?php echo htmlspecialchars($st); ?>" <?php echo (($u['account_status'] ?? '') === $st) ? 'selected' : ''; ?>><?php echo htmlspecialchars(ucwords($st)); ?></option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                        <button class="btn-secondary" type="submit">Update</button>
                                                    </form>

                                                    <form class="admin-inline-form admin-inline-form--grid" action="" method="post">
                                                        <input type="hidden" name="entity" value="user">
                                                        <input type="hidden" name="id" value="<?php echo (int) $u['id']; ?>">
                                                        <input type="hidden" name="action" value="set_role">
                                                        <select name="role" aria-label="Update user role">
                                                            <?php foreach (['seeker', 'employer', 'admin'] as $r) : ?>
                                                                <option value="<?php echo htmlspecialchars($r); ?>" <?php echo (($u['role'] ?? '') === $r) ? 'selected' : ''; ?>><?php echo htmlspecialchars(ucwords($r)); ?></option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                        <button class="btn-secondary" type="submit">Set Role</button>
                                                    </form>
                                                </div>

                                                <div class="admin-actions-right">
                                                    <form class="admin-inline-form" action="" method="post">
                                                        <input type="hidden" name="entity" value="user">
                                                        <input type="hidden" name="id" value="<?php echo (int) $u['id']; ?>">
                                                        <input type="hidden" name="action" value="delete">
                                                        <button class="btn-secondary btn-danger" type="submit">Delete</button>
                                                    </form>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else : ?>
                                <tr>
                                    <td colspan="6">No users found.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

            <?php elseif ($section === 'jobs') : ?>
                <form class="list-grid admin-filters" method="get" action="admin.php">
                    <input type="hidden" name="section" value="jobs">
                    <div class="filter-group">
                        <label for="job_status">Status</label>
                        <select id="job_status" name="status">
                            <option value="">All</option>
                            <?php foreach (['draft', 'active', 'closed', 'expired'] as $st) : ?>
                                <option value="<?php echo htmlspecialchars($st); ?>" <?php echo (($_GET['status'] ?? '') === $st) ? 'selected' : ''; ?>><?php echo htmlspecialchars(ucwords($st)); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label for="job_q">Search</label>
                        <input id="job_q" name="q" type="text" placeholder="Job title or employer" value="<?php echo htmlspecialchars($_GET['q'] ?? ''); ?>">
                    </div>
                    <button class="btn-primary" type="submit">Apply</button>
                </form>
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
                                        <td><a href="job-details.php?id=<?php echo (int) $j['id']; ?>" target="_blank"><?php echo htmlspecialchars($j['title']); ?></a></td>
                                        <td><?php echo htmlspecialchars($j['employer_name']); ?></td>
                                        <td><span class="badge badge-outline"><?php echo htmlspecialchars(ucwords($j['status'] ?? '')); ?></span></td>
                                        <td><?php echo !empty($j['created_at']) ? htmlspecialchars(date('Y-m-d', strtotime($j['created_at']))) : ''; ?></td>
                                        <td>
                                            <form action="" method="post" style="display:inline-block;">
                                                <input type="hidden" name="entity" value="job">
                                                <input type="hidden" name="id" value="<?php echo (int) $j['id']; ?>">
                                                <input type="hidden" name="action" value="set_status">
                                                <select name="status">
                                                    <?php foreach (['draft', 'active', 'closed', 'expired'] as $st) : ?>
                                                        <option value="<?php echo htmlspecialchars($st); ?>" <?php echo (($j['status'] ?? '') === $st) ? 'selected' : ''; ?>><?php echo htmlspecialchars(ucwords($st)); ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                                <button class="btn-secondary" type="submit">Update</button>
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
                <form class="list-grid admin-filters" method="get" action="admin.php">
                    <input type="hidden" name="section" value="applications">
                    <div class="filter-group">
                        <label for="app_status">Status</label>
                        <select id="app_status" name="status">
                            <option value="">All</option>
                            <?php foreach (['pending', 'reviewed', 'interview', 'offered', 'hired', 'rejected'] as $st) : ?>
                                <option value="<?php echo htmlspecialchars($st); ?>" <?php echo (($_GET['status'] ?? '') === $st) ? 'selected' : ''; ?>><?php echo htmlspecialchars(ucwords($st)); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label for="app_q">Search</label>
                        <input id="app_q" name="q" type="text" placeholder="Job, seeker name, or email" value="<?php echo htmlspecialchars($_GET['q'] ?? ''); ?>">
                    </div>
                    <button class="btn-primary" type="submit">Apply</button>
                </form>
                <div class="table-wrapper" style="margin-top:1rem;">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Job</th>
                                <th>Applicant</th>
                                <th>Applied On</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($applications)) : ?>
                                <?php foreach ($applications as $a) : ?>
                                    <tr>
                                        <td><?php echo (int) $a['id']; ?></td>
                                        <td><a href="job-details.php?id=<?php echo (int) $a['job_id']; ?>" target="_blank"><?php echo htmlspecialchars($a['title']); ?></a></td>
                                        <td><?php echo htmlspecialchars($a['seeker_name']); ?></td>
                                        <td><?php echo htmlspecialchars(date('Y-m-d', strtotime($a['applied_at']))); ?></td>
                                        <td><span class="badge badge-outline"><?php echo htmlspecialchars(ucwords($a['status'])); ?></span></td>
                                        <td>
                                            <form action="" method="post" style="display:inline-block;">
                                                <input type="hidden" name="entity" value="application">
                                                <input type="hidden" name="id" value="<?php echo (int) $a['id']; ?>">
                                                <input type="hidden" name="action" value="set_status">
                                                <select name="status">
                                                    <?php foreach (['pending', 'reviewed', 'interview', 'offered', 'hired', 'rejected'] as $st) : ?>
                                                        <option value="<?php echo htmlspecialchars($st); ?>" <?php echo (($a['status'] ?? '') === $st) ? 'selected' : ''; ?>><?php echo htmlspecialchars(ucwords($st)); ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                                <button class="btn-secondary" type="submit">Update</button>
                                            </form>
                                            <form action="" method="post" style="display:inline-block;margin-left:0.25rem;">
                                                <input type="hidden" name="entity" value="application">
                                                <input type="hidden" name="id" value="<?php echo (int) $a['id']; ?>">
                                                <input type="hidden" name="action" value="delete">
                                                <button class="btn-secondary" type="submit">Delete</button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else : ?>
                                <tr>
                                    <td colspan="6">No applications found.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

            <?php elseif ($section === 'reports') : ?>
                <?php if (!tableExists($conn, 'reports')) : ?>
                    <p class="muted-text">Reports table not configured yet.</p>
                <?php else : ?>
                    <form class="list-grid admin-filters" method="get" action="admin.php">
                        <input type="hidden" name="section" value="reports">
                        <div class="filter-group">
                            <label for="report_status">Status</label>
                            <select id="report_status" name="status">
                                <option value="">All</option>
                                <?php foreach (['Pending', 'Reviewed', 'Resolved'] as $st) : ?>
                                    <option value="<?php echo htmlspecialchars($st); ?>" <?php echo (($_GET['status'] ?? '') === $st) ? 'selected' : ''; ?>><?php echo htmlspecialchars($st); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <button class="btn-primary" type="submit">Apply</button>
                    </form>
                    <div class="table-wrapper" style="margin-top:1rem;">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Reporter</th>
                                    <th>Reported User</th>
                                    <th>Reason</th>
                                    <th>Status</th>
                                    <th>Created</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($reports)) : ?>
                                    <?php foreach ($reports as $r) : ?>
                                        <tr>
                                            <td><?php echo (int) $r['id']; ?></td>
                                            <td><?php echo htmlspecialchars($r['reporter_name'] ?? ''); ?></td>
                                            <td><?php echo htmlspecialchars($r['reported_name'] ?? ''); ?></td>
                                            <td><?php echo htmlspecialchars($r['reason'] ?? ''); ?></td>
                                            <td><span class="badge badge-outline"><?php echo htmlspecialchars($r['status'] ?? ''); ?></span></td>
                                            <td><?php echo !empty($r['created_at']) ? htmlspecialchars(date('Y-m-d', strtotime($r['created_at']))) : ''; ?></td>
                                            <td>
                                                <form action="" method="post" style="display:inline-block;">
                                                    <input type="hidden" name="entity" value="report">
                                                    <input type="hidden" name="id" value="<?php echo (int) $r['id']; ?>">
                                                    <input type="hidden" name="action" value="set_status">
                                                    <select name="status">
                                                        <?php foreach (['Pending', 'Reviewed', 'Resolved'] as $st) : ?>
                                                            <option value="<?php echo htmlspecialchars($st); ?>" <?php echo (($r['status'] ?? '') === $st) ? 'selected' : ''; ?>><?php echo htmlspecialchars($st); ?></option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                    <button class="btn-secondary" type="submit">Update</button>
                                                </form>
                                                <form action="" method="post" style="display:inline-block;margin-left:0.25rem;">
                                                    <input type="hidden" name="entity" value="report">
                                                    <input type="hidden" name="id" value="<?php echo (int) $r['id']; ?>">
                                                    <input type="hidden" name="action" value="delete">
                                                    <button class="btn-secondary" type="submit">Delete</button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else : ?>
                                    <tr>
                                        <td colspan="7">No reports found.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>

            <?php elseif ($section === 'payments') : ?>
                <?php if (!tableExists($conn, 'payments')) : ?>
                    <p class="muted-text">Payments table not configured yet.</p>
                <?php else : ?>
                    <form class="list-grid admin-filters" method="get" action="admin.php">
                        <input type="hidden" name="section" value="payments">
                        <div class="filter-group">
                            <label for="pay_status">Status</label>
                            <select id="pay_status" name="status">
                                <option value="">All</option>
                                <?php foreach (['Pending', 'Completed', 'Failed'] as $st) : ?>
                                    <option value="<?php echo htmlspecialchars($st); ?>" <?php echo (($_GET['status'] ?? '') === $st) ? 'selected' : ''; ?>><?php echo htmlspecialchars($st); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <button class="btn-primary" type="submit">Apply</button>
                    </form>
                    <div class="table-wrapper" style="margin-top:1rem;">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Employer</th>
                                    <th>Amount</th>
                                    <th>Currency</th>
                                    <th>Status</th>
                                    <th>Created</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($payments)) : ?>
                                    <?php foreach ($payments as $p) : ?>
                                        <tr>
                                            <td><?php echo (int) $p['id']; ?></td>
                                            <td><?php echo htmlspecialchars($p['employer_name'] ?? ''); ?></td>
                                            <td><?php echo htmlspecialchars($p['amount'] ?? ''); ?></td>
                                            <td><?php echo htmlspecialchars($p['currency'] ?? ''); ?></td>
                                            <td><span class="badge badge-outline"><?php echo htmlspecialchars($p['payment_status'] ?? ''); ?></span></td>
                                            <td><?php echo !empty($p['created_at']) ? htmlspecialchars(date('Y-m-d', strtotime($p['created_at']))) : ''; ?></td>
                                            <td>
                                                <form action="" method="post" style="display:inline-block;">
                                                    <input type="hidden" name="entity" value="payment">
                                                    <input type="hidden" name="id" value="<?php echo (int) $p['id']; ?>">
                                                    <input type="hidden" name="action" value="set_status">
                                                    <select name="status">
                                                        <?php foreach (['Pending', 'Completed', 'Failed'] as $st) : ?>
                                                            <option value="<?php echo htmlspecialchars($st); ?>" <?php echo (($p['payment_status'] ?? '') === $st) ? 'selected' : ''; ?>><?php echo htmlspecialchars($st); ?></option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                    <button class="btn-secondary" type="submit">Update</button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else : ?>
                                    <tr>
                                        <td colspan="7">No payments found.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>

            <?php elseif ($section === 'audit') : ?>
                <?php if (!tableExists($conn, 'audit_logs')) : ?>
                    <p class="muted-text">Audit logs table not configured yet.</p>
                <?php else : ?>
                    <div class="table-wrapper" style="margin-top:1rem;">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Admin</th>
                                    <th>Action</th>
                                    <th>Target</th>
                                    <th>Created</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($auditLogs)) : ?>
                                    <?php foreach ($auditLogs as $l) : ?>
                                        <tr>
                                            <td><?php echo (int) $l['id']; ?></td>
                                            <td><?php echo htmlspecialchars($l['admin_name'] ?? ''); ?></td>
                                            <td><?php echo htmlspecialchars($l['action'] ?? ''); ?></td>
                                            <td><?php echo htmlspecialchars(($l['target_type'] ?? '') . ':' . ($l['target_id'] ?? '')); ?></td>
                                            <td><?php echo htmlspecialchars($l['created_at'] ?? ''); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else : ?>
                                    <tr>
                                        <td colspan="5">No audit logs found.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>

            <?php endif; ?>
        </div>
    </section>
</main>

<?php include 'includes/footer.php'; ?>
