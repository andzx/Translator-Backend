<?php
require_once 'config.php';
require_once 'api.php';


// Fetch JSON input data
$input = json_decode(file_get_contents('php://input'), TRUE);

// Get session, token and segment id
$session = htmlspecialchars($input['session']);
$token = htmlspecialchars($input['token']);
$segment_id = htmlspecialchars($input['segment_id']);

$api = new API($db);
$api->validate_credentials($session, $token);

// Check what is being patched
if (isset($input['text'])) {
	// Patch target segment text
	$text = htmlspecialchars($input['text']);
	$api->patch_target_segment($segment_id, $text);
} else if (isset($input['complete'])) {
	// Patch target segment completion status
	$complete = htmlspecialchars($input['complete']);
	$api->patch_target_segment($segment_id, null, $complete);
}
?>