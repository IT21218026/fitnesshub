<?php
$page_title = "Notifications - Pulse Fitness Hub";
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
    // Handle notification actions
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (isset($_POST['mark_all_read'])) {
            $stmt = $pdo->prepare("
                UPDATE notifications 
                SET is_read = 1 
                WHERE user_id = ? AND is_read = 0
            ");
            $stmt->execute([$user_id]);
            $success_message = "All notifications marked as read";

        } elseif (isset($_POST['delete_all_read'])) {
            $stmt = $pdo->prepare("
                DELETE FROM notifications 
                WHERE user_id = ? AND is_read = 1
            ");
            $stmt->execute([$user_id]);
            $success_message = "All read notifications deleted";

        } elseif (isset($_POST['delete_notification'])) {
            $notification_id = $_POST['notification_id'];
            $stmt = $pdo->prepare("
                DELETE FROM notifications 
                WHERE id = ? AND user_id = ?
            ");
            $stmt->execute([$notification_id, $user_id]);
            $success_message = "Notification deleted";
        }
    }

    // Get all notifications
    $stmt = $pdo->prepare("
        SELECT n.*, 
               CASE 
                   WHEN n.type = 'booking' THEN 'bi-calendar-check'
                   WHEN n.type = 'payment' THEN 'bi-credit-card'
                   WHEN n.type = 'class' THEN 'bi-people'
                   WHEN n.type = 'membership' THEN 'bi-card-text'
                   ELSE 'bi-bell'
               END as icon_class
        FROM notifications n
        WHERE n.user_id = ?
        ORDER BY n.created_at DESC
    ");
    $stmt->execute([$user_id]);
    $notifications = $stmt->fetchAll();

    // Count unread notifications
    $stmt = $pdo->prepare("
        SELECT COUNT(*) 
        FROM notifications 
        WHERE user_id = ? AND is_read = 0
    ");
    $stmt->execute([$user_id]);
    $unread_count = $stmt->fetchColumn();

} catch (PDOException $e) {
    $error_message = "Error: " . $e->getMessage();
}

require_once 'includes/header.php';
?>

<div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1>Notifications</h1>
        <?php if ($unread_count > 0): ?>
            <span class="badge bg-primary"><?php echo $unread_count; ?> unread</span>
        <?php endif; ?>
    </div>

    <?php if ($success_message): ?>
        <div class="alert alert-success"><?php echo $success_message; ?></div>
    <?php endif; ?>
    <?php if ($error_message): ?>
        <div class="alert alert-danger"><?php echo $error_message; ?></div>
    <?php endif; ?>

    <!-- Notification Actions -->
    <div class="card mb-4">
        <div class="card-body">
            <div class="d-flex gap-2">
                <form method="POST" class="d-inline">
                    <button type="submit" name="mark_all_read" class="btn btn-outline-primary">
                        <i class="bi bi-check-all"></i> Mark All as Read
                    </button>
                </form>
                <form method="POST" class="d-inline">
                    <button type="submit" name="delete_all_read" class="btn btn-outline-danger">
                        <i class="bi bi-trash"></i> Delete All Read
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- Notifications List -->
    <div class="card">
        <div class="card-body">
            <?php if (empty($notifications)): ?>
                <div class="text-center py-4">
                    <i class="bi bi-bell-slash display-1 text-muted"></i>
                    <h3 class="mt-3">No Notifications</h3>
                    <p class="text-muted">You don't have any notifications yet.</p>
                </div>
            <?php else: ?>
                <div class="list-group">
                    <?php foreach ($notifications as $notification): ?>
                        <div class="list-group-item <?php echo $notification['is_read'] ? '' : 'list-group-item-primary'; ?>">
                            <div class="d-flex w-100 justify-content-between align-items-center">
                                <div class="d-flex align-items-center">
                                    <i class="bi <?php echo $notification['icon_class']; ?> fs-4 me-3"></i>
                                    <div>
                                        <h5 class="mb-1"><?php echo htmlspecialchars($notification['title']); ?></h5>
                                        <p class="mb-1"><?php echo htmlspecialchars($notification['message']); ?></p>
                                        <small class="text-muted">
                                            <?php echo date('F j, Y g:i A', strtotime($notification['created_at'])); ?>
                                        </small>
                                    </div>
                                </div>
                                <form method="POST" class="d-inline">
                                    <input type="hidden" name="notification_id" value="<?php echo $notification['id']; ?>">
                                    <button type="submit" name="delete_notification" class="btn btn-sm btn-outline-danger">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?> 