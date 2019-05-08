<?php
/**
 * EventSource endpoint
 * 
 * For details visit the link below
 * 
 * @link https://jmap.io/spec-core.html#event-source
 */
use go\core\App;
use go\core\db\Query;
use go\core\jmap\State;
use go\core\orm\EntityType;

require("../vendor/autoload.php");

//Create the app with the database connection
App::get()->setAuthState(new State());

//Hard code debug to false to prevent spamming of log.
App::get()->getDebugger()->enabled = true;

header('Cache-Control: no-cache');
header('Pragma: no-cache');
//header('Connection: keep-alive');
header("Content-Type: text/event-stream");

ini_set('zlib.output_compression', 0);
ini_set('implicit_flush', 1);

const CHECK_INTERVAL = 5;
const MAX_LIFE_TIME = 120;

$ping = $_GET['ping'] ?? 10;
$sleeping = 0;


function sendMessage($type, $data) {
	echo "event: $type\n";
	echo 'data: ' . json_encode($data);
	echo "\n\n";
	
	if(ob_get_level() > 0) {
		ob_flush();
	}
	
	flush();	
}
sendMessage('ping', []);

$query = new Query();
//Client may specify 
if(isset($_GET['types'])) {
	$entityNames = explode(",", $_GET['types']);
	$query->where('e.name', 'IN', $entityNames);
}
$entities = EntityType::findAll($query);
$map = [];
foreach($entities as $e) {
	$map[$e->getName()] = $e->getClassName();
}

function checkChanges() {
	global $map;
	
	$state = [];
	foreach ($map as $name => $cls) {		
		$cls::getType()->clearCache();
		$state[$name] = $cls::getState();
	}
	// sendMessage('ping', $state);
	return $state;
}

$changes = checkChanges();


function diff($old, $new) {

		$diff = [];
		
		foreach ($new as $key => $value) {
			if (!isset($old[$key]) || $old[$key] !== $value) {
				$diff[$key] = $value;
			}
		}

		return $diff;
	}
	
	//sendMessage('ping', []);
for($i = 0; $i < MAX_LIFE_TIME; $i += CHECK_INTERVAL) {

//	sendMessage('test', [$sleeping, $ping]);
	if ($sleeping >= $ping) {
		$sleeping = 0;
		sendMessage('ping', []);
	}
	
	$new = checkChanges();
	$diff = diff($changes, $new);
	if(!empty($diff)) {
		$sleeping = 0;
		sendMessage('state', $diff);
		$changes = $new;
	}
	$sleeping += CHECK_INTERVAL;
	sleep(CHECK_INTERVAL);
}