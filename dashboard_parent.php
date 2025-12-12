<?php
require "db.php";
session_start();

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'parent') {
	header("Location: login.php");
	exit();
}

$parent_id = $_SESSION['parent_id'] ?? 0;
$user_id = $_SESSION['user_id'];

// Fetch parent details
$parent_query = "SELECT bu.email, bu.full_name FROM bartauser bu 
JOIN parent_user pu ON bu.id = pu.user_id 
WHERE pu.id = $parent_id LIMIT 1";
$parent_result = mysqli_query($conn, $parent_query);
$parent_data = $parent_result ? mysqli_fetch_assoc($parent_result) : [];

// Fetch linked children
$children = [];
$children_query = "SELECT id, user_id, username, bio FROM tween_user 
WHERE parent_id = $parent_id";
$children_result = mysqli_query($conn, $children_query);
if ($children_result) {
	while ($child = mysqli_fetch_assoc($children_result)) {
		$children[] = $child;
	}
}

// Fetch tween link requests
$link_requests = [];
$link_requests_query = "SELECT tr.tween_id,tr.parent_id, tu.username, tu.bio, bu.email, tr.sent_at FROM tween_link_request tr 
JOIN tween_user tu ON tr.tween_id = tu.id 
JOIN bartauser bu ON tu.user_id = bu.id 
WHERE tr.parent_id = $parent_id AND tr.status = 'pending'";
$link_requests_result = mysqli_query($conn, $link_requests_query);
if ($link_requests_result) {
	while ($request = mysqli_fetch_assoc($link_requests_result)) {
		$link_requests[] = $request;
	}
}

// Fetch friend requests pending approval
$friend_requests = [];
$friend_requests_query = "SELECT cr.requester_id, cr.receiver_id, tu.username AS receiver_name,
rtu.username AS requester_name, rtu.bio AS requester_bio, cr.sent_at FROM connection_request cr 
JOIN tween_user tu ON cr.receiver_id = tu.id 
JOIN tween_user rtu ON cr.requester_id = rtu.id 
WHERE tu.parent_id = $parent_id AND cr.receiver_parent_approved = 0";
$friend_requests_result = mysqli_query($conn, $friend_requests_query);
if ($friend_requests_result) {
	while ($freq = mysqli_fetch_assoc($friend_requests_result)) {
		$friend_requests[] = $freq;
	}
}

// Fetch flagged messages pending approval (with blocked_word highlight)
$flagged_messages = [];

// Step 1: Get blocked words for all this parent's tweens
$blocked_words = [];
$bw_query = "SELECT word, tween_id FROM blocked_word WHERE tween_id IN (SELECT id FROM tween_user WHERE parent_id = $parent_id)";
$bw_result = mysqli_query($conn, $bw_query);
if ($bw_result) {
	while ($bw = mysqli_fetch_assoc($bw_result)) {
		// Store array by tween_id for easy lookup per child
		$blocked_words[$bw['tween_id']][] = $bw['word'];
	}
}

// Step 2: Fetch unclean, pending-approval messages received by one of this parent's tweens
$flagged_query = "SELECT m.id, tu.id AS tween_id, tu.username AS child_name, m.text_content, m.sent_at,
bu.full_name AS from_user, m.sender_id, im.receiver_id
FROM message m
JOIN individual_message im ON m.id = im.message_id
JOIN tween_user tu ON im.receiver_id = tu.user_id
JOIN bartauser bu ON m.sender_id = bu.id
WHERE m.is_clean = 0 AND m.parent_approval = 0 AND tu.parent_id = $parent_id
ORDER BY m.sent_at DESC";

$flagged_result = mysqli_query($conn, $flagged_query);
if ($flagged_result) {
	while ($msg = mysqli_fetch_assoc($flagged_result)) {

		// Step 3: Check which blocked word triggered the flag — compare message with blocked words for this tween
		$triggered_word = '';
		if (isset($blocked_words[$msg['tween_id']])) {
			foreach ($blocked_words[$msg['tween_id']] as $word) {
				// Case-insensitive search
				if (stripos($msg['text_content'], $word) !== false) {
					$triggered_word = $word;
					break; // Show first match
				}
			}
		}

		// Add the matched word to the message data
		$msg['blocked_word'] = $triggered_word;
		$flagged_messages[] = $msg;
	}
}

// Fetch blocked words for all children display:
$blocked_words = [];
foreach ($children as $child) {
	$bw_query = "SELECT word_id, word FROM blocked_word WHERE tween_id = {$child['id']} ORDER BY word ASC";
	$bw_result = mysqli_query($conn, $bw_query);
	if ($bw_result) {
		while ($bw = mysqli_fetch_assoc($bw_result)) {
			$bw['tween_id'] = $child['id'];
			$bw['tween_username'] = $child['username'];
			$blocked_words[] = $bw;
		}
	}
}

