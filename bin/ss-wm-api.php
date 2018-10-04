<?php

$VERSION="0.3.1";
$CONFIG_FILE="";

$myscript = $_SERVER["SCRIPT_FILENAME"];

if(!file_exists($CONFIG_FILE)) {
  printf("$myscript:  Missing config file: $CONFIG_FILE in ss-wm-api.php\n");
  exit(0);
} 

$ini_array = parse_ini_file($CONFIG_FILE);

if (strpos($ini_array["URL"], 'https') !== false) {
   $HTTP="https";
} else {
   $HTTP="http";
}

$STATSEEKER_IP=$ini_array["STATSEEKER"];
$username = $ini_array["USERNAME"];
$password = $ini_array["PASSWORD"];


$DeviceId2Name = array ();
$Ports = array ();




# Clean json_decode function.
function json_decode_nice($json, $assoc = FALSE){ 
    $json = str_replace(array("\n","\r"),"",$json); 
    $json = preg_replace('/([{,]+)(\s*)([^"]+?)\s*:/','$1"$3":',$json);
    $json = preg_replace('/(,)\s*}$/','}',$json);
    return json_decode($json,$assoc); 
}


function api_test() {
	global $username;
	global $password;
	global $STATSEEKER_IP;
	global $DeviceId2Name;
        global $HTTP;

	$context = stream_context_create(array(
	    'http' => array( 'header'  => "Authorization: Basic " . base64_encode("$username:$password"))
	));

$url = <<<EOD
$HTTP://$STATSEEKER_IP/api
EOD;
	$response = @file_get_contents($url,false,$context);
        if($response === FALSE) {
	    printf("AUTHFAIL: $username\n");
            exit;
        }
}



# Get all the devices in a group via the API.
function get_ss_group_id($group){
	global $username;
	global $password;
	global $STATSEEKER_IP;
	global $DeviceId2Name;
        global $HTTP;


	$groupstr = str_replace(' ', '%20', $group);

	$context = stream_context_create(array(
	    'http' => array( 'header'  => "Authorization: Basic " . base64_encode("$username:$password"))
	));

$url = <<<EOD
$HTTP://$STATSEEKER_IP/api/v2.1/group?indent=2&links=none&fields=name&name_filter=IS("$groupstr")
EOD;
	$response = file_get_contents($url,false,$context);
	$res = json_decode_nice($response,true);

	if(!isset($res["data"]["objects"][0]["data"][0]["id"])) {
		printf("ERROR: Can't find group: $group\n");
		printf("0");
		exit;
	}

        $id = $res["data"]["objects"][0]["data"][0]["id"];

	return $res["data"]["objects"][0]["data"][0]["id"];
}

# Get all the ports in a group via the API.
function get_ss_group_port($group){
	global $username;
	global $password;
	global $STATSEEKER_IP;
	global $DeviceId2Name;
	global $ini_array;
        global $HTTP;

	$datafile = fopen("{$ini_array["TMP_DIR"]}/ss-data-cache.csv.tmp","w");

	$context = stream_context_create(array(
	    'http' => array( 'header'  => "Authorization: Basic " . base64_encode("$username:$password"))
	));

	$group = get_ss_group_id($ini_array["GROUP"]);

$url = <<<EOD
$HTTP://$STATSEEKER_IP/api/v2.1/cdt_port/?fields=RxUtil,TxUtil,RxBps,TxBps,deviceid,name,ifIndex,ifName&RxUtil_formats=avg&TxBps_formats=avg&RxBps_formats=avg&TxUtil_formats=avg&groups=$group&links=none&index=3&limit=4000&indent=3&RxUtil_timefilter=range=now%20-2m%20to%20now%20-1m&TxUtil_timefilter=range=now%20-2m%20to%20now%20-1m&TxBps_timefilter=range=now%20-2m%20to%20now%20-1m&RxBps_timefilter=range=now%20-2m%20to%20now%20-1m&RxUtil_sort=1,desc,avg
EOD;
	$response = file_get_contents($url,false,$context);
	$portres = json_decode_nice($response,true);

# Loop to get device ids and then request their names.

	foreach ($portres["data"]["objects"][0]["data"] as $port) {
#		printf("NAME: ".$port["name"]."  deviceID: ".$port["deviceid"]."\n");
		$Devices[$port["deviceid"]] = 1;
	}



$url = <<<EOD
$HTTP://$STATSEEKER_IP/api/v2.1/cdt_device/?fields=id,name&links=none&id_filter=IN(
EOD;
	foreach (array_keys($Devices) as $d) {
		$url = $url.$d.",";
	}
	$url = $url.$d.")";
	print $url;


	$response = file_get_contents($url,false,$context);
	$res = json_decode_nice($response,true);
	foreach ($res["data"]["objects"][0]["data"] as $d) {
		$DevID2Name[$d["id"]] = $d["name"];
	}

  	foreach ($portres["data"]["objects"][0]["data"] as $port) {
                $ifname = $port["name"];  
                $ifnameFix = str_replace("/","-", $ifname);
                $devname = $DevID2Name[$port["deviceid"]];
                print "Dev: ".$devname." PORT: ".$ifname."\n";
                $cmd = sprintf("mv {$ini_array["TMP_DIR"]}/graph/%s.%s.png {$ini_array["GRAPH_DIR"]}/%s.%s.png\n", md5($devname),$port["ifIndex"], $devname,$ifnameFix);
                print $cmd;
                exec($cmd);

                $rxutil = $port["RxUtil"]["avg"];
                $txutil = $port["TxUtil"]["avg"];
                $rxbps = $port["RxBps"]["avg"];
                $txbps = $port["TxBps"]["avg"];
   		if($rxutil=="") {
			$rxutil = 0;
		}
   		if($txutil=="") {
			$txutil = 0;
		}
		fwrite($datafile,"$devname,$ifname,$rxutil,$txutil,$rxbps,$txbps\n");
        }
	fclose($datafile);

}


// Parse without sections

$options = getopt("grt");

if(isset($options["g"])) {
	$group = get_ss_group_id($ini_array["GROUP"]);
	print $group;
} 
elseif(isset($options["r"])) {
	get_ss_group_port($ini_array["GROUP"]);
}
elseif(isset($options["t"])) {
	api_test();
} else {
	print "Incorrect Options passed to PHP script";
}

?>
