<?php
session_start();
header('Content-Type: application/json');

require "../db.php";

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'tween') {
	echo json_encode(['error' => 'Unauthorized']);
	exit;
}

$tween_id = (int)$_SESSION['tween_id'];

if (!isset($_POST['message_id']) || !isset($_POST['text'])) {
	echo json_encode(['error' => 'Missing message_id or text']);
	exit;
}

$message_id = (int)$_POST['message_id'];
$new_text = trim($_POST['text']);

if ($message_id <= 0) {
	echo json_encode(['error' => 'Invalid message_id']);
	exit;
}

if (empty($new_text)) {
	echo json_encode(['error' => 'Message text cannot be empty']);
	exit;
}

// Verify the message exists and is sent by this user
$query = "SELECT m.sender_id FROM message m WHERE m.id = $message_id AND m.is_deleted = 0";
$result = mysqli_query($conn, $query);
if (!$result || mysqli_num_rows($result) == 0) {
	echo json_encode(['error' => 'Message not found', 'message_id' => $message_id]);
	exit;
}

$row = mysqli_fetch_assoc($result);
if ((int)$row['sender_id'] !== $tween_id) {
	echo json_encode(['error' => 'You can only edit your own messages', 'message_id' => $message_id]);
	exit;
}

// Update the message text and mark as edited
$escaped_text = mysqli_real_escape_string($conn, $new_text);
$query = "UPDATE message SET text_content = '$escaped_text', is_edited = 1, edited_at = NOW() WHERE id = $message_id";
$result = mysqli_query($conn, $query);
if (!$result) {
	echo json_encode(['error' => 'Failed to edit message']);
	exit;
}

echo json_encode(['success' => true]);
exit;
