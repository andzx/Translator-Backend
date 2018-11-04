<?php
require_once 'config.php';
require_once 'api.php';


// Fetch JSON input data
$input = json_decode(file_get_contents('php://input'), TRUE);

// Make data variables
$session = htmlspecialchars($input['session']);
$token = htmlspecialchars($input['token']);
$title = htmlspecialchars($input['title']);
$description = htmlspecialchars($input['description']);
$glossary = htmlspecialchars($input['glossary']);
$segments = $input['segments'];

$api = new API($db);
$api->validate_credentials($session, $token);
$api->post_project($title, $description, $glossary, $segments);
?>