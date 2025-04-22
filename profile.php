<?php
$page_title = "Edit Profile - Pulse Fitness Hub";
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
    // Get user details
    $stmt = $pdo->prepare("SELECT * FROM users WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();

    // Get user profile
    $stmt = $pdo->prepare("SELECT * FROM user_profiles WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $profile = $stmt->fetch();

    // Handle form submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Start transaction
        $pdo->beginTransaction();

        try {
            // Update user details (basic info)
            $stmt = $pdo->prepare("
                UPDATE users 
                SET first_name = ?, last_name = ?, email = ?, phone = ?
                WHERE user_id = ?
            ");
            $stmt->execute([
                $_POST['first_name'],
                $_POST['last_name'],
                $_POST['email'],
                $_POST['phone'] ?? null,
                $user_id
            ]);

            // Update or insert profile (detailed info)
            if ($profile) {
                $stmt = $pdo->prepare("
                    UPDATE user_profiles 
                    SET height = ?, weight = ?, fitness_goals = ?, 
                        medical_conditions = ?, emergency_contact = ?
                    WHERE user_id = ?
                ");
                $stmt->execute([
                    $_POST['height'] ?? null,
                    $_POST['weight'] ?? null,
                    $_POST['fitness_goals'] ?? null,
                    $_POST['medical_conditions'] ?? null,
                    $_POST['emergency_contact'] ?? null,
                    $user_id
                ]);
            } else {
                $stmt = $pdo->prepare("
                    INSERT INTO user_profiles 
                    (user_id, height, weight, fitness_goals, medical_conditions, emergency_contact)
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $user_id,
                    $_POST['height'] ?? null,
                    $_POST['weight'] ?? null,
                    $_POST['fitness_goals'] ?? null,
                    $_POST['medical_conditions'] ?? null,
                    $_POST['emergency_contact'] ?? null
                ]);
            }

            // Handle profile picture upload
            if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === 0) {
                $allowed = ['jpg', 'jpeg', 'png', 'gif'];
                $filename = $_FILES['profile_picture']['name'];
                $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

                if (in_array($ext, $allowed)) {
                    $upload_dir = 'assets/uploads/profiles/';
                    if (!file_exists($upload_dir)) {
                        mkdir($upload_dir, 0777, true);
                    }
                    
                    $new_filename = 'profile_' . $user_id . '_' . time() . '.' . $ext;
                    $upload_path = $upload_dir . $new_filename;

                    if (move_uploaded_file($_FILES['profile_picture']['tmp_name'], $upload_path)) {
                        // Delete old profile picture if exists
                        if (!empty($user['profile_picture']) && file_exists($user['profile_picture'])) {
                            unlink($user['profile_picture']);
                        }
                        
                        // Update profile picture in database
                        $stmt = $pdo->prepare("UPDATE users SET profile_picture = ? WHERE user_id = ?");
                        $stmt->execute([$upload_path, $user_id]);
                    }
                }
            }

            $pdo->commit();
            $success_message = "Profile updated successfully!";

            // Refresh user data
            $stmt = $pdo->prepare("SELECT * FROM users WHERE user_id = ?");
            $stmt->execute([$user_id]);
            $user = $stmt->fetch();

            $stmt = $pdo->prepare("SELECT * FROM user_profiles WHERE user_id = ?");
            $stmt->execute([$user_id]);
            $profile = $stmt->fetch();

        } catch (Exception $e) {
            $pdo->rollBack();
            $error_message = "Error updating profile: " . $e->getMessage();
        }
    }

} catch(PDOException $e) {
    $error_message = "Error: " . $e->getMessage();
}

require_once 'includes/header.php';
?>

