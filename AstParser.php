<?php



$debug = false;

$registry['outpattern'] = array();

$rly_std_code = "";
$pstn_std_code = "";
$ntp_server = "10.169.250.99";

$gateways = array(); /**< PRI gateways here */
$registrars = array();
//$phones = array(); /**< To see that phone names are unique. */
$phone_names = array();
$phone_disp = array(); /**< To ensure that phone display names are unique. */ 
$macs = array(); /**< To see that the macs are correct and unique. */
$icom_no = array(); /**< To see that intercom numbers in an intercom is unique. */ 
$rly_no = array(); /**< To see that the rly numbers are unique. */
$vid = array();
$vid['icom'] = array();
$vid['phones'] = array();
$ipphone2 = array();

$parse_tree = array();
$parse_tree['pstn'] = array();
$parse_tree['conf'] = array();
$parse_tree['bnum'] = array();
$parse_tree['map'] = array();
$parse_tree['bregs'] = array();

function DEBUG($msg) {
    global $debug;
    if ($debug) {
        echo "** DEBUG **: $msg\n";
    }
}

function Error($msg) {
    global $line;
    global $cp;
    global $line_no;
    
    echo "**Error**: Line No. $line_no: $msg\n"; 
    echo "$line\n";
    for ($i = 0; $i < $cp; $i++) {
        echo ' ';
    }
    echo "^___ Error detected here.\n";
    exit(-1);
}

// This require should be defined here 
require 'names.php';
require 'exten.php' ;
require 'route.php';
require 'gwsip.php';
require 'ipphone.php';
require 'dir.php';
//require 'fxsport.php';
require 'analogport.php';
require 'conference.php';
require 'lib.php';
require 'byte.php';
require 'map.php';
require 'callgroup.php';
require 'boss_secy.php';
require 'backup.php';


$current_token = 0;
$line_no = 0;
$cp = 0; /**< Current Pointer */    
$line = "";
$rest_token = "";


function eat_white_spaces() {
    global $cp, $line;
    while ($cp < strlen($line)) {
        /* eat white spaces */
        if ($line[$cp] != ' ' and $line[$cp] != '\t') {
            break;
        }
        $cp++;
    }
}
    
function myalnum($ch) {
    if (!ctype_alnum($ch)) {
        if ($ch != '-' and $ch != '_') {
            return false;
        }
    }
    return true;
}

function __check_ip_byte($ip1)
{
    if (!($ip1 >= 0 and $ip1 <= 255)) {
        Error("Malformed IP address!");
    }
}

function get_token_ipv4() 
{
    $d = ".";
    $rv = ""; 
    $ip = get_token();
    __check_ip_byte($ip);
    match_token($d);
    $rv .= "${ip}.";

    $ip = get_token();
    __check_ip_byte($ip);
    match_token($d);
    $rv .= "${ip}.";

    $ip = get_token();
    __check_ip_byte($ip);
    match_token($d);
    $rv .= "${ip}.";

    $ip = get_token();
    __check_ip_byte($ip);
    $rv .= "${ip}";
    
    return $rv;
    
}


function get_token() {
    global $cp;
    global $line;

    DEBUG("In get_token()[A]: $line, $cp");
    
    if ($cp == strlen($line)) {
        DEBUG("In get_token(): Reached end of line.");
        return false;
    }
    
    eat_white_spaces();

    for ($i = $cp; $i < strlen($line); $i++) {
        DEBUG("In for loop[B] line[$i]: $line[$i]");
        if (!myalnum($line[$i])) {
            /* got a token */
            break;
        }
    }

    $token_len = $i - $cp;
    $token = substr($line, $cp, $token_len);
    $cp = $i;
    DEBUG("In get_token()[B]: $token, $cp, $token_len");
    return trim($token);
}

function get_token_till($ch) {
    global $cp;
    global $line;

    eat_white_spaces();
    $i = $cp;
    while($cp < strlen($line)) {
        if ($line[$cp] == $ch) {
            break;
        }
        $cp++;
    }
    $token = substr($line, $i, $cp - $i);
    $token = trim($token);
    DEBUG("In get_token_till(): Returning: \"$token\" - " . trim($token));
    return trim($token);
}

function lookup() {
    global $line, $cp;
    return $line[$cp];
}


function match_token($str) {
    global $cp;
    global $line;
    $i = 0;
    eat_white_spaces();
    if ($cp == strlen($line)) {
        Error("Expected: $str");
    }

    while ($cp < strlen($line) ) {
        DEBUG(" ****** cp: $line[$cp]");
        if ($i < strlen($str)) {
            if ($line[$cp] != $str[$i]) {
                Error("Expected: $str.");
            }
        } else {
            break;
        }
        
        $cp++;
        $i++;
    }
}



function validate_digits($n) {
    $p = 0;
    while ($p < strlen($n)) {
        if (!($n[$p] >= '0' and $n[$p] <= '9')) {
            Error("Expecting integers. Got \"$n\".");
        }
        $p++;
    }
}


