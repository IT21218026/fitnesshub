<?php
$page_title = "Trainer Profile - Pulse Fitness Hub";
require_once 'includes/config.php';

// Check if trainer ID is provided
if (!isset($_GET['id'])) {
    header("Location: trainers.php");
    exit();
}

$trainer_id = $_GET['id'];

try {
    // Get trainer details
    $stmt = $conn->prepare("
        SELECT u.*, tp.*,
               (SELECT COUNT(*) FROM time_slots ts 
                WHERE ts.trainer_id = tp.trainer_id 
                AND ts.status = 'booked') as total_sessions,
               (SELECT AVG(rating) FROM trainer_reviews tr 
                WHERE tr.trainer_id = tp.trainer_id) as avg_rating,
               (SELECT COUNT(*) FROM trainer_reviews tr 
                WHERE tr.trainer_id = tp.trainer_id) as review_count
        FROM users u
        JOIN trainer_profiles tp ON u.user_id = tp.user_id
        WHERE tp.trainer_id = ?
    ");
    $stmt->execute([$trainer_id]);
    
    if ($stmt->rowCount() === 0) {
        header("Location: trainers.php");
        exit();
    }

    $trainer = $stmt->fetch();

    // Get trainer's available slots for the next 7 days
    $stmt = $conn->prepare("
        SELECT ts.*, c.name as class_name, c.difficulty_level
        FROM time_slots ts
        JOIN classes c ON ts.class_id = c.class_id
        WHERE ts.trainer_id = ?
        AND ts.status = 'available'
        AND ts.start_time BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 7 DAY)
        ORDER BY ts.start_time ASC
    ");
    $stmt->execute([$trainer_id]);
    $available_slots = $stmt->fetchAll();

    // Get trainer reviews
    $stmt = $conn->prepare("
        SELECT tr.*, u.first_name, u.last_name, u.profile_picture
        FROM trainer_reviews tr
        JOIN users u ON tr.user_id = u.user_id
        WHERE tr.trainer_id = ?
        ORDER BY tr.created_at DESC
        LIMIT 5
    ");
    $stmt->execute([$trainer_id]);
    $reviews = $stmt->fetchAll();

} catch(PDOException $e) {
    die("Error: " . $e->getMessage());
}

require_once 'includes/header.php';
?>

<!-- Trainer Profile Header -->
<div class="profile-header">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-md-3 text-center text-md-start">
                <img src="<?php echo $trainer['profile_picture'] ?? 'assets/images/default-trainer.jpg'; ?>" 
                     alt="<?php echo htmlspecialchars($trainer['first_name']); ?>"
                     class="profile-avatar">
            </div>
            <div class="col-md-6">
                <h1 class="mb-2"><?php echo htmlspecialchars($trainer['first_name'] . ' ' . $trainer['last_name']); ?></h1>
                <p class="lead mb-0"><?php echo htmlspecialchars($trainer['specialization']); ?></p>
                <div class="mt-3">
                    <?php if ($trainer['avg_rating']): ?>
                        <div class="d-inline-block me-3">
                            <div class="h5 mb-0">
                                <i class="bi bi-star-fill text-warning"></i>
                                <?php echo number_format($trainer['avg_rating'], 1); ?>
                                <small class="text-white-50">(<?php echo $trainer['review_count']; ?> reviews)</small>
                            </div>
                        </div>
                    <?php endif; ?>
                    <div class="d-inline-block me-3">
                        <div class="h5 mb-0">
                            <i class="bi bi-calendar-check"></i>
                            <?php echo $trainer['total_sessions']; ?> sessions
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3 text-center text-md-end mt-3 mt-md-0">
                <?php if (isset($_SESSION['user_id'])): ?>
                    <a href="classes.php?trainer=<?php echo $trainer_id; ?>" class="btn btn-light btn-lg">Book Session</a>
                <?php else: ?>
                    <a href="login.php" class="btn btn-light btn-lg">Login to Book</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="container mt-4">
    <div class="row">
        <!-- Main Content -->
        <div class="col-lg-8">
            <!-- About Section -->
            <div class="card mb-4">
                <div class="card-body">
                    <h2 class="card-title h4 mb-3">About Me</h2>
                    <p><?php echo nl2br(htmlspecialchars($trainer['bio'])); ?></p>
                    
                    <div class="row mt-4">
                        <div class="col-md-6">
                            <h3 class="h5">Specializations</h3>
                            <ul class="list-unstyled">
                                <?php 
                                $specializations = explode(',', $trainer['specialization']);
                                foreach ($specializations as $spec): 
                                ?>
                                    <li><i class="bi bi-check-circle text-success"></i> <?php echo trim($spec); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                        <div class="col-md-6">
                            <h3 class="h5">Experience & Certifications</h3>
                            <ul class="list-unstyled">
                                <li><i class="bi bi-award text-primary"></i> <?php echo $trainer['experience_years']; ?> Years Experience</li>
                                <li><i class="bi bi-patch-check text-primary"></i> Certified Personal Trainer</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Available Slots -->
            <div class="card mb-4">
                <div class="card-body">
                    <h2 class="card-title h4 mb-3">Available Time Slots</h2>
                    <?php if ($available_slots): ?>
                        <div class="list-group">
                            <?php foreach ($available_slots as $slot): ?>
                                <div class="list-group-item">
                                    <div class="d-flex w-100 justify-content-between align-items-center">
                                        <div>
                                            <h6 class="mb-1"><?php echo htmlspecialchars($slot['class_name']); ?></h6>
                                            <p class="mb-1">
                                                <i class="bi bi-calendar"></i>
                                                <?php echo date('F j, Y', strtotime($slot['start_time'])); ?>
                                                <span class="mx-2">|</span>
                                                <i class="bi bi-clock"></i>
                                                <?php 
                                                    echo date('g:i A', strtotime($slot['start_time'])) . ' - ' . 
                                                         date('g:i A', strtotime($slot['end_time']));
                                                ?>
                                            </p>
                                        </div>
                                        <?php if (isset($_SESSION['user_id'])): ?>
                                            <a href="booking.php?slot_id=<?php echo $slot['slot_id']; ?>" 
                                               class="btn btn-primary btn-sm">Book Now</a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p class="text-muted">No available slots for the next 7 days.</p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Reviews -->
            <div class="card">
                <div class="card-body">
                    <h2 class="card-title h4 mb-3">Client Reviews</h2>
                    <?php if ($reviews): ?>
                        <?php foreach ($reviews as $review): ?>
                            <div class="d-flex mb-4">
                                <img src="<?php echo $review['profile_picture'] ?? 'assets/images/default-avatar.jpg'; ?>" 
                                     class="rounded-circle me-3" width="50" height="50"
                                     alt="<?php echo htmlspecialchars($review['first_name']); ?>">
                                <div>
                                    <div class="d-flex align-items-center mb-1">
                                        <h6 class="mb-0 me-2">
                                            <?php echo htmlspecialchars($review['first_name'] . ' ' . $review['last_name']); ?>
                                        </h6>
                                        <div class="text-warning">
                                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                                <i class="bi bi-star<?php echo $i <= $review['rating'] ? '-fill' : ''; ?>"></i>
                                            <?php endfor; ?>
                                        </div>
                                    </div>
                                    <p class="mb-1"><?php echo htmlspecialchars($review['comment']); ?></p>
                                    <small class="text-muted">
                                        <?php echo date('F j, Y', strtotime($review['created_at'])); ?>
                                    </small>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p class="text-muted">No reviews yet.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Sidebar -->
        <div class="col-lg-4 mt-4 mt-lg-0">
            <!-- Quick Stats -->
            <div class="card mb-4">
                <div class="card-body">
                    <h3 class="card-title h5 mb-3">Quick Stats</h3>
                    <div class="row text-center">
                        <div class="col-6 mb-3">
                            <h4 class="h2 mb-1"><?php echo $trainer['total_sessions']; ?></h4>
                            <small class="text-muted">Sessions Completed</small>
                        </div>
                        <div class="col-6 mb-3">
                            <h4 class="h2 mb-1"><?php echo $trainer['experience_years']; ?></h4>
                            <small class="text-muted">Years Experience</small>
                        </div>
                        <div class="col-6">
                            <h4 class="h2 mb-1"><?php echo count($available_slots); ?></h4>
                            <small class="text-muted">Available Slots</small>
                        </div>
                        <div class="col-6">
                            <h4 class="h2 mb-1">
                                <?php echo $trainer['avg_rating'] ? number_format($trainer['avg_rating'], 1) : 'N/A'; ?>
                            </h4>
                            <small class="text-muted">Average Rating</small>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Contact Card -->
            <div class="card">
                <div class="card-body text-center">
                    <h3 class="card-title h5 mb-3">Have Questions?</h3>
                    <p class="mb-3">Want to learn more about my training programs or have specific questions?</p>
                    <a href="contact.php?trainer_id=<?php echo $trainer_id; ?>" class="btn btn-primary">
                        Contact <?php echo htmlspecialchars($trainer['first_name']); ?>
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?> 