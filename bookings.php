<?php
$page_title = "My Bookings - Pulse Fitness Hub";
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
    // Get user's bookings
    $stmt = $pdo->prepare("
        SELECT b.*, 
               ts.start_time, ts.end_time, ts.status as slot_status,
               c.name as class_name, c.description, c.difficulty_level,
               CONCAT(u.first_name, ' ', u.last_name) as trainer_name,
               tp.hourly_rate, tp.rating
        FROM bookings b
        JOIN time_slots ts ON b.slot_id = ts.slot_id
        JOIN classes c ON ts.class_id = c.class_id
        JOIN trainer_profiles tp ON ts.trainer_id = tp.trainer_id
        JOIN users u ON tp.user_id = u.user_id
        WHERE b.user_id = ?
        ORDER BY ts.start_time DESC
    ");
    $stmt->execute([$user_id]);
    $bookings = $stmt->fetchAll();

    // Handle booking cancellation
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancel_booking'])) {
        $booking_id = $_POST['booking_id'];
        
        // Start transaction
        $pdo->beginTransaction();
        
        try {
            // Get booking details
            $stmt = $pdo->prepare("
                SELECT b.*, ts.start_time 
                FROM bookings b
                JOIN time_slots ts ON b.slot_id = ts.slot_id
                WHERE b.booking_id = ? AND b.user_id = ?
            ");
            $stmt->execute([$booking_id, $user_id]);
            $booking = $stmt->fetch();
            
            if (!$booking) {
                throw new Exception("Booking not found.");
            }
            
            // Check if cancellation is allowed (24 hours before class)
            $class_time = strtotime($booking['start_time']);
            $current_time = time();
            $hours_until_class = ($class_time - $current_time) / 3600;
            
            if ($hours_until_class < 24) {
                throw new Exception("Cancellation is only allowed 24 hours before the class.");
            }
            
            // Update booking status
            $stmt = $pdo->prepare("
                UPDATE bookings 
                SET status = 'cancelled' 
                WHERE booking_id = ? AND user_id = ?
            ");
            $stmt->execute([$booking_id, $user_id]);
            
            // Update slot status
            $stmt = $pdo->prepare("
                UPDATE time_slots 
                SET status = 'available' 
                WHERE slot_id = ?
            ");
            $stmt->execute([$booking['slot_id']]);
            
            // Create notification
            $stmt = $pdo->prepare("
                INSERT INTO notifications (user_id, title, message, type)
                VALUES (?, 'Booking Cancelled', ?, 'booking')
            ");
            $notification_message = "Your booking for {$booking['class_name']} has been cancelled.";
            $stmt->execute([$user_id, $notification_message]);
            
            $pdo->commit();
            $success = "Booking cancelled successfully!";
            
            // Refresh bookings
            $stmt = $pdo->prepare("
                SELECT b.*, 
                       ts.start_time, ts.end_time, ts.status as slot_status,
                       c.name as class_name, c.description, c.difficulty_level,
                       CONCAT(u.first_name, ' ', u.last_name) as trainer_name,
                       tp.hourly_rate, tp.rating
                FROM bookings b
                JOIN time_slots ts ON b.slot_id = ts.slot_id
                JOIN classes c ON ts.class_id = c.class_id
                JOIN trainer_profiles tp ON ts.trainer_id = tp.trainer_id
                JOIN users u ON tp.user_id = u.user_id
                WHERE b.user_id = ?
                ORDER BY ts.start_time DESC
            ");
            $stmt->execute([$user_id]);
            $bookings = $stmt->fetchAll();
            
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = $e->getMessage();
        }
    }
    
} catch(PDOException $e) {
    $error = "Error: " . $e->getMessage();
}

require_once 'includes/header.php';
?>

<div class="container mt-4">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1 class="h3 mb-0">My Bookings</h1>
                <a href="classes.php" class="btn btn-primary">
                    <i class="bi bi-plus-circle"></i> Book New Class
                </a>
            </div>
            
            <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo $success; ?></div>
            <?php endif; ?>
            
            <?php if (empty($bookings)): ?>
                <div class="card">
                    <div class="card-body text-center py-5">
                        <i class="bi bi-calendar-x text-muted" style="font-size: 3rem;"></i>
                        <h3 class="mt-3">No Bookings Found</h3>
                        <p class="text-muted">You haven't booked any classes yet.</p>
                        <a href="classes.php" class="btn btn-primary mt-3">Browse Classes</a>
                    </div>
                </div>
            <?php else: ?>
                <div class="row">
                    <?php foreach ($bookings as $booking): ?>
                        <div class="col-md-6 col-lg-4 mb-4">
                            <div class="card h-100">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-start mb-3">
                                        <h5 class="card-title mb-0"><?php echo htmlspecialchars($booking['class_name']); ?></h5>
                                        <span class="badge bg-<?php 
                                            echo $booking['status'] === 'confirmed' ? 'success' : 
                                                 ($booking['status'] === 'pending' ? 'warning' : 'danger');
                                        ?>">
                                            <?php echo ucfirst($booking['status']); ?>
                                        </span>
                                    </div>
                                    
                                    <p class="text-muted small mb-3"><?php echo htmlspecialchars($booking['description']); ?></p>
                                    
                                    <div class="mb-3">
                                        <div class="d-flex align-items-center mb-2">
                                            <i class="bi bi-calendar me-2"></i>
                                            <span><?php echo date('F j, Y', strtotime($booking['start_time'])); ?></span>
                                        </div>
                                        <div class="d-flex align-items-center mb-2">
                                            <i class="bi bi-clock me-2"></i>
                                            <span>
                                                <?php 
                                                    echo date('g:i A', strtotime($booking['start_time'])) . ' - ' . 
                                                         date('g:i A', strtotime($booking['end_time']));
                                                ?>
                                            </span>
                                        </div>
                                        <div class="d-flex align-items-center">
                                            <i class="bi bi-person me-2"></i>
                                            <span>
                                                <?php echo htmlspecialchars($booking['trainer_name']); ?>
                                                <?php if ($booking['rating']): ?>
                                                    <span class="ms-2">
                                                        <i class="bi bi-star-fill text-warning"></i>
                                                        <?php echo number_format($booking['rating'], 1); ?>
                                                    </span>
                                                <?php endif; ?>
                                            </span>
                                        </div>
                                    </div>
                                    
                                    <div class="d-flex justify-content-between align-items-center">
                                        <span class="badge bg-<?php 
                                            echo $booking['difficulty_level'] === 'beginner' ? 'success' : 
                                                 ($booking['difficulty_level'] === 'intermediate' ? 'warning' : 'danger');
                                        ?>">
                                            <?php echo ucfirst($booking['difficulty_level']); ?>
                                        </span>
                                        <span class="text-primary">
                                            $<?php echo number_format($booking['hourly_rate'], 2); ?>/hr
                                        </span>
                                    </div>
                                </div>
                                
                                <?php if ($booking['status'] === 'confirmed'): ?>
                                    <div class="card-footer bg-transparent">
                                        <form method="POST" action="" class="d-grid">
                                            <input type="hidden" name="booking_id" value="<?php echo $booking['booking_id']; ?>">
                                            <button type="submit" name="cancel_booking" class="btn btn-outline-danger" 
                                                    onclick="return confirm('Are you sure you want to cancel this booking?')">
                                                <i class="bi bi-x-circle"></i> Cancel Booking
                                            </button>
                                        </form>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?> 