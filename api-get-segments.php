<?php
require_once 'config.php';
require_once 'api.php';


// Get session and token
$session = htmlspecialchars($_GET['session']);
$token = htmlspecialchars($_GET['token']);
$project_id = htmlspecialchars($_GET['project_id']);

$api = new API($db);
$api->validate_credentials($session, $token);
$api->get_segments($project_id);
?>