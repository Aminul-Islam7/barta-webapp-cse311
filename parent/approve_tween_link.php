<?php
// Approve/reject tween linking
session_start();
require "../db.php";

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: ../parent_dashboard.php");
    exit;
}

$parent_id = $_SESSION['parent_id'] ?? null;
$tween_id  = intval($_POST['tween_id']);    
$action = $_POST['action'];# Approve or reject

// APPROVE REQUEST
if ($action === "approve") {

    // Assign tween to parent
    mysqli_query($conn,"UPDATE tween_user 
                        SET parent_id = $parent_id 
                        WHERE id = $tween_id");
    // Update request from table
    mysqli_query($conn,"UPDATE tween_link_request 
                        SET status = 'approved'
                        WHERE tween_id = $tween_id AND parent_id = $parent_id"); 
    $_SESSION['msg_success'] = "Tween has been successfully linked to your account.";

} 

// REJECT REQUEST
else if ($action === "reject") {
    // Update only the pending request
    mysqli_query($conn,"UPDATE tween_link_request 
                        SET status = 'denied'
                        WHERE tween_id = $tween_id AND parent_id = $parent_id");
    $_SESSION['msg_success'] = "Tween link request rejected.";
}

// Redirect back to parent dashboard
header("Location: ../parent_dashboard.php");
exit;     
?>


