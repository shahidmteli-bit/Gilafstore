<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_admin();

$db = get_db_connection();

// Fetch all users with phone
$users = $db->query("
    SELECT 
        id,
        name,
        email,
        phone,
        COALESCE(is_admin, 0) AS is_admin,
        COALESCE(is_blocked, 0) AS is_blocked,
        COALESCE(is_restricted, 0) AS is_restricted,
        restriction_reason,
        created_at
    FROM users 
    ORDER BY created_at DESC
")->fetchAll(PDO::FETCH_ASSOC);

// Generate Excel file (CSV format with Excel-compatible encoding)
$filename = 'Users_Report_' . date('Y-m-d_H-i-s') . '.csv';

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Pragma: no-cache');
header('Expires: 0');

$output = fopen('php://output', 'w');

// Add BOM for Excel UTF-8 compatibility
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

// Report title
fputcsv($output, ['Users Report — Gilaf Store']);
fputcsv($output, ['Generated on: ' . date('Y-m-d H:i:s')]);
fputcsv($output, ['Total Users: ' . count($users)]);
fputcsv($output, []);

// Headers
fputcsv($output, [
    'User ID',
    'Name',
    'Email',
    'Phone',
    'Role',
    'Status',
    'Restriction Reason',
    'Joined Date'
]);

// Counters
$totalAdmins = 0;
$totalCustomers = 0;
$totalBlocked = 0;
$totalRestricted = 0;
$totalActive = 0;

foreach ($users as $user) {
    $role = $user['is_admin'] ? 'Admin' : 'Customer';
    
    if ($user['is_blocked']) {
        $status = 'Blocked';
        $totalBlocked++;
    } elseif ($user['is_restricted']) {
        $status = 'Restricted';
        $totalRestricted++;
    } else {
        $status = 'Active';
        $totalActive++;
    }
    
    if ($user['is_admin']) $totalAdmins++;
    else $totalCustomers++;

    fputcsv($output, [
        '#' . $user['id'],
        $user['name'],
        $user['email'],
        $user['phone'] ?? '',
        $role,
        $status,
        $user['restriction_reason'] ?? '',
        date('Y-m-d H:i:s', strtotime($user['created_at']))
    ]);
}

// Summary
fputcsv($output, []);
fputcsv($output, ['--- SUMMARY ---']);
fputcsv($output, ['Total Users', count($users)]);
fputcsv($output, ['Admins', $totalAdmins]);
fputcsv($output, ['Customers', $totalCustomers]);
fputcsv($output, ['Active', $totalActive]);
fputcsv($output, ['Blocked', $totalBlocked]);
fputcsv($output, ['Restricted', $totalRestricted]);

fclose($output);
exit;