<div class="container mt-4">
    <div class="row">
        <div class="col-lg-8 mx-auto">
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title h4 mb-0">Edit Profile</h2>
                </div>
                <div class="card-body">
                    <?php if ($success_message): ?>
                        <div class="alert alert-success"><?php echo htmlspecialchars($success_message); ?></div>
                    <?php endif; ?>
                    <?php if ($error_message): ?>
                        <div class="alert alert-danger"><?php echo htmlspecialchars($error_message); ?></div>
                    <?php endif; ?>

                    <form method="POST" enctype="multipart/form-data">
                        <!-- Profile Picture -->
                        <div class="text-center mb-4">
                            <img src="<?php echo !empty($user['profile_picture']) ? htmlspecialchars($user['profile_picture']) : 'assets/images/default-avatar.jpg'; ?>" 
                                 alt="Profile Picture" 
                                 class="rounded-circle mb-3" 
                                 width="150">
                            <div class="mb-3">
                                <label for="profile_picture" class="form-label">Change Profile Picture</label>
                                <input type="file" class="form-control" id="profile_picture" name="profile_picture" accept="image/*">
                            </div>
                        </div>

                        <!-- Personal Information -->
                        <h3 class="h5 mb-3">Personal Information</h3>
                        <div class="row g-3 mb-4">
                            <div class="col-md-6">
                                <label for="first_name" class="form-label">First Name</label>
                                <input type="text" class="form-control" id="first_name" name="first_name" 
                                       value="<?php echo !empty($user['first_name']) ? htmlspecialchars($user['first_name']) : ''; ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label for="last_name" class="form-label">Last Name</label>
                                <input type="text" class="form-control" id="last_name" name="last_name" 
                                       value="<?php echo !empty($user['last_name']) ? htmlspecialchars($user['last_name']) : ''; ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label for="email" class="form-label">Email</label>
                                <input type="email" class="form-control" id="email" name="email" 
                                       value="<?php echo !empty($user['email']) ? htmlspecialchars($user['email']) : ''; ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label for="phone" class="form-label">Phone</label>
                                <input type="tel" class="form-control" id="phone" name="phone" 
                                       value="<?php echo !empty($user['phone']) ? htmlspecialchars($user['phone']) : ''; ?>">
                            </div>
                        </div>

                        <!-- Fitness Profile -->
                        <h3 class="h5 mb-3">Fitness Profile</h3>
                        <div class="row g-3 mb-4">
                            <div class="col-md-6">
                                <label for="height" class="form-label">Height (cm)</label>
                                <input type="number" class="form-control" id="height" name="height" 
                                       value="<?php echo !empty($profile['height']) ? htmlspecialchars($profile['height']) : ''; ?>">
                            </div>
                            <div class="col-md-6">
                                <label for="weight" class="form-label">Weight (kg)</label>
                                <input type="number" class="form-control" id="weight" name="weight" 
                                       value="<?php echo !empty($profile['weight']) ? htmlspecialchars($profile['weight']) : ''; ?>">
                            </div>
                            <div class="col-12">
                                <label for="fitness_goals" class="form-label">Fitness Goals</label>
                                <textarea class="form-control" id="fitness_goals" name="fitness_goals" rows="3"><?php echo !empty($profile['fitness_goals']) ? htmlspecialchars($profile['fitness_goals']) : ''; ?></textarea>
                            </div>
                        </div>

                        <!-- Health Information -->
                        <h3 class="h5 mb-3">Health Information</h3>
                        <div class="row g-3 mb-4">
                            <div class="col-12">
                                <label for="medical_conditions" class="form-label">Medical Conditions</label>
                                <textarea class="form-control" id="medical_conditions" name="medical_conditions" rows="3"><?php echo !empty($profile['medical_conditions']) ? htmlspecialchars($profile['medical_conditions']) : ''; ?></textarea>
                            </div>
                            <div class="col-12">
                                <label for="emergency_contact" class="form-label">Emergency Contact</label>
                                <input type="text" class="form-control" id="emergency_contact" name="emergency_contact" 
                                       value="<?php echo !empty($profile['emergency_contact']) ? htmlspecialchars($profile['emergency_contact']) : ''; ?>">
                            </div>
                        </div>

                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-primary">Save Changes</button>
                            <a href="dashboard.php" class="btn btn-outline-secondary">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?> 
                        <!-- Fitness Goals -->
                        <h3 class="h5 mb-3">Fitness Goals</h3>
                        <div id="goals-container">
                            <?php foreach ($goals as $index => $goal): ?>
                                <div class="goal-item row g-3 mb-3">
                                    <div class="col-md-4">
                                        <label class="form-label">Goal Type</label>
                                        <select class="form-select" name="goals[<?php echo $index; ?>][type]">
                                            <option value="weight_loss" <?php echo $goal['goal_type'] === 'weight_loss' ? 'selected' : ''; ?>>Weight Loss</option>
                                            <option value="muscle_gain" <?php echo $goal['goal_type'] === 'muscle_gain' ? 'selected' : ''; ?>>Muscle Gain</option>
                                            <option value="endurance" <?php echo $goal['goal_type'] === 'endurance' ? 'selected' : ''; ?>>Endurance</option>
                                            <option value="flexibility" <?php echo $goal['goal_type'] === 'flexibility' ? 'selected' : ''; ?>>Flexibility</option>
                                        </select>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label">Target</label>
                                        <input type="text" class="form-control" name="goals[<?php echo $index; ?>][target]" 
                                               value="<?php echo htmlspecialchars($goal['target_value']); ?>">
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label">Target Date</label>
                                        <input type="date" class="form-control" name="goals[<?php echo $index; ?>][date]" 
                                               value="<?php echo $goal['target_date']; ?>">
                                    </div>
                                    <div class="col-md-2 d-flex align-items-end">
                                        <button type="button" class="btn btn-danger remove-goal">Remove</button>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <button type="button" class="btn btn-secondary mb-4" id="add-goal">Add Goal</button>

                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-primary">Save Changes</button>
                            <a href="dashboard.php" class="btn btn-outline-secondary">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Add new goal
    document.getElementById('add-goal').addEventListener('click', function() {
        const container = document.getElementById('goals-container');
        const index = container.children.length;
        const goalHtml = `
            <div class="goal-item row g-3 mb-3">
                <div class="col-md-4">
                    <label class="form-label">Goal Type</label>
                    <select class="form-select" name="goals[${index}][type]">
                        <option value="weight_loss">Weight Loss</option>
                        <option value="muscle_gain">Muscle Gain</option>
                        <option value="endurance">Endurance</option>
                        <option value="flexibility">Flexibility</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Target</label>
                    <input type="text" class="form-control" name="goals[${index}][target]">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Target Date</label>
                    <input type="date" class="form-control" name="goals[${index}][date]">
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button type="button" class="btn btn-danger remove-goal">Remove</button>
                </div>
            </div>
        `;
        container.insertAdjacentHTML('beforeend', goalHtml);
    });

    // Remove goal
    document.addEventListener('click', function(e) {
        if (e.target.classList.contains('remove-goal')) {
            e.target.closest('.goal-item').remove();
        }
    });
});
</script>

<?php require_once 'includes/footer.php'; ?> 