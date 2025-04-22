<?php
$page_title = "Track Progress - Pulse Fitness Hub";
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
    // Get user's latest progress data
    $stmt = $pdo->prepare("
        SELECT * FROM progress_tracking 
        WHERE user_id = ? 
        ORDER BY date_recorded DESC 
        LIMIT 10
    ");
    $stmt->execute([$user_id]);
    $progress_data = $stmt->fetchAll();

    // Get user's fitness goals
    try {
        $stmt = $pdo->prepare("
            SELECT * FROM fitness_goals 
            WHERE user_id = ? 
            ORDER BY target_date ASC
        ");
        $stmt->execute([$user_id]);
        $goals = $stmt->fetchAll();
    } catch (PDOException $e) {
        // If fitness_goals table doesn't exist, set goals to empty array
        $goals = [];
        $error_message = "Fitness goals feature is not available yet. Please check back later.";
    }

    // Handle form submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO progress_tracking 
                (user_id, date_recorded, weight, body_fat_percentage, 
                 chest_measurement, waist_measurement, hip_measurement, 
                 bicep_measurement, thigh_measurement, notes)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $user_id,
                $_POST['date_recorded'],
                $_POST['weight'],
                $_POST['body_fat_percentage'],
                $_POST['chest_measurement'],
                $_POST['waist_measurement'],
                $_POST['hip_measurement'],
                $_POST['bicep_measurement'],
                $_POST['thigh_measurement'],
                $_POST['notes']
            ]);

            $success_message = "Progress recorded successfully!";
            
            // Refresh progress data
            $stmt = $pdo->prepare("
                SELECT * FROM progress_tracking 
                WHERE user_id = ? 
                ORDER BY date_recorded DESC 
                LIMIT 10
            ");
            $stmt->execute([$user_id]);
            $progress_data = $stmt->fetchAll();
        } catch (PDOException $e) {
            $error_message = "Error recording progress: " . $e->getMessage();
        }
    }

} catch(PDOException $e) {
    $error_message = "Error: " . $e->getMessage();
}

require_once 'includes/header.php';
?>

