<?php
session_start();
require "../db.php";

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'tween') {
	echo json_encode(['error' => 'Unauthorized']);
	exit;
}

$username = isset($_GET['username']) ? mysqli_real_escape_string($conn, $_GET['username']) : '';

if (empty($username)) {
	echo json_encode(['error' => 'Username required']);
	exit;
}

$query = "SELECT tu.id as tween_id, tu.username, tu.bio, bu.full_name
          FROM tween_user tu
          JOIN bartauser bu ON tu.user_id = bu.id AND bu.role = 'tween'
          WHERE tu.username = '$username'";
$result = mysqli_query($conn, $query);

if (mysqli_num_rows($result) === 0) {
	echo json_encode(['error' => 'User not found']);
	exit;
}

$user = mysqli_fetch_assoc($result);
	// Check whether current tween has a pending outgoing friend request to this user
	$tween_id = isset($_SESSION['tween_id']) ? intval($_SESSION['tween_id']) : 0;
	$target_id = intval($user['tween_id']);
	$pending = false;
	if ($tween_id && $target_id) {
		$rq = "SELECT * FROM connection_request WHERE requester_id = $tween_id AND receiver_id = $target_id AND receiver_accepted = 0";
		$rqres = mysqli_query($conn, $rq);
		if ($rqres && mysqli_num_rows($rqres) > 0) {
			$pending = true;
		}
	}

	$user['request_pending'] = $pending;
echo json_encode($user);
