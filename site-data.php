<?php
declare(strict_types=1);

$courses = [
    'BCA' => 'Bachelor of Computer Applications focused on software, databases, and web systems.',
    'BBA' => 'Bachelor of Business Administration covering management, finance, and operations.',
    'B.Sc Computer Science' => 'Computer science degree with programming, algorithms, and system fundamentals.',
    'B.Sc IT' => 'Information technology program focused on networking, applications, and infrastructure.',
    'B.Com' => 'Commerce degree covering accounting, taxation, business law, and finance.',
    'BA English' => 'Arts program emphasizing literature, communication, writing, and language studies.',
    'B.Tech CSE' => 'Engineering track for computer science, programming, software design, and computing theory.',
];

$courseFees = [
    'BCA' => 15000,
    'BBA' => 20000,
    'B.Sc Computer Science' => 25000,
    'B.Sc IT' => 22000,
    'B.Com' => 17000,
    'BA English' => 18000,
    'B.Tech CSE' => 21000,
];

function getCourseFeeAmount(string $course): int
{
    global $courseFees;

    return (int) ($courseFees[$course] ?? 0);
}

$states = [
    'Andhra Pradesh', 'Arunachal Pradesh', 'Assam', 'Bihar', 'Chhattisgarh', 'Goa', 'Gujarat',
    'Haryana', 'Himachal Pradesh', 'Jharkhand', 'Karnataka', 'Kerala', 'Madhya Pradesh',
    'Maharashtra', 'Manipur', 'Meghalaya', 'Mizoram', 'Nagaland', 'Odisha', 'Punjab',
    'Rajasthan', 'Sikkim', 'Tamil Nadu', 'Telangana', 'Tripura', 'Uttar Pradesh', 'Uttarakhand',
    'West Bengal', 'Andaman and Nicobar Islands', 'Chandigarh', 'Dadra and Nagar Haveli and Daman and Diu',
    'Delhi', 'Jammu and Kashmir', 'Ladakh', 'Lakshadweep', 'Puducherry'
];
