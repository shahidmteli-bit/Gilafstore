<?php
session_start();
require_once '../includes/db_connect.php';
require_once '../includes/functions.php';

// Check if admin is logged in
if (empty($_SESSION['user']) || empty($_SESSION['user']['is_admin'])) {
    header('Location: admin_login.php');
    exit();
}

// Get filter parameters
$fromDate = $_GET['from_date'] ?? date('Y-m-01');
$toDate = $_GET['to_date'] ?? date('Y-m-d');
$actionType = $_GET['action_type'] ?? '';
$tableName = $_GET['table_name'] ?? '';
$changedBy = $_GET['changed_by'] ?? '';

// Build query
$whereConditions = [];
$params = [];
$types = '';

if ($fromDate && $toDate) {
    $whereConditions[] = "DATE(gat.changed_at) BETWEEN ? AND ?";
    $params[] = $fromDate;
    $params[] = $toDate;
    $types .= 'ss';
}

if ($actionType) {
    $whereConditions[] = "gat.action_type = ?";
    $params[] = $actionType;
    $types .= 's';
}

if ($tableName) {
    $whereConditions[] = "gat.table_name = ?";
    $params[] = $tableName;
    $types .= 's';
}

if ($changedBy) {
    $whereConditions[] = "gat.changed_by = ?";
    $params[] = $changedBy;
    $types .= 'i';
}

$whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

// Get audit trail records
$query = "SELECT gat.*, u.name as admin_name, u.email as admin_email
          FROM gst_audit_trail gat
          LEFT JOIN users u ON gat.changed_by = u.id
          {$whereClause}
          ORDER BY gat.changed_at DESC";

$stmt = $conn->prepare($query);
if ($stmt === false) {
    die("Query preparation failed: " . $conn->error);
}

if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
$auditRecords = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];

// Set headers for CSV download
$filename = 'gst_audit_log_' . $fromDate . '_to_' . $toDate . '_' . date('YmdHis') . '.csv';
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Pragma: no-cache');
header('Expires: 0');

// Create output stream
$output = fopen('php://output', 'w');

// Add BOM for Excel UTF-8 support
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

// Add CSV headers
fputcsv($output, [
    'ID',
    'Date & Time',
    'Action Type',
    'Table Name',
    'Record ID',
    'Changed By',
    'Admin Email',
    'Old Values',
    'New Values',
    'IP Address',
    'User Agent'
]);

// Add data rows
foreach ($auditRecords as $record) {
    // Format old and new values for better readability
    $oldValues = '';
    if (!empty($record['old_values'])) {
        $oldData = json_decode($record['old_values'], true);
        if (is_array($oldData)) {
            $oldValues = implode('; ', array_map(function($k, $v) {
                return "$k: $v";
            }, array_keys($oldData), $oldData));
        } else {
            $oldValues = $record['old_values'];
        }
    }
    
    $newValues = '';
    if (!empty($record['new_values'])) {
        $newData = json_decode($record['new_values'], true);
        if (is_array($newData)) {
            $newValues = implode('; ', array_map(function($k, $v) {
                return "$k: $v";
            }, array_keys($newData), $newData));
        } else {
            $newValues = $record['new_values'];
        }
    }
    
    fputcsv($output, [
        $record['id'],
        $record['changed_at'],
        strtoupper($record['action_type']),
        $record['table_name'],
        $record['record_id'] ?? 'N/A',
        $record['admin_name'] ?? 'Unknown',
        $record['admin_email'] ?? 'N/A',
        $oldValues,
        $newValues,
        $record['ip_address'] ?? 'N/A',
        $record['user_agent'] ?? 'N/A'
    ]);
}

fclose($output);
exit();
