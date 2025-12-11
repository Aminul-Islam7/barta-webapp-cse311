<?php
session_start();
require "../db.php";

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'tween') {
	echo json_encode(['error' => 'Unauthorized']);
	exit;
}

$tween_id = $_SESSION['tween_id'];
$target_id = isset($_POST['tween_id']) ? (int)$_POST['tween_id'] : 0;

if (!$target_id) {
	echo json_encode(['error' => 'Invalid target']);
	exit;
}

if ($target_id == $tween_id) {
	echo json_encode(['error' => 'Cannot unblock yourself']);
	exit;
}

// Delete the block connection
$query = "DELETE FROM connection WHERE sender_id = $tween_id AND receiver_id = $target_id AND type = 'blocked'";
$result = mysqli_query($conn, $query);

if ($result) {
	echo json_encode(['success' => true]);
} else {
	echo json_encode(['error' => 'Failed to unblock user']);
}
