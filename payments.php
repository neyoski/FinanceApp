<?php
session_start();
require_once '../includes/db.php';
require_once '../includes/functions.php';

checkAdminAuth();

$db = new Database();
$conn = $db->getConnection();
$message = '';

// Handle payment approval/rejection
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $payment_id = $_POST['payment_id'] ?? '';
    $action = $_POST['action'] ?? '';
    $admin_notes = $_POST['admin_notes'] ?? '';
    
    if (empty($payment_id) || empty($action)) {
        $message = 'Error: Invalid request.';
    } else {
        // Get payment details
        $stmt = $conn->prepare("SELECT user_id, amount FROM dues WHERE id = ?");
        $stmt->execute([$payment_id]);
        $payment = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($payment) {
            $new_status = $action === 'approve' ? 'paid' : 'rejected';
            $stmt = $conn->prepare("
                UPDATE dues 
                SET status = ?, 
                    admin_approval = ?
                WHERE id = ?
            ");
            
            if ($stmt->execute([
                $new_status,
                $action === 'approve' ? 1 : 0,
                $payment_id
            ])) {
                // Log the activity
                $activity = $action === 'approve' ? 'approved' : 'rejected';
                logActivity($conn, $_SESSION['user_id'], 'payment_' . $activity, 
                    'Payment #' . $payment_id . ' ' . $activity . ($admin_notes ? " - Note: $admin_notes" : ''));
                
                // Create notification for resident
                $title = 'Payment ' . ucfirst($activity);
                $notif_message = 'Your payment of ' . formatCurrency($payment['amount']) . 
                    ' has been ' . $activity;
                if ($admin_notes) {
                    $notif_message .= ". Admin notes: " . $admin_notes;
                }
                
                $stmt = $conn->prepare("
                    INSERT INTO notifications (title, message, created_by) 
                    VALUES (?, ?, ?)
                ");
                $stmt->execute([$title, $notif_message, $_SESSION['user_id']]);
                
                $message = 'Payment has been ' . $activity . ' successfully.';
            } else {
                $message = 'Error: Failed to update payment status.';
            }
        } else {
            $message = 'Error: Payment not found.';
        }
    }
}

// Get all payments with user information
$stmt = $conn->prepare("
    SELECT d.*, u.username, u.full_name, u.email
    FROM dues d
    JOIN users u ON d.user_id = u.id
    ORDER BY d.created_at DESC
");
$stmt->execute();
$payments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get payment statistics
$stmt = $conn->prepare("
    SELECT 
        COUNT(*) as total_payments,
        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_count,
        SUM(CASE WHEN status = 'paid' THEN 1 ELSE 0 END) as approved_count,
        SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected_count,
        SUM(CASE WHEN status = 'paid' THEN amount ELSE 0 END) as total_approved
    FROM dues
");
$stmt->execute();
$stats = $stmt->fetch(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Management - Estate Finance Management</title>
    <link rel="stylesheet" href="../assets/css/admin-payments.css">
    
</head>
<body>
    <div class="dashboard-container">
        <nav class="dashboard-nav">
            <h2>Admin Dashboard</h2>
            <ul>
                <li><a href="dashboard.php">Dashboard</a></li>
                <li><a href="residents.php">Residents</a></li>
                <li><a href="payments.php" class="active">Payments</a></li>
                <li><a href="notifications.php">Notifications</a></li>
                <li><a href="../logout.php">Logout</a></li>
            </ul>
        </nav>
        
        <main class="dashboard-main">
            <?php if ($message): ?>
                <div class="alert <?php echo strpos($message, 'Error:') === 0 ? 'alert-danger' : 'alert-success'; ?>">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <div class="dashboard-stats payment-stats">
                <div class="stat-card">
                    <h3>Total Payments</h3>
                    <p><?php echo $stats['total_payments']; ?></p>
                </div>
                <div class="stat-card">
                    <h3>Pending Approval</h3>
                    <p><?php echo $stats['pending_count']; ?></p>
                </div>
                <div class="stat-card">
                    <h3>Approved Payments</h3>
                    <p><?php echo $stats['approved_count']; ?></p>
                </div>
                <div class="stat-card">
                    <h3>Total Amount Approved</h3>
                    <p><?php echo formatCurrency($stats['total_approved']); ?></p>
                </div>
            </div>

            <div class="dashboard-section">
                <h3>Payment Management</h3>
                <div class="payment-list admin-payment-list">
                    <?php if (empty($payments)): ?>
                        <p>No payments found.</p>
                    <?php else: ?>
                        <?php foreach ($payments as $payment): ?>
                            <div class="payment-item <?php echo $payment['status']; ?>">
                                <div class="payment-info">
                                    <div class="payment-header">
                                        <h4><?php echo formatCurrency($payment['amount']); ?></h4>
                                        <span class="status status-<?php echo $payment['status']; ?>">
                                            <?php echo ucfirst($payment['status']); ?>
                                        </span>
                                    </div>
                                    <div class="resident-info">
                                        <p>
                                            <strong>Resident:</strong> <?php echo htmlspecialchars($payment['full_name']); ?> 
                                            (<?php echo htmlspecialchars($payment['email']); ?>)
                                        </p>
                                    </div>
                                    <div class="payment-details">
                                        <p>
                                            <strong>Date:</strong> <?php echo date('M j, Y', strtotime($payment['due_date'])); ?><br>
                                            <strong>Method:</strong> <?php echo ucfirst(str_replace('_', ' ', $payment['payment_method'])); ?><br>
                                            <?php if ($payment['reference_number']): ?>
                                                <strong>Reference:</strong> <?php echo htmlspecialchars($payment['reference_number']); ?><br>
                                            <?php endif; ?>
                                            <strong>Submitted:</strong> <?php echo date('M j, Y H:i', strtotime($payment['created_at'])); ?>
                                        </p>
                                    </div>
                                    <?php if ($payment['description']): ?>
                                        <div class="payment-description">
                                            <strong>Description:</strong><br>
                                            <?php echo htmlspecialchars($payment['description']); ?>
                                        </div>
                                    <?php endif; ?>
                                    <?php if ($payment['admin_notes']): ?>
                                        <div class="admin-notes">
                                            <strong>Admin Notes:</strong><br>
                                            <?php echo htmlspecialchars($payment['admin_notes']); ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                
                                <?php if ($payment['payment_proof']): ?>
                                    <div class="payment-proof">
                                        <p>Payment proof available</p>
                                        <a href="../assets/uploads/<?php echo htmlspecialchars($payment['payment_proof']); ?>" 
                                           target="_blank" class="btn btn-small">View Proof</a>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if ($payment['status'] === 'pending'): ?>
                                    <div class="payment-actions">
                                        <form method="POST" class="approval-form">
                                            <input type="hidden" name="payment_id" value="<?php echo $payment['id']; ?>">
                                            <div class="form-group">
                                                <label for="admin_notes_<?php echo $payment['id']; ?>">Notes</label>
                                                <textarea name="admin_notes" id="admin_notes_<?php echo $payment['id']; ?>" 
                                                    rows="2" placeholder="Add any notes about this payment"></textarea>
                                            </div>
                                            <div class="button-group">
                                                <button type="submit" name="action" value="approve" 
                                                    class="btn btn-success btn-small">Approve</button>
                                                <button type="submit" name="action" value="reject" 
                                                    class="btn btn-danger btn-small">Reject</button>
                                            </div>
                                        </form>
                                    </div>
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