<div class="container mt-4">
    <div class="row">
        <!-- Progress Form -->
        <div class="col-lg-4">
            <div class="card mb-4">
                <div class="card-header">
                    <h2 class="card-title h4 mb-0">Record Progress</h2>
                </div>
                <div class="card-body">
                    <?php if ($success_message): ?>
                        <div class="alert alert-success"><?php echo $success_message; ?></div>
                    <?php endif; ?>
                    <?php if ($error_message): ?>
                        <div class="alert alert-danger"><?php echo $error_message; ?></div>
                    <?php endif; ?>

                    <form method="POST">
                        <div class="mb-3">
                            <label for="date_recorded" class="form-label">Date</label>
                            <input type="date" class="form-control" id="date_recorded" name="date_recorded" 
                                   value="<?php echo date('Y-m-d'); ?>" required>
                        </div>

                        <div class="mb-3">
                            <label for="weight" class="form-label">Weight (kg)</label>
                            <input type="number" step="0.1" class="form-control" id="weight" name="weight" required>
                        </div>

                        <div class="mb-3">
                            <label for="body_fat_percentage" class="form-label">Body Fat Percentage</label>
                            <input type="number" step="0.1" class="form-control" id="body_fat_percentage" 
                                   name="body_fat_percentage">
                        </div>

                        <h3 class="h5 mt-4 mb-3">Body Measurements (cm)</h3>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="chest_measurement" class="form-label">Chest</label>
                                <input type="number" step="0.1" class="form-control" id="chest_measurement" 
                                       name="chest_measurement">
                            </div>
                            <div class="col-md-6">
                                <label for="waist_measurement" class="form-label">Waist</label>
                                <input type="number" step="0.1" class="form-control" id="waist_measurement" 
                                       name="waist_measurement">
                            </div>
                            <div class="col-md-6">
                                <label for="hip_measurement" class="form-label">Hip</label>
                                <input type="number" step="0.1" class="form-control" id="hip_measurement" 
                                       name="hip_measurement">
                            </div>
                            <div class="col-md-6">
                                <label for="bicep_measurement" class="form-label">Bicep</label>
                                <input type="number" step="0.1" class="form-control" id="bicep_measurement" 
                                       name="bicep_measurement">
                            </div>
                            <div class="col-md-6">
                                <label for="thigh_measurement" class="form-label">Thigh</label>
                                <input type="number" step="0.1" class="form-control" id="thigh_measurement" 
                                       name="thigh_measurement">
                            </div>
                        </div>

                        <div class="mb-3 mt-3">
                            <label for="notes" class="form-label">Notes</label>
                            <textarea class="form-control" id="notes" name="notes" rows="3"></textarea>
                        </div>

                        <button type="submit" class="btn btn-primary">Save Progress</button>
                    </form>
                </div>
            </div>

            <!-- Goals Summary -->
            <?php if ($goals): ?>
                <div class="card">
                    <div class="card-header">
                        <h2 class="card-title h4 mb-0">Fitness Goals</h2>
                    </div>
                    <div class="card-body">
                        <div class="list-group">
                            <?php foreach ($goals as $goal): ?>
                                <div class="list-group-item">
                                    <div class="d-flex w-100 justify-content-between">
                                        <h5 class="mb-1"><?php echo ucfirst(str_replace('_', ' ', $goal['goal_type'])); ?></h5>
                                        <small class="text-muted">
                                            Target: <?php echo $goal['target_date']; ?>
                                        </small>
                                    </div>
                                    <p class="mb-1">Target: <?php echo $goal['target_value']; ?></p>
                                    <?php if ($progress_data): ?>
                                        <?php
                                        $latest = $progress_data[0];
                                        $progress = 0;
                                        switch ($goal['goal_type']) {
                                            case 'weight_loss':
                                                $progress = (($latest['weight'] - $goal['target_value']) / $latest['weight']) * 100;
                                                break;
                                            case 'muscle_gain':
                                                // Calculate based on measurements
                                                break;
                                            case 'endurance':
                                                // Calculate based on workout data
                                                break;
                                            case 'flexibility':
                                                // Calculate based on flexibility tests
                                                break;
                                        }
                                        ?>
                                        <div class="progress mt-2">
                                            <div class="progress-bar" role="progressbar" 
                                                 style="width: <?php echo min(100, max(0, $progress)); ?>%">
                                                <?php echo round($progress); ?>%
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <!-- Progress Charts -->
        <div class="col-lg-8">
            <div class="card mb-4">
                <div class="card-body">
                    <h2 class="card-title h4">Progress Charts</h2>
                    <canvas id="progressChart"></canvas>
                </div>
            </div>

            <!-- Progress History -->
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title h4 mb-0">Progress History</h2>
                </div>
                <div class="card-body">
                    <?php if ($progress_data): ?>
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Weight</th>
                                        <th>Body Fat</th>
                                        <th>Chest</th>
                                        <th>Waist</th>
                                        <th>Hip</th>
                                        <th>Bicep</th>
                                        <th>Thigh</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($progress_data as $data): ?>
                                        <tr>
                                            <td><?php echo date('M d, Y', strtotime($data['date_recorded'])); ?></td>
                                            <td><?php echo $data['weight']; ?> kg</td>
                                            <td><?php echo $data['body_fat_percentage']; ?>%</td>
                                            <td><?php echo $data['chest_measurement']; ?> cm</td>
                                            <td><?php echo $data['waist_measurement']; ?> cm</td>
                                            <td><?php echo $data['hip_measurement']; ?> cm</td>
                                            <td><?php echo $data['bicep_measurement']; ?> cm</td>
                                            <td><?php echo $data['thigh_measurement']; ?> cm</td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <p class="text-muted">No progress data available. Start tracking your progress today!</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const ctx = document.getElementById('progressChart').getContext('2d');
    new Chart(ctx, {
        type: 'line',
        data: {
            labels: <?php echo json_encode(array_map(function($data) {
                return date('M d', strtotime($data['date_recorded']));
            }, $progress_data)); ?>,
            datasets: [{
                label: 'Weight (kg)',
                data: <?php echo json_encode(array_map(function($data) {
                    return $data['weight'];
                }, $progress_data)); ?>,
                borderColor: '#007bff',
                tension: 0.1,
                fill: false
            }, {
                label: 'Body Fat (%)',
                data: <?php echo json_encode(array_map(function($data) {
                    return $data['body_fat_percentage'];
                }, $progress_data)); ?>,
                borderColor: '#28a745',
                tension: 0.1,
                fill: false
            }, {
                label: 'Chest (cm)',
                data: <?php echo json_encode(array_map(function($data) {
                    return $data['chest_measurement'];
                }, $progress_data)); ?>,
                borderColor: '#dc3545',
                tension: 0.1,
                fill: false
            }, {
                label: 'Waist (cm)',
                data: <?php echo json_encode(array_map(function($data) {
                    return $data['waist_measurement'];
                }, $progress_data)); ?>,
                borderColor: '#ffc107',
                tension: 0.1,
                fill: false
            }]
        },
        options: {
            responsive: true,
            scales: {
                y: {
                    beginAtZero: false,
                    title: {
                        display: true,
                        text: 'Value'
                    }
                },
                x: {
                    title: {
                        display: true,
                        text: 'Date'
                    }
                }
            }
        }
    });
});
</script>