<?php
session_start();
require_once '../includes/db.php';
require_once '../includes/functions.php';

checkAdminAuth();

$db = new Database();
$conn = $db->getConnection();

// Handle status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['issue_id'], $_POST['status'])) {
    $issueId = $_POST['issue_id'];
    $status = $_POST['status'];
    
    $stmt = $conn->prepare("UPDATE issues SET status = ? WHERE id = ?");
    if ($stmt->execute([$status, $issueId])) {
        // Get issue details for notification
        $stmt = $conn->prepare("SELECT i.*, u.full_name FROM issues i JOIN users u ON i.user_id = u.id WHERE i.id = ?");
        $stmt->execute([$issueId]);
        $issue = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Log activity
        logActivity($conn, $_SESSION['user_id'], 'maintenance_update', "Updated maintenance request status to $status");
        
        // Create notification for resident
        $notificationTitle = "Maintenance Request Update";
        $notificationMessage = "Your maintenance request '{$issue['title']}' has been marked as " . str_replace('_', ' ', $status);
        $stmt = $conn->prepare("INSERT INTO notifications (title, message, user_id, created_at) VALUES (?, ?, ?, NOW())");
        $stmt->execute([$notificationTitle, $notificationMessage, $issue['user_id']]);
        
        header("Location: maintenance.php?success=1");
        exit;
    }
}

// Get all maintenance requests with user information
$stmt = $conn->prepare("
    SELECT i.*, u.full_name, u.username 
    FROM issues i 
    JOIN users u ON i.user_id = u.id 
    ORDER BY i.created_at DESC
");
$stmt->execute();
$maintenanceRequests = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get maintenance statistics
$stmt = $conn->prepare("
    SELECT 
        COUNT(*) as total_requests,
        COUNT(CASE WHEN status = 'open' THEN 1 END) as open_requests,
        COUNT(CASE WHEN status = 'in_progress' THEN 1 END) as in_progress_requests,
        COUNT(CASE WHEN status = 'resolved' THEN 1 END) as resolved_requests
    FROM issues
");
$stmt->execute();
$stats = $stmt->fetch(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Maintenance Requests - Admin Dashboard</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <div class="dashboard-container">
        <nav class="dashboard-nav">
            <h2>Admin Dashboard</h2>
            <ul>
                <li><a href="dashboard.php">Dashboard</a></li>
                <li><a href="residents.php">Residents</a></li>
                <li><a href="payments.php">Payments</a></li>
                <li><a href="maintenance.php" class="active">Maintenance</a></li>
                <li><a href="notifications.php">Notifications</a></li>
                <li><a href="../logout.php">Logout</a></li>
            </ul>
        </nav>
        
        <main class="dashboard-main">
            <h1>Maintenance Requests</h1>
            
            <?php if (isset($_GET['success'])): ?>
            <div class="alert success">Maintenance request updated successfully!</div>
            <?php endif; ?>
            
            <div class="stats-container">
                <div class="stat-card">
                    <h3>Total Requests</h3>
                    <p><?= $stats['total_requests'] ?></p>
                </div>
                <div class="stat-card">
                    <h3>Open Requests</h3>
                    <p><?= $stats['open_requests'] ?></p>
                </div>
                <div class="stat-card">
                    <h3>In Progress</h3>
                    <p><?= $stats['in_progress_requests'] ?></p>
                </div>
                <div class="stat-card">
                    <h3>Resolved</h3>
                    <p><?= $stats['resolved_requests'] ?></p>
                </div>
            </div>
            
            <div class="maintenance-list">
                <h2>All Maintenance Requests</h2>
                <table>
                    <thead>
                        <tr>
                            <th>Resident</th>
                            <th>Title</th>
                            <th>Description</th>
                            <th>Status</th>
                            <th>Date Submitted</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($maintenanceRequests as $request): ?>
                        <tr>
                            <td><?= htmlspecialchars($request['full_name']) ?></td>
                            <td><?= htmlspecialchars($request['title']) ?></td>
                            <td><?= htmlspecialchars($request['description']) ?></td>
                            <td>
                                <span class="status-badge <?= $request['status'] ?>">
                                    <?= ucfirst(str_replace('_', ' ', $request['status'])) ?>
                                </span>
                            </td>
                            <td><?= date('M d, Y', strtotime($request['created_at'])) ?></td>
                            <td>
                                <form method="POST" class="inline-form">
                                    <input type="hidden" name="issue_id" value="<?= $request['id'] ?>">
                                    <select name="status" onchange="this.form.submit()">
                                        <option value="open" <?= $request['status'] === 'open' ? 'selected' : '' ?>>Open</option>
                                        <option value="in_progress" <?= $request['status'] === 'in_progress' ? 'selected' : '' ?>>In Progress</option>
                                        <option value="resolved" <?= $request['status'] === 'resolved' ? 'selected' : '' ?>>Resolved</option>
                                    </select>
                                </form>
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