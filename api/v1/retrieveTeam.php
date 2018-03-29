<?php
header("Content-Type: application/json");

$data = array();

if (!isSet($configLoaded)) {
include_once "../../config.php";
}

if (isSet($_GET["showHiddenData"])) {
	if ($_GET["showHiddenData"] == $hiddenDataKey) {
		$showHiddenData = true;
	} else {
		$showHiddenData = false;
	}
} else {
	$showHiddenData = false;
}

if (!isSet($_GET["teamNumber"])) {
	echo "{ \"Error\": \"Invalid request!\"}";
	http_response_code(400);
	exit;
} else {
	$data["TeamNumber"] = $_GET["teamNumber"];
}

if (!isSet($_GET["eventCode"])) {
	echo "{ \"Error\": \"Invalid request!\"}";
	http_response_code(400);
	exit;
} else {
	$data["EventCode"] = $_GET["eventCode"];
	$data["SeasonYear"] = substr($data["EventCode"],0,4);
}

$teamDataPath = $data["EventCode"]."/".$data["TeamNumber"];
	
if (!file_exists($teamDataPath)) {
	$teamDataPath = "api/v1/".$teamDataPath;
	if (!file_exists($teamDataPath)) {
		echo "{ \"Error\": \"Team data not found for the specified event!\" }";
		http_response_code(404);
		exit;
	}
}

if (filesize($teamDataPath."/pitScout.json")>0) {
	$file = fopen($teamDataPath."/pitScout.json","r");
    $data["Pit"] = json_decode(fread($file,filesize($teamDataPath."/pitScout.json")),true);
	
	$unneededData = array("App","Version","EventKey","TeamNumber","ScouterName");
	foreach($unneededData as $dataToRemove) {
		unset($data["Pit"][$dataToRemove]);
	}
	
    if (!$showHiddenData) {
        unset($data["Pit"]["NoAlliance"]);
    }
	
	fclose($file);
	
} else {
	$data["Pit"] = array(
		"Pre_StartingPos" => "Unknown",	
		"Auto_CrossedBaseline" => "Unknown",
		"Auto_Notes" => "Unknown",
		"Auto_PlaceSwitch" => "Unknown",
		"Auto_PlaceScale" => "Unknown",
		"Teleop_ScalePlace" => "Unknown",
		"Teleop_SwitchPlace" => "Unknown",
		"Teleop_ExchangeVisit" => "Unknown",
		"Teleop_Notes" => "Unknown",
		"RobotNotes" => "Unknown",
		"Teleop_Climb" => "Unknown",
		"Strategy_PowerUp" => "Unknown",
		"Strategy_General" => "Unknown",
    );
    if ($showHiddenData) {
        $data["Pit"]["NoAlliance"] = "Unknown";
    }
}

