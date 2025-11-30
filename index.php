<?php

$server_name="localhost";
$user_name="root";  //user has all admin right now as root
$password="";
$db_name="barta_db";

//Creating Connection
$conn=mysqli_connect($server_name, $user_name, $password, $db_name);

//Checking connection
if(!$conn){
    die("Connection Failed: ".mysqli_connect_error());
}

if(isset($_POST['signup'])){  //if signup button is clicked

    //collect form data___filled html data in php
    $Full_Name = $_POST['full_name'];   //inside POST, MUST give EXACT html name 
    $DOB = $_POST['dob'];    
    $Username = $_POST['username'];

    $Password = $_POST['password'];    
    $Email = $_POST['email'];    
    $Bio = $_POST['bio'];

    //Data Insert Query

    //insert into=copied from sql(we didnt enter anything in sql, only for easier copy of row names)
    //values if from above php $ data
    $sql_query = "INSERT INTO birth_reg_info(`full_name`,`DOB`,`Gender`,`House_Village_Road`,`District`,`POST_CODE`,
    `Father_name`,`Father_NID`,`Mother_name`,`Mother_NID`,`Contact_Name`,`Contact_Phone`,`Contact_Relationship`)
    VALUES('$Full_Name','$DOB','$Gender','$address','$district','$postalCode','$Father_name','$Father_nid',
    '$Mother_name','$Mother_nid','$contactName','$contactPhone','$contactRelation');" ;

    //sending query to db__echo to print in php
    //Query Execution
    if(mysqli_query($conn,$sql_query)){
        echo "Barta Sign Up Information successfully inserted into db!";
    }
    else{
        echo "Error: ".$sql_query."<br>".mysqli_error($conn); //error>prints on webpg
    }

    mysqli_close($conn);
}

?>