function validate_ip($ip) {
    if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
        Error("The IP: \"$ip\" is not valid!");
    }
}

function validate_mac($mac) {
    if (!filter_var($mac, FILTER_VALIDATE_MAC)) {
        Error("The MAC-ID: \"$mac\" is not valid!");
    }
}
    
function do_ntp_server($l) {
    global $ntp_server;
    match_token("ntp-server");
    $t = get_token_ipv4();
    //validate_ip($t);
    $ntp_server = $t;
}

function do_rly_std_code($l) 
{
    global $rly_std_code;
    match_token("rly_std_code");
    $std = get_token();
    validate_digits($std);
    $rly_std_code = $std;
}

function do_pstn_std_code($l) 
{
    global $parse_tree;
    match_token("pstn_std_code");
    $std = get_token();
    validate_digits($std);
    $parse_tree['pstn_std_code'] = $std;
}

function is_valid_mac($mac)
{
  return (preg_match('/([a-fA-F0-9]{2}[:|\-]?){6}/', $mac) == 1);
}

function exists_phone($name) {
    global $phone_names;
    if(isset($phone_names[$name])) {
        Error("Phone \"$name\" exists. Defined at line ". $phone_names[$name]['line_no'] . ". ");
    }
} 

function phone_should_exist($name) {
    global $phone_names;
    if(!isset($phone_names[$name])) {
        Error("Phone \"$name\" does not exists.");
    }
} 

function register_phone_name($name, $type) {
    global $phone_names;
    DEBUG("register_phone_name(): Registering $name - $type");
    $phone_names[$name] = array();
    $phone_names[$name]['line_no'] = get_current_line_no();
    $phone_names[$name]['type'] = $type;
}

function is_analog_phone($name) {
    global $phone_names;
    return ($phone_names[$name]['type'] == 'ANALOG');
}

function register_mac($m) {
    global $macs;
    global $line_no;
    if(isset($macs[$m])) {
        Error("The MAC Id $m is already defined in line no. " . $macs[$m] . ".");
    }
    if (!is_valid_mac($m)) {
        Error("The MAC ID \"$m\" is not valid.");
    }

    $macs[$m] = $line_no;
}

function register_ph_disps($dname) {
    global $phone_disp;
    global $line_no;
    if(isset($phone_disp[$dname])) {
        Error("The dislay name $dname is already defined in line no. " . $phone_disp[$dname] . ".");
    }
    $phone_disp[$dname] = $line_no;
}

function register_icom_no($icom, $num) {
    global $icom_no;
    global $line_no;

    if (!ctype_digit($num)) {
        Error("The Intercom number should only have digits. Got - $num");
    }

    if(!isset($icom_no[$icom])) {
        $icom_no[$icom] = array();
        $icom_no[$icom][$num] = $line_no;
    } else {
        if (!isset($icom_no[$icom][$num])) {
            $icom_no[$icom][$num] = $line_no;
        } else {
            Error("Icom No. $num is already defined in Icom $icom at line No. " . $icom_no[$icom][$num] );
        }
    }
}

function register_pstn_no($num) {
    global $parse_tree, $line_no;

    if($num == -1) {
        /* No processing if we got a -1 */
        return;
    }

    if (!ctype_digit($num)) {
        Error("The PSTN number should have only digits. Got - $num" . ".");
    }

    if(!isset($parse_tree['pstn'][$num])) {
        $parse_tree['pstn'][$num] = $line_no;
    } else {
        Error("PSTN No. $num aready exists at line No. " . $parse_tree['pstn'][$num] . ".");
    }
}

function register_rly_no($num) {
    global $rly_no;
    global $line_no;

    if ($num == "-1") {
        /* No processing if we got a -1. */
        return;
    }

    if (!ctype_digit($num)) {
        Error("The railway number should have only digits. Got - $num" . ".");
    }

    if (!isset($rly_no[$num])) {
        $rly_no[$num] = $line_no;
    } else {
        Error("Rly No. $num already assigned at line No " . $rly_no[$num] . ".");
    }
}
    
function do_ipphone_action($n, $str) {
    global $registrars;
    global $phone_names, $line_no;

    $phone = explode(",", $str);
    
    foreach ($registrars as $rname => $regis) {
        foreach($regis['icoms'] as $iname => $icom) {
            if ($iname == $phone[0]) {
                /* Add Phone to this icom */
                DEBUG("Found Icom: $iname");
                $registrars[$rname]['icoms'][$iname]['phones'][$n] = array();
                $registrars[$rname]['icoms'][$iname]['phones'][$n]['disp_name'] = $phone[1];
                $registrars[$rname]['icoms'][$iname]['phones'][$n]['mac_id'] = $phone[2];
                $registrars[$rname]['icoms'][$iname]['phones'][$n]['make'] = $phone[3];
                $registrars[$rname]['icoms'][$iname]['phones'][$n]['icom_no'] = $phone[4];
                $registrars[$rname]['icoms'][$iname]['phones'][$n]['secret'] = $phone[5];
                $registrars[$rname]['icoms'][$iname]['phones'][$n]['unique_no'] = $phone[6];
                $registrars[$rname]['icoms'][$iname]['phones'][$n]['pstn'] = $phone[7];
				$registrars[$rname]['icoms'][$iname]['phones'][$n]['regis'] = $regis['ip'];
                break;
            }
        }
    }
    register_phone_name($n, 'IPPHONE');
    //$phones[$n] = $line_no;
}


