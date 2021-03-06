<?php
switch($_SERVER['REQUEST_METHOD']){
  case 'GET':case 'POST':break;
  case 'OPTIONS':
    header("Access-Control-Allow-Origin: *");
    header("Access-Control-Allow-Headers: Last-Event-ID,".
      " Origin, X-Requested-With, Content-Type, Accept,".
      " Authorization");
    exit;
  default:
    header("HTTP/1.0 405 Method Not Allowed");
    header("Allow: GET,POST,OPTIONS");
    exit;
  }

$GLOBALS['is_longpoll'] = array_key_exists('longpoll',$_POST)
  || array_key_exists('longpoll',$_GET);
$GLOBALS['is_xhr'] = array_key_exists('xhr',$_POST)
  || array_key_exists('xhr',$_GET);
$GLOBALS['is_sse'] = !($GLOBALS['is_longpoll'] || $GLOBALS['is_xhr']);

date_default_timezone_set('UTC');
set_time_limit(0);

include_once("fxpair.id.php");

if($GLOBALS['is_sse'])header("Content-Type: text/event-stream");
else header("Content-Type: text/plain");
header('Cache-Control: no-cache, must-revalidate');
header('Expires: Sun, 31 Dec 2000 05:00:00 GMT');
header("Access-Control-Allow-Origin: *");

$symbols = array(
  new FXPair('EUR/USD', 1.3030, 0.0001, 5, 360, 47),
  new FXPair('EUR/USD', 1.3030, 0.0001, 5, 360, 47),
  new FXPair('USD/JPY', 95.10, 0.01, 3, 341, 55),
  new FXPair('AUD/GBP', 1.455, 0.0002, 5, 319, 39),
  );

function sendData($data){
if($GLOBALS['is_sse'])echo "data:";
echo json_encode($data)."\n";
if($GLOBALS['is_sse'])echo "\n";  //Extra blank line
@flush();@ob_flush();
}

function sendIdAndData($data){
if($GLOBALS['is_sse']){
    $id = $data['rows'][0]['id'];
    echo "id:".json_encode($id)."\n";
    }
sendData($data);
}


$clock = microtime(true);
if(isset($argc) && $argc>=2 && $argv[1]!='')
    $t = $argv[1];
elseif(array_key_exists('seed',$_REQUEST))
    $t = $_REQUEST['seed'];
else{
    $t = $clock;
    sendData( array('seed' => $t) );
    }

if(array_key_exists('HTTP_LAST_EVENT_ID',$_SERVER)){
    $lastId = $_SERVER['HTTP_LAST_EVENT_ID'];
    }
elseif(array_key_exists('last_id',$_POST)){
    $lastId = $_POST['last_id'];
    }
elseif(array_key_exists('last_id',$_GET)){
    $lastId = $_GET['last_id'];
    }
elseif(isset($argc) && $argc>=3)$lastId = $argv[2];  //commandline control
else $lastId = null;

if($lastId)$t = $lastId / 1000.0;

mt_srand($t*1000);

$nextKeepalive = time() + 15;

while(true){
  $sleepSecs = mt_rand(250,500)/1000.0;
  $now = microtime(true);
  $adjustment = $now - $clock;

  usleep( ($sleepSecs - $adjustment) * 1000000 );
  $t += $sleepSecs;
  $clock += $sleepSecs;

  $s = @file_get_contents("shutdown.txt");
  if($s){
    $when = strtotime($s);
    $untilSecs = $when - time();
    if($when > 0 && $untilSecs > 0){
      $until = date("Y-m-d H:i:s T",$when);
      sendData( array(
        'action' => 'scheduled_shutdown',
        'until' => $until,
        'until_secs' => $untilSecs
        ) );
      break;
      }
    //else ignore: either a bad timestamp or time has already passed
    }

  if(time()>$nextKeepalive){
    sendData( array(
      'action' => 'keep-alive',
      'timestamp' => gmdate("Y-m-d H:i:s")
      ) );
    $nextKeepalive = time() + 15;
    }
  $ix = mt_rand(0,count($symbols)-1);
  sendIdAndData($symbols[$ix]->generate($t));

  if($GLOBALS['is_longpoll'])break;
  }
