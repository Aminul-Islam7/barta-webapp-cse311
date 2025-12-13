<?php
session_start();
require "../db.php";

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'tween') {
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$tween_id = $_SESSION['tween_id'];
$requester_id = isset($_POST['request_id']) ? (int) $_POST['request_id'] : 0;

if (!$requester_id) {
    echo json_encode(['error' => 'Invalid request ID']);
    exit;
}

// Delete request
$query = "DELETE FROM connection_request WHERE requester_id = $requester_id AND receiver_id = $tween_id";

if (mysqli_query($conn, $query)) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['error' => 'Failed to decline request']);
}
?>