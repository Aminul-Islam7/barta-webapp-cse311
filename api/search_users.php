<?php
session_start();
require "../db.php";

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'tween') {
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$tween_id = $_SESSION['tween_id'];
$query = isset($_GET['q']) ? trim($_GET['q']) : '';

if (empty($query)) {
    echo json_encode([]);
    exit;
}

$search_term = mysqli_real_escape_string($conn, $query);

// Get list of blocked users (both directions) from connection table (type = 'blocked')
$blocked_query = "SELECT CASE WHEN sender_id = $tween_id THEN receiver_id ELSE sender_id END as other_id
                  FROM connection
                  WHERE (sender_id = $tween_id OR receiver_id = $tween_id) AND type = 'blocked'";
$blocked_result = mysqli_query($conn, $blocked_query);
$blocked_ids = [];
while ($row = mysqli_fetch_assoc($blocked_result)) {
    if (!empty($row['other_id']))
        $blocked_ids[] = $row['other_id'];
}
$blocked_ids_str = empty($blocked_ids) ? '0' : implode(',', $blocked_ids);

// Search for friends matching the query
$friends_query = "SELECT 
    CASE WHEN c.sender_id = $tween_id THEN c.receiver_id ELSE c.sender_id END AS tween_id,
    tu.username, 
    bu.full_name, 
    tu.bio,
    'friend' as type
FROM connection c
JOIN tween_user tu ON (CASE WHEN c.sender_id = $tween_id THEN c.receiver_id ELSE c.sender_id END) = tu.id
JOIN bartauser bu ON tu.user_id = bu.id AND bu.role = 'tween'
WHERE (c.sender_id = $tween_id OR c.receiver_id = $tween_id)
    AND c.type = 'added'
    AND (tu.username LIKE '%$search_term%' OR bu.full_name LIKE '%$search_term%')
ORDER BY bu.full_name ASC";

$friends_result = mysqli_query($conn, $friends_query);
$friends = [];
while ($row = mysqli_fetch_assoc($friends_result)) {
    $friends[] = $row;
}

// Search for non-friends (active tweens not blocked and not already friends)
// Get list of friend IDs
$friend_ids = array_map(function ($f) {
    return $f['tween_id'];
}, $friends);
$friend_ids[] = $tween_id; // exclude self
$friend_ids_str = implode(',', $friend_ids);

$non_friends_query = "SELECT 
    tu.id as tween_id,
    tu.username, 
    bu.full_name, 
    tu.bio,
    'non_friend' as type
FROM tween_user tu
JOIN bartauser bu ON tu.user_id = bu.id AND bu.role = 'tween'
WHERE tu.is_active = 1
    AND tu.id NOT IN ($friend_ids_str)
    AND tu.id NOT IN ($blocked_ids_str)
    AND (tu.username LIKE '%$search_term%' OR bu.full_name LIKE '%$search_term%')
ORDER BY bu.full_name ASC
LIMIT 20";

$non_friends_result = mysqli_query($conn, $non_friends_query);
$non_friends = [];
while ($row = mysqli_fetch_assoc($non_friends_result)) {
    $non_friends[] = $row;
}

echo json_encode([
    'friends' => $friends,
    'non_friends' => $non_friends
]);
