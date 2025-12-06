<?php
require "db.php";
session_start();

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'parent') {
	header("Location: login.php");
	exit();
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
	<title>Parent Dashboard - Barta</title>
	<?php include "includes/header.php"; ?>
</head>

<body>

	<h2>Welcome, <?php echo $_SESSION['full_name']; ?>!</h2>
	<p>Your role: <?php echo $_SESSION['role']; ?></p>

	<a href="logout.php">Logout</a>

</body>

</html>