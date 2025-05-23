<?php
session_start();
require_once '../includes/db.php';
require_once '../includes/functions.php';

checkResidentAuth();

$db = new Database();
$conn = $db->getConnection();

// Get user's payment summary
$userId = $_SESSION['user_id'];
$stmt = $conn->prepare("
    SELECT 
        SUM(CASE WHEN status = 'paid' THEN amount ELSE 0 END) as total_paid,
        SUM(CASE WHEN status = 'pending' THEN amount ELSE 0 END) as total_pending,
        COUNT(CASE WHEN status = 'overdue' THEN 1 END) as overdue_count
    FROM dues 
    WHERE user_id = ?
");
$stmt->execute([$userId]);
$paymentSummary = $stmt->fetch(PDO::FETCH_ASSOC);

// Get recent payments
$stmt = $conn->prepare("
    SELECT * FROM dues 
    WHERE user_id = ? 
    ORDER BY created_at DESC 
    LIMIT 5
");
$stmt->execute([$userId]);
$recentPayments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get notifications
$notifications = getNotifications($conn, $userId);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Resident Dashboard - Estate Finance Management</title>
    <link rel="stylesheet" href="../assets/css/resident-dashboard.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>
    <div class="dashboard-container">
        <nav class="dashboard-nav">
            <h2>Resident Dashboard</h2>
            <ul>
                <li><a href="dashboard.php" class="active">Dashboard</a></li>
                <li><a href="payments.php">Payments</a></li>
                <li><a href="notifications.php">Notifications</a></li>
                <li><a href="profile.php">Profile</a></li>
                <li><a href="../logout.php">Logout</a></li>
            </ul>
        </nav>
        
        <main class="dashboard-main">
            <div class="dashboard-stats">
                <div class="stat-card">
                    <h3>Total Paid</h3>
                    <p><?php echo formatCurrency($paymentSummary['total_paid'] ?? 0); ?></p>
                </div>
                <div class="stat-card">
                    <h3>Pending Payments</h3>
                    <p><?php echo formatCurrency($paymentSummary['total_pending'] ?? 0); ?></p>
                </div>
                <div class="stat-card">
                    <h3>Overdue Payments</h3>
                    <p><?php echo $paymentSummary['overdue_count'] ?? 0; ?></p>
                </div>
            </div>

            <div class="dashboard-section">
                <h3>Recent Payments</h3>
                <div class="payment-list">
                    <?php if (empty($recentPayments)): ?>
                        <p>No recent payments found.</p>
                    <?php else: ?>
                        <?php foreach ($recentPayments as $payment): ?>
                            <div class="payment-item">
                                <div class="payment-info">
                                    <h4><?php echo formatCurrency($payment['amount']); ?></h4>
                                    <p>Due Date: <?php echo date('M j, Y', strtotime($payment['due_date'])); ?></p>
                                    <span class="status status-<?php echo $payment['status']; ?>">
                                        <?php echo ucfirst($payment['status']); ?>
                                    </span>
                                </div>
                                <small><?php echo date('M j, Y H:i', strtotime($payment['created_at'])); ?></small>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <div class="dashboard-section">
                <h3>Notifications</h3>
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