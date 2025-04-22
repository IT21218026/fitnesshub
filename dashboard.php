<?php
$page_title = "Dashboard - Pulse Fitness Hub";
require_once 'includes/config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Get user data
$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];

try {
    // Get user details
    $stmt = $pdo->prepare("SELECT * FROM users WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();

    // Get user profile
    $stmt = $pdo->prepare("SELECT * FROM user_profiles WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $profile = $stmt->fetch();

    // Get upcoming bookings
    $stmt = $pdo->prepare("
        SELECT b.*, ts.start_time, ts.end_time, c.name as class_name, 
               CONCAT(u.first_name, ' ', u.last_name) as trainer_name
        FROM bookings b
        JOIN time_slots ts ON b.slot_id = ts.slot_id
        JOIN classes c ON ts.class_id = c.class_id
        JOIN trainer_profiles tp ON ts.trainer_id = tp.trainer_id
        JOIN users u ON tp.user_id = u.user_id
        WHERE b.user_id = ? AND ts.start_time >= NOW()
        ORDER BY ts.start_time ASC
        LIMIT 5
    ");
    $stmt->execute([$user_id]);
    $upcoming_bookings = $stmt->fetchAll();

    // Get active membership
    $stmt = $pdo->prepare("
        SELECT m.*, um.start_date, um.end_date, um.status
        FROM user_memberships um
        JOIN memberships m ON um.membership_id = m.membership_id
        WHERE um.user_id = ? AND um.status = 'active' AND um.end_date >= CURDATE()
        ORDER BY um.end_date DESC
        LIMIT 1
    ");
    $stmt->execute([$user_id]);
    $membership = $stmt->fetch();

    // Get payment history
    $stmt = $pdo->prepare("
        SELECT * FROM payments 
        WHERE user_id = ? 
        ORDER BY payment_date DESC 
        LIMIT 5
    ");
    $stmt->execute([$user_id]);
    $payments = $stmt->fetchAll();

    // Get progress data for chart
    $stmt = $pdo->prepare("
        SELECT date_recorded, weight, body_fat_percentage
        FROM progress_tracking
        WHERE user_id = ?
        ORDER BY date_recorded ASC
        LIMIT 10
    ");
    $stmt->execute([$user_id]);
    $progress_data = $stmt->fetchAll();

    // Get recent notifications
    $stmt = $pdo->prepare("
        SELECT * FROM notifications 
        WHERE user_id = ? 
        ORDER BY created_at DESC 
        LIMIT 5
    ");
    $stmt->execute([$user_id]);
    $notifications = $stmt->fetchAll();

} catch(PDOException $e) {
    die("Error: " . $e->getMessage());
}

require_once 'includes/header.php';
?>

<!-- Modern Dashboard Header -->
<div class="dashboard-header bg-gradient-primary text-white py-4 mb-4">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-lg-8">
                <h1 class="display-4 mb-2">Welcome back, <?php echo htmlspecialchars($user['first_name']); ?>! 👋</h1>
                <p class="lead opacity-75 mb-0">Track your fitness journey and achieve your goals</p>
            </div>
            <div class="col-lg-4 text-lg-end mt-3 mt-lg-0">
                <a href="profile.php" class="btn btn-light btn-lg rounded-pill shadow-sm">
                    <i class="fas fa-user-edit me-2"></i>Update Profile
                </a>
            </div>
        </div>
    </div>
</div>

<div class="container">
    <div class="row g-4">
        <!-- Main Content Area -->
        <div class="col-lg-8">
            <!-- Stats Cards -->
            <div class="row g-4 mb-4">
                <div class="col-md-4">
                    <div class="card stat-card h-100 border-0 shadow-sm">
                        <div class="card-body">
                            <div class="d-flex align-items-center mb-3">
                                <div class="stat-icon bg-primary-subtle rounded-circle p-3 me-3">
                                    <i class="fas fa-calendar-check fa-lg text-primary"></i>
                                </div>
                                <div>
                                    <h6 class="card-subtitle text-muted">Upcoming</h6>
                                    <h2 class="card-title mb-0"><?php echo count($upcoming_bookings); ?></h2>
                                </div>
                            </div>
                            <p class="card-text text-muted">Classes scheduled</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card stat-card h-100 border-0 shadow-sm">
                        <div class="card-body">
                            <div class="d-flex align-items-center mb-3">
                                <div class="stat-icon bg-success-subtle rounded-circle p-3 me-3">
                                    <i class="fas fa-dumbbell fa-lg text-success"></i>
                                </div>
                                <div>
                                    <h6 class="card-subtitle text-muted">Current</h6>
                                    <h2 class="card-title mb-0"><?php echo end($progress_data) ? end($progress_data)['weight'] : 'N/A'; ?></h2>
                                </div>
                            </div>
                            <p class="card-text text-muted">Weight (kg)</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card stat-card h-100 border-0 shadow-sm">
                        <div class="card-body">
                            <div class="d-flex align-items-center mb-3">
                                <div class="stat-icon bg-info-subtle rounded-circle p-3 me-3">
                                    <i class="fas fa-trophy fa-lg text-info"></i>
                                </div>
                                <div>
                                    <h6 class="card-subtitle text-muted">Completed</h6>
                                    <h2 class="card-title mb-0">
                                        <?php
                                        $stmt = $pdo->prepare("SELECT COUNT(*) FROM bookings WHERE user_id = ? AND status = 'completed'");
                                        $stmt->execute([$user_id]);
                                        echo $stmt->fetchColumn();
                                        ?>
                                    </h2>
                                </div>
                            </div>
                            <p class="card-text text-muted">Total classes</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Progress Chart -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h2 class="card-title h4 mb-0">Your Progress</h2>
                        <div class="btn-group">
                            <button type="button" class="btn btn-outline-primary btn-sm active">Weight</button>
                            <button type="button" class="btn btn-outline-primary btn-sm">Body Fat</button>
                        </div>
                    </div>
                    <canvas id="progressChart" height="300"></canvas>
                </div>
            </div>

            <!-- Upcoming Classes -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h2 class="card-title h4 mb-0">Upcoming Classes</h2>
                        <a href="classes.php" class="btn btn-primary rounded-pill">
                            <i class="fas fa-plus me-2"></i>Book New Class
                        </a>
                    </div>
                    <?php if ($upcoming_bookings): ?>
                        <div class="upcoming-classes">
                            <?php foreach ($upcoming_bookings as $booking): ?>
                                <div class="class-card p-3 mb-3 bg-light rounded-3">
                                    <div class="row align-items-center">
                                        <div class="col-auto">
                                            <div class="class-icon bg-primary text-white rounded-circle p-3">
                                                <i class="fas fa-running fa-lg"></i>
                                            </div>
                                        </div>
                                        <div class="col">
                                            <h5 class="mb-1"><?php echo htmlspecialchars($booking['class_name']); ?></h5>
                                            <p class="mb-0 text-muted">
                                                <i class="fas fa-user me-2"></i><?php echo htmlspecialchars($booking['trainer_name']); ?>
                                            </p>
                                        </div>
                                        <div class="col-auto text-end">
                                            <div class="text-primary mb-1">
                                                <i class="fas fa-clock me-1"></i>
                                                <?php echo date('g:i A', strtotime($booking['start_time'])); ?>
                                            </div>
                                            <div class="text-muted small">
                                                <?php echo date('M d, Y', strtotime($booking['start_time'])); ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-5">
                            <img src="assets/images/empty-calendar.svg" alt="No Classes" class="mb-3" width="150">
                            <h5>No Upcoming Classes</h5>
                            <p class="text-muted">Book your first class to start your fitness journey!</p>
                            <a href="classes.php" class="btn btn-primary rounded-pill">Browse Classes</a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Sidebar -->
        <div class="col-lg-4">
            <!-- Profile Card -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body text-center">
                    <div class="position-relative d-inline-block mb-3">
                        <img src="<?php echo $user['profile_picture'] ?? 'assets/images/default-avatar.jpg'; ?>" 
                             alt="Profile Picture" 
                             class="rounded-circle border border-4 border-white shadow"
                             width="120" height="120"
                             style="object-fit: cover;">
                        <a href="profile.php" class="position-absolute bottom-0 end-0 bg-primary text-white rounded-circle p-2">
                            <i class="fas fa-camera"></i>
                        </a>
                    </div>
                    <h3 class="h5 mb-2"><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></h3>
                    <p class="text-muted mb-3">
                        <i class="fas fa-clock me-1"></i>
                        Member since <?php echo date('F Y', strtotime($user['created_at'])); ?>
                    </p>
                    <div class="d-grid gap-2">
                        <a href="profile.php" class="btn btn-outline-primary rounded-pill">
                            <i class="fas fa-user-edit me-2"></i>Edit Profile
                        </a>
                        <a href="progress.php" class="btn btn-outline-success rounded-pill">
                            <i class="fas fa-chart-line me-2"></i>View Progress
                        </a>
                    </div>
                </div>
            </div>

            <!-- Membership Card -->
            <?php if ($membership): ?>
            <div class="card border-0 shadow-sm membership-card bg-gradient-primary text-white mb-4">
                <div class="card-body">
                    <div class="d-flex align-items-center mb-3">
                        <div class="membership-icon rounded-circle bg-white p-3 me-3">
                            <i class="fas fa-crown fa-lg text-primary"></i>
                        </div>
                        <div>
                            <h3 class="h5 mb-1">Active Membership</h3>
                            <p class="mb-0 opacity-75"><?php echo htmlspecialchars($membership['name']); ?></p>
                        </div>
                    </div>
                    <div class="membership-details p-3 bg-white bg-opacity-10 rounded-3 mb-3">
                        <div class="row g-3">
                            <div class="col-6">
                                <div class="small opacity-75">Start Date</div>
                                <div class="fw-bold"><?php echo date('M d, Y', strtotime($membership['start_date'])); ?></div>
                            </div>
                            <div class="col-6">
                                <div class="small opacity-75">End Date</div>
                                <div class="fw-bold"><?php echo date('M d, Y', strtotime($membership['end_date'])); ?></div>
                            </div>
                        </div>
                    </div>
                    <div class="text-center">
                        <a href="membership.php" class="btn btn-light rounded-pill w-100">
                            <i class="fas fa-arrow-right me-2"></i>View Details
                        </a>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Recent Activity -->
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <h3 class="h5 mb-4">Recent Activity</h3>
                    <?php if ($notifications): ?>
                        <div class="activity-timeline">
                            <?php foreach ($notifications as $notification): ?>
                                <div class="activity-item d-flex pb-3 mb-3 border-bottom">
                                    <div class="activity-icon rounded-circle p-2 me-3
                                        <?php
                                        echo $notification['type'] === 'booking' ? 'bg-primary-subtle text-primary' :
                                            ($notification['type'] === 'payment' ? 'bg-success-subtle text-success' :
                                            'bg-info-subtle text-info');
                                        ?>">
                                        <i class="fas <?php
                                        echo $notification['type'] === 'booking' ? 'fa-calendar-check' :
                                            ($notification['type'] === 'payment' ? 'fa-credit-card' :
                                            'fa-bell');
                                        ?>"></i>
                                    </div>
                                    <div>
                                        <p class="mb-1"><?php echo htmlspecialchars($notification['message']); ?></p>
                                        <small class="text-muted">
                                            <i class="fas fa-clock me-1"></i>
                                            <?php echo date('M d, g:i A', strtotime($notification['created_at'])); ?>
                                        </small>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p class="text-muted text-center mb-0">No recent activity</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
/* Custom CSS for Dashboard */
.dashboard-header {
    background: linear-gradient(135deg, #4e54c8, #8f94fb);
    margin-top: -1.5rem;
    margin-bottom: 2rem;
    padding: 3rem 0;
}

.stat-card {
    transition: transform 0.2s;
}

.stat-card:hover {
    transform: translateY(-5px);
}

.stat-icon {
    width: 48px;
    height: 48px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.membership-card {
    background: linear-gradient(135deg, #4e54c8, #8f94fb);
    overflow: hidden;
}

.membership-icon {
    width: 48px;
    height: 48px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.activity-icon {
    width: 40px;
    height: 40px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.class-icon {
    width: 48px;
    height: 48px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.upcoming-classes .class-card {
    transition: transform 0.2s;
}

.upcoming-classes .class-card:hover {
    transform: translateX(5px);
}

/* Progress Chart Customization */
#progressChart {
    max-height: 300px;
}

/* Responsive Adjustments */
@media (max-width: 768px) {
    .dashboard-header {
        padding: 2rem 0;
    }
    
    .display-4 {
        font-size: 2rem;
    }
}
</style>

<script>
// Initialize Progress Chart
document.addEventListener('DOMContentLoaded', function() {
    const ctx = document.getElementById('progressChart').getContext('2d');
    const progressChart = new Chart(ctx, {
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
                borderColor: '#4e54c8',
                backgroundColor: 'rgba(78, 84, 200, 0.1)',
                tension: 0.4,
                fill: true
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false
                }
            },
            scales: {
                y: {
                    beginAtZero: false,
                    grid: {
                        display: true,
                        drawBorder: false
                    }
                },
                x: {
                    grid: {
                        display: false
                    }
                }
            }
        }
    });
});
</script>