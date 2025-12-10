<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'tween') {
	header("Location: login.php");
	exit;
}

require "db.php";

$user_id = $_SESSION['user_id'];
$tween_id = $_SESSION['tween_id'];
$query = "SELECT is_active
          FROM tween_user
          WHERE user_id = $user_id";
$result = mysqli_query($conn, $query);
$row = mysqli_fetch_assoc($result);
if (!$row['is_active']) {
	header("Location: tween/approval.php");
	exit;
}

$query = "SELECT bu.full_name, tu.username
          FROM tween_user tu
          JOIN bartauser bu ON tu.user_id = bu.id
          WHERE tu.user_id = $user_id";
$result = mysqli_query($conn, $query);
$user = mysqli_fetch_assoc($result);

include "tween/get_contacts.php";

$selected_friend = null;
$selected_group = null;
$messages = [];
$contact_info = null;

if (isset($_GET['u'])) {
	$username = mysqli_real_escape_string($conn, urldecode($_GET['u']));
	$query = "SELECT tu.id as tween_id, tu.username, tu.bio, bu.full_name
				FROM tween_user tu
				JOIN bartauser bu ON tu.user_id = bu.id
				WHERE tu.username = '$username'";
	$result = mysqli_query($conn, $query);
	if (mysqli_num_rows($result) > 0) {
		$selected_friend = mysqli_fetch_assoc($result);
		$friend_id = $selected_friend['tween_id'];
		$is_friend = false;
		foreach ($friends as $f) {
			if ($f['tween_id'] == $friend_id) {
				$is_friend = true;
				break;
			}
		}
		if ($is_friend) {
			$query = "SELECT m.id, m.sender_id, m.text_content, m.sent_at, m.is_edited, tu.username as sender_username, bu.full_name as sender_name
			          FROM message m
			          JOIN individual_message im ON m.id = im.message_id
			          JOIN tween_user tu ON m.sender_id = tu.id
			          JOIN bartauser bu ON tu.user_id = bu.id
			          WHERE ((m.sender_id = $tween_id AND im.receiver_id = $friend_id) OR (m.sender_id = $friend_id AND im.receiver_id = $tween_id))
			          AND m.is_deleted = 0
			          ORDER BY m.sent_at ASC";
			$result = mysqli_query($conn, $query);
			$messages = [];
			while ($row = mysqli_fetch_assoc($result)) {
				$messages[] = $row;
			}
			$contact_info = $selected_friend;
		}
	}
} elseif (isset($_GET['group'])) {
	$group_id = (int)$_GET['group'];
	$is_member = false;
	foreach ($groups as $g) {
		if ($g['id'] == $group_id) {
			$is_member = true;
			$selected_group = $g;
			break;
		}
	}
	if ($is_member) {
		$query = "SELECT m.id, m.sender_id, m.text_content, m.sent_at, m.is_edited, tu.username as sender_username, bu.full_name as sender_name
		          FROM message m
		          JOIN group_message gm ON m.id = gm.message_id
		          JOIN tween_user tu ON m.sender_id = tu.id
		          JOIN bartauser bu ON tu.user_id = bu.id
		          WHERE gm.group_id = $group_id AND m.is_deleted = 0
		          ORDER BY m.sent_at ASC";
		$result = mysqli_query($conn, $query);
		$messages = [];
		while ($row = mysqli_fetch_assoc($result)) {
			$messages[] = $row;
		}
		$contact_info = $selected_group;
		$query = "SELECT tu.username, bu.full_name
		          FROM group_member gm
		          JOIN tween_user tu ON gm.member_id = tu.id
		          JOIN bartauser bu ON tu.user_id = bu.id
		          WHERE gm.group_id = $group_id";
		$result = mysqli_query($conn, $query);
		$members = [];
		while ($row = mysqli_fetch_assoc($result)) {
			$members[] = $row;
		}
		$contact_info['members'] = $members;
	}
}

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
			<a href="index.php"><img src="/barta-webapp-cse311/assets/img/logo.png" alt="Barta" class="logo"></a>
		</div>
		<div class="nav-middle">
			<button class="nav-btn" title="Friends"><i class="fa-duotone fa-solid fa-user-group"></i></button>
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
			<div class="search-hint text-muted"></div>
		</div>
		<div class="contacts">
			<div class="contacts-header">
				<h3>Friends</h3>
			</div>
			<div class="contacts-list">
				<?php foreach ($friends as $friend): ?>
					<div class="contact-item <?php echo (isset($selected_friend) && $selected_friend['username'] === $friend['username']) ? 'is-selected' : ''; ?>" data-type="friend" data-username="<?php echo htmlspecialchars($friend['username']); ?>" data-is-friend="true">
						<div class="contact-icon-circle">
							<i class="fa-solid fa-user"></i>
						</div>
						<div>
							<div><?php echo htmlspecialchars($friend['full_name']); ?></div>
							<div class="text-muted contact-preview"><?php
																	$preview = 'Click to chat';
																	if (!empty($friend['last_message_text'])) {
																		$previewText = htmlspecialchars($friend['last_message_text']);
																		// add prefix if tween sent it
																		if (!empty($friend['last_message_sender_id']) && $friend['last_message_sender_id'] == $tween_id) {
																			$previewText = 'You: ' . $previewText;
																		}
																		// truncate safely
																		if (mb_strlen($previewText) > 40) {
																			$previewText = mb_substr($previewText, 0, 37) . '...';
																		}
																		$preview = $previewText;
																	}
																	echo $preview; ?></div>
						</div>
					</div>
				<?php endforeach; ?>

			</div>
		</div>
		<div class="groups">
			<div class="groups-header">
				<h3>Groups</h3>

			</div>
			<div class="groups-list">
				<?php foreach ($groups as $group): ?>
					<div class="group-item <?php echo (isset($selected_group) && $selected_group['id'] == $group['id']) ? 'is-selected' : ''; ?>" data-type="group" data-group-id="<?php echo htmlspecialchars($group['id']); ?>">
						<div class="contact-icon-circle">
							<i class="fa-solid fa-users"></i>
						</div>
						<div>
							<div><?php echo htmlspecialchars($group['group_name']); ?></div>
							<div class="text-muted contact-preview">
								<?php
								$preview = 'Click to chat';
								if (!empty($group['last_message_text'])) {
									$previewText = htmlspecialchars($group['last_message_text']);
									if (!empty($group['last_message_sender_id']) && $group['last_message_sender_id'] == $tween_id) {
										$previewText = 'You: ' . $previewText;
									}
									if (mb_strlen($previewText) > 40) {
										$previewText = mb_substr($previewText, 0, 37) . '...';
									}
									$preview = $previewText;
								}
								echo $preview; ?>
							</div>
						</div>
					</div>
				<?php endforeach; ?>

			</div>
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
	<div class="middle-panel <?php echo (!$selected_friend && !$selected_group) ? 'expanded' : ''; ?>">
		<div class="empty-state" <?php echo (!$selected_friend && !$selected_group) ? '' : 'style="display: none;"'; ?>>
			<i class="fa-solid fa-comments"></i>
			<p class="text-muted">Select or search a friend to chat.</p>
		</div>
		<div class="chat-container" <?php echo (!$selected_friend && !$selected_group) ? 'style="display: none;"' : ''; ?>>
			<div class="chat-header">
				<div class="contact-icon-circle">
					<i class="fa-solid fa-<?php echo $selected_friend ? 'user' : 'users'; ?>"></i>
				</div>
				<span><?php echo htmlspecialchars($selected_friend['full_name'] ?? $selected_group['group_name'] ?? ''); ?></span>
				<button class="btn btn-icon toggle-right-panel-btn" title="Details"><i class="fa-duotone fa-solid fa-chevrons-right"></i></button>
			</div>
			<div class="messages">
				<?php if ($selected_friend || $selected_group): ?>
					<?php
					$msgCount = count($messages);
					for ($i = 0; $i < $msgCount; $i++):
						$msg = $messages[$i];
						$isOwn = $msg['sender_id'] == $tween_id;
						$prevSame = ($i > 0 && $messages[$i - 1]['sender_id'] == $msg['sender_id']);
						$nextSame = ($i < $msgCount - 1 && $messages[$i + 1]['sender_id'] == $msg['sender_id']);
						$showSender = !$prevSame;
						$noSenderClass = $showSender ? '' : ' no-sender';
						$cutTopClass = '';
						$cutBottomClass = '';
						if ($isOwn) {
							if ($prevSame) $cutTopClass = ' cut-top-right';
							if ($nextSame) $cutBottomClass = ' cut-bottom-right';
						} else {
							if ($prevSame) $cutTopClass = ' cut-top-left';
							if ($nextSame) $cutBottomClass = ' cut-bottom-left';
						}
						$messageClasses = 'message' . ($isOwn ? ' own' : '') . $cutTopClass . $cutBottomClass;
					?>
						<div class="message-wrapper<?php echo $isOwn ? ' own' : ''; ?><?php echo $noSenderClass; ?>" data-sender-id="<?php echo htmlspecialchars($msg['sender_id']); ?>" data-message-id="<?php echo htmlspecialchars($msg['id']); ?>">
							<?php if ($showSender): ?>
								<div class="sender"><?php echo htmlspecialchars($isOwn ? 'Me' : ($msg['sender_name'] ?? '')); ?></div>
							<?php else: ?>
								<div class="sender" style="display:none;"></div>
							<?php endif; ?>
							<div class="<?php echo $messageClasses; ?>">
								<div class="text"><?php echo htmlspecialchars($msg['text_content'] ?? ''); ?></div>
								<div class="timestamp"><?php echo date('g:i A', strtotime($msg['sent_at'] ?? 'now')); ?></div>
							</div>
						</div>
					<?php endfor; ?>
				<?php endif; ?>
			</div>
			<div class="message-input" <?php echo (!$selected_friend && !$selected_group) ? 'style="display: none;"' : ''; ?>>
				<textarea placeholder="Type a message..." rows="1"></textarea>
				<button class="btn btn-primary" title="Send message"><i class="fa-jelly-fill fa-regular fa-paper-plane"></i></button>
			</div>
		</div>
	</div>
	<div class="right-panel <?php echo (!$selected_friend && !$selected_group) ? 'hidden' : ''; ?>">
		<?php if (!$contact_info): ?>
			<div class="info-panel">
				<p>Select a contact to view details.</p>
			</div>
		<?php else: ?>
			<div class="info-panel">
				<div class="user-avatar">
					<div class="avatar-circle">
						<i class="fa-solid fa-<?php echo $selected_friend ? 'user' : 'users'; ?>"></i>
					</div>
				</div>
				<div class="user-details">
					<h3><?php echo htmlspecialchars($selected_friend ? $contact_info['full_name'] : $contact_info['group_name']); ?></h3>
					<small class="text-muted"><?php echo $selected_friend ? '@' . htmlspecialchars($contact_info['username']) : 'Group'; ?></small>
					<?php if ($selected_friend): ?>
						<p><?php echo htmlspecialchars(html_entity_decode($contact_info['bio'] ?? '')); ?></p>
					<?php else: ?>
						<p>Members: <?php echo htmlspecialchars(implode(', ', array_map(function ($m) {
										return $m['full_name'];
									}, $contact_info['members']))); ?></p>
					<?php endif; ?>
				</div>
				<?php if ($selected_friend): ?>
					<div class="action-buttons">
						<button class="btn-round" title="Unfriend"><i class="fa-solid fa-user-xmark"></i></button>
						<button class="btn-round btn-block" title="Block"><i class="fa-solid fa-ban"></i></button>
					</div>
				<?php endif; ?>
			</div>
		<?php endif; ?>
	</div>

	<!-- Templates -->
	<div id="message-template" style="display: none;">
		<div class="message-wrapper">
			<div class="sender"></div>
			<div class="message">
				<div class="text"></div>
				<div class="timestamp"></div>
			</div>
		</div>
	</div>
	<div id="no-messages-template" style="display: none;">
		<div class="no-messages">Start new conversation..</div>
	</div>
	<div id="friend-info-template" style="display: none;">
		<div class="user-avatar">
			<div class="avatar-circle"><i class="fa-solid fa-user"></i></div>
		</div>
		<div class="user-details">
			<h3></h3>
			<small class="text-muted"></small>
			<p></p>
		</div>
		<div class="action-buttons">
			<button class="btn-round" title="Unfriend"><i class="fa-solid fa-user-xmark"></i></button>
			<button class="btn-round btn-block" title="Block"><i class="fa-solid fa-ban"></i></button>
		</div>
	</div>
	<div id="group-info-template" style="display: none;">
		<div class="user-avatar">
			<div class="avatar-circle"><i class="fa-solid fa-users"></i></div>
		</div>
		<div class="user-details">
			<h3></h3>
			<small class="text-muted">Group</small>
			<p></p>
		</div>
	</div>
	<div id="non-friend-info-template" style="display: none;">
		<div class="user-avatar">
			<div class="avatar-circle"><i class="fa-solid fa-user"></i></div>
		</div>
		<div class="user-details">
			<h3></h3>
			<small class="text-muted"></small>
			<p></p>
		</div>
		<div class="action-buttons">
			<button class="btn btn-primary btn-add-friend" title="Send Friend Request"><i class="fa-solid fa-user-plus"></i> Send Friend Request</button>
			<button class="btn-round btn-block" title="Block"><i class="fa-solid fa-ban"></i></button>
		</div>
	</div>
	<div id="context-menu-template" style="display: none;">
		<div class="context-menu">
			<button class="context-menu-item" data-action="edit">
				<i class="fa-solid fa-pen-to-square"></i> Edit
			</button>
			<button class="context-menu-item" data-action="delete">
				<i class="fa-jelly-fill fa-regular fa-trash"></i> Delete
			</button>
		</div>
	</div>

	<!-- Modals -->
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
	<script src="assets/js/tween.js"></script>
</body>

</html>