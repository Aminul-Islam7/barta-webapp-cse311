<?php
session_start();
header('Content-Type: application/json');
ini_set('display_errors', 0);
error_reporting(E_ALL);

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') != 'tween') {
	echo json_encode(['error' => 'Unauthorized']);
	exit;
}

require_once __DIR__ . '/../db.php';

$tween_id = isset($_SESSION['tween_id']) ? (int) $_SESSION['tween_id'] : 0;
if (!$tween_id) {
	echo json_encode(['error' => 'No tween id in session']);
	exit;
}

// Use existing contact logic so friend data matches sidebar
$gc_path = __DIR__ . '/get_contacts.php';
if (file_exists($gc_path)) {
	// include inside a local scope to reduce side effects
	include $gc_path;
} else {
	echo json_encode(['error' => 'Server misconfiguration', 'detail' => 'contacts include missing']);
	exit;
}
// validate variables
if (!isset($friends) || !is_array($friends))
	$friends = [];
if (!isset($groups) || !is_array($groups))
	$groups = [];

// Fetch blocked users
$query = "SELECT tu.id, tu.username, bu.full_name
          FROM connection c
          JOIN tween_user tu ON c.receiver_id = tu.id
          JOIN bartauser bu ON tu.user_id = bu.id
          WHERE c.sender_id = $tween_id
          AND c.type = 'blocked'
          ORDER BY bu.full_name ASC";
$result = mysqli_query($conn, $query);
if ($result === false) {
	echo json_encode(['error' => 'DB error', 'detail' => mysqli_error($conn)]);
	exit;
}
$blocked = [];
while ($row = mysqli_fetch_assoc($result)) {
	$blocked[] = $row;
}

// Fetch incoming friend requests that are approved by both parents
$query = "SELECT cr.requester_id as request_id, tu.id as tween_id, tu.username, bu.full_name
          FROM connection_request cr
          JOIN tween_user tu ON cr.requester_id = tu.id
          JOIN bartauser bu ON tu.user_id = bu.id
          WHERE cr.receiver_id = $tween_id
          AND cr.requester_parent_approved = 1
          AND cr.receiver_parent_approved = 1
          AND cr.receiver_accepted = 0
          ORDER BY cr.sent_at DESC";
$result = mysqli_query($conn, $query);
if ($result === false) {
	echo json_encode(['error' => 'DB error', 'detail' => mysqli_error($conn)]);
	exit;
}
$pending_requests = [];
while ($row = mysqli_fetch_assoc($result)) {
	$pending_requests[] = $row;
}

echo json_encode([
	'success' => true,
	'friends' => $friends,
	'blocked' => $blocked,
	'pending_requests' => $pending_requests
]);
