<?php
session_start();
require "../db.php";

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'tween') {
	echo json_encode(['error' => 'Unauthorized']);
	exit;
}

$username = isset($_GET['username']) ? mysqli_real_escape_string($conn, $_GET['username']) : '';

if (empty($username)) {
	echo json_encode(['error' => 'Username required']);
	exit;
}

$query = "SELECT tu.id as tween_id, tu.username, tu.bio, bu.full_name
          FROM tween_user tu
          JOIN bartauser bu ON tu.user_id = bu.id AND bu.role = 'tween'
          WHERE tu.username = '$username'";
$result = mysqli_query($conn, $query);

if (mysqli_num_rows($result) === 0) {
	echo json_encode(['error' => 'User not found']);
	exit;
}

$user = mysqli_fetch_assoc($result);
echo json_encode($user);
