<?php

require_once '../ProstieZvonki/ProstieZvonki.php';

$pz = ProstieZvonki::getInstance();

echo call_user_func($_GET['action'], $pz, $_GET);

function is_connected(ProstieZvonki $pz) {
	return $pz->isConnected();
}

function connect(ProstieZvonki $pz) {
	$pz->connect(array(
		'client_id'     => '101',
		'client_type'   => 'tinyCRM',
		'host'          => 'localhost',
		'port'          => '10150',
		'proxy_enabled' => 'false',
	));
}

function disconnect(ProstieZvonki $pz) {
	$pz->disconnect();
}

function call(ProstieZvonki $pz, array $input) {
	$pz->call($input['from'], $input['to']);
}

function get_events(ProstieZvonki $pz) {
	return json_encode($pz->getEvents());
}