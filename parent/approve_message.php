<?php
// Approve/reject flagged messages
session_start();
require "../db.php";

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: ../dashboard_parent.php");
    exit;
}

$parent_id = $_SESSION['parent_id'] ?? null;
$message_id = intval($_POST['message_id']);
$action = $_POST['action'];

if (!$parent_id || !$message_id || !in_array($action, ['approve', 'reject'])) {
    header("Location: ../dashboard_parent.php");
    exit;
}

// Verify parent has permission to approve this message
$check_query = "SELECT 1 
                FROM message m
                JOIN individual_message im ON m.id = im.message_id
                JOIN tween_user tu ON tu.id = im.receiver_id
                WHERE m.id = ? AND tu.parent_id = ?";
                
$check_stmt = mysqli_prepare($conn, $check_query);
mysqli_stmt_bind_param($check_stmt, "ii", $message_id, $parent_id);
mysqli_stmt_execute($check_stmt);
mysqli_stmt_store_result($check_stmt);

if (mysqli_stmt_num_rows($check_stmt) === 0) {
    mysqli_stmt_close($check_stmt);
    header("Location: ../dashboard_parent.php");
    exit;
}
mysqli_stmt_close($check_stmt);

// Process approval or rejection
if ($action === "approve") {
    $stmt = mysqli_prepare($conn, "UPDATE message SET is_clean = 1, parent_approval = 'approved' WHERE id = ?");
    mysqli_stmt_bind_param($stmt, "i", $message_id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
} 
else if ($action === "reject") {
    $stmt = mysqli_prepare($conn, "UPDATE message SET is_clean = 0, parent_approval = 'rejected' WHERE id = ?");
    mysqli_stmt_bind_param($stmt, "i", $message_id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
}
// Redirect back to parent dashboard
header("Location: ../dashboard_parent.php");
exit;
?>
