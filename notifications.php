<?php
session_start();
require_once '../includes/db.php';
require_once '../includes/functions.php';

checkResidentAuth();

$db = new Database();
$conn = $db->getConnection();
$userId = $_SESSION['user_id'];

// Mark notification as read
if (isset($_POST['mark_read']) && isset($_POST['notification_id'])) {
    $notificationId = $_POST['notification_id'];
    
    // Check if already marked as read
    $stmt = $conn->prepare("SELECT id FROM notification_reads WHERE notification_id = ? AND user_id = ?");
    $stmt->execute([$notificationId, $userId]);
    
    if ($stmt->rowCount() === 0) {
        $stmt = $conn->prepare("INSERT INTO notification_reads (notification_id, user_id) VALUES (?, ?)");
        $stmt->execute([$notificationId, $userId]);
        
        logActivity($conn, $userId, 'notification_read', 'User read notification #' . $notificationId);
    }
}

// Get all notifications with read status
$stmt = $conn->prepare("
    SELECT 
        n.*, 
        CASE WHEN nr.id IS NOT NULL THEN 1 ELSE 0 END as is_read
    FROM notifications n
    LEFT JOIN notification_reads nr ON n.id = nr.notification_id AND nr.user_id = ?
    ORDER BY n.created_at DESC
");
$stmt->execute([$userId]);
$notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Count unread notifications
$unreadCount = array_reduce($notifications, function($carry, $item) {
    return $carry + ($item['is_read'] ? 0 : 1);
}, 0);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifications - Estate Finance Management</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/resident-notifications.css">
</head>
<body>
    <div class="dashboard-container">
        <nav class="dashboard-nav">
            <h2>Resident Dashboard</h2>
            <ul>
                <li><a href="dashboard.php">Dashboard</a></li>
                <li><a href="payments.php">Payments</a></li>
                <li><a href="notifications.php" class="active">Notifications</a></li>
                <li><a href="profile.php">Profile</a></li>
                <li><a href="../logout.php">Logout</a></li>
            </ul>
        </nav>
        
        <main class="dashboard-main">
            <div class="dashboard-header">
                <h3>Notifications</h3>
                <?php if ($unreadCount > 0): ?>
                    <span class="badge badge-warning"><?php echo $unreadCount; ?> unread</span>
                <?php endif; ?>
            </div>

            <div class="dashboard-section">
                <div class="notification-list">
                    <?php if (empty($notifications)): ?>
                        <p>No notifications found.</p>
                    <?php else: ?>
                        <?php foreach ($notifications as $notification): ?>
                            <div class="notification-item <?php echo $notification['is_read'] ? 'read' : 'unread'; ?>">
                                <div class="notification-content">
                                    <h4><?php echo htmlspecialchars($notification['title']); ?></h4>
                                    <p><?php echo htmlspecialchars($notification['message']); ?></p>
                                    <small><?php echo date('M j, Y H:i', strtotime($notification['created_at'])); ?></small>
                                </div>
                                <?php if (!$notification['is_read']): ?>
                                    <form method="POST" class="notification-action">
                                        <input type="hidden" name="notification_id" value="<?php echo $notification['id']; ?>">
                                        <button type="submit" name="mark_read" class="btn btn-small">Mark as Read</button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>
</body>
</html>