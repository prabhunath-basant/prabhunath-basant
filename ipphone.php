<?php

function do_mitel($ph, $icom, $phone_name, $regis_name) {
    $vlanid = 0;
    $rv = "";
    $nline = "\n";

    $rv .= "MAC=" . str_replace(":", "", $ph['mac_id']) . ".cfg\n";
    $rv .= "(\ncat <<MAC" . str_replace(":", "", $ph['mac_id']) . $nline;
	$rv .= "; Conf for " . $ph['disp_name'] . " \n";
    
    if (isset($vid['icom'][$icom])) {
        $vlanid = $vid['icom'][$icom];
    }
    
    if (isset($vid['phones'][$phone_name])) {
        $vlanid = $vid['phones'][$phone_name];
    }
    
    $rv .= "time server disabled: 0 " . $nline;
    $rv .= "time server1: 10.169.250.99 " . $nline;
    $rv .= "time zone name: Custom "  . $nline;
    $rv .= "time zone minutes: -330 " . $nline;
    $rv .= "dst minutes: 0 "  . $nline;
    $rv .= "lldp: 0 "  . $nline;

    $rv .= "sip proxy ip: " . $ph['regis'] . $nline ;
    $rv .= "sip proxy port: 0 " . $nline ;
    $rv .= "sip registrar ip: " . $ph['regis'] . $nline ;
    $rv .= "sip registrar port: 0 " . $nline ;
    $rv .= "sip registration period: 120 " . $nline ;
    $rv .= "sip registration retry timer: 120 " . $nline ;
    $rv .= "sip blf subscription period: 120 "  . $nline ;

    $rv .= "dhcp option 132 vlan id enabled: 1 " . $nline ;

    $rv .= "sip screen name: " . $ph['unique_no'] . ":" . $ph['disp_name'] . $nline;
    $rv .= "sip user name: " . $ph['unique_no'] . $nline;
    $rv .= "sip display name: " . $ph['disp_name'] . $nline;
    $rv .= "sip auth name: " . $ph['unique_no'] . $nline;
    $rv .= "sip password: " . $ph['secret'] . $nline;

    /*
     * TODO:
     * This code needs to be corrected for Mitel
     *********************************************************************
    if (isset($ipphone2[$phone_name])) {
        //* Define account 2 - here 
        $rv .= "account.2.enable = 1\n";
        $label = "";
        if ($ipphone2[$phone_name]['label'] != "") {
            $label = $ipphone2[$phone_name]['label'];
        } else {
            $label = $ipphone2[$phone_name]['uid'] . ":" . $ph['disp_name'];
        }
        $rv .= "account.2.label = $label\n";
        $rv .= "account.2.display_name = " . $ph['disp_name'] . $nline; 
        $rv .= "account.2.user_name = " . $ipphone2[$phone_name]['uid'] . $nline; 
        $rv .= "account.2.auth_name = " . $ipphone2[$phone_name]['uid'] . $nline;
        $rv .= "account.2.password = " .  $ipphone2[$phone_name]['passwd'] . $nline;
        $rv .= "account.2.sip_server1 = " .  $ipphone2[$phone_name]['sip'] . $nline;

        if ($ip_backup != "-1") {
            $rv .= "account.2.sip_server.2.address = ${ip_backup}\n";
        }    
    }
    
    */

    $rv .= "MAC" . str_replace(":", "", $ph['mac_id']) . $nline;
    $rv .= ") > \$MAC\n"; 
	$rv .= "\n";
	return $rv;

}

