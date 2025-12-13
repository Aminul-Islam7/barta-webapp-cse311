<?php
session_start();
require "db.php";

$errors = [
	'invalid_username' => 'Invalid username',
	'invalid_credentials' => 'Incorrect username or password',
	'db_error' => 'Database error. Please try again'
];

$success = [
	'registered' => 'Registration successful! Please log in'
];

// If already logged in, redirect
if (isset($_SESSION['logged_in']) && $_SESSION['logged_in']) {
	if ($_SESSION['role'] == 'tween') {
		header("Location: dashboard_tween.php");
		exit;
	} elseif ($_SESSION['role'] == 'parent') {
		header("Location: dashboard_parent.php");
		exit;
	}
}

// Handle login POST
if ($_SERVER["REQUEST_METHOD"] == "POST") {
	// Tween login
	if (isset($_POST['username'])) {
		$username = filter_input(INPUT_POST, 'username', FILTER_SANITIZE_SPECIAL_CHARS);
		if (!$username) {
			header("Location: login.php?error=invalid_username");
			exit;
		}
		$password = $_POST['password'];
		$query = "SELECT tu.user_id, bu.password_hash, tu.id AS tween_id FROM tween_user tu JOIN bartauser bu ON tu.user_id = bu.id WHERE tu.username = '$username'";
		$result = mysqli_query($conn, $query);
		if (mysqli_num_rows($result) == 0) {
			header("Location: login.php?error=invalid_credentials");
			exit;
		}
		$row = mysqli_fetch_assoc($result);
		if (!password_verify($password, $row['password_hash'])) {
			header("Location: login.php?error=invalid_credentials");
			exit;
		}
		$_SESSION['user_id'] = $row['user_id'];
		$_SESSION['tween_id'] = $row['tween_id'];
		$_SESSION['username'] = $username;
		$_SESSION['logged_in'] = true;
		$_SESSION['role'] = 'tween';
		$query = "SELECT is_active FROM tween_user WHERE user_id = " . $row['user_id'];
		$result2 = mysqli_query($conn, $query);
		$row2 = mysqli_fetch_assoc($result2);
		if ($row2['is_active'] == 0) {
			header("Location: tween/approval.php");
			exit;
		} else {
			header("Location: dashboard_tween.php");
			exit;
		}
	}

	// Parent login
	if (isset($_POST['email'])) {
		$email = filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL);
		if (!$email) {
			header("Location: login.php?error=invalid_email");
			exit;
		}
		$password = $_POST['password'];
		$query = "SELECT bu.id AS user_id, bu.email, bu.password_hash, pu.id AS parent_id FROM parent_user pu JOIN bartauser bu ON pu.user_id = bu.id WHERE bu.email = '$email' LIMIT 1";
		$result = mysqli_query($conn, $query);
		if (mysqli_num_rows($result) == 0) {
			header("Location: login.php?error=invalid_credentials");
			exit;
		}
		$row = mysqli_fetch_assoc($result);
		if (!password_verify($password, $row['password_hash'])) {
			header("Location: login.php?error=invalid_credentials");
			exit;
		}
		$_SESSION['user_id'] = $row['user_id'];
		$_SESSION['parent_id'] = $row['parent_id'];
		$_SESSION['email'] = $email;
		$_SESSION['logged_in'] = true;
		$_SESSION['role'] = 'parent';
		header("Location: dashboard_parent.php");
		exit;
	}
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
	<title>Barta - Login</title>
	<?php include "header.php"; ?>
</head>

<body>
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
					<?php if (isset($_GET['success'])): ?>
						<div class="form-success-message">
							<?php echo $success[$_GET['success']] ?? 'Success'; ?>
						</div>
					<?php endif; ?>
					<h1 class="p-login__headline">Login as</h1>
					<div class="p-login__toggle">
						<button class="btn btn-primary user-type-btn" data-target="tween">Tween</button>
						<button class="btn btn-secondary user-type-btn" data-target="parent">Parent</button>
					</div>
				</div>

				<div class="p-login__forms">
					<form id="tween" class="form form--visible" action="login.php" method="post">
						<label class="form-label">Username</label>
						<input class="form-input" type="text" name="username" placeholder="Tween Username">
						<label class="form-label">Password</label>
						<input class="form-input" type="password" name="password" placeholder="Password">
						<?php if (isset($_GET['error'])): ?>
							<div class="form-error-message">
								<?php echo $errors[$_GET['error']] ?? 'An error occurred'; ?>
							</div>
						<?php endif; ?>
						<button class="btn btn-primary mt-1" type="submit">Login as Tween</button>
					</form>
					<form id="parent" class="form form--hidden" action="login.php" method="post">
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