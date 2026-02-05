<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function checkLoggedIn(): void
{
    if (empty($_SESSION['user_id'])) {
        header('Location: login.php');
        exit();
    }
}

function sanitizeInput(string $value): string
{
    return trim(strip_tags($value));
}

function getTableColumns(mysqli $conn, string $table): array
{
    static $cache = [];
    $key = strtolower($table);
    if (isset($cache[$key])) {
        return $cache[$key];
    }

    $cols = [];
    try {
        $res = $conn->query('SHOW COLUMNS FROM `' . $conn->real_escape_string($table) . '`');
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                if (!empty($row['Field'])) {
                    $cols[] = (string) $row['Field'];
                }
            }
        }
    } catch (mysqli_sql_exception $e) {
        $cols = [];
    }

    $cache[$key] = $cols;
    return $cols;
}

function tableHasColumn(mysqli $conn, string $table, string $column): bool
{
    return in_array($column, getTableColumns($conn, $table), true);
}

function tableExists(mysqli $conn, string $table): bool
{
    try {
        $res = $conn->query("SHOW TABLES LIKE '" . $conn->real_escape_string($table) . "'");
        return $res && $res->num_rows > 0;
    } catch (mysqli_sql_exception $e) {
        return false;
    }
}

function firstExistingColumn(mysqli $conn, string $table, array $candidates): ?string
{
    $cols = getTableColumns($conn, $table);
    foreach ($candidates as $candidate) {
        if (in_array($candidate, $cols, true)) {
            return $candidate;
        }
    }
    return null;
}

function bindStmtParams(mysqli_stmt $stmt, string $types, array &$params): void
{
    $bind = [];
    $bind[] = $types;
    foreach (array_keys($params) as $k) {
        $bind[] = &$params[$k];
    }
    call_user_func_array([$stmt, 'bind_param'], $bind);
}

function ensureNotificationsTable(mysqli $conn): bool
{
    if (tableExists($conn, 'notifications')) {
        return true;
    }

    try {
        $conn->query(
            "CREATE TABLE IF NOT EXISTS notifications (\n"
                . "  id INT AUTO_INCREMENT PRIMARY KEY,\n"
                . "  user_id INT NOT NULL,\n"
                . "  title VARCHAR(255) NOT NULL,\n"
                . "  message TEXT NOT NULL,\n"
                . "  is_read TINYINT(1) NOT NULL DEFAULT 0,\n"
                . "  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,\n"
                . "  INDEX idx_notifications_user (user_id),\n"
                . "  INDEX idx_notifications_read (user_id, is_read)\n"
                . ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
        );
        return tableExists($conn, 'notifications');
    } catch (mysqli_sql_exception $e) {
        return false;
    }
}

function ensureMessagesTable(mysqli $conn): bool
{
    if (tableExists($conn, 'messages')) {
        return true;
    }

    try {
        $conn->query(
            "CREATE TABLE IF NOT EXISTS messages (\n"
                . "  id INT AUTO_INCREMENT PRIMARY KEY,\n"
                . "  sender_id INT NOT NULL,\n"
                . "  receiver_id INT NOT NULL,\n"
                . "  body TEXT NOT NULL,\n"
                . "  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,\n"
                . "  is_read TINYINT(1) NOT NULL DEFAULT 0,\n"
                . "  INDEX idx_messages_sender (sender_id),\n"
                . "  INDEX idx_messages_receiver (receiver_id),\n"
                . "  INDEX idx_messages_read (receiver_id, is_read)\n"
                . ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
        );
        return tableExists($conn, 'messages');
    } catch (mysqli_sql_exception $e) {
        return false;
    }
}

function createNotification(mysqli $conn, int $userId, string $title, string $message): bool
{
    if ($userId <= 0) {
        return false;
    }

    if (!ensureNotificationsTable($conn) || !tableHasColumn($conn, 'notifications', 'user_id')) {
        return false;
    }

    $titleCol = firstExistingColumn($conn, 'notifications', ['title', 'subject']);
    $messageCol = firstExistingColumn($conn, 'notifications', ['message', 'body', 'content']);
    $isReadCol = firstExistingColumn($conn, 'notifications', ['is_read', 'read', 'seen']);
    $createdAtCol = firstExistingColumn($conn, 'notifications', ['created_at', 'created']);

    $cols = ['user_id'];
    $vals = ['?'];
    $types = 'i';
    $params = [$userId];

    if ($titleCol !== null) {
        $cols[] = $titleCol;
        $vals[] = '?';
        $types .= 's';
        $params[] = $title;
    } elseif ($messageCol !== null) {
        $message = trim($title . ($message !== '' ? ': ' : '') . $message);
    }

    if ($messageCol !== null) {
        $cols[] = $messageCol;
        $vals[] = '?';
        $types .= 's';
        $params[] = $message;
    }

    if ($isReadCol !== null) {
        $isRead = 0;
        $cols[] = $isReadCol;
        $vals[] = '?';
        $types .= 'i';
        $params[] = $isRead;
    }

    if ($createdAtCol !== null) {
        $cols[] = $createdAtCol;
        $vals[] = 'NOW()';
    }

    $sql = 'INSERT INTO notifications (' . implode(', ', $cols) . ') VALUES (' . implode(', ', $vals) . ')';
    try {
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            return false;
        }

        $bindParams = $params;
        bindStmtParams($stmt, $types, $bindParams);
        $ok = $stmt->execute();
        $stmt->close();
        return (bool) $ok;
    } catch (mysqli_sql_exception $e) {
        return false;
    }
}

function getUnreadNotificationCount(mysqli $conn, int $userId): int
{
    if ($userId <= 0) {
        return 0;
    }
    if (!ensureNotificationsTable($conn) || !tableHasColumn($conn, 'notifications', 'user_id')) {
        return 0;
    }

    $isReadCol = firstExistingColumn($conn, 'notifications', ['is_read', 'read', 'seen']);
    if ($isReadCol === null) {
        return 0;
    }

    try {
        $stmt = $conn->prepare('SELECT COUNT(*) AS c FROM notifications WHERE user_id = ? AND ' . $isReadCol . ' = 0');
        if (!$stmt) {
            return 0;
        }
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $res = $stmt->get_result();
        $count = 0;
        if ($res && $row = $res->fetch_assoc()) {
            $count = (int) ($row['c'] ?? 0);
        }
        $stmt->close();
        return $count;
    } catch (mysqli_sql_exception $e) {
        return 0;
    }
}

function getUnreadMessageCount(mysqli $conn, int $userId): int
{
    if ($userId <= 0) {
        return 0;
    }
    if (!ensureMessagesTable($conn) || !tableHasColumn($conn, 'messages', 'receiver_id')) {
        return 0;
    }

    $isReadCol = firstExistingColumn($conn, 'messages', ['is_read', 'read', 'seen']);
    if ($isReadCol === null) {
        return 0;
    }

    try {
        $stmt = $conn->prepare('SELECT COUNT(*) AS c FROM messages WHERE receiver_id = ? AND ' . $isReadCol . ' = 0');
        if (!$stmt) {
            return 0;
        }
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $res = $stmt->get_result();
        $count = 0;
        if ($res && $row = $res->fetch_assoc()) {
            $count = (int) ($row['c'] ?? 0);
        }
        $stmt->close();
        return $count;
    } catch (mysqli_sql_exception $e) {
        return 0;
    }
}
?>
