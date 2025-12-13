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

$timeout = 25; // seconds
$start_time = time();
$sleep_interval = 500000; // 0.5 seconds in microseconds

// If last_active_time is provided, we poll. Otherwise, return immediately (first load).
if ($last_active_time) {
	set_time_limit(0);

	while (time() - $start_time < $timeout) {
		// Check for any updates relevant to the user's contacts list
		// 1. New or edited individual messages
		// 2. New or edited group messages
		// 3. New connections (friends)
		// 4. New group memberships or left groups

		// We use a single query with subqueries to get the overall max timestamp
		$query = "SELECT GREATEST(
			IFNULL((
				SELECT MAX(GREATEST(m.sent_at, IFNULL(m.edited_at, '1000-01-01 00:00:00')))
				FROM message m
				JOIN individual_message im ON m.id = im.message_id
				WHERE (m.sender_id = $tween_id OR im.receiver_id = $tween_id)
			), '1000-01-01 00:00:00'),
			IFNULL((
				SELECT MAX(GREATEST(m.sent_at, IFNULL(m.edited_at, '1000-01-01 00:00:00')))
				FROM message m
				JOIN group_message gm ON m.id = gm.message_id
				JOIN group_member gmem ON gm.group_id = gmem.group_id
				WHERE gmem.member_id = $tween_id
			), '1000-01-01 00:00:00'),
			IFNULL((
				SELECT MAX(added_at) 
				FROM connection 
				WHERE sender_id = $tween_id OR receiver_id = $tween_id
			), '1000-01-01 00:00:00'),
			IFNULL((
				SELECT MAX(joined_at) 
				FROM group_member 
				WHERE member_id = $tween_id
			), '1000-01-01 00:00:00')
		) as current_max_time";

		$result = mysqli_query($conn, $query);
		$row = mysqli_fetch_assoc($result);
		$current_max_time = $row['current_max_time'];

		if ($current_max_time > $last_active_time) {
			break; // Changes detected
		}

		usleep($sleep_interval);

		// Check connection overlap 
		// (optional: ping DB to keep connection alive if needed, but 25s is usually fine)
	}
}

// Re-fetch the contact list using the existing logic
// Note: get_contacts.php expects $tween_id to be set (we have it) 
// and $conn to be active (we have it)
require __DIR__ . '/../tween/get_contacts.php';

// Calculate the new max_time from the fetched data to return to client
// This ensures the client syncs validation with the data it actually receives
$max_time_found = $last_active_time ? $last_active_time : '1000-01-01 00:00:00';

// Check friends
if (isset($friends)) {
	foreach ($friends as &$f) {
		$f['last_message_at'] = isset($f['last_message_at']) && $f['last_message_at'] ? $f['last_message_at'] : null;
		if ($f['last_message_at'] && $f['last_message_at'] > $max_time_found) {
			$max_time_found = $f['last_message_at'];
		}
		// Also check if we should consider connection time? 
		// get_contacts doesn't return connection time in $friends array usually, 
		// but the query logic above checked it. 
		// If a new friend is added but no message, max_time_found needs to update 
		// so we don't loop forever.
	}
}

// Check groups
if (isset($groups)) {
	foreach ($groups as &$g) {
		$g['last_message_at'] = isset($g['last_message_at']) && $g['last_message_at'] ? $g['last_message_at'] : null;
		if ($g['last_message_at'] && $g['last_message_at'] > $max_time_found) {
			$max_time_found = $g['last_message_at'];
		}
	}
}

// If we broke because of a connection/group join but no message update, 
// we must ensure returned last_active_time is >= current_max_time from DB 
// otherwise client will immediately poll and find 'new' data again.
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
