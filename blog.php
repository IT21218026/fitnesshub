<?php
$page_title = "Blog - Pulse Fitness Hub";
require_once 'includes/config.php';

// Check if user is logged in
$user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
$success_message = '';
$error_message = '';

try {
    // Get filter parameters
    $category = isset($_GET['category']) ? $_GET['category'] : '';
    $search = isset($_GET['search']) ? $_GET['search'] : '';

    // Get all categories
    $stmt = $pdo->query("SELECT DISTINCT category FROM blog_posts WHERE status = 'published'");
    $categories = $stmt->fetchAll(PDO::FETCH_COLUMN);

    // Build query for blog posts
    $query = "
        SELECT bp.*, 
               u.first_name, u.last_name,
               COUNT(DISTINCT bc.id) as comment_count,
               COUNT(DISTINCT bl.id) as like_count,
               SUM(CASE WHEN bl.user_id = ? THEN 1 ELSE 0 END) as user_liked
        FROM blog_posts bp
        JOIN users u ON bp.author_id = u.id
        LEFT JOIN blog_comments bc ON bp.id = bc.post_id
        LEFT JOIN blog_likes bl ON bp.id = bl.post_id
        WHERE bp.status = 'published'
    ";

    $params = [$user_id];

    if ($category) {
        $query .= " AND bp.category = ?";
        $params[] = $category;
    }

    if ($search) {
        $query .= " AND (bp.title LIKE ? OR bp.content LIKE ?)";
        $search_param = "%$search%";
        $params[] = $search_param;
        $params[] = $search_param;
    }

    $query .= " GROUP BY bp.id ORDER BY bp.created_at DESC";

    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $posts = $stmt->fetchAll();

    // Handle comment submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['post_id']) && $user_id) {
        $post_id = $_POST['post_id'];
        $comment = trim($_POST['comment']);

        if (empty($comment)) {
            throw new Exception("Comment cannot be empty");
        }

        $stmt = $pdo->prepare("
            INSERT INTO blog_comments (post_id, user_id, content, created_at)
            VALUES (?, ?, ?, NOW())
        ");
        $stmt->execute([$post_id, $user_id, $comment]);

        // Create notification for post author
        $stmt = $pdo->prepare("
            INSERT INTO notifications (user_id, title, message, type)
            SELECT author_id, 'New Comment', ?, 'comment'
            FROM blog_posts WHERE id = ?
        ");
        $notification_message = "Someone commented on your post: " . substr($comment, 0, 50) . "...";
        $stmt->execute([$notification_message, $post_id]);

        $success_message = "Comment added successfully";
    }

    // Handle like/unlike
    if (isset($_GET['like_post']) && $user_id) {
        $post_id = $_GET['like_post'];
        
        // Check if user already liked the post
        $stmt = $pdo->prepare("
            SELECT COUNT(*) FROM blog_likes 
            WHERE post_id = ? AND user_id = ?
        ");
        $stmt->execute([$post_id, $user_id]);
        
        if ($stmt->fetchColumn() > 0) {
            // Unlike
            $stmt = $pdo->prepare("
                DELETE FROM blog_likes 
                WHERE post_id = ? AND user_id = ?
            ");
            $stmt->execute([$post_id, $user_id]);
        } else {
            // Like
            $stmt = $pdo->prepare("
                INSERT INTO blog_likes (post_id, user_id, created_at)
                VALUES (?, ?, NOW())
            ");
            $stmt->execute([$post_id, $user_id]);

            // Create notification for post author
            $stmt = $pdo->prepare("
                INSERT INTO notifications (user_id, title, message, type)
                SELECT author_id, 'New Like', 'Someone liked your post', 'like'
                FROM blog_posts WHERE id = ?
            ");
            $stmt->execute([$post_id]);
        }

        // Redirect to prevent form resubmission
        header("Location: blog.php" . ($category ? "?category=$category" : ""));
        exit();
    }

} catch (Exception $e) {
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

    <!-- Blog Header -->
    <div class="row mb-4">
        <div class="col-md-8">
            <h1>Fitness Blog</h1>
            <p class="text-muted">Read the latest fitness tips, success stories, and health advice from our experts.</p>
        </div>
        <div class="col-md-4">
            <form method="GET" class="d-flex">
                <input type="text" name="search" class="form-control me-2" 
                       placeholder="Search posts..." value="<?php echo htmlspecialchars($search); ?>">
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-search"></i>
                </button>
            </form>
        </div>
    </div>

    <div class="row">
        <!-- Main Content -->
        <div class="col-lg-8">
            <?php if (empty($posts)): ?>
                <div class="alert alert-info">
                    No blog posts found. Please try different search criteria.
                </div>
            <?php else: ?>
                <?php foreach ($posts as $post): ?>
                    <div class="card mb-4">
                        <?php if ($post['featured_image']): ?>
                            <img src="<?php echo htmlspecialchars($post['featured_image']); ?>" 
                                 class="card-img-top" alt="<?php echo htmlspecialchars($post['title']); ?>">
                        <?php endif; ?>
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span class="badge bg-primary"><?php echo htmlspecialchars($post['category']); ?></span>
                                <small class="text-muted">
                                    <?php echo date('F j, Y', strtotime($post['created_at'])); ?>
                                </small>
                            </div>
                            <h2 class="card-title h4">
                                <?php echo htmlspecialchars($post['title']); ?>
                            </h2>
                            <p class="card-text">
                                <?php echo nl2br(htmlspecialchars($post['content'])); ?>
                            </p>
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <span class="text-muted">
                                        <i class="bi bi-person"></i>
                                        <?php echo htmlspecialchars($post['first_name'] . ' ' . $post['last_name']); ?>
                                    </span>
                                </div>
                                <div class="d-flex gap-3">
                                    <a href="?like_post=<?php echo $post['id']; ?><?php echo $category ? "&category=$category" : ''; ?>" 
                                       class="text-decoration-none">
                                        <i class="bi <?php echo $post['user_liked'] ? 'bi-heart-fill text-danger' : 'bi-heart'; ?>"></i>
                                        <span class="ms-1"><?php echo $post['like_count']; ?></span>
                                    </a>
                                    <span class="text-muted">
                                        <i class="bi bi-chat"></i>
                                        <?php echo $post['comment_count']; ?> comments
                                    </span>
                                </div>
                            </div>
                        </div>
                        <div class="card-footer">
                            <form method="POST" class="mt-3">
                                <input type="hidden" name="post_id" value="<?php echo $post['id']; ?>">
                                <div class="input-group">
                                    <input type="text" name="comment" class="form-control" 
                                           placeholder="Write a comment..." required>
                                    <button type="submit" class="btn btn-primary" <?php echo $user_id ? '' : 'disabled'; ?>>
                                        Comment
                                    </button>
                                </div>
                            </form>
                            <?php if (!$user_id): ?>
                                <small class="text-muted">
                                    <a href="login.php">Login</a> to comment
                                </small>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- Sidebar -->
        <div class="col-lg-4">
            <!-- Categories -->
            <div class="card mb-4">
                <div class="card-header">
                    <h3 class="card-title h5 mb-0">Categories</h3>
                </div>
                <div class="list-group list-group-flush">
                    <a href="blog.php" 
                       class="list-group-item list-group-item-action <?php echo !$category ? 'active' : ''; ?>">
                        All Categories
                    </a>
                    <?php foreach ($categories as $cat): ?>
                        <a href="?category=<?php echo urlencode($cat); ?>" 
                           class="list-group-item list-group-item-action <?php echo $category === $cat ? 'active' : ''; ?>">
                            <?php echo htmlspecialchars($cat); ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Recent Posts -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title h5 mb-0">Recent Posts</h3>
                </div>
                <div class="list-group list-group-flush">
                    <?php foreach (array_slice($posts, 0, 5) as $recent): ?>
                        <a href="#post-<?php echo $recent['id']; ?>" class="list-group-item list-group-item-action">
                            <div class="d-flex w-100 justify-content-between">
                                <h6 class="mb-1"><?php echo htmlspecialchars($recent['title']); ?></h6>
                                <small class="text-muted">
                                    <?php echo date('M d', strtotime($recent['created_at'])); ?>
                                </small>
                            </div>
                            <small class="text-muted">
                                <?php echo htmlspecialchars($recent['first_name'] . ' ' . $recent['last_name']); ?>
                            </small>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?> 