<?php

//$debug = 1;


function generate_route_extens($regis, $do_clip) {
    global $routes;

    $rv = "[outgoing]\n";
    foreach($routes as $n => $rarr) {
        if ($rarr['name'] == $regis) {
			if ($do_clip) {
				$rv .= "exten => _" . $rarr['pat'] . ", 1, Set(CALLERID(all)=\${CLI_RLY})\n";
			} else {
				$rv .= "exten => _" . $rarr['pat'] . ", 1, Noop\n";
			}
            foreach($rarr['route_specs'] as $rs) {
                $rv .= "    same => n, Dial(";
                switch($rs['tech']) {
                case "sip":
                    $rv .= "SIP/sip-";
                    break;
                case "pri":
                    $rv .= "DAHDI/";
                    break;
                }

                $rv .= $rs['name'] . "/";
                if(isset($rs['fn'])) {
                    /* Transate function exists */
                    switch($rs['fn']['fn']) {
                    case "prefix":
                        $rv .= $rs['fn']['val'] . "\${EXTEN})\n";
                        break;
                    case "postfix":
                        $rv .= "\${EXTEN}" . $rs['fn']['val'] . ")\n";
                        break;
                    case "slice":
                        $rv .= "\${EXTEN:" . $rs['fn']['val'] . "})\n";
                        break;
                    case "preslice":
                        $rv .= $rs['fn']['val'][0] . "\${EXTEN:" . $rs['fn']['val'][1] . "})\n";
                        break;
                    default:
                        Error("Bug: In gnerate_route_exten().");
                        break;
                    }
                } else {
                    $rv .= "\${EXTEN})\n";
                }
            }
            $rv .= "    same => n, Hangup\n\n";
        }
    }
    return $rv;
}    

function gen_rly_exten_gws()
{
    global $gateways;
    $keys = ['pri', 'sip'];
    foreach($keys as $k) {
        if(isset($gateways[$k])) {
            foreach($gateways[$k] as $gwname => $gw) {
                $file_name = "$gwname-" . $gw['ip'] . "-extensions.conf";
                $fout = fopen($file_name, "w") or die("Cannot open $filename!");
                $rv = "";
                $rv .= emit_fixed_code();
                $rv .= get_rly_exten_code($gwname, false);
                $rv .= get_conf_exten_code($gwname);
                $rv .= generate_route_extens($gwname, false);
                fwrite($fout, $rv);
                fclose($fout);
            }
        }
    }

}

function emit_fixed_code()
{
    $rv = "";
    $rv .= emit_pstn_code();
    $rv .= emit_dial_rly_code();
    $rv .= emit_change_conf_pin_code();
    $rv .= emit_feature_code();
    return $rv;
}

function emit_feature_code() 
{
    $rv = <<<END
[rly]
;conference admin - play pin.
exten => *26630*, 1, Set(CALLERID(all)=\${CLI_RLY})
    same => n, Set(ADMIN=\${CALLERID(num)})
    same => n, Goto(rly,playpin-\${ADMIN:-5},1)

;conference admin - set pin.
exten => *26631*, 1, Set(CALLERID(all)=\${CLI_RLY})
    same => n, Set(ADMIN=\${CALLERID(num)})
    same => n, Goto(rly,setpin-\${ADMIN:-5},1)

exten => *30, 1, CallCompletionRequest
    same => n, Answer(500)
    same => n, Playback(auth-thankyou)
    same => n, Hangup

exten => *31, 1, CallCompletionCancel
    same => n, Answer(500)
    same => n, Playback(auth-thankyou)
    same => n, Hangup

exten => *38, 1, Answer
    same => n, SayUnixTime(,Asia/Kolkata,ABdY \’digits/at\’ IMp)
    same => n, Hangup


END;
    return $rv;
}

function emit_pstn_code()
{
    $rv = <<<END
[dial_pstn]
;ARG1 -> Rly No.
;ARG2 -> PSTN No.
;ARG3 -> Dialed EXTENSION
;ARG4 -> FXO SIP trunk
exten => s, 1, Set(GROUP(pstn)=\${ARG2})
    same => n, Set(COUNT=\${GROUP_COUNT(\${ARG2}@pstn)})
    same => n, GotoIf(\$[\${COUNT}>1]?busy:continue)
    same => n(busy), Answer
    same => n, Palytone(busy)
    same => n, Hangup
    same => n(continue), Dial(SIP/\${ARG4}/\${ARG1}\${ARG3}, 60, tT) 
    same => n, Hangup


END;
    
    return $rv;
}

