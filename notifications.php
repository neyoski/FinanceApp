<?php
session_start();
require_once '../includes/db.php';
require_once '../includes/functions.php';

checkAdminAuth();

$db = new Database();
$conn = $db->getConnection();

$message = '';

// Handle notification creation
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = $_POST['title'] ?? '';
    $content = $_POST['content'] ?? '';
    
    if (empty($title) || empty($content)) {
        $message = 'Error: Title and content are required.';
    } else {
        $stmt = $conn->prepare("
            INSERT INTO notifications (title, message, created_by) 
            VALUES (?, ?, ?)
        ");
        
        if ($stmt->execute([$title, $content, $_SESSION['user_id']])) {
            logActivity($conn, $_SESSION['user_id'], 'notification_created', 'Created new notification: ' . $title);
            $message = 'Notification created successfully.';
        } else {
            $message = 'Error: Failed to create notification.';
        }
    }
}

// Get all notifications
$notifications = getNotifications($conn);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifications - Estate Finance Management</title>
    <link rel="stylesheet" href="../assets/css/admin-notifications.css">
</head>
<body>
    <div class="dashboard-container">
        <nav class="dashboard-nav">
            <h2>Admin Dashboard</h2>
            <ul>
                <li><a href="dashboard.php">Dashboard</a></li>
                <li><a href="residents.php">Residents</a></li>
                <li><a href="payments.php">Payments</a></li>
                <li><a href="notifications.php" class="active">Notifications</a></li>
                <li><a href="../logout.php">Logout</a></li>
            </ul>
        </nav>
        
        <main class="dashboard-main">
            <?php if ($message): ?>
                <div class="alert <?php echo strpos($message, 'Error:') === 0 ? 'alert-danger' : 'alert-success'; ?>">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <div class="dashboard-section">
                <h3>Create New Notification</h3>
                <form method="POST" class="notification-form">
                    <div class="form-group">
                        <label for="title">Title</label>
                        <input type="text" id="title" name="title" required>
                    </div>
                    <div class="form-group">
                        <label for="content">Content</label>
                        <textarea id="content" name="content" required></textarea>
                    </div>
                    <button type="submit" class="btn btn-primary">Create Notification</button>
                </form>
            </div>

            <div class="dashboard-section">
                <h3>All Notifications</h3>
                <div class="notification-list">
                    <?php if (empty($notifications)): ?>
                        <p>No notifications found.</p>
                    <?php else: ?>
                        <?php foreach ($notifications as $notification): ?>
                            <div class="notification-item">
                                <h4><?php echo htmlspecialchars($notification['title']); ?></h4>
                                <p><?php echo htmlspecialchars($notification['message']); ?></p>
                                <small><?php echo date('M j, Y H:i', strtotime($notification['created_at'])); ?></small>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>
</body>
</html>