<?php
require "db.php";
session_start();

// Manually set parent_id for testing
$parent_id = 1; // Change to your parent's ID

echo "<h1>Testing Flagged Messages</h1>";

// Your query
$query = "SELECT m.id, 
          tu_receiver.id AS tween_id, 
          tu_receiver.username AS child_name, 
          m.text_content, 
          m.sent_at,
          tu_sender.username AS from_user,
          m.is_clean,
          m.parent_approval
          
          FROM message m
          JOIN individual_message im ON m.id = im.message_id
          JOIN tween_user tu_receiver ON tu_receiver.user_id = im.receiver_id
          JOIN tween_user tu_sender ON tu_sender.user_id = m.sender_id
          
          WHERE m.is_clean = 0 
          AND m.is_deleted = 0 
          AND m.parent_approval = 'pending'
          AND tu_receiver.parent_id = $parent_id
          ORDER BY m.sent_at DESC";

$result = mysqli_query($conn, $query);

echo "<h2>Query:</h2><pre>" . htmlspecialchars($query) . "</pre>";
echo "<h2>Results:</h2>";

if ($result && mysqli_num_rows($result) > 0) {
    echo "<table border='1'><tr><th>ID</th><th>Child</th><th>From</th><th>Message</th><th>Is Clean</th><th>Parent Approval</th></tr>";
    while ($row = mysqli_fetch_assoc($result)) {
        echo "<tr>";
        echo "<td>" . $row['id'] . "</td>";
        echo "<td>" . $row['child_name'] . "</td>";
        echo "<td>" . $row['from_user'] . "</td>";
        echo "<td>" . htmlspecialchars(substr($row['text_content'], 0, 50)) . "...</td>";
        echo "<td>" . $row['is_clean'] . "</td>";
        echo "<td>" . $row['parent_approval'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "No results found.<br>";
    echo "Error: " . mysqli_error($conn);
}
?>