function do_aphone_action($n, $str) {
    global $registrars;
    global $phone_names, $line_no;

    $phone = explode(",", $str);
    foreach ($registrars as $rname => $regis) {
        foreach($regis['icoms'] as $iname => $icom) {
            if ($iname == $phone[0]) {
                /* Add Phone to this icom */
                DEBUG("Found Icom: $iname");
                $registrars[$rname]['icoms'][$iname]['phones'][$n] = array();
                $registrars[$rname]['icoms'][$iname]['phones'][$n]['disp_name'] = $phone[1];
                $registrars[$rname]['icoms'][$iname]['phones'][$n]['icom_no'] = $phone[2];
                $registrars[$rname]['icoms'][$iname]['phones'][$n]['secret'] = $phone[3];
                $registrars[$rname]['icoms'][$iname]['phones'][$n]['unique_no'] = $phone[4];
                $registrars[$rname]['icoms'][$iname]['phones'][$n]['pstn'] = $phone[5];
                //$icom['phones'][$n] = $str;
                //$regis['icoms'][$iname]['phones'][$n] = $str;
                //print_r($icom);
                break;
            }
        }
    }
    register_phone_name($n, 'ANALOG');
    //$phones[$n] = $line_no;
}

function registrar_should_exist($name) {
    global $registrars;
    $rv = false;
    foreach ($registrars as $n => $regis) {
        if ($n == $name) {
            $rv = true;
            break;
        }
    }
    if (!$rv) {
        Error("Registrar $name does not exist.");
    }
}

function exists_registrar_name($name) {
    global $registrars;
    foreach ($registrars as $n => $regis) {
        if ($n == $name) {
            Error("Registrar $name already exists.");
        }
    }
}

function exists_registrar_ip($ip) {
    global $registrars;
    foreach ($registrars as $n => $regis) {
        if ($ip == $regis["ip"] or ($ip == $regis['ip_backup'])) {
            Error("Registrar IP: $ip already exists.");
        }
    }
}

function icom_should_exist($name) {
    global $registrars;
    foreach ($registrars as $n => $regis) {
        foreach($regis['icoms'] as $iname => $icom) {
            if ($name == $iname) {
                return true;
            }
        }
    }
    Error("Icom name $name does not exists.");
}


function exists_icom($name) {
    global $registrars;
    foreach ($registrars as $n => $regis) {
        foreach($regis['icoms'] as $iname => $icom) {
            if ($name == $iname) {
                Error("Icom $name exists in Registrar $n.");
            }
        }
    }
}

function do_registrar_action($name, $ip, $ip_backup) {
    global $registrars, $parse_tree;
    
    $registrars[$name] = array();
    $registrars[$name]['ip'] = $ip;
    $registrars[$name]['icoms'] = array();
    $registrars[$name]['ip_backup'] = $ip_backup;

    $parse_tree['bregs'][$name] = array();
    $parse_tree['bregs'][$name]['ip_backup'] = $ip_backup;
    $parse_tree['bregs'][$name]['ip_main'] = $ip;
    
}

function do_registrar($l) {
    global $registrars, $names, $line_no;
    DEBUG("In do_registrar(): $l");
    match_token("registrar"); /* Got the registrat */
    $name = get_token(); /* Get the name of the registrar */
    $names->add_name($name, $line_no);
    exists_registrar_name($name);
    match_token("(");
    $ip = get_token_ipv4();
    validate_ip($ip);
    exists_registrar_ip($ip);
    $ip_backup = '-1';
    if (lookup() == ",") {
        // Looks like a backup is also defined.
        match_token(",");
        $ip_backup = get_token_ipv4();
        exists_registrar_ip($ip_backup);
    }
    match_token(")");
    DEBUG("in do_resistrar(): name: $name, ip: $ip, ip_backup: $ip_backup");
    do_registrar_action($name, $ip, $ip_backup);
}

function do_icom($l) {
    global $registrars;

    DEBUG("In do_icom(): $l");
    match_token("icom");
    $name = get_token();
    exists_icom($name);
    match_token("(");
    $regis = get_token_till(")");
    match_token(")");
    DEBUG("In do_icom(): name: $name, regis: $regis");

    if (!isset($registrars[$regis]['ip'])) {
        Error("Registrar $regis is not defined.");
    }

    $registrars[$regis]['icoms'][$name] = array();
    $registrars[$regis]['icoms'][$name]['phones'] = array();
    $registrars[$regis]['icoms'][$name]['boss_secy'] = array();
    
}

