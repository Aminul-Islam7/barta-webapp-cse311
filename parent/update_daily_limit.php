<?php
// Update child's daily message limit
session_start();
require "../db.php";  
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: ../dashboard_parent.php");
    exit;
}

$parent_id = $_SESSION['parent_id'] ?? null;
$tween_id  = intval($_POST['tween_id']);
$new_limit = intval($_POST['daily_limit']);

// Validate
if (!$parent_id || !$tween_id || $new_limit < 0) {
    header("Location: ../dashboard_parent.php");
    exit;
}

// Update limit
$update = mysqli_query($conn,"UPDATE tween_user 
                              SET daily_msg_limit = $new_limit
                              WHERE id = $tween_id AND parent_id = $parent_id AND is_active = 1");

// Redirect back to parent dashboard
header("Location: ../dashboard_parent.php");
exit;
?>





