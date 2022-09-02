<?php

function do_boss_secy_action($icom_name, $boss, $secy, $type) {
    DEBUG("In do_boss_secy_action(). Icom: $icom_name, Boss: $boss, Secy: $secy");
    global $registrars;
    
    foreach($registrars as $rname => $regis) {
        foreach($regis['icoms'] as $iname => $icom) {
            if ($iname == $icom_name) {
                DEBUG("In do_boss_secy_action(): Found Icom: $iname");
                $registrars[$rname]['icoms'][$icom_name]['boss_secy'][$boss]['secy'] = $secy;
                $registrars[$rname]['icoms'][$icom_name]['boss_secy'][$boss]['type'] = $type;

                /* New Code */
                
                $registrars[$rname]['icoms'][$icom_name]['phones'][$boss]['secy'] = $secy;
                $registrars[$rname]['icoms'][$icom_name]['phones'][$boss]['secy_no'] = get_phone_info($secy)[1];
                $registrars[$rname]['icoms'][$icom_name]['phones'][$boss]['secy_type'] = $type;
            }
        }
    }
}

function do_boss_secy($l) {
    DEBUG("In do_boss_secy(): $l");

    match_token("boss_secy");

    $boss = get_token();
    phone_should_exist($boss);
    match_token("(");

    $icom = get_token();
    match_token(",");
   icom_should_exist($icom);

    $type = get_token();
    match_token(",");
    if (($type != "default") and ($type != "only_secy")) {
        Error("Unknown boss_secy tyep $type.");
    }

    $secy = get_token();
    phone_should_exist($secy);
    match_token(")");

    if ($boss == $secy) {
        Error("The Boss and Secy cannot be same!");
    }
    
    DEBUG("In go_boss_secy(): Boss: $boss, Icom: $icom, Secy: $secy, type: $type");
    do_boss_secy_action($icom, $boss, $secy, $type);
}


?>