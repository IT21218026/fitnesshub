<?php
$page_title = "Class Schedule - Pulse Fitness Hub";
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
    // Get filter parameters
    $date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');
    $difficulty = isset($_GET['difficulty']) ? $_GET['difficulty'] : '';
    $trainer_id = isset($_GET['trainer_id']) ? $_GET['trainer_id'] : '';
    $class_type = isset($_GET['class_type']) ? $_GET['class_type'] : '';

    // Get all trainers for filter
    $stmt = $pdo->query("SELECT id, first_name, last_name FROM users WHERE role = 'trainer'");
    $trainers = $stmt->fetchAll();

    // Get all class types for filter
    $stmt = $pdo->query("SELECT DISTINCT type FROM classes");
    $class_types = $stmt->fetchAll(PDO::FETCH_COLUMN);

    // Build query for available classes
    $query = "
        SELECT ts.*, c.name as class_name, c.description, c.difficulty_level, c.type,
               u.first_name as trainer_first_name, u.last_name as trainer_last_name,
               COUNT(b.id) as booked_count, c.max_participants
        FROM time_slots ts
        JOIN classes c ON ts.class_id = c.id
        JOIN users u ON ts.trainer_id = u.id
        LEFT JOIN bookings b ON ts.id = b.time_slot_id
        WHERE ts.status = 'available'
        AND ts.start_time >= NOW()
    ";

    $params = [];

    if ($date) {
        $query .= " AND DATE(ts.start_time) = ?";
        $params[] = $date;
    }

    if ($difficulty) {
        $query .= " AND c.difficulty_level = ?";
        $params[] = $difficulty;
    }

    if ($trainer_id) {
        $query .= " AND ts.trainer_id = ?";
        $params[] = $trainer_id;
    }

    if ($class_type) {
        $query .= " AND c.type = ?";
        $params[] = $class_type;
    }

    $query .= " GROUP BY ts.id HAVING booked_count < c.max_participants ORDER BY ts.start_time";

    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $classes = $stmt->fetchAll();

} catch(PDOException $e) {
    $error_message = "Error: " . $e->getMessage();
}

require_once 'includes/header.php';
?>

<div class="container mt-4">
    <?php if ($success_message): ?>
        <div class="alert alert-success"><?php echo $success_message; ?></div>
    <?php endif; ?>
    <?php if ($error_message): ?>
        <div class="alert alert-danger"><?php echo $error_message; ?></div>
    <?php endif; ?>

    <!-- Filters -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-3">
                    <label for="date" class="form-label">Date</label>
                    <input type="date" class="form-control" id="date" name="date" 
                           value="<?php echo $date; ?>" min="<?php echo date('Y-m-d'); ?>">
                </div>
                <div class="col-md-3">
                    <label for="difficulty" class="form-label">Difficulty</label>
                    <select class="form-select" id="difficulty" name="difficulty">
                        <option value="">All Levels</option>
                        <option value="beginner" <?php echo $difficulty === 'beginner' ? 'selected' : ''; ?>>Beginner</option>
                        <option value="intermediate" <?php echo $difficulty === 'intermediate' ? 'selected' : ''; ?>>Intermediate</option>
                        <option value="advanced" <?php echo $difficulty === 'advanced' ? 'selected' : ''; ?>>Advanced</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="trainer_id" class="form-label">Trainer</label>
                    <select class="form-select" id="trainer_id" name="trainer_id">
                        <option value="">All Trainers</option>
                        <?php foreach ($trainers as $trainer): ?>
                            <option value="<?php echo $trainer['id']; ?>" 
                                    <?php echo $trainer_id == $trainer['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($trainer['first_name'] . ' ' . $trainer['last_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="class_type" class="form-label">Class Type</label>
                    <select class="form-select" id="class_type" name="class_type">
                        <option value="">All Types</option>
                        <?php foreach ($class_types as $type): ?>
                            <option value="<?php echo $type; ?>" 
                                    <?php echo $class_type === $type ? 'selected' : ''; ?>>
                                <?php echo ucfirst($type); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-12">
                    <button type="submit" class="btn btn-primary">Filter Classes</button>
                    <a href="class_schedule.php" class="btn btn-outline-secondary">Reset Filters</a>
                </div>
            </form>
        </div>
    </div>

    <!-- Classes List -->
    <div class="row">
        <?php if (empty($classes)): ?>
            <div class="col-12">
                <div class="alert alert-info">
                    No classes available for the selected filters. Please try different criteria.
                </div>
            </div>
        <?php else: ?>
            <?php foreach ($classes as $class): ?>
                <div class="col-md-6 col-lg-4 mb-4">
                    <div class="card h-100">
                        <div class="card-body">
                            <h5 class="card-title"><?php echo htmlspecialchars($class['class_name']); ?></h5>
                            <p class="card-text text-muted">
                                <?php echo htmlspecialchars($class['description']); ?>
                            </p>
                            <div class="mb-3">
                                <p class="mb-1">
                                    <i class="bi bi-calendar"></i>
                                    <?php echo date('F j, Y', strtotime($class['start_time'])); ?>
                                </p>
                                <p class="mb-1">
                                    <i class="bi bi-clock"></i>
                                    <?php echo date('g:i A', strtotime($class['start_time'])); ?> - 
                                    <?php echo date('g:i A', strtotime($class['end_time'])); ?>
                                </p>
                                <p class="mb-1">
                                    <i class="bi bi-person"></i>
                                    Trainer: <?php echo htmlspecialchars($class['trainer_first_name'] . ' ' . $class['trainer_last_name']); ?>
                                </p>
                                <p class="mb-2">
                                    <i class="bi bi-lightning"></i>
                                    Difficulty: 
                                    <span class="badge bg-<?php 
                                        echo $class['difficulty_level'] === 'beginner' ? 'success' : 
                                            ($class['difficulty_level'] === 'intermediate' ? 'warning' : 'danger'); 
                                    ?>">
                                        <?php echo ucfirst($class['difficulty_level']); ?>
                                    </span>
                                </p>
                                <p class="mb-2">
                                    <i class="bi bi-people"></i>
                                    Available Spots: 
                                    <?php echo $class['max_participants'] - $class['booked_count']; ?> / 
                                    <?php echo $class['max_participants']; ?>
                                </p>
                            </div>
                            <a href="booking.php?slot_id=<?php echo $class['id']; ?>" 
                               class="btn btn-primary w-100">Book Now</a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?> 