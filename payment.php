<?php
$page_title = "Make Payment - Pulse Fitness Hub";
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
    // Get available memberships
    $stmt = $pdo->query("SELECT * FROM memberships ORDER BY price ASC");
    $memberships = $stmt->fetchAll();

    // Get user's active membership if any
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

    // Get pending payments
    $stmt = $pdo->prepare("
        SELECT p.*, m.name as membership_name
        FROM payments p
        LEFT JOIN user_memberships um ON p.user_id = um.user_id
        LEFT JOIN memberships m ON um.membership_id = m.membership_id
        WHERE p.user_id = ? AND p.status = 'pending'
        ORDER BY p.payment_date DESC
    ");
    $stmt->execute([$user_id]);
    $pending_payments = $stmt->fetchAll();

    // Handle form submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $pdo->beginTransaction();

        try {
            if (isset($_POST['membership_id'])) {
                // Get membership details
                $stmt = $pdo->prepare("SELECT price, duration FROM memberships WHERE membership_id = ?");
                $stmt->execute([$_POST['membership_id']]);
                $membership = $stmt->fetch();

                if (!$membership) {
                    throw new Exception("Invalid membership selected.");
                }

                // Process membership payment
                $stmt = $pdo->prepare("
                    INSERT INTO payments 
                    (user_id, amount, payment_type, status, payment_date)
                    VALUES (?, ?, 'membership', 'completed', NOW())
                ");
                
                $stmt->execute([
                    $user_id,
                    $membership['price']  // Use the price from the membership record
                ]);

                $payment_id = $pdo->lastInsertId();

                // Create or update membership
                if ($active_membership) {
                    $stmt = $pdo->prepare("
                        UPDATE user_memberships 
                        SET status = 'expired' 
                        WHERE user_id = ? AND status = 'active'
                    ");
                    $stmt->execute([$user_id]);
                }

                $stmt = $pdo->prepare("
                    INSERT INTO user_memberships 
                    (user_id, membership_id, start_date, end_date, status)
                    VALUES (?, ?, CURDATE(), DATE_ADD(CURDATE(), INTERVAL ? MONTH), 'active')
                ");
                
                $stmt->execute([
                    $user_id,
                    $_POST['membership_id'],
                    $membership['duration']  // Use the duration from the membership record
                ]);

                $success_message = "Membership payment processed successfully!";
            } elseif (isset($_POST['booking_id'])) {
                // Process booking payment
                $stmt = $pdo->prepare("
                    UPDATE payments 
                    SET status = 'completed', payment_date = NOW()
                    WHERE payment_id = ?
                ");
                
                $stmt->execute([$_POST['payment_id']]);

                $success_message = "Booking payment processed successfully!";
            }

            $pdo->commit();
        } catch (Exception $e) {
            $pdo->rollBack();
            $error_message = "Error processing payment: " . $e->getMessage();
        }
    }

} catch(PDOException $e) {
    $error_message = "Error: " . $e->getMessage();
}

require_once 'includes/header.php';
?>

<div class="container py-5">
    <div class="row">
        <div class="col-12">
            <?php if (isset($error_message)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?php echo $error_message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>
            <?php if (isset($success_message)): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <?php echo $success_message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="row mb-4">
        <div class="col-lg-8">
            <div class="card shadow-sm">
                <div class="card-header bg-primary text-white">
                    <h5 class="card-title mb-0">Select Membership Plan</h5>
                </div>
                <div class="card-body">
                    <form action="" method="post" id="paymentForm" class="needs-validation" novalidate>
                        <div class="mb-4">
                            <label for="membership_id" class="form-label">Available Memberships</label>
                            <select name="membership_id" id="membership_id" class="form-select form-select-lg" required>
                                <option value="">Choose a membership plan...</option>
                                <?php foreach ($memberships as $membership): ?>
                                    <option value="<?php echo $membership['membership_id']; ?>" 
                                            data-price="<?php echo $membership['price']; ?>"
                                            data-duration="<?php echo $membership['duration']; ?>">
                                        <?php echo $membership['name']; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="invalid-feedback">Please select a membership plan.</div>
                        </div>

                        <div id="membershipDetails" class="card bg-light mb-4 p-3 d-none">
                            <div class="membership-info"></div>
                            <hr class="my-3">
                            <div class="membership-features">
                                <h6 class="text-muted mb-3">Membership Benefits:</h6>
                                <ul class="list-unstyled">
                                    <li><i class="fas fa-check text-success me-2"></i>Access to all gym facilities</li>
                                    <li><i class="fas fa-check text-success me-2"></i>Free fitness assessment</li>
                                    <li><i class="fas fa-check text-success me-2"></i>Group classes included</li>
                                    <li><i class="fas fa-check text-success me-2"></i>Personal locker</li>
                                </ul>
                            </div>
                        </div>

                        <input type="hidden" name="payment_amount" id="paymentAmount">
                        <input type="hidden" name="payment_duration" id="paymentDuration">
                        
                        <div class="d-grid">
                            <button type="submit" name="process_payment" class="btn btn-primary btn-lg">
                                <i class="fas fa-credit-card me-2"></i>Proceed to Payment
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card shadow-sm">
                <div class="card-header bg-info text-white">
                    <h5 class="card-title mb-0">Payment History</h5>
                </div>
                <div class="card-body">
                    <?php if (!empty($pending_payments)): ?>
                        <div class="list-group">
                            <?php foreach ($pending_payments as $payment): ?>
                                <div class="list-group-item">
                                    <div class="d-flex w-100 justify-content-between align-items-center">
                                        <div>
                                            <h6 class="mb-1"><?php echo $payment['membership_name']; ?></h6>
                                            <p class="mb-1 text-muted">Amount: $<?php echo number_format($payment['amount'], 2); ?></p>
                                            <small>Date: <?php echo date('M d, Y', strtotime($payment['payment_date'])); ?></small>
                                        </div>
                                        <span class="badge bg-warning text-dark">Pending</span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-4">
                            <i class="fas fa-receipt text-muted fa-3x mb-3"></i>
                            <p class="text-muted mb-0">No pending payments</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('paymentForm');
    const membershipSelect = document.getElementById('membership_id');
    const membershipDetails = document.getElementById('membershipDetails');
    const paymentAmount = document.getElementById('paymentAmount');
    const paymentDuration = document.getElementById('paymentDuration');

    // Form validation
    form.addEventListener('submit', function(event) {
        if (!form.checkValidity()) {
            event.preventDefault();
            event.stopPropagation();
        }
        form.classList.add('was-validated');
    });

    // Membership selection handler
    membershipSelect.addEventListener('change', function() {
        const selectedOption = this.options[this.selectedIndex];
        if (selectedOption.value) {
            const price = selectedOption.dataset.price;
            const duration = selectedOption.dataset.duration;
            
            membershipDetails.classList.remove('d-none');
            membershipDetails.querySelector('.membership-info').innerHTML = `
                <h5 class="text-primary mb-3">${selectedOption.text}</h5>
                <div class="row">
                    <div class="col-6">
                        <p class="mb-1"><strong>Price:</strong></p>
                        <h4 class="text-success mb-0">$${parseFloat(price).toFixed(2)}</h4>
                    </div>
                    <div class="col-6">
                        <p class="mb-1"><strong>Duration:</strong></p>
                        <h4 class="text-primary mb-0">${duration} months</h4>
                    </div>
                </div>
            `;

            paymentAmount.value = price;
            paymentDuration.value = duration;
        } else {
            membershipDetails.classList.add('d-none');
            paymentAmount.value = '';
            paymentDuration.value = '';
        }
    });

    // Initialize tooltips
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    const tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
});
</script>