function do_ipphone($l) {
    DEBUG("in do_ipphone(): $l");
    $comma = ",";
    match_token("ipphone");
    $name = get_token();
    exists_phone($name);
    match_token("(");
    $icom_name = get_token_till($comma);
    icom_should_exist($icom_name);
    match_token($comma);

    $disp_name = get_token_till($comma);
    register_ph_disps($disp_name);
    match_token($comma);

    $mac_id = get_token_till($comma);
    register_mac($mac_id);
    match_token($comma);

    $make = get_token_till($comma);
    match_token($comma);

    $icom_no = get_token_till($comma);
    register_icom_no($icom_name, $icom_no);
    match_token($comma);

    $secret = get_token_till($comma);
    match_token($comma);

    $rly_no = get_token_till($comma);
    register_rly_no($rly_no);
    match_token($comma);

    $pstn_no = get_token_till(")");
    register_pstn_no($pstn_no);
    match_token(")");

    $tmp = array($icom_name, $disp_name, $mac_id, $make, $icom_no, $secret, $rly_no, $pstn_no);
    $str = implode(",", $tmp);
    do_ipphone_action($name, $str);
}
                
function do_ipphone_($l) {
    DEBUG("In do_ipphone(): $l");
    match_token("ipphone");
    $name = get_token();
    exists_phone($name);
    match_token("(");
    $str = get_token_till(")");
    match_token(")");
    DEBUG("In do_ipphone(): Name: $name, Str: $str");
    $arr = explode(",", $str);
    if (sizeof($arr) != "8") {
        Error("The definition of ipphone is erronous. It must have 8 parameters.\n\t(<icom_name>,<disp_name>,<MAC_ID>,<make>,<icom_no>,<secret>,<rly_no>,<pstn_no>)\n\t Only " . sizeof($arr) . " found.");
    } else {
        do_ipphone_action($name, $str);
    }
    
}


function do_aphone($l) {
    DEBUG("in do_ipphone(): $l");
    $comma = ",";
    match_token("aphone");
    $name = get_token();
    exists_phone($name);
    match_token("(");
    $icom_name = get_token_till($comma);
    icom_should_exist($icom_name);
    match_token($comma);

    $disp_name = get_token_till($comma);
    register_ph_disps($disp_name);
    match_token($comma);

    $icom_no = get_token_till($comma);
    register_icom_no($icom_name, $icom_no);
    match_token($comma);

    $secret = get_token_till($comma);
    match_token($comma);

    $rly_no = get_token_till($comma);
    register_rly_no($rly_no);
    match_token($comma);

    $pstn_no = get_token_till(")");
    match_token(")");

    $tmp = array($icom_name, $disp_name, $icom_no, $secret, $rly_no, $pstn_no);
    $str = implode(",", $tmp);
    do_aphone_action($name, $str);
}


function do_aphone_($l) {
    DEBUG("In do_aphone(): $l");
    match_token("aphone");
    $name = get_token();
    exists_phone($name);
    match_token("(");
    $str = get_token_till(")");
    match_token(")");
    DEBUG("In do_aphone(): Name: $name, Str: $str");
    $arr = explode(",", $str);
    if (sizeof($arr) != "7") {
        Error("The definition of ipphone is erronous. It must have 8 parameters.\n\t(<icom_name>,<disp_name>,<make>,<icom_no>,<secret>,<rly_no>,<pstn_no>)\n\t Only " . sizeof($arr) . " found.");
    } else {
        do_aphone_action($name, $str);
    }

}


function exists_gateway_ip($ip, $type) {
    global $gateways;
    if (isset($gateways[$type])) {
        foreach($gateways[$type] as $name => $gw) {
            DEBUG("In exists_gateway_ip(): $ip, $type");
            if ($gateways[$type][$name]['ip'] == $ip){
                Error("The gateway IP \"$ip\" is already defined.");
            }
        }
    }
}

function exists_gateway_name($name) {
    global $gateways;
    foreach($gateways as $type => $gw) {
        foreach($gateways[$type] as $n => $gw2) {
            if ($name == $n) {
                Error("The gateway \"$n\" is already defined.");
            }
        }
    }
}


