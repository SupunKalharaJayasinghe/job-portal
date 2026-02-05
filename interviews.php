<?php
require_once 'core/db.php';
require_once 'core/functions.php';

checkLoggedIn();

$userId = $_SESSION['user_id'] ?? null;
$role = $_SESSION['role'] ?? '';

if ($role !== 'seeker' && $role !== 'employer') {
    header('Location: dashboard.php');
    exit();
}

// Handle interview actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['interview_id'], $_POST['action'])) {
    $intId = (int) $_POST['interview_id'];
    $action = $_POST['action'];

    $ctx = null;
    try {
        $ctxStmt = $conn->prepare(
            'SELECT i.application_id, i.interview_date, j.title, a.seeker_id, j.employer_id '
                . 'FROM interviews i '
                . 'JOIN applications a ON i.application_id = a.id '
                . 'JOIN jobs j ON a.job_id = j.id '
                . 'WHERE i.id = ? LIMIT 1'
        );
        if ($ctxStmt) {
            $ctxStmt->bind_param('i', $intId);
            $ctxStmt->execute();
            $ctxRes = $ctxStmt->get_result();
            $ctx = $ctxRes ? $ctxRes->fetch_assoc() : null;
            $ctxStmt->close();
        }
    } catch (mysqli_sql_exception $e) {
        $ctx = null;
    }

    if ($intId > 0) {
        if ($action === 'reschedule') {
            $date = sanitizeInput($_POST['interview_date'] ?? '');
            $time = sanitizeInput($_POST['interview_time'] ?? '');
            if ($date !== '' && $time !== '') {
                $datetime = $date . ' ' . $time . ':00';
                $stmt = $conn->prepare('UPDATE interviews SET interview_date = ?, status = ? WHERE id = ?');
                if ($stmt) {
                    $status = 'Scheduled';
                    $stmt->bind_param('ssi', $datetime, $status, $intId);
                    $stmt->execute();
                    $stmt->close();
                }

                if ($ctx) {
                    $otherId = $role === 'seeker' ? (int) ($ctx['employer_id'] ?? 0) : (int) ($ctx['seeker_id'] ?? 0);
                    $jobTitle = (string) ($ctx['title'] ?? 'a job');
                    if ($otherId > 0) {
                        createNotification($conn, $otherId, 'Interview rescheduled', 'Interview for "' . $jobTitle . '" has been rescheduled to ' . $date . ' ' . $time . '.');
                    }
                }
            }
        } elseif ($action === 'cancel') {
            $stmt = $conn->prepare("UPDATE interviews SET status = 'Cancelled' WHERE id = ?");
            if ($stmt) {
                $stmt->bind_param('i', $intId);
                $stmt->execute();
                $stmt->close();
            }

            if ($ctx) {
                $otherId = $role === 'seeker' ? (int) ($ctx['employer_id'] ?? 0) : (int) ($ctx['seeker_id'] ?? 0);
                $jobTitle = (string) ($ctx['title'] ?? 'a job');
                if ($otherId > 0) {
                    createNotification($conn, $otherId, 'Interview cancelled', 'Interview for "' . $jobTitle . '" has been cancelled.');
                }
            }
        } elseif ($action === 'complete') {
            $stmt = $conn->prepare("UPDATE interviews SET status = 'Completed' WHERE id = ?");
            if ($stmt) {
                $stmt->bind_param('i', $intId);
                $stmt->execute();
                $stmt->close();
            }

            if ($ctx) {
                $otherId = $role === 'seeker' ? (int) ($ctx['employer_id'] ?? 0) : (int) ($ctx['seeker_id'] ?? 0);
                $jobTitle = (string) ($ctx['title'] ?? 'a job');
                if ($otherId > 0) {
                    createNotification($conn, $otherId, 'Interview completed', 'Interview for "' . $jobTitle . '" has been marked as completed.');
                }
            }
        }
    }

    header('Location: interviews.php');
    exit();
}

$interviews = [];

if ($role === 'seeker') {
    $sql = "SELECT i.*, j.title,
                   COALESCE(ep.company_name, employer_user.username) AS company_name
            FROM interviews i
            JOIN applications a ON i.application_id = a.id
            JOIN jobs j ON a.job_id = j.id
            JOIN users employer_user ON j.employer_id = employer_user.id
            LEFT JOIN employer_profiles ep ON j.employer_id = ep.user_id
            WHERE a.seeker_id = ?
            ORDER BY i.interview_date DESC";
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $res = $stmt->get_result();
        $interviews = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
        $stmt->close();
    }
} else {
    $sql = "SELECT i.*, j.title, u.username AS seeker_name
            FROM interviews i
            JOIN applications a ON i.application_id = a.id
            JOIN jobs j ON a.job_id = j.id
            JOIN users u ON a.seeker_id = u.id
            WHERE j.employer_id = ?
            ORDER BY i.interview_date DESC";
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $res = $stmt->get_result();
        $interviews = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
        $stmt->close();
    }
}

