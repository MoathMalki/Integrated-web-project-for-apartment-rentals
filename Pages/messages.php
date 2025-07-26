<?php
// Messages page - View notifications and communications
requireLogin();

$user_id = $_SESSION['user_id'];

// Handle mark as read
if (isset($_POST['mark_read']) && isset($_POST['message_id'])) {
    markMessageAsRead($pdo, $_POST['message_id'], $user_id);
    header('Location: main.php?page=messages');
    exit;
}

// Handle mark all as read
if (isset($_POST['mark_all_read'])) {
    $mark_all_sql = "UPDATE messages SET is_read = 1 WHERE user_id = :user_id";
    executeQuery($pdo, $mark_all_sql, [':user_id' => $user_id]);
    header('Location: main.php?page=messages');
    exit;
}

// Get sorting preferences
$current_sort = isset($_GET['sort']) ? $_GET['sort'] : 'created_date';
$current_order = isset($_GET['order']) ? $_GET['order'] : 'DESC';

// Handle sorting
if (isset($_GET['sort'])) {
    $new_order = ($current_sort === $_GET['sort'] && $current_order === 'ASC') ? 'DESC' : 'ASC';
    
    $url_params = $_GET;
    $url_params['sort'] = $_GET['sort'];
    $url_params['order'] = $new_order;
    unset($url_params['page']);
    
    header('Location: main.php?page=messages&' . http_build_query($url_params));
    exit;
}

// Get filter
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';

// Build messages query
$messages_sql = "SELECT m.*, u.name as sender_name 
                 FROM messages m 
                 LEFT JOIN users u ON m.sender_id = u.user_id 
                 WHERE m.user_id = :user_id";

$params = [':user_id' => $user_id];

// Apply filter
switch ($filter) {
    case 'unread':
        $messages_sql .= " AND m.is_read = 0";
        break;
    case 'read':
        $messages_sql .= " AND m.is_read = 1";
        break;
    case 'system':
        $messages_sql .= " AND m.sender_type = 'system'";
        break;
}

$messages_sql .= " ORDER BY m.$current_sort $current_order";

$messages = executeQuery($pdo, $messages_sql, $params)->fetchAll();

// Get message statistics
$stats_sql = "SELECT 
                COUNT(*) as total_messages,
                COUNT(CASE WHEN is_read = 0 THEN 1 END) as unread_messages,
                COUNT(CASE WHEN is_read = 1 THEN 1 END) as read_messages,
                COUNT(CASE WHEN sender_type = 'system' THEN 1 END) as system_messages
              FROM messages 
              WHERE user_id = :user_id";

$stats = executeQuery($pdo, $stats_sql, [':user_id' => $user_id])->fetch();

$open_id = isset($_GET['open']) ? $_GET['open'] : null;
?>

