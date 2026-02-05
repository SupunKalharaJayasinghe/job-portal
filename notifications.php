<?php
require_once 'core/db.php';
require_once 'core/functions.php';

checkLoggedIn();

$userId = $_SESSION['user_id'] ?? null;
$hasNotificationsTable = ensureNotificationsTable($conn) && tableHasColumn($conn, 'notifications', 'user_id');
$notifIdCol = $hasNotificationsTable ? firstExistingColumn($conn, 'notifications', ['id', 'notification_id']) : null;
$notifTitleCol = $hasNotificationsTable ? firstExistingColumn($conn, 'notifications', ['title', 'subject']) : null;
$notifMessageCol = $hasNotificationsTable ? firstExistingColumn($conn, 'notifications', ['message', 'body', 'content']) : null;
$notifReadCol = $hasNotificationsTable ? firstExistingColumn($conn, 'notifications', ['is_read', 'read', 'seen']) : null;
$notifCreatedCol = $hasNotificationsTable ? firstExistingColumn($conn, 'notifications', ['created_at', 'created']) : null;

if (!function_exists('formatRelativeTime')) {
    function formatRelativeTime(string $datetime): string
    {
        $timestamp = strtotime($datetime);
        if (!$timestamp) {
            return '';
        }
        $diff = time() - $timestamp;
        if ($diff < 60) {
            return 'Just now';
        }
        if ($diff < 3600) {
            $minutes = (int) floor($diff / 60);
            return $minutes === 1 ? '1 minute ago' : $minutes . ' minutes ago';
        }
        if ($diff < 86400) {
            $hours = (int) floor($diff / 3600);
            return $hours === 1 ? '1 hour ago' : $hours . ' hours ago';
        }
        $days = (int) floor($diff / 86400);
        if ($days < 30) {
            return $days === 1 ? '1 day ago' : $days . ' days ago';
        }
        return date('M j, Y', $timestamp);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && $_POST['action'] === 'mark_all') {
        if ($hasNotificationsTable && $notifReadCol !== null) {
            $stmt = $conn->prepare('UPDATE notifications SET ' . $notifReadCol . ' = 1 WHERE user_id = ?');
            if ($stmt) {
                $stmt->bind_param('i', $userId);
                $stmt->execute();
                $stmt->close();
            }
        }
        header('Location: notifications.php');
        exit();
    } elseif (isset($_POST['action']) && $_POST['action'] === 'mark_one' && isset($_POST['notification_id'])) {
        $nid = (int) $_POST['notification_id'];
        if ($nid > 0) {
            if ($hasNotificationsTable && $notifReadCol !== null && $notifIdCol !== null) {
                $stmt = $conn->prepare('UPDATE notifications SET ' . $notifReadCol . ' = 1 WHERE ' . $notifIdCol . ' = ? AND user_id = ?');
                if ($stmt) {
                    $stmt->bind_param('ii', $nid, $userId);
                    $stmt->execute();
                    $stmt->close();
                }
            }
        }
        header('Location: notifications.php');
        exit();
    }
}

$notifications = [];
if ($hasNotificationsTable) {
    $selectId = $notifIdCol !== null ? ($notifIdCol . ' AS id') : 'NULL AS id';
    $selectTitle = $notifTitleCol !== null ? ($notifTitleCol . ' AS title') : 'NULL AS title';
    $selectMessage = $notifMessageCol !== null ? ($notifMessageCol . ' AS message') : 'NULL AS message';
    $selectCreated = $notifCreatedCol !== null ? ($notifCreatedCol . ' AS created_at') : 'NULL AS created_at';
    $selectRead = $notifReadCol !== null ? ($notifReadCol . ' AS is_read') : '0 AS is_read';
    $orderCol = $notifCreatedCol !== null ? $notifCreatedCol : ($notifIdCol !== null ? $notifIdCol : 'user_id');

    $stmt = $conn->prepare('SELECT ' . $selectId . ', ' . $selectTitle . ', ' . $selectMessage . ', ' . $selectCreated . ', ' . $selectRead . ' FROM notifications WHERE user_id = ? ORDER BY ' . $orderCol . ' DESC');
    if ($stmt) {
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $res = $stmt->get_result();
        $notifications = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
        $stmt->close();
    }
}

include 'includes/header.php';
?>

<main class="dashboard-page notifications-page">
    <section class="welcome-section welcome-section--dashboard">
        <div class="welcome-inner">
            <div class="welcome-eyebrow">Alerts</div>
            <h1>Notifications</h1>
            <p>Stay up to date with activity related to your account.</p>
            <div class="welcome-actions">
                <a class="btn-secondary" href="dashboard.php">Back to dashboard</a>
                <a class="btn-secondary" href="messages.php">Messages</a>
                <?php if ($hasNotificationsTable && $notifReadCol !== null) : ?>
                    <form action="" method="post" class="inline-form">
                        <input type="hidden" name="action" value="mark_all">
                        <button class="btn-secondary" type="submit">Mark All as Read</button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <section class="dashboard-panel">
        <div class="section-header">
            <div>
                <h2>All Notifications</h2>
                <p>Review recent updates and clear unread alerts.</p>
            </div>
        </div>

        <div class="table-wrapper">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Title</th>
                        <th>Message</th>
                        <th>Time</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!$hasNotificationsTable) : ?>
                        <tr>
                            <td colspan="5">Notifications are not available in the current database schema.</td>
                        </tr>
                    <?php elseif (!empty($notifications)) : ?>
                        <?php foreach ($notifications as $n) : ?>
                            <?php $isRead = !empty($n['is_read']); ?>
                            <tr>
                                <td><?php echo htmlspecialchars($n['title'] ?? 'Notification'); ?></td>
                                <td><?php echo htmlspecialchars($n['message'] ?? ''); ?></td>
                                <td class="muted-text"><?php echo htmlspecialchars(formatRelativeTime($n['created_at'] ?? '')); ?></td>
                                <td>
                                    <?php if ($isRead) : ?>
                                        <span class="badge badge-outline">Read</span>
                                    <?php else : ?>
                                        <span class="badge">New</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (!$isRead && !empty($n['id']) && $notifReadCol !== null && $notifIdCol !== null) : ?>
                                        <form action="" method="post" class="inline-form">
                                            <input type="hidden" name="action" value="mark_one">
                                            <input type="hidden" name="notification_id" value="<?php echo (int) $n['id']; ?>">
                                            <button class="btn-secondary" type="submit">Mark as Read</button>
                                        </form>
                                    <?php else : ?>
                                        <span class="muted-text">—</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else : ?>
                        <tr>
                            <td colspan="5">No notifications yet.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
</main>

<?php include 'includes/footer.php'; ?>
