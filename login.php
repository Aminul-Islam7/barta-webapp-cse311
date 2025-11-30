<!--Login for Parent-->

<?php
session_start();
require "db.php";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    //Validating email format
    if (!filter_var($_POST['email'], FILTER_VALIDATE_EMAIL)) {
        header("Location: login.php?error=invalid_email");
        exit;
    }

    $email = $_POST['email'];
    $password = $_POST['password'];

    //Checking email in database
    $sql = "SELECT * FROM bartaUser WHERE email = '$email' LIMIT 1";
    $result = $conn->query($sql);

    if ($result->num_rows == 1) {

        $user = $result->fetch_assoc();

        //Verifing password
        if (password_verify($password, $user['password_hash'])) {

            //Storing session data
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['full_name'] = $user['full_name'];

            //Redirect to homepage/dashboard
            header("Location: dashboard.php");
            exit();

        } else {
            echo "<h3>Incorrect Password</h3>";
			//header("Location: login.php?error=wrong_password");
            //exit;
        }

    } else {
        echo "<h3>No Account Found With That Email</h3>";
		//header("Location: login.php?error=no_account");
        //exit;
    }

    $conn->close();
}
?>

<!--php
// Login for Tween
-->