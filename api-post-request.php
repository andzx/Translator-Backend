<?php
require_once 'config.php';
require_once 'api.php';


// Fetch JSON input data
$input = json_decode(file_get_contents('php://input'), TRUE);

// Make data variables
$session = htmlspecialchars($input['session']);
$token = htmlspecialchars($input['token']);
$text = htmlspecialchars($input['text']);
$context = htmlspecialchars($input['context']);
$project_id = htmlspecialchars($input['project_id']);
$segment_id = htmlspecialchars($input['segment_id']);

$api = new API($db);
$api->validate_credentials($session, $token);
$api->post_request($text, $context, $project_id, $segment_id);
?>