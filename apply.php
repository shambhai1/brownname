<?php
declare(strict_types=1);

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/site-data.php';

startUserSession();
$currentUser = getCurrentUser();
$selectedCourse = trim((string) ($_GET['course'] ?? ''));
$upiId = 'onlinecollegeadmission@upi';
$upiPayeeName = APP_NAME;
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Apply | <?php echo htmlspecialchars(APP_NAME, ENT_QUOTES, 'UTF-8'); ?></title>
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
    <main class="apply-section">
        <div class="container form-wrap">
            <h1>Admission Application Form</h1>
            <p class="page-intro">Complete the application in guided steps. Your draft is kept on this device and, if logged in, also in your account draft storage.</p>
            <div class="dashboard-actions">
                <button class="btn-secondary" type="button" id="clearDraftBtn">Clear Saved Draft</button>
                <span class="hint-text">Form draft is saved automatically on this device.</span>
            </div>
            <section class="form-progress-shell">
                <div class="progress-header">
                    <span>Application Steps</span>
                    <span id="stepCounter">Step 1 of 4</span>
                </div>
                <div class="progress-track">
                    <div class="progress-fill" id="formProgressFill" style="width: 25%;"></div>
                </div>
                <div class="form-step-tabs">
                    <span class="form-step-tab is-active">Personal</span>
                    <span class="form-step-tab">Academic</span>
                    <span class="form-step-tab">Documents</span>
                    <span class="form-step-tab">Verify</span>
                </div>
            </section>
            <section class="payment-section payment-fee-preview">
                <h3>Admission Fee Structure</h3>
                <div class="payment-fee-grid">
                    <?php foreach ($courseFees as $courseName => $fee): ?>
                        <article class="payment-fee-card">
                            <strong><?php echo htmlspecialchars($courseName, ENT_QUOTES, 'UTF-8'); ?></strong>
                            <span>Yearly Fee: Rs <?php echo number_format((float) $fee, 0); ?></span>
                        </article>
                    <?php endforeach; ?>
                </div>
            </section>
            <form id="admissionForm" novalidate>
                <section class="form-step-panel is-active" data-step="1">
                    <h3>Step 1: Personal Details</h3>
                    <div class="grid-2">
                        <label>Full Name
                            <input type="text" name="full_name" required minlength="3">
                        </label>
                        <label>Email
                            <input type="email" name="email" required>
                        </label>
                        <label>Phone
                            <input type="tel" name="phone" required pattern="[0-9]{10,15}">
                        </label>
                        <label>OTP Delivery Method
                            <select name="otp_delivery" required>
                                <option value="both" selected>Email + SMS (Recommended)</option>
                                <option value="email">Email only</option>
                                <option value="sms">SMS only</option>
                            </select>
                        </label>
                        <label>Date of Birth
                            <input type="date" name="dob" id="dobInput" required max="<?php echo date('Y-m-d'); ?>">
                        </label>
                        <label>Gender
                            <select name="gender" required>
                                <option value="">Select</option>
                                <option>Male</option>
                                <option>Female</option>
                                <option>Other</option>
                            </select>
                        </label>
                    </div>
                    <div class="form-step-actions">
                        <button class="btn-primary js-step-next" type="button">Next Step</button>
                    </div>
                </section>

                <section class="form-step-panel" data-step="2">
                    <h3>Step 2: Academic and Address Details</h3>
                    <div class="grid-2">
                        <label>Course
                            <select name="course" required>
                                <option value="">Select Course</option>
                                <?php foreach (array_keys($courses) as $course): ?>
                                    <option<?php echo $selectedCourse === $course ? ' selected' : ''; ?>><?php echo htmlspecialchars($course, ENT_QUOTES, 'UTF-8'); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <label>City
                            <input type="text" name="city" required>
                        </label>
                        <label>State
                            <select name="state" required>
                                <option value="">Select State/UT</option>
                                <?php foreach ($states as $state): ?>
                                    <option><?php echo htmlspecialchars($state, ENT_QUOTES, 'UTF-8'); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <label>Zip Code
                            <input type="text" name="zip_code" required pattern="[0-9]{4,10}">
                        </label>
                        <label>Previous Marks (%)
                            <input type="number" name="previous_marks" min="0" max="100" step="0.01" required>
                        </label>
                    </div>
                    <label>Address
                        <textarea name="address" rows="3" required></textarea>
                    </label>
                    <div class="form-step-actions">
                        <button class="btn-secondary js-step-prev" type="button">Previous</button>
                        <button class="btn-primary js-step-next" type="button">Next Step</button>
                    </div>
                </section>

                <section class="form-step-panel" data-step="3">
                    <h3>Step 3: Upload Documents</h3>
                    <div class="grid-2">
                        <label>Upload Marksheet (PDF/JPG/PNG, max 2 MB)
                            <input type="file" name="marksheet_file" accept=".pdf,.jpg,.jpeg,.png" required>
                        </label>
                        <label>Upload ID Proof (PDF/JPG/PNG, max 2 MB)
                            <input type="file" name="id_proof_file" accept=".pdf,.jpg,.jpeg,.png" required>
                        </label>
                        <label>Upload Passport Photo (JPG/PNG, max 2 MB)
                            <input type="file" name="photo_file" accept=".jpg,.jpeg,.png" required>
                        </label>
                    </div>
                    <div class="form-step-actions">
                        <button class="btn-secondary js-step-prev" type="button">Previous</button>
                        <button class="btn-primary js-step-next" type="button">Next Step</button>
                    </div>
                </section>

                <section class="form-step-panel" data-step="4">
                    <h3>Step 4: Verify and Submit</h3>
                    <p class="page-intro">Review your entries, send OTP, then verify it to complete the application.</p>
                    <button class="btn-primary" type="submit">Send OTP</button>
                    <div id="otpSection" style="display:none;">
                        <label>Enter OTP (sent to your email or phone)
                            <input type="text" name="otp_code" id="otpCode" pattern="[0-9]{6}" maxlength="6" placeholder="6-digit OTP">
                        </label>
                        <div class="otp-actions">
                            <button class="btn-secondary" type="button" id="resendOtpBtn" disabled>Resend OTP in 30s</button>
                            <span id="resendHint" class="hint-text">You can resend OTP after 30 seconds.</span>
                        </div>
                        <button class="btn-primary" type="button" id="verifyOtpBtn">Verify OTP & Final Submit</button>
                    </div>
                    <div class="form-step-actions">
                        <button class="btn-secondary js-step-prev" type="button">Previous</button>
                    </div>
                </section>
                <p id="formMessage" class="message" aria-live="polite"></p>
            </form>
            <section id="paymentSection" class="payment-section" style="display:none;">
                <h3>Admission Payment</h3>
                <p class="payment-note">Application submitted. Select a payment method to continue fee payment.</p>
                <div class="payment-summary-box">
                    <div>
                        <span class="payment-label">Selected Course</span>
                        <strong id="paymentCourseName">Not selected</strong>
                    </div>
                    <div>
                        <span class="payment-label">Yearly Admission Fee</span>
                        <strong id="paymentFeeAmount">Rs 0</strong>
                    </div>
                </div>
                <div class="payment-methods">
                    <label><input type="radio" name="payment_method" value="Google Pay" checked> Google Pay</label>
                    <label><input type="radio" name="payment_method" value="PhonePe"> PhonePe</label>
                    <label><input type="radio" name="payment_method" value="Paytm"> Paytm</label>
                    <label><input type="radio" name="payment_method" value="Other UPI"> Other UPI App</label>
                </div>
                <div id="upiQrSection" class="payment-qr-block">
                    <div class="payment-qr-card">
                        <img id="paymentQrImage" alt="UPI payment QR code">
                    </div>
                    <div class="payment-qr-details">
                        <p class="payment-label">Selected App</p>
                        <strong id="paymentMethodName">Google Pay</strong>
                        <p class="payment-label">UPI ID</p>
                        <strong id="paymentUpiId"><?php echo htmlspecialchars($upiId, ENT_QUOTES, 'UTF-8'); ?></strong>
                        <p id="paymentQrCopy" class="payment-qr-copy">Scan this QR in Google Pay and pay the exact yearly admission fee for the selected course.</p>
                    </div>
                </div>
                <input type="hidden" id="paymentApplicationId" value="">
                <div class="grid-2">
                    <label>Transaction ID
                        <input type="text" id="transactionIdInput" placeholder="Enter payment transaction ID">
                    </label>
                    <label>Payment Screenshot (optional)
                        <input type="file" id="paymentScreenshotInput" accept=".jpg,.jpeg,.png,.pdf">
                    </label>
                </div>
                <button class="btn-primary" type="button" id="payNowBtn">Submit Payment Details</button>
                <p id="paymentMessage" class="message" aria-live="polite"></p>
            </section>
        </div>
    </main>
    <script>
        window.COLLEGE_ADMISSION_API_BASE = <?php
            $scriptDirectory = dirname($_SERVER['SCRIPT_NAME'] ?? '/');
            if ($scriptDirectory === '\\' || $scriptDirectory === '.' || $scriptDirectory === '') {
                $scriptDirectory = '/';
            }
            $apiBasePath = rtrim(str_replace('\\\\', '/', $scriptDirectory), '/');
            echo json_encode($apiBasePath . '/api/');
        ?>;
        window.COLLEGE_ADMISSION_COURSE_FEES = <?php echo json_encode($courseFees); ?>;
        window.COLLEGE_ADMISSION_UPI_ID = <?php echo json_encode($upiId); ?>;
        window.COLLEGE_ADMISSION_UPI_NAME = <?php echo json_encode($upiPayeeName); ?>;
        window.COLLEGE_ADMISSION_DRAFT_DB_ENABLED = <?php echo json_encode($currentUser !== null); ?>;
    </script>
    <script src="assets/js/app.js"></script>
</body>
</html>