function do_yealink($ph, $icom, $phone_name, $regis_name) {
    global $vid, $ntp_server, $ipphone2;

    $vlanid = 0;
	$nline = "\n";
    $rv = "";
    $rv .= "MAC=" . str_replace(":", "", $ph['mac_id']) . ".cfg\n";
    $rv .= "(\ncat <<MAC" . str_replace(":", "", $ph['mac_id']) . $nline;
	$rv .= "; Conf for " . $ph['disp_name'] . " \n";
    
    if (isset($vid['icom'][$icom])) {
        $vlanid = $vid['icom'][$icom];
    }
    
    if (isset($vid['phones'][$phone_name])) {
        $vlanid = $vid['phones'][$phone_name];
    }
    
    $rv .= "account.1.enable = 1\n";
    $rv .= "account.1.label = " . $ph['disp_name'] . $nline;
    $rv .= "account.1.display_name = " . $ph['disp_name'] . $nline;
    $rv .= "account.1.auth_name = " . $ph['unique_no'] . $nline;
    $rv .= "account.1.user_name = " . $ph['unique_no'] . $nline;
    $rv .= "account.1.password = " . $ph['secret'] . $nline;
    $rv .= "account.1.sip_server.1.address = " . $ph['regis'] . $nline;

    $ip_backup = get_regis_backup($regis_name) ;
    if ($ip_backup != "-1") {
        $rv .= "account.1.sip_server.2.address = ${ip_backup}\n";
    }    

    $rv .= "local_time.time_zone = +5:30\n";
    $rv .= "local_time.ntp_server1 = " . $ntp_server . $nline;

    if (isset($ipphone2[$phone_name])) {
        /* Define account 2 - here */
        $rv .= "account.2.enable = 1\n";
        $label = "";
        if ($ipphone2[$phone_name]['label'] != "") {
            $label = $ipphone2[$phone_name]['label'];
        } else {
            $label = $ipphone2[$phone_name]['uid'] . ":" . $ph['disp_name'];
        }
        $rv .= "account.2.label = $label\n";
        $rv .= "account.2.display_name = " . $ph['disp_name'] . $nline; 
        $rv .= "account.2.user_name = " . $ipphone2[$phone_name]['uid'] . $nline; 
        $rv .= "account.2.auth_name = " . $ipphone2[$phone_name]['uid'] . $nline;
        $rv .= "account.2.password = " .  $ipphone2[$phone_name]['passwd'] . $nline;
        $rv .= "account.2.sip_server1 = " .  $ipphone2[$phone_name]['sip'] . $nline;

        if ($ip_backup != "-1") {
            $rv .= "account.2.sip_server.2.address = ${ip_backup}\n";
        }    
    }

    $rv .= "MAC" . str_replace(":", "", $ph['mac_id']) . $nline;
    $rv .= ") > \$MAC\n"; 
	$rv .= "\n";
	return $rv;

}

