<?php
$page_title = "Classes - Pulse Fitness Hub";
require_once 'includes/config.php';
require_once 'includes/header.php';

// Get filter parameters
$difficulty = isset($_GET['difficulty']) ? $_GET['difficulty'] : '';
$trainer = isset($_GET['trainer']) ? $_GET['trainer'] : '';
$date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');

try {
    // Get all trainers for filter
    $trainers = $pdo->query("
        SELECT u.user_id, u.first_name, u.last_name 
        FROM users u 
        JOIN trainer_profiles tp ON u.user_id = tp.user_id
        ORDER BY u.first_name, u.last_name
    ")->fetchAll();

    // Build query for classes with available slots
    $query = "
        SELECT c.*, ts.slot_id, ts.start_time, ts.end_time,
               CONCAT(u.first_name, ' ', u.last_name) as trainer_name,
               tp.trainer_id, tp.rating
        FROM classes c
        JOIN time_slots ts ON c.class_id = ts.class_id
        JOIN trainer_profiles tp ON ts.trainer_id = tp.trainer_id
        JOIN users u ON tp.user_id = u.user_id
        WHERE ts.status = 'available'
        AND DATE(ts.start_time) = ?
    ";

    $params = [$date];

    if ($difficulty) {
        $query .= " AND c.difficulty_level = ?";
        $params[] = $difficulty;
    }

    if ($trainer) {
        $query .= " AND tp.trainer_id = ?";
        $params[] = $trainer;
    }

    $query .= " ORDER BY ts.start_time ASC";

    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $classes = $stmt->fetchAll();

} catch(PDOException $e) {
    die("Error: " . $e->getMessage());
}
?>

<!-- Classes Header -->
<div class="bg-primary text-white py-5">
    <div class="container">
        <h1 class="mb-3">Fitness Classes</h1>
        <p class="lead mb-0">Choose from our wide range of classes led by expert trainers</p>
    </div>
</div>

<div class="container mt-4">
    <!-- Filters -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-3">
                    <label for="date" class="form-label">Date</label>
                    <input type="date" class="form-control" id="date" name="date" 
                           value="<?php echo htmlspecialchars($date); ?>" min="<?php echo date('Y-m-d'); ?>">
                </div>
                <div class="col-md-3">
                    <label for="difficulty" class="form-label">Difficulty Level</label>
                    <select class="form-select" id="difficulty" name="difficulty">
                        <option value="">All Levels</option>
                        <option value="beginner" <?php echo $difficulty === 'beginner' ? 'selected' : ''; ?>>Beginner</option>
                        <option value="intermediate" <?php echo $difficulty === 'intermediate' ? 'selected' : ''; ?>>Intermediate</option>
                        <option value="advanced" <?php echo $difficulty === 'advanced' ? 'selected' : ''; ?>>Advanced</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="trainer" class="form-label">Trainer</label>
                    <select class="form-select" id="trainer" name="trainer">
                        <option value="">All Trainers</option>
                        <?php foreach ($trainers as $t): ?>
                            <option value="<?php echo $t['user_id']; ?>" 
                                    <?php echo $trainer == $t['user_id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($t['first_name'] . ' ' . $t['last_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">&nbsp;</label>
                    <button type="submit" class="btn btn-primary d-block w-100">Apply Filters</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Classes Grid -->
    <div class="row g-4">
        <?php if ($classes): ?>
            <?php foreach ($classes as $class): ?>
                <div class="col-md-6 col-lg-4">
                    <div class="card h-100">
                        <img src="assets/images/classes/<?php echo htmlspecialchars($class['class_id']); ?>.jpg" 
                             class="card-img-top" alt="<?php echo htmlspecialchars($class['name']); ?>">
                        <div class="card-body">
                            <h3 class="card-title h5"><?php echo htmlspecialchars($class['name']); ?></h3>
                            <p class="card-text"><?php echo htmlspecialchars($class['description']); ?></p>
                            
                            <div class="mb-3">
                                <small class="text-muted">
                                    <i class="bi bi-clock"></i> 
                                    <?php echo date('g:i A', strtotime($class['start_time'])); ?> - 
                                    <?php echo date('g:i A', strtotime($class['end_time'])); ?>
                                </small>
                            </div>
                            
                            <div class="mb-3">
                                <small class="text-muted">
                                    <i class="bi bi-person"></i> 
                                    Trainer: <?php echo htmlspecialchars($class['trainer_name']); ?>
                                    <?php if ($class['rating']): ?>
                                        <span class="ms-2">
                                            <i class="bi bi-star-fill text-warning"></i>
                                            <?php echo number_format($class['rating'], 1); ?>
                                        </span>
                                    <?php endif; ?>
                                </small>
                            </div>

                            <div class="d-flex justify-content-between align-items-center">
                                <span class="badge bg-<?php 
                                    echo $class['difficulty_level'] === 'beginner' ? 'success' : 
                                         ($class['difficulty_level'] === 'intermediate' ? 'warning' : 'danger');
                                ?>">
                                    <?php echo ucfirst($class['difficulty_level']); ?>
                                </span>
                                <?php if (isset($_SESSION['user_id'])): ?>
                                    <a href="booking.php?slot_id=<?php echo $class['slot_id']; ?>" 
                                       class="btn btn-primary btn-sm">
                                        Book Now
                                    </a>
                                <?php else: ?>
                                    <a href="login.php" class="btn btn-outline-primary btn-sm">
                                        Login to Book
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="col-12">
                <div class="alert alert-info">
                    No classes available for the selected filters. Try different options or another date.
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?> 