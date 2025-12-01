<?php
session_start();

if (isset($_SESSION['user_id']) && isset($_SESSION['role'])) {
	if ($_SESSION['role'] == 'tween') {
		header("Location: dashboard_tween.php");
		exit;
	} elseif ($_SESSION['role'] == 'parent') {
		header("Location: dashboard_parent.php");
		exit;
	}
} else {
	header("Location: login.php");
	exit;
}