function do_akuvox ($ph, $icom, $phone_name, $regis_name) {
    global $vid, $ntp_server, $ipphone2;

    $vlanid = 0;
	$nline = "\n";
    $rv = "";
    $rv .= "MAC=" . str_replace(":", "", $ph['mac_id']) . ".cfg\n";
    $rv .= "(\ncat <<MAC" . str_replace(":", "", $ph['mac_id']) . $nline;
	$rv .= "; Conf for " . $ph['disp_name'] . " \n";
	$rv .= "Config.Autoprovision.Mode.Mode = 1 \n";
	$rv .= "Config.Network.PC.Type = 1\n";
	$rv .= "Config.Network.Lan.Type = 0\n";
    
    if (isset($vid['icom'][$icom])) {
        $vlanid = $vid['icom'][$icom];
    }
    
    if (isset($vid['phones'][$phone_name])) {
        $vlanid = $vid['phones'][$phone_name];
    }
    
    if ($vlanid != 0) {
        $rv .= "Config.Network.Vlan.LanVlanEnable = 1\n";
        $rv .= "Config.Network.Vlan.LanVid = $vlanid\n";
    }

	$rv .= "Config.Account1.General.Enable = 1\n";
	$label = $ph['icom_no'] . ":" . $ph['unique_no'];
	$rv .= "Config.Account1.General.Label = $label\n";
	$rv .= "Config.Account1.General.DisplayName = " . $ph['disp_name'] . $nline; 
	$rv .= "Config.Account1.General.Username = " . $ph['unique_no'] . $nline; 
	$rv .= "Config.Account1.General.AuthName = " . $ph['unique_no'] . $nline;
	$rv .= "Config.Account1.General.Pwd = " . $ph['secret'] . $nline;
	//$rv .= "Config.Account1.General.Pwd = 22222" . $nline;
	$rv .= "Config.Account1.Sip.Server = " . $ph['regis'] . "\n";
    $rv .= "Config.Account1.Sip.Port =\n";

    $ip_backup = get_regis_backup($regis_name) ;
    if ($ip_backup == "-1") {
        $rv .= "Config.Account1.Sip.Server2 =\n";
    } else {
        	$rv .= "Config.Account1.Sip.Server2 = ${ip_backup}\n";
    }    
	$rv .= "Config.Account1.Sip.Port2 =\n";
	$rv .= "Config.Account1.Dtmf.Type = 1\n"; /* This is RFC2833 */

    DEBUG("phone_name: $phone_name");
    if (isset($ipphone2[$phone_name])) {
        /* Define account 2 - GMICOM - here */
        $rv .= "Config.Account2.General.Enable = 1\n";
        $label = "";
        if ($ipphone2[$phone_name]['label'] != "") {
            $label = $ipphone2[$phone_name]['label'];
        } else {
            $label = $ipphone2[$phone_name]['uid'] . ":" . $ph['disp_name'];
        }
        $rv .= "Config.Account2.General.Label = $label\n";
        $rv .= "Config.Account2.General.DisplayName = " . $ph['disp_name'] . $nline; 
        $rv .= "Config.Account2.General.Username = " . $ipphone2[$phone_name]['uid'] . $nline; 
        $rv .= "Config.Account2.General.AuthName = " . $ipphone2[$phone_name]['uid'] . $nline;
        $rv .= "Config.Account2.General.Pwd = " .  $ipphone2[$phone_name]['passwd'] . $nline;
        $rv .= "Config.Account2.Sip.Server = " .  $ipphone2[$phone_name]['sip'] . "\n";
        $rv .= "Config.Account2.Sip.Port =\n";
        $rv .= "Config.Account2.Sip.Server2 =\n";
        $rv .= "Config.Account2.Sip.Port2 =\n";
        $rv .= "Config.Account2.Dtmf.Type = 1\n";
    }

	$rv .= "Config.RemotePhoneBook.Remote_Phone_Book0.DisplayName = ECRHQ\n";
	$rv .= "Config.RemotePhoneBook.Remote_Phone_Book0.Url=http://10.169.250.99/akuvox/akuvox-dir.xml\n";
	$rv .= "Config.Settings.SNTP.Enable = 1\n";
	$rv .= "Config.Settings.SNTP.TimeZone = +05:30\n";
	$rv .= "Config.Settings.SNTP.Name = IST\n";
	$rv .= "Config.Settings.SNTP.NTPServer1 = $ntp_server\n";
	$rv .= "Config.Settings.SNTP.NTPServer2 = 1.pool.ntp.org\n";
	$rv .= "Config.Settings.SNTP.DTS = 0\n";
	$rv .= "Config.Settings.DateTime.TimeFormat = 2\n";
	$rv .= "Config.Settings.DateTime.DateFormat = 2\n";
	$rv .= "Config.Settings.Login.Password = admin\n";
	$rv .= "Config.Settings.Web_Login.Password = 22222\n";//" . $ph['secret'] . "\n";
    $rv .= "MAC" . str_replace(":", "", $ph['mac_id']) . $nline;
    $rv .= ") > \$MAC\n"; 
	$rv .= "\n";
	return $rv;
}

	
function gen_ip_phone_conf() {
	global $registrars, $vid;
    $f = fopen("ipphone.sh", "w") or die ("Cannot open file ipphone.sh!");
	$rv = "";
	foreach ($registrars as $rname => $regis) {
        foreach($regis['icoms'] as $iname => $icom) {
			foreach($icom['phones'] as $name => $ph) {
				if (isset($ph['make'])) {
					switch(strtoupper($ph['make'])) {
                    case "AKUVOX-R67G":
                    case "AKUVOX-R55G":
                        $rv .= do_akuvox($ph, $iname, $name, $rname);
                        break;
                    case "YEALINK-T27G":
                    case "YEALINK-T23G":
                        $rv .= do_yealink($ph, $iname, $name, $rname);
                        break;
                    case "MITEL-6865":
                        $rv .= do_mitel($ph, $iname, $name, $rname);
                        break;
                    default:
                        echo "***Unknown IP phone make: ". strtoupper($ph['make']) . "\n";
                        break;
					}	
				}
			}
		}
	}
	fwrite($f, $rv);
    fclose($f);
}
	
?>