function do_gateway($l) {
    global $names, $line_no;
    DEBUG("In do_gateway(): $l");
    $comma = ",";

    match_token("gateway");
    $gwname = get_token();
    register_gateway($gwname);
    $names->add_name($gwname, $line_no); 
    
    match_token("(");
    
    $type = get_token();
			
    if (($type != "pri") and ($type != "fxs") and ($type != "fxo") and ($type != "sip")) {
        Error("Expected gateway type as \"pri\" or \"fxs\" or \"fxo\". Got \"$type\".");
    }
    
	match_token($comma);
    
    $npri = get_token();
    validate_digits($npri);
    match_token($comma);
    
    $ip = get_token_till(")");
    exists_gateway_ip($ip, $type);
    match_token(")");
    validate_ip($ip);
    
    DEBUG("In do_gateway(): Name: $gwname, Type: $type, PRI: $npri, IP: $ip");
	do_gateway_action($gwname, $type, $npri, $ip);	
}



function do_gateway_action($name, $type, $nports, $ip) {
    global $gateways;
    
	/* A better approach may be to write the following.
	 * 
	 * if(!isset($gateways[$type])) {
     *       $gateways[$type] = array();
     *   }
     *   $gateways[$type][$name] = array();
     *   $gateways[$type][$name]['ip'] = $ip;
	 *	
	 */

    switch($type) {
    case "pri": 
        if(!isset($gateways['pri'])) {
            $gateways['pri'] = array();
        }
        $gateways['pri'][$name] = array();
        $gateways['pri'][$name]['ip'] = $ip;
		$gateways['pri'][$name]['nports'] = $nports;
        break;

	case "fxs":
		if (!isset($gateways['fxs'])) {
				$gateways['fxs'] = array();
		}
		$gateways['fxs'][$name] = array();
		$gateways['fxs'][$name]['ip'] = $ip;
		$gateways['fxs'][$name]['nports'] = $nports;
		break;

    case "fxo":
        if (!isset($gateways['fxo'])) {
				$gateways['fxo'] = array();
		}
		$gateways['fxo'][$name] = array();
		$gateways['fxo'][$name]['ip'] = $ip;
		$gateways['fxo'][$name]['nports'] = $nports;
		break;

    case "sip":
        if (!isset($gateways['sip'])) {
            $gateways['sip'] = array();
		}
		$gateways['sip'][$name] = array();
		$gateways['sip'][$name]['ip'] = $ip;
		$gateways['sip'][$name]['nports'] = $nports;
		break;
        
    default:
        Error("Bug: Should not reach here. Please report it.");
        break;
    }
}

function register_gateway($name) {
    global $gateways, $line_no;
  
    if(!isset($gateways['names'])) {
        $gateways['names'] = array();
    }
    if(!isset($gateways['names'][$name])) {
        $gateways['names'][$name] = $line_no;
    } else {
        Error("Gateway $name already defined at line " . $gateways['names'][$name]);
    }
    global $debug;
    if ($debug) {
        echo "In register_gateways():";
        print_r($gateways);
    }
}

function gateway_should_exist($gw) {
    global $gateways;

    global $debug;
    if ($debug) {
        echo "In gateway_should_exist(): \"$gw\"";
        print_r($gateways);
    }
    if (!isset($gateways['names'][$gw])) {
        Error("Gateway $gw does not exist!");
    }
}

function register_outpattern($pat) {
    global $registry, $line_no;
    
    if (!isset($registry['outpattern'][$pat])) {
        $registry['outpattern'][$pat] = $line_no;
    } else {
        Error("Outpattern $pat already defined at line No. " . $registry['outpattern'][$pat] . ".\n");
    }
}

function do_outpattern($l) {
    $comma = ",";
    match_token("outpattern");

    $name = get_token();
    register_outpattern($name);
    match_token("(");
    
    $regis = get_token();
    registrar_should_exist($regis);
    match_token($comma);
    
    $pat = get_token_till($comma);
    match_token($comma);
    
    $gwlist = get_token_till(")");
    $gws = explode(":", $gwlist);
    $gws = array_map("trim", $gws);
    foreach ($gws as $g) {
        gateway_should_exist($g);
    }
    match_token(")");

    do_outpattern_action($name, $regis, $pat, $gws);
}

function do_outpattern_action($name, $regis, $pat, $gwarr) {
    global $registrars;

    if(!isset($registrars[$regis]['out'])) {
        $registrars[$regis]['out'] = array();
    }
    $registrars[$regis]['out'][$pat] = $gwarr;
	if ($debug) {
		echo "END of do_outpattern_action(): ";
		print_r($registrars);
	}
}


function do_translate($l) {
    match_token("translate");
    
    $name = get_token();
    match_token("(");
    
    $fn = get_token();
    switch($fn) {
    case "prefix":
    case "postfix":
    case "slice":
        break;
    default:
        Error("Unknown translate function: $fn");
    }

    match_token("(");
    $n = "";
    $offset = "";
    $len = "";
    switch($fn) {
    case "prefix":
    case "postfix":
        $n = get_token();
        /* $n can be digits on any other pattern */
        break;
    case "slice":
        $offset = get_token();
        validate_digits($offset);
        match_token(":");
        $len = get_token();
        validate_digits($len);
        break;
    default:
        Error("BUG - Should not be here!");
        break;
    }

    match_token(")");
    match_token(")");

    //do_translate_action($name, $fn, $n, $offset, $len);
}