?>

<!DOCTYPE html>
<html lang="en">

<head>
	<title>Parent Dashboard - Barta</title>
	<?php include "includes/header.php"; ?>
</head>

<body class="p-dashboard-parent">

	<!-- Header -->
	<header class="p-dashboard-parent__header">
		<div class="p-dashboard-parent__header-left">
			<img src="assets/img/logo.png" alt="Barta" class="p-dashboard-parent__logo">
		</div>
		<div class="p-dashboard-parent__header-right">
			<div class="p-dashboard-parent__parent-info">
				<?php echo htmlspecialchars('Welcome ' . $parent_data['full_name'] . '!' ?? 'Welcome Parent!'); ?>
			</div>
			<a href="parent/settings.php" class="btn btn-secondary p-dashboard-parent__settings-btn">⚙️ Settings</a>
			<!-- Setting Panel -->
			<div id="settingsPanel" class="p-dashboard-parent_settings-panel">
				<div class="p-dashboard-parent_settings-header">
					<h2>Parent Settings</h2>
					<button id="closeSettings" class="p-dashboard-parent_settings-close-btn">✖</button>
				</div>

				<div class="p-dashboard-parent_settings-section">
					<!-- Update Name -->
					<h3>Change Full Name</h3>
					<form method="POST" action="parent/settings.php">
						<input type="hidden" name="action" value="change_name">
						<input type="text" name="full_name"
							value="<?php echo htmlspecialchars($parent_data['full_name']); ?>"
							required>

						<button type="submit" style="gap: 0.5rem;" class="btn btn-primary">Change Name</button>
					</form>
				</div>

				<hr>

				<div class="settings-section">
					<!-- Update Password -->
					<h3>Change Password</h3>
					<form method="POST" action="parent/settings.php">
						<input type="hidden" name="action" value="change_password">

						<label>Old Password</label>
						<input type="password" name="old_password" required>

						<label>New Password</label>
						<input type="password" name="new_password" required>

						<button type="submit" style="gap: 0.5rem;" class="btn btn-primary">Change Password</button>
					</form>
				</div>
			</div>

			<!-- Logout Button -->
			<a href="logout.php" class="btn btn-secondary p-dashboard-parent__settings-btn">Logout</a>
		</div>
	</header>

	<!-- Main Content -->
	<main class="p-dashboard-parent__content">

		<!-- A. Tween Link Requests -->
		<section class="dashboard-panel">
			<h2 class="dashboard-panel__title">📋 Tween Link Requests</h2>
			<?php if (count($link_requests) > 0): ?>
				<table class="dashboard-table">
					<thead>
						<tr>
							<th>Tween Name</th>
							<th>Email</th>
							<th>Requested At</th>
							<th>Actions</th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ($link_requests as $req): ?>
							<tr>
								<td><?php echo htmlspecialchars($req['username']); ?></td>
								<td><?php echo htmlspecialchars($req['email']); ?></td>
								<td><?php echo date('M d, Y H:i', strtotime($req['sent_at'])); ?></td>
								<td>
									<div class="dashboard-actions">
										<form method="POST" action="parent/approve_tween_link.php" style="display: inline;">
											<input type="hidden" name="tween_id" value="<?php echo $req['tween_id']; ?>">
											<input type="hidden" name="parent_id" value="<?php echo $req['parent_id']; ?>">
											<button type="submit" class="btn btn-primary" name="action" value="approve">Approve</button>
										</form>
										<form method="POST" action="parent/approve_tween_link.php" style="display: inline;">
											<input type="hidden" name="tween_id" value="<?php echo $req['tween_id']; ?>">
											<input type="hidden" name="parent_id" value="<?php echo $req['parent_id']; ?>">
											<button type="submit" class="btn btn-secondary" name="action" value="reject">Reject</button>
										</form>
									</div>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php else: ?>
				<div class="dashboard-empty">
					<div class="dashboard-empty__icon">📭</div>
					<p>No pending tween link requests</p>
				</div>
			<?php endif; ?>
		</section>

		<!-- B. Friend Requests Pending Approval -->
		<section class="dashboard-panel">
			<h2 class="dashboard-panel__title">👥 Friend Requests Pending Approval</h2>
			<?php if (count($friend_requests) > 0): ?>
				<table class="dashboard-table">
					<thead>
						<tr>
							<th>Child Name</th>
							<th>Requester</th>
							<th>Direction</th>
							<th>Bio</th>
							<th>Requested At</th>
							<th>Actions</th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ($friend_requests as $freq): ?>
							<tr>
								<td><?php echo htmlspecialchars($freq['receiver_name']); ?></td>
								<td><?php echo htmlspecialchars($freq['requester_name']); ?></td>
								<td><?php echo htmlspecialchars(substr($freq['requester_bio'], 0, 50)); ?></td>
								<td><?php echo date('M d, Y H:i', strtotime($freq['sent_at'])); ?></td>
								<td>
									<div class="dashboard-actions">
										<form method="POST" action="parent/approve_friend_request.php" style="display: inline;">
											<input type="hidden" name="requester_id" value="<?php echo $freq['requester_id']; ?>">
											<input type="hidden" name="receiver_id" value="<?php echo $freq['receiver_id']; ?>">
											<button type="submit" class="btn btn-primary" name="action" value="approve">Approve</button>
										</form>
										<form method="POST" action="parent/approve_friend_request.php" style="display: inline;">
											<input type="hidden" name="requester_id" value="<?php echo $freq['requester_id']; ?>">
											<input type="hidden" name="receiver_id" value="<?php echo $freq['receiver_id']; ?>">
											<button type="submit" class="btn btn-secondary" name="action" value="decline">Decline</button>
										</form>
									</div>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php else: ?>
				<div class="dashboard-empty">
					<div class="dashboard-empty__icon">📭</div>
					<p>No pending friend requests</p>
				</div>
			<?php endif; ?>
		</section>

		<!-- C. Flagged Messages Pending Parent Approval -->
		<section class="dashboard-panel">
			<h2 class="dashboard-panel__title">💬 Flagged Messages Pending Approval</h2>
			<?php if (count($flagged_messages) > 0): ?>
				<table class="dashboard-table">
					<thead>
						<tr>
							<th>Child Name</th>
							<th>Message</th>
							<th>Blocked Word</th>
							<th>From</th>
							<th>Sent At</th>
							<th>Actions</th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ($flagged_messages as $msg): ?>
							<tr>
								<td><?php echo htmlspecialchars($msg['child_name']); ?></td>
								<td>
									<div class="request-preview">
										<?php
										// Highlight blocked word if present
										$preview = htmlspecialchars(substr($msg['text_content'], 0, 50));
										if ($msg['blocked_word']) {
											$preview = str_ireplace(
												htmlspecialchars($msg['blocked_word']),
												'<span class="blocked-word-highlight">' . htmlspecialchars($msg['blocked_word']) . '</span>',
												$preview
											);
										}
										echo $preview;
										if (strlen($msg['text_content']) > 50) {
											echo '<div class="request-preview__subtitle">...</div>';
										}
										?>
									</div>
								</td>
								<td>
									<?php
									if ($msg['blocked_word']) {
										echo '<span class="blocked-word-highlight">' . htmlspecialchars($msg['blocked_word']) . '</span>';
									} else {
										echo '-';
									}
									?>
								</td>
								<td><?php echo htmlspecialchars($msg['from_user']); ?></td>
								<td><?php echo date('M d, Y H:i', strtotime($msg['sent_at'])); ?></td>
								<td>
									<div class="dashboard-actions">
										<form method="POST" action="parent/approve_message.php" style="display: inline;">
											<input type="hidden" name="message_id" value="<?php echo $msg['id']; ?>">
											<button type="submit" class="btn btn-primary" name="action" value="approve">Approve</button>
										</form>
										<form method="POST" action="parent/approve_message.php" style="display: inline;">
											<input type="hidden" name="message_id" value="<?php echo $msg['id']; ?>">
											<button type="submit" class="btn btn-secondary" name="action" value="reject">Reject</button>
										</form>
									</div>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php else: ?>
				<div class="dashboard-empty">
					<div class="dashboard-empty__icon">📭</div>
					<p>No messages pending approval</p>
				</div>
			<?php endif; ?>
		</section>

		<!-- D. Linked Children Overview -->
		<section class="dashboard-panel">
			<h2 class="dashboard-panel__title">👶 Linked Children</h2>
			<?php if (count($children) > 0): ?>
				<div class="children-grid">
					<?php foreach ($children as $child): ?>
						<?php
						// Get child stats:

						// Get Sent messages
						$sent_count = 0;
						$sent_count_query = "SELECT COUNT(*) as count FROM message WHERE sender_id = {$child['user_id']}";
						$sent_count_result = mysqli_query($conn, $sent_count_query);
						if ($sent_count_result) {
							$sent_count = mysqli_fetch_assoc($sent_count_result)['count'];
						}

						// Get Received messages						
						$received_count = 0;
						$received_count_query = "SELECT COUNT(*) as count FROM individual_message im
						JOIN message m ON im.message_id = m.id WHERE im.receiver_id = {$child['user_id']}";
						$received_count_result = mysqli_query($conn, $received_count_query);
						if ($received_count_result) {
							$received_count = mysqli_fetch_assoc($received_count_result)['count'];
						}

						// Get daily limit
						$child_limit = $child['daily_msg_limit'] ?? 0;
						?>

						<div class="child-card">
							<div class="child-card__header">
								<div>
									<div class="child-card__name"><?php echo htmlspecialchars($child['username']); ?></div>
									<div class="child-card__bio" style="margin-top: 0.25rem; font-style: italic;"><?php echo htmlspecialchars($child['bio'] ?? ''); ?></div>
								</div>
							</div>

							<div class="child-card__stat" style="margin-top: -1.5rem;">
								<span class="child-card__stat-label">Sent Messages:</span>
								<span class="child-card__stat-value"><?php echo $sent_count; ?></span>
							</div>

							<div class="child-card__stat">
								<span class="child-card__stat-label">Received Messages:</span>
								<span class="child-card__stat-value"><?php echo $received_count; ?></span>
							</div>

							<label class="form-label" style="margin-top: 1rem;">Daily Message Limit</label>
							<form method="POST" action="parent/update_daily_limit.php" style="display: flex; gap: 0.5rem;">
								<input type="hidden" name="tween_id" value="<?php echo $child['id']; ?>">
								<input type="number" name="daily_limit" value="<?php echo $child_limit; ?>" class="form-input child-card__limit-input" min="0">
								<button type="submit" class="btn btn-primary" style="height: 65px; padding: 0.5rem 1rem;">Update</button>
							</form>

							<div class="child-card__actions">
    							<button class="btn btn-secondary view-details-btn" data-child-id="<?php echo $child['id']; ?>">View Details</button>
							</div>
							<!-- Hidden container for details -->
							<div 
								class="child-card__extra" id="child-details-<?php echo $child['id']; ?>" style="display: none; margin-top: 0.5rem;">
							</div>
							
						</div>
					<?php endforeach; ?>
				</div>
			<?php else: ?>
				<div class="dashboard-empty">
					<div class="dashboard-empty__icon">👶</div>
					<p>No linked children yet.</p>
				</div>
			<?php endif; ?>
		</section>

		<!-- E. List of Child's Friends -->
		<section class="dashboard-panel">
			<h2 class="dashboard-panel__title">👫 Tween's Friend List</h2>
			<?php
			$children_query = "SELECT * FROM tween_user WHERE parent_id = $parent_id";
			$children_result = mysqli_query($conn, $children_query);
			$children = [];
			if ($children_result) {
				while ($child = mysqli_fetch_assoc($children_result)) {
					// Get all connected tweens (friends)
					$friends = [];
					$friend_query = "SELECT t.id, t.username, t.bio
						FROM connection_request cr
						JOIN tween_user t ON t.id = cr.requester_id
						WHERE cr.receiver_id = {$child['id']} AND cr.receiver_accepted = 1
						UNION
						SELECT t.id, t.username, t.bio
						FROM connection_request cr
						JOIN tween_user t ON t.id = cr.receiver_id
						WHERE cr.requester_id = {$child['id']} AND cr.receiver_accepted = 1)";
					
					$friend_result = mysqli_query($conn, $friend_query);
					// Check blocked status of each friend		
					if ($friend_result) {
						while ($row = mysqli_fetch_assoc($friend_result)) {
							// Excluding info of the child themselves in the list
							if ($row['id'] != $child['id']) {
								// Check if this friend is blocked
								$block_check_query = "SELECT * FROM connection 
													  WHERE ((sender_id = {$child['id']} AND receiver_id = {$row['id']}) OR 
													  (sender_id = {$row['id']} AND receiver_id = {$child['id']})) AND type = 'blocked'";
								$block_check_result = mysqli_query($conn, $block_check_query);
								
								$row['is_blocked'] = ($block_check_result && mysqli_num_rows($block_check_result) > 0);
								$friends[] = $row;
							}
						}
					}
					$child['friends'] = $friends;
					$children[] = $child;
				}
			}
			?>
			<?php if (empty($children)): ?>
				<!-- Checking if children are linked -->
				<div class="dashboard-empty">
					<div class="dashboard-empty__icon">👫</div>
					<p>No child linked yet.</p>
				</div>
			<?php endif; ?>

			<?php foreach ($children as $child): ?>
				<h3><?php echo htmlspecialchars($child['username']); ?>'s Friends</h3>
				<?php if (count($child['friends']) > 0): ?>
					<table class="dashboard-table">
						<thead>
							<tr>
								<th>Friend Name</th>
								<th>Bio</th>
								<th>Actions</th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ($child['friends'] as $friend): ?>
								<tr>
									<td><?php echo htmlspecialchars($friend['username']); ?></td>
									<td><?php echo htmlspecialchars(substr($friend['bio'] ?? '', 0, 50)); ?></td>
									<td>
										<?php if ($friend['is_blocked']): ?>
											<span class="badge badge--blocked">Blocked</span>
										<?php else: ?>
											<span class="badge badge--active">Active</span>
										<?php endif; ?>
									</td>
									<td>
										<div class="dashboard-actions">
											<?php if ($friend['is_blocked']): ?>
												<!-- Unblock friend -->
												<form method="POST" action="parent/manage_blocking_child_friend.php" style="display: inline;">
													<input type="hidden" name="action" value="unblock">
													<input type="hidden" name="child_id" value="<?php echo $child['id']; ?>">
													<input type="hidden" name="friend_id" value="<?php echo $friend['id']; ?>">
													<button type="submit" class="btn btn-secondary" onclick="return confirm('Are you sure you want to unblock this friend?')">
													✅ Unblock</button>
												</form>
											<?php else: ?>
												<!-- Block friend -->
												<form method="POST" action="parent/manage_blocking_child_friend.php" style="display: inline;">
													<input type="hidden" name="child_id" value="<?php echo $child['id']; ?>">
													<input type="hidden" name="friend_id" value="<?php echo $friend['id']; ?>">
													<button type="submit" class="btn btn-secondary" onclick="return confirm('Are you sure you want to block this friend?')">
													🚫 Block</button>
												</form>
											<?php endif; ?>
											<!-- Remove friend -->
											<form method="POST" action="parent/manage_blocking_child_friend.php" style="display: inline;">
												<input type="hidden" name="child_id" value="<?php echo $child['id']; ?>">
												<input type="hidden" name="friend_id" value="<?php echo $friend['id']; ?>">
												<button type="submit" class="btn btn-secondary">❌ Remove</button>
											</form>
										</div>
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				<?php else: ?>
					<div class="dashboard-empty">
						<div class="dashboard-empty__icon">👫</div>
						<?php if (empty($children)  || empty($child['username'])): ?>
							<p>No child linked yet.</p>
						<?php else: ?>
							<p>No friends for <?php echo htmlspecialchars($child['username']); ?> yet.</p>
						<?php endif; ?>
					</div>
				<?php endif; ?>
			<?php endforeach; ?>
		</section>

		<!-- F. Blocked Words Management -->
		<section class="dashboard-panel">
			<h2 class="dashboard-panel__title">🚫 Blocked Words Management</h2>
			<form method="POST" action="parent/manage_blocked_word.php" class="blocked-words-form">
				<input type="hidden" name="action" value="add">
				<select name="tween_id" required class="form-select">
					<option value="">Choose Child</option>
					<?php foreach ($children as $child): ?>
						<option value="<?php echo $child['id']; ?>"><?php echo htmlspecialchars($child['username']); ?></option>
					<?php endforeach; ?>
				</select>
				<input type="text" name="word" class="form-input" placeholder="Add new blocked word" required>
				<button type="submit" class="btn btn-primary">Add Word</button>
			</form>

			<?php if (count($blocked_words) > 0): ?>
				<div class="blocked-words-list">
					<?php foreach ($blocked_words as $word): ?>
						<div class="blocked-word-badge">
							<?php echo htmlspecialchars($word['tween_username']); ?>: <b><?php echo htmlspecialchars($word['word']); ?></b>
							<form method="POST" action="parent/manage_blocked_word.php" style="display: inline; margin-left: 0.5rem;">
								<input type="hidden" name="action" value="remove">
								<input type="hidden" name="word_id" value="<?php echo $word['word_id']; ?>">
								<button type="submit" class="blocked-word-badge__remove" onclick="return confirm('Remove this word?');">×</button>
							</form>
						</div>
					<?php endforeach; ?>
				</div>
			<?php else: ?>
				<div class="dashboard-empty">
					<div class="dashboard-empty__icon">✨</div>
					<p>No blocked words configured yet</p>
				</div>
			<?php endif; ?>
		</section>

	</main>

	<!-- Print Button -->
	<button class="btn btn-primary print-btn" onclick="window.print()">🖨️ Print Dashboard</button>

	<script src="assets/js/parent.js"></script>

</body>

</html>