function emit_dial_rly_code() 
{
    $rv = <<<END
[dial_rly_local]
; ARG1 -> dialed railway extension
; ARG2 -> Secy if any
; ARG3 -> secy type. Currently default|only_secy.
; ARG4 -> Should CALLERID be modified? 
exten => s,1,GotoIf($["\${ARG4}" != "yes"]?normal)
    same => n, Set(CALLERID(all)=\${CLI_RLY})
    same => n(normal), GotoIf($["0\${ARG2}" == "0"]?nosecy:secy)
    same => n(nosecy), Dial(SIP/\${ARG1}, 60, tT)
    same => n, Hangup
    same => n(secy), GotoIf(\$["\${ARG3}" == "default"]?default)
    same => n, GotoIf($["\${ARG3}" == "only_secy"]?only_secy)
    same => n, Noop(Falblack case is same as default.)
    same => n(default), Dial(SIP/\${ARG2}, 25, tT)
    same => n, Dial(SIP/\${ARG1}, 60, tT)
    same => n, Hangup
    same => n(only_secy), Dial(SIP/\${ARG2}, 60, tT)
    same => n, Hangup


[dial_rly_remote]
; ARG1 -> Rly No.
; ARG2 -> Primary SIP peer - required.
; ARG3 -> Secondary SIP Peer- May be blank
exten => s,1,GotoIf($["0\${ARG3}" == "0"]?no-secondary)
    same => n,Set(STATUS=\${SIPPEER(\${ARG2},status)})
    same => n, GotoIf($["\${STATUS}" == "UNREACHABLE"]?secondary)
    same => n(no-secondary), Dial(SIP/\${ARG2}/\${ARG1})
    same => n, Hangup
    same => n(secondary),Dial(SIP/\${ARG3}/\${ARG1})
    same => n, Hangup 



END;
    return $rv;
}

function emit_change_conf_pin_code()
{
    $rv = <<<END
[change-conf-pin]
exten => s, 1, Answer
    same => n, Playback(conf-getpin)
    same => n, Read(pin,,4)
    same => n, Set(DB(conf/\${ARG1})=\${pin})
    same => n, Goto(rly,playpin-\${ARG2},1)
    same => n, Hangup


END;
    return $rv;
}


function get_byte_exten_code()
{
    global $parse_tree;
    $rv = "";
    $rv .= "[byte-icom]\n";
    foreach($parse_tree['bnum'] as $ext => $bv) {
        //$rv .= "exten => $ext, 1, Dial(
        $rv .= "exten => $ext, 1, Set(CALLERID(all)=\${CLI_BYTE})\n";
        $rv .= "\tsame => n, Goto(rly,byte-" . $bv['rly_no'] . ",1)\n";
    }
    $rv .= "\n"; 
    return $rv;
}

function get_map_exten_code()
{
    global $parse_tree;
    $found = false;
    $rv = "";
    foreach($parse_tree['map'] as $regis => $mv) {
        foreach ($mv as $num => $rly_no) {
            $found = true;
            $rv .= "exten => $num, 1, Goto(rly,$rly_no,1)\n";
        }
    }

    if ($found) {
        $rv = "[rly]\n" . $rv;
    }
    return $rv;
}

