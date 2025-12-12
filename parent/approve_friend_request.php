<?php
// Approve/decline child's friend request
session_start();
require "../db.php";  
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
            SET receiver_parent_approved = 1, requester_parent_approved = 1 
            WHERE requester_id = $requester_id 
            AND receiver_id = $receiver_id";

    mysqli_query($conn, $sql);

 //Check if BOTH parents approved AND receiver accepted
    $check_sql = "SELECT * FROM connection_request
                  WHERE requester_id = $requester_id AND receiver_id = $receiver_id
                  AND receiver_parent_approved = 1 AND requester_parent_approved = 1
                  AND receiver_accepted = 1
                  LIMIT 1";

    $check_result = mysqli_query($conn, $check_sql);

    if ($check_result && mysqli_num_rows($check_result) === 1) {

        //Prevent duplicate connections
        $exists_sql = "SELECT 1 FROM connection
                       WHERE (sender_id = $requester_id AND receiver_id = $receiver_id)
                       OR (sender_id = $receiver_id AND receiver_id = $requester_id)
                       LIMIT 1";

        $exists_result = mysqli_query($conn, $exists_sql);

        if (!$exists_result || mysqli_num_rows($exists_result) === 0) {

            //CREATE FRIEND CONNECTION 🎉
            $insert_sql = "INSERT INTO connection (sender_id, receiver_id, type, added_at)
                           VALUES ($requester_id, $receiver_id, 'added', NOW())";
            mysqli_query($conn, $insert_sql);
        }
    }
// DECLINE REQUEST
} elseif ($action === "decline") {
    $sql = "UPDATE connection_request 
            SET receiver_parent_approved = -1, requester_parent_approved = -1
            WHERE requester_id = $requester_id 
            AND receiver_id = $receiver_id";

    mysqli_query($conn, $sql); 
}
// Redirect back to parent dashboard
header("Location: ../dashboard_parent.php");
exit;
?>









