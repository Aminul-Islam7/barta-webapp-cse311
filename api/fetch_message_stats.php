<?php
session_start();
header('Content-Type: application/json');

require "../db.php";

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'tween') {
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$tween_id = (int) $_SESSION['tween_id'];

$limitQuery = "SELECT daily_msg_limit FROM tween_user WHERE id = $tween_id LIMIT 1";
$limitRes = mysqli_query($conn, $limitQuery);
$limitRow = mysqli_fetch_assoc($limitRes);
$dailyLimit = $limitRow ? (int) $limitRow['daily_msg_limit'] : 100;

$sentQuery = "SELECT COUNT(*) AS c FROM message WHERE sender_id = $tween_id AND DATE(sent_at) = CURDATE() AND is_deleted = 0";
$sentRes = mysqli_query($conn, $sentQuery);
$sentRow = mysqli_fetch_assoc($sentRes);
$sentCount = $sentRow ? (int) $sentRow['c'] : 0;

$receivedIndividualQuery = "SELECT COUNT(*) AS c 
                            FROM message m
                            JOIN individual_message im ON m.id = im.message_id
                            WHERE im.receiver_id = $tween_id 
                            AND DATE(m.sent_at) = CURDATE()
                            AND m.is_deleted = 0";
$receivedIndividualRes = mysqli_query($conn, $receivedIndividualQuery);
$receivedIndividualRow = mysqli_fetch_assoc($receivedIndividualRes);
$receivedCount = $receivedIndividualRow ? (int) $receivedIndividualRow['c'] : 0;



$response = [
    'success' => true,
    'daily_limit' => $dailyLimit,
    'sent_count' => $sentCount,
    'received_count' => $receivedCount
];

echo json_encode($response);
exit;