function do_translate_action($name, $fn, $n, $offset, $len) {
    
}

function do_vlan($l) {
    match_token("vlan");
    $icom = get_token();
    icom_should_exist($icom);
    match_token("(");
    $vlanid = get_token();
    validate_digits($vlanid);
    match_token(")");
    do_vlan_action($icom, $vlanid);
}


function do_vlan_action ($icom, $vlanid) {
    global $vid;
    if(isset($vid['icom'][$icom])) {
        Error("Redefinition of vlan for $icom");
    }
    $vid['icom'][$icom] = $vlanid;
}

function do_phone_vlan($l) {
    match_token("phone-vlan");
    $ph = get_token();
    phone_should_exist($ph);
    match_token("(");
    $vlanid = get_token();
    validate_digits($vlanid);
    match_token(")");
    do_phone_vlan_action($ph, $vlanid);
}

function do_phone_vlan_action($ph, $id) {
    global $vid;
    if (isset($vid['phones'][$ph])) {
        Error("Redefinition of vlan for phone: $ph");
    }
    $vid['phones'][$ph] = $id;
}

function do_ipphone2($l) {
    $comma = ",";
    match_token("ipphone2");
    $pname = get_token();
    phone_should_exist($pname);
    match_token("(");
    $sip = get_token_till(",");
    validate_ip($sip);
    match_token($comma);
    $uid = get_token();
    match_token($comma);
    $passwd = get_token();
    $label = "";
    if (lookup() != ')') {
        /* A label for the phone has been specified. */
        match_token($comma);
        $label = get_token_till(")");
    }
    match_token(")");
    do_ipphone2_action($pname, $sip, $uid, $passwd, $label);
}

function do_ipphone2_action ($pname, $sip, $uid, $passwd, $label="") {
    global $ipphone2, $line_no;

    if (isset($ipphone2[$pname])) {
        Error("ipphone2 for $pname already defnied at " . $ipphone2[$pname]['line_no']);
    }

    $ipphone2[$pname] = array(
        'sip' => $sip,
        'uid' => $uid,
        'passwd' => $passwd,
        'line_no' => $line_no,
        'label' => $label, 
    );
}

function parse($l) {
    if ($l == "") return;
    $tmp = explode(" ", $l);
    $keyword = $tmp[0];
    switch($keyword) {
    case "callgroup":
        do_callgroup();
        break;
    case "map":
        do_map();
        break;
    case "byte":
        do_byte();
        break;
    case "conference":
        do_conference();
        break;
    case "fxoport":
        do_fxoport($l);
        break;
	case "fxsport":
		do_fxsport($l);
		break;
    case "ipphone2":
        do_ipphone2($l);
        break;
    case "phone-vlan":
        do_phone_vlan($l);
        break;
    case "vlan":
        do_vlan($l);
        break;
    case "ntp-server":
        do_ntp_server($l);
        break;
    case "rly_std_code":
        do_rly_std_code($l);
        break;
    case "pstn_std_code":
        do_pstn_std_code($l);
        break;
    case "route":
        do_route($l);
        break;
    case "translate":
        do_translate($l);
        break;
    case "outpattern":
        do_outpattern($l);
        break;
    case "registrar":
        do_registrar($l);
        break;
    case "icom":
        do_icom($l);
        break;
    case "ipphone":
        do_ipphone($l);
        break;
    case "aphone": 
        do_aphone($l);
        break;
    case "boss_secy":
        do_boss_secy($l);
        break;
    case "gateway":
        do_gateway($l);
        break;
    default:
        Error("Unknown keyword: $keyword");
        break;
    }
}


function parse_file($file_contents) {
    global $line, $line_no, $cp;
 
    $lines = explode("\n", $file_contents);
    foreach ($lines as $l) {
        $line = $l;
        $line_no++;
        $cp = 0;
        $code = trim($l);
        if (strlen($code) == 0) {
            /* Blank line. continue processing. */
            continue;
        }
        if ($code[0] == '#') {
            /* Comment */
            continue;
        }
        parse($code);
    }
}


function get_sip_conf_helper($arr, $regis_ip) 
{
    global $parse_tree;
    $rv = "";
    foreach($arr as $n => $g) {
        if ($regis_ip != $g['ip']) {
            $rv .= "[sip-$n]\n";
            $rv .= "context=rly\n";
            $rv .= "type=friend\n";
            $rv .= "qualify=yes\n";
            $rv .= "insecure=invite\n";
            $rv .= "host=". $g['ip'] . "\n";
            $rv .= "\n";
        }
    }
    return $rv;
}

