<?php
require_once 'core/db.php';
require_once 'core/functions.php';

checkLoggedIn();

$userId = $_SESSION['user_id'] ?? null;

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
        $stmt = $conn->prepare('UPDATE notifications SET is_read = 1 WHERE user_id = ?');
        if ($stmt) {
            $stmt->bind_param('i', $userId);
            $stmt->execute();
            $stmt->close();
        }
        header('Location: notifications.php');
        exit();
    } elseif (isset($_POST['action']) && $_POST['action'] === 'mark_one' && isset($_POST['notification_id'])) {
        $nid = (int) $_POST['notification_id'];
        if ($nid > 0) {
            $stmt = $conn->prepare('UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?');
            if ($stmt) {
                $stmt->bind_param('ii', $nid, $userId);
                $stmt->execute();
                $stmt->close();
            }
        }
        header('Location: notifications.php');
        exit();
    }
}

$notifications = [];
$stmt = $conn->prepare('SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC');
if ($stmt) {
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $res = $stmt->get_result();
    $notifications = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
    $stmt->close();
}

include 'includes/header.php';
?>

<main class="dashboard-page notifications-page">
    <section class="section-header">
        <h1>Notifications</h1>
        <p>Stay up to date with activity related to your account.</p>
    </section>

    <section class="form-section" style="max-width:720px;margin:0 auto;">
        <form action="" method="post" style="margin-bottom:1rem;display:flex;justify-content:flex-end;">
            <input type="hidden" name="action" value="mark_all">
            <button class="btn-secondary" type="submit">Mark All as Read</button>
        </form>

        <div class="list-grid">
            <?php if (!empty($notifications)) : ?>
                <?php foreach ($notifications as $n) : ?>
                    <?php $isRead = !empty($n['is_read']); ?>
                    <article class="job-card" style="border-color:<?php echo $isRead ? 'var(--border)' : 'var(--primary-bg)'; ?>;">
                        <h3><?php echo htmlspecialchars($n['title'] ?? 'Notification'); ?></h3>
                        <p class="company-name"><?php echo htmlspecialchars($n['message'] ?? ''); ?></p>
                        <p class="job-meta-line">
                            <span class="muted-text"><?php echo htmlspecialchars(formatRelativeTime($n['created_at'] ?? '')); ?></span>
                            <?php if (!$isRead) : ?>
                                <span class="badge" style="margin-left:0.5rem;">New</span>
                            <?php endif; ?>
                        </p>
                        <?php if (!$isRead) : ?>
                            <form action="" method="post" style="margin-top:0.5rem;">
                                <input type="hidden" name="action" value="mark_one">
                                <input type="hidden" name="notification_id" value="<?php echo (int) $n['id']; ?>">
                                <button class="btn-secondary" type="submit">Mark as Read</button>
                            </form>
                        <?php endif; ?>
                    </article>
                <?php endforeach; ?>
            <?php else : ?>
                <p class="muted-text">No notifications yet.</p>
            <?php endif; ?>
        </div>
    </section>
</main>

<?php include 'includes/footer.php'; ?>
