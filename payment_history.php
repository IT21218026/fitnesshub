<?php
$page_title = "Payment History - Pulse Fitness Hub";
require_once 'includes/config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$error_message = '';

try {
    // Get payment history
    $stmt = $conn->prepare("
        SELECT p.*, b.booking_id, c.name as class_name, m.name as membership_name
        FROM payments p
        LEFT JOIN bookings b ON p.booking_id = b.booking_id
        LEFT JOIN time_slots ts ON b.slot_id = ts.slot_id
        LEFT JOIN classes c ON ts.class_id = c.class_id
        LEFT JOIN user_memberships um ON p.payment_type = 'membership' AND p.user_id = um.user_id
        LEFT JOIN memberships m ON um.membership_id = m.membership_id
        WHERE p.user_id = ?
        ORDER BY p.payment_date DESC
    ");
    $stmt->execute([$user_id]);
    $payments = $stmt->fetchAll();

    // Calculate total spent
    $total_spent = 0;
    foreach ($payments as $payment) {
        if ($payment['status'] === 'completed') {
            $total_spent += $payment['amount'];
        }
    }

} catch(PDOException $e) {
    $error_message = "Error: " . $e->getMessage();
}

require_once 'includes/header.php';
?>

<div class="container mt-4">
    <div class="row">
        <div class="col-12">
            <div class="card mb-4">
                <div class="card-header">
                    <h2 class="card-title h4 mb-0">Payment History</h2>
                </div>
                <div class="card-body">
                    <?php if ($error_message): ?>
                        <div class="alert alert-danger"><?php echo $error_message; ?></div>
                    <?php endif; ?>

                    <div class="row mb-4">
                        <div class="col-md-6">
                            <div class="card bg-light">
                                <div class="card-body">
                                    <h3 class="h5">Total Spent</h3>
                                    <p class="display-6">$<?php echo number_format($total_spent, 2); ?></p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card bg-light">
                                <div class="card-body">
                                    <h3 class="h5">Total Transactions</h3>
                                    <p class="display-6"><?php echo count($payments); ?></p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <?php if ($payments): ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Type</th>
                                        <th>Description</th>
                                        <th>Amount</th>
                                        <th>Status</th>
                                        <th>Receipt</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($payments as $payment): ?>
                                        <tr>
                                            <td><?php echo date('M d, Y', strtotime($payment['payment_date'])); ?></td>
                                            <td>
                                                <?php if ($payment['payment_type'] === 'membership'): ?>
                                                    <span class="badge bg-primary">Membership</span>
                                                <?php else: ?>
                                                    <span class="badge bg-success">Class</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($payment['payment_type'] === 'membership'): ?>
                                                    <?php echo htmlspecialchars($payment['membership_name']); ?>
                                                <?php else: ?>
                                                    <?php echo htmlspecialchars($payment['class_name']); ?>
                                                <?php endif; ?>
                                            </td>
                                            <td>$<?php echo number_format($payment['amount'], 2); ?></td>
                                            <td>
                                                <?php if ($payment['status'] === 'completed'): ?>
                                                    <span class="badge bg-success">Completed</span>
                                                <?php else: ?>
                                                    <span class="badge bg-warning">Pending</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <button class="btn btn-sm btn-outline-primary" 
                                                        onclick="window.print()">
                                                    <i class="fas fa-print"></i> Print
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <p class="text-muted">No payment history available.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?> 