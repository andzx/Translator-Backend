<?php
require_once 'config.php';
require_once 'api.php';


// Get session and token
$session = htmlspecialchars($_GET['session']);
$token = htmlspecialchars($_GET['token']);
$segment_id = htmlspecialchars($_GET['segment_id']);

// Initialize the API class
$api = new API($db);

// Perform operations
$api->validate_credentials($session, $token);
$api->get_source_segment($segment_id);
?>