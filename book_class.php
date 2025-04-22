<?php
$page_title = "Book Class - Pulse Fitness Hub";
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

    // Get all trainers for filter
    $stmt = $pdo->query("SELECT user_id, first_name, last_name FROM users WHERE role = 'trainer'");
    $trainers = $stmt->fetchAll();

    // Build query for available classes
    $query = "
        SELECT ts.*, c.name as class_name, c.description, c.difficulty_level, 
               u.first_name as trainer_first_name, u.last_name as trainer_last_name,
               COUNT(b.booking_id) as booked_count, c.max_participants
        FROM time_slots ts
        JOIN classes c ON ts.class_id = c.class_id
        JOIN users u ON ts.trainer_id = u.user_id
        LEFT JOIN bookings b ON ts.slot_id = b.slot_id
        WHERE ts.status = 'available'
        AND ts.date >= CURDATE()
    ";

    $params = [];

    if ($date) {
        $query .= " AND ts.date = ?";
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

    $query .= " GROUP BY ts.slot_id HAVING booked_count < c.max_participants ORDER BY ts.date, ts.start_time";

    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $classes = $stmt->fetchAll();

    // Handle booking submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['slot_id'])) {
        $pdo->beginTransaction();

        try {
            // Check if slot is still available
            $stmt = $pdo->prepare("
                SELECT ts.*, c.max_participants, COUNT(b.booking_id) as booked_count
                FROM time_slots ts
                JOIN classes c ON ts.class_id = c.class_id
                LEFT JOIN bookings b ON ts.slot_id = b.slot_id
                WHERE ts.slot_id = ? AND ts.status = 'available'
                GROUP BY ts.slot_id
                HAVING booked_count < c.max_participants
                FOR UPDATE
            ");
            $stmt->execute([$_POST['slot_id']]);
            $slot = $stmt->fetch();

            if (!$slot) {
                throw new Exception("This class is no longer available.");
            }

            // Create booking
            $stmt = $pdo->prepare("
                INSERT INTO bookings (user_id, slot_id, booking_date, status)
                VALUES (?, ?, NOW(), 'confirmed')
            ");
            $stmt->execute([$user_id, $_POST['slot_id']]);

            // Create payment record
            $stmt = $pdo->prepare("
                INSERT INTO payments (user_id, booking_id, amount, payment_type, status)
                VALUES (?, ?, ?, 'class', 'pending')
            ");
            $stmt->execute([
                $user_id,
                $pdo->lastInsertId(),
                $slot['price']
            ]);

            $pdo->commit();
            $success_message = "Class booked successfully! Please complete the payment to confirm your booking.";

        } catch (Exception $e) {
            $pdo->rollBack();
            $error_message = "Error booking class: " . $e->getMessage();
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
                    <h2 class="card-title h4 mb-0">Book a Class</h2>
                </div>
                <div class="card-body">
                    <?php if ($success_message): ?>
                        <div class="alert alert-success"><?php echo $success_message; ?></div>
                    <?php endif; ?>
                    <?php if ($error_message): ?>
                        <div class="alert alert-danger"><?php echo $error_message; ?></div>
                    <?php endif; ?>

                    <!-- Filters -->
                    <form method="GET" class="row g-3 mb-4">
                        <div class="col-md-4">
                            <label for="date" class="form-label">Date</label>
                            <input type="date" class="form-control" id="date" name="date" 
                                   value="<?php echo $date; ?>" min="<?php echo date('Y-m-d'); ?>">
                        </div>
                        <div class="col-md-4">
                            <label for="difficulty" class="form-label">Difficulty Level</label>
                            <select class="form-select" id="difficulty" name="difficulty">
                                <option value="">All Levels</option>
                                <option value="beginner" <?php echo $difficulty === 'beginner' ? 'selected' : ''; ?>>Beginner</option>
                                <option value="intermediate" <?php echo $difficulty === 'intermediate' ? 'selected' : ''; ?>>Intermediate</option>
                                <option value="advanced" <?php echo $difficulty === 'advanced' ? 'selected' : ''; ?>>Advanced</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label for="trainer_id" class="form-label">Trainer</label>
                            <select class="form-select" id="trainer_id" name="trainer_id">
                                <option value="">All Trainers</option>
                                <?php foreach ($trainers as $trainer): ?>
                                    <option value="<?php echo $trainer['user_id']; ?>" 
                                            <?php echo $trainer_id == $trainer['user_id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($trainer['first_name'] . ' ' . $trainer['last_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12">
                            <button type="submit" class="btn btn-primary">Filter Classes</button>
                            <a href="book_class.php" class="btn btn-outline-secondary">Clear Filters</a>
                        </div>
                    </form>

                    <!-- Available Classes -->
                    <?php if ($classes): ?>
                        <div class="row">
                            <?php foreach ($classes as $class): ?>
                                <div class="col-md-6 col-lg-4 mb-4">
                                    <div class="card h-100">
                                        <img src="assets/images/classes/<?php echo strtolower(str_replace(' ', '-', $class['class_name'])); ?>.jpg" 
                                             class="card-img-top" alt="<?php echo htmlspecialchars($class['class_name']); ?>">
                                        <div class="card-body">
                                            <h3 class="h5"><?php echo htmlspecialchars($class['class_name']); ?></h3>
                                            <p class="text-muted">
                                                <?php echo date('l, M d', strtotime($class['date'])); ?> at 
                                                <?php echo date('h:i A', strtotime($class['start_time'])); ?>
                                            </p>
                                            <p class="mb-2"><?php echo htmlspecialchars($class['description']); ?></p>
                                            <p class="mb-2">
                                                Trainer: <?php echo htmlspecialchars($class['trainer_first_name'] . ' ' . $class['trainer_last_name']); ?>
                                            </p>
                                            <p class="mb-2">
                                                Difficulty: 
                                                <span class="badge bg-<?php 
                                                    echo $class['difficulty_level'] === 'beginner' ? 'success' : 
                                                        ($class['difficulty_level'] === 'intermediate' ? 'warning' : 'danger'); 
                                                ?>">
                                                    <?php echo ucfirst($class['difficulty_level']); ?>
                                                </span>
                                            </p>
                                            <p class="mb-2">
                                                Available Spots: 
                                                <?php echo $class['max_participants'] - $class['booked_count']; ?> / 
                                                <?php echo $class['max_participants']; ?>
                                            </p>
                                            <p class="mb-3">
                                                Price: $<?php echo number_format($class['price'], 2); ?>
                                            </p>
                                            <form method="POST">
                                                <input type="hidden" name="slot_id" value="<?php echo $class['slot_id']; ?>">
                                                <button type="submit" class="btn btn-primary w-100">Book Now</button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-info">
                            <p class="mb-0">No classes available matching your criteria. Please try different filters.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?> 