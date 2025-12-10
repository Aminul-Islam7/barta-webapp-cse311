<?php
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/../db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'tween') {
	echo json_encode(['error' => 'Unauthorized']);
	exit;
}

// reuse get_contacts logic to build arrays
require_once __DIR__ . '/../tween/get_contacts.php';

foreach ($friends as &$f) {
	$f['last_message_at'] = isset($f['last_message_at']) && $f['last_message_at'] ? $f['last_message_at'] : null;
}
foreach ($groups as &$g) {
	$g['last_message_at'] = isset($g['last_message_at']) && $g['last_message_at'] ? $g['last_message_at'] : null;
}

$response = ['friends' => $friends, 'groups' => $groups];

echo json_encode($response);
exit;
