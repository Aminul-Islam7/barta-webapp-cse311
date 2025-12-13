<?php


require_once __DIR__ . "/../db.php";

$tween_id = $_SESSION['tween_id'];

$query = "SELECT c.sender_id, c.receiver_id,
		  CASE WHEN c.sender_id = $tween_id THEN c.receiver_id ELSE c.sender_id END AS tween_id,
		  tu.username, tu.bio, bu.full_name
		  FROM connection c
		  JOIN tween_user tu ON tu.id = (CASE WHEN c.sender_id = $tween_id THEN c.receiver_id ELSE c.sender_id END)
		  JOIN bartauser bu ON tu.user_id = bu.id AND bu.role = 'tween'
		  WHERE (c.sender_id = $tween_id OR c.receiver_id = $tween_id) AND c.type = 'added'";
$result = mysqli_query($conn, $query);
$friends = [];
while ($row = mysqli_fetch_assoc($result)) {
	$friend_id = (int) $row['tween_id'];
	$lastMsgQuery = "SELECT m.sender_id, m.text_content, m.sent_at, m.is_clean, m.parent_approval
	                 FROM message m
	                 JOIN individual_message im ON m.id = im.message_id
	                 WHERE ((m.sender_id = $tween_id AND im.receiver_id = $friend_id) OR (m.sender_id = $friend_id AND im.receiver_id = $tween_id))
	                 AND m.is_deleted = 0
	                 ORDER BY m.sent_at DESC
	                 LIMIT 1";
	$lastRes = mysqli_query($conn, $lastMsgQuery);
	if ($lastRes && mysqli_num_rows($lastRes) > 0) {
		$lastRow = mysqli_fetch_assoc($lastRes);

		$displayText = $lastRow['text_content'];
		if ((int) $lastRow['is_clean'] === 0 && (int) $lastRow['sender_id'] !== $tween_id) {
			if ($lastRow['parent_approval'] === 'pending') {
				$displayText = 'Message pending approval...';
			} elseif ($lastRow['parent_approval'] === 'rejected') {
				$displayText = 'Message blocked.';
			}
		}

		$row['last_message_text'] = $displayText;
		$row['last_message_sender_id'] = (int) $lastRow['sender_id'];
		$row['last_message_at'] = $lastRow['sent_at'];
	} else {
		$row['last_message_text'] = null;
		$row['last_message_sender_id'] = null;
		$row['last_message_at'] = null;
	}
	$friends[] = $row;
}

if (count($friends) > 1) {
	usort($friends, function ($a, $b) {
		$at = isset($a['last_message_at']) && $a['last_message_at'] ? strtotime($a['last_message_at']) : 0;
		$bt = isset($b['last_message_at']) && $b['last_message_at'] ? strtotime($b['last_message_at']) : 0;
		return $bt <=> $at;
	});
}

$groups = [];
