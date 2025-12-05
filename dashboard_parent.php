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

//NEED TO CONTINUE CHECKING FROM HERE//

// Fetch messages pending approval
$messages = [];
$messages_query = "SELECT m.id, tu.username AS child_name, m.text_content, m.sent_at,
bu.full_name AS from_user, m.sender_id, im.receiver_id
FROM message m
JOIN individual_message im ON m.id = im.message_id
JOIN tween_user tu ON im.receiver_id = tu.user_id
JOIN bartauser bu ON m.sender_id = bu.id
WHERE m.is_clean = 0 AND m.parent_approval = 0 AND tu.parent_id = $parent_id
ORDER BY m.sent_at DESC";
$messages_result = mysqli_query($conn, $messages_query);
if ($messages_result) {
	while ($msg = mysqli_fetch_assoc($messages_result)) {
		$messages[] = $msg;
	}
}

// Fetch flagged messages
$flagged_messages = [];
$flagged_query = "SELECT fm.id, tu.username AS child_name, fm.message_text, fm.blocked_word, fm.created_at FROM flagged_message fm JOIN tween_user tu ON fm.tween_id = tu.id WHERE tu.parent_id = $parent_id ORDER BY fm.created_at DESC";
$flagged_result = mysqli_query($conn, $flagged_query);
if ($flagged_result) {
	while ($flagged = mysqli_fetch_assoc($flagged_result)) {
		$flagged_messages[] = $flagged;
	}
}

