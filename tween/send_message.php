<?php
session_start();
header('Content-Type: application/json');

require "../db.php";

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'tween') {
	echo json_encode(['error' => 'Unauthorized']);
	exit;
}

$tween_id = (int) $_SESSION['tween_id'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
	echo json_encode(['error' => 'Invalid request method']);
	exit;
}

$text = isset($_POST['text']) ? trim($_POST['text']) : '';
if ($text === '') {
	echo json_encode(['error' => 'Message cannot be empty']);
	exit;
}

// enforce reasonable length
if (strlen($text) > 4096) {
	echo json_encode(['error' => 'Message too long']);
	exit;
}

// Optional: enforce daily limit
$limitQuery = "SELECT daily_msg_limit FROM tween_user WHERE id = $tween_id LIMIT 1";
$limitRes = mysqli_query($conn, $limitQuery);
$limitRow = mysqli_fetch_assoc($limitRes);
$dailyLimit = $limitRow ? (int) $limitRow['daily_msg_limit'] : 100;

$todayCountQuery = "SELECT COUNT(*) AS c FROM message WHERE sender_id = $tween_id AND DATE(sent_at) = CURDATE()";
$todayCountRes = mysqli_query($conn, $todayCountQuery);
$todayCountRow = mysqli_fetch_assoc($todayCountRes);
$todayCount = $todayCountRow ? (int) $todayCountRow['c'] : 0;
if ($todayCount >= $dailyLimit) {
	echo json_encode(['error' => 'Daily message limit reached']);
	exit;
}

// decide target (friend or group)
$targetType = null;
$targetId = null;
if (!empty($_POST['u'])) {
	$targetType = 'friend';
	$username = mysqli_real_escape_string($conn, trim($_POST['u']));
	$q = "SELECT id FROM tween_user WHERE username = '$username' LIMIT 1";
	$r = mysqli_query($conn, $q);
	if (!$r || mysqli_num_rows($r) == 0) {
		echo json_encode(['error' => 'Friend not found']);
		exit;
	}
	$tr = mysqli_fetch_assoc($r);
	$targetId = (int) $tr['id'];
	// verify friendship exists and is added
	$check = "SELECT 1 FROM connection c WHERE ((c.sender_id = $tween_id AND c.receiver_id = $targetId) OR (c.sender_id = $targetId AND c.receiver_id = $tween_id)) AND c.type = 'added' LIMIT 1";
	$checkRes = mysqli_query($conn, $check);
	if (!$checkRes || mysqli_num_rows($checkRes) == 0) {
		echo json_encode(['error' => 'Not friends']);
		exit;
	}
} elseif (!empty($_POST['group'])) {
	// Group feature disabled
	echo json_encode(['error' => 'Group messaging is currently disabled']);
	exit;
	// $targetType = 'group';
	// $groupId = (int) $_POST['group'];
	// $q = "SELECT id FROM user_group WHERE id = $groupId AND is_active = 1 LIMIT 1";
	// $r = mysqli_query($conn, $q);
	// if (!$r || mysqli_num_rows($r) == 0) {
	// 	echo json_encode(['error' => 'Group not found']);
	// 	exit;
	// }
	// // verify membership
	// $check = "SELECT 1 FROM group_member gm WHERE gm.group_id = $groupId AND gm.member_id = $tween_id LIMIT 1";
	// $checkRes = mysqli_query($conn, $check);
	// if (!$checkRes || mysqli_num_rows($checkRes) == 0) {
	// 	echo json_encode(['error' => 'Not a group member']);
	// 	exit;
	// }
	// $targetId = $groupId;
} else {
	echo json_encode(['error' => 'Missing target']);
	exit;
}

// Blocked Words Logic
$isClean = 1;
$parentApproval = 'not required';

// Only check blocked words for individual messages (Logic per user requirement "receiver's parent")
if ($targetType === 'friend') {
	// Fetch blocked words for the receiver
	$blockedWordsQuery = "SELECT word FROM blocked_word WHERE tween_id = $targetId";
	$bwRes = mysqli_query($conn, $blockedWordsQuery);
	$blockedWords = [];
	if ($bwRes) {
		while ($bwRow = mysqli_fetch_assoc($bwRes)) {
			$blockedWords[] = strtolower($bwRow['word']);
		}
	}

	if (!empty($blockedWords)) {
		$lowerText = strtolower($text);
		foreach ($blockedWords as $word) {
			// Simple containment check. Could be improved with regex boundary check.
			if (strpos($lowerText, $word) !== false) {
				$isClean = 0;
				$parentApproval = 'pending';
				break;
			}
		}
	}
}

// insert into message
$escapedText = mysqli_real_escape_string($conn, $text);
$insert = "INSERT INTO message (sender_id, text_content, is_clean, parent_approval) VALUES ($tween_id, '$escapedText', $isClean, '$parentApproval')";
if (!mysqli_query($conn, $insert)) {
	echo json_encode(['error' => 'Failed to insert message']);
	exit;
}
$messageId = mysqli_insert_id($conn);

// insert into message type table
if ($targetType === 'friend') {
	$insRel = "INSERT INTO individual_message (message_id, receiver_id) VALUES ($messageId, $targetId)";
} else {
	// $insRel = "INSERT INTO group_message (message_id, group_id) VALUES ($messageId, $targetId)";
	echo json_encode(['error' => 'Group messaging disabled']);
	exit;
}
if (!mysqli_query($conn, $insRel)) {
	// Cleanup message
	mysqli_query($conn, "DELETE FROM message WHERE id = $messageId");
	echo json_encode(['error' => 'Failed to link message to recipient']);
	exit;
}

// Fetch the newly created message joined with sender info
$fetchMsgQuery = "SELECT m.id, m.sender_id, m.text_content, m.sent_at, m.is_edited, m.is_clean, m.parent_approval, tu.username as sender_username, bu.full_name as sender_name
FROM message m
JOIN tween_user tu ON m.sender_id = tu.id
JOIN bartauser bu ON tu.user_id = bu.id
WHERE m.id = $messageId LIMIT 1";
$fetchRes = mysqli_query($conn, $fetchMsgQuery);
if (!$fetchRes || mysqli_num_rows($fetchRes) == 0) {
	echo json_encode(['error' => 'Failed to fetch message']);
	exit;
}
$message = mysqli_fetch_assoc($fetchRes);

$response = ['success' => true, 'message' => $message, 'me_id' => $tween_id, 'target_type' => $targetType, 'target' => ($targetType === 'friend' ? $username : $targetId)];

echo json_encode($response);
exit;
