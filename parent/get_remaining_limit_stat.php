<?php
// Check child's daily message limit
session_start();
require "../db.php";

$parent_id = $_SESSION['parent_id'] ?? null;
$child_id  = intval($_GET['child_id']);

if (!$parent_id || !$child_id) {
    echo json_encode(['error' => 'Invalid request']);
    exit;
}

// Fetching Child Info
$check = mysqli_query($conn, "SELECT * FROM tween_user WHERE id = $child_id AND parent_id = $parent_id LIMIT 1");
$child = mysqli_fetch_assoc($check);

if (!$child) {
    echo json_encode(['error' => 'Child not found']);
    exit;
}

// Count messages sent today
$today = date("Y-m-d");
$sent_today_result = mysqli_query($conn,"SELECT COUNT(*) AS count
                                         FROM message
                                         WHERE sender_id = {$child['user_id']} AND DATE(sent_at) = '$today'");
$sent_today = mysqli_fetch_assoc($sent_today_result)['count'];

$limit = $child['daily_msg_limit'];
$remaining = max(0, $limit - $sent_today);

echo json_encode([
    'limit' => $limit,
    'sent_today' => $sent_today,
    'remaining' => $remaining
]);
exit;;
?>
