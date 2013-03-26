<?php

require_once __DIR__ . '/lib/CTI/CTIClient.php';

$config = parse_ini_file(__DIR__ . '/config.ini');

$listener = new CTIClient('', $config);
$listener->connect();
$listener->listenLoop(true);

exit;
