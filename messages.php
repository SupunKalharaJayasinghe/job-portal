<?php
require_once 'core/db.php';
require_once 'core/functions.php';

checkLoggedIn();

$currentUserId = $_SESSION['user_id'] ?? null;
$currentRole = $_SESSION['role'] ?? '';

// Handle sending a new message
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['to_id'], $_POST['body'])) {
    $toId = (int) $_POST['to_id'];
    $body = trim($_POST['body']);
    if ($toId > 0 && $toId !== $currentUserId && $body !== '') {
        $cleanBody = sanitizeInput($body);
        $msgStmt = $conn->prepare('INSERT INTO messages (sender_id, receiver_id, body, created_at, is_read) VALUES (?, ?, ?, NOW(), 0)');
        if ($msgStmt) {
            $msgStmt->bind_param('iis', $currentUserId, $toId, $cleanBody);
            $msgStmt->execute();
            $msgStmt->close();
        }
        header('Location: messages.php?with=' . $toId);
        exit();
    }
}

// Load conversation list
$conversations = [];
$convStmt = $conn->prepare('SELECT DISTINCT CASE WHEN sender_id = ? THEN receiver_id ELSE sender_id END AS other_id FROM messages WHERE sender_id = ? OR receiver_id = ?');
if ($convStmt) {
    $convStmt->bind_param('iii', $currentUserId, $currentUserId, $currentUserId);
    $convStmt->execute();
    $convRes = $convStmt->get_result();
    $otherIds = $convRes ? $convRes->fetch_all(MYSQLI_ASSOC) : [];
    $convStmt->close();

    foreach ($otherIds as $row) {
        $otherId = (int) $row['other_id'];
        if ($otherId <= 0) {
            continue;
        }

        $uStmt = $conn->prepare('SELECT username FROM users WHERE id = ? LIMIT 1');
        $uName = 'User';
        if ($uStmt) {
            $uStmt->bind_param('i', $otherId);
            $uStmt->execute();
            $uRes = $uStmt->get_result();
            $uRow = $uRes ? $uRes->fetch_assoc() : null;
            if ($uRow) {
                $uName = $uRow['username'];
            }
            $uStmt->close();
        }

        $lastStmt = $conn->prepare('SELECT body, created_at FROM messages WHERE (sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?) ORDER BY created_at DESC LIMIT 1');
        $lastBody = '';
        $lastAt = '';
        if ($lastStmt) {
            $lastStmt->bind_param('iiii', $currentUserId, $otherId, $otherId, $currentUserId);
            $lastStmt->execute();
            $lRes = $lastStmt->get_result();
            $lRow = $lRes ? $lRes->fetch_assoc() : null;
            if ($lRow) {
                $lastBody = $lRow['body'] ?? '';
                $lastAt = $lRow['created_at'] ?? '';
            }
            $lastStmt->close();
        }

        $unreadStmt = $conn->prepare('SELECT COUNT(*) AS c FROM messages WHERE sender_id = ? AND receiver_id = ? AND is_read = 0');
        $unread = 0;
        if ($unreadStmt) {
            $unreadStmt->bind_param('ii', $otherId, $currentUserId);
            $unreadStmt->execute();
            $unRes = $unreadStmt->get_result();
            if ($unRes && $u = $unRes->fetch_assoc()) {
                $unread = (int) $u['c'];
            }
            $unreadStmt->close();
        }

        if (strlen($lastBody) > 60) {
            $lastBody = substr($lastBody, 0, 57) . '...';
        }

        $conversations[] = [
            'other_id' => $otherId,
            'username' => $uName,
            'last_body' => $lastBody,
            'last_at' => $lastAt,
            'unread' => $unread,
        ];
    }
}

$selectedId = isset($_GET['with']) ? (int) $_GET['with'] : 0;
if ($selectedId <= 0 && !empty($conversations)) {
    $selectedId = (int) $conversations[0]['other_id'];
}

$messages = [];
$otherUser = null;

