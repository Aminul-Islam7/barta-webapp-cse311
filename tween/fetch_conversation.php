<?php
session_start();
header('Content-Type: application/json');

require "../db.php";

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'tween') {
	echo json_encode(['error' => 'Unauthorized']);
	exit;
}

$tween_id = (int)$_SESSION['tween_id'];

$since = isset($_GET['since']) ? (int)$_GET['since'] : 0;

// Release session lock to prevent blocking other requests (like send_message)
session_write_close();

// Long polling configuration (only used when since > 0)
$LONG_POLL_TIMEOUT = 25; // seconds
$LONG_POLL_SLEEP_MICRO = 500000; // 0.5 seconds

$response = ['messages' => [], 'contact' => null, 'type' => null];

if (isset($_GET['u'])) {
	// Fetch by friend's username and check if the username exists
	$username = mysqli_real_escape_string($conn, urldecode($_GET['u']));
	$query = "SELECT tu.id as tween_id, tu.username, tu.bio, bu.full_name
          FROM tween_user tu
          JOIN bartauser bu ON tu.user_id = bu.id
          WHERE tu.username = '$username'
          LIMIT 1";
	$result = mysqli_query($conn, $query);
	if (mysqli_num_rows($result) == 0) {
		echo json_encode(['error' => 'Friend not found']);
		exit;
	}
	$friend = mysqli_fetch_assoc($result);
	$friend_id = (int)$friend['tween_id'];

	// Verify friendship from connection table
	$checkQuery = "SELECT *
          FROM connection c
          WHERE ((c.sender_id = $tween_id AND c.receiver_id = $friend_id) OR (c.sender_id = $friend_id AND c.receiver_id = $tween_id))
          AND c.type = 'added'
          LIMIT 1";
	$checkRes = mysqli_query($conn, $checkQuery);

	if (!$checkRes || mysqli_num_rows($checkRes) == 0) {
		echo json_encode(['error' => 'Not friends']);
		exit;
	}

	// Fetch messages between the tween and the friend
	// If since > 0, hold the request up to $LONG_POLL_TIMEOUT and return when new messages arrive
	$query = "SELECT m.id, m.sender_id, m.text_content, m.sent_at, m.is_edited, tu.username as sender_username, bu.full_name as sender_name
          FROM message m
          JOIN individual_message im ON m.id = im.message_id
          JOIN tween_user tu ON m.sender_id = tu.id
          JOIN bartauser bu ON tu.user_id = bu.id
          WHERE ((m.sender_id = $tween_id AND im.receiver_id = $friend_id) OR (m.sender_id = $friend_id AND im.receiver_id = $tween_id))
          AND m.is_deleted = 0
		  AND m.id > $since
          ORDER BY m.sent_at ASC";
	// If client is asking only for changes, enable long-polling wait loop
	if ($since > 0) {
		set_time_limit(0);
		ignore_user_abort(true);
		$start = microtime(true);
		while ((microtime(true) - $start) < $LONG_POLL_TIMEOUT) {
			$result = mysqli_query($conn, $query);
			if ($result && mysqli_num_rows($result) > 0) break;
			usleep($LONG_POLL_SLEEP_MICRO);
		}
	} else {
		$result = mysqli_query($conn, $query);
	}
	$messages = [];
	while ($row = mysqli_fetch_assoc($result)) {
		$messages[] = $row;
	}

	$response['messages'] = $messages;
	$response['contact'] = $friend;
	$response['type'] = 'friend';
	$response['me_id'] = $tween_id;

	echo json_encode($response);
	exit;
}

if (isset($_GET['group'])) {
	// Fetch by group ID and check if the group exists
	$group_id = (int)$_GET['group'];
	$query = "SELECT ug.id, ug.group_name, ug.color
				FROM user_group ug
				WHERE ug.id = $group_id AND ug.is_active = 1";
	$result = mysqli_query($conn, $query);
	if (mysqli_num_rows($result) == 0) {
		echo json_encode(['error' => 'Group not found']);
		exit;
	}
	$group = mysqli_fetch_assoc($result);

	// Verify membership
	$check = "SELECT 1
          FROM group_member gm
          WHERE gm.group_id = $group_id AND gm.member_id = $tween_id
          LIMIT 1";
	$checkRes = mysqli_query($conn, $check);
	if (!$checkRes || mysqli_num_rows($checkRes) == 0) {
		echo json_encode(['error' => 'Not a group member']);
		exit;
	}

	// Fetch group messages
	$query = "SELECT m.id, m.sender_id, m.text_content, m.sent_at, m.is_edited, tu.username as sender_username, bu.full_name as sender_name
          FROM message m
          JOIN group_message gm ON m.id = gm.message_id
          JOIN tween_user tu ON m.sender_id = tu.id
          JOIN bartauser bu ON tu.user_id = bu.id
          WHERE gm.group_id = $group_id AND m.is_deleted = 0
		  AND m.id > $since
          ORDER BY m.sent_at ASC";
	if ($since > 0) {
		set_time_limit(0);
		ignore_user_abort(true);
		$start = microtime(true);
		while ((microtime(true) - $start) < $LONG_POLL_TIMEOUT) {
			$result = mysqli_query($conn, $query);
			if ($result && mysqli_num_rows($result) > 0) break;
			usleep($LONG_POLL_SLEEP_MICRO);
		}
	} else {
		$result = mysqli_query($conn, $query);
	}
	$messages = [];
	while ($row = mysqli_fetch_assoc($result)) {
		$messages[] = $row;
	}

	$query = "SELECT tu.username, bu.full_name
				FROM group_member gm
				JOIN tween_user tu ON gm.member_id = tu.id
				JOIN bartauser bu ON tu.user_id = bu.id
				WHERE gm.group_id = $group_id";
	$result = mysqli_query($conn, $query);
	$members = [];
	while ($row = mysqli_fetch_assoc($result)) {
		$members[] = $row;
	}

	$response['messages'] = $messages;
	$response['contact'] = $group;
	$response['contact']['members'] = $members;
	$response['type'] = 'group';
	$response['me_id'] = $tween_id;

	echo json_encode($response);
	exit;
}

echo json_encode(['error' => 'Missing parameter']);
exit;
