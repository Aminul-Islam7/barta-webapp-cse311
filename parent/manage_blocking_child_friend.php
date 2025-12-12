// Parent blocks/unblocks child's friend
<?php
session_start();
require "../db.php";

$parent_id = $_SESSION['parent_id'] ?? null;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: ../dashboard_parent.php");
    exit;
}

$action = $_POST['action'] ?? '';
$child_id = intval($_POST['child_id'] ?? 0);
$friend_id = intval($_POST['friend_id'] ?? 0);

// Basic validation
if (!$parent_id || !$child_id || !$friend_id || !in_array($action, ['block','remove'])) {
    header("Location: ../dashboard_parent.php");
    exit;
}

// Verify child belongs to parent
$check_child = mysqli_prepare($conn,"SELECT id, user_id FROM tween_user WHERE id = ? AND parent_id = ?");
mysqli_stmt_bind_param($check_child, "ii", $child_id, $parent_id);
mysqli_stmt_execute($check_child);
mysqli_stmt_store_result($check_child);

if (mysqli_stmt_num_rows($check_child) === 0) {
    mysqli_stmt_close($check_child);
    header("Location: ../dashboard_parent.php");
    exit;
}
mysqli_stmt_close($check_child);

if ($action === 'block') {
    // ========== BLOCK FRIEND ==========
    $stmt1 = mysqli_prepare($conn,"UPDATE FROM connection
                                   SET type = 'blocked' 
                                   WHERE (sender_id = ? AND receiver_id = ?) 
                                   OR (sender_id = ? AND receiver_id = ?)");
    mysqli_stmt_bind_param($stmt1, "iiii", $child_id, $friend_id, $friend_id, $child_id);
    mysqli_stmt_execute($stmt1);
    mysqli_stmt_close($stmt1);
    
} elseif( $action === 'unblock') {
    // ========== UNBLOCK FRIEND ==========
    $stmt2 = mysqli_prepare($conn,"UPDATE FROM connection
                                   SET type = 'added' 
                                   WHERE (sender_id = ? AND receiver_id = ?) 
                                   OR (sender_id = ? AND receiver_id = ?)");
    mysqli_stmt_bind_param($stmt2, "iiii", $child_id, $friend_id, $friend_id, $child_id);
    mysqli_stmt_execute($stmt2);
    mysqli_stmt_close($stmt2);
    
} elseif ($action === 'remove') {
    // ========== REMOVE FRIEND ==========
    $stmt = mysqli_prepare($conn,"DELETE FROM connection 
                                  WHERE (sender_id = ? AND receiver_id = ?) 
                                  OR (sender_id = ? AND receiver_id = ?)");
    mysqli_stmt_bind_param($stmt, "iiii", $child_id, $friend_id, $friend_id, $child_id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
}

// Redirect back to dashboard
header("Location: ../dashboard_parent.php");
exit;
?>