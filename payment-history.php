<?php
$page_title = "Payment History - Pulse Fitness Hub";
require_once 'includes/config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$error = '';
$success = '';

try {
    // Get payment history
    $stmt = $pdo->prepare("
        SELECT p.*, 
               b.booking_id,
               ts.start_time,
               ts.end_time,
               c.name as class_name,
               m.name as membership_name,
               m.duration as membership_duration
        FROM payments p
        LEFT JOIN bookings b ON p.booking_id = b.booking_id
        LEFT JOIN time_slots ts ON b.slot_id = ts.slot_id
        LEFT JOIN classes c ON ts.class_id = c.class_id
        LEFT JOIN user_memberships um ON p.user_id = um.user_id AND p.payment_type = 'membership'
        LEFT JOIN memberships m ON um.membership_id = m.membership_id
        WHERE p.user_id = ? 
        ORDER BY p.payment_date DESC 
        LIMIT 5
    ");
    $stmt->execute([$user_id]);
    $payments = $stmt->fetchAll();

} catch(PDOException $e) {
    $error = "Error: " . $e->getMessage();
}

require_once 'includes/header.php';
?>

<div class="container mt-4">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1 class="h3 mb-0">Payment History</h1>
                <a href="membership.php" class="btn btn-primary">
                    <i class="fas fa-plus-circle"></i> Make New Payment
                </a>
            </div>
            
            <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <?php if (empty($payments)): ?>
                <div class="card">
                    <div class="card-body text-center py-5">
                        <i class="fas fa-receipt text-muted" style="font-size: 3rem;"></i>
                        <h3 class="mt-3">No Payment History</h3>
                        <p class="text-muted">You haven't made any payments yet.</p>
                        <a href="membership.php" class="btn btn-primary mt-3">View Membership Plans</a>
                    </div>
                </div>
            <?php else: ?>
                <div class="card">
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Type</th>
                                        <th>Description</th>
                                        <th>Amount</th>
                                        <th>Status</th>
                                        <th>Transaction ID</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($payments as $payment): ?>
                                        <tr>
                                            <td><?php echo date('F j, Y', strtotime($payment['payment_date'])); ?></td>
                                            <td>
                                                <span class="badge bg-<?php 
                                                    echo $payment['payment_type'] === 'membership' ? 'primary' : 
                                                         ($payment['payment_type'] === 'class' ? 'info' : 'warning');
                                                ?>">
                                                    <?php echo $payment['payment_type'] === 'membership' ? $payment['membership_name'] : ($payment['payment_type'] === 'class' ? $payment['class_name'] : 'Personal Training'); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php echo $payment['payment_type'] === 'membership' ? $payment['membership_name'] : ($payment['payment_type'] === 'class' ? $payment['class_name'] : 'Personal Training'); ?>
                                            </td>
                                            <td>$<?php echo number_format($payment['amount'], 2); ?></td>
                                            <td>
                                                <span class="badge bg-<?php 
                                                    echo $payment['status'] === 'completed' ? 'success' : 
                                                         ($payment['status'] === 'pending' ? 'warning' : 'danger');
                                                ?>">
                                                    <?php echo ucfirst($payment['status']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php if ($payment['transaction_id']): ?>
                                                    <span class="text-muted"><?php echo $payment['transaction_id']; ?></span>
                                                <?php else: ?>
                                                    <span class="text-muted">N/A</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?> 