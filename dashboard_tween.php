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

$query = "SELECT bu.full_name, tu.username FROM tween_user tu JOIN bartauser bu ON tu.user_id = bu.id WHERE tu.user_id = $user_id";
$result = mysqli_query($conn, $query);
$user = mysqli_fetch_assoc($result);

?>

<!DOCTYPE html>
<html lang="en">

<head>
	<title>Barta Chat</title>
	<?php include "includes/header.php"; ?>
</head>

<body class="p-tween-dashboard">
	<div class="nav-panel">
		<div class="nav-top">
			<img src="/barta-webapp-cse311/assets/img/logo.png" alt="Barta" class="logo">
		</div>
		<div class="nav-middle">
			<button class="nav-btn" title="Friend Requests"><i class="fa-duotone fa-solid fa-user-group"></i></button>
			<button class="nav-btn" id="create-group-btn" title="Create Group"><i class="fa-duotone fa-solid fa-users-medical"></i></button>
			<button class="nav-btn" title="Message Limits"><i class="fa-solid fa-gauge-high"></i></button>
			<button class="nav-btn" id="theme-toggle" title="Toggle Theme"><i class="fa-jelly-fill fa-regular fa-moon"></i></button>
			<button class="nav-btn" title="Settings"><i class="fa-jelly-fill fa-regular fa-gear"></i></button>
			<button class="nav-btn" title="Help/Support"><i class="fa-jelly-duo fa-regular fa-question"></i></button>
		</div>
		<div class="nav-bottom">
			<form action="logout.php" method="post">
				<button class="nav-btn" type="submit" title="Logout"><i class="fa-jelly-fill fa-regular fa-arrow-right-from-bracket"></i></button>
			</form>
		</div>
	</div>
	<div class="left-panel">
		<div class="top-bar">
			<i class="fa-regular fa-magnifying-glass search-icon"></i>
			<input type="text" class="search-box" placeholder="Search for friends">
		</div>
		<div class="contacts">
			<h3>Friends</h3>
			<div class="contact-item" data-type="friend">
				<div class="contact-icon-circle">
					<i class="fa-solid fa-user"></i>
				</div>
				<div>
					<div>Naylah H Chowdhury</div>
					<div class="text-muted contact-preview">let's play valorant</div>
				</div>
			</div>
			<div class="contact-item" data-type="friend">
				<div class="contact-icon-circle">
					<i class="fa-solid fa-user"></i>
				</div>
				<div>
					<div>Maymuna Khanom</div>
					<div class="text-muted contact-preview">You: hey there</div>
				</div>
			</div>
			<!-- More friends -->
			<div class="contact-item" data-type="friend">
				<div class="contact-icon-circle">
					<i class="fa-solid fa-user"></i>
				</div>
				<div>
					<div>Md. Shahriar Rakib Rabbi</div>
					<div class="text-muted contact-preview">bro acho?</div>
				</div>
			</div>
			<!-- More friends -->
		</div>
		<div class="groups">
			<div class="groups-header">
				<h3>Groups</h3>

			</div>
			<div class="group-item" data-type="group">
				<div class="contact-icon-circle">
					<i class="fa-solid fa-users"></i>
				</div>
				<div>
					<div>Group One</div>
					<div class="text-muted contact-preview">You: hola guys!</div>
				</div>
			</div>
			<div class="group-item" data-type="group">
				<div class="contact-icon-circle">
					<i class="fa-solid fa-users"></i>
				</div>
				<div>
					<div>Group Two</div>
					<div class="text-muted contact-preview">Talha: @aminul you there?</div>
				</div>
			</div>
			<!-- More groups -->
		</div>
		<div class="bottom-bar" id="profile-btn">
			<div class="profile-content">
				<i class="fa-solid fa-user"></i>
				<div class="user-info">
					<div><?php echo htmlspecialchars($user['full_name']); ?></div>
					<small class="text-muted">@<?php echo htmlspecialchars($user['username']); ?></small>
				</div>
			</div>
		</div>
	</div>
	<div class="middle-panel">
		<div class="chat-header">
			<div class="contact-icon-circle">
				<i class="fa-solid fa-user"></i>
			</div>
			<span>Naylah H Chowdhury</span>
		</div>
		<div class="messages">
			<div class="message-wrapper">
				<div class="sender">Naylah</div>
				<div class="message">
					<div class="text">hello! how are you?</div>
					<div class="timestamp">10:30 AM</div>
				</div>
			</div>
			<div class="message-wrapper own">
				<div class="sender">Me</div>
				<div class="message own">
					<div class="text">i'm good, thanks! wbu?</div>
					<div class="timestamp">10:32 AM</div>
				</div>
			</div>
			<div class="message-wrapper">
				<div class="sender">Naylah</div>
				<div class="message">
					<div class="text">doing great!</div>
					<div class="timestamp">10:35 AM</div>
				</div>
			</div>
			<div class="message-wrapper">
				<div class="sender">Naylah</div>
				<div class="message">
					<div class="text">let's play valorant</div>
					<div class="timestamp">10:35 AM</div>
				</div>
			</div>
			<!-- More messages -->
		</div>
		<div class="message-input">
			<textarea placeholder="Type a message..." rows="1"></textarea>
			<button class="btn btn-primary"><i class="fa-jelly-fill fa-regular fa-paper-plane"></i></button>
		</div>
	</div>
	<div class="right-panel">
		<div class="info-panel">
			<div class="user-avatar">
				<div class="avatar-circle">
					<i class="fa-solid fa-user"></i>
				</div>
			</div>
			<div class="user-details">
				<h3>Naylah H Chowdhury</h3>
				<small class="text-muted">@naylah</small>
				<p>Free Palestine 🇵🇸</p>
			</div>
			<div class="action-buttons">
				<button class="btn-round" title="Unfriend"><i class="fa-solid fa-user-xmark"></i></button>
				<button class="btn-round btn-block" title="Block"><i class="fa-solid fa-ban"></i></button>
			</div>
		</div>
	</div>

	<!-- Create Group Modal -->
	<div class="modal" id="create-group-modal">
		<div class="modal-content">
			<h3><i class="fa-duotone fa-solid fa-users-medical"></i> Create New Group</h3>
			<div class="form">
				<input type="text" class="form-input" placeholder="Group Name">
				<div class="form-row">
					<div class="color-picker">
						<input type="color" id="group-color" value="#2c8c84">
						<div class="color-circle" id="color-display"></div>
					</div>
					<label class="form-label">Group Theme Color</label>
				</div>
			</div>
			<div class="modal-buttons">
				<button class="btn btn-secondary" id="cancel-create">Cancel</button>
				<button class="btn btn-primary">Create</button>
			</div>
		</div>
	</div>

	<!-- Confirmation Modal -->
	<div class="modal" id="confirmation-modal">
		<div class="modal-content">
			<h3>Confirm Action</h3>
			<p class="confirmation-message">Are you sure?</p>
			<div class="modal-buttons">
				<button class="btn btn-secondary" id="cancel-confirmation">Cancel</button>
				<button class="btn btn-primary btn-confirm">Confirm</button>
			</div>
		</div>
	</div>

	<!-- Edit Message Modal -->
	<div class="modal" id="edit-message-modal">
		<div class="modal-content">
			<h3><i class="fa-solid fa-pen-to-square"></i> Edit Message</h3>
			<div class="form">
				<textarea class="form-textarea" id="edit-message-text" rows="3" placeholder="Edit your message..."></textarea>
			</div>
			<div class="modal-buttons">
				<button class="btn btn-secondary" id="cancel-edit">Cancel</button>
				<button class="btn btn-primary" id="save-edit">Save</button>
			</div>
		</div>
	</div>
	<script src="/barta-webapp-cse311/assets/js/tween.js"></script>
</body>

</html>