<?php
require_once 'includes/config.php';

// Fetch featured classes
try {
    $stmt = $pdo->query("
        SELECT c.*, t.first_name, t.last_name, 
               COUNT(b.booking_id) as booked_count
        FROM classes c
        LEFT JOIN users t ON c.trainer_id = t.user_id
        LEFT JOIN time_slots ts ON c.class_id = ts.class_id
        LEFT JOIN bookings b ON ts.time_slot_id = b.time_slot_id
        WHERE c.is_featured = 1
        GROUP BY c.class_id
        ORDER BY c.created_at DESC
        LIMIT 3
    ");
    $featured_classes = $stmt->fetchAll();
} catch (PDOException $e) {
    $featured_classes = [];
    error_log("Error fetching featured classes: " . $e->getMessage());
}

// Fetch latest blog posts
try {
    $stmt = $pdo->query("
        SELECT b.*, u.first_name, u.last_name
        FROM blog_posts b
        JOIN users u ON b.author_id = u.user_id
        WHERE b.status = 'published'
        ORDER BY b.created_at DESC
        LIMIT 3
    ");
    $blog_posts = $stmt->fetchAll();
} catch (PDOException $e) {
    $blog_posts = [];
    error_log("Error fetching blog posts: " . $e->getMessage());
}

// Fetch testimonials
try {
    $stmt = $pdo->query("
        SELECT t.*, u.first_name, u.last_name
        FROM testimonials t
        JOIN users u ON t.user_id = u.user_id
        WHERE t.status = 'approved'
        ORDER BY t.created_at DESC
        LIMIT 3
    ");
    $testimonials = $stmt->fetchAll();
} catch (PDOException $e) {
    $testimonials = [];
    error_log("Error fetching testimonials: " . $e->getMessage());
}

// Set page title
$page_title = "Pulse Fitness Hub - Your Path to Fitness";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #4e73df;
            --secondary-color: #1cc88a;
            --dark-color: #5a5c69;
            --light-color: #f8f9fc;
        }

        body {
            font-family: 'Nunito', sans-serif;
            background-color: var(--light-color);
        }

        .hero-section {
            position: relative;
            height: 100vh;
            background: linear-gradient(rgba(0, 0, 0, 0.5), rgba(0, 0, 0, 0.5)), url('assets/images/hero-bg.jpg');
            background-size: cover;
            background-position: center;
            display: flex;
            align-items: center;
            color: white;
        }

        .hero-content {
            position: relative;
            z-index: 2;
        }

        .hero-title {
            font-size: 3.5rem;
            font-weight: 700;
            margin-bottom: 1.5rem;
            text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.5);
        }

        .hero-subtitle {
            font-size: 1.5rem;
            margin-bottom: 2rem;
            text-shadow: 1px 1px 2px rgba(0, 0, 0, 0.5);
        }

        .stats-container {
            display: flex;
            justify-content: space-around;
            margin: 3rem 0;
            background: rgba(255, 255, 255, 0.1);
            padding: 2rem;
            border-radius: 15px;
            backdrop-filter: blur(5px);
        }

        .stat-item {
            text-align: center;
        }

        .stat-number {
            font-size: 2.5rem;
            font-weight: 700;
            color: var(--secondary-color);
            margin-bottom: 0.5rem;
        }

        .stat-label {
            font-size: 1.1rem;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .btn-custom {
            padding: 0.8rem 2rem;
            border-radius: 50px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
            transition: all 0.3s ease;
        }

        .btn-primary {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
        }

        .btn-primary:hover {
            background-color: #2e59d9;
            border-color: #2e59d9;
            transform: translateY(-2px);
        }

        .btn-outline-light {
            border: 2px solid white;
        }

        .btn-outline-light:hover {
            background-color: white;
            color: var(--primary-color);
            transform: translateY(-2px);
        }

        .featured-classes {
            padding: 5rem 0;
            background-color: white;
        }

        .section-title {
            text-align: center;
            margin-bottom: 3rem;
        }

        .section-title h2 {
            font-size: 2.5rem;
            font-weight: 700;
            color: var(--dark-color);
            margin-bottom: 1rem;
        }

        .section-title p {
            color: var(--dark-color);
            font-size: 1.1rem;
        }

        .class-card {
            background: white;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
            margin-bottom: 2rem;
        }

        .class-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 15px 30px rgba(0, 0, 0, 0.2);
        }

        .class-image {
            height: 200px;
            overflow: hidden;
        }

        .class-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.3s ease;
        }

        .class-card:hover .class-image img {
            transform: scale(1.1);
        }

        .class-info {
            padding: 1.5rem;
        }

        .class-title {
            font-size: 1.5rem;
            font-weight: 600;
            margin-bottom: 1rem;
            color: var(--dark-color);
        }

        .class-meta {
            display: flex;
            justify-content: space-between;
            margin-bottom: 1rem;
            color: var(--dark-color);
        }

        .class-meta span {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .class-description {
            color: var(--dark-color);
            margin-bottom: 1.5rem;
        }

        .testimonials {
            padding: 5rem 0;
            background: linear-gradient(rgba(78, 115, 223, 0.1), rgba(28, 200, 138, 0.1));
        }

        .testimonial-card {
            background: white;
            border-radius: 15px;
            padding: 2rem;
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
            margin-bottom: 2rem;
            transition: all 0.3s ease;
        }

        .testimonial-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 30px rgba(0, 0, 0, 0.2);
        }

        .testimonial-content {
            font-style: italic;
            color: var(--dark-color);
            margin-bottom: 1.5rem;
        }

        .testimonial-author {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .author-info h4 {
            margin: 0;
            color: var(--dark-color);
            font-weight: 600;
        }

        .author-info p {
            margin: 0;
            color: var(--dark-color);
            opacity: 0.8;
        }

        @media (max-width: 768px) {
            .hero-title {
                font-size: 2.5rem;
            }

            .hero-subtitle {
                font-size: 1.2rem;
            }

            .stats-container {
                flex-direction: column;
                gap: 1.5rem;
            }
        }

        .gym-details {
            background-color: #f8f9fc;
        }
        .gym-features i {
            font-size: 1.2rem;
        }
        .gym-photos img {
            height: 250px;
            object-fit: cover;
            transition: transform 0.3s ease;
        }
        .gym-photos img:hover {
            transform: scale(1.05);
        }
        .location-info i {
            width: 20px;
        }

        /* Gym Photos Section Styles */
        .gym-photos-section {
            background-color: #f8f9fc;
        }
        .gym-photo-card {
            position: relative;
            overflow: hidden;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            transition: transform 0.3s ease;
        }
        .gym-photo-card:hover {
            transform: translateY(-5px);
        }
        .gym-photo-card img {
            width: 100%;
            height: 300px;
            object-fit: cover;
            transition: transform 0.3s ease;
        }
        .gym-photo-card:hover img {
            transform: scale(1.05);
        }
        .photo-overlay {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            background: linear-gradient(to top, rgba(0,0,0,0.8), transparent);
            color: white;
            padding: 20px;
            opacity: 0;
            transition: opacity 0.3s ease;
        }
        .gym-photo-card:hover .photo-overlay {
            opacity: 1;
        }

        /* Footer Styles */
        .footer {
            background-color: #343a40;
        }
        .footer a {
            text-decoration: none;
            transition: color 0.3s ease;
        }
        .footer a:hover {
            color: var(--primary-color) !important;
        }
        .social-links a {
            display: inline-block;
            width: 35px;
            height: 35px;
            line-height: 35px;
            text-align: center;
            background: rgba(255,255,255,0.1);
            border-radius: 50%;
            transition: all 0.3s ease;
        }
        .social-links a:hover {
            background: var(--primary-color);
            transform: translateY(-3px);
        }
    </style>
</head>
<body>
    <?php include 'includes/header.php'; ?>

    <!-- Hero Section -->
    <section class="hero-section">
        <div class="container">
            <div class="hero-content text-center">
                <h1 class="hero-title">Transform Your Body, Transform Your Life</h1>
                <p class="hero-subtitle">Join our community of fitness enthusiasts and start your journey today</p>
                
                <div class="stats-container">
                    <div class="stat-item">
                        <div class="stat-number" id="memberCount">0</div>
                        <div class="stat-label">Active Members</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-number" id="classCount">0</div>
                        <div class="stat-label">Classes</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-number" id="trainerCount">0</div>
                        <div class="stat-label">Expert Trainers</div>
                    </div>
                </div>

                <?php if (!isset($_SESSION['user_id'])): ?>
                    <div class="mt-4">
                        <a href="register.php" class="btn btn-primary btn-custom me-3">
                            <i class="fas fa-user-plus me-2"></i>Join Now
                        </a>
                        <a href="login.php" class="btn btn-outline-light btn-custom">
                            <i class="fas fa-sign-in-alt me-2"></i>Login
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <!-- Gym Location and Details Section -->
    <section class="gym-details py-5">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-6">
                    <h2 class="mb-4">Our State-of-the-Art Facility</h2>
                    <p class="lead mb-4">Experience fitness like never before at our modern, well-equipped gym facility.</p>
                    
                    <div class="gym-features mb-4">
                        <div class="row">
                            <div class="col-6 mb-3">
                                <div class="d-flex align-items-center">
                                    <i class="fas fa-dumbbell text-primary me-2"></i>
                                    <span>Modern Equipment</span>
                                </div>
                            </div>
                            <div class="col-6 mb-3">
                                <div class="d-flex align-items-center">
                                    <i class="fas fa-shower text-primary me-2"></i>
                                    <span>Locker Rooms</span>
                                </div>
                            </div>
                            <div class="col-6 mb-3">
                                <div class="d-flex align-items-center">
                                    <i class="fas fa-parking text-primary me-2"></i>
                                    <span>Free Parking</span>
                                </div>
                            </div>
                            <div class="col-6 mb-3">
                                <div class="d-flex align-items-center">
                                    <i class="fas fa-wifi text-primary me-2"></i>
                                    <span>Free WiFi</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="location-info">
                        <h4 class="mb-3">Location</h4>
                        <p class="mb-2">
                            <i class="fas fa-map-marker-alt text-primary me-2"></i>
                            123 Fitness Street, Colombo 05, Sri Lanka
                        </p>
                        <p class="mb-2">
                            <i class="fas fa-phone text-primary me-2"></i>
                            +94 11 234 5678
                        </p>
                        <p class="mb-2">
                            <i class="fas fa-envelope text-primary me-2"></i>
                            info@pulsefitnesshub.com
                        </p>
                        <p class="mb-4">
                            <i class="fas fa-clock text-primary me-2"></i>
                            Open 7 days a week, 5:00 AM - 10:00 PM
                        </p>
                        <a href="https://maps.google.com" target="_blank" class="btn btn-outline-primary">
                            <i class="fas fa-map-marked-alt me-2"></i>View on Map
                        </a>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="gym-photos">
                        <div class="row g-3">
                            <div class="col-6">
                                <img src="assets/images/image1.jpg" alt="Gym Interior" class="img-fluid rounded shadow">
                            </div>
                            <div class="col-6">
                                <img src="assets/images/image2.jpg" alt="Gym Equipment" class="img-fluid rounded shadow">
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Featured Classes -->
    <section class="featured-classes">
        <div class="container">
            <div class="section-title">
                <h2>Featured Classes</h2>
                <p>Discover our most popular fitness classes</p>
            </div>
            <div class="row">
                <?php foreach ($featured_classes as $class): ?>
                    <div class="col-md-4">
                        <div class="class-card">
                            <div class="class-image">
                                <img src="uploads/<?php echo htmlspecialchars($class['image'] ?? 'default-class.jpg'); ?>" 
                                     alt="<?php echo htmlspecialchars($class['name']); ?>">
                            </div>
                            <div class="class-info">
                                <h3 class="class-title"><?php echo htmlspecialchars($class['name']); ?></h3>
                                <div class="class-meta">
                                    <span><i class="fas fa-user-tie"></i> <?php echo htmlspecialchars($class['first_name'] . ' ' . $class['last_name']); ?></span>
                                    <span><i class="fas fa-users"></i> <?php echo $class['booked_count']; ?> Booked</span>
                                </div>
                                <p class="class-description"><?php echo htmlspecialchars($class['description']); ?></p>
                                <a href="class_details.php?id=<?php echo $class['class_id']; ?>" class="btn btn-primary w-100">
                                    View Details
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- Testimonials -->
    <section class="testimonials">
        <div class="container">
            <div class="section-title">
                <h2>What Our Members Say</h2>
                <p>Real stories from our fitness community</p>
            </div>
            <div class="row">
                <?php foreach ($testimonials as $testimonial): ?>
                    <div class="col-md-4">
                        <div class="testimonial-card">
                            <div class="testimonial-content">
                                <p><?php echo htmlspecialchars($testimonial['content']); ?></p>
                            </div>
                            <div class="testim-estimonial-author">
                                <div class="author-info">
                                    <h4><?php echo htmlspecialchars($testimonial['first_name'] . ' ' . $testimonial['last_name']); ?></h4>
                                    <p><?php echo htmlspecialchars($testimonial['role']); ?></p>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- Enhanced Gym Photos Section -->
    <section class="gym-photos-section py-5 bg-light">
        <div class="container">
            <div class="section-title text-center mb-5">
                <h2>Our Gym Facility</h2>
                <p class="lead">Take a virtual tour of our state-of-the-art fitness center</p>
            </div>
            <div class="row g-4">
                <div class="col-md-6">
                    <div class="gym-photo-card">
                        <img src="assets/images/image1.jpg" alt="Gym Interior" class="img-fluid rounded shadow">
                        <div class="photo-overlay">
                            <h4>Main Workout Area</h4>
                            <p>Spacious and well-equipped training space</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="gym-photo-card">
                        <img src="assets/images/image2.jpg" alt="Gym Equipment" class="img-fluid rounded shadow">
                        <div class="photo-overlay">
                            <h4>Premium Equipment</h4>
                            <p>Latest fitness machines and free weights</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="gym-photo-card">
                        <img src="assets/images/image3.jpg" alt="Group Classes" class="img-fluid rounded shadow">
                        <div class="photo-overlay">
                            <h4>Group Classes</h4>
                            <p>Dynamic group training sessions</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="gym-photo-card">
                        <img src="assets/images/image4.jpg" alt="Personal Training" class="img-fluid rounded shadow">
                        <div class="photo-overlay">
                            <h4>Personal Training</h4>
                            <p>One-on-one expert guidance</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <script>
        // Animate statistics
        function animateValue(id, start, end, duration) {
            let current = start;
            const range = end - start;
            const increment = range / (duration / 16);
            const element = document.getElementById(id);
            
            const timer = setInterval(() => {
                current += increment;
                element.textContent = Math.floor(current);
                
                if (current >= end) {
                    clearInterval(timer);
                    element.textContent = end;
                }
            }, 16);
        }

        // Start animations when the page loads
        window.addEventListener('load', () => {
            animateValue('memberCount', 0, 500, 2000);
            animateValue('classCount', 0, 50, 2000);
            animateValue('trainerCount', 0, 20, 2000);
        });
    </script>

    <?php require_once 'includes/footer.php'; ?>
</body>
</html> 