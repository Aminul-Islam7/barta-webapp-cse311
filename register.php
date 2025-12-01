<!DOCTYPE html>
<html lang="en">

<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>Barta - Register</title>
	<link rel="icon" href="assets/img/logo.png" type="image/png">
	<link rel="stylesheet" href="assets/css/style.css">
</head>

<?php
// The body class reveals which page is active to make page-specific CSS easier
?>

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
						<button class="btn btn-primary mt-1" type="submit">Register as Tween</button>
						<a class="btn btn-secondary mt-1" href="login.php">Login instead</a>
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
							<option value="passport">Passport</option>
							<option value="nid">NID</option>
							<option value="drivers license">Driver's License</option>
						</select>
						<label class="form-label mt-1" for="personal_id_number">ID Number</label>
						<input class="form-input" type="text" name="personal_id_number" required>
						<button class="btn btn-primary mt-1" type="submit">Register as Parent</button>
						<a class="btn btn-secondary mt-1" href="login.php">Login instead</a>
					</form>
				</div>
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