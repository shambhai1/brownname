<?php
declare(strict_types=1);

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/site-data.php';

startUserSession();
$currentUser = getCurrentUser();
$selectedCourse = trim((string) ($_GET['course'] ?? ''));
$selectedDescription = $courses[$selectedCourse] ?? null;
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Courses | <?php echo htmlspecialchars(APP_NAME, ENT_QUOTES, 'UTF-8'); ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;700;800&family=Space+Grotesk:wght@500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/styles.css">
    <style>
        .course-selector-grid {
            display: grid;
            gap: 0.9rem;
            grid-template-columns: repeat(5, minmax(0, 1fr));
        }
        .course-selector-grid .course-link-card {
            display: block;
            min-height: 72px;
            width: 100%;
            padding: 1rem;
            background: #ffffff;
            border: 1px solid #dce6f6;
            border-radius: 10px;
            box-shadow: 0 8px 20px rgba(35, 57, 101, 0.08);
            text-decoration: none;
            color: #0e1e3a;
        }
        .course-selector-grid .course-link-card.is-active {
            background: #edf4ff;
            border-color: #8db6ff;
            color: #0b66ff;
        }
        .course-selector-grid .course-link-label {
            display: block;
            font-weight: 700;
            font-size: 0.98rem;
            line-height: 1.35;
            color: inherit;
        }
        @media (max-width: 820px) {
            .course-selector-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }
    </style>
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
    <main class="apply-section">
        <div class="container page-layout">
            <section class="page-card">
                <p class="tag">Programs</p>
                <h1>Choose a Course</h1>
                <p class="page-intro">Select a course card to open its details on this page.</p>
                <div class="course-selector-grid">
                    <?php foreach ($courses as $courseName => $description): ?>
                        <a class="course-link-card<?php echo $selectedCourse === $courseName ? ' is-active' : ''; ?>" href="courses.php?course=<?php echo urlencode($courseName); ?>">
                            <span class="course-link-label"><?php echo htmlspecialchars($courseName, ENT_QUOTES, 'UTF-8'); ?></span>
                        </a>
                    <?php endforeach; ?>
                </div>
            </section>
            <section class="page-card">
                <?php if ($selectedDescription === null): ?>
                    <p class="tag">Course Detail</p>
                    <h2>Select a program</h2>
                    <p class="page-intro">The selected course details will appear here. Then continue to the application page.</p>
                <?php else: ?>
                    <p class="tag">Course Detail</p>
                    <h2><?php echo htmlspecialchars($selectedCourse, ENT_QUOTES, 'UTF-8'); ?></h2>
                    <p class="page-intro"><?php echo htmlspecialchars($selectedDescription, ENT_QUOTES, 'UTF-8'); ?></p>
                    <div class="detail-row detail-row-rect">
                        <span class="detail-label">Duration</span>
                        <span class="detail-value">3 to 4 years depending on program structure</span>
                    </div>
                    <div class="detail-row detail-row-rect">
                        <span class="detail-label">Admission Mode</span>
                        <span class="detail-value">Online application with document upload and OTP verification</span>
                    </div>
                    <a class="btn-primary" href="apply.php?course=<?php echo urlencode($selectedCourse); ?>">Apply for this Course</a>
                <?php endif; ?>
            </section>
        </div>
    </main>
</body>
</html>
