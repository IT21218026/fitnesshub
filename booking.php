<?php
$page_title = "Book Class - Pulse Fitness Hub";
require_once 'includes/config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Check if slot_id is provided
if (!isset($_GET['slot_id'])) {
    header("Location: classes.php");
    exit();
}

$slot_id = $_GET['slot_id'];
$user_id = $_SESSION['user_id'];
$error = '';
$success = '';

try {
    // Get slot details
    $stmt = $pdo->prepare("
        SELECT ts.*, c.name as class_name, c.description, c.difficulty_level,
               CONCAT(u.first_name, ' ', u.last_name) as trainer_name,
               tp.hourly_rate, tp.rating
        FROM time_slots ts
        JOIN classes c ON ts.class_id = c.class_id
        JOIN trainer_profiles tp ON ts.trainer_id = tp.trainer_id
        JOIN users u ON tp.user_id = u.user_id
        WHERE ts.slot_id = ? AND ts.status = 'available'
    ");
    $stmt->execute([$slot_id]);
    
    if ($stmt->rowCount() === 0) {
        header("Location: classes.php");
        exit();
    }

    $slot = $stmt->fetch();
    
    // Process booking
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Start transaction
        $pdo->beginTransaction();
        
        try {
            // Check if slot is still available
            $stmt = $pdo->prepare("SELECT status FROM time_slots WHERE slot_id = ? FOR UPDATE");
            $stmt->execute([$slot_id]);
            $current_status = $stmt->fetchColumn();
            
            if ($current_status !== 'available') {
                throw new Exception("This slot is no longer available.");
            }
            
            // Create booking
            $stmt = $pdo->prepare("
                INSERT INTO bookings (user_id, slot_id, status)
                VALUES (?, ?, 'pending')
            ");
            $stmt->execute([$user_id, $slot_id]);
            $booking_id = $pdo->lastInsertId();
            
            // Update slot status
            $stmt = $pdo->prepare("
                UPDATE time_slots 
                SET status = 'booked'
                WHERE slot_id = ?
            ");
            $stmt->execute([$slot_id]);
            
            // Create notification for user
            $stmt = $pdo->prepare("
                INSERT INTO notifications (user_id, title, message, type)
                VALUES (?, 'Class Booked Successfully', ?, 'booking')
            ");
            $notification_message = "You have successfully booked {$slot['class_name']} with {$slot['trainer_name']} on " . 
                                  date('F j, Y', strtotime($slot['start_time'])) . " at " . 
                                  date('g:i A', strtotime($slot['start_time']));
            $stmt->execute([$user_id, $notification_message]);
            
            // Commit transaction
            $pdo->commit();
            
            $success = "Class booked successfully! You will be redirected to your dashboard.";
            header("refresh:3;url=dashboard.php");
            
        } catch (Exception $e) {
            // Rollback transaction on error
            $pdo->rollBack();
            $error = $e->getMessage();
        }
    }
    
} catch(PDOException $e) {
    die("Error: " . $e->getMessage());
}

require_once 'includes/header.php';
?>

<div class="container mt-4">
    <div class="row">
        <div class="col-md-8 mx-auto">
            <div class="card">
                <div class="card-body">
                    <h2 class="card-title mb-4">Book Class</h2>
                    
                    <?php if ($error): ?>
                        <div class="alert alert-danger"><?php echo $error; ?></div>
                    <?php endif; ?>
                    
                    <?php if ($success): ?>
                        <div class="alert alert-success"><?php echo $success; ?></div>
                    <?php else: ?>
                        <!-- Class Details -->
                        <div class="mb-4">
                            <h3 class="h5"><?php echo htmlspecialchars($slot['class_name']); ?></h3>
                            <p class="text-muted mb-3"><?php echo htmlspecialchars($slot['description']); ?></p>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <ul class="list-unstyled">
                                        <li class="mb-2">
                                            <i class="bi bi-calendar"></i>
                                            <strong>Date:</strong> 
                                            <?php echo date('F j, Y', strtotime($slot['start_time'])); ?>
                                        </li>
                                        <li class="mb-2">
                                            <i class="bi bi-clock"></i>
                                            <strong>Time:</strong>
                                            <?php 
                                                echo date('g:i A', strtotime($slot['start_time'])) . ' - ' . 
                                                     date('g:i A', strtotime($slot['end_time']));
                                            ?>
                                        </li>
                                        <li class="mb-2">
                                            <i class="bi bi-person"></i>
                                            <strong>Trainer:</strong>
                                            <?php echo htmlspecialchars($slot['trainer_name']); ?>
                                            <?php if ($slot['rating']): ?>
                                                <span class="ms-2">
                                                    <i class="bi bi-star-fill text-warning"></i>
                                                    <?php echo number_format($slot['rating'], 1); ?>
                                                </span>
                                            <?php endif; ?>
                                        </li>
                                    </ul>
                                </div>
                                <div class="col-md-6">
                                    <ul class="list-unstyled">
                                        <li class="mb-2">
                                            <i class="bi bi-lightning"></i>
                                            <strong>Difficulty:</strong>
                                            <span class="badge bg-<?php 
                                                echo $slot['difficulty_level'] === 'beginner' ? 'success' : 
                                                     ($slot['difficulty_level'] === 'intermediate' ? 'warning' : 'danger');
                                            ?>">
                                                <?php echo ucfirst($slot['difficulty_level']); ?>
                                            </span>
                                        </li>
                                        <li class="mb-2">
                                            <i class="bi bi-currency-dollar"></i>
                                            <strong>Rate:</strong>
                                            $<?php echo number_format($slot['hourly_rate'], 2); ?> per hour
                                        </li>
                                        <li class="mb-2">
                                            <i class="bi bi-clock-history"></i>
                                            <strong>Duration:</strong>
                                            <?php 
                                                $duration = round((strtotime($slot['end_time']) - strtotime($slot['start_time'])) / 60);
                                                echo $duration . ' minutes';
                                            ?>
                                        </li>
                                    </ul>
                                </div>
                            </div>
                        </div>

                        <!-- Booking Form -->
                        <form method="POST" action="" id="bookingForm">
                            <div class="alert alert-info">
                                <h4 class="alert-heading h5">Please Note:</h4>
                                <ul class="mb-0">
                                    <li>Booking is on a first-come, first-served basis</li>
                                    <li>Please arrive 10 minutes before the class starts</li>
                                    <li>Cancellation is allowed up to 24 hours before the class</li>
                                </ul>
                            </div>
                            
                            <div class="form-check mb-3">
                                <input class="form-check-input" type="checkbox" id="terms" required>
                                <label class="form-check-label" for="terms">
                                    I agree to the booking terms and conditions
                                </label>
                            </div>
                            
                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-primary">Confirm Booking</button>
                                <a href="classes.php" class="btn btn-outline-secondary">Cancel</a>
                            </div>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?> 