function generate_sip_conf() {
    global $registrars;
    global $gateways, $parse_tree, $rly_std_code;
    $rv = array();

    foreach($registrars as $rname => $regis) {
        $regis_ip = $regis['ip'];
        $filename = "$rname-" . $regis['ip'] . "-sip.conf";
        $fout = fopen($filename, "w") or die ("Cannot open file $filename!");
        $rv[$rname] = "";
        $rv[$rname] .= "; sip.conf for Registrar: $rname\n\n";
        $rv[$rname] .= "; sip trunks to other registrars in the system\n\n";
        foreach($registrars as $r => $re) {            
            if ($r == $rname) {
                continue;
            } else {
                $rv[$rname] .= "[sip-$r]\n";
                $rv[$rname] .= "context=rly\n";
                $rv[$rname] .= "qualify=yes\n";
                $rv[$rname] .= "type=friend\n";
                $rv[$rname] .= "host=" . $re['ip'] . "\n";
                $rv[$rname] .= "\n";

                /* Does this has a backup as well? */
                if($parse_tree['bregs'][$r]['ip_backup'] != "-1") {
                    /* Do SIP trunk with the backup as well */ 
                    $rv[$rname] .= "[sip-$r-backup]\n";
                    $rv[$rname] .= "context=rly\n";
                    $rv[$rname] .= "qualify=yes\n";
                    $rv[$rname] .= "type=friend\n";
                    $rv[$rname] .= "host=" . $parse_tree['bregs'][$r]['ip_backup'] . "\n";
                    $rv[$rname] .= "\n";
                }
            }
        }

        if (isset($gateways['pri'])) {
            $rv[$rname] .= "; SIP trunk to PRI gateways\n\n";
            $rv[$rname] .= get_sip_conf_helper($gateways['pri'], $regis['ip']);
        }

        if (isset($gateways['sip'])) {
            $rv[$rname] .= "; SIP trunk to SIP gateways\n\n";
            $rv[$rname] .= get_sip_conf_helper($gateways['sip'], $regis['ip']);
        }

        if (isset($gateways['fxo'])) {
            $rv[$rname] .= "; SIP trunk to FXO gateway\n\n";
            $rv[$rname] .= get_sip_conf_helper($gateways['fxo'], $regis['ip']);
        }

        foreach($regis['icoms'] as $iname => $icom) {
            $rv[$rname] .= "; Phones for Icom: $iname\n\n";
            foreach($icom['phones'] as $ph => $phone) {
                $context = "icom-$iname";
                if (isset($phone['bnum'])) {
                    /* This phone is part of byte exchange */
                    $context = "icom-${iname}-byte";
                }
                $rv[$rname] .= "; Phone for $ph\n";
                $rv[$rname] .= "[" . $phone['unique_no'] . "]\n";
                $rv[$rname] .= "type=friend\n";
                $rv[$rname] .= "secret=" . $phone['secret'] . "\n";
                $rv[$rname] .= "context=$context\n";
                $rv[$rname] .= "qualify=yes\n";
                $rv[$rname] .= "host=dynamic\n";
                $rv[$rname] .= "callcounter=yes\n";
                $rv[$rname] .= "notifyhold=yes\n";
                $rv[$rname] .= "notifyringing=yes\n";
                $rv[$rname] .= "allowsubscribe=yes\n";
                $rv[$rname] .= "dtmfmode=rfc2833\n";
                $rv[$rname] .= "cc_agent_policy=generic\n";
                $rv[$rname] .= "cc_monitor_policy=generic\n";
                $rv[$rname] .= "sendrpid=pai\n";
                $rv[$rname] .= "trustrpid=yes\n";
                $rv[$rname] .= "busylevel=1\n";                
                $rv[$rname] .= "callerid=\"" . $phone['disp_name'] . "\"<". $phone['icom_no'] . ">\n";

                $rv[$rname] .= "setvar=CLI_RLY=\"" . $phone['disp_name'] . "\"<". $rly_std_code . $phone['unique_no'] . ">\n";

                if (isset($phone['callgroup'])) {
                    $rv[$rname] .= "namedcallgroup=" . $phone['callgroup'] . "\n";
                }
                if (isset($phone['pickupgroup'])) {
                    $rv[$rname] .= "namedpickupgroup=" . $phone['pickupgroup'] . "\n";
                }
                if (isset($phone['bnum'])) {
                    /* This phone is part of byte exchange */
                    $rv[$rname] .= "setvar=CLI_BYTE=\"" .$phone['disp_name'] . "\"<" .$phone['bnum'] . ">\n";
                }
                $rv[$rname] .= "\n";
            }
        }
        fwrite($fout, $rv[$rname]);
        fclose($fout);
    }

    gen_gw_sip();
    return $rv;
}


function generate_exten_conf() {
    __get_exten_code();
    gen_rly_exten_gws();
}

