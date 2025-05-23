<?php
session_start();
require_once '../includes/db.php';
require_once '../includes/functions.php';

checkResidentAuth();

$db = new Database();
$conn = $db->getConnection();
$userId = $_SESSION['user_id'];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = htmlspecialchars($_POST['title']);
    $description = htmlspecialchars($_POST['description']);
    
    $stmt = $conn->prepare("INSERT INTO issues (user_id, title, description) VALUES (?, ?, ?)");
    if ($stmt->execute([$userId, $title, $description])) {
        $_SESSION['success'] = 'Maintenance request submitted successfully!';
    } else {
        $_SESSION['error'] = 'Failed to submit maintenance request.';
    }
    header('Location: maintenance.php');
    exit;
}

// Get user's maintenance requests
$stmt = $conn->prepare("SELECT * FROM issues WHERE user_id = ? ORDER BY created_at DESC");
$stmt->execute([$userId]);
$issues = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Maintenance Requests - Estate Finance Management</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <div class="dashboard-container">
        <nav class="dashboard-nav">
            <h2>Resident Dashboard</h2>
            <ul>
                <li><a href="dashboard.php">Dashboard</a></li>
                <li><a href="payments.php">Payments</a></li>
                <li><a href="maintenance.php" class="active">Maintenance</a></li>
                <li><a href="notifications.php">Notifications</a></li>
                <li><a href="profile.php">Profile</a></li>
                <li><a href="../logout.php">Logout</a></li>
            </ul>
        </nav>
        
        <main class="dashboard-content">
            <h1>Maintenance Requests</h1>
            
            <?php if (isset($_SESSION['success'])): ?>
                <div class="alert success">
                    <?php 
                    echo $_SESSION['success'];
                    unset($_SESSION['success']);
                    ?>
                </div>
            <?php endif; ?>
            
            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert error">
                    <?php 
                    echo $_SESSION['error'];
                    unset($_SESSION['error']);
                    ?>
                </div>
            <?php endif; ?>
            
            <div class="maintenance-form">
                <h2>Submit New Request</h2>
                <form action="maintenance.php" method="POST">
                    <div class="form-group">
                        <label for="title">Title:</label>
                        <input type="text" id="title" name="title" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="description">Description:</label>
                        <textarea id="description" name="description" rows="4" required></textarea>
                    </div>
                    
                    <button type="submit" class="btn">Submit Request</button>
                </form>
            </div>
            
            <div class="maintenance-list">
                <h2>Your Requests</h2>
                <?php if ($issues): ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Title</th>
                                <th>Description</th>
                                <th>Status</th>
                                <th>Created At</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($issues as $issue): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($issue['title']); ?></td>
                                    <td><?php echo htmlspecialchars($issue['description']); ?></td>
                                    <td>
                                        <span class="status-badge <?php echo $issue['status']; ?>">
                                            <?php echo ucfirst(str_replace('_', ' ', $issue['status'])); ?>
                                        </span>
                                    </td>
                                    <td><?php echo date('M d, Y H:i', strtotime($issue['created_at'])); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p>No maintenance requests found.</p>
                <?php endif; ?>
            </div>
        </main>
    </div>
</body>
</html>