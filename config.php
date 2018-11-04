<?php
/*----------------------------------------------*/
/* This file contains the project configuration */
/*----------------------------------------------*/

$user = 'root';
$password = '';
$db = new PDO('mysql:host=localhost;port=3306;dbname=translator', $user, $password);
$db->setAttribute( PDO::ATTR_ERRMODE, PDO::ERRMODE_WARNING ); // During development only

?>