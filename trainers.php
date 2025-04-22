<?php
$page_title = "Our Trainers - Pulse Fitness Hub";
require_once 'includes/config.php';
require_once 'includes/header.php';

try {
    // Get all trainers with their profiles
    $stmt = $pdo->query("
        SELECT u.*, tp.*,
               (SELECT COUNT(*) FROM time_slots ts 
                WHERE ts.trainer_id = tp.trainer_id 
                AND ts.status = 'booked') as total_sessions,
               (SELECT COUNT(*) FROM time_slots ts 
                WHERE ts.trainer_id = tp.trainer_id 
                AND ts.status = 'available'
                AND ts.start_time > NOW()) as available_slots
        FROM users u
        JOIN trainer_profiles tp ON u.user_id = tp.user_id
        WHERE u.role = 'trainer'
        ORDER BY tp.rating DESC
    ");
    $trainers = $stmt->fetchAll();
} catch(PDOException $e) {
    die("Error: " . $e->getMessage());
}
?>

<!-- Trainers Header -->
<div class="bg-primary text-white py-5">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-md-8">
                <h1 class="mb-3">Our Expert Trainers</h1>
                <p class="lead mb-0">Meet our certified fitness professionals dedicated to helping you achieve your goals</p>
            </div>
            <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
                <div class="col-md-4 text-md-end">
                    <a href="admin/trainers/add.php" class="btn btn-light">Add New Trainer</a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="container mt-4">
    <!-- Trainer Cards -->
    <div class="row g-4">
        <?php foreach ($trainers as $trainer): ?>
            <div class="col-md-6 col-lg-4">
                <div class="card h-100">
                    <img src="<?php echo $trainer['profile_picture'] ?? 'assets/images/default-trainer.jpg'; ?>" 
                         class="card-img-top" alt="<?php echo htmlspecialchars($trainer['first_name']); ?>">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start mb-3">
                            <div>
                                <h3 class="card-title h5 mb-1">
                                    <?php echo htmlspecialchars($trainer['first_name'] . ' ' . $trainer['last_name']); ?>
                                </h3>
                                <p class="text-muted mb-0">
                                    <i class="bi bi-award"></i> 
                                    <?php echo htmlspecialchars($trainer['specialization']); ?>
                                </p>
                            </div>
                            <?php if ($trainer['rating']): ?>
                                <div class="badge bg-warning text-dark">
                                    <i class="bi bi-star-fill"></i> 
                                    <?php echo number_format($trainer['rating'], 1); ?>
                                </div>
                            <?php endif; ?>
                        </div>

                        <p class="card-text"><?php echo htmlspecialchars($trainer['bio']); ?></p>
                        
                        <div class="row g-2 mb-3">
                            <div class="col-6">
                                <div class="border rounded p-2 text-center">
                                    <small class="d-block text-muted">Experience</small>
                                    <strong><?php echo isset($trainer['experience_years']) ? $trainer['experience_years'] . ' Years' : 'N/A'; ?></strong>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="border rounded p-2 text-center">
                                    <small class="d-block text-muted">Sessions</small>
                                    <strong><?php echo $trainer['total_sessions']; ?></strong>
                                </div>
                            </div>
                        </div>

                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <small class="text-muted">
                                    <i class="bi bi-calendar-check"></i>
                                    <?php echo $trainer['available_slots']; ?> slots available
                                </small>
                            </div>
                            <div>
                                <a href="trainer_profile.php?id=<?php echo $trainer['trainer_id']; ?>" 
                                   class="btn btn-outline-primary btn-sm">View Profile</a>
                                <?php if (isset($_SESSION['user_id'])): ?>
                                    <a href="classes.php?trainer=<?php echo $trainer['trainer_id']; ?>" 
                                       class="btn btn-primary btn-sm">Book Session</a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- Become a Trainer Section -->
    <div class="card mt-5">
        <div class="card-body text-center py-5">
            <h2 class="h4 mb-3">Want to Join Our Team?</h2>
            <p class="mb-4">Are you a certified fitness trainer? Join Pulse Fitness Hub and help others achieve their fitness goals.</p>
            <a href="trainer_application.php" class="btn btn-primary">Apply as Trainer</a>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?> 