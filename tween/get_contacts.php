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
	$groups[] = $row;
}
