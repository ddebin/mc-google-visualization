<?php
ini_set('include_path', realpath(dirname(__FILE__) . '/../lib/'));

require_once 'MC/Google/Visualization.php';

$db = new PDO('sqlite:example.db');
$vis = new MC_Google_Visualization($db);
?>
