<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'tween') {
	header("Location: ../login.php");
	exit;
}

$user_id = $_SESSION['user_id'];
$tween_id = $_SESSION['tween_id'];

require "../db.php";

// Check if active
$query = "SELECT is_active, parent_id FROM tween_user WHERE user_id = $user_id";
$result = mysqli_query($conn, $query);
$row = mysqli_fetch_assoc($result);
if ($row['is_active'] == 1 && $row['parent_id']) {
	header("Location: ../dashboard_tween.php");
	exit;
}

// Get full_name
$query = "SELECT full_name FROM bartauser WHERE id = $user_id";
$result = mysqli_query($conn, $query);
$user = mysqli_fetch_assoc($result);
$full_name = $user['full_name'];

// Send request
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['parent_email'])) {
	$parent_email = filter_input(INPUT_POST, 'parent_email', FILTER_VALIDATE_EMAIL);
	if (!$parent_email) {
		$error = 'Invalid email address';
	} else {
		// Check if parent exists and get parent_id
		$query = "SELECT pu.id FROM parent_user pu JOIN bartauser bu ON pu.user_id = bu.id WHERE bu.email = '$parent_email'";
		$result = mysqli_query($conn, $query);
		if (mysqli_num_rows($result) == 0) {
			$error = 'Parent email not found. Please ensure your parent has registered.';
		} else {
			$parent_row = mysqli_fetch_assoc($result);
			$parent_id = $parent_row['id'];
			// Check if request already exists
			$query = "SELECT status FROM tween_link_request WHERE tween_id = $tween_id AND parent_id = $parent_id";
			$result_check = mysqli_query($conn, $query);
			if (mysqli_num_rows($result_check) > 0) {
				$error = 'A link request to this parent already exists. Please wait for their approval.';
			} else {
				// Insert request
				$query = "INSERT INTO tween_link_request (tween_id, parent_id, status) VALUES ($tween_id, $parent_id, 'pending')";
				if (mysqli_query($conn, $query)) {
					$success = 'Link request sent successfully!';
				} else {
					$error = 'Failed to send request. Please try again.';
				}
			}
		}
	}
}

// Cancel sent request
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['cancel_parent_id'])) {
	$cancel_parent_id = (int)$_POST['cancel_parent_id'];
	if ($cancel_parent_id > 0) {
		$query = "DELETE FROM tween_link_request WHERE tween_id = $tween_id AND parent_id = $cancel_parent_id";
		$res = mysqli_query($conn, $query);
		if ($res && mysqli_affected_rows($conn) > 0) {
			$success = 'Request canceled.';
		} else {
			$error = 'Unable to cancel request.';
		}
	} else {
		$error = 'Invalid parent id.';
	}
}

// Fetch existing requests
$query = "SELECT r.tween_id, r.parent_id AS request_parent_id, r.sent_at, bu.full_name as parent_name, bu.email as parent_email, bu.id AS parent_bartauser_id, r.status FROM tween_link_request r JOIN parent_user pu ON r.parent_id = pu.id JOIN bartauser bu ON pu.user_id = bu.id WHERE r.tween_id = $tween_id ORDER BY r.sent_at DESC";
$result = mysqli_query($conn, $query);
$requests = [];
while ($row = mysqli_fetch_assoc($result)) {
	$requests[] = $row;
}

// Check if any request is approved, then activate and redirect
foreach ($requests as $req) {
	if ($req['status'] === 'approved') {
		// Use the bartauser.id (parent_bartauser_id) as tween_user.parent_id references bartauser.id
		$parentBartaId = (int)($req['parent_bartauser_id'] ?? 0);
		if ($parentBartaId > 0) {
			$query = "UPDATE tween_user SET is_active = 1, parent_id = $parentBartaId WHERE user_id = $user_id";
			mysqli_query($conn, $query);
			header("Location: ../dashboard_tween.php");
			exit;
		}
	}
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
	<title>Barta - Tween Approval</title>
	<?php include "../includes/header.php"; ?>
</head>

<body class="p-approval">

	<main class="p-approval">
		<div class="card p-approval__card">
			<div class="p-approval__content">
				<div class="p-approval__logo">
					<img src="../assets/img/logo.png" alt="Barta">
				</div>
				<h1>Hello <?php echo htmlspecialchars($full_name); ?>, welcome to Barta!</h1>
				<p>To use this app, please link your account to your parent by entering their email address below. Your parent will need to create an account first to approve your access.</p>

				<form class="form" action="approval.php" method="post">
					<label class="form-label" for="parent_email">Parent Email</label>
					<div style="display: flex; gap: 0.5rem; align-items: flex-start;">
						<input class="form-input" type="email" name="parent_email" id="parent_email" required style="flex: 1;">
						<button class="btn btn-primary" type="submit">Send Link Request</button>
					</div>
					<?php if (isset($error)): ?>
						<div class="form-error-message">
							<?php echo $error; ?>
						</div>
					<?php endif; ?>
					<?php if (isset($success)): ?>
						<div class="form-success-message">
							<?php echo $success; ?>
						</div>
					<?php endif; ?>
				</form>


				<?php if (!empty($requests)): ?>
					<h2 class="mt-2">Link Requests</h2>
					<div class="table">
						<div class="table-header">
							<div class="table-cell">Parent</div>
							<div class="table-cell">Sent At</div>
							<div class="table-cell">Status</div>
							<div class="table-cell">Action</div>
						</div>
						<?php foreach ($requests as $req): ?>
							<div class="table-row">
								<div class="table-cell">
									<div><?php echo htmlspecialchars($req['parent_name']); ?></div>
									<small style="color: var(--text-muted);"><?php echo htmlspecialchars($req['parent_email']); ?></small>
								</div>
								<div class="table-cell"><?php echo date('M j, Y g:i A', strtotime($req['sent_at'])); ?></div>
								<div class="table-cell"><?php echo htmlspecialchars(ucfirst($req['status'])); ?></div>
								<div class="table-cell">
									<?php if ($req['status'] == 'pending' || $req['status'] == 'denied'): ?>
										<form action="approval.php" method="post">
											<input type="hidden" name="cancel_parent_id" value="<?php echo isset($req['request_parent_id']) ? $req['request_parent_id'] : ''; ?>">
											<button class="btn btn-secondary-error" type="submit">Cancel</button>
										</form>
									<?php endif; ?>
								</div>
							</div>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>

				<div style="text-align: center; margin-top: 1.5rem;">
					<a class="btn btn-secondary" href="../logout.php">Logout</a>
				</div>
			</div>
		</div>
	</main>

	<script src="../assets/js/auth.js"></script>
</body>

</html>