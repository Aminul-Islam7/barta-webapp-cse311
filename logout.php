<?php

// Logout as Parent
session_start();
session_destroy();
header("Location: login.php");
exit();
