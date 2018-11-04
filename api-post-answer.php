<?php
require_once 'config.php';
require_once 'api.php';


// Fetch JSON input data
$input = json_decode(file_get_contents('php://input'), TRUE);

// Make data variables
$session = htmlspecialchars($input['session']);
$token = htmlspecialchars($input['token']);
$text = htmlspecialchars($input['text']);
$project_id = htmlspecialchars($input['project_id']);
$segment_id = htmlspecialchars($input['segment_id']);
$request_id = htmlspecialchars($input['request_id']);

$api = new API($db);
$api->validate_credentials($session, $token);
$api->post_answer($text, $project_id, $segment_id, $request_id);
?>