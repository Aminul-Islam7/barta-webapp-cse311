<?php
require 'db.php';

// 1. Create blocked_words table
$sql = "CREATE TABLE IF NOT EXISTS blocked_words (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    word VARCHAR(255) NOT NULL,
    FOREIGN KEY (user_id) REFERENCES tween_user(id) ON DELETE CASCADE
)";

if ($conn->query($sql) === TRUE) {
    echo "Table blocked_words created or exists.<br>";
} else {
    echo "Error creating table: " . $conn->error . "<br>";
}

// 2. Add columns to message table
$checkCol = $conn->query("SHOW COLUMNS FROM message LIKE 'is_clean'");
if ($checkCol->num_rows == 0) {
    $sql = "ALTER TABLE message ADD COLUMN is_clean TINYINT(1) DEFAULT 1";
    $conn->query($sql);
    echo "Added is_clean column.<br>";
} else {
    echo "Column is_clean exists.<br>";
}

$checkCol = $conn->query("SHOW COLUMNS FROM message LIKE 'parent_approval'");
if ($checkCol->num_rows == 0) {
    $sql = "ALTER TABLE message ADD COLUMN parent_approval ENUM('approved', 'pending', 'rejected') DEFAULT 'approved'";
    $conn->query($sql);
    echo "Added parent_approval column.<br>";
} else {
    echo "Column parent_approval exists.<br>";
}

// 3. Add some test data
// Let's assume user_id 1 wants to block 'badword'
// We need to know a valid user_id. Let's just insert for all users for testing or just one if we can find one.
// Actually, let's just make sure the table struct is there. I'll add a UI or manual insert for data later if needed, 
// OR I can insert a common word for a specific user ID if I knew it.
// Checking fetch_conversation.php I saw $tween_id usage.
// Let's just output success.

echo "Setup complete.";
?>