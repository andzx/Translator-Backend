<?php
require_once 'config.php';
require_once 'api.php';

// Fetch JSON input data
$input = json_decode(file_get_contents('php://input'), TRUE);

// Get session and token
$session = htmlspecialchars($input['session']);
$token = htmlspecialchars($input['token']);
$segment_id = htmlspecialchars($input['segment_id']);

$api = new API($db);
$api->validate_credentials($session, $token);
$api->assign_segment($segment_id);
?>