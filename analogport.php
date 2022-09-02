 <?php
 
$fxsportnames = array();
$fxoportnames = array();

$phones_fxs = array();


function fxs_gw_should_exist($name) {
	global $gateways;
	if (!isset($gateways['fxs'][$name])) {
		Error("The FXS gateway $name dows not exist!");
	}
}

function fxo_fw_should_exist($name) {
    global $gateways;
    if (!isset($gateways['fxo'][$name])) {
        Error("The FXO gateway $name does not exist!");
    }
}

function do_fxsport($l) 
{
	global $fxsportnames, $line_no, $gateways;
	$comma = ',';
	match_token("fxsport");
	$port_name = get_token();
	if (isset($fxsportnames[$port_name])) {
		Error("Port name $port_name already exists at line No. " . $fxsportnames[$port_name]);
	} else {
		$fxsportnames[$port_name] = $line_no;
	}
	match_token("(");
	$fxsgw = get_token();
	fxs_gw_should_exist($fxsgw);
	match_token($comma);
	$port_no = get_token();
	match_token($comma);
	$ph_name = get_token();
	phone_should_exist($ph_name);

    /* This phone should not have been assigned a port earlier. */
    if (isset($phones_fxs[$ph_name])) {
        Error ("This phone has been alloted an FXS port allotted at line " . $phones_fxs[$ph_name] . "!");
    }

    /* This phone should be analog */
    if(!is_analog_phone($ph_name)) {
        Error("FXS port being assigned to a phone which is not analog!");
    }
    
	match_token(")");
	
	/* do_fxs_port_action here */
	if (!isset($gateways['fxs'][$fxsgw]['ports'])) {
		$gateways['fxs'][$fxsgw]['ports'] = array();
	}
	if (isset($gateways['fxs'][$fxsgw]['ports'][$port_no])) {
		/* Port number is already defined */
		$msg = "Looks like port number $port_no for FXS gateway $fxsgw is being reassigned. Already assigned at line No. ";
		$msg .= $gateways['fxs'][$fxsgw]['ports'][$port_no]['line_no'];
		$msg .= " for phone ";
		$msg .= $gateways['fxs'][$fxsgw]['ports'][$port_no]['phone_name'];
        $msg .= ".";
		Error($msg);
		
	} else {
		$gateways['fxs'][$fxsgw]['ports'][$port_no] = array();
		$gateways['fxs'][$fxsgw]['ports'][$port_no]['line_no'] = $line_no;
		$gateways['fxs'][$fxsgw]['ports'][$port_no]['port_name'] = $port_name;
		$gateways['fxs'][$fxsgw]['ports'][$port_no]['phone_name'] = $ph_name;
	}

    $phones_fxs[$ph_name] = get_current_line_no();
}
 
 
function gen_fxs_conf() 
{
	global $gateways;
	$rv = "";
	if (isset($gateways['fxs']))  {
        $fxsmap = fopen("fxsmap.csv", "w") or die ("Cannot open file fxsmap.csv!");
		foreach ($gateways['fxs'] as $fxs => $val) {
            fwrite($fxsmap, $fxs . "," . $val['ip'] . "\n");
			$filename = $fxs . '-' . $val['ip'] . '.xml';
			$fout = fopen($filename, "w") or die ("Cannot open file $filename!");
			$rv  = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
			$rv .= "<config version=\"1.0\">\n";
			for ($i = 0; $i < $val['nports']; $i++) {
				if (isset($val['ports'][$i])) {
					list($disp_name, $uid, $pwd) = get_phone_info($val['ports'][$i]['phone_name']);
					$rv .= "  <port" . $i . ">\n";
					$rv .= "    <param ";
					$rv .= 'ipprofileindex="0" ';
					$rv .= 'telprofileindex="0" ';
					$rv .= 'display_name="' . $disp_name . '" ';
					$rv .= 'user_id="' . $uid . '" ';
					$rv .= 'auth_id="' . $uid . '" ';
					$rv .= 'auth_pwd="' . $pwd . '" />' . "\n";
					$rv .= "  </port" . $i . ">\n";
                    fwrite($fxsmap, "$i,$uid,$disp_name\n");
				} else {
					$rv .= "  <port" . $i . ">\n";
					$rv .= "    <param ";
					$rv .= 'ipprofileindex="" ';
					$rv .= 'telprofileindex="" ';
					$rv .= 'display_name="" ';
					$rv .= 'user_id="" ';
					$rv .= 'auth_id="" ';
					$rv .= 'auth_pwd="" />' . "\n";
					$rv .= "  </port" . $i . ">\n";
				}
			}
			$rv .= "</config>\n";
			fwrite($fout, $rv);
			fclose($fout);
		}
        fclose($fxsmap);
	}
}

function do_fxoport($l)
{
    global $line_no, $gateways;
    global $fxoportnames;
    $comma = ',';
    match_token("fxoport");
    $port_name = get_token();
    if (isset($fxoportnames[$port_name])) {
        Error("Port name $port_name already exists at Line No. " . $fxoportnames[$port_name] . ".");
    } else {
        $fxoportnames[$port_name] = $line_no;
    }
    match_token("(");
    $fxogw = get_token();
    match_token($comma);
    $port_no = get_token();
    match_token($comma);
    $ph_name = get_token();
    phone_should_exist($ph_name);
    
    if (get_phone_info($ph_name)[3] == "-1") {
        Error("No PSTN No. defined for Phone $ph_name.");
    }
    
    match_token(")");

    if(!isset($gateways['fxo'][$fxogw]['ports'])) {
        $gateways['fxo'][$fxogw]['ports'] = array();
    }

    if (isset($gateways['fxo'][$fxogw]['ports'][$port_no])) {
        $msg = "FXO port $port_no already allocated at line number ";
        $msg .= $gateways['fxo'][$fxogw]['ports'][$port_no]['line_no'];
        $mgs .= "for phone ";
        $msg .= $gateways['fxo'][$fxogw]['ports'][$port_no]['phone_name'];
        $msg .= ".";
        Error($msg);
    } else {
        $gateways['fxo'][$fxogw]['ports'][$port_no] = array();
        $gateways['fxo'][$fxogw]['ports'][$port_no]['line_no'] = $line_no;
        $gateways['fxo'][$fxogw]['ports'][$port_no]['port_name'] = $port_name;
        $gateways['fxo'][$fxogw]['ports'][$port_no]['phone_name'] = $ph_name;
    }
        
}


function get_fxo_for_pstn($ph_name) 
{
    global $gateways;
    foreach ($gateways['fxo'] as $fxo_name => $fv) {
        foreach($fv['ports'] as $p => $pv) {
            if ($ph_name == $pv['phone_name']) {
                return $fxo_name;
            }
        }
    }
    $pstn_no = get_phone_info($ph_name)[3];
    echo "We donot have the fxo port allocated for the PSTN number for phone $ph_name with PSTN No. ${pstn_no}.\n"; 
    return "-1";
}

?>