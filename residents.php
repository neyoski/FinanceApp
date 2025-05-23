<?php
session_start();
require_once '../includes/db.php';
require_once '../includes/functions.php';

checkAdminAuth();

$db = new Database();
$conn = $db->getConnection();

// Get all residents
$stmt = $conn->query("
    SELECT u.*, 
           COUNT(DISTINCT CASE WHEN d.status = 'paid' THEN d.id END) as paid_payments,
           COUNT(DISTINCT CASE WHEN d.status = 'pending' THEN d.id END) as pending_payments,
           SUM(CASE WHEN d.status = 'paid' THEN d.amount ELSE 0 END) as total_paid
    FROM users u
    LEFT JOIN dues d ON u.id = d.user_id
    WHERE u.role = 'resident'
    GROUP BY u.id
    ORDER BY u.username
");
$residents = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Residents - Estate Finance Management</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="../assets/css/admin-residents.css">
</head>
<body>
    <div class="dashboard-container">
        <nav class="dashboard-nav">
            <h2>Admin Dashboard</h2>
            <ul>
                <li><a href="dashboard.php">Dashboard</a></li>
                <li><a href="residents.php" class="active">Residents</a></li>
                <li><a href="payments.php">Payments</a></li>
                <li><a href="notifications.php">Notifications</a></li>
                <li><a href="../logout.php">Logout</a></li>
            </ul>
        </nav>
        
        <main class="dashboard-main">
            <div class="section-header">
                <h2>Manage Residents</h2>
            </div>

            <div class="residents-list">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Username</th>
                            <th>Full Name</th>
                            <th>Email</th>
                            <th>Paid Payments</th>
                            <th>Pending Payments</th>
                            <th>Total Paid</th>
                            <th>Last Activity</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($residents as $resident): 
                            // Get last activity
                            $activityStmt = $conn->prepare("SELECT created_at, description FROM activity_logs WHERE user_id = ? ORDER BY created_at DESC LIMIT 1");
                            $activityStmt->execute([$resident['id']]);
                            $lastActivity = $activityStmt->fetch(PDO::FETCH_ASSOC);
                        ?>
                        <tr>
                            <td><?php echo htmlspecialchars($resident['username']); ?></td>
                            <td><?php echo htmlspecialchars($resident['full_name']); ?></td>
                            <td><?php echo htmlspecialchars($resident['email']); ?></td>
                            <td><?php echo $resident['paid_payments']; ?></td>
                            <td>
                                <span class="<?php echo $resident['pending_payments'] > 0 ? 'status-pending' : 'status-none'; ?>">
                                    <?php echo $resident['pending_payments']; ?>
                                </span>
                            </td>
                            <td><?php echo formatCurrency($resident['total_paid']); ?></td>
                            <td>
                                <?php if ($lastActivity): ?>
                                    <span title="<?php echo htmlspecialchars($lastActivity['description']); ?>">
                                        <?php echo date('M j, Y H:i', strtotime($lastActivity['created_at'])); ?>
                                    </span>
                                <?php else: ?>
                                    No activity
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </main>
    </div>
</body>
</html>