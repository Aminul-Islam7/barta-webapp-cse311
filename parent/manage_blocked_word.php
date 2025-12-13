<?php
// Add forbidden word
session_start();
require "../db.php";

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: ../dashboard_parent.php");
    exit;
}

$parent_id = $_SESSION['parent_id'] ?? null;
$tween_id = intval($_POST['tween_id']);
$word = trim($_POST['word']);

$action = $_POST['action'] ?? '';  // 'add' or 'remove'

if ($action === 'add') {
    // ADD BLOCKED WORD
    $tween_id = intval($_POST['tween_id'] ?? 0);
    $word = trim($_POST['word'] ?? '');

    if (!$parent_id || !$tween_id || empty($word)) {
        header("Location: ../dashboard_parent.php");
        exit;
    }

    $query = "SELECT 1 FROM tween_user WHERE id = ? AND parent_id = ?";
    $check = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($check, "ii", $tween_id, $parent_id);
    mysqli_stmt_execute($check);
    mysqli_stmt_store_result($check);

    if (mysqli_stmt_num_rows($check) === 0) {
        mysqli_stmt_close($check);
        header("Location: ../dashboard_parent.php");
        exit;
    }
    mysqli_stmt_close($check);

    $checkQuery = "SELECT 1 FROM blocked_word WHERE tween_id = ? AND word = ?";
    $check_word = mysqli_prepare($conn, $checkQuery);
    mysqli_stmt_bind_param($check_word, "is", $tween_id, $word);
    mysqli_stmt_execute($check_word);
    mysqli_stmt_store_result($check_word);

    if (mysqli_stmt_num_rows($check_word) > 0) {
        mysqli_stmt_close($check_word);
        header("Location: ../dashboard_parent.php");
        exit;
    }
    mysqli_stmt_close($check_word);

    $stmt = mysqli_prepare($conn, "INSERT INTO blocked_word (tween_id, word) VALUES (?, ?)");
    mysqli_stmt_bind_param($stmt, "is", $tween_id, $word);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

} elseif ($action === 'remove') {
    $word_id = intval($_POST['word_id'] ?? 0);

    if (!$parent_id || !$word_id) {
        header("Location: ../dashboard_parent.php");
        exit;
    }

    $query = "SELECT bw.word_id 
                                    FROM blocked_word bw
                                    JOIN tween_user tu ON bw.tween_id = tu.id
                                    WHERE bw.word_id = ? AND tu.parent_id = ?";
    $check = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($check, "ii", $word_id, $parent_id);
    mysqli_stmt_execute($check);
    mysqli_stmt_store_result($check);

    if (mysqli_stmt_num_rows($check) === 0) {
        mysqli_stmt_close($check);
        header("Location: ../dashboard_parent.php");
        exit;
    }
    mysqli_stmt_close($check);
    // Delete the word (with prepared statement for security)
    $stmt = mysqli_prepare($conn, "DELETE FROM blocked_word WHERE word_id = ?");
    mysqli_stmt_bind_param($stmt, "i", $word_id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
}

header("Location: ../dashboard_parent.php");
exit;
?>