<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/site-data.php';
require_once __DIR__ . '/../includes/utilities.php';

requireAdminLogin();

$applicationId = filter_var($_GET['id'] ?? $_POST['application_id'] ?? null, FILTER_VALIDATE_INT, [
    'options' => ['min_range' => 1],
]);

if ($applicationId === false) {
    header('Location: applications.php');
    exit;
}

$error = '';
$success = '';
$application = null;

function validateOptionalDocumentUploads(array $files): array
{
    $errors = [];
    $clean = [];
    $rules = [
        'marksheet_file' => [
            'label' => 'Marksheet',
            'allowed_ext' => ['pdf', 'jpg', 'jpeg', 'png'],
            'allowed_mime' => ['application/pdf', 'image/jpeg', 'image/png'],
        ],
        'id_proof_file' => [
            'label' => 'ID Proof',
            'allowed_ext' => ['pdf', 'jpg', 'jpeg', 'png'],
            'allowed_mime' => ['application/pdf', 'image/jpeg', 'image/png'],
        ],
        'photo_file' => [
            'label' => 'Photo',
            'allowed_ext' => ['jpg', 'jpeg', 'png'],
            'allowed_mime' => ['image/jpeg', 'image/png'],
        ],
    ];

    foreach ($rules as $field => $rule) {
        $file = $files[$field] ?? null;
        if (!is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            continue;
        }

        $errorCode = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($errorCode !== UPLOAD_ERR_OK) {
            $errors[$field] = $rule['label'] . ' upload failed.';
            continue;
        }

        $size = (int) ($file['size'] ?? 0);
        if ($size <= 0 || $size > DOCUMENT_MAX_SIZE_BYTES) {
            $errors[$field] = $rule['label'] . ' must be under 2 MB.';
            continue;
        }

        $originalName = (string) ($file['name'] ?? '');
        $tmpPath = (string) ($file['tmp_name'] ?? '');
        $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        if (!in_array($ext, $rule['allowed_ext'], true)) {
            $errors[$field] = $rule['label'] . ' has invalid file format.';
            continue;
        }

        $mime = '';
        if (is_file($tmpPath)) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            if ($finfo !== false) {
                $mime = (string) finfo_file($finfo, $tmpPath);
                finfo_close($finfo);
            }
        }

        if ($mime !== '' && !in_array($mime, $rule['allowed_mime'], true)) {
            $errors[$field] = $rule['label'] . ' has invalid MIME type.';
            continue;
        }

        $clean[$field] = [
            'tmp_name' => $tmpPath,
            'original_name' => $originalName,
            'extension' => $ext,
        ];
    }

    return [
        'errors' => $errors,
        'clean' => $clean,
    ];
}

function storeOptionalDocumentUploads(array $validatedFiles): array
{
    if ($validatedFiles === []) {
        return [
            'errors' => [],
            'paths' => [],
        ];
    }

    $uploadDir = __DIR__ . '/../storage/uploads';
    if (!is_dir($uploadDir) && !mkdir($uploadDir, 0777, true) && !is_dir($uploadDir)) {
        return [
            'errors' => ['upload' => 'Could not create upload directory.'],
            'paths' => [],
        ];
    }

    $paths = [];
    $fieldToColumn = [
        'marksheet_file' => 'marksheet_path',
        'id_proof_file' => 'id_proof_path',
        'photo_file' => 'photo_path',
    ];

    foreach ($validatedFiles as $field => $file) {
        if (!isset($fieldToColumn[$field]) || !is_array($file)) {
            continue;
        }

        $safeName = $field . '_' . date('YmdHis') . '_' . bin2hex(random_bytes(6)) . '.' . $file['extension'];
        $absolutePath = $uploadDir . '/' . $safeName;
        if (!move_uploaded_file($file['tmp_name'], $absolutePath)) {
            return [
                'errors' => ['upload' => 'Failed to save uploaded file.'],
                'paths' => [],
            ];
        }

        $paths[$fieldToColumn[$field]] = 'storage/uploads/' . $safeName;
    }

    return [
        'errors' => [],
        'paths' => $paths,
    ];
}

