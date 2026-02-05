<?php
require_once 'core/db.php';
require_once 'core/functions.php';

checkLoggedIn();

$currentUserId = $_SESSION['user_id'] ?? null;
$currentRole = $_SESSION['role'] ?? '';
$hasMessagesTable = ensureMessagesTable($conn)
    && tableHasColumn($conn, 'messages', 'sender_id')
    && tableHasColumn($conn, 'messages', 'receiver_id')
    && tableHasColumn($conn, 'messages', 'body');
$messageCreatedCol = $hasMessagesTable ? firstExistingColumn($conn, 'messages', ['created_at', 'created']) : null;
$messageReadCol = $hasMessagesTable ? firstExistingColumn($conn, 'messages', ['is_read', 'read', 'seen']) : null;
$messageIdCol = $hasMessagesTable ? firstExistingColumn($conn, 'messages', ['id', 'message_id', 'msg_id']) : null;

// Handle sending a new message
if ($hasMessagesTable && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['to_id'], $_POST['body'])) {
    $toId = (int) $_POST['to_id'];
    $body = trim($_POST['body']);
    if ($toId > 0 && $toId !== $currentUserId && $body !== '') {
        $cleanBody = sanitizeInput($body);
        $cols = ['sender_id', 'receiver_id', 'body'];
        $vals = ['?', '?', '?'];
        $types = 'iis';
        $params = [$currentUserId, $toId, $cleanBody];

        if ($messageCreatedCol !== null) {
            $cols[] = $messageCreatedCol;
            $vals[] = 'NOW()';
        }
        if ($messageReadCol !== null) {
            $cols[] = $messageReadCol;
            $vals[] = '?';
            $types .= 'i';
            $params[] = 0;
        }

        $insertSql = 'INSERT INTO messages (' . implode(', ', $cols) . ') VALUES (' . implode(', ', $vals) . ')';
        $msgStmt = $conn->prepare($insertSql);
        if ($msgStmt) {
            $bindParams = $params;
            bindStmtParams($msgStmt, $types, $bindParams);
            $msgStmt->execute();
            $msgStmt->close();
        }

        $senderName = (string) ($_SESSION['username'] ?? 'Someone');
        $excerpt = $cleanBody;
        if (strlen($excerpt) > 140) {
            $excerpt = substr($excerpt, 0, 137) . '...';
        }
        createNotification($conn, $toId, 'New message', $senderName . ' sent you a message: ' . $excerpt);

        header('Location: messages.php?with=' . $toId);
        exit();
    }
}

