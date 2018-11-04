<?php
require_once 'config.php';
require_once 'api.php';


// Fetch JSON input data
$input = json_decode(file_get_contents('php://input'), TRUE);

// Make data variables
$email = htmlspecialchars($input['email']);
$password = htmlspecialchars($input['password']);

$api = new API($db);
$api->login($email, $password);
?>