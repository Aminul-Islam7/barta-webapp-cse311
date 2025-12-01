<?php
// Main 3-panel tween messaging interface
session_start();
echo "Welcome, " . htmlspecialchars($_SESSION['username']) . "!" . "<br>";
if (!isset($_SESSION['logged_in']) || $_SESSION['role'] != 'tween') {
	header("Location: login.php");
	exit;
}

// logout
if ($_SERVER["REQUEST_METHOD"] == "POST") {
	session_destroy();
	header("Location: login.php");
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