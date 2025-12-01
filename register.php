<?php
$errors = [
	'invalid_name' => 'Invalid name',
	'invalid_date' => 'Invalid birth date',
	'invalid_email' => 'Invalid email address',
	'password_too_short' => 'Password must be at least 8 characters',
	'invalid_username' => 'Invalid username',
	'username_exists' => 'Username already exists',
	'email_exists' => 'Email already exists',
	'invalid_id_type' => 'Invalid ID type',
	'invalid_id_number' => 'Invalid ID number',
	'tween_age_invalid' => 'Only ages 8 – 12 can use this app',
	'parent_age_invalid' => 'You must be 21 or older to register as a parent',
	'db_error' => 'Database error. Please try again'
];
?>

<!DOCTYPE html>
<html lang="en">

<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>Barta - Register</title>
	<link rel="icon" href="assets/img/logo.png" type="image/png">
	<link rel="stylesheet" href="assets/css/style.css">
</head>

<body class="p-<?php echo basename($_SERVER['PHP_SELF'], '.php'); ?>">

	<main class="p-register">
		<div class="card p-register__card">
			<div class="p-register__left">
				<div class="p-register__actions">
					<h1 class="p-register__headline">Register as</h1>
					<div class="p-register__toggle">
						<button class="btn user-type-btn" data-target="tween">Tween</button>
						<button class="btn user-type-btn" data-target="parent">Parent</button>
					</div>
				</div>
				<div class="p-register__forms">
					<form id="tween" class="form form--visible" action="#" method="post">
						<label class="form-label" for="full_name">Full Name</label>
						<input class="form-input" type="text" name="full_name" required>
						<label class="form-label" for="birth_date">Birth Date</label>
						<input class="form-input" type="date" name="birth_date" required>
						<label class="form-label" for="email">Email</label>
						<input class="form-input" type="email" name="email" required>
						<label class="form-label" for="password">Password</label>
						<input class="form-input" type="password" name="password" required>
						<label class="form-label" for="username">Username</label>
						<input class="form-input" type="text" name="username" required>
						<label class="form-label" for="bio">Bio (optional)</label>
						<textarea class="form-textarea" name="bio" rows="2"></textarea>
						<?php if (isset($_GET['error'])): ?>
							<div class="form-error-message">
								<?php echo $errors[$_GET['error']] ?? 'An error occurred'; ?>
							</div>
						<?php endif; ?>
						<button class="btn btn-primary mt-1" type="submit">Register as Tween</button>
					</form>
					<form id="parent" class="form form--hidden" action="#" method="post">
						<label class="form-label" for="full_name">Full Name</label>
						<input class="form-input" type="text" name="full_name" required>
						<label class="form-label" for="birth_date">Birth Date</label>
						<input class="form-input" type="date" name="birth_date" required>
						<label class="form-label" for="email">Email</label>
						<input class="form-input" type="email" name="email" required>
						<label class="form-label" for="password">Password</label>
						<input class="form-input" type="password" name="password" required>
						<label class="form-label" for="personal_id_type">ID Type</label>
						<select class="form-select" name="personal_id_type" required>
							<option value="nid">NID</option>
							<option value="passport">Passport</option>
							<option value="drivers license">Driver's License</option>
						</select>
						<label class="form-label mt-1" for="personal_id_number">ID Number</label>
						<input class="form-input" type="text" name="personal_id_number" required>
						<?php if (isset($_GET['error'])): ?>
							<div class="form-error-message">
								<?php echo $errors[$_GET['error']] ?? 'An error occurred'; ?>
							</div>
						<?php endif; ?>
						<button class="btn btn-primary mt-1" type="submit">Register as Parent</button>

					</form>
				</div>
				<a class="btn btn-secondary p-register__login-btn " href="login.php">Login instead</a>
			</div>
			<div class="p-register__right">
				<div class="p-register__brand">
					<img src="assets/img/logo.png" class="p-register__logo">
					<img src="assets/img/text-logo.png" alt="Barta" class="p-register__title">
					<p class="p-register__tagline">Safe messaging for tweens</p>
				</div>
				<div class="p-register__info">
					<p>By registering on this platform you agree to our <a href="terms.php">Terms & Conditions</a> and <a href="privacy.php">Privacy Policy</a></p>
				</div>
			</div>
		</div>
	</main>

	<script src="assets/js/auth.js"></script>
</body>

</html>

<?php

require "db.php";