if ($selectedId > 0) {
    $uStmt = $conn->prepare('SELECT username FROM users WHERE id = ? LIMIT 1');
    if ($uStmt) {
        $uStmt->bind_param('i', $selectedId);
        $uStmt->execute();
        $uRes = $uStmt->get_result();
        $otherUser = $uRes ? $uRes->fetch_assoc() : null;
        $uStmt->close();
    }

    $msgStmt = $conn->prepare('SELECT * FROM messages WHERE (sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?) ORDER BY created_at ASC');
    if ($msgStmt) {
        $msgStmt->bind_param('iiii', $currentUserId, $selectedId, $selectedId, $currentUserId);
        $msgStmt->execute();
        $mRes = $msgStmt->get_result();
        $messages = $mRes ? $mRes->fetch_all(MYSQLI_ASSOC) : [];
        $msgStmt->close();
    }

    // Mark incoming messages as read
    $readStmt = $conn->prepare('UPDATE messages SET is_read = 1 WHERE sender_id = ? AND receiver_id = ? AND is_read = 0');
    if ($readStmt) {
        $readStmt->bind_param('ii', $selectedId, $currentUserId);
        $readStmt->execute();
        $readStmt->close();
    }
}

include 'includes/header.php';
?>

<main class="dashboard-page messages-page">
    <section class="layout-with-filters">
        <aside class="filter-panel" style="max-height:80vh;overflow:auto;">
            <h3>Conversations</h3>
            <div class="list-grid">
                <?php if (!empty($conversations)) : ?>
                    <?php foreach ($conversations as $conv) : ?>
                        <a class="job-card" href="messages.php?with=<?php echo (int) $conv['other_id']; ?>">
                            <h3><?php echo htmlspecialchars($conv['username']); ?></h3>
                            <?php if ($conv['last_body'] !== '') : ?>
                                <p class="company-name"><?php echo htmlspecialchars($conv['last_body']); ?></p>
                            <?php endif; ?>
                            <p class="job-meta-line">
                                <?php if ($conv['unread'] > 0) : ?>
                                    <span class="badge">Unread: <?php echo (int) $conv['unread']; ?></span>
                                <?php else : ?>
                                    <span class="muted-text">No new messages</span>
                                <?php endif; ?>
                            </p>
                        </a>
                    <?php endforeach; ?>
                <?php else : ?>
                    <p class="muted-text">No conversations yet.</p>
                <?php endif; ?>
            </div>
        </aside>

        <section class="profile-card" style="min-height:60vh;display:flex;flex-direction:column;gap:1rem;">
            <?php if ($selectedId > 0 && $otherUser) : ?>
                <div class="section-header">
                    <h2>Conversation with <?php echo htmlspecialchars($otherUser['username']); ?></h2>
                </div>
                <div class="list-grid" style="flex:1;overflow:auto;max-height:50vh;">
                    <?php if (!empty($messages)) : ?>
                        <?php foreach ($messages as $m) : ?>
                            <?php $isMe = ((int) $m['sender_id'] === $currentUserId); ?>
                            <div class="value-item" style="align-self:<?php echo $isMe ? 'flex-end' : 'flex-start'; ?>;max-width:80%;">
                                <p class="muted-text" style="margin-bottom:0.25rem;">
                                    <?php echo $isMe ? 'You' : htmlspecialchars($otherUser['username']); ?> · <?php echo htmlspecialchars(date('Y-m-d H:i', strtotime($m['created_at']))); ?>
                                </p>
                                <p><?php echo nl2br(htmlspecialchars($m['body'])); ?></p>
                            </div>
                        <?php endforeach; ?>
                    <?php else : ?>
                        <p class="muted-text">No messages yet. Say hello.</p>
                    <?php endif; ?>
                </div>
                <form class="auth-form" action="" method="post" style="margin-top:1rem;">
                    <input type="hidden" name="to_id" value="<?php echo (int) $selectedId; ?>">
                    <div class="form-group">
                        <label for="body">Message</label>
                        <textarea id="body" name="body" rows="3" placeholder="Type your message..." required></textarea>
                    </div>
                    <button class="btn-primary" type="submit">Send</button>
                </form>
            <?php else : ?>
                <p class="muted-text">Select a conversation on the left to start messaging.</p>
            <?php endif; ?>
        </section>
    </section>
</main>

<?php include 'includes/footer.php'; ?>
