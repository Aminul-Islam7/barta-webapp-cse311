<?php
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/../db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'tween') {
	echo json_encode(['error' => 'Unauthorized']);
	exit;
}

$tween_id = (int) $_SESSION['tween_id'];

// Get last active time from client (sent as string, e.g., '2023-01-01 12:00:00')
$last_active_time = isset($_GET['last_active_time']) ? urldecode($_GET['last_active_time']) : null;

// Allow other requests to proceed while this script waits
session_write_close();

$timeout = 25;
$start_time = time();
$sleep_interval = 500000;

if ($last_active_time) {
	set_time_limit(0);

	while (time() - $start_time < $timeout) {
		$query = "SELECT GREATEST(
			IFNULL((
				SELECT MAX(GREATEST(m.sent_at, IFNULL(m.edited_at, '1000-01-01 00:00:00')))
				FROM message m
				JOIN individual_message im ON m.id = im.message_id
				WHERE (m.sender_id = $tween_id OR im.receiver_id = $tween_id)
			), '1000-01-01 00:00:00'),
			IFNULL((
				SELECT MAX(added_at) 
				FROM connection 
				WHERE sender_id = $tween_id OR receiver_id = $tween_id
			), '1000-01-01 00:00:00')
		) as current_max_time";

		$result = mysqli_query($conn, $query);
		$row = mysqli_fetch_assoc($result);
		$current_max_time = $row['current_max_time'];

		if ($current_max_time > $last_active_time) {
			break; // Changes detected
		}

		usleep($sleep_interval);


	}
}

require __DIR__ . '/../tween/get_contacts.php';
$max_time_found = $last_active_time ? $last_active_time : '1000-01-01 00:00:00';

if (isset($friends)) {
	foreach ($friends as &$f) {
		$f['last_message_at'] = isset($f['last_message_at']) && $f['last_message_at'] ? $f['last_message_at'] : null;
		if ($f['last_message_at'] && $f['last_message_at'] > $max_time_found) {
			$max_time_found = $f['last_message_at'];
		}
	}
}

if (isset($groups)) {
	foreach ($groups as &$g) {
		$g['last_message_at'] = isset($g['last_message_at']) && $g['last_message_at'] ? $g['last_message_at'] : null;
		if ($g['last_message_at'] && $g['last_message_at'] > $max_time_found) {
			$max_time_found = $g['last_message_at'];
		}
	}
}

if (isset($current_max_time) && $current_max_time > $max_time_found) {
	$max_time_found = $current_max_time;
}

$response = [
	'friends' => $friends ?? [],
	'groups' => $groups ?? [],
	'last_active_time' => $max_time_found
];

echo json_encode($response);
exit;
