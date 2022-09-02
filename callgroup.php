<?php

function set_callgroup($name, $icom_name, $phone) 
{
    global $registrars;
    foreach ($registrars as $regis => $reg) {
        foreach($reg['icoms'] as $icname => $icom) {
            if($icname == $icom_name) {
                foreach($icom['phones'] as $ph => $phv) {
                    if ($ph == $phone) {
                        $registrars[$regis]['icoms'][$icname]['phones'][$ph]['callgroup'] = $name;
                        $registrars[$regis]['icoms'][$icname]['phones'][$ph]['pickupgroup'] = $name;
                        return;
                    }
                }
                return;
            }
        }
    }
}

function do_callgroup_action($name, $icom_name, $phones)
{
    foreach($phones as $ph) {
        set_callgroup($name, $icom_name, $ph);
    }
}
 
function do_callgroup()
{
    $c = ",";
    match_token("callgroup");
    $name = get_token();
    match_token("(");
    $icom_name = get_token();
    match_token($c);
    $phone[] = get_token();
    match_token($c);
    $phone[] = get_token();
    
    while(lookup() == $c) {
        match_token($c);
        $phone[] = get_token();
    }

    match_token(")");

    do_callgroup_action($name, $icom_name, $phone);

}
?>