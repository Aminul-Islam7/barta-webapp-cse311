<?php
session_start();
header('Content-Type: application/json');

require "../db.php";

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'tween') {
	echo json_encode(['error' => 'Unauthorized']);
	exit;
}

$tween_id = (int)$_SESSION['tween_id'];

if (!isset($_POST['message_id'])) {
	echo json_encode(['error' => 'Missing message_id']);
	exit;
}

$message_id = (int)$_POST['message_id'];
if ($message_id <= 0) {
	echo json_encode(['error' => 'Invalid message_id', 'message_id' => $_POST['message_id']]);
	exit;
}

// Verify the message exists and is sent by this user
$query = "SELECT m.sender_id FROM message m WHERE m.id = $message_id AND m.is_deleted = 0";
$result = mysqli_query($conn, $query);
if (!$result || mysqli_num_rows($result) == 0) {
	// Additional diagnostic: check if message exists but is deleted or otherwise
	$checkExist = mysqli_query($conn, "SELECT id, is_deleted FROM message WHERE id = $message_id LIMIT 1");
	$existsInfo = null;
	if ($checkExist && mysqli_num_rows($checkExist) > 0) {
		$existsInfo = mysqli_fetch_assoc($checkExist);
	}
	echo json_encode(['error' => 'Message not found', 'message_id' => $message_id, 'exists' => $existsInfo]);
	exit;
}

$row = mysqli_fetch_assoc($result);
if ((int)$row['sender_id'] !== $tween_id) {
	echo json_encode(['error' => 'You can only delete your own messages', 'message_id' => $message_id]);
	exit;
}

// Mark the message as deleted and update edited_at for tracking
$query = "UPDATE message SET is_deleted = 1, edited_at = NOW() WHERE id = $message_id";
$result = mysqli_query($conn, $query);
if (!$result) {
	echo json_encode(['error' => 'Failed to delete message']);
	exit;
}

echo json_encode(['success' => true]);
exit;
