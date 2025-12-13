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

$query = "SELECT requester_id, receiver_id, requester_parent_approved, receiver_parent_approved FROM connection_request
          WHERE requester_id = $requester_id AND receiver_id = $tween_id";
$result = mysqli_query($conn, $query);

if (mysqli_num_rows($result) === 0) {
    echo json_encode(['error' => 'Friend request not found']);
    exit;
}

// Remove from connection_request
$del_query = "DELETE FROM connection_request WHERE requester_id = $requester_id AND receiver_id = $tween_id";
mysqli_query($conn, $del_query);

// Add to connection table
$checkQuery = "SELECT type FROM connection
              WHERE (sender_id = $requester_id AND receiver_id = $tween_id)
                 OR (sender_id = $tween_id AND receiver_id = $requester_id)";
$chk_res = mysqli_query($conn, $checkQuery);

if (mysqli_num_rows($chk_res) == 0) {
    $ins_query = "INSERT INTO connection (sender_id, receiver_id, type) VALUES ($requester_id, $tween_id, 'added')";
    if (mysqli_query($conn, $ins_query)) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['error' => 'Failed to create connection']);
    }
} else {
    // Already connected or blocked?
    $row = mysqli_fetch_assoc($chk_res);
    if ($row['type'] == 'blocked') {
        $upd_query = "UPDATE connection SET type = 'added' WHERE (sender_id = $requester_id AND receiver_id = $tween_id) OR (sender_id = $tween_id AND receiver_id = $requester_id)";
        mysqli_query($conn, $upd_query);
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => true, 'message' => 'Already connected']);
    }
}
?>