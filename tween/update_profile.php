<?php
session_start();
header('Content-Type: application/json');
require "../db.php";

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'tween') {
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$tween_id = (int) $_SESSION['tween_id'];
$user_id = (int) $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['error' => 'Invalid request method']);
    exit;
}

$full_name = trim($_POST['full_name'] ?? '');
$username = trim($_POST['username'] ?? '');
$bio = trim($_POST['bio'] ?? '');
$email = trim($_POST['email'] ?? '');

if (empty($full_name) || empty($username) || empty($email)) {
    echo json_encode(['error' => 'Name, Username and Email are required']);
    exit;
}

$checkUser = $conn->prepare("SELECT id FROM tween_user WHERE username = ? AND id != ?");
$checkUser->bind_param("si", $username, $tween_id);
$checkUser->execute();
if ($checkUser->get_result()->num_rows > 0) {
    echo json_encode(['error' => 'Username already taken']);
    exit;
}
$checkUser->close();

$checkEmail = $conn->prepare("SELECT id FROM bartauser WHERE email = ? AND id != ?");
$checkEmail->bind_param("si", $email, $user_id);
$checkEmail->execute();
if ($checkEmail->get_result()->num_rows > 0) {
    echo json_encode(['error' => 'Email already registered']);
    exit;
}
$checkEmail->close();

// Update transactions
$conn->begin_transaction();

try {
    // Update bartauser
    $stmt1 = $conn->prepare("UPDATE bartauser SET full_name = ?, email = ? WHERE id = ?");
    $stmt1->bind_param("ssi", $full_name, $email, $user_id);
    if (!$stmt1->execute()) {
        throw new Exception("Failed to update general info");
    }
    $stmt1->close();

    // Update tween_user
    $stmt2 = $conn->prepare("UPDATE tween_user SET username = ?, bio = ? WHERE id = ?");
    $stmt2->bind_param("ssi", $username, $bio, $tween_id);
    if (!$stmt2->execute()) {
        throw new Exception("Failed to update tween profile");
    }
    $stmt2->close();

    $conn->commit();
    echo json_encode(['success' => true, 'message' => 'Profile updated successfully']);
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['error' => $e->getMessage()]);
}

$conn->close();
?>