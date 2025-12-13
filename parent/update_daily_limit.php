<?php
// Update child's daily message limit
session_start();
require "../db.php";  

// Set JSON header
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['error' => 'Invalid request method']);
    exit;
}

$parent_id = $_SESSION['parent_id'] ?? null;
$tween_id  = intval($_POST['tween_id']);
$new_limit = intval($_POST['daily_limit']);

// Validate
if (!$parent_id || !$tween_id || $new_limit < 0) {
    echo json_encode(['error' => 'Invalid parameters']);
    exit;
}

// Check after validating $parent_id and $tween_id
$ownership_check = mysqli_query($conn,"SELECT id FROM tween_user WHERE id = $tween_id AND parent_id = $parent_id");
if (mysqli_num_rows($ownership_check) === 0) {
    echo json_encode(['error' => 'Child not found or access denied']);
    exit;
}

// Update limit
$update = mysqli_query($conn,"UPDATE tween_user 
                              SET daily_msg_limit = $new_limit
                              WHERE id = $tween_id AND parent_id = $parent_id AND is_active = 1");
if ($update) {
    echo json_encode(['success' => true, 'message' => 'Daily limit updated successfully']);
} else {
    echo json_encode(['error' => 'Update failed']);
}
exit;
?>