try {
    $pdo = getConnection();

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $payload = validateAdmissionPayload($_POST);
        $optionalDocuments = validateOptionalDocumentUploads($_FILES);

        if ($payload['errors'] !== []) {
            $error = implode(' ', array_values($payload['errors']));
        } elseif ($optionalDocuments['errors'] !== []) {
            $error = implode(' ', array_values($optionalDocuments['errors']));
        } else {
            $currentStmt = $pdo->prepare('SELECT * FROM applications WHERE id = :id LIMIT 1');
            $currentStmt->execute(['id' => $applicationId]);
            $current = $currentStmt->fetch();

            if (!$current) {
                $error = 'Application not found.';
            } else {
                $data = $payload['clean'];
                $documentPaths = [
                    'marksheet_path' => (string) ($current['marksheet_path'] ?? ''),
                    'id_proof_path' => (string) ($current['id_proof_path'] ?? ''),
                    'photo_path' => (string) ($current['photo_path'] ?? ''),
                ];

                if ($optionalDocuments['clean'] !== []) {
                        $stored = storeOptionalDocumentUploads($optionalDocuments['clean']);
                        if ($stored['errors'] !== []) {
                            $error = implode(' ', array_values($stored['errors']));
                        } else {
                            $documentPaths = array_merge($documentPaths, $stored['paths']);
                        }
                }

                if ($error === '') {
                    $updateData = array_merge($data, $documentPaths, ['id' => $applicationId]);
                    $updateStmt = $pdo->prepare(
                        'UPDATE applications
                         SET full_name = :full_name,
                             email = :email,
                             phone = :phone,
                             dob = :dob,
                             gender = :gender,
                             course = :course,
                             address = :address,
                             city = :city,
                             state = :state,
                             zip_code = :zip_code,
                             previous_marks = :previous_marks,
                             marksheet_path = :marksheet_path,
                             id_proof_path = :id_proof_path,
                             photo_path = :photo_path
                         WHERE id = :id'
                    );
                    $updateStmt->execute($updateData);
                    $success = 'Application updated successfully.';
                }
            }
        }
    }

    $stmt = $pdo->prepare('SELECT * FROM applications WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $applicationId]);
    $application = $stmt->fetch();
} catch (Throwable $e) {
    $error = 'Unable to load or update this application right now.';
}

