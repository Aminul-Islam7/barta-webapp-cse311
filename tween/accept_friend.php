<?php
session_start();
require "../db.php";

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'tween') {
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$tween_id = $_SESSION['tween_id']; // This is the receiver
$requester_id = isset($_POST['request_id']) ? (int) $_POST['request_id'] : 0;

if (!$requester_id) {
    echo json_encode(['error' => 'Invalid request ID']);
    exit;
}

// Verify strict request existence (and that it is for me)
$query = "SELECT * FROM connection_request 
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
// Check if already connected just in case
$chk_query = "SELECT * FROM connection 
              WHERE (sender_id = $requester_id AND receiver_id = $tween_id) 
                 OR (sender_id = $tween_id AND receiver_id = $requester_id)";
$chk_res = mysqli_query($conn, $chk_query);

if (mysqli_num_rows($chk_res) == 0) {
    // Insert new connection
    $ins_query = "INSERT INTO connection (sender_id, receiver_id, type) VALUES ($requester_id, $tween_id, 'added')";
    if (mysqli_query($conn, $ins_query)) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['error' => 'Failed to create connection']);
    }
} else {
    // Already connected or blocked? Update to added if blocked? 
    // Usually shouldn't happen if logic is correct, but let's just say success or update to 'added'.
    // If it was blocked, we might want to unblock.
    $row = mysqli_fetch_assoc($chk_res);
    if ($row['type'] == 'blocked') {
        // If I blocked him, I shouldn't be accepting his request (request shouldn't exist).
        // If he blocked me, he can't send request.
        // Just force update to 'added'
        $upd_query = "UPDATE connection SET type = 'added' WHERE (sender_id = $requester_id AND receiver_id = $tween_id) OR (sender_id = $tween_id AND receiver_id = $requester_id)";
        mysqli_query($conn, $upd_query);
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => true, 'message' => 'Already connected']);
    }
}
?>