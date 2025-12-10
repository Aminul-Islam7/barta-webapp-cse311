<?php
// get_contacts.php - Fetch friends and groups for tween dashboard

require_once __DIR__ . "/../db.php";

$tween_id = $_SESSION['tween_id'];

// Fetch friends
$query = "SELECT c.sender_id, c.receiver_id, tu.id as tween_id, tu.username, tu.bio, bu.full_name
          FROM connection c
          JOIN tween_user tu ON (tu.id = c.sender_id OR tu.id = c.receiver_id) AND tu.id != $tween_id
          JOIN bartauser bu ON tu.user_id = bu.id
          WHERE (c.sender_id = $tween_id OR c.receiver_id = $tween_id) AND c.type = 'added'";
$result = mysqli_query($conn, $query);
$friends = [];
while ($row = mysqli_fetch_assoc($result)) {
	// Fetch last message for this friend conversation
	$friend_id = (int)$row['tween_id'];
	$lastMsgQuery = "SELECT m.sender_id, m.text_content, m.sent_at
	                 FROM message m
	                 JOIN individual_message im ON m.id = im.message_id
	                 WHERE ((m.sender_id = $tween_id AND im.receiver_id = $friend_id) OR (m.sender_id = $friend_id AND im.receiver_id = $tween_id))
	                 AND m.is_deleted = 0
	                 ORDER BY m.sent_at DESC
	                 LIMIT 1";
	$lastRes = mysqli_query($conn, $lastMsgQuery);
	if ($lastRes && mysqli_num_rows($lastRes) > 0) {
		$lastRow = mysqli_fetch_assoc($lastRes);
		$row['last_message_text'] = $lastRow['text_content'];
		$row['last_message_sender_id'] = (int)$lastRow['sender_id'];
		$row['last_message_at'] = $lastRow['sent_at'];
	} else {
		$row['last_message_text'] = null;
		$row['last_message_sender_id'] = null;
		$row['last_message_at'] = null;
	}
	$friends[] = $row;
}

// Fetch groups
$query = "SELECT ug.id, ug.group_name, ug.color
          FROM user_group ug
          JOIN group_member gm ON ug.id = gm.group_id
          WHERE gm.member_id = $tween_id AND ug.is_active = 1";
$result = mysqli_query($conn, $query);
$groups = [];
while ($row = mysqli_fetch_assoc($result)) {
	// Fetch last message for this group
	$group_id = (int)$row['id'];
	$lastMsgQuery = "SELECT m.sender_id, m.text_content, m.sent_at
	                 FROM message m
	                 JOIN group_message gm ON m.id = gm.message_id
	                 WHERE gm.group_id = $group_id AND m.is_deleted = 0
	                 ORDER BY m.sent_at DESC
	                 LIMIT 1";
	$lastRes = mysqli_query($conn, $lastMsgQuery);
	if ($lastRes && mysqli_num_rows($lastRes) > 0) {
		$lastRow = mysqli_fetch_assoc($lastRes);
		$row['last_message_text'] = $lastRow['text_content'];
		$row['last_message_sender_id'] = (int)$lastRow['sender_id'];
		$row['last_message_at'] = $lastRow['sent_at'];
	} else {
		$row['last_message_text'] = null;
		$row['last_message_sender_id'] = null;
		$row['last_message_at'] = null;
	}
	$groups[] = $row;
}
