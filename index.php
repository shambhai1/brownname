<?php
declare(strict_types=1);

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/site-data.php';

startUserSession();
$currentUser = getCurrentUser();
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo htmlspecialchars(APP_NAME, ENT_QUOTES, 'UTF-8'); ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;700;800&family=Space+Grotesk:wght@500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/styles.css">
</head>
<body>
    <header class="site-header">
        <div class="container nav-wrap">
            <a class="logo" href="index.php">
                <span class="logo-mark" aria-hidden="true"></span>
                <span>Online College Admission System</span>
            </a>
            <nav>
                <a href="index.php">Home</a>
                <a href="courses.php">Courses</a>
                <a href="apply.php">Apply</a>
                <a href="admin/login.php">Admin</a>
                <?php if ($currentUser !== null): ?>
                    <a href="dashboard.php">Dashboard</a>
                    <a href="logout.php">Logout</a>
                <?php else: ?>
                    <a class="nav-login" href="login.php">Login</a>
                    <a href="signup.php">Sign Up</a>
                <?php endif; ?>
            </nav>
        </div>
    </header>

    <main id="home">
        <section class="hero">
            <div class="container hero-grid">
                <div class="hero-panel">
                    <p class="tag">Admissions Open 2026-27</p>
                    <h1>Online College Admission System</h1>
                    <p class="sub">
                        Apply online in minutes, upload documents securely, and manage your admission status from one portal.
                    </p>
                    <div class="hero-actions">
                        <a class="btn-primary" href="apply.php">Register Now</a>
                        <a class="btn-outline" href="courses.php">Explore Courses</a>
                    </div>
                </div>
            </div>
        </section>

        <section class="courses container">
            <div class="page-tiles">
                <a class="page-tile" href="courses.php">
                    <h2>Courses</h2>
                    <p>Open the full course page and choose a program to view its details.</p>
                </a>
                <a class="page-tile" href="apply.php">
                    <h2>Application Form</h2>
                    <p>Open the admission page separately and complete the form without crowding the landing page.</p>
                </a>
            </div>
        </section>
    </main>

    <footer class="site-footer">
        <div class="container">
            <p>&copy; <?php echo date('Y'); ?> Online College Admission System. All rights reserved.</p>
        </div>
    </footer>

</body>
</html>
