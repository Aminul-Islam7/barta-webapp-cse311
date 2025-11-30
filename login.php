<!DOCTYPE html>
<html lang="en">

<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>Barta - Welcome</title>
	<link rel="stylesheet" href="assets/css/style.css">
</head>

<?php
// The body class reveals which page is active to make page-specific CSS easier
?>

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
					<p class="p-login__headline">Login as</p>
					<div class="p-login__toggle">
						<button class="btn btn-primary p-login__user-type-btn" data-target="tween">Tween</button>
						<button class="btn btn-secondary p-login__user-type-btn" data-target="parent">Parent</button>
					</div>
				</div>

				<div class="p-login__forms">
					<form id="tween" class="p-login__form p-login__form--visible" action="#" method="post">
						<label class="form-label">Username</label>
						<input class="form-input" type="text" name="username" placeholder="Tween Username">
						<label class="form-label mt-1">Password</label>
						<input class="form-input" type="password" name="password" placeholder="Password">
						<button class="btn btn-primary mt-2" type="submit">Login as Tween</button>
					</form>
					<form id="parent" class="p-login__form p-login__form--hidden" action="#" method="post">
						<label class="form-label">Email</label>
						<input class="form-input" type="email" name="email" placeholder="Parent Email">
						<label class="form-label mt-1">Password</label>
						<input class="form-input" type="password" name="password" placeholder="Password">
						<button class="btn btn-primary mt-2" type="submit">Login as Parent</button>
					</form>
				</div>

				<div class="p-login__register">
					<a class="btn btn-secondary p-login__register-btn " href="register_tween.php">Create an account</a>
				</div>
			</div>
		</div>
	</main>

	<script src="assets/js/login.js"></script>
</body>

</html>

<?php
// Login for Tween/Parent - UI only. Authentication endpoints are not modified.
?>