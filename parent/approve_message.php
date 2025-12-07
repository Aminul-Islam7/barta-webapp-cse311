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

if ($action === "approve") {
    mysqli_query($conn,"UPDATE message 
                        SET is_clean = 1, parent_approval = 1 
                        WHERE id = $message_id");
} 

else if ($action === "reject") {
    mysqli_query($conn,"UPDATE message 
                        SET is_deleted = 1, parent_approval = 0, is_clean = 0 
                        WHERE id = $message_id");
}
// Redirect back to parent dashboard
header("Location: ../dashboard_parent.php");
exit;
?>