// Tween registration
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['username'])) {

	// Sanitize and validate inputs
	$full_name = filter_input(INPUT_POST, 'full_name', FILTER_SANITIZE_SPECIAL_CHARS);
	if (!$full_name || strlen($full_name) < 2) {
		header("Location: register.php?error=invalid_name");
		exit;
	}

	$birth_date = $_POST['birth_date'];
	if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $birth_date) || !strtotime($birth_date)) {
		header("Location: register.php?error=invalid_date");
		exit;
	}

	$age = date_diff(date_create($birth_date), date_create('today'))->y;
	if ($age < 8 || $age > 12) {
		header("Location: register.php?error=tween_age_invalid");
		exit;
	}

	$email = filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL);
	if (!$email) {
		header("Location: register.php?error=invalid_email");
		exit;
	}

	$password = $_POST['password'];
	if (strlen($password) < 8) {
		header("Location: register.php?error=password_too_short");
		exit;
	}

	$username = filter_input(INPUT_POST, 'username', FILTER_SANITIZE_SPECIAL_CHARS);
	if (!$username || strlen($username) < 3) {
		header("Location: register.php?error=invalid_username");
		exit;
	}

	$bio = filter_input(INPUT_POST, 'bio', FILTER_SANITIZE_SPECIAL_CHARS) ?: '';

	// Check if email already exists
	$query = "SELECT id FROM bartauser WHERE email = '$email'";
	$result = mysqli_query($conn, $query);
	if (mysqli_num_rows($result) > 0) {
		header("Location: register.php?error=email_exists");
		exit;
	}

	// Check if username already exists
	$query = "SELECT id FROM tween_user WHERE username = '$username'";
	$result = mysqli_query($conn, $query);
	if (mysqli_num_rows($result) > 0) {
		header("Location: register.php?error=username_exists");
		exit;
	}

	// Start transaction to rollback changes if error occurs on any of the inserts
	mysqli_begin_transaction($conn);

	try {
		// Insert into bartauser
		$password_hash = password_hash($password, PASSWORD_DEFAULT);
		$role = 'tween';
		$query = "INSERT INTO bartauser (email, password_hash, full_name, birth_date, role) VALUES ('$email', '$password_hash', '$full_name', '$birth_date', '$role')";

		if (!mysqli_query($conn, $query)) {
			throw new Exception('Failed to insert into bartauser');
		}

		$user_id = mysqli_insert_id($conn);

		// Insert into tween_user
		$query = "INSERT INTO tween_user (user_id, username, parent_id, bio, is_active, daily_msg_limit) VALUES ($user_id, '$username', NULL, '$bio', 1, 100)";

		if (!mysqli_query($conn, $query)) {
			throw new Exception('Failed to insert into tween_user');
		}

		// Commit transaction
		mysqli_commit($conn);
		header("Location: login.php?success=registered");
		exit;
	} catch (Exception $e) {
		// Rollback on error
		mysqli_rollback($conn);
		header("Location: register.php?error=db_error");
		exit;
	}
}

// Parent registration
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['personal_id_type'])) {

	// Sanitize and validate inputs
	$full_name = filter_input(INPUT_POST, 'full_name', FILTER_SANITIZE_SPECIAL_CHARS);
	if (!$full_name || strlen($full_name) < 2) {
		header("Location: register.php?error=invalid_name");
		exit;
	}

	$birth_date = $_POST['birth_date'];
	if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $birth_date) || !strtotime($birth_date)) {
		header("Location: register.php?error=invalid_date");
		exit;
	}

	$age = date_diff(date_create($birth_date), date_create('today'))->y;
	if ($age <= 21) {
		header("Location: register.php?error=parent_age_invalid");
		exit;
	}

	$email = filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL);
	if (!$email) {
		header("Location: register.php?error=invalid_email");
		exit;
	}

	$password = $_POST['password'];
	if (strlen($password) < 8) {
		header("Location: register.php?error=password_too_short");
		exit;
	}

	$personal_id_type = $_POST['personal_id_type'];
	if (!in_array($personal_id_type, ['nid', 'passport', 'drivers license'])) {
		header("Location: register.php?error=invalid_id_type");
		exit;
	}

	$personal_id_number = filter_input(INPUT_POST, 'personal_id_number', FILTER_SANITIZE_SPECIAL_CHARS);
	if (!$personal_id_number || strlen($personal_id_number) < 5) {
		header("Location: register.php?error=invalid_id_number");
		exit;
	}

	// Check if email already exists
	$query = "SELECT id FROM bartauser WHERE email = '$email'";
	$result = mysqli_query($conn, $query);
	if (mysqli_num_rows($result) > 0) {
		header("Location: register.php?error=email_exists");
		exit;
	}

	// Start transaction
	mysqli_begin_transaction($conn);

	try {
		// Insert into bartauser
		$password_hash = password_hash($password, PASSWORD_DEFAULT);
		$role = 'parent';
		$query = "INSERT INTO bartauser (email, password_hash, full_name, birth_date, role) VALUES ('$email', '$password_hash', '$full_name', '$birth_date', '$role')";

		if (!mysqli_query($conn, $query)) {
			throw new Exception('Failed to insert into bartauser');
		}

		$user_id = mysqli_insert_id($conn);

		// Insert into parent_user
		$query = "INSERT INTO parent_user (user_id, personal_id_type, personal_id_number) VALUES ($user_id, '$personal_id_type', '$personal_id_number')";

		if (!mysqli_query($conn, $query)) {
			throw new Exception('Failed to insert into parent_user');
		}

		// Commit transaction
		mysqli_commit($conn);
		header("Location: login.php?success=registered");
		exit;
	} catch (Exception $e) {
		// Rollback on error
		mysqli_rollback($conn);
		header("Location: register.php?error=db_error");
		exit;
	}
}

?>