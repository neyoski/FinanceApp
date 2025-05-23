<?php
session_start();
require_once '../includes/db.php';
require_once '../includes/functions.php';

checkAdminAuth();

$db = new Database();
$conn = $db->getConnection();

// Get summary statistics
$stmt = $conn->query("SELECT COUNT(*) as total_residents FROM users WHERE role = 'resident'");
$totalResidents = $stmt->fetch(PDO::FETCH_ASSOC)['total_residents'];

$stmt = $conn->query("SELECT COUNT(*) as pending_payments FROM dues WHERE status = 'pending'");
$pendingPayments = $stmt->fetch(PDO::FETCH_ASSOC)['pending_payments'];

$stmt = $conn->query("SELECT SUM(amount) as total_paid FROM dues WHERE status = 'paid'");
$totalPaid = $stmt->fetch(PDO::FETCH_ASSOC)['total_paid'] ?? 0;

// Get recent activities
$stmt = $conn->query("
    SELECT al.*, u.username 
    FROM activity_logs al 
    JOIN users u ON al.user_id = u.id 
    ORDER BY al.created_at DESC 
    LIMIT 10
");
$recentActivities = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get notifications
$notifications = getNotifications($conn);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Estate Finance Management</title>
    <link rel="stylesheet" href="../assets/css/admin-dashboard.css">
</head>
<body>
    <div class="dashboard-container">
        <nav class="dashboard-nav">
            <h2>Admin Dashboard</h2>
            <ul>
                <li><a href="dashboard.php" class="active">Dashboard</a></li>
                <li><a href="residents.php">Residents</a></li>
                <li><a href="payments.php">Payments</a></li>
                <li><a href="notifications.php">Notifications</a></li>
                <li><a href="../logout.php">Logout</a></li>
            </ul>
        </nav>
        
        <main class="dashboard-main">
            <div class="dashboard-stats">
                <div class="stat-card">
                    <h3>Total Residents</h3>
                    <p><?php echo $totalResidents; ?></p>
                </div>
                <div class="stat-card">
                    <h3>Pending Payments</h3>
                    <p><?php echo $pendingPayments; ?></p>
                </div>
                <div class="stat-card">
                    <h3>Total Paid</h3>
                    <p><?php echo formatCurrency($totalPaid); ?></p>
                </div>
            </div>

            <div class="dashboard-section">
                <h3>Recent Activities</h3>
                <div class="activity-list">
                    <?php foreach ($recentActivities as $activity): ?>
                        <div class="activity-item">
                            <p>
                                <strong><?php echo htmlspecialchars($activity['username']); ?></strong>
                                <?php echo htmlspecialchars($activity['description']); ?>
                            </p>
                            <small><?php echo date('M j, Y H:i', strtotime($activity['created_at'])); ?></small>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="dashboard-section">
                <h3>Recent Notifications</h3>
                <div class="notification-list">
                    <?php foreach ($notifications as $notification): ?>
                        <div class="notification-item">
                            <h4><?php echo htmlspecialchars($notification['title']); ?></h4>
                            <p><?php echo htmlspecialchars($notification['message']); ?></p>
                            <small><?php echo date('M j, Y H:i', strtotime($notification['created_at'])); ?></small>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </main>
    </div>
</body>
</html>