<?php
//
// This is based on the a sample 'empty' DS plugin.
//
// Pluggable datasource for PHP Weathermap 0.9
// - return a statseeker data 

// TARGET statseeker:devicename:ifName:DataType


global $CONFIG_FILE;
$CONFIG_FILE="";


if(!file_exists($CONFIG_FILE)) {
  printf("lib/datasources/WeatherMapDataSource_statseeker.php:  Missing config file: $CONFIG_FILE\n");
  exit(0);
}


$debug_ss=0;


function json_decode_nice($cfg,$json, $assoc = FALSE){ 
    $json = str_replace(array("\n","\r"),"",$json); 
    $json = preg_replace('/([{,]+)(\s*)([^"]+?)\s*:/','$1"$3":',$json);
    $json = preg_replace('/(,)\s*}$/','}',$json);
    return json_decode($json,$assoc); 
}

function get_ss_data_old($cfg,$device,$port,$dtype) {

        global $CONFIG_FILE;
	$cfg = parse_ini_file($CONFIG_FILE);

        if (strpos($cfg["URL"], 'https') !== false) {
           $HTTP="https";
        } else {
           $HTTP="http";
        }


	$STATSEEKER_IP = $cfg["STATSEEKER"];
	$username = $cfg["USERNAME"];
	$password = $cfg["PASSWORD"];

	$context = stream_context_create(array(
	    'http' => array(
		'header'  => "Authorization: Basic " . base64_encode("$username:$password")
	    )
	));
	if($dtype != "Bps" && $dtype != "Util") {
		print "Statseeker: Invalid Data type $dtyle.    Valid types (Bps,Util)\n";
	}
	

$url = <<<EOD
$HTTP://$STATSEEKER_IP/api/port/?indent=2&links=none&fields=device.name,name,Tx$dtype,Rx$dtype&formats=max&where={%22port.name%22:[%22=%22,%22$port%22]}&where={%22device.name%22:[%22=%22,%22$device%22]}&match=all&timefilter=range%3Dnow%20-%202m%20TO%20now%20-%201m&limit=200
EOD;

#	print "API REQUEST TO: $url\n";

	$response = file_get_contents($url,false,$context);

#print $url;

	$res = json_decode_nice($cfg,$response,true);
       
	if(!isset($res["result"][0])) {
		print "NO DATA FOR URL:\n$url\n\n";
	}
	return array(intval($res["result"][0]["Rx$dtype"]["max"]), intval($res["result"][0]["Tx$dtype"]["max"]));
}



function get_ss_data($cfg,$device,$port,$dtype) {
        global $CONFIG_FILE;
    $cfg = parse_ini_file($CONFIG_FILE);

    if($dtype != "Bps" && $dtype != "Util") {
        print "Statseeker: Invalid Data type $dtyle.    Valid types (Bps,Util)\n";
    }

    $datafile = fopen("{$cfg["TMP_DIR"]}/ss-data-cache.csv.tmp","r");
    while(($data = fgetcsv($datafile,1000,",")) !== FALSE) {
        $num = count($data);
        print "$device,$data[0],$port,$data[1],$data[2],$data[3],$data[4],$data[5]\n";

        if($device==$data[0] and $port==$data[1]) {
            if($dtype == "Util" ) {
                return array(intval($data[2]),intval($data[3]));
            }
            if($dtype == "Bps" ) {
                return array(intval($data[4]),intval($data[5]));
            }
        }
    }

}



class WeatherMapDataSource_statseeker extends WeatherMapDataSource {


	function Init(&$map)
	{
		return(TRUE);
	}


	function Recognise($targetstring)
	{
		if(preg_match("/^statseeker:(\S+)$/",$targetstring,$matches))
		{ 	
			print "TARGET: $targetstring\n";
			return TRUE;
		}
		else
		{
			return FALSE;
		}
	}

	function ReadData($targetstring, &$map, &$item)
	{
		global $debug_ss;
		global $cfg;
		$data[IN] = NULL;
		$data[OUT] = NULL;
		$data_time = 0;


		if(preg_match("/^statseeker:(\S+)$/",$targetstring,$matches))
		{
			$fields = explode(":",$targetstring);
			$device = $fields[1];
			$port = $fields[2];
			$dtype = $fields[3];
			if($debug_ss) {
				print "Device: $device\nPort:$port\nType:$dtype\n";
			}

			$ret = get_ss_data($cfg,$device,$port,$dtype);

			$data[IN] = $ret[0];
			$data[OUT] = $ret[1];
		}

		print ("Statseeker Device: $device Port: $port   Returning (".($data[IN]===NULL?'NULL':$data[IN]).",".($data[OUT]===NULL?'NULL':$data[OUT]).",$data_time)\n");
		
		return( array($data[IN], $data[OUT], $data_time) );
		//return( array(32, 41, $data_time) );
	}
}

// vim:ts=4:sw=4:
?>