if (!$application) {
    header('Location: applications.php');
    exit;
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Edit Application | <?php echo htmlspecialchars(APP_NAME, ENT_QUOTES, 'UTF-8'); ?></title>
    <link rel="stylesheet" href="../assets/css/styles.css">
    <style>
        .admin-edit-shell { max-width: 1200px; }
        .admin-edit-header { display: flex; justify-content: space-between; align-items: flex-start; gap: 1rem; margin-bottom: 1.25rem; }
        .admin-edit-meta { display: grid; grid-template-columns: repeat(2, minmax(180px, 1fr)); gap: 0.85rem; margin: 1rem 0 1.5rem; }
        .admin-meta-card { background: #f8fbff; border: 1px solid #dce6f6; border-radius: 12px; padding: 0.95rem 1rem; }
        .admin-meta-card span { display: block; font-size: 0.76rem; font-weight: 800; letter-spacing: 0.08em; text-transform: uppercase; color: #5f7fb3; margin-bottom: 0.35rem; }
        .admin-doc-grid { display: grid; grid-template-columns: repeat(3, minmax(180px, 1fr)); gap: 0.85rem; margin: 1rem 0 1.5rem; }
        .admin-doc-card { background: #f8fbff; border: 1px solid #dce6f6; border-radius: 12px; padding: 0.95rem 1rem; }
        .admin-doc-card strong { display: block; margin-bottom: 0.5rem; }
        .admin-doc-card a { word-break: break-word; }
        @media (max-width: 760px) {
            .admin-edit-header { flex-direction: column; }
            .admin-edit-meta,
            .admin-doc-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <main class="apply-section">
        <div class="container form-wrap admin-edit-shell">
            <div class="admin-edit-header">
                <div>
                    <h1>Edit Student Application</h1>
                    <p class="page-intro">Administrator can update all student admission details here.</p>
                </div>
                <div class="dashboard-actions">
                    <a class="btn-secondary" href="applications.php">Back to Applications</a>
                    <a class="btn-secondary" href="../index.php">Back to Home</a>
                </div>
            </div>
            <?php if ($success !== ''): ?>
                <p class="message success"><?php echo htmlspecialchars($success, ENT_QUOTES, 'UTF-8'); ?></p>
            <?php endif; ?>
            <?php if ($error !== ''): ?>
                <p class="message error"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></p>
            <?php endif; ?>
            <div class="admin-edit-meta">
                <div class="admin-meta-card">
                    <span>Application ID</span>
                    #<?php echo (int) $application['id']; ?>
                </div>
                <div class="admin-meta-card">
                    <span>Submitted On</span>
                    <?php echo htmlspecialchars((string) $application['created_at'], ENT_QUOTES, 'UTF-8'); ?>
                </div>
            </div>
            <form method="post" enctype="multipart/form-data" class="auth-form">
                <input type="hidden" name="application_id" value="<?php echo (int) $application['id']; ?>">
                <div class="grid-2">
                    <label>Full Name
                        <input type="text" name="full_name" required minlength="3" value="<?php echo htmlspecialchars((string) $application['full_name'], ENT_QUOTES, 'UTF-8'); ?>">
                    </label>
                    <label>Email
                        <input type="email" name="email" required value="<?php echo htmlspecialchars((string) $application['email'], ENT_QUOTES, 'UTF-8'); ?>">
                    </label>
                    <label>Phone
                        <input type="tel" name="phone" required pattern="[0-9]{10,15}" value="<?php echo htmlspecialchars((string) $application['phone'], ENT_QUOTES, 'UTF-8'); ?>">
                    </label>
                    <label>Date of Birth
                        <input type="date" name="dob" required value="<?php echo htmlspecialchars((string) $application['dob'], ENT_QUOTES, 'UTF-8'); ?>">
                    </label>
                    <label>Gender
                        <select name="gender" required>
                            <option value="Male"<?php echo $application['gender'] === 'Male' ? ' selected' : ''; ?>>Male</option>
                            <option value="Female"<?php echo $application['gender'] === 'Female' ? ' selected' : ''; ?>>Female</option>
                            <option value="Other"<?php echo $application['gender'] === 'Other' ? ' selected' : ''; ?>>Other</option>
                        </select>
                    </label>
                    <label>Course
                        <select name="course" required>
                            <option value="">Select Course</option>
                            <?php foreach (array_keys($courses) as $course): ?>
                                <option value="<?php echo htmlspecialchars($course, ENT_QUOTES, 'UTF-8'); ?>"<?php echo $application['course'] === $course ? ' selected' : ''; ?>><?php echo htmlspecialchars($course, ENT_QUOTES, 'UTF-8'); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label>City
                        <input type="text" name="city" required value="<?php echo htmlspecialchars((string) $application['city'], ENT_QUOTES, 'UTF-8'); ?>">
                    </label>
                    <label>State
                        <select name="state" required>
                            <option value="">Select State/UT</option>
                            <?php foreach ($states as $state): ?>
                                <option value="<?php echo htmlspecialchars($state, ENT_QUOTES, 'UTF-8'); ?>"<?php echo $application['state'] === $state ? ' selected' : ''; ?>><?php echo htmlspecialchars($state, ENT_QUOTES, 'UTF-8'); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label>Zip Code
                        <input type="text" name="zip_code" required pattern="[0-9]{4,10}" value="<?php echo htmlspecialchars((string) $application['zip_code'], ENT_QUOTES, 'UTF-8'); ?>">
                    </label>
                    <label>Previous Marks (%)
                        <input type="number" name="previous_marks" min="0" max="100" step="0.01" required value="<?php echo htmlspecialchars((string) $application['previous_marks'], ENT_QUOTES, 'UTF-8'); ?>">
                    </label>
                </div>
                <label>Address
                    <textarea name="address" rows="4" required><?php echo htmlspecialchars((string) $application['address'], ENT_QUOTES, 'UTF-8'); ?></textarea>
                </label>
                <div class="grid-2">
                    <label>Replace Marksheet (optional)
                        <input type="file" name="marksheet_file" accept=".pdf,.jpg,.jpeg,.png">
                    </label>
                    <label>Replace ID Proof (optional)
                        <input type="file" name="id_proof_file" accept=".pdf,.jpg,.jpeg,.png">
                    </label>
                    <label>Replace Photo (optional)
                        <input type="file" name="photo_file" accept=".jpg,.jpeg,.png">
                    </label>
                </div>
                <div class="admin-doc-grid">
                    <div class="admin-doc-card">
                        <strong>Current Marksheet</strong>
                        <?php if (!empty($application['marksheet_path'])): ?>
                            <a href="../<?php echo htmlspecialchars((string) $application['marksheet_path'], ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener noreferrer">Open current marksheet</a>
                        <?php else: ?>
                            <span>No marksheet uploaded.</span>
                        <?php endif; ?>
                    </div>
                    <div class="admin-doc-card">
                        <strong>Current ID Proof</strong>
                        <?php if (!empty($application['id_proof_path'])): ?>
                            <a href="../<?php echo htmlspecialchars((string) $application['id_proof_path'], ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener noreferrer">Open current ID proof</a>
                        <?php else: ?>
                            <span>No ID proof uploaded.</span>
                        <?php endif; ?>
                    </div>
                    <div class="admin-doc-card">
                        <strong>Current Photo</strong>
                        <?php if (!empty($application['photo_path'])): ?>
                            <a href="../<?php echo htmlspecialchars((string) $application['photo_path'], ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener noreferrer">Open current photo</a>
                        <?php else: ?>
                            <span>No photo uploaded.</span>
                        <?php endif; ?>
                    </div>
                </div>
                <button class="btn-primary" type="submit">Update Application</button>
            </form>
        </div>
    </main>
</body>
</html>
