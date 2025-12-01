<!DOCTYPE html>
<html lang="en">

<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>Barta - Welcome</title>
	<link rel="icon" href="assets/img/logo.png" type="image/png">
	<link rel="stylesheet" href="assets/css/style.css">
</head>

<body class="p-<?php echo basename($_SERVER['PHP_SELF'], '.php'); ?>">

	<main class="p-login">
		<div class="card p-login__card">
			<div class="p-login__left">
				<div class="p-login__brand">
					<img src="assets/img/logo.png" class="p-login__logo">
					<img src="assets/img/text-logo.png" alt="Barta" class="p-login__title">
					<p class="p-login__tagline">Safe messaging for tweens</p>
				</div>
			</div>
			<div class="p-login__right">
				<div class="p-login__actions">
					<h1 class="p-login__headline">Login as</h1>
					<div class="p-login__toggle">
						<button class="btn btn-primary user-type-btn" data-target="tween">Tween</button>
						<button class="btn btn-secondary user-type-btn" data-target="parent">Parent</button>
					</div>
				</div>

				<div class="p-login__forms">
					<form id="tween" class="form form--visible" action="#" method="post">
						<label class="form-label">Username</label>
						<input class="form-input" type="text" name="username" placeholder="Tween Username">
						<label class="form-label">Password</label>
						<input class="form-input" type="password" name="password" placeholder="Password">
						<button class="btn btn-primary mt-1" type="submit">Login as Tween</button>
					</form>
					<form id="parent" class="form form--hidden" action="#" method="post">
						<label class="form-label">Email</label>
						<input class="form-input" type="email" name="email" placeholder="Parent Email">
						<label class="form-label">Password</label>
						<input class="form-input" type="password" name="password" placeholder="Password">
						<button class="btn btn-primary mt-1" type="submit">Login as Parent</button>
					</form>
				</div>

				<a class="btn btn-secondary p-login__register-btn " href="register.php">Create an account</a>
			</div>
		</div>
	</main>

	<script src="assets/js/auth.js"></script>
</body>

</html>

<!--Login for Parent-->

<?php
session_start();
require "db.php";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

	//Validating email format
	if (!filter_var($_POST['email'], FILTER_VALIDATE_EMAIL)) {
		header("Location: login.php?error=invalid_email");
		exit;
	}

	$email = $_POST['email'];
	$password = $_POST['password'];

	//Checking email in database
	$sql = "SELECT * FROM bartaUser WHERE email = '$email' LIMIT 1";
	$result = $conn->query($sql);

	if ($result->num_rows == 1) {

		$user = $result->fetch_assoc();

		//Verifing password
		if (password_verify($password, $user['password_hash'])) {

			//Storing session data
			$_SESSION['user_id'] = $user['id'];
			$_SESSION['role'] = $user['role'];
			$_SESSION['full_name'] = $user['full_name'];

			//Redirect to homepage/dashboard
			header("Location: dashboard.php");
			exit();
		} else {
			echo "<h3>Incorrect Password</h3>";
			//header("Location: login.php?error=wrong_password");
			//exit;
		}
	} else {
		echo "<h3>No Account Found With That Email</h3>";
		//header("Location: login.php?error=no_account");
		//exit;
	}

	$conn->close();
}
?>

<!--php
// Login for Tween
-->