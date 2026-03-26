<?php
declare(strict_types=1);

function sanitizeText(?string $value): string
{
    return trim((string) $value);
}

function validateAdmissionPayload(array $data): array
{
    $errors = [];

    $fullName = sanitizeText($data['full_name'] ?? '');
    $email = sanitizeText($data['email'] ?? '');
    $phone = sanitizeText($data['phone'] ?? '');
    $dob = sanitizeText($data['dob'] ?? '');
    $gender = sanitizeText($data['gender'] ?? '');
    $course = sanitizeText($data['course'] ?? '');
    $address = sanitizeText($data['address'] ?? '');
    $city = sanitizeText($data['city'] ?? '');
    $state = sanitizeText($data['state'] ?? '');
    $zipCode = sanitizeText($data['zip_code'] ?? '');
    $previousMarks = sanitizeText($data['previous_marks'] ?? '');

    if ($fullName === '' || mb_strlen($fullName) < 3) {
        $errors['full_name'] = 'Full name must be at least 3 characters.';
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Please provide a valid email address.';
    }

    if (!preg_match('/^[0-9]{10,15}$/', $phone)) {
        $errors['phone'] = 'Phone must be 10 to 15 digits.';
    }

    if ($dob === '' || strtotime($dob) === false) {
        $errors['dob'] = 'Please provide a valid date of birth.';
    }

    if (!in_array($gender, ['Male', 'Female', 'Other'], true)) {
        $errors['gender'] = 'Please select a valid gender.';
    }

    if ($course === '') {
        $errors['course'] = 'Please select a course.';
    }

    if ($address === '' || mb_strlen($address) < 10) {
        $errors['address'] = 'Address must be at least 10 characters.';
    }

    if ($city === '') {
        $errors['city'] = 'City is required.';
    }

    if ($state === '') {
        $errors['state'] = 'State is required.';
    }

    if (!preg_match('/^[0-9]{4,10}$/', $zipCode)) {
        $errors['zip_code'] = 'Zip code must be 4 to 10 digits.';
    }

    if (!is_numeric($previousMarks) || (float) $previousMarks < 0 || (float) $previousMarks > 100) {
        $errors['previous_marks'] = 'Previous marks must be between 0 and 100.';
    }

    return [
        'errors' => $errors,
        'clean' => [
            'full_name' => $fullName,
            'email' => $email,
            'phone' => $phone,
            'dob' => $dob,
            'gender' => $gender,
            'course' => $course,
            'address' => $address,
            'city' => $city,
            'state' => $state,
            'zip_code' => $zipCode,
            'previous_marks' => (float) $previousMarks,
        ],
    ];
}