// Fetch blocked words
$blocked_words = [];
$blocked_words_query = "SELECT id, word FROM blocked_word WHERE parent_id = $parent_id ORDER BY word ASC";
$blocked_words_result = mysqli_query($conn, $blocked_words_query);
if ($blocked_words_result) {
	while ($bw = mysqli_fetch_assoc($blocked_words_result)) {
		$blocked_words[] = $bw;
	}
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>Parent Dashboard - Barta</title>
	<link rel="icon" href="assets/img/logo.png" type="image/png">
	<link rel="stylesheet" href="assets/css/style.css">
</head>

<body class="p-dashboard-parent">

	<!-- Header -->
	<header class="p-dashboard-parent__header">
		<div class="p-dashboard-parent__header-left">
			<img src="assets/img/logo.png" alt="Barta" class="p-dashboard-parent__logo">
		</div>
		<div class="p-dashboard-parent__header-right">
			<div class="p-dashboard-parent__parent-info">
				<?php echo htmlspecialchars('Welcome '. $parent_data['full_name'] .'!' ?? 'Welcome Parent!'); ?>
			</div>
			<a href="parent/settings.php" class="btn btn-secondary p-dashboard-parent__settings-btn">⚙️ Settings</a>
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

		<!-- C. Messages Pending Parent Approval -->
		<section class="dashboard-panel">
			<h2 class="dashboard-panel__title">💬 Messages Pending Approval</h2>
			<?php if (count($messages) > 0): ?>
				<table class="dashboard-table">
					<thead>
						<tr>
							<th>Child Name</th>
							<th>Message</th>
							<th>From</th>
							<th>Sent At</th>
							<th>Actions</th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ($messages as $msg): ?>
							<tr>
								<td><?php echo htmlspecialchars($msg['child_name']); ?></td>
								<td>
									<div class="request-preview">									
											<?php echo htmlspecialchars(substr($msg['text_content'], 0, 50)); ?>
											<?php if (strlen($msg['text_content']) > 50): ?>
											<div class="request-preview__subtitle">...</div>
										<?php endif; ?>
									</div>
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
						// Get child stats
						$sent_count = 0;
						$received_count = 0;
						$sent_count_query = "SELECT COUNT(*) as count FROM message WHERE from_user_id = {$child['user_id']}";
						$sent_count_result = mysqli_query($conn, $sent_count_query);
						if ($sent_count_result) {
							$sent_count = mysqli_fetch_assoc($sent_count_result)['count'];
						}

						$received_count_query = "SELECT COUNT(*) as count FROM message WHERE to_user_id = {$child['user_id']}";
						$received_count_result = mysqli_query($conn, $received_count_query);
						if ($received_count_result) {
							$received_count = mysqli_fetch_assoc($received_count_result)['count'];
						}

						// Get daily limit
						$child_limit = 0;
						$limit_query = "SELECT daily_message_limit FROM tween_user WHERE id = {$child['id']} LIMIT 1";
						$limit_result = mysqli_query($conn, $limit_query);
						if ($limit_result) {
							$child_limit = mysqli_fetch_assoc($limit_result)['daily_message_limit'] ?? 0;
						}
						?>
						<div class="child-card">
							<div class="child-card__header">
								<div>
									<div class="child-card__name"><?php echo htmlspecialchars($child['username']); ?></div>
									<div class="child-card__bio"><?php echo htmlspecialchars($child['bio'] ?? ''); ?></div>
								</div>
							</div>

							<div class="child-card__stat">
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
								<button type="submit" class="btn btn-primary" style="padding: 0.5rem 1rem;">Update</button>
							</form>

							<div class="child-card__actions">
								<a href="parent/settings.php?child_id=<?php echo $child['id']; ?>" class="btn btn-secondary">View Details</a>
							</div>
						</div>
					<?php endforeach; ?>
				</div>
			<?php else: ?>
				<div class="dashboard-empty">
					<div class="dashboard-empty__icon">👶</div>
					<p>No linked children yet. Visit Settings to link a child.</p>
				</div>
			<?php endif; ?>
		</section>

		<!-- E. Flagged Messages (Bad Words Found) -->
		<section class="dashboard-panel">
			<h2 class="dashboard-panel__title">⚠️ Flagged Messages (Blocked Words)</h2>
			<?php if (count($flagged_messages) > 0): ?>
				<table class="dashboard-table">
					<thead>
						<tr>
							<th>Child Name</th>
							<th>Message Preview</th>
							<th>Blocked Word</th>
							<th>Detected At</th>
							<th>Actions</th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ($flagged_messages as $flagged): ?>
							<tr>
								<td><?php echo htmlspecialchars($flagged['child_name']); ?></td>
								<td>
									<div class="request-preview">
										<div class="request-preview__title"><?php echo htmlspecialchars(substr($flagged['message_text'], 0, 40)); ?></div>
									</div>
								</td>
								<td><span class="status-badge status-badge--flagged"><?php echo htmlspecialchars($flagged['blocked_word']); ?></span></td>
								<td><?php echo date('M d, Y H:i', strtotime($flagged['created_at'])); ?></td>
								<td>
									<div class="dashboard-actions">
										<form method="POST" action="parent/approve_message.php" style="display: inline;">
											<input type="hidden" name="flagged_id" value="<?php echo $flagged['id']; ?>">
											<button type="submit" class="btn btn-primary" name="action" value="approve">Approve</button>
										</form>
										<form method="POST" action="parent/approve_message.php" style="display: inline;">
											<input type="hidden" name="flagged_id" value="<?php echo $flagged['id']; ?>">
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
					<div class="dashboard-empty__icon">✅</div>
					<p>No flagged messages</p>
				</div>
			<?php endif; ?>
		</section>

		<!-- F. List of Child's Friends -->
		<section class="dashboard-panel">
			<h2 class="dashboard-panel__title">👫 Tween's Friend List</h2>
			<?php
			$friends_query = "SELECT DISTINCT u.id, u.username, u.bio FROM bartauser u JOIN friend_request fr ON (fr.from_user_id = u.id OR fr.to_user_id = u.id) JOIN tween_user tu ON (fr.tween_id = tu.id) WHERE tu.parent_id = $parent_id AND fr.status = 'accepted' ORDER BY u.username";
			$friends_result = mysqli_query($conn, $friends_query);
			$has_friends = $friends_result && mysqli_num_rows($friends_result) > 0;
			?>
			<?php if ($has_friends): ?>
				<table class="dashboard-table">
					<thead>
						<tr>
							<th>Friend Name</th>
							<th>Bio</th>
							<th>Actions</th>
						</tr>
					</thead>
					<tbody>
						<?php while ($friend = mysqli_fetch_assoc($friends_result)): ?>
							<tr>
								<td><?php echo htmlspecialchars($friend['username']); ?></td>
								<td><?php echo htmlspecialchars(substr($friend['bio'] ?? '', 0, 50)); ?></td>
								<td>
									<div class="dashboard-actions">
										<form method="POST" action="parent/block_child_friend.php" style="display: inline;">
											<input type="hidden" name="friend_id" value="<?php echo $friend['id']; ?>">
											<button type="submit" class="btn btn-secondary">🚫 Block</button>
										</form>
										<form method="POST" action="parent/remove_child_friend.php" style="display: inline;">
											<input type="hidden" name="friend_id" value="<?php echo $friend['id']; ?>">
											<button type="submit" class="btn btn-secondary">❌ Remove</button>
										</form>
									</div>
								</td>
							</tr>
						<?php endwhile; ?>
					</tbody>
				</table>
			<?php else: ?>
				<div class="dashboard-empty">
					<div class="dashboard-empty__icon">👫</div>
					<p>No friends of tween yet</p>
				</div>
			<?php endif; ?>
		</section>

		<!-- G. Blocked Words Management -->
		<section class="dashboard-panel">
			<h2 class="dashboard-panel__title">🚫 Blocked Words Management</h2>
			<form method="POST" action="parent/add_blocked_word.php" class="blocked-words-form">
				<input type="text" name="word" class="form-input" placeholder="Add new blocked word" required>
				<button type="submit" class="btn btn-primary">Add Word</button>
			</form>

			<?php if (count($blocked_words) > 0): ?>
				<div class="blocked-words-list">
					<?php foreach ($blocked_words as $word): ?>
						<div class="blocked-word-badge">
							<?php echo htmlspecialchars($word['word']); ?>
							<form method="POST" action="parent/remove_blocked_word.php" style="display: inline; margin-left: 0.5rem;">
								<input type="hidden" name="word_id" value="<?php echo $word['id']; ?>">
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