if (filesize($teamDataPath."/standScout.json")>0) {
	$file = fopen($teamDataPath."/standScout.json","r");
	$rawLine = explode("\n",fread ($file,filesize($teamDataPath."/standScout.json")));
	$LowPlace = 0;
	$HighPlace = 0;
	$LowDrop = 0;
	$HighDrop = 0;
    $Exchange = 0;
    $matchData = array();
	foreach($rawLine as $line) {
        $json = json_decode($line,true);
        if (!$showHiddenData) {
            unset($json["NoAlliance"]);
        }
		if ($json["Auto_PlaceScale"] == "Placed") $json["Auto_PlaceScale"] = 1;
		if ($json["Auto_PlaceSwitch"] == "Placed") $json["Auto_PlaceSwitch"] = 1;
		
		if (!isSet($json["Auto_DropSwitch"])) {
			$json["Auto_DropSwitch"] = 0;
			$json["Auto_DropScale"] = 0;
			$json["Teleop_SwitchDrop"] = 0;
			$json["Teleop_ScaleDrop"] = 0;
		}
		
        $matchData[$json["MatchNumber"]][] = $json;
		$LowPlace += $json["Auto_PlaceSwitch"] + $json["Teleop_SwitchPlace"];
		$HighPlace += $json["Auto_PlaceScale"] + $json["Teleop_ScalePlace"];
		$LowDrop += $json["Auto_DropSwitch"] + $json["Teleop_SwitchDrop"];
		$HighDrop += $json["Auto_DropScale"] + $json["Teleop_ScaleDrop"];
		$Exchange += $json["Teleop_ExchangeVisit"];
        
    }
    $data["Stand"] = array(
        "Matches" => $matchData,
        "AvgExchangeVisits" => $Exchange/(count($rawLine)-1),
        "AvgSwitchPlaces" => $LowPlace/(count($rawLine)-1),
        "AvgScalePlaces" => $HighPlace/(count($rawLine)-1),
		"AvgSwitchDrops" => $LowDrop/(count($rawLine)-1),
        "AvgScaleDrops" => $HighDrop/(count($rawLine)-1),
    );
	fclose($file);
} else {
    $data["Stand"] = array(
        "Matches" => array(),
        "AvgExchangeVisits" => "Unknown",
        "AvgSwitchPlaces" => "Unknown",
        "AvgScalePlaces" => "Unknown",
		"AvgSwitchDrops" => "Unknown",
        "AvgScaleDrops" => "Unknown",
    );
}

$url1 = 'http://www.thebluealliance.com/api/v3/event/'.$data["EventCode"].'/simple';
$ch1 = curl_init($url1);
curl_setopt($ch1, CURLOPT_HTTPHEADER, array('X-TBA-Auth-Key: '.$TBAAuthKey));
curl_setopt($ch1, CURLOPT_RETURNTRANSFER, true);
$result1 = json_decode(curl_exec($ch1),true);
if (isSet($result1["name"])) {
	$data["EventName"] = $result1["name"];
} else {
	$data["EventName"] = "Error getting event name.";
}
curl_close($ch1);

$url2 = 'http://www.thebluealliance.com/api/v3/team/frc'.$data["TeamNumber"].'/simple';
$ch2 = curl_init($url2);
curl_setopt($ch2, CURLOPT_HTTPHEADER, array('X-TBA-Auth-Key: '.$TBAAuthKey));
curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);
$result2 = json_decode(curl_exec($ch2),true);
if (isSet($result2["nickname"])) {
	$data["TeamName"] = $result2["nickname"];
} else {
    $data["TeamName"] = "Error getting team nickname.";
}
curl_close($ch2);

$url3 = 'http://www.thebluealliance.com/api/v3/team/frc'.$data["TeamNumber"].'/event/'.$data["EventCode"].'/status';
$ch3 = curl_init($url3);
curl_setopt($ch3, CURLOPT_HTTPHEADER, array('X-TBA-Auth-Key: '.$TBAAuthKey));
curl_setopt($ch3, CURLOPT_RETURNTRANSFER, true);
$result3 = json_decode(curl_exec($ch3),true);
if (isSet($result3["overall_status_str"])) {
	$data["TeamStatusString"] = $result3["overall_status_str"];
} else {
	$data["TeamStatusString"] = "Status Unavailable";
}
curl_close($ch3);

$url4 = 'http://www.thebluealliance.com/api/v3/team/frc'.$data["TeamNumber"]."/media/".$data["SeasonYear"];
$ch4 = curl_init($url4);
curl_setopt($ch4, CURLOPT_HTTPHEADER, array('X-TBA-Auth-Key: '.$TBAAuthKey));
curl_setopt($ch4, CURLOPT_RETURNTRANSFER, true);
$result4 = json_decode(curl_exec($ch4),true);
$data["Media"] = $result4;
curl_close($ch4);

$perferred = -1;
for ($i = 0; $i < count($result4) && $perferred == -1; $i++) {
	if ($result4[$i]["preferred"] == true) $perferred = $i;
}
if ($perferred == -1) $perferred = 0;

$data["Media"]["Preferred"] = $perferred;

echo json_encode($data);
?>