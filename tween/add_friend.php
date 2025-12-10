<?php
session_start();
require "../db.php";

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'tween') {
	echo json_encode(['error' => 'Unauthorized']);
	exit;
}

$tween_id = $_SESSION['tween_id'];
$username = isset($_POST['username']) ? mysqli_real_escape_string($conn, $_POST['username']) : '';

if (empty($username)) {
	echo json_encode(['error' => 'Username required']);
	exit;
}

// Get target tween ID
$query = "SELECT id FROM tween_user WHERE username = '$username'";
$result = mysqli_query($conn, $query);

if (mysqli_num_rows($result) === 0) {
	echo json_encode(['error' => 'User not found']);
	exit;
}

$target = mysqli_fetch_assoc($result);
$target_id = $target['id'];

if ($target_id == $tween_id) {
	echo json_encode(['error' => 'Cannot add yourself']);
	exit;
}

// Check connection table for existing friendship or block
$conn_query = "SELECT type FROM connection 
               WHERE (sender_id = $tween_id AND receiver_id = $target_id) 
                  OR (sender_id = $target_id AND receiver_id = $tween_id)";
$conn_result = mysqli_query($conn, $conn_query);

if (mysqli_num_rows($conn_result) > 0) {
	$existing = mysqli_fetch_assoc($conn_result);
	if ($existing['type'] === 'added') {
		echo json_encode(['error' => 'Already friends']);
		exit;
	} else if ($existing['type'] === 'blocked') {
		echo json_encode(['error' => 'Cannot send friend request']); // Blocked
		exit;
	}
}

// Check connection_request table for pending requests
$req_query = "SELECT * FROM connection_request 
              WHERE (requester_id = $tween_id AND receiver_id = $target_id) 
                 OR (requester_id = $target_id AND receiver_id = $tween_id)";
$req_result = mysqli_query($conn, $req_query);

if (mysqli_num_rows($req_result) > 0) {
	$req = mysqli_fetch_assoc($req_result);
	// If request exists and not yet accepted (receiver_accepted is 0 by default)
	if ($req['receiver_accepted'] == 0) {
		echo json_encode(['error' => 'Friend request already pending']);
		exit;
	}
}

// Create friend request
$insert_query = "INSERT INTO connection_request (requester_id, receiver_id, sent_at) 
                 VALUES ($tween_id, $target_id, NOW())";

if (mysqli_query($conn, $insert_query)) {
	echo json_encode(['success' => true, 'message' => 'Friend request sent']);
} else {
	echo json_encode(['error' => 'Failed to send friend request']);
}