function get_icom_exten_code($icom_name, $icom_arr)
{
    $icom_exten = "icom-$icom_name";
    $rv = "[$icom_exten-byte]\n";
    $rv .= "include => ${icom_exten}\n";
    $rv .= "include => byte-icom\n";
    $rv .= "\n";
    $rv .= "[icom-${icom_name}]\n";
    foreach($icom_arr['phones'] as $ph => $pv) {
        $icom_no = $pv['icom_no'];
        $sip = $pv['unique_no'];
        $rv .= ";$ph - " . $pv['disp_name'] . "\n";
        $rv .= "exten => $icom_no,hint,SIP/$sip\n";
        $rv .= "exten => $icom_no,1,Dial(SIP/$sip, 60, tT)\n";
        $rv .= "\tsame => n, Hangup\n";

        if (isset($pv['pstn']) and $pv['pstn'] != "-1") {
            $rly_no = $pv['unique_no'];
            $pstn_no = $pv['pstn'];
            $icom_no = $pv['icom_no'];
            $sip_fxo = "sip-" . get_fxo_for_pstn($ph);
            $rv .= "exten => _NXXXXXXXXX/$icom_no,1,GoSub(dial_pstn,s,1($rly_no,$pstn_no,\${EXTEN},$sip_fxo))\n";
            $rv .= "exten => _0ZXXXXXXXXX/$icom_no,1,GoSub(dial_pstn,s,1($rly_no,$pstn_no,\${EXTEN},$sip_fxo))\n";
            /* Do we have a secy defined as well? */
            if (isset($pv['secy'])) {
                /* Secy should also use the PSTN No. */
                $secy_icom_no = get_phone_info($pv['secy'])[4]; 
                $rv .= "exten =>  _NXXXXXXXXX/$secy_icom_no,1,GoSub(dial_pstn,s,1($rly_no,$pstn_no,\${EXTEN},$sip_fxo))\n";
                $rv .= "exten => _0ZXXXXXXXXX/$secy_icom_no,1,GoSub(dial_pstn,s,1($rly_no,$pstn_no,\${EXTEN},$sip_fxo))\n";
            }
        }
        $rv .= "\n";
    }
    $rv .= "include => rly\n";
    //$rv .= "include => outgoing\n";
    $rv .= "\n";
    return $rv;
}


function get_regis_exten_code($regis, $reg_arr) 
{
    $rv = "";
    foreach($reg_arr['icoms'] as $icom_name => $icom) {
        $rv .= get_icom_exten_code($icom_name, $icom);
    }
    return $rv;
}

function _get_rly_local_exten_helper($phone_name, $phone_val)
{
    global $rly_std_code, $parse_tree;
    $pstn_std_code = "";
    if (isset($parse_tree['pstn_std_code'])) {
        $pstn_std_code = $parse_tree['pstn_std_code'];
    } 

    $rv = "";
    $rly_no = $phone_val['unique_no'];
    $secy_no = (isset($phone_val['secy_no']))?$phone_val['secy_no']:'';
    $secy_type = (isset($phone_val['secy_type']))?$phone_val['secy_type']:'default';

    $rv .= "exten => $rly_no, 1, GoSub(dial_rly_local,s,1($rly_no,$secy_no,$secy_type,yes))\n";
    $rv .= "exten => t$rly_no, 1, GoSub(dial_rly_local,s,1($rly_no,$secy_no,$secy_type,no))\n";
    $rv .= "exten => ${rly_std_code}$rly_no, 1, Goto(rly,$rly_no,1)\n";
    /* Check for Byte Icom */
    if(isset($phone_val['bnum'])) {
        /* This phone is part of byte intercom. Add a byte extension. */
        $rv .= "exten => byte-$rly_no, 1, Dial(SIP/$rly_no, 60, tT)\n";
        $rv .= "\tsame => n, Hangup\n";
    }
    if(isset($phone_val['pstn']) and ($phone_val['pstn'] != "-1")) {
        $pstn_no = $phone_val['pstn'];
        $rv .= "exten => $pstn_no, 1, Goto(rly,$rly_no,1)\n";
        $rv .= "exten => ${pstn_std_code}$pstn_no, 1, Goto(rly,$rly_no,1)\n";
    }
    $rv .= "\n";
    return $rv;
}

function _get_rly_remote_helper($phone_name, $phone_val, $sip_trunk, $sip_trunk_backup, $doclip = true)
{
    global $rly_std_code;
    $rv = "";
    $rly_no = $phone_val['unique_no'];

    if ($doclip) {
        $rv .= "exten => $rly_no, 1, Set(CALLERID(all)=\${CLI_RLY})\n";
    } else {
        $rv .= "exten => $rly_no, 1, Noop\n";
    }
    $rv .= "\tsame => n, GoSub(dial_rly_remote,s,1(t$rly_no,$sip_trunk,$sip_trunk_backup))\n";
    $rv .= "exten => ${rly_std_code}$rly_no, 1, Goto(rly,$rly_no,1)\n";
    if(isset($phone_val['bnum'])) {
        /* This phone is part of byte intercom. Add a byte extension. */
        //$rv .= "exten => byte-$rly_no, 1, Dial(SIP/$sip_trunk/byte-$rly_no, 60, tT)\n";
        $rv .= "exten => byte-$rly_no, 1, GoSub(dial_rly_remote,s,1(byte-$rly_no,$sip_trunk,$sip_trunk_backup))\n";
        $rv .= "\tsame => n, Hangup\n";
    }
    $rv .= "\n";
    return $rv;
}