include 'includes/header.php';
?>

<main class="dashboard-page interviews-page">
    <section class="section-header">
        <h1>Interviews</h1>
        <?php if ($role === 'seeker') : ?>
            <p>View upcoming and past interviews for your applications.</p>
        <?php else : ?>
            <p>Manage interviews scheduled with your candidates.</p>
        <?php endif; ?>
    </section>

    <section class="table-wrapper">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Time</th>
                    <th>Job Title</th>
                    <th><?php echo $role === 'seeker' ? 'Company' : 'Candidate'; ?></th>
                    <th>Mode</th>
                    <th>Location</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($interviews)) : ?>
                    <?php foreach ($interviews as $iv) : ?>
                        <?php
                        $dt = !empty($iv['interview_date']) ? strtotime($iv['interview_date']) : null;
                        $dateStr = $dt ? date('Y-m-d', $dt) : '';
                        $timeStr = $dt ? date('H:i', $dt) : '';
                        ?>
                        <tr>
                            <td><?php echo htmlspecialchars($dateStr); ?></td>
                            <td><?php echo htmlspecialchars($timeStr); ?></td>
                            <td><?php echo htmlspecialchars($iv['title']); ?></td>
                            <td>
                                <?php if ($role === 'seeker') : ?>
                                    <?php echo htmlspecialchars($iv['company_name']); ?>
                                <?php else : ?>
                                    <?php echo htmlspecialchars($iv['seeker_name']); ?>
                                <?php endif; ?>
                            </td>
                            <td><?php echo htmlspecialchars($iv['mode'] ?? ''); ?></td>
                            <td><?php echo htmlspecialchars($iv['location'] ?? ''); ?></td>
                            <td><span class="badge badge-outline"><?php echo htmlspecialchars(ucwords($iv['status'] ?? '')); ?></span></td>
                            <td>
                                <button class="btn-secondary resched-btn" type="button"
                                        data-int-id="<?php echo (int) $iv['id']; ?>"
                                        data-job-title="<?php echo htmlspecialchars($iv['title']); ?>">
                                    Reschedule
                                </button>
                                <form action="" method="post" style="display:inline-block;margin-left:0.25rem;">
                                    <input type="hidden" name="interview_id" value="<?php echo (int) $iv['id']; ?>">
                                    <input type="hidden" name="action" value="cancel">
                                    <button class="btn-secondary" type="submit">Cancel</button>
                                </form>
                                <form action="" method="post" style="display:inline-block;margin-left:0.25rem;">
                                    <input type="hidden" name="interview_id" value="<?php echo (int) $iv['id']; ?>">
                                    <input type="hidden" name="action" value="complete">
                                    <button class="btn-secondary" type="submit">Mark Complete</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else : ?>
                    <tr>
                        <td colspan="8">No interviews found.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </section>

    <div class="modal-overlay" id="reschedModal" style="display:none;">
        <div class="modal">
            <div class="modal-header">
                <h2 id="reschedTitle">Reschedule Interview</h2>
                <button type="button" class="modal-close" id="closeReschedModal">&times;</button>
            </div>
            <form class="auth-form" action="" method="post">
                <input type="hidden" name="interview_id" id="reschedId" value="">
                <input type="hidden" name="action" value="reschedule">
                <div class="form-group">
                    <label for="interview_date">New Date</label>
                    <input type="date" id="interview_date" name="interview_date" required>
                </div>
                <div class="form-group">
                    <label for="interview_time">New Time</label>
                    <input type="time" id="interview_time" name="interview_time" required>
                </div>
                <button class="btn-primary" type="submit">Save</button>
            </form>
        </div>
    </div>
</main>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var modal = document.getElementById('reschedModal');
    var closeBtn = document.getElementById('closeReschedModal');
    var idInput = document.getElementById('reschedId');
    var titleEl = document.getElementById('reschedTitle');
    var buttons = document.querySelectorAll('.resched-btn');

    function openModal(id, title) {
        if (!modal) return;
        idInput.value = id;
        if (titleEl) {
            titleEl.textContent = 'Reschedule Interview: ' + title;
        }
        modal.style.display = 'flex';
    }

    function closeModal() {
        if (modal) {
            modal.style.display = 'none';
        }
    }

    if (buttons) {
        buttons.forEach(function (btn) {
            btn.addEventListener('click', function () {
                var id = this.getAttribute('data-int-id');
                var title = this.getAttribute('data-job-title') || '';
                openModal(id, title);
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
