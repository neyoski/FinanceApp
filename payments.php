<?php
session_start();
require_once '../includes/db.php';
require_once '../includes/functions.php';

checkResidentAuth();

$db = new Database();
$conn = $db->getConnection();
$userId = $_SESSION['user_id'];
$message = '';

// Handle payment submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['submit_payment'])) {
        $amount = $_POST['amount'] ?? '';
        $payment_date = $_POST['payment_date'] ?? '';
        $payment_method = $_POST['payment_method'] ?? '';
        $reference_number = $_POST['reference_number'] ?? '';
        $description = $_POST['description'] ?? '';
        
        if (empty($amount) || empty($payment_date) || empty($payment_method)) {
            $message = 'Error: Amount, payment date, and payment method are required.';
        } else {
            // Handle file upload
            $payment_proof = null;
            if (isset($_FILES['payment_proof']) && $_FILES['payment_proof']['error'] === UPLOAD_ERR_OK) {
                $file = $_FILES['payment_proof'];
                
                // Define upload directory
                $uploadDir = __DIR__ . '/../assets/uploads/';
                
                // Create directory if it doesn't exist
                if (!file_exists($uploadDir)) {
                    mkdir($uploadDir, 0777, true);
                }
                
                // Validate file type
                if (!in_array($file['type'], ALLOWED_FILE_TYPES)) {
                    $message = 'Error: Invalid file type. Please upload JPG, PNG or PDF files only.';
                } else if ($file['size'] > MAX_FILE_SIZE) {
                    $message = 'Error: File size too large. Maximum size is 5MB.';
                } else {
                    // Generate unique filename
                    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
                    $filename = uniqid() . '.' . $extension;
                    $uploadPath = $uploadDir . $filename;
                    
                    if (move_uploaded_file($file['tmp_name'], $uploadPath)) {
                        $payment_proof = $filename;
                    } else {
                        $message = 'Error: Failed to upload file.';
                    }
                }
            }
            
            if (empty($message)) {
                // Insert payment record
                $stmt = $conn->prepare("
                    INSERT INTO dues (user_id, amount, due_date, status, payment_proof) 
                    VALUES (?, ?, ?, 'pending', ?)
                ");
                
                if ($stmt->execute([
                    $userId, 
                    $amount, 
                    $payment_date, 
                    $payment_proof
                ])) {
                    logActivity($conn, $userId, 'payment_submitted', 
                        'Payment of ' . formatCurrency($amount) . ' submitted');
                    $message = 'Payment submitted successfully.';
                } else {
                    $message = 'Error: Failed to submit payment.';
                }
            }
        }
    }
}

// Get all payments for the user
$stmt = $conn->prepare("
    SELECT * FROM dues 
    WHERE user_id = ? 
    ORDER BY created_at DESC
");
$stmt->execute([$userId]);
$payments = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payments - Estate Finance Management</title>
    <link rel="stylesheet" href="../assets/css/resident-payments.css">
</head>
<body>
    <div class="dashboard-container">
        <nav class="dashboard-nav">
            <h2>Resident Dashboard</h2>
            <ul>
                <li><a href="dashboard.php">Dashboard</a></li>
                <li><a href="payments.php" class="active">Payments</a></li>
                <li><a href="notifications.php">Notifications</a></li>
                <li><a href="profile.php">Profile</a></li>
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
                <h3>Submit New Payment</h3>
                <form method="POST" enctype="multipart/form-data" class="payment-form">
                    <div class="form-group">
                        <label for="amount">Amount (₦)</label>
                        <input type="number" id="amount" name="amount" step="0.01" min="0" required>
                    </div>

                    <div class="form-group">
                        <label for="payment_date">Payment Date</label>
                        <input type="date" id="payment_date" name="payment_date" required>
                    </div>

                    <div class="form-group">
                        <label for="payment_method">Payment Method</label>
                        <select id="payment_method" name="payment_method" required>
                            <option value="">Select Payment Method</option>
                            <option value="bank_transfer">Bank Transfer</option>
                            <option value="cash">Cash</option>
                            <option value="cheque">Cheque</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="reference_number">Reference Number</label>
                        <input type="text" id="reference_number" name="reference_number">
                        <small>Transaction reference number or cheque number</small>
                    </div>

                    <div class="form-group">
                        <label for="description">Payment Description</label>
                        <textarea id="description" name="description" rows="3"></textarea>
                    </div>

                    <div class="form-group">
                        <label for="payment_proof">Payment Proof</label>
                        <input type="file" id="payment_proof" name="payment_proof" accept=".jpg,.jpeg,.png,.pdf" required>
                        <small>Upload receipt or proof of payment (JPG, PNG, or PDF, max 5MB)</small>
                    </div>

                    <button type="submit" name="submit_payment" class="btn btn-primary">Submit Payment</button>
                </form>
            </div>

            <div class="dashboard-section">
                <h3>Payment History</h3>
                <div class="payment-list">
                    <?php if (empty($payments)): ?>
                        <p>No payments found.</p>
                    <?php else: ?>
                        <?php foreach ($payments as $payment): ?>
                            <div class="payment-item">
                                <div class="payment-info">
                                    <h4><?php echo formatCurrency($payment['amount']); ?></h4>
                                    <p>
                                        <strong>Date:</strong> <?php echo date('M j, Y', strtotime($payment['due_date'])); ?><br>
                                        <strong>Method:</strong> <?php echo ucfirst(str_replace('_', ' ', $payment['payment_method'])); ?><br>
                                        <?php if ($payment['reference_number']): ?>
                                            <strong>Reference:</strong> <?php echo htmlspecialchars($payment['reference_number']); ?><br>
                                        <?php endif; ?>
                                        <strong>Status:</strong> 
                                        <span class="status status-<?php echo $payment['status']; ?>">
                                            <?php echo ucfirst($payment['status']); ?>
                                        </span>
                                    </p>
                                    <?php if ($payment['description']): ?>
                                        <p class="payment-description">
                                            <?php echo htmlspecialchars($payment['description']); ?>
                                        </p>
                                    <?php endif; ?>
                                </div>
                                <?php if ($payment['payment_proof']): ?>
                                    <div class="payment-proof">
                                        <p>Payment proof uploaded</p>
                                        <small>Status: <?php echo $payment['admin_approval'] ? 'Approved' : 'Pending Approval'; ?></small>
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