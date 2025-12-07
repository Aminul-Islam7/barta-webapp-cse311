<?php
// Approve/decline child's friend request
session_start();
require "../db.php";   // adjust path if needed
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: ../dashboard_parent.php");
    exit;
}

$requester_id = intval($_POST['requester_id']);
$receiver_id  = intval($_POST['receiver_id']);      
$action       = $_POST['action']; 
// APPROVE REQUEST
if ($action === "approve") {
    $sql = "UPDATE connection_request 
            SET receiver_accepted = 1 
            WHERE requester_id = $requester_id 
            AND receiver_id = $receiver_id";

    mysqli_query($conn, $sql);
// DECLINE REQUEST
} elseif ($action === "decline") {
    $sql = "UPDATE connection_request 
            SET receiver_accepted = 0
            WHERE requester_id = $requester_id 
            AND receiver_id = $receiver_id";

    mysqli_query($conn, $sql); 
}
// Redirect back to parent dashboard
header("Location: ../dashboard_parent.php");
exit;
?>
