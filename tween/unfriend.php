<?php
session_start();
require "../db.php";

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'tween') {
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$tween_id = $_SESSION['tween_id'];
$friend_id = isset($_POST['friend_id']) ? (int) $_POST['friend_id'] : 0;

if (!$friend_id) {
    echo json_encode(['error' => 'Invalid friend ID']);
    exit;
}

// Remove the connection (unfriend)
// We check for both directions: (me -> him) or (him -> me)
$query = "DELETE FROM connection 
          WHERE ((sender_id = $tween_id AND receiver_id = $friend_id) 
             OR (sender_id = $friend_id AND receiver_id = $tween_id)) 
          AND type = 'added'";

if (mysqli_query($conn, $query)) {
    if (mysqli_affected_rows($conn) > 0) {
        echo json_encode(['success' => true]);
    } else {
        // Even if no rows were deleted (maybe already unfriended), we can consider it a success 
        // regarding the intent "make sure we are not friends". 
        // But maybe return distinctive message? For now success is fine to update UI.
        echo json_encode(['success' => true, 'message' => 'No connection found or already removed']);
    }
} else {
    echo json_encode(['error' => 'Database error: ' . mysqli_error($conn)]);
}
?>