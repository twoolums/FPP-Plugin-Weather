#!/usr/bin/php
<?
error_reporting(0);

$pluginName ="Weather";
$myPid = getmypid();

$messageQueue_Plugin = "MessageQueue";
$MESSAGE_QUEUE_PLUGIN_ENABLED=false;

$DEBUG=false;

$skipJSsettings = 1;
include_once("/opt/fpp/www/config.php");
include_once("/opt/fpp/www/common.php");
include_once("functions.inc.php");

require ("lock.helper.php");

define('LOCK_DIR', '/tmp/');
define('LOCK_SUFFIX', '.lock');

$logFile = $settings['logDirectory']."/".$pluginName.".log";


$WEATHER_URL="http://api.openweathermap.org/data/2.5/weather?q=";

$messageQueuePluginPath = $pluginDirectory."/".$messageQueue_Plugin."/";

$messageQueueFile = urldecode(ReadSettingFromFile("MESSAGE_FILE",$messageQueue_Plugin));

if(file_exists($messageQueuePluginPath."functions.inc.php"))
        {
                include $messageQueuePluginPath."functions.inc.php";
                $MESSAGE_QUEUE_PLUGIN_ENABLED=true;

        } else {
                logEntry("Message Queue Plugin not installed, some features will be disabled");
        }

        $pluginConfigFile = $settings['configDirectory'] . "/plugin." .$pluginName;
        if (file_exists($pluginConfigFile))
        	$pluginSettings = parse_ini_file($pluginConfigFile);

$ENABLED = urldecode($pluginSettings['ENABLED']);//("ENABLED",$pluginName));

//echo "ENABLED: ".$ENABLED."\n";


if(($pid = lockHelper::lock()) === FALSE) {
        exit(0);
}

        if($ENABLED != "on" && $ENABLED != "1") {
                logEntry("Plugin Status: DISABLED Please enable in Plugin Setup to use & Restart FPPD Daemon");
                lockHelper::unlock();
                exit(0);
        }
        

        
	$SEPARATOR = urldecode($pluginSettings['SEPARATOR']);//("SEPARATOR",$pluginName));
	$CITY= urldecode($pluginSettings['CITY']);//("CITY",$pluginName));
	$STATE= urldecode($pluginSettings['STATE']);//("STATE",$pluginName);
	$INCLUDE_WIND = urldecode($pluginSettings['INCLUDE_WIND']);
	$INCLUDE_TEMP = urldecode($pluginSettings['INCLUDE_TEMP']);
	$INCLUDE_HUMIDITY = urldecode($pluginSettings['INCLUDE_HUMIDITY']);
	$API_KEY= urldecode($pluginSettings['API_KEY']);


//$WEATHER_URL .= $CITY;//.",".$STATE;
	$WEATHER_URL .= $CITY.",".$STATE."&APPID=".$API_KEY;
	
        //  Initiate curl
        $ch = curl_init();
        // Disable SSL verification
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        // Will return the response, if false it print the response
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        // Set the url
        curl_setopt($ch, CURLOPT_URL,$WEATHER_URL);
        // Execute
        $result=curl_exec($ch);
        // Closing
        curl_close($ch);

        // Will dump a beauty json :3

	$weatherData= json_decode($result,true);

	//print_r($weatherData);

	$currentTemp = $weatherData['main']['temp'];
	logEntry("Current tmep before conversion: ".$currentTemp);
	

	$currentTemp = (($currentTemp-273.15)*1.8)+32;

	logEntry("Current tmep after conversion: ".$currentTemp);
	
	//echo "current temp; ".$currentTemp."\n";
	$currentWind = (int)$weatherData['wind']['speed']." MPH";
	$currentWindDirection = $weatherData['wind']['deg'];

	//$currentWindDirection =349;

	if($currentWindDirection > 348 && $currentWindDirection <=359) {
		$currentWindDirection = "N";

	} elseif($currentWindDirection >=0 && $currentWindDirection <=45) {
		$currentWindDirection = "N";

	} elseif($currentWindDirection > 45 && $currentWindDirection <=68) {
		$currentWindDirection = "NE";

	} elseif($currentWindDirection > 68 && $currentWindDirection <= 112) {
		$currentWindDirection = "E";

	} elseif($currentWindDirection > 112 && $currentWindDirection <=168) {
		$currentWindDirection = "SE";

	} elseif($currentWindDirection > 168 && $currentWindDirection <= 192) {
		$currentWindDirection = "S";

	} elseif($currentWindDirection > 192 && $currentWindDirection <= 258) {
		$currentWindDirection = "SW";
	
	} elseif($currentWindDirection > 258 && $currentWindDirection <= 282) {
		$currentWindDirection = "W";

	} elseif($currentWindDirection > 282 && $currentWindDirection <= 348) {
		$currentWindDirection = "NW";
	} 

	//echo "current wind: ".$currentWind." direction: ".$currentWindDirection."\n";

	$clouds = $weatherData['clouds']['all'];

	if($clouds > 1) {
		$clouds = (int)$clouds."% cloud cover";
	}

	//echo "Clouds: ".$clouds."\n";

	$humidity = $weatherData['main']['humidity'];

	//echo "humidity: ".$humidity."%\n";
	
	//MessageText=""
	$messageText="";
	
	if($INCLUDE_TEMP == 1 || $INCLUDE_TEMP == "on")
		$messageText .= $SEPARATOR." Temp: ".$currentTemp;

	if($INCLUDE_WIND == 1 || $INCLUDE_WIND == "on")
		$messageText .= " ". $SEPARATOR." Wind: ".$currentWindDirection." ".$currentWind;

	if($INCLUDE_HUMIDITY == 1 || $INCLUDE_HUMIDITY == "on") 
		$messageText .= " ".$SEPARATOR." Humidity: ".$humidity."%";
	
	//$messageText = "Temp: ".$currentTemp." ".$SEPARATOR." Wind: ".$currentWindDirection." ".$currentWind." ".$SEPARATOR." Humidity: ".$humidity."%";
	//echo "messageText: ".$messageText."\n";

	logEntry("Weather string: ".$messageText);
	
	addNewMessage($messageText,$pluginName,$pluginData=$CITY." ".$STATE);

lockHelper::unlock();

?>