function generate_exten_conf_() {
    global $registrars, $rly_std_code;


    $rv = array();
    
    foreach($registrars as $rname => $regis) {
        $filename = "$rname-" . $regis['ip'] . "-extensions.conf";
        $fout = fopen($filename, "w") or die ("Cannot open file $filename!");
        $rv[$rname] = "";
        $rv[$rname] .= "; extensions.conf for Registrar: $rname\n\n";
        $rv[$rname] .= emit_fixed_code();
        foreach($regis['icoms'] as $iname => $icom) {
            $rv[$rname] .= "; Phones for Icom: $iname\n\n";
            $rv[$rname] .= "[icom-$iname-byte]\n";
            $rv[$rname] .= "include => icom-$iname\n";
            $rv[$rname] .= "include => byte-icom\n";
            $rv[$rname] .= "\n";
            $rv[$rname] .= "[icom-$iname]\n";
            foreach($com['phones'] as $ph => $phone) {
                $rv[$rname] .= "; " . $phone['disp_name'] . "\n";
				$rv[$rname] .= "exten => " . $phone['icom_no'] . ", hint, SIP/" . $phone['unique_no'] . "\n";
                $rv[$rname] .= "exten => " . $phone['icom_no'] . ", 1,  Dial(SIP/" . $phone['unique_no'] . ", 60, tT)\n";
                $rv[$rname] .= "\tsame => n, Hangup\n";

                
                if ($phone['pstn'] != "-1") {
                    if (($fxo_name = get_fxo_for_pstn($ph)) != "-1") {
                        $rv[$rname] .= "; " . $phone['disp_name'] . " can dial PSTN\n";
                        $part_dial_string = ", 1, GoSub(pstn,s,1(" . $phone['unique_no'] . "," . $phone['pstn'] . ",\${EXTEN},sip-" . $fxo_name . "))\n\tsame => n, Hangup\n";  
                        $rv[$rname] .= "exten => _NXXXXXXXXX/" . $phone['icom_no'] . $part_dial_string;
                        $rv[$rname] .= "exten => _0NXXXXXXXXX/" . $phone['icom_no'] . $part_dial_string;

                        if (isset($icom['boss_secy'][$ph])) {
                            /* A secy exists. Allow her to dial PSTN */
                            $secy = $icom['boss_secy'][$ph]['secy'];
                            $secy_icom = get_phone_info($secy)[4];
                            $rv[$rname] .= "; Secy can dial PSTN\n";
                            $rv[$rname] .= "exten => _NXXXXXXXXX/" . $secy_icom . $part_dial_string;
                            $rv[$rname] .= "exten => _0NXXXXXXXXX/" . $secy_icom . $part_dial_string;
                        }
                    }
                }
                $rv[$rname] .= "\n";
                
            }
			$rv[$rname] .= "\ninclude => rly\ninclude => outgoing\n\n\n";
        }
        
        
        $rv[$rname] .= generate_rly_extens($rname, true);
        $rv[$rname] .= generate_route_extens($rname, true);
        
        /* generate the [outgoing] context */
        $rv[$rname] .= "[gw-outgoing]\n";
        if (isset($regis['out'])) {
            foreach($regis['out'] as $pat => $gws) {
                $rv[$rname] .= "exten => _$pat, 1, Dial(SIP/sip-gw-$gws[0]/\${EXTEN})\n";
                $i = 1;
                while($i < count($gws)) {
                    $rv[$rname] .= "\tsame => n, Dial(SIP/sip-gw-" . $gws[$i] . "/\${EXTEN})\n";
                    $i++;
                }
                $rv[$rname] .= "\n";
            }
        }
        $rv[$rname] .= "[regis-incoming]\ninclude => rly\n\n";
        
        fwrite($fout, $rv[$rname]);
        fclose($fout);
    }

    gen_extens_gws();
    return $rv;

}



function generate_conf() {
    $rv = generate_sip_conf();
    /*
      foreach ($rv as $rname => $conf) {
        echo $conf;
    }
    */
    $rv = generate_exten_conf();
    /*
    foreach($rv as $rname => $conf) {
        echo $conf;
    }
    */
    gen_ip_phone_conf();
	gen_fxs_conf();
    gen_reg_backup_conf();
    generate_dir();
}

function main() {
    global $argv, $ipphone2;

    $ifile = $argv[1];    
    $input = file_get_contents($ifile);

    parse_file($input);
    //print_r($ipphone2);
    generate_conf();
	echo "\n";
}

function debug_main() {
    global $registrars;
    global $argv;

    global $rly_no;
    global $icom_no;
    global $phone_disp;
    global $phone_names;
    global $macs;
    global $routes;
    global $gateways, $parse_tree;

    $ifile = $argv[1];    
    $input = file_get_contents($ifile);

    parse_file($input);
    
    print_r($registrars);
    print_r($parse_tree);
    print_r($rly_no);
    print_r($icom_no);
    print_r($macs);
    print_r($phone_disp);
    print_r($phone_names);
    print_r($gateways);

    generate_conf();

}

$names = new Names();
//debug_main();
main();
//print_r($names);

?>