<div class="messages-page"> 
    <div class="summary-card">
        <h1 class="summary-title">💬 Messages & Notifications</h1>
        <p class="summary-desc">Stay updated with important communications and system notifications</p>
        <div class="stats-row">
            <div class="stat-card total">
                <span class="stat-icon">📧</span>
                <div class="stat-value"><?php echo $stats['total_messages']; ?></div>
                <div class="stat-label">Total Messages</div>
            </div>
            <div class="stat-card unread">
                <span class="stat-icon">🔔</span>
                <div class="stat-value"><?php echo $stats['unread_messages']; ?></div>
                <div class="stat-label">Unread Messages</div>
            </div>
            <div class="stat-card read">
                <span class="stat-icon">✅</span>
                <div class="stat-value"><?php echo $stats['read_messages']; ?></div>
                <div class="stat-label">Read Messages</div>
            </div>
            <div class="stat-card system">
                <span class="stat-icon">⚙️</span>
                <div class="stat-value"><?php echo $stats['system_messages']; ?></div>
                <div class="stat-label">System Messages</div>
            </div>
        </div>
    </div>

    <!-- Message Filters and Actions -->
    <div class="message-controls">
        <div class="filter-buttons">
            <a href="?filter=all" class="filter-btn <?php echo $filter === 'all' ? 'active' : ''; ?>">
                📧 All Messages (<?php echo $stats['total_messages']; ?>)
            </a>
            <a href="?filter=unread" class="filter-btn <?php echo $filter === 'unread' ? 'active' : ''; ?>">
                🔔 Unread (<?php echo $stats['unread_messages']; ?>)
            </a>
            <a href="?filter=read" class="filter-btn <?php echo $filter === 'read' ? 'active' : ''; ?>">
                ✅ Read (<?php echo $stats['read_messages']; ?>)
            </a>
            <a href="?filter=system" class="filter-btn <?php echo $filter === 'system' ? 'active' : ''; ?>">
                ⚙️ System (<?php echo $stats['system_messages']; ?>)
            </a>
        </div>

        <?php if ($stats['unread_messages'] > 0): ?>
            <div class="message-actions">
                <form method="POST" style="display: inline;">
                    <button type="submit" name="mark_all_read" class="btn btn-secondary">
                        ✅ Mark All as Read
                    </button>
                </form>
            </div>
        <?php endif; ?>
    </div>

    <?php if (empty($messages)): ?>
        <div class="no-messages">
            <div class="empty-state">
                <?php if ($filter === 'unread'): ?>
                    <h2>🎉 All Caught Up!</h2>
                    <p>You have no unread messages. Great job staying on top of your communications!</p>
                <?php elseif ($filter === 'system'): ?>
                    <h2>⚙️ No System Messages</h2>
                    <p>You haven't received any system notifications yet.</p>
                <?php else: ?>
                    <h2>📭 No Messages</h2>
                    <p>You haven't received any messages yet. Messages will appear here when you:</p>
                    <ul>
                        <li>🏠 Rent or list a flat</li>
                        <li>📅 Book viewing appointments</li>
                        <li>✉️ Receive system notifications</li>
                        <li>💬 Get updates from owners or customers</li>
                    </ul>
                <?php endif; ?>
                
                <div class="empty-actions">
                    <?php if ($_SESSION['user_type'] === 'customer'): ?>
                        <a href="main.php?page=search" class="btn btn-primary">🔍 Search Flats</a>
                    <?php elseif ($_SESSION['user_type'] === 'owner'): ?>
                        <a href="main.php?page=offer-flat" class="btn btn-primary">➕ List a Flat</a>
                    <?php endif; ?>
                    <a href="main.php" class="btn btn-secondary">🏠 Go Home</a>
                </div>
            </div>
        </div>
    <?php else: ?>

        <!-- Messages Table -->
        <div class="messages-section">
            <div class="section-header">
                <h2><?php 
                    switch($filter) {
                        case 'unread': echo '🔔 Unread Messages'; break;
                        case 'read': echo '✅ Read Messages'; break;
                        case 'system': echo '⚙️ System Messages'; break;
                        default: echo '📧 All Messages'; break;
                    }
                ?></h2>
                <p>Showing <?php echo count($messages); ?> message<?php echo count($messages) !== 1 ? 's' : ''; ?></p>
            </div>

            <div class="table-container">
                <table class="messages-table">
                    <thead>
                        <tr>
                            <th>Status</th>
                            <th>
                                <a href="?sort=title&filter=<?php echo $filter; ?>" class="sort-link">
                                    Title
                                    <?php if ($current_sort === 'title'): ?>
                                        <span class="sort-icon"><?php echo $current_order === 'ASC' ? '▲' : '▼'; ?></span>
                                    <?php endif; ?>
                                </a>
                            </th>
                            <th>
                                <a href="?sort=sender_type&filter=<?php echo $filter; ?>" class="sort-link">
                                    From
                                    <?php if ($current_sort === 'sender_type'): ?>
                                        <span class="sort-icon"><?php echo $current_order === 'ASC' ? '▲' : '▼'; ?></span>
                                    <?php endif; ?>
                                </a>
                            </th>
                            <th>
                                <a href="?sort=created_date&filter=<?php echo $filter; ?>" class="sort-link">
                                    Date & Time
                                    <?php if ($current_sort === 'created_date'): ?>
                                        <span class="sort-icon"><?php echo $current_order === 'ASC' ? '▲' : '▼'; ?></span>
                                    <?php endif; ?>
                                </a>
                            </th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($messages as $message): ?>
                            <tr>
                                <td>
                                    <?php
                                    if (isset($message['sender_type']) && $message['sender_type'] === 'system') {
                                        echo 'System';
                                    } elseif (isset($message['is_read']) && $message['is_read']) {
                                        echo 'Read';
                                    } else {
                                        echo 'Unread';
                                    }
                                    ?>
                                </td>
                                <td>
                                    <a href="main.php?page=messages&open=<?php echo $message['message_id']; ?>">
                                        <?php echo htmlspecialchars($message['title']); ?>
                                    </a>
                                </td>
                                <td><?php echo htmlspecialchars($message['sender_name']); ?></td>
                                <td><?php echo formatDateTime($message['created_date']); ?></td>
                                <td><!-- actions --></td>
                            </tr>
                            <tr id="content-<?php echo $message['message_id']; ?>" class="message-content" style="display:<?php echo ($open_id == $message['message_id']) ? 'table-row' : 'none'; ?>;">
                                <td colspan="5">
                                    <?php echo nl2br(htmlspecialchars($message['message'])); ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

    <?php endif; ?>

    <!-- Message Tips -->
    <div class="message-tips">
        <h3>💡 Message Center Tips</h3>
        <div class="tips-grid">
            <div class="tip-card">
                <h4>🔔 Stay Notified</h4>
                <p>Check your messages regularly for important updates about your rentals, appointments, and system notifications.</p>
            </div>
            <div class="tip-card">
                <h4>📱 Quick Actions</h4>
                <p>Click on any message row to expand and read the full content. Use the Mark as Read button to organize your inbox.</p>
            </div>
            <div class="tip-card">
                <h4>🗂️ Filter Messages</h4>
                <p>Use the filter buttons above to quickly find unread messages, system notifications, or messages from specific users.</p>
            </div>
            <div class="tip-card">
                <h4>📊 Sort Options</h4>
                <p>Click on column headers to sort messages by title, sender, or date to better organize your communications.</p>
            </div>
        </div>
    </div>
</div>