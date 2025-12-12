<?php
// Parent settings
session_start();
require "../includes/db.php";

if (!isset($_SESSION['parent_id'])) {
    header("Location: ../login.php");
    exit;
}

$parent_id = $_SESSION['parent_id'];

$q = mysqli_query($conn, "SELECT user_id FROM parent_user WHERE id = $parent_id");
$parent = mysqli_fetch_assoc($q);
$user_id = $parent['user_id'];

$action = $_POST['action'] ?? null;

if ($action === "change_name") {
    $full_name = trim($_POST['full_name']);

    mysqli_query($conn, "UPDATE bartauser SET full_name = '$full_name' WHERE id = $user_id");
    header("Location: ../dashboard_parent.php");
    exit;
}

if ($action === "change_password") {
    $old = $_POST['old_password'];
    $new = password_hash($_POST['new_password'], PASSWORD_BCRYPT);

    // verify old password
    $q = mysqli_query($conn, "SELECT password_hash FROM bartauser WHERE id = $user_id");
    $row = mysqli_fetch_assoc($q);

    if (!password_verify($old, $row['password_hash'])) {
        header("Location: ../dashboard_parent.php");
        exit;
    }

    mysqli_query($conn, "UPDATE bartauser SET password_hash = '$new' WHERE id = $user_id");
    header("Location: ../dashboard_parent.php");
    exit;
}

// Redirect back to dashboard
header("Location: ../dashboard_parent.php");
exit;
?>
