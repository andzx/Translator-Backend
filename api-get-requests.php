<?php
require_once 'config.php';
require_once 'api.php';


// Get session and token
$session = htmlspecialchars($_GET['session']);
$token = htmlspecialchars($_GET['token']);

$api = new API($db);
$api->validate_credentials($session, $token);
$api->get_requests();
?>