// Load conversation list
$conversations = [];
$otherIds = [];
if ($hasMessagesTable) {
    $convStmt = $conn->prepare('SELECT DISTINCT CASE WHEN sender_id = ? THEN receiver_id ELSE sender_id END AS other_id FROM messages WHERE sender_id = ? OR receiver_id = ?');
    if ($convStmt) {
        $convStmt->bind_param('iii', $currentUserId, $currentUserId, $currentUserId);
        $convStmt->execute();
        $convRes = $convStmt->get_result();
        $otherIds = $convRes ? $convRes->fetch_all(MYSQLI_ASSOC) : [];
        $convStmt->close();
    }

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

        $lastBody = '';
        $lastAt = '';
        $orderCol = $messageCreatedCol !== null ? $messageCreatedCol : ($messageIdCol !== null ? $messageIdCol : 'sender_id');
        $lastSelectAt = $messageCreatedCol !== null ? ($messageCreatedCol . ' AS created_at') : 'NULL AS created_at';
        $lastStmt = $conn->prepare('SELECT body, ' . $lastSelectAt . ' FROM messages WHERE (sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?) ORDER BY ' . $orderCol . ' DESC LIMIT 1');
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

        $unread = 0;
        if ($messageReadCol !== null) {
            $unreadStmt = $conn->prepare('SELECT COUNT(*) AS c FROM messages WHERE sender_id = ? AND receiver_id = ? AND ' . $messageReadCol . ' = 0');
            if ($unreadStmt) {
                $unreadStmt->bind_param('ii', $otherId, $currentUserId);
                $unreadStmt->execute();
                $unRes = $unreadStmt->get_result();
                if ($unRes && $u = $unRes->fetch_assoc()) {
                    $unread = (int) $u['c'];
                }
                $unreadStmt->close();
            }
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

if ($hasMessagesTable && $selectedId > 0) {
    $uStmt = $conn->prepare('SELECT username FROM users WHERE id = ? LIMIT 1');
    if ($uStmt) {
        $uStmt->bind_param('i', $selectedId);
        $uStmt->execute();
        $uRes = $uStmt->get_result();
        $otherUser = $uRes ? $uRes->fetch_assoc() : null;
        $uStmt->close();
    }

    $orderCol = $messageCreatedCol !== null ? $messageCreatedCol : ($messageIdCol !== null ? $messageIdCol : 'sender_id');
    $selectCreated = $messageCreatedCol !== null ? ('m.' . $messageCreatedCol . ' AS created_at') : 'NULL AS created_at';
    $msgStmt = $conn->prepare('SELECT m.sender_id, m.receiver_id, m.body, ' . $selectCreated . ' FROM messages m WHERE (m.sender_id = ? AND m.receiver_id = ?) OR (m.sender_id = ? AND m.receiver_id = ?) ORDER BY ' . $orderCol . ' ASC');
    if ($msgStmt) {
        $msgStmt->bind_param('iiii', $currentUserId, $selectedId, $selectedId, $currentUserId);
        $msgStmt->execute();
        $mRes = $msgStmt->get_result();
        $messages = $mRes ? $mRes->fetch_all(MYSQLI_ASSOC) : [];
        $msgStmt->close();
    }

    // Mark incoming messages as read
    if ($messageReadCol !== null) {
        $readStmt = $conn->prepare('UPDATE messages SET ' . $messageReadCol . ' = 1 WHERE sender_id = ? AND receiver_id = ? AND ' . $messageReadCol . ' = 0');
        if ($readStmt) {
            $readStmt->bind_param('ii', $selectedId, $currentUserId);
            $readStmt->execute();
            $readStmt->close();
        }
    }
}

include 'includes/header.php';
?>

<main class="dashboard-page messages-page">
    <section class="welcome-section welcome-section--dashboard">
        <div class="welcome-inner">
            <div class="welcome-eyebrow">Inbox</div>
            <h1>Messages</h1>
            <p>Keep your conversations organized and respond quickly.</p>
            <div class="welcome-actions">
                <a class="btn-secondary" href="dashboard.php">Back to dashboard</a>
                <a class="btn-secondary" href="notifications.php">Notifications</a>
            </div>
        </div>
    </section>

    <section class="layout-with-filters">
        <aside class="filter-panel messages-sidebar">
            <h3>Conversations</h3>
            <div class="list-grid messages-conversation-list">
                <?php if (!empty($conversations)) : ?>
                    <?php foreach ($conversations as $conv) : ?>
                        <?php $active = ((int) $conv['other_id'] === (int) $selectedId) ? ' active' : ''; ?>
                        <a class="job-card message-conversation<?php echo $active; ?>" href="messages.php?with=<?php echo (int) $conv['other_id']; ?>">
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

        <section class="dashboard-panel messages-thread">
            <?php if ($selectedId > 0 && $otherUser) : ?>
                <div class="section-header">
                    <div>
                        <h2>Conversation with <?php echo htmlspecialchars($otherUser['username']); ?></h2>
                        <p class="muted-text">Send messages and keep track of replies.</p>
                    </div>
                </div>
                <div class="messages-thread-list">
                    <?php if (!empty($messages)) : ?>
                        <?php foreach ($messages as $m) : ?>
                            <?php $isMe = ((int) $m['sender_id'] === $currentUserId); ?>
                            <?php
                            $timeLabel = '';
                            if (!empty($m['created_at'])) {
                                $ts = strtotime((string) $m['created_at']);
                                if ($ts) {
                                    $timeLabel = date('Y-m-d H:i', $ts);
                                }
                            }
                            ?>
                            <div class="message-bubble <?php echo $isMe ? 'message-bubble--me' : 'message-bubble--them'; ?>">
                                <div class="message-bubble-meta muted-text">
                                    <?php echo $isMe ? 'You' : htmlspecialchars($otherUser['username']); ?><?php echo $timeLabel !== '' ? ' · ' . htmlspecialchars($timeLabel) : ''; ?>
                                </div>
                                <div class="message-bubble-body"><?php echo nl2br(htmlspecialchars($m['body'])); ?></div>
                            </div>
                        <?php endforeach; ?>
                    <?php else : ?>
                        <p class="muted-text">No messages yet. Say hello.</p>
                    <?php endif; ?>
                </div>
                <form class="auth-form messages-compose" action="" method="post">
                    <input type="hidden" name="to_id" value="<?php echo (int) $selectedId; ?>">
                    <div class="form-group">
                        <label for="body">Message</label>
                        <textarea id="body" name="body" rows="3" placeholder="Type your message..." required></textarea>
                    </div>
                    <button class="btn-primary" type="submit">Send</button>
                </form>
            <?php else : ?>
                <div class="section-header">
                    <div>
                        <h2>Start Messaging</h2>
                        <p class="muted-text">Select a conversation on the left to start messaging.</p>
                    </div>
                </div>
            <?php endif; ?>
        </section>
    </section>
</main>

<?php include 'includes/footer.php'; ?>
