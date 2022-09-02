<?php

function _get_attended_conf_exten_code($val, $islocal) 
{
    $rv = "";
    $conf_no = $val['rly_no'];
    $icom_no = $val['icom_no'];
    $conf_name = $val['name'];
    if ($islocal) {
        $rv .= "exten => ${conf_no}/${icom_no}, 1, Confbridge($conf_name)\n";
    }
    return $rv;
}

function _get_default_conf_exten_code($val, $islocal) 
{
    global $rly_std_code;
    $rv = "";
    if($islocal) {
        $rv .= _get_conf_local_exten_code_helper($val['name'], $val['rly_no'], $val['admin_no']);
    } else {
        $conf_no = $val['rly_no'];
        $sip_trunk = "sip-" . $val['regis'];
        $rv .= "exten => " . $rly_std_code . $conf_no . ", 1, Goto(rly," . $conf_no . ",1)\n"; 
        $rv .= "exten => $conf_no,1,Set(CALLERID(all)=\${CLI_RLY})\n";
        $rv .= "\tsame => n, Dial(SIP/$sip_trunk/conf-${conf_no})\n";
    } 
    return $rv;
}

function get_conf_exten_code($regis)
{
    global $registrars, $parse_tree;
    $rv = "[rly]\n";
    foreach($parse_tree['conf'] as $conf => $val) {
        $islocal = ($val['regis'] == $regis);
        switch($val['type']) {
        case "attended":
            $rv .= _get_attended_conf_exten_code($val, $islocal);
            break;
        case "default":
            $rv .= _get_default_conf_exten_code($val, $islocal); 
            break;
        }
    }
    return $rv;
}            


function _get_conf_local_exten_code_helper($conf_name, $conf_rly_no, $conf_admin_no)
{
    $rv = "";

    $rv .= "[rly]\n";
    $rv .= "exten => $conf_rly_no, 1, Set(CALLERID(all)=\${CLI_RLY})\n";
    $rv .= "\tsame => n, Goto(rly,conf-$conf_rly_no,1)\n";
    //$rv .= "exten => t$conf_rly_no, 1, GoSub(dial_rly_local,s,1(conf-$conf_rly_no,,,no))\n";
    $rv .= "exten => conf-$conf_rly_no, 1, Answer\n";
    //$rv .= <<<END
    $rv .= "\tsame => n, Playback(conf-getpin)\n";
    $rv .= "\tsame => n, Read(pin,,4)\n";
    $rv .= "\tsame => n, Noop(\${pin})\n";
    $rv .= "\tsame => n, Set(Dbpin=\${DB(conf/" . $conf_name . ")})\n";
    $rv .= "\t" . 'same => n, GotoIf($["${Dbpin}" != ""]?continue)' . "\n";
    $rv .= "\tsame => n, Set(Dbpin=0000)\n";
    $rv .= "\tsame => n(continue), Noop(\${Dbpin})\n";
    $rv .= "\t" . 'same => n, GotoIf($["${Dbpin}" != "${pin}"]?error)' . "\n";
    $rv .= "\tsame => n, Confbridge($conf_name)\n";
    $rv .= "\tsame => n, Hangup\n";
    $rv .= "\tsame => n(error), Playback(conf-invalidpin)\n";
    $rv .= "\tsame => n, Hangup\n";
    
    $rv .= "\nexten => playpin-${conf_admin_no}, 1, Answer\n";
    $rv .= "\tsame => n, Set(PIN=\${DB(conf/" . $conf_name . ")})\n";
    $rv .= "\t" . 'same => n, GotoIf($["X${PIN}" != "X"]?play)' . "\n";
    $rv .= "\tsame => n, Set(PIN=0000)\n";
    $rv .= "\tsame => n(play), SayDigits(\${PIN})\n";
    $rv .= "\tsame => n, Hangup\n";
    
    $rv .= "exten => setpin-$conf_admin_no, 1, GoSub(change-conf-pin,s,1($conf_name,\${EXTEN:7}))\n\n";
    return $rv;
}


function check_conf_type($conf_type) {
    switch($conf_type) {
    case "default":
    case "attended":
        break;
    default:
        Error("Unknown conference type $conf_type!");
        break;
    }
}

function do_conference_action($conf, $regis, $rly_no, $admin_phone, $conf_type)
{
    global $parse_tree;
    if(isset($parse_tree['conf'][$conf])) {
        Error("Conference $cong already defined at line No. " . $parse_tree['conf'][$conf]['line_no']);
    }

    $parse_tree['conf'][$conf] = array();
    $parse_tree['conf'][$conf]['line_no'] = get_current_line_no();
    $parse_tree['conf'][$conf]['regis'] = $regis;
    $parse_tree['conf'][$conf]['type'] = $conf_type;
    $parse_tree['conf'][$conf]['rly_no'] = $rly_no;
    $parse_tree['conf'][$conf]['name'] = $conf;
    $parse_tree['conf'][$conf]['admin_no'] = get_phone_info($admin_phone)[1];
    $parse_tree['conf'][$conf]['icom_no'] = get_phone_info($admin_phone)[4];
    $parse_tree['conf'][$conf]['icom_name'] = get_phone_info($admin_phone)[5];
    $parse_tree['conf'][$conf]['admin_phone'] = $admin_phone;
    
}

function do_conference()
{
    $c = ",";
    $conf_type = "default";
    match_token("conference");
    $conf = get_token();
    match_token("(");
    $regis = get_token();
    registrar_should_exist($regis);
    match_token($c);
    $rly_no = get_token();
    register_rly_no($rly_no);
    match_token($c);
    $admin_phone = get_token();
    phone_should_exist($admin_phone);
    if(lookup() == $c) {
        match_token($c);
        $conf_type = get_token();
        check_conf_type($conf_type);
    } 
    match_token(")");

    do_conference_action($conf, $regis, $rly_no, $admin_phone, $conf_type);
}
?>