<?php
session_start();
header('Content-Type: application/json');

require "../db.php";

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'tween') {
	echo json_encode(['error' => 'Unauthorized']);
	exit;
}

$tween_id = (int)$_SESSION['tween_id'];

// Only enable long polling if 'since' parameter is explicitly provided
$is_polling = isset($_GET['since']);
$since = $is_polling ? (int)$_GET['since'] : 0;
$last_active_time = isset($_GET['last_active_time']) ? urldecode($_GET['last_active_time']) : null;

// Release session lock to prevent blocking other requests (like send_message)
session_write_close();

// Long polling configuration (only used when polling)
$LONG_POLL_TIMEOUT = 25; // seconds
$LONG_POLL_SLEEP_MICRO = 500000; // 0.5 seconds

$response = ['messages' => [], 'contact' => null, 'type' => null];

if (isset($_GET['u'])) {
	// Fetch by friend's username and check if the username exists
	$username = mysqli_real_escape_string($conn, urldecode($_GET['u']));
	$query = "SELECT tu.id as tween_id, tu.username, tu.bio, bu.full_name, bu.role
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
	// Make sure this maps to a tween bartauser row — if not, treat as not found
	if (!isset($friend['role']) || $friend['role'] !== 'tween') {
		echo json_encode(['error' => 'Friend not found']);
		exit;
	}

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

	// Decode bio for proper display
	$friend['bio'] = html_entity_decode($friend['bio']);

	// Fetch messages between the tween and the friend
	$query = "SELECT m.id, m.sender_id, m.text_content, m.sent_at, m.is_edited, m.edited_at, tu.username as sender_username, bu.full_name as sender_name
          FROM message m
          JOIN individual_message im ON m.id = im.message_id
          JOIN tween_user tu ON m.sender_id = tu.id
          JOIN bartauser bu ON tu.user_id = bu.id
          WHERE ((m.sender_id = $tween_id AND im.receiver_id = $friend_id) OR (m.sender_id = $friend_id AND im.receiver_id = $tween_id))
          AND m.is_deleted = 0
		  AND m.id > $since
          ORDER BY m.sent_at ASC";

	if ($is_polling) {
		// Determine the time watermark for edits/deletes
		$sinceTime = $last_active_time;
		if (!$sinceTime && $since > 0) {
			// Fallback: derive from message ID if no last_active_time provided
			$sinceQuery = "SELECT sent_at FROM message WHERE id = $since LIMIT 1";
			$sinceResult = mysqli_query($conn, $sinceQuery);
			if ($sinceResult && mysqli_num_rows($sinceResult) > 0) {
				$sinceRow = mysqli_fetch_assoc($sinceResult);
				$sinceTime = $sinceRow['sent_at'];
			}
		}

		set_time_limit(0);
		ignore_user_abort(true);
		$start = microtime(true);
		while ((microtime(true) - $start) < $LONG_POLL_TIMEOUT) {
			// Check for new messages
			$result = mysqli_query($conn, $query);
			if ($result && mysqli_num_rows($result) > 0) break;

			// Check for edits/deletes since our last seen timestamp
			if ($sinceTime) {
				$changeQuery = "SELECT 1 FROM message m JOIN individual_message im ON m.id = im.message_id 
								WHERE ((m.sender_id = $tween_id AND im.receiver_id = $friend_id) OR (m.sender_id = $friend_id AND im.receiver_id = $tween_id)) 
								AND ((m.is_deleted = 1 AND m.edited_at > '$sinceTime') OR (m.is_edited = 1 AND m.edited_at > '$sinceTime')) LIMIT 1";
				$changeRes = mysqli_query($conn, $changeQuery);
				if ($changeRes && mysqli_num_rows($changeRes) > 0) break;
			}
			usleep($LONG_POLL_SLEEP_MICRO);
		}
	} else {
		$result = mysqli_query($conn, $query);
	}

	$messages = [];
	$max_time = $last_active_time;
	while ($row = mysqli_fetch_assoc($result)) {
		$messages[] = $row;
		if (!$max_time || $row['sent_at'] > $max_time) $max_time = $row['sent_at'];
		if ($row['is_edited'] && $row['edited_at'] > $max_time) $max_time = $row['edited_at'];
	}

	// Check for deleted and edited messages (if polling)
	$deleted_ids = [];
	$edited_messages = [];
	if ($is_polling) {
		// Re-calculate sinceTime in case it wasn't set before loop (e.g. first poll)
		$sinceTime = $last_active_time;
		if (!$sinceTime && $since > 0) {
			$sinceQuery = "SELECT sent_at FROM message WHERE id = $since LIMIT 1";
			$sinceResult = mysqli_query($conn, $sinceQuery);
			if ($sinceResult && mysqli_num_rows($sinceResult) > 0) {
				$sinceRow = mysqli_fetch_assoc($sinceResult);
				$sinceTime = $sinceRow['sent_at'];
			}
		}

		if ($sinceTime) {
			// Deleted messages
			$delQuery = "SELECT m.id, m.edited_at FROM message m
			          JOIN individual_message im ON m.id = im.message_id
			          WHERE ((m.sender_id = $tween_id AND im.receiver_id = $friend_id) OR (m.sender_id = $friend_id AND im.receiver_id = $tween_id))
			          AND m.is_deleted = 1 AND m.edited_at > '$sinceTime'";
			$delResult = mysqli_query($conn, $delQuery);
			if ($delResult) {
				while ($delRow = mysqli_fetch_assoc($delResult)) {
					$deleted_ids[] = (int)$delRow['id'];
					if ($delRow['edited_at'] > $max_time) $max_time = $delRow['edited_at'];
				}
			}
			// Edited messages
			$editQuery = "SELECT m.id, m.text_content, m.edited_at FROM message m
			          JOIN individual_message im ON m.id = im.message_id
			          WHERE ((m.sender_id = $tween_id AND im.receiver_id = $friend_id) OR (m.sender_id = $friend_id AND im.receiver_id = $tween_id))
			          AND m.is_deleted = 0 AND m.is_edited = 1 AND m.edited_at > '$sinceTime'";
			$editResult = mysqli_query($conn, $editQuery);
			if ($editResult) {
				while ($editRow = mysqli_fetch_assoc($editResult)) {
					$edited_messages[] = ['id' => (int)$editRow['id'], 'text_content' => $editRow['text_content']];
					if ($editRow['edited_at'] > $max_time) $max_time = $editRow['edited_at'];
				}
			}
		}
	}

	$response['messages'] = $messages;
	$response['deleted_message_ids'] = $deleted_ids;
	$response['edited_messages'] = $edited_messages;
	$response['contact'] = $friend;
	$response['type'] = 'friend';
	$response['me_id'] = $tween_id;
	$response['last_active_time'] = $max_time;

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
	$query = "SELECT m.id, m.sender_id, m.text_content, m.sent_at, m.is_edited, m.edited_at, tu.username as sender_username, bu.full_name as sender_name
          FROM message m
          JOIN group_message gm ON m.id = gm.message_id
          JOIN tween_user tu ON m.sender_id = tu.id
          JOIN bartauser bu ON tu.user_id = bu.id
          WHERE gm.group_id = $group_id AND m.is_deleted = 0
		  AND m.id > $since
          ORDER BY m.sent_at ASC";

	if ($is_polling) {
		// Determine the time watermark for edits/deletes
		$sinceTime = $last_active_time;
		if (!$sinceTime && $since > 0) {
			// Fallback: derive from message ID if no last_active_time provided
			$sinceQuery = "SELECT sent_at FROM message WHERE id = $since LIMIT 1";
			$sinceResult = mysqli_query($conn, $sinceQuery);
			if ($sinceResult && mysqli_num_rows($sinceResult) > 0) {
				$sinceRow = mysqli_fetch_assoc($sinceResult);
				$sinceTime = $sinceRow['sent_at'];
			}
		}

		set_time_limit(0);
		ignore_user_abort(true);
		$start = microtime(true);
		while ((microtime(true) - $start) < $LONG_POLL_TIMEOUT) {
			$result = mysqli_query($conn, $query);
			if ($result && mysqli_num_rows($result) > 0) break;

			if ($sinceTime) {
				$changeQuery = "SELECT 1 FROM message m JOIN group_message gm ON m.id = gm.message_id 
								WHERE gm.group_id = $group_id 
								AND ((m.is_deleted = 1 AND m.edited_at > '$sinceTime') OR (m.is_edited = 1 AND m.edited_at > '$sinceTime')) LIMIT 1";
				$changeRes = mysqli_query($conn, $changeQuery);
				if ($changeRes && mysqli_num_rows($changeRes) > 0) break;
			}
			usleep($LONG_POLL_SLEEP_MICRO);
		}
	} else {
		$result = mysqli_query($conn, $query);
	}

	$messages = [];
	$max_time = $last_active_time;
	while ($row = mysqli_fetch_assoc($result)) {
		$messages[] = $row;
		if (!$max_time || $row['sent_at'] > $max_time) $max_time = $row['sent_at'];
		if ($row['is_edited'] && $row['edited_at'] > $max_time) $max_time = $row['edited_at'];
	}

	// Check for deleted and edited messages (if polling)
	$deleted_ids = [];
	$edited_messages = [];
	if ($is_polling) {
		// Re-calculate sinceTime
		$sinceTime = $last_active_time;
		if (!$sinceTime && $since > 0) {
			$sinceQuery = "SELECT sent_at FROM message WHERE id = $since LIMIT 1";
			$sinceResult = mysqli_query($conn, $sinceQuery);
			if ($sinceResult && mysqli_num_rows($sinceResult) > 0) {
				$sinceRow = mysqli_fetch_assoc($sinceResult);
				$sinceTime = $sinceRow['sent_at'];
			}
		}

		if ($sinceTime) {
			// Deleted messages
			$delQuery = "SELECT m.id, m.edited_at FROM message m
			          JOIN group_message gm ON m.id = gm.message_id
			          WHERE gm.group_id = $group_id AND m.is_deleted = 1 AND m.edited_at > '$sinceTime'";
			$delResult = mysqli_query($conn, $delQuery);
			if ($delResult) {
				while ($delRow = mysqli_fetch_assoc($delResult)) {
					$deleted_ids[] = (int)$delRow['id'];
					if ($delRow['edited_at'] > $max_time) $max_time = $delRow['edited_at'];
				}
			}
			// Edited messages
			$editQuery = "SELECT m.id, m.text_content, m.edited_at FROM message m
			          JOIN group_message gm ON m.id = gm.message_id
			          WHERE gm.group_id = $group_id AND m.is_deleted = 0 AND m.is_edited = 1 AND m.edited_at > '$sinceTime'";
			$editResult = mysqli_query($conn, $editQuery);
			if ($editResult) {
				while ($editRow = mysqli_fetch_assoc($editResult)) {
					$edited_messages[] = ['id' => (int)$editRow['id'], 'text_content' => $editRow['text_content']];
					if ($editRow['edited_at'] > $max_time) $max_time = $editRow['edited_at'];
				}
			}
		}
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
	$response['deleted_message_ids'] = $deleted_ids;
	$response['edited_messages'] = $edited_messages;
	$response['contact'] = $group;
	$response['contact']['members'] = $members;
	$response['type'] = 'group';
	$response['me_id'] = $tween_id;
	$response['last_active_time'] = $max_time;

	echo json_encode($response);
	exit;
}

echo json_encode(['error' => 'Missing parameter']);
exit;
