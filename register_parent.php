<?php

// Parent signup

require "db.php";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    //Collecting data from the HTML form
    $full_name   = $_POST['full_name'];
    $email       = $_POST['email'];
    $password    = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $dob         = $_POST['dob'];
    $id_type     = $_POST['id_type'];
    $id_number   = $_POST['Id_Number'];

    $role = "parent_user";  
    $created = date("Y-m-d H:i:s");

    //Inserting into bartaUser table
    $sqlUser = "INSERT INTO bartaUser 
                (email, password_hash, full_name, birth_date, role, created_at)
                VALUES 
                ('$email', '$password', '$full_name', '$dob', '$role', '$created')";

    if ($conn->query($sqlUser) === TRUE) {

        //Get the new user ID
        $user_id = $conn->insert_id;

        //Inserting into parent table
        $sqlParent = "INSERT INTO parent_user 
                      (user_id, personal_id_type, personal_id_number, created_at)
                      VALUES 
                      ('$user_id', '$id_type', '$id_number', '$created')";

        if ($conn->query($sqlParent) === TRUE) {
            echo "<h2>Parent Registration Successful!</h2>";
        } else {
            echo "Error inserting into parent_user table: " . $conn->error;
        }

    } else {
        echo "Error inserting into bartaUser table: " . $conn->error;
    }

    $conn->close();
}
?>
