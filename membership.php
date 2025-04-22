<?php
$page_title = "Membership - Pulse Fitness Hub";
require_once 'includes/config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$success_message = '';
$error_message = '';

try {
    // Get user's active membership
    $stmt = $pdo->prepare("
        SELECT m.*, um.start_date, um.end_date, um.status
        FROM user_memberships um
        JOIN memberships m ON um.membership_id = m.membership_id
        WHERE um.user_id = ? AND um.status = 'active' AND um.end_date >= CURDATE()
        ORDER BY um.end_date DESC
        LIMIT 1
    ");
    $stmt->execute([$user_id]);
    $active_membership = $stmt->fetch();

    // Get user's membership history
    $stmt = $pdo->prepare("
        SELECT m.*, um.start_date, um.end_date, um.status
        FROM user_memberships um
        JOIN memberships m ON um.membership_id = m.membership_id
        WHERE um.user_id = ?
        ORDER BY um.end_date DESC
    ");
    $stmt->execute([$user_id]);
    $membership_history = $stmt->fetchAll();

    // Get available memberships for upgrade
    $stmt = $pdo->query("
        SELECT * FROM memberships 
        ORDER BY price ASC
    ");
    $available_memberships = $stmt->fetchAll();

} catch(PDOException $e) {
    $error_message = "Error: " . $e->getMessage();
}

require_once 'includes/header.php';
?>

<div class="container mt-4">
    <div class="row">
        <!-- Current Membership -->
        <div class="col-lg-8">
            <div class="card mb-4">
                <div class="card-header">
                    <h2 class="card-title h4 mb-0">Current Membership</h2>
                </div>
                <div class="card-body">
                    <?php if ($error_message): ?>
                        <div class="alert alert-danger"><?php echo $error_message; ?></div>
                    <?php endif; ?>

                    <?php if ($active_membership): ?>
                        <div class="row">
                            <div class="col-md-6">
                                <h3 class="h5"><?php echo htmlspecialchars($active_membership['name']); ?></h3>
                                <p class="mb-1">Status: <span class="badge bg-success">Active</span></p>
                                <p class="mb-1">Start Date: <?php echo date('M d, Y', strtotime($active_membership['start_date'])); ?></p>
                                <p class="mb-1">End Date: <?php echo date('M d, Y', strtotime($active_membership['end_date'])); ?></p>
                                <p class="mb-1">Price: $<?php echo number_format($active_membership['price']); ?></p>
                            </div>
                            <div class="col-md-6">
                                <h3 class="h5">Benefits</h3>
                                <ul class="list-unstyled">
                                    <li><i class="fas fa-check text-success"></i> Access to all classes</li>
                                    <li><i class="fas fa-check text-success"></i> Personal trainer consultation</li>
                                    <li><i class="fas fa-check text-success"></i> Locker access</li>
                                    <li><i class="fas fa-check text-success"></i> Free towel service</li>
                                </ul>
                            </div>
                        </div>

                        <div class="mt-4">
                            <a href="payment.php" class="btn btn-primary">Renew Membership</a>
                            <a href="payment_history.php" class="btn btn-outline-secondary">View Payment History</a>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-warning">
                            <h3 class="h5">No Active Membership</h3>
                            <p>You don't have an active membership. Purchase a membership to access all our facilities and classes.</p>
                            <a href="payment.php" class="btn btn-primary">Purchase Membership</a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Membership History -->
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title h4 mb-0">Membership History</h2>
                </div>
                <div class="card-body">
                    <?php if ($membership_history): ?>
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Membership</th>
                                        <th>Start Date</th>
                                        <th>End Date</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($membership_history as $membership): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($membership['name']); ?></td>
                                            <td><?php echo date('M d, Y', strtotime($membership['start_date'])); ?></td>
                                            <td><?php echo date('M d, Y', strtotime($membership['end_date'])); ?></td>
                                            <td>
                                                <?php if ($membership['status'] === 'active'): ?>
                                                    <span class="badge bg-success">Active</span>
                                                <?php else: ?>
                                                    <span class="badge bg-secondary">Expired</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <p class="text-muted">No membership history available.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Available Memberships -->
        <div class="col-lg-4">
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title h4 mb-0">Available Memberships</h2>
                </div>
                <div class="card-body">
                    <?php foreach ($available_memberships as $membership): ?>
                        <div class="card mb-3">
                            <div class="card-body">
                                <h3 class="h5"><?php echo htmlspecialchars($membership['name']); ?></h3>
                                <p class="display-6">$<?php echo number_format($membership['price']); ?></p>
                                <p class="mb-2">Duration: <?php echo $membership['duration']; ?> months</p>
                                <ul class="list-unstyled">
                                    <li><i class="fas fa-check text-success"></i> Access to all classes</li>
                                    <li><i class="fas fa-check text-success"></i> Personal trainer consultation</li>
                                    <li><i class="fas fa-check text-success"></i> Locker access</li>
                                    <li><i class="fas fa-check text-success"></i> Free towel service</li>
                                </ul>
                                <a href="payment.php?membership_id=<?php echo $membership['membership_id']; ?>" 
                                   class="btn btn-primary w-100">
                                    Purchase Now
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?> 