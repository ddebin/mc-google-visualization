<?php

use MC\Google\Visualization;

require_once __DIR__.'/../vendor/autoload.php';

$db = new PDO('sqlite:example.db');

/** @noinspection PhpUnhandledExceptionInspection */
$vis = new Visualization($db);
