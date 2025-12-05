<?php
// Main 3-panel tween messaging interface
session_start();

require "db.php";

$user_id = $_SESSION['user_id'];
$query = "SELECT is_active FROM tween_user WHERE user_id = $user_id";
$result = mysqli_query($conn, $query);
$row = mysqli_fetch_assoc($result);
if (!$row['is_active']) {
	header("Location: tween/approval.php");
	exit;
}

?>

<!DOCTYPE html>
<html lang="en">

<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>Document</title>
</head>

<body>
	<h1>Tween Dashboard - Under Construction</h1>
	<form action="logout.php" method="post">
		<button type="submit">Logout</button>
	</form>

</body>

</html>