function get_rly_exten_code($regis_name, $doclip=true)
{
    global $registrars, $parse_tree;
    global $rly_std_code;
 
    $rv = "[rly]\n";
    foreach($registrars as $regis => $reg) {
        $local = ($regis_name == $regis)?true:false;
        foreach($reg['icoms'] as $icom_name => $icoms) {
            foreach($icoms['phones'] as $ph => $phv) {
                $rv .= ";$ph - " . $phv['disp_name'] .  "\n";
                if ($local) {
                    $rv .= _get_rly_local_exten_helper($ph, $phv);
                } else {
                    $sip_trunk = "sip-$regis";
                    $sip_trunk_backup = '';
                    /* Do we have a backup registrar for $regis */
                    if($parse_tree['bregs'][$regis]['ip_backup'] != "-1") {
                        $sip_trunk_backup = "sip-$regis-backup";
                    }
                    $rv .= _get_rly_remote_helper($ph, $phv, $sip_trunk, $sip_trunk_backup, $doclip);
                }
            }
        }
    }
    $rv .= "\ninclude => outgoing\n\n";
    return $rv;
}

// Non functional code 
function get_rly_exten_code_($regis_name)
{
    global $registrars;
    global $rly_std_code;
 
    $rv = "[rly]\n";
    foreach($registrars as $regis => $reg) {
        $local = ($regis_name == $regis)?true:false;
        foreach($reg['icoms'] as $icom_name => $icoms) {
            foreach($icoms['phones'] as $ph => $phv) {
                $rly_no = $phv['unique_no'];

                $secy_no = (isset($phv['secy_no']))?$phv['secy_no']:'';
                $secy_type = (isset($phv['secy_type']))?$phv['secy_type']:'default';
                if ($local) {
                    $rv .= "exten => $rly_no, 1, GoSub(dial_rly_local,s,1($rly_no,$secy_no,$secy_type,yes))\n";
                    $rv .= "exten => t$rly_no, 1, GoSub(dial_rly_local,s,1($rly_no,$secy_no,$secy_type,no))\n";
                    $rv .= "exten => ${rly_std_code}$rly_no, 1, Goto(rly,$rly_no,1)\n";
                    /* Check for Byte Icom */
                    if(isset($phv['bnum'])) {
                        /* This phone is part of byte intercom. Add a byte extension. */
                        $rv .= "exten => byte-$rly_no, 1, Dial(SIP/$rly_no, 60, tT)\n";
                        $rv .= "\tsame => n, Hangup\n";
                    }
                    $rv .= "\n";
                } else {
                    $sip_trunk = "sip-$regis";
                    $rv .= "exten => $rly_no, 1, Set(CALLERID(all)=\${CLI_RLY})\n";
                    $rv .= "\tsame => n, GoSub(dial_rly_remote,s,1(t$rly_no,$sip_trunk,))\n";
                    $rv .= "exten => ${rly_std_code}$rly_no, 1, Goto(rly,$rly_no,1)\n";
                    if(isset($phv['bnum'])) {
                        /* This phone is part of byte intercom. Add a byte extension. */
                        $rv .= "exten => byte-$rly_no, 1, Dial(SIP/$sip_trunk/byte-$rly_no, 60, tT)\n";
                        $rv .= "\tsame => n, Hangup\n";
                    }
                    $rv .= "\n";
                }
            }
        }
    }
    $rv .= "\n";
    return $rv;
}

function __get_exten_code() 
{
    global $registrars, $parse_tree;
    foreach($registrars as $regis => $reg) {
        $rv = "";
        $filename = "$regis-" . $reg['ip'] . "-extensions.conf";
        $fout = fopen($filename, "w");
        $rv .= "; extensions.conf for $regis <" . $reg['ip'] . ">\n\n";
        $rv .= emit_fixed_code();
        $rv .= get_regis_exten_code($regis, $reg);
        $rv .= get_rly_exten_code($regis);

        $rv .= get_byte_exten_code($regis);
        $rv .= get_conf_exten_code($regis);
        $rv .= get_map_exten_code();
        $rv .= generate_route_extens($regis, true);
        fwrite($fout, $rv);
        fclose($fout);
    }
    
}
?>
