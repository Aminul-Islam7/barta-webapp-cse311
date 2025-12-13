<?php
session_start();
header('Content-Type: application/json');
require "../db.php";

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'tween') {
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$tween_id = (int) $_SESSION['tween_id'];

$query = "SELECT 
            bu.full_name, 
            bu.email, 
            bu.birth_date, 
            tu.username, 
            tu.bio, 
            pbu.full_name AS parent_name
          FROM tween_user tu
          JOIN bartauser bu ON tu.user_id = bu.id
          LEFT JOIN parent_user pu ON tu.parent_id = pu.id
          LEFT JOIN bartauser pbu ON pu.user_id = pbu.id
          WHERE tu.id = ?";

$stmt = $conn->prepare($query);
$stmt->bind_param("i", $tween_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $data = $result->fetch_assoc();
    echo json_encode(['success' => true, 'data' => $data]);
} else {
    echo json_encode(['error' => 'User not found']);
}

$stmt->close();
$conn->close();
?>