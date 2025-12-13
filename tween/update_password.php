<?php
session_start();
header('Content-Type: application/json');
require "../db.php";

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$user_id = (int) $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['error' => 'Invalid request method']);
    exit;
}

$current_password = $_POST['current_password'] ?? '';
$new_password = $_POST['new_password'] ?? '';

if (empty($current_password) || empty($new_password)) {
    echo json_encode(['error' => 'All fields are required']);
    exit;
}

// Fetch current hash
$stmt = $conn->prepare("SELECT password_hash FROM bartauser WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(['error' => 'User not found']);
    exit;
}

$user = $result->fetch_assoc();
if (!password_verify($current_password, $user['password_hash'])) {
    echo json_encode(['error' => 'Incorrect current password']);
    exit;
}

// Update password
$new_hash = password_hash($new_password, PASSWORD_DEFAULT);
$update = $conn->prepare("UPDATE bartauser SET password_hash = ? WHERE id = ?");
$update->bind_param("si", $new_hash, $user_id);

if ($update->execute()) {
    echo json_encode(['success' => true, 'message' => 'Password updated successfully']);
} else {
    echo json_encode(['error' => 'Failed to update password']);
}

$stmt->close();
$update->close();
$conn->close();
?>