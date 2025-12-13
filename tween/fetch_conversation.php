<?php
session_start();
header('Content-Type: application/json');

require "../db.php";

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'tween') {
	echo json_encode(['error' => 'Unauthorized']);
	exit;
}

$tween_id = (int) $_SESSION['tween_id'];

// Only enable long polling if 'since' parameter is explicitly provided
$is_polling = isset($_GET['since']);
$since = $is_polling ? (int) $_GET['since'] : 0;
$last_active_time = isset($_GET['last_active_time']) ? urldecode($_GET['last_active_time']) : null;

session_write_close();

$LONG_POLL_TIMEOUT = 25;
$LONG_POLL_SLEEP_MICRO = 500000;

$response = ['messages' => [], 'contact' => null, 'type' => null];

if (isset($_GET['u'])) {
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
	$friend_id = (int) $friend['tween_id'];
	if (!isset($friend['role']) || $friend['role'] !== 'tween') {
		echo json_encode(['error' => 'Friend not found']);
		exit;
	}

	$blockQuery = "SELECT *
          FROM connection c
          WHERE ((c.sender_id = $tween_id AND c.receiver_id = $friend_id) OR (c.sender_id = $friend_id AND c.receiver_id = $tween_id))
          AND c.type = 'blocked'
          LIMIT 1";
	$blockRes = mysqli_query($conn, $blockQuery);
	if ($blockRes && mysqli_num_rows($blockRes) > 0) {
		echo json_encode(['error' => 'Friend not found']);
		exit;
	}

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

	$friend['bio'] = html_entity_decode($friend['bio']);

	$query = "SELECT m.id, m.sender_id, m.text_content, m.sent_at, m.is_edited, m.edited_at, m.is_clean, m.parent_approval, tu.username as sender_username, bu.full_name as sender_name
          FROM message m
          JOIN individual_message im ON m.id = im.message_id
          JOIN tween_user tu ON m.sender_id = tu.id
          JOIN bartauser bu ON tu.user_id = bu.id
          WHERE ((m.sender_id = $tween_id AND im.receiver_id = $friend_id) OR (m.sender_id = $friend_id AND im.receiver_id = $tween_id))
          AND m.is_deleted = 0
		  AND m.id > $since
          ORDER BY m.sent_at ASC";

	if ($is_polling) {
		$sinceTime = $last_active_time;
		if (!$sinceTime && $since > 0) {
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
			if ($result && mysqli_num_rows($result) > 0)
				break;

			if ($sinceTime) {
				$changeQuery = "SELECT 1 FROM message m JOIN individual_message im ON m.id = im.message_id 
								WHERE ((m.sender_id = $tween_id AND im.receiver_id = $friend_id) OR (m.sender_id = $friend_id AND im.receiver_id = $tween_id)) 
								AND ((m.is_deleted = 1 AND m.edited_at > '$sinceTime') OR (m.is_edited = 1 AND m.edited_at > '$sinceTime')) LIMIT 1";
				$changeRes = mysqli_query($conn, $changeQuery);
				if ($changeRes && mysqli_num_rows($changeRes) > 0)
					break;
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
		if (!$max_time || $row['sent_at'] > $max_time)
			$max_time = $row['sent_at'];
		if ($row['is_edited'] && $row['edited_at'] > $max_time)
			$max_time = $row['edited_at'];
	}

	$deleted_ids = [];
	$edited_messages = [];
	if ($is_polling) {
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
			$delQuery = "SELECT m.id, m.edited_at FROM message m
			          JOIN individual_message im ON m.id = im.message_id
			          WHERE ((m.sender_id = $tween_id AND im.receiver_id = $friend_id) OR (m.sender_id = $friend_id AND im.receiver_id = $tween_id))
			          AND m.is_deleted = 1 AND m.edited_at > '$sinceTime'";
			$delResult = mysqli_query($conn, $delQuery);
			if ($delResult) {
				while ($delRow = mysqli_fetch_assoc($delResult)) {
					$deleted_ids[] = (int) $delRow['id'];
					if ($delRow['edited_at'] > $max_time)
						$max_time = $delRow['edited_at'];
				}
			}
			$editQuery = "SELECT m.id, m.text_content, m.edited_at, m.is_clean, m.parent_approval FROM message m
			          JOIN individual_message im ON m.id = im.message_id
			          WHERE ((m.sender_id = $tween_id AND im.receiver_id = $friend_id) OR (m.sender_id = $friend_id AND im.receiver_id = $tween_id))
			          AND m.is_deleted = 0 AND m.is_edited = 1 AND m.edited_at > '$sinceTime'";
			$editResult = mysqli_query($conn, $editQuery);
			if ($editResult) {
				while ($editRow = mysqli_fetch_assoc($editResult)) {
					$edited_messages[] = [
						'id' => (int) $editRow['id'],
						'text_content' => $editRow['text_content'],
						'is_clean' => $editRow['is_clean'],
						'parent_approval' => $editRow['parent_approval']
					];
					if ($editRow['edited_at'] > $max_time)
						$max_time = $editRow['edited_at'];
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

	$limitQuery = "SELECT daily_msg_limit FROM tween_user WHERE id = $tween_id LIMIT 1";
	$limitRes = mysqli_query($conn, $limitQuery);
	$limitRow = mysqli_fetch_assoc($limitRes);
	$dailyLimit = $limitRow ? (int) $limitRow['daily_msg_limit'] : 100;

	$todayCountQuery = "SELECT COUNT(*) AS c FROM message WHERE sender_id = $tween_id AND DATE(sent_at) = CURDATE()";
	$todayCountRes = mysqli_query($conn, $todayCountQuery);
	$todayCountRow = mysqli_fetch_assoc($todayCountRes);
	$todayCount = $todayCountRow ? (int) $todayCountRow['c'] : 0;

	$response['daily_limit'] = $dailyLimit;
	$response['today_count'] = $todayCount;
	$response['limit_reached'] = ($todayCount >= $dailyLimit);

	echo json_encode($response);
	exit;
}

if (isset($_GET['group'])) {
	echo json_encode(['error' => 'Group messaging is currently disabled']);
	exit;


}

echo json_encode(['error' => 'Missing parameter']);
exit;
