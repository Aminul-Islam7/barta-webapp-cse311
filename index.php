<?php
session_start();

if (isset($_SESSION['user_id']) && isset($_SESSION['role'])) {

	require "db.php";

	if ($_SESSION['role'] == 'tween') {
		$query = "SELECT is_active FROM tween_user WHERE user_id = " . $_SESSION['user_id'];
		$result = mysqli_query($conn, $query);
		$row = mysqli_fetch_assoc($result);
		if (!$row['is_active']) {
			header("Location: tween/approval.php");
			exit;
		} else {
			header("Location: dashboard_tween.php");
			exit;
		}
	} elseif ($_SESSION['role'] == 'parent') {
		header("Location: dashboard_parent.php");
		exit;
	}
} else {
	header("Location: login.php");
	exit;
}
