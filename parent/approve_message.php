<?php
// Approve/reject flagged messages
session_start();
require "../db.php";

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: ../parent_dashboard.php");
    exit;
}

$parent_id = $_SESSION['parent_id'] ?? null;
$message_id = intval($_POST['message_id']);
$action = $_POST['action'];

if ($action === "approve") {

    mysqli_query($conn,"UPDATE message 
                        SET is_clean = 1, parent_approval = 1 
                        WHERE id = $message_id");

    $_SESSION['msg_success'] = "Message approved and delivered.";

} 
else if ($action === "reject") {

    mysqli_query($conn,"UPDATE message 
                        SET is_deleted = 1, parent_approval = 0, is_clean = 0 
                        WHERE id = $message_id");

    $_SESSION['msg_success'] = "Message rejected and removed.";
}

// Redirect back to parent dashboard
header("Location: ../parent_dashboard.php");